<?php

namespace Tests\Unit;

use App\Enums\AbsenceType as T;
use App\Services\AbsenceCheck;
use App\Services\AbsenceRules;
use App\Services\WorkingDays;
use PHPUnit\Framework\TestCase;

/** compliance-rules.md §3, including go-live acceptance tests 1 and 2 (§13). */
class AbsenceRulesTest extends TestCase
{
    private function rules(array $overrides = []): AbsenceRules
    {
        // England & Wales bank holidays used below.
        return new AbsenceRules(new WorkingDays(['2026-05-25', '2026-08-31', '2026-12-25', '2026-12-28', '2027-01-01']), $overrides);
    }

    private function check(T $type, string $start, string $end, array $existing = [], float $days = 5, bool $sponsored = true, array $rules = []): AbsenceCheck
    {
        return $this->rules($rules)->check($type, $start, $end, $days, $sponsored, $existing);
    }

    // ---- Unpaid limit ----

    public function test_acceptance_21_unpaid_days_in_a_year_for_a_5_day_worker_is_reported_on_day_21(): void
    {
        $c = $this->check(T::Unpaid, '2026-06-01', '2026-06-29');

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame(21, $c->days);
        $this->assertSame('2026-06-29', $c->trigger, '21st working day');
        $this->assertSame('2026-07-13', $c->deadline, 'trigger + 10 working days');
        $this->assertSame('Unpaid leave over 4 weeks in 2026', $c->event);
    }

    public function test_earlier_unpaid_leave_in_the_year_counts_towards_the_limit(): void
    {
        // Prototype: Aisha has 6 unpaid days in March; 15 more in October takes her to 21.
        $c = $this->check(T::Unpaid, '2026-10-05', '2026-10-23', [['type' => 'unpaid', 'start' => '2026-03-02', 'end' => '2026-03-09']]);

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame('2026-10-23', $c->trigger);
        $this->assertSame('2026-11-06', $c->deadline);
        $this->assertStringContainsString('6 already taken + 15 in this absence = 21, over the 20-day limit', $c->detail);
    }

    public function test_exactly_at_the_limit_is_not_reported(): void
    {
        $c = $this->check(T::Unpaid, '2026-06-01', '2026-06-26');

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
        $this->assertStringContainsString('= 20 of 20. 0 days left', $c->detail);
        $this->assertNull($c->deadline);
    }

    public function test_limit_is_pro_rata_to_working_days_per_week(): void
    {
        // 3 days a week: limit 12, so the 13th unpaid working day triggers.
        $c = $this->check(T::Unpaid, '2026-06-01', '2026-06-17', days: 3);

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame('2026-06-17', $c->trigger);
    }

    public function test_calendar_leave_year_resets_on_1_january(): void
    {
        $december = [['type' => 'unpaid', 'start' => '2026-12-01', 'end' => '2026-12-21']]; // 15 working days
        $c = $this->check(T::Unpaid, '2027-01-04', '2027-01-15', $december);                 // 10 working days

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
    }

    public function test_rolling_leave_year_counts_the_previous_12_months(): void
    {
        $december = [['type' => 'unpaid', 'start' => '2026-12-01', 'end' => '2026-12-21']];
        $c = $this->check(T::Unpaid, '2027-01-04', '2027-01-15', $december, rules: ['unpaid_leave_year' => 'rolling']);

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame('2027-01-11', $c->trigger, '15 in December + 4–8 Jan (1 Jan is a bank holiday) = 20, so 11 Jan is the 21st');
    }

    public function test_unpaid_leave_already_over_the_limit_does_not_trigger_again(): void
    {
        $c = $this->check(T::Unpaid, '2026-10-05', '2026-10-06', [['type' => 'unpaid', 'start' => '2026-06-01', 'end' => '2026-06-29']]);

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
        $this->assertNotEmpty($c->warnings);
    }

    public function test_the_limit_weeks_is_a_setting(): void
    {
        $c = $this->check(T::Unpaid, '2026-06-01', '2026-06-15', rules: ['unpaid_limit_weeks' => 2]);

        $this->assertSame('2026-06-15', $c->trigger, '2 weeks = 10 days, so the 11th working day triggers');
    }

    // ---- Unauthorised streak ----

    public function test_acceptance_10_consecutive_unauthorised_days_across_a_weekend_and_bank_holiday(): void
    {
        // Mon 24 Aug to Mon 7 Sep 2026: two weekends and the 31 Aug bank holiday are skipped.
        $c = $this->check(T::Unauthorised, '2026-08-24', '2026-09-07');

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame(10, $c->days);
        $this->assertSame('2026-09-07', $c->trigger, '10th working day');
        $this->assertSame('2026-09-21', $c->deadline);
        $this->assertSame('Unauthorised absence reached 10 consecutive working days', $c->event);
    }

    public function test_separate_records_that_follow_on_make_one_streak(): void
    {
        $c = $this->check(T::Unauthorised, '2026-09-01', '2026-09-07', [['type' => 'unauthorised', 'start' => '2026-08-24', 'end' => '2026-08-28']]);

        $this->assertSame(AbsenceCheck::REPORT, $c->status);
        $this->assertSame('2026-09-07', $c->trigger);
    }

