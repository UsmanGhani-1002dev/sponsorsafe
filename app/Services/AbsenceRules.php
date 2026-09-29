<?php

namespace App\Services;

use App\Enums\AbsenceType;
use App\Models\Business;
use DateTimeImmutable;

/**
 * Absence rules: compliance-rules.md §3. Pure logic over working days, so every rule is unit-tested.
 *
 * - Unpaid limit: unpaid + unauthorised working days in the leave year above (limit weeks × working days per
 *   week). Trigger = the working day on which the limit is crossed.
 * - Unauthorised streak: N consecutive working days (weekends and bank holidays do not break it; separate
 *   records that follow on count as one streak). Trigger = the Nth day.
 * - Deadline = trigger + worker deadline (working days). Reporting applies to sponsored workers only.
 */
class AbsenceRules
{
    private const DEFAULTS = [
        'unpaid_limit_weeks' => 4,
        'unpaid_leave_year' => 'calendar',
        'unauthorised_trigger_days' => 10,
        'worker_report_deadline_days' => 10,
        'exempt_absence_types' => ['sick_self', 'sick_fit', 'family', 'jury'],
        'self_cert_max_days' => 7,
    ];

    private array $rules;

    public function __construct(private WorkingDays $wd, array $rules = [])
    {
        $this->rules = [...self::DEFAULTS, ...array_intersect_key($rules, self::DEFAULTS)];
    }

    public static function forBusiness(Business $business): self
    {
        return new self(WorkingDays::fromDatabase(), collect(array_keys(self::DEFAULTS))->mapWithKeys(fn ($k) => [$k => $business->rule($k)])->all());
    }

    /**
     * @param  iterable<array{type: AbsenceType|string, start: string, end: string, label?: string}>  $existing  the employee's other absences
     */
    public function check(AbsenceType $type, string $start, string $end, float $daysPerWeek, bool $sponsored, iterable $existing = []): AbsenceCheck
    {
        if ($start === '' || $end === '' || $end < $start) {
            return new AbsenceCheck(AbsenceCheck::INVALID, 'Check the dates', 'The last day must be on or after the first day.');
        }
        $days = $this->wd->between($start, $end);
        if (! $days) {
            return new AbsenceCheck(AbsenceCheck::INVALID, 'No working days', 'These dates are all weekends or bank holidays, so there is nothing to record.');
        }

        $others = [];
        foreach ($existing as $a) {
            $a['type'] = $a['type'] instanceof AbsenceType ? $a['type'] : AbsenceType::from($a['type']);
            if ($a['start'] <= $end && $a['end'] >= $start) {
                return new AbsenceCheck(AbsenceCheck::INVALID, 'Overlaps another absence',
                    'This overlaps '.mb_strtolower($a['type']->label()).' from '.self::fmt($a['start']).' to '.self::fmt($a['end']).'. Change the dates or remove the other entry first.');
            }
            $others[] = $a;
        }

        $warnings = $this->warnings($type, $start, $end, $sponsored);
        $n = count($days);

        $result = match (true) {
            $type === AbsenceType::Unauthorised => $this->unauthorised($days, $others, $daysPerWeek),
            $type === AbsenceType::Unpaid => $this->unpaid($days, $others, $daysPerWeek),
            default => null,
        };
        if (! $result) {
            $detail = $type->label().' is not reportable. It is kept in the absence log as evidence of attendance.';

            return new AbsenceCheck(AbsenceCheck::NONE, 'No report needed', $detail, $n, warnings: $warnings);
        }
        // Reporting duties apply to sponsored workers only; everyone else is logged, never reported.
        if (! $sponsored) {
            return new AbsenceCheck(AbsenceCheck::NONE, 'No report needed', 'Not a sponsored worker, so this is kept in the absence log and never reported.', $n, warnings: $warnings);
        }
        [$status, $title, $detail, $trigger, $event, $extra] = $result;
        $warnings = [...$extra, ...$warnings];
        if ($status !== AbsenceCheck::REPORT) {
            return new AbsenceCheck($status, $title, $detail, $n, warnings: $warnings);
        }

        return new AbsenceCheck(AbsenceCheck::REPORT, 'Report to Home Office', $detail, $n, $trigger,
            $this->wd->add($trigger, (int) $this->rules['worker_report_deadline_days']), $event, $warnings);
    }

    /** Unpaid + unauthorised working days already used in the leave year containing $date, and the limit. */
    public function unpaidUsage(string $date, float $daysPerWeek, iterable $absences): array
    {
        $used = 0;
        $from = $this->windowStart($date);
        foreach ($this->unpaidDays($absences) as $d) {
            $used += (int) ($d >= $from && $d <= $date);
        }

        return ['used' => $used, 'limit' => $this->limit($daysPerWeek)];
    }

