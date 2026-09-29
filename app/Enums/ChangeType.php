<?php

namespace App\Enums;

/** "Record a change" on the History tab: compliance-rules.md §5. */
enum ChangeType: string
{
    case JobTitle = 'job_title';
    case Soc = 'soc';
    case SalaryReduction = 'salary_reduction';
    case SalaryIncrease = 'salary_increase';
    case Hours = 'hours';
    case Address = 'address';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::JobTitle => 'Job title',
            self::Soc => 'SOC code / duties',
            self::SalaryReduction => 'Salary – reduction',
            self::SalaryIncrease => 'Salary – increase',
            self::Hours => 'Contracted weekly hours',
            self::Address => 'Home address',
            self::Phone => 'Phone',
            self::Email => 'Email',
        };
    }

    /** The employee field this change updates. */
    public function field(): string
    {
        return match ($this) {
            self::JobTitle => 'job_title',
            self::Soc => 'soc_code',
            self::SalaryReduction, self::SalaryIncrease => 'salary',
            self::Hours => 'contracted_hours',
            self::Address => 'address',
            self::Phone => 'phone',
            self::Email => 'email',
        };
    }

    /** Must be reported on the SMS when the worker is sponsored (log only for everyone else). */
    public function reportableIfSponsored(): bool
    {
        return in_array($this, [self::JobTitle, self::Soc, self::SalaryReduction, self::Hours], true);
    }

    /** SOC codes only exist for sponsored workers. */
    public function sponsoredOnly(): bool
    {
        return $this === self::Soc;
    }

    public static function options(bool $sponsored): array
    {
        return array_values(array_map(fn (self $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'field' => $t->field(),
            'reportable' => $sponsored && $t->reportableIfSponsored(),
        ], array_filter(self::cases(), fn (self $t) => $sponsored || ! $t->sponsoredOnly())));
    }
}
