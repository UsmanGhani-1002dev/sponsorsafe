<?php

namespace Tests\Feature;

use App\Enums\DocumentCategory;
use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Notifications\ComplianceDigest;
use App\Notifications\EmployeeReminder;
use Carbon\CarbonImmutable;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Reminders emailed to employees themselves: new visa and share code, and documents HR asked for. */
class EmployeeRemindersTest extends TestCase
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

    /** A sponsored worker with an active portal login. */
    private function aisha(array $attributes = [], bool $portal = true): Employee
    {
        $name = $attributes['full_name'] ?? 'Aisha Rahman';
        $email = strtolower(strtok($name, ' ')).'@example.com';
        $user = $portal ? User::factory()->create(['business_id' => $this->business->id, 'name' => $name, 'email' => $email]) : null;

        return Employee::factory()->sponsored()->create([
            'business_id' => $this->business->id, 'full_name' => $name, 'email' => $email, 'user_id' => $user?->id,
            'follow_up_check_due' => null, 'passport_expiry' => null, ...$attributes,
        ]);
    }

    /** Run the daily job on a date and return the employee emails sent that day: [subject, texts]. */
    private function runOn(string $date, Employee $employee): array
    {
        $this->travelTo(CarbonImmutable::parse("$date 07:00"));
        $before = Notification::sent($employee->user, EmployeeReminder::class)->count();
        $this->artisan('reminders:send')->assertSuccessful();

        return Notification::sent($employee->user, EmployeeReminder::class)->slice($before)
            ->map(fn (EmployeeReminder $n) => [$n->toMail($employee->user)->subject, array_column($n->items, 'text')])->values()->all();
    }

    private function request(Employee $e, string $on, DocumentCategory $category = DocumentCategory::Passport): DocumentRequest
    {
        $r = DocumentRequest::create(['business_id' => $this->business->id, 'employee_id' => $e->id, 'category' => $category, 'requested_by' => $this->admin->id]);
        $r->forceFill(['created_at' => "$on 10:00:00"])->save();

        return $r;
    }

    public function test_the_employee_is_asked_for_their_new_visa_and_share_code_at_90_60_and_30_days_and_when_expired(): void
    {
        $e = $this->aisha(['visa_expiry' => '2027-01-10']);

        $this->assertSame([], $this->runOn('2026-10-11', $e));
        [[$subject, $texts]] = $this->runOn('2026-10-12', $e);
        $this->assertSame('Please send your new share code', $subject);
        $this->assertStringStartsWith('Your visa or permission to work in the UK ends on 10 Jan 2027 (90 days). When you get your new visa or extension, send your new visa expiry date', $texts[0]);
        $this->assertStringContainsString('with your new share code in the note', $texts[0]);

        $this->assertSame([], $this->runOn('2026-10-13', $e)); // once per stage
        $this->assertCount(1, $this->runOn('2026-11-11', $e)); // 60 days
        $this->assertCount(1, $this->runOn('2026-12-11', $e)); // 30 days
        [[, $expired]] = $this->runOn('2027-01-11', $e);
        $this->assertStringStartsWith('Your visa or permission to work in the UK ended on 10 Jan 2027.', $expired[0]);

        // The admins still get their own digest.
        Notification::assertSentTo($this->admin, ComplianceDigest::class);
    }

    public function test_no_visa_reminder_while_their_new_visa_is_waiting_for_hr(): void
    {
        $e = $this->aisha(['visa_expiry' => '2027-01-10']);
        EmployeeRequest::forceCreate(['business_id' => $this->business->id, 'employee_id' => $e->id, 'kind' => 'change', 'change_kind' => 'visa',
            'value' => '2030-01-10', 'status' => EmployeeRequest::PENDING]);

        $this->assertSame([], $this->runOn('2026-12-11', $e));
    }

    public function test_hr_asking_for_a_document_emails_the_employee_at_once(): void
    {
        $e = $this->aisha();
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));

        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/document-requests", ['category' => 'passport'])
            ->assertSessionHas('success', 'Passport / identity requested from Aisha. We have emailed them, and it will appear in their portal.');

        Notification::assertSentTo($e->user, EmployeeReminder::class, function (EmployeeReminder $n) use ($e) {
            $mail = $n->toMail($e->user);

            return $mail->subject === 'Please upload your passport / identity'
                && $n->items[0]['text'] === 'Demo Retail Ltd has asked you for your passport / identity. Please upload it in the portal under My documents.'
                && $mail->actionUrl === route('portal.documents');
        });
        $this->assertSame([], $this->runOn('2026-11-25', $e)); // not repeated the next morning
    }

    public function test_a_document_still_not_sent_is_chased_after_7_and_14_days_then_stops(): void
    {
        $e = $this->aisha();
        $this->request($e, '2026-11-02');

        $this->assertCount(1, $this->runOn('2026-11-03', $e)); // asked when the request was made without an email (e.g. Add employee)
        $this->assertSame([], $this->runOn('2026-11-08', $e));
        [[, $texts]] = $this->runOn('2026-11-09', $e);
        $this->assertSame(['Demo Retail Ltd has asked you for your passport / identity (asked on 2 Nov 2026). Please upload it in the portal under My documents.'], $texts);
        $this->assertCount(1, $this->runOn('2026-11-16', $e)); // 14 days
        $this->assertSame([], $this->runOn('2026-11-30', $e)); // no more after that
    }

    public function test_no_chasing_once_uploaded_or_cancelled(): void
    {
        $e = $this->aisha();
        $uploaded = $this->request($e, '2026-11-02');
        EmployeeRequest::forceCreate(['business_id' => $this->business->id, 'employee_id' => $e->id, 'kind' => 'document',
            'document_request_id' => $uploaded->id, 'status' => EmployeeRequest::PENDING]);
        $this->request($e, '2026-11-02', DocumentCategory::Contract)->update(['status' => DocumentRequest::STATUS_CANCELLED]);

        $this->assertSame([], $this->runOn('2026-11-09', $e));
    }

    public function test_several_things_go_in_one_email(): void
    {
        $e = $this->aisha(['visa_expiry' => '2026-12-24']);
        $this->request($e, '2026-11-24');

        $emails = $this->runOn('2026-11-24', $e);
        $this->assertCount(1, $emails);
        $this->assertSame('2 things Demo Retail Ltd needs from you', $emails[0][0]);
    }

    public function test_only_current_employees_with_portal_access_of_active_businesses_are_emailed(): void
    {
        $noPortal = $this->aisha(['visa_expiry' => '2026-12-24'], portal: false);
        $this->request($noPortal, '2026-11-24');
        $this->actingAs($this->admin)->post("/app/employees/{$noPortal->id}/document-requests", ['category' => 'contract'])
            ->assertSessionHas('success', 'Employment contract requested from Aisha. They do not have portal access yet, so send them an invite too.');

        $off = $this->aisha(['visa_expiry' => '2026-12-24', 'full_name' => 'Rahul Mehta']);
        $off->user->update(['active' => false]);
        $leaver = $this->aisha(['visa_expiry' => '2026-12-24', 'full_name' => 'Kasia Nowak', 'ended_on' => '2026-11-01']);
        $paused = Business::factory()->create(['status' => Business::SUSPENDED]);
        $elsewhere = $this->aisha(['visa_expiry' => '2026-12-24', 'full_name' => 'Chloe Martin', 'business_id' => $paused->id]);

        $this->travelTo(CarbonImmutable::parse('2026-11-24 07:00'));
        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertNotSentTo([$off->user, $leaver->user, $elsewhere->user], EmployeeReminder::class);
        Notification::assertSentToTimes($this->admin, ComplianceDigest::class, 1);
    }
}
