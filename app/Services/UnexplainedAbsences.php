<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Business;
use App\Models\ClockIn;
use App\Models\Employee;
use App\Models\UnexplainedAbsence;
use App\Models\User;
use App\Services\ClockIns\ClockInSource;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Unexplained absences (compliance-rules §11). The absence log stays the compliance record; clock-in data is
 * used for one check only: a scheduled working day with no clock-in and no absence entry becomes an alert,
 * which the admin classifies (record the absence, or "Worked – clock-in missed").
 *
 * A day is only checked when the business has clock-in data for it, so a missing import never floods the
 * dashboard. The check is off until the business switches it on (Settings → Clock-in check).
 */
class UnexplainedAbsences
{
    private const MAX_ROWS = 20000;

    public function __construct(private ClockInSource $source) {}

    public static function enabled(Business $business): bool
    {
        return (bool) $business->rule('clock_in_check');
    }

    /**
     * A clock-in system's CSV export: columns "email" and "date" (and optionally "time"), one row per clock-in.
     * Dates as 2026-10-05 or 05/10/2026. Each day in the file is then checked.
     *
     * @return array{imported: int, days: int, alerts: int, skipped: list<string>}
     */
    public function importCsv(Business $business, UploadedFile $file, User $by): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $header, true))->first(fn ($i) => $i !== false);
        [$emailCol, $dateCol, $timeCol] = [$col(['email', 'email address']), $col(['date', 'day']), $col(['time', 'clock in', 'clock-in', 'first in', 'in'])];
        if ($emailCol === null || $dateCol === null) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The file needs an "email" column and a "date" column in its first row.']);
        }

        $rows = [];
        $skipped = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false && $line <= self::MAX_ROWS) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $date = self::parseDate((string) ($cells[$dateCol] ?? ''));
            if (! $date) {
                $skipped[] = "Row {$line}: the date \"".trim((string) ($cells[$dateCol] ?? ''))."\" was not recognised.";

                continue;
            }
            $rows[] = ['email' => (string) ($cells[$emailCol] ?? ''), 'date' => $date, 'time' => $timeCol !== null ? trim((string) ($cells[$timeCol] ?? '')) ?: null : null, 'line' => $line];
        }
        fclose($handle);

        [$imported, $unknown, $days] = $this->store($business, $rows, 'csv');
        foreach ($unknown as $row) {
            $skipped[] = "Row {$row['line']}: no employee with the email \"{$row['email']}\".";
        }
        $alerts = $days->sum(fn (string $d) => $this->scan($business, CarbonImmutable::parse($d)));
        Audit::log('clock_ins.imported', $business, ['rows' => $imported, 'days' => $days->count(), 'skipped' => count($skipped)], $by);

        return ['imported' => $imported, 'days' => $days->count(), 'alerts' => $alerts, 'skipped' => array_slice($skipped, 0, 20)];
    }

    /**
     * Nightly (`absences:check-clock-ins`, 20:00): fetch today's clock-ins from the connected source (none yet)
     * and check today for every business that has the check on. Returns [businesses checked, new alerts].
     *
     * @return array{int, int}
     */
    public function nightly(): array
    {
        [$checked, $alerts] = [0, 0];
        $today = CarbonImmutable::today();
        Business::where('status', Business::ACTIVE)->orderBy('id')->each(function (Business $business) use ($today, &$checked, &$alerts) {
            if (! self::enabled($business)) {
                return;
            }
            $rows = collect($this->source->fetch($business, $today))->map(fn ($r) => [...$r, 'date' => $today->format('Y-m-d'), 'line' => 0])->all();
            $this->store($business, $rows, 'integration');
            $alerts += $this->scan($business, $today);
            $checked++;
        });

        return [$checked, $alerts];
    }

    /**
     * Check one day: every employee scheduled to work, with no clock-in and no absence covering the day,
     * gets an open alert (once). Skipped when the check is off or there is no clock-in data for that day.
     */
    public function scan(Business $business, CarbonInterface $date): int
    {
        $day = $date->format('Y-m-d');
        if (! self::enabled($business) || $day > today()->format('Y-m-d')
            || ! ClockIn::where('business_id', $business->id)->whereDate('date', $day)->exists()) {
            return 0;
        }
        $wd = WorkingDays::fromDatabase();
        $clockedIn = ClockIn::where('business_id', $business->id)->whereDate('date', $day)->pluck('employee_id')->flip();
        $absent = Absence::where('business_id', $business->id)->whereDate('start_date', '<=', $day)->whereDate('end_date', '>=', $day)->pluck('employee_id')->flip();
        $flagged = UnexplainedAbsence::where('business_id', $business->id)->whereDate('date', $day)->pluck('employee_id')->flip();

        $new = 0;
        $business->employees()
            ->whereDate('start_date', '<=', $day)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhereDate('ended_on', '>=', $day))
            ->get(['id', 'business_id', 'work_days', 'start_date', 'ended_on'])
            ->each(function (Employee $e) use ($date, $day, $wd, $clockedIn, $absent, $flagged, $business, &$new) {
                if ($clockedIn->has($e->id) || $absent->has($e->id) || $flagged->has($e->id) || ! $e->scheduledOn(CarbonImmutable::parse($day), $wd)) {
                    return;
                }
                UnexplainedAbsence::create(['business_id' => $business->id, 'employee_id' => $e->id, 'date' => $day]);
                $new++;
            });

        return $new;
    }

    /** "Worked – clock-in missed": nothing to record. */
    public function markWorked(UnexplainedAbsence $alert, User $by): void
    {
        $this->resolve($alert, UnexplainedAbsence::WORKED, null, $by);
        Audit::log('unexplained_absence.worked', $alert, ['employee_id' => $alert->employee_id, 'date' => $alert->date->format('Y-m-d')], $by);
    }

    /** Called by AbsenceRecorder: an absence covering an open alert's day classifies it. */
    public function resolveCoveredBy(Absence $absence, User $by): void
    {
        UnexplainedAbsence::open()->where('employee_id', $absence->employee_id)
            ->whereDate('date', '>=', $absence->start_date->format('Y-m-d'))->whereDate('date', '<=', $absence->end_date->format('Y-m-d'))
            ->get()->each(fn (UnexplainedAbsence $a) => $this->resolve($a, UnexplainedAbsence::ABSENCE, $absence, $by));
    }

    /** Open alerts for the dashboard, oldest first. */
    public function open(Business $business): Collection
    {
        return UnexplainedAbsence::open()->where('business_id', $business->id)->with('employee:id,full_name')->orderBy('date')->get();
    }

    /**
     * Save clock-ins (earliest time per person per day). Returns [rows stored, rows with an unknown email, days covered].
     *
     * @return array{int, list<array>, Collection<int, string>}
     */
    private function store(Business $business, array $rows, string $source): array
    {
        $byEmail = $business->employees()->get(['id', 'email'])->keyBy(fn (Employee $e) => mb_strtolower(trim((string) $e->email)));
        $stored = 0;
        $unknown = [];
        $days = collect();
        foreach ($rows as $row) {
            $employee = $byEmail->get(mb_strtolower(trim($row['email'])));
            if (! $employee) {
                $unknown[] = $row;

                continue;
            }
            $time = self::parseTime($row['time'] ?? null);
            $existing = ClockIn::where('employee_id', $employee->id)->whereDate('date', $row['date'])->first();
            if ($existing) {
                if ($time && (! $existing->first_in || $time < $existing->first_in)) {
                    $existing->update(['first_in' => $time]);
                }
            } else {
                ClockIn::create(['business_id' => $business->id, 'employee_id' => $employee->id, 'date' => $row['date'], 'first_in' => $time, 'source' => $source, 'imported_at' => now()]);
            }
            // A clock-in that arrives late clears the alert for that day.
            UnexplainedAbsence::open()->where('employee_id', $employee->id)->whereDate('date', $row['date'])->get()
                ->each(fn (UnexplainedAbsence $a) => $this->resolve($a, UnexplainedAbsence::WORKED, null, null));
            $stored++;
            $days->push($row['date']);
        }

        return [$stored, $unknown, $days->unique()->sort()->values()];
    }

    private function resolve(UnexplainedAbsence $alert, string $status, ?Absence $absence, ?User $by): void
    {
        $alert->update(['status' => $status, 'absence_id' => $absence?->id, 'resolved_by' => $by?->id, 'resolved_at' => now()]);
    }

    private static function parseDate(string $value): ?string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'd/m/y'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($d && $d->format($format) === $value) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private static function parseTime(?string $value): ?string
    {
        if (! $value || ! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($value), $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $m[1], $m[2]);
    }
}
