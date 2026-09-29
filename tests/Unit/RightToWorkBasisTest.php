<?php

namespace Tests\Unit;

use App\Enums\DocumentCategory;
use App\Enums\RightToWorkBasis as B;
use App\Services\EmployeeRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** compliance-rules.md §1 table, row by row. */
class RightToWorkBasisTest extends TestCase
{
    public static function table(): array
    {
        //                 basis                timeLimited shareCode sponsored
        return [
            'British/Irish' => [B::BritishIrish, false, false, false],
            'EUSS settled' => [B::EussSettled, false, true, false],
            'EUSS pre-settled' => [B::EussPreSettled, true, true, false],
            'ILR' => [B::Ilr, false, true, false],
            'Skilled Worker' => [B::Sponsored, true, true, true],
            'Other visa' => [B::OtherVisa, true, true, false],
        ];
    }

    #[DataProvider('table')]
    public function test_each_basis_matches_the_rules_table(B $basis, bool $limited, bool $share, bool $sponsored): void
    {
        $this->assertSame($limited, $basis->timeLimited(), 'time-limited');
        $this->assertSame($share, $basis->usesShareCode(), 'share code');
        $this->assertSame($sponsored, $basis->sponsored(), 'sponsored');
    }

    #[DataProvider('table')]
    public function test_follow_up_check_is_due_on_visa_expiry_only_when_time_limited(B $basis, bool $limited, bool $share, bool $sponsored): void
    {
        $this->assertSame($limited ? '2027-02-14' : null, EmployeeRules::followUpCheckDue($basis, '2027-02-14'));
    }

    public function test_sponsored_workers_need_cos_and_recruitment_evidence_as_well(): void
    {
        $standard = array_map(fn ($c) => $c->value, DocumentCategory::requiredFor(false));
        $sponsored = array_map(fn ($c) => $c->value, DocumentCategory::requiredFor(true));

        $this->assertSame(['rtw', 'passport', 'contract', 'jd', 'payroll'], $standard);
        $this->assertSame([...$standard, 'cos', 'recruit'], $sponsored);
    }
}