    private function unpaid(array $days, array $others, float $daysPerWeek): array
    {
        $limit = $this->limit($daysPerWeek);
        $all = array_unique([...$this->unpaidDays($others), ...$days]);
        sort($all);
        $inWindow = function (string $d) use ($all) {
            $from = $this->windowStart($d);

            return count(array_filter($all, fn ($x) => $x >= $from && $x <= $d));
        };

        $first = $days[0];
        $already = $inWindow($first) - 1; // unpaid days before this absence in the same leave year
        $year = $this->rules['unpaid_leave_year'] === 'rolling' ? 'the 12 months to '.self::fmt(end($days)) : substr($first, 0, 4);
        $n = count($days);
        $total = $already + $n;
        $limitText = self::num($limit).'-day limit ('.self::num($this->rules['unpaid_limit_weeks']).' weeks)';

        if ($already > $limit) {
            return [AbsenceCheck::NOT_YET, 'Already over the limit', "Unpaid days in {$year} were already over the {$limitText} before this absence. It should already have been reported.", null, null,
                ['Already over the unpaid limit for this year. Check the earlier report was made and whether sponsorship must stop.']];
        }
        foreach ($days as $d) {
            if ($inWindow($d) > $limit) {
                return [AbsenceCheck::REPORT, 'Report to Home Office',
                    "Unpaid days in {$year}: {$already} already taken + {$n} in this absence = {$total}, over the {$limitText}. The limit is crossed on ".self::fmt($d).'. Consider whether sponsorship must stop.',
                    $d, 'Unpaid leave over '.self::num($this->rules['unpaid_limit_weeks']).' weeks in '.$year, []];
            }
        }

        $left = self::num($limit - $total);

        return [AbsenceCheck::NOT_YET, 'No report needed', "Unpaid days in {$year}: {$already} + {$n} = {$total} of ".self::num($limit).". {$left} days left before a report is required.", null, null, []];
    }

    private function unauthorised(array $days, array $others, float $daysPerWeek): array
    {
        $needed = (int) $this->rules['unauthorised_trigger_days'];
        $set = array_flip([...$this->typeDays($others, AbsenceType::Unauthorised), ...$days]);
        $streak = function (string $d) use ($set) {
            $count = 0;
            while (isset($set[$d])) {
                $count++;
                $d = $this->wd->previous($d);
            }

            return $count;
        };

        $before = $streak($days[0]) - 1; // consecutive unauthorised days straight before this entry
        if ($before >= $needed) {
            return [AbsenceCheck::NOT_YET, 'Streak already reported', "This continues a streak that reached {$needed} consecutive working days before this entry.", null, null,
                ['The streak passed '.$needed.' working days before this entry. Check it was reported.']];
        }
        foreach ($days as $d) {
            if ($streak($d) === $needed) {
                return [AbsenceCheck::REPORT, 'Report to Home Office',
                    "{$needed} consecutive working days of unauthorised absence. The threshold is reached on ".self::fmt($d).'.',
                    $d, "Unauthorised absence reached {$needed} consecutive working days", $this->unpaidCheck($days, $others, $daysPerWeek)];
            }
        }
        $now = $streak(end($days));

        return [AbsenceCheck::NOT_YET, 'No report yet',
            "{$now} of {$needed} consecutive working days. Keep trying to contact the employee and note each attempt. A report is needed on day {$needed}.",
            null, null, $this->unpaidCheck($days, $others, $daysPerWeek)];
    }

    /** Unauthorised days also count towards the unpaid limit: warn if they cross it. */
    private function unpaidCheck(array $days, array $others, float $daysPerWeek): array
    {
        [$status, , , $trigger] = $this->unpaid($days, $others, $daysPerWeek);

        return $status === AbsenceCheck::REPORT ? ['This also takes unpaid and unauthorised days over the yearly limit on '.self::fmt($trigger).'.'] : [];
    }

    private function warnings(AbsenceType $type, string $start, string $end, bool $sponsored): array
    {
        $out = [];
        $calendarDays = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
        $max = (int) $this->rules['self_cert_max_days'];
        if ($type === AbsenceType::SickSelf && $calendarDays > $max) {
            $out[] = "Self-certification only covers up to {$max} days. Choose \"Sickness – fit note\" and upload the fit note.";
        }
        if ($type === AbsenceType::SickFitNote) {
            $out[] = 'Upload the fit note (file only, no medical details).';
        }
        if ($sponsored && $type->mayReducePay() && ! in_array($type->value, (array) $this->rules['exempt_absence_types'], true)) {
            $out[] = 'If this leave is paid below the salary on the CoS, reduced pay may be a reportable salary change.';
        }

        return $out;
    }

    private function limit(float $daysPerWeek): float
    {
        return (float) $this->rules['unpaid_limit_weeks'] * $daysPerWeek;
    }

    /** First day of the leave year that $date falls in. */
    private function windowStart(string $date): string
    {
        return $this->rules['unpaid_leave_year'] === 'rolling'
            ? (new DateTimeImmutable($date))->modify('-1 year +1 day')->format('Y-m-d')
            : substr($date, 0, 4).'-01-01';
    }

    private function unpaidDays(iterable $absences): array
    {
        $out = [];
        foreach ($absences as $a) {
            $type = $a['type'] instanceof AbsenceType ? $a['type'] : AbsenceType::from($a['type']);
            if ($type->countsTowardsUnpaidLimit()) {
                array_push($out, ...$this->wd->between($a['start'], $a['end']));
            }
        }

        return $out;
    }

    private function typeDays(array $absences, AbsenceType $type): array
    {
        $out = [];
        foreach ($absences as $a) {
            if ($a['type'] === $type) {
                array_push($out, ...$this->wd->between($a['start'], $a['end']));
            }
        }

        return $out;
    }

    private static function fmt(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j M Y');
    }

    private static function num(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');
    }
}
