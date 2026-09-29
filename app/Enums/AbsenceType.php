<?php

namespace App\Enums;

/** Absence types: compliance-rules.md §3. */
enum AbsenceType: string
{
    case Annual = 'annual';
    case BankHoliday = 'bank';
    case SickSelf = 'sick_self';
    case SickFitNote = 'sick_fit';
    case Family = 'family';
    case Compassionate = 'compassionate';
    case OtherPaid = 'other_paid';
    case Unpaid = 'unpaid';
    case Unauthorised = 'unauthorised';
    case Jury = 'jury';
    case Training = 'training';

    public function label(): string
    {
        return match ($this) {
            self::Annual => 'Annual leave',
            self::BankHoliday => 'Bank holiday',
            self::SickSelf => 'Sickness – self-certified (1–7 days)',
            self::SickFitNote => 'Sickness – fit note (8+ days)',
            self::Family => 'Maternity / paternity / adoption / shared parental',
            self::Compassionate => 'Compassionate / bereavement',
            self::OtherPaid => 'Other paid leave',
            self::Unpaid => 'Unpaid leave',
            self::Unauthorised => 'Unauthorised absence',
            self::Jury => 'Jury service',
            self::Training => 'Training / study leave',
        };
    }

    public function pay(): string
    {
        return match ($this) {
            self::Annual, self::BankHoliday, self::Training => 'Paid',
            self::SickSelf, self::SickFitNote => 'SSP / sick pay',
            self::Family => 'Statutory',
            self::Compassionate, self::Jury => 'Per policy',
            self::OtherPaid => 'Paid (full salary)',
            self::Unpaid, self::Unauthorised => 'Unpaid',
        };
    }

    /** Counts towards the unpaid limit (4 × working days per week in the leave year). */
    public function countsTowardsUnpaidLimit(): bool
    {
        return $this === self::Unpaid || $this === self::Unauthorised;
    }

    /** May be paid below the normal salary (SSP, statutory or "per policy" pay). */
    public function mayReducePay(): bool
    {
        return in_array($this, [self::SickSelf, self::SickFitNote, self::Family, self::Compassionate, self::Jury], true);
    }

    public function isSickness(): bool
    {
        return $this === self::SickSelf || $this === self::SickFitNote;
    }

    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label(), 'pay' => $t->pay()], self::cases());
    }
}
