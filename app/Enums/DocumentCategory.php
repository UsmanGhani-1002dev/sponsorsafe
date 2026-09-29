<?php

namespace App\Enums;

/** Document categories: compliance-rules.md §2. */
enum DocumentCategory: string
{
    case RightToWork = 'rtw';
    case Passport = 'passport';
    case Cos = 'cos';
    case Contract = 'contract';
    case JobDescription = 'jd';
    case Recruitment = 'recruit';
    case Payroll = 'payroll';
    case Absence = 'absence';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RightToWork => 'Right-to-work check result',
            self::Passport => 'Passport / identity',
            self::Cos => 'Certificate of Sponsorship',
            self::Contract => 'Employment contract',
            self::JobDescription => 'Job description',
            self::Recruitment => 'Recruitment evidence (advert, shortlist, interview notes)',
            self::Payroll => 'Payslips / payroll records',
            self::Absence => 'Absence evidence (fit notes, approvals)',
            self::Other => 'Other',
        };
    }

    /** Required for everyone; sponsored workers also need the CoS and recruitment evidence. */
    public static function requiredFor(bool $sponsored): array
    {
        $required = [self::RightToWork, self::Passport, self::Contract, self::JobDescription, self::Payroll];

        return $sponsored ? [...$required, self::Cos, self::Recruitment] : $required;
    }
}
