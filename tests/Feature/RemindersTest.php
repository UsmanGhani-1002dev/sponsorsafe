<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Models\User;
use App\Notifications\ComplianceDigest;
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

    /** Run the daily job on a date and return the texts emailed to the admin that day. */
    private function runOn(string $date): array
    {
        $this->travelTo(CarbonImmutable::parse("$date 07:00"));
        $before = Notification::sent($this->admin, ComplianceDigest::class)->count();
        $this->artisan('reminders:send')->assertSuccessful();

        return Notification::sent($this->admin, ComplianceDigest::class)->slice($before)
            ->flatMap(fn (ComplianceDigest $n) => array_column($n->items, 'text'))->values()->all();
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
        $this->sponsored(['visa_expiry' => '2026-10-20', 'follow_up_check_due' => '2026-10-20', 'passport_expiry' => '2026-11-01']);
        $second = User::factory()->admin()->create(['business_id' => $this->business->id]);
        $suspended = Business::factory()->suspended()->create();
        $suspendedAdmin = User::factory()->admin()->create(['business_id' => $suspended->id]);
        Employee::factory()->sponsored()->create(['business_id' => $suspended->id, 'visa_expiry' => '2026-10-20']);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 07:00'));
        $this->artisan('reminders:send')->expectsOutputToContain('Sent 3 reminder(s) to 1 business(es).');

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
        $this->sponsored(['visa_expiry' => '2026-11-05', 'follow_up_check_due' => null]);

        $this->actingAs($this->admin)->get('/app')->assertInertia(fn (Assert $p) => $p->has('reminders', 1)
            ->where('reminders.0.text', "Aisha Rahman's visa or permission ends on 5 Nov 2026 (30 days)")
            ->where('reminders.0.tone', 'red')
            ->where('reminders.0.href', '/app/employees/'.Employee::sole()->id));
    }
}
