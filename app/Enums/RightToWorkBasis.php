<?php

namespace App\Enums;

/** Right-to-work basis: compliance-rules.md §1. Drives the Add employee form and follow-up checks. */
enum RightToWorkBasis: string
{
    case BritishIrish = 'british_irish';
    case EussSettled = 'euss_settled';
    case EussPreSettled = 'euss_presettled';
    case Ilr = 'ilr';
    case Sponsored = 'sponsored';
    case OtherVisa = 'other_visa';

    /** Visa types offered for "Other visa". */
    public const OTHER_VISA_TYPES = ['Graduate', 'Dependant', 'Student', 'Other'];

    public function label(): string
    {
        return match ($this) {
            self::BritishIrish => 'British or Irish citizen',
            self::EussSettled => 'EU Settlement Scheme – settled status',
            self::EussPreSettled => 'EU Settlement Scheme – pre-settled status',
            self::Ilr => 'Indefinite leave to remain',
            self::Sponsored => 'Skilled Worker visa – sponsored by this business',
            self::OtherVisa => 'Other visa (Graduate, Dependant, Student…)',
        };
    }

    /** Permission ends on a date, so a follow-up check is due before it. */
    public function timeLimited(): bool
    {
        return in_array($this, [self::EussPreSettled, self::Sponsored, self::OtherVisa], true);
    }

    /** Checked online with a Home Office share code (British/Irish: manual passport check or IDVT). */
    public function usesShareCode(): bool
    {
        return $this !== self::BritishIrish;
    }

    /** Sponsor reporting duties apply only to workers this business sponsors. */
    public function sponsored(): bool
    {
        return $this === self::Sponsored;
    }

    /** Fixed visa/status wording for bases that have one; null means HR picks it (Other visa) or there is none. */
    public function fixedVisaType(): ?string
    {
        return match ($this) {
            self::Sponsored => 'Skilled Worker',
            self::EussPreSettled => 'EUSS pre-settled status',
            default => null,
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::BritishIrish => 'Check an original British or Irish passport in person (or use a certified IDVT provider). Keep a signed, dated copy. No follow-up check is needed.',
            self::EussSettled, self::Ilr => 'Do an online check with the share code and save the result. No follow-up check is needed.',
            self::EussPreSettled => 'Do an online check with the share code. Record the expiry date: a follow-up check is due before it.',
            self::Sponsored => 'Do an online check with the share code before they start. Job, salary and hours must match the CoS. A follow-up check is due before the visa expires, and sponsor reporting duties apply.',
            self::OtherVisa => 'Do an online check with the share code and record any work restrictions (e.g. Student hour limits). A follow-up check is due before the visa expires.',
        };
    }

    /** Everything the form needs, so the rules live in one place (PHP) and the UI just follows them. */
    public static function options(): array
    {
        return array_map(fn (self $b) => [
            'value' => $b->value,
            'label' => $b->label(),
            'timeLimited' => $b->timeLimited(),
            'shareCode' => $b->usesShareCode(),
            'sponsored' => $b->sponsored(),
            'hint' => $b->hint(),
        ], self::cases());
    }
}
