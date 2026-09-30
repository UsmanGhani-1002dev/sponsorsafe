<?php

namespace App\Support;

use App\Enums\AbsenceType;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Services\WorkingDays;

/** Annual leave for the current year: 5.6 weeks pro rata (a setting), capped at 28 days, minus taken and pending. */
class LeaveBalance
{
    public static function for(Employee $e, WorkingDays $wd): array
    {
        $year = today()->year;
        $allowance = min(28, round((float) $e->business->rule('annual_leave_weeks') * (float) $e->days_per_week, 1));
        $taken = (int) $e->absences()->where('type', AbsenceType::Annual->value)->whereYear('start_date', $year)->sum('working_days');
        $pending = $e->requests()->pending()->where('kind', 'leave')->where('leave_type', AbsenceType::Annual->value)->whereYear('start_date', $year)->get()
            ->sum(fn (EmployeeRequest $r) => $wd->count($r->start_date, $r->end_date));

        return ['year' => (string) $year, 'allowance' => self::num($allowance), 'taken' => $taken, 'pending' => $pending, 'left' => self::num($allowance - $taken - $pending)];
    }

    public static function num(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');
    }
}
