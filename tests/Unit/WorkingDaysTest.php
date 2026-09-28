<?php

namespace Tests\Unit;

use App\Services\WorkingDays;
use PHPUnit\Framework\TestCase;

class WorkingDaysTest extends TestCase
{
    private function wd(): WorkingDays
    {
        // Subset of England & Wales 2026 bank holidays.
        return new WorkingDays(['2026-04-03', '2026-04-06', '2026-05-04', '2026-05-25', '2026-08-31', '2026-12-25', '2026-12-28', '2027-01-01']);
    }

    public function test_weekends_and_bank_holidays_are_not_working_days(): void
    {
        $wd = $this->wd();
        $this->assertTrue($wd->isWorkingDay('2026-09-24'));   // Thursday
        $this->assertFalse($wd->isWorkingDay('2026-09-26'));  // Saturday
        $this->assertFalse($wd->isWorkingDay('2026-08-31'));  // Summer bank holiday
    }

    public function test_report_deadline_is_ten_working_days_after_trigger(): void
    {
        $this->assertSame('2026-09-24', $this->wd()->add('2026-09-10', 10));
        $this->assertSame('2026-06-26', $this->wd()->add('2026-06-12', 10));
    }

    public function test_deadline_skips_christmas_bank_holidays(): void
    {
        // 18 Dec 2026 + 10 working days, skipping 25 Dec, 28 Dec and 1 Jan.
        $this->assertSame('2027-01-06', $this->wd()->add('2026-12-18', 10));
    }

    public function test_counts_working_days_in_a_range(): void
    {
        $this->assertSame(6, $this->wd()->count('2026-03-02', '2026-03-09'));
        $this->assertSame(4, $this->wd()->count('2026-04-06', '2026-04-10')); // Easter Monday excluded
        $this->assertSame(15, $this->wd()->count('2026-10-05', '2026-10-23'));
    }

    public function test_tenth_working_day_of_an_unauthorised_streak(): void
    {
        $days = $this->wd()->between('2026-09-28', '2026-10-09');
        $this->assertCount(10, $days);
        $this->assertSame('2026-10-09', $days[9]);
    }

    public function test_working_days_until_a_deadline(): void
    {
        $wd = $this->wd();
        $this->assertSame(0, $wd->until('2026-09-24', '2026-09-24'));
        $this->assertSame(2, $wd->until('2026-09-24', '2026-09-28'));
        $this->assertSame(-2, $wd->until('2026-09-28', '2026-09-24'));
    }
}