    public function test_a_working_day_back_at_work_breaks_the_streak(): void
    {
        // Worked on Fri 28 Aug, so the streak restarts on 1 Sep.
        $c = $this->check(T::Unauthorised, '2026-09-01', '2026-09-08', [['type' => 'unauthorised', 'start' => '2026-08-24', 'end' => '2026-08-27']]);

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
        $this->assertStringContainsString('6 of 10 consecutive working days', $c->detail);
    }

    public function test_nine_days_is_not_reported_yet(): void
    {
        $c = $this->check(T::Unauthorised, '2026-09-28', '2026-10-08');

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
        $this->assertSame('No report yet', $c->title);
        $this->assertStringContainsString('9 of 10', $c->detail);
    }

    public function test_continuing_a_streak_already_over_the_threshold_does_not_trigger_again(): void
    {
        $c = $this->check(T::Unauthorised, '2026-09-08', '2026-09-09', [['type' => 'unauthorised', 'start' => '2026-08-24', 'end' => '2026-09-07']]);

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status);
        $this->assertNotEmpty($c->warnings);
    }

    public function test_unauthorised_days_also_count_towards_the_unpaid_limit(): void
    {
        $c = $this->check(T::Unauthorised, '2026-10-05', '2026-10-12', [['type' => 'unpaid', 'start' => '2026-06-01', 'end' => '2026-06-19']]);

        $this->assertSame(AbsenceCheck::NOT_YET, $c->status, '6 of 10 unauthorised days');
        $this->assertStringContainsString('yearly limit on 12 Oct 2026', implode(' ', $c->warnings));
    }

    public function test_trigger_days_is_a_setting(): void
    {
        $c = $this->check(T::Unauthorised, '2026-09-28', '2026-10-02', rules: ['unauthorised_trigger_days' => 5]);

        $this->assertSame('2026-10-02', $c->trigger);
    }

    // ---- Sponsored only, other types, warnings, validation ----

    public function test_reporting_applies_to_sponsored_workers_only(): void
    {
        $c = $this->check(T::Unauthorised, '2026-08-24', '2026-09-07', sponsored: false);

        $this->assertSame(AbsenceCheck::NONE, $c->status);
        $this->assertNull($c->deadline);
        $this->assertStringContainsString('never reported', $c->detail);
    }

    public function test_other_absence_types_are_never_reported(): void
    {
        foreach ([T::Annual, T::BankHoliday, T::SickSelf, T::SickFitNote, T::Family, T::Compassionate, T::OtherPaid, T::Jury, T::Training] as $type) {
            $c = $this->check($type, '2026-06-01', '2026-07-31');
            $this->assertSame(AbsenceCheck::NONE, $c->status, $type->label());
        }
    }

    public function test_self_certified_sickness_over_7_calendar_days_needs_a_fit_note(): void
    {
        $this->assertSame([], $this->check(T::SickSelf, '2026-09-28', '2026-10-04')->warnings, '7 calendar days is fine');
        $this->assertStringContainsString('fit note', $this->check(T::SickSelf, '2026-09-28', '2026-10-05')->warnings[0]);
    }

    public function test_reduced_pay_warning_for_sponsored_workers_on_non_exempt_leave(): void
    {
        $this->assertStringContainsString('reportable salary change', $this->check(T::Compassionate, '2026-09-28', '2026-09-30')->warnings[0]);
        $this->assertSame([], $this->check(T::SickSelf, '2026-09-28', '2026-09-30')->warnings, 'sickness is exempt by default');
        $this->assertSame([], $this->check(T::Compassionate, '2026-09-28', '2026-09-30', sponsored: false)->warnings);
        $this->assertSame([], $this->check(T::Compassionate, '2026-09-28', '2026-09-30', rules: ['exempt_absence_types' => ['compassionate']])->warnings);
    }

    public function test_invalid_dates_and_overlaps_are_rejected(): void
    {
        $this->assertSame(AbsenceCheck::INVALID, $this->check(T::Annual, '2026-10-09', '2026-10-05')->status);
        $this->assertSame('No working days', $this->check(T::Annual, '2026-10-03', '2026-10-04')->title, 'a weekend');
        $overlap = $this->check(T::Annual, '2026-08-12', '2026-08-20', [['type' => 'annual', 'start' => '2026-08-10', 'end' => '2026-08-14']]);
        $this->assertSame(AbsenceCheck::INVALID, $overlap->status);
        $this->assertStringContainsString('annual leave from 10 Aug 2026 to 14 Aug 2026', $overlap->detail);
    }

    public function test_unpaid_usage_for_the_profile(): void
    {
        $usage = $this->rules()->unpaidUsage('2026-09-29', 5, [
            ['type' => 'unpaid', 'start' => '2026-03-02', 'end' => '2026-03-09'],
            ['type' => 'annual', 'start' => '2026-08-10', 'end' => '2026-08-14'],
            ['type' => 'unpaid', 'start' => '2025-11-03', 'end' => '2025-11-07'],
        ]);

        $this->assertSame(['used' => 6, 'limit' => 20.0], $usage);
    }
}
