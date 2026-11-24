<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Document;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Models\User;
use App\Notifications\ComplianceDigest;
use App\Services\Reminders;
use Carbon\CarbonImmutable;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Stage 8a: reminders (compliance-rules §12), once per stage, by email digest and on the dashboard. */
class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        Notification::fake();
        $this->business = Business::factory()->create(['name' => 'Demo Retail Ltd']);
        $this->admin = User::factory()->admin()->create(['business_id' => $this->business->id, 'name' => 'Nadia Khan']);
    }

    private function sponsored(array $attributes = []): Employee
    {
        return Employee::factory()->sponsored()->create(['business_id' => $this->business->id, 'full_name' => 'Aisha Rahman', ...$attributes]);
    }

    /**
     * Run the daily job on a date and return the texts emailed to the admin that day. Missing-payslip reminders are left
     * out unless asked for (the factory's workers started in 2025 with no payslips).
     */
    private function runOn(string $date, bool $payslips = false): array
    {
        $this->travelTo(CarbonImmutable::parse("$date 07:00"));
        $before = Notification::sent($this->admin, ComplianceDigest::class)->count();
        $this->artisan('reminders:send')->assertSuccessful();

        return Notification::sent($this->admin, ComplianceDigest::class)->slice($before)
            ->flatMap(fn (ComplianceDigest $n) => $n->items)
            ->filter(fn ($i) => $payslips === ($i['type'] === Reminders::PAYSLIP))
            ->pluck('text')->values()->all();
    }

    private function payslip(Employee $e, string $on, ?string $review = null): Document
    {
        $doc = Document::create(['business_id' => $e->business_id, 'employee_id' => $e->id, 'category' => 'payroll', 'original_name' => "payslip-$on.pdf",
            'path' => "documents/x/$on.enc", 'mime' => 'application/pdf', 'size' => 10, 'review_status' => $review]);
        $doc->forceFill(['created_at' => "$on 09:00:00"])->save();

        return $doc;
    }

    public function test_acceptance_visa_expiry_alerts_fire_at_90_60_and_30_days(): void
    {
        $this->sponsored(['visa_expiry' => '2027-01-10', 'follow_up_check_due' => null]);

        $this->assertSame([], $this->runOn('2026-10-11')); // 91 days before
        $this->assertSame(["Aisha Rahman's visa or permission ends on 10 Jan 2027 (90 days)"], $this->runOn('2026-10-12'));
        $this->assertSame([], $this->runOn('2026-10-13')); // the 90-day reminder is not repeated
        $this->assertSame(["Aisha Rahman's visa or permission ends on 10 Jan 2027 (60 days)"], $this->runOn('2026-11-11'));
        $this->assertSame(["Aisha Rahman's visa or permission ends on 10 Jan 2027 (30 days)"], $this->runOn('2026-12-11'));
        $this->assertSame([], $this->runOn('2026-12-20'));
        $this->assertSame(["Aisha Rahman's visa or permission expired on 10 Jan 2027"], $this->runOn('2027-01-11'));
    }

    public function test_a_new_visa_expiry_starts_the_reminders_again(): void
    {
        $e = $this->sponsored(['visa_expiry' => '2026-12-31', 'follow_up_check_due' => null]);
        $this->assertCount(1, $this->runOn('2026-12-01')); // 30 days

        $e->update(['visa_expiry' => '2027-03-01']); // extension granted
        $this->assertSame(["Aisha Rahman's visa or permission ends on 1 Mar 2027 (90 days)"], $this->runOn('2026-12-01'));
    }

    public function test_follow_up_checks_and_passport_expiry(): void
    {
        $this->sponsored(['visa_expiry' => null, 'follow_up_check_due' => '2026-11-05', 'passport_expiry' => '2026-12-30']);

        $this->assertSame([
            'Follow-up right-to-work check for Aisha Rahman due on 5 Nov 2026 (30 days)',
            "Aisha Rahman's passport expires on 30 Dec 2026 (85 days)",
        ], $this->runOn('2026-10-06'));
        $this->assertSame(['Follow-up right-to-work check for Aisha Rahman was due on 5 Nov 2026'], $this->runOn('2026-11-06'));
    }

    public function test_home_office_deadlines_five_working_days_before_and_when_overdue(): void
    {
        $e = $this->sponsored(['visa_expiry' => null, 'follow_up_check_due' => null]);
        ReportTask::create(['business_id' => $this->business->id, 'level' => 'worker', 'employee_id' => $e->id, 'event' => 'Job title changed',
            'trigger_on' => '2026-10-01', 'deadline' => '2026-10-15', 'source' => 'manual']);

        $this->assertSame([], $this->runOn('2026-10-07')); // 6 working days left
        $this->assertSame(['Home Office report due on 15 Oct 2026: Job title changed (Aisha Rahman)'], $this->runOn('2026-10-08'));
        $this->assertSame([], $this->runOn('2026-10-15'));
        $this->assertSame(['Overdue Home Office report: Job title changed (Aisha Rahman), deadline was 15 Oct 2026'], $this->runOn('2026-10-16'));

        // Once reported, nothing more.
        ReportTask::query()->update(['status' => ReportTask::REPORTED]);
        $this->assertSame([], $this->runOn('2026-10-20'));
    }

    public function test_leavers_records_due_for_deletion_are_reminded_monthly(): void
    {
        Employee::factory()->left('2024-06-30')->create(['business_id' => $this->business->id, 'delete_after' => '2025-06-30', 'rtw_delete_after' => '2026-06-30']);

        $this->assertSame(["1 leaver's record is due for deletion: please review it"], $this->runOn('2026-10-01'));
        $this->assertSame([], $this->runOn('2026-10-15'));
        $this->assertCount(1, $this->runOn('2026-11-01'));
    }

    public function test_one_digest_per_business_and_only_for_active_businesses(): void
    {
        $this->payslip($this->sponsored(['visa_expiry' => '2026-10-20', 'follow_up_check_due' => '2026-10-20', 'passport_expiry' => '2026-11-01']), '2026-10-01');
        $second = User::factory()->admin()->create(['business_id' => $this->business->id]);
        $suspended = Business::factory()->suspended()->create();
        $suspendedAdmin = User::factory()->admin()->create(['business_id' => $suspended->id]);
        Employee::factory()->sponsored()->create(['business_id' => $suspended->id, 'visa_expiry' => '2026-10-20']);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));
        $this->artisan('reminders:send')->expectsOutputToContain('Sent 3 reminder(s) to 1 business(es), and reminders to 0 employee(s).');

        Notification::assertSentToTimes($this->admin, ComplianceDigest::class, 1);
        Notification::assertSentToTimes($second, ComplianceDigest::class, 1);
        Notification::assertNotSentTo($suspendedAdmin, ComplianceDigest::class);
        Notification::assertSentTo($this->admin, ComplianceDigest::class, fn (ComplianceDigest $n) => $n->business === 'Demo Retail Ltd'
            && $n->toMail($this->admin)->subject === '3 compliance reminders for Demo Retail Ltd');
    }

    public function test_the_lead_times_are_business_settings(): void
    {
        $this->business->update(['settings' => ['expiry_alert_days' => [45], 'passport_alert_days' => 10]]);
        $this->sponsored(['visa_expiry' => '2026-11-20', 'follow_up_check_due' => null, 'passport_expiry' => '2026-11-20']);

        $this->assertSame(["Aisha Rahman's visa or permission ends on 20 Nov 2026 (45 days)"], $this->runOn('2026-10-06'));
    }

    public function test_the_dashboard_shows_what_is_coming_up(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00'));
        $this->sponsored(['visa_expiry' => '2026-11-05', 'follow_up_check_due' => null, 'start_date' => '2026-09-14']);

        $this->actingAs($this->admin)->get('/app')->assertInertia(fn (Assert $p) => $p->has('reminders', 1)
            ->where('reminders.0.text', "Aisha Rahman's visa or permission ends on 5 Nov 2026 (30 days)")
            ->where('reminders.0.tone', 'red')
            ->where('reminders.0.href', '/app/employees/'.Employee::sole()->id));
    }

    public function test_a_missing_payslip_is_reminded_when_overdue_then_monthly_until_one_is_uploaded(): void
    {
        $e = $this->sponsored(['visa_expiry' => null, 'follow_up_check_due' => null]);
        $this->payslip($e, '2026-10-01');
        $this->payslip($e, '2026-10-20', review: Document::PENDING_REVIEW); // sent from the portal, not accepted yet: does not count

        $this->assertSame([], $this->runOn('2026-11-05', payslips: true)); // 35 days: still fresh
        $this->assertSame(['No payslip uploaded for Aisha Rahman since 1 Oct 2026: upload the latest one from your accountant'], $this->runOn('2026-11-06', payslips: true));
        $this->assertSame([], $this->runOn('2026-11-20', payslips: true)); // not repeated every day
        $this->assertCount(1, $this->runOn('2026-12-06', payslips: true)); // a month later, still missing

        $this->payslip($e, '2026-12-07');
        $this->assertSame([], $this->runOn('2026-12-08', payslips: true));
        $this->assertCount(1, $this->runOn('2027-01-12', payslips: true)); // the next one is late: starts again
    }

    public function test_payslip_reminders_are_for_sponsored_workers_and_wait_for_a_new_starters_first_payday(): void
    {
        $this->sponsored(['full_name' => 'Rahul Mehta', 'start_date' => '2026-10-25']);
        $this->sponsored(['full_name' => 'Kasia Nowak', 'start_date' => '2026-09-01']);
        Employee::factory()->create(['business_id' => $this->business->id, 'full_name' => 'James Carter', 'start_date' => '2025-01-13']); // not sponsored
        Employee::factory()->sponsored()->left('2026-10-31')->create(['business_id' => $this->business->id, 'full_name' => 'Chloe Martin']);

        $this->assertSame(['No payslip on file for Kasia Nowak, who started on 1 Sep 2026: upload the payslips your accountant sends'], $this->runOn('2026-11-24', payslips: true));
        // Rahul's first payslip is late 35 days after he started; Kasia is not reminded again until a month after her first reminder.
        $this->assertSame([], $this->runOn('2026-11-29', payslips: true));
        $this->assertSame(['No payslip on file for Rahul Mehta, who started on 25 Oct 2026: upload the payslips your accountant sends'], $this->runOn('2026-11-30', payslips: true));

        $this->actingAs($this->admin)->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('reminders', fn ($r) => collect($r)->pluck('href')->contains('/app/employees/'.Employee::where('full_name', 'Kasia Nowak')->value('id').'?tab=docs')));
    }
}
