<?php

namespace App\Support;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\BankHoliday;
use App\Models\Business;
use App\Models\Employee;
use App\Services\AbsenceRules;
use App\Services\WorkingDays;
use Carbon\CarbonImmutable;

/**
 * Absence → Calendar: one month, one row per person employed that month, one cell per day. Coloured by kind
 * (leave, sickness, unpaid, unauthorised); the person's usual days off and days outside their employment are shaded.
 * Sponsored workers also show their unpaid days against the limit for the leave year (compliance-rules §3).
 */
class AbsenceCalendar
{
    public const KINDS = ['leave' => 'Leave', 'sick' => 'Sickness', 'unpaid' => 'Unpaid leave', 'unauthorised' => 'Unauthorised'];

    public static function kind(AbsenceType $type): string
    {
        return match (true) {
            $type->isSickness() => 'sick',
            $type === AbsenceType::Unpaid => 'unpaid',
            $type === AbsenceType::Unauthorised => 'unauthorised',
            default => 'leave',
        };
    }

    /** "2026-11" → the first of that month; anything else → this month. */
    public static function parseMonth(?string $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^(20\d\d)-(0[1-9]|1[0-2])$/', $value, $m)) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1);
        }

        return CarbonImmutable::today()->startOfMonth();
    }

    public function month(Business $business, CarbonImmutable $month): array
    {
        $start = $month->startOfMonth();
        $end = $month->endOfMonth()->startOfDay();
        $today = CarbonImmutable::today();
        $wd = WorkingDays::fromDatabase();
        $rules = AbsenceRules::forBusiness($business);

        $holidays = BankHoliday::where('division', 'england-and-wales')->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('title', 'date')->mapWithKeys(fn ($title, $date) => [substr((string) $date, 0, 10) => $title]);

        $days = [];
        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $days[] = [
                'date' => $d->format('Y-m-d'),
                'day' => $d->day,
                'weekday' => $d->format('D'),
                'weekend' => $d->isWeekend(),
                'bankHoliday' => $holidays[$d->format('Y-m-d')] ?? null,
                'today' => $d->equalTo($today),
                'label' => $d->format('D j M Y'),
            ];
        }

        // Everyone employed at some point in the month; absences reach back a year for the unpaid limit.
        $people = $business->employees()
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $end->format('Y-m-d')))
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $start->format('Y-m-d')))
            ->with(['absences' => fn ($q) => $q->where('start_date', '<=', $end->format('Y-m-d'))->where('end_date', '>=', $start->subYear()->format('Y-m-d'))->orderBy('start_date')])
            ->orderBy('full_name')
            ->get();

        return [
            'month' => [
                'value' => $start->format('Y-m'),
                'label' => $start->format('F Y'),
                'prev' => $start->subMonth()->format('Y-m'),
                'next' => $start->addMonth()->format('Y-m'),
                'isCurrent' => $start->equalTo($today->startOfMonth()),
            ],
            'days' => $days,
            'people' => $people->map(fn (Employee $e) => $this->row($e, $start, $end, $wd, $rules))->values()->all(),
            'kinds' => self::KINDS,
        ];
    }

    private function row(Employee $e, CarbonImmutable $start, CarbonImmutable $end, WorkingDays $wd, AbsenceRules $rules): array
    {
        $inMonth = $e->absences->filter(fn (Absence $a) => $a->end_date->gte($start));
        $cells = [];
        $counts = array_fill_keys(array_keys(self::KINDS), 0);

        for ($d = $start; $d <= $end; $d = $d->addDay()) {
            $date = $d->format('Y-m-d');
            if (($e->start_date && $d->lt($e->start_date)) || ($e->ended_on && $d->gt($e->ended_on))) {
                $cells[] = ['state' => 'out'];

                continue;
            }
            $scheduled = $e->scheduledOn($d, $wd);
            $absence = $inMonth->first(fn (Absence $a) => $date >= $a->start_date->format('Y-m-d') && $date <= $a->end_date->format('Y-m-d'));
            if ($absence) {
                $kind = self::kind($absence->type);
                $counts[$kind] += (int) $scheduled;
                $cells[] = ['state' => 'absence', 'kind' => $kind, 'scheduled' => $scheduled, 'title' => $absence->type->label().' · '.$absence->dates()];
            } else {
                $cells[] = ['state' => $scheduled ? 'work' : 'off'];
            }
        }

        $unpaid = null;
        if ($e->isSponsored()) {
            $usage = $rules->unpaidUsage($end->format('Y-m-d'), (float) $e->days_per_week, $e->absences->map->forRules());
            $unpaid = [
                'text' => 'Unpaid '.$usage['used'].' of '.rtrim(rtrim(number_format($usage['limit'], 1, '.', ''), '0'), '.'),
                'tone' => $usage['used'] > $usage['limit'] ? 'red' : ($usage['used'] >= $usage['limit'] * 0.75 ? 'amber' : 'grey'),
            ];
        }

        return [
            'id' => $e->id,
            'name' => $e->full_name,
            'sponsored' => $e->isSponsored(),
            'current' => $e->ended_on === null,
            'unpaid' => $unpaid,
            'counts' => array_filter($counts),
            'cells' => $cells,
        ];
    }
}
