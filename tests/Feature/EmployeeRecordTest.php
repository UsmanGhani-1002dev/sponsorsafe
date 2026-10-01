<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\User;
use App\Notifications\PortalInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

class EmployeeRecordTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    public function test_adding_an_employee_logs_the_creation_and_audits_it(): void
    {
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('sponsored'))->assertRedirect();

        $e = Employee::sole();
        $this->assertSame($this->admin->business_id, $e->business_id);
        $change = EmployeeChange::sole();
        $this->assertSame('Employee record created', $change->label);
        $this->assertSame('Skilled Worker visa – sponsored by this business', $change->new_value);
        $this->assertSame($this->admin->id, $change->changed_by);
        $this->assertTrue(AuditLog::where('action', 'employee.created')->where('subject_id', $e->id)->exists());
    }

    public function test_passport_ni_and_share_code_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('sponsored'));

        $raw = DB::table('employees')->first();
        foreach (['ni_number' => 'QQ123456C', 'passport_number' => 'AB1234567', 'share_code' => 'W7X9KP2QR'] as $field => $plain) {
            $this->assertNotSame($plain, $raw->{$field});
            $this->assertStringNotContainsString($plain, $raw->{$field});
            $this->assertSame($plain, Employee::sole()->{$field}, "$field decrypts to the normalised value");
        }
    }

    public function test_pages_only_ever_show_the_last_four_characters(): void
    {
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('sponsored'));
        $e = Employee::sole();

        foreach (["/app/employees/{$e->id}", '/app/employees'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('QQ123456C')->assertDontSee('AB1234567')->assertDontSee('W7X9KP2QR');
        }
        $this->get("/app/employees/{$e->id}")->assertInertia(fn (Assert $p) => $p
            ->where('sections.0.fields', fn ($fields) => collect($fields)->firstWhere('label', 'Share code')['value'] === '•••• P2QR')
            ->where('sections.2.fields', fn ($fields) => collect($fields)->firstWhere('label', 'National Insurance number')['value'] === '•••• 456C'
                && collect($fields)->firstWhere('label', 'Passport number')['value'] === '•••• 4567')
            ->where('personal.values.ni_number', '')
            ->where('personal.masked.ni_number', '•••• 456C'));
    }

    public function test_portal_invite_creates_a_login_emails_a_link_and_requests_passport(): void
    {
        Notification::fake();
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('sponsored', ['portal_invite' => true]))->assertRedirect();

        $e = Employee::sole();
        $this->assertNotNull($e->user);
        $this->assertSame(User::ROLE_EMPLOYEE, $e->user->role);
        $this->assertSame('sara.ali@example.com', $e->user->email);
        $this->assertSame('invited', $e->portalStatus());
        Notification::assertSentTo($e->user, PortalInvite::class);
        $request = DocumentRequest::sole();
        $this->assertSame('passport', $request->category->value);
        $this->assertSame(DocumentRequest::STATUS_AWAITING, $request->status);
    }

    public function test_no_portal_invite_means_no_login_and_no_document_request(): void
    {
        Notification::fake();
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('british_irish'));

        $this->assertNull(Employee::sole()->user_id);
        $this->assertSame(0, DocumentRequest::count());
        Notification::assertNothingSent();
    }

    public function test_a_portal_invite_needs_an_email_no_other_account_uses(): void
    {
        User::factory()->create(['email' => 'sara.ali@example.com']);
        $this->actingAs($this->admin)->post('/app/employees', $this->payload('british_irish', ['portal_invite' => true]))->assertSessionHasErrors('email');
    }

    public function test_record_a_change_logs_old_new_who_and_the_change_type(): void
    {
        $e = $this->employee(['job_title' => 'Sales Assistant']);

        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'job_title', 'value' => 'Store Supervisor'])
            ->assertSessionHas('success', 'Saved and logged in the history. No Home Office report needed.');

        $this->assertSame('Store Supervisor', $e->fresh()->job_title);
        $change = EmployeeChange::sole();
        $this->assertSame(['job_title', 'Job title', 'Sales Assistant', 'Store Supervisor', $this->admin->id],
            [$change->field, $change->label, $change->old_value, $change->new_value, $change->changed_by]);
        $this->assertTrue(AuditLog::where('action', 'employee.updated')->exists());
    }

    public function test_reportable_changes_for_sponsored_workers_say_so_with_the_deadline(): void
    {
        $this->travelTo('2026-09-28');
        $e = $this->employee(['salary' => 42000], sponsored: true);

        // 10 working days from Mon 28 Sep 2026 is Mon 12 Oct 2026.
        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'salary_reduction', 'value' => '£40,000'])
            ->assertSessionHas('success', 'Saved and logged. A Home Office report task was created: report this change on the Sponsor Management System by 12 Oct 2026.');
        $change = EmployeeChange::sole();
        $this->assertSame(['Salary – reduction', '£42,000.00', '£40,000.00'], [$change->label, $change->old_value, $change->new_value]);
    }

    public function test_salary_increase_is_log_only_even_for_sponsored_workers(): void
    {
        $e = $this->employee(['salary' => 42000], sponsored: true);

        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'salary_increase', 'value' => '45000'])
            ->assertSessionHas('success', 'Saved and logged in the history. No Home Office report needed.');
    }

    public function test_a_reduction_must_be_lower_and_an_increase_higher(): void
    {
        $e = $this->employee(['salary' => 42000], sponsored: true);
        $this->actingAs($this->admin);

        $this->post("/app/employees/{$e->id}/changes", ['type' => 'salary_reduction', 'value' => '45000'])->assertSessionHasErrors('value');
        $this->post("/app/employees/{$e->id}/changes", ['type' => 'salary_increase', 'value' => '40000'])->assertSessionHasErrors('value');
        $this->assertSame(0, EmployeeChange::count());
    }

    public function test_soc_code_changes_are_only_for_sponsored_workers(): void
    {
        $e = $this->employee();
        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'soc', 'value' => '7132'])->assertSessionHasErrors('type');
    }

    public function test_recording_the_same_value_is_refused(): void
    {
        $e = $this->employee(['job_title' => 'Sales Assistant']);
        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'job_title', 'value' => 'Sales Assistant'])->assertSessionHasErrors('value');
    }

    public function test_changing_email_updates_the_portal_login(): void
    {
        $user = User::factory()->create(['business_id' => $this->admin->business_id, 'email' => 'old@example.com']);
        $e = $this->employee(['user_id' => $user->id, 'email' => 'old@example.com']);

        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'email', 'value' => 'New@Example.com'])->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_correcting_personal_details_logs_each_field_and_keeps_blank_secrets(): void
    {
        $e = $this->employee(['full_name' => 'Sara Aly', 'ni_number' => 'QQ123456A', 'passport_number' => 'AB1234567']);

        $this->actingAs($this->admin)->put("/app/employees/{$e->id}/personal", [
            'full_name' => 'Sara Ali', 'date_of_birth' => '1995-04-12', 'nationality' => 'British',
            'ni_number' => 'QQ 65 43 21 B', 'passport_number' => '', 'passport_expiry' => '',
        ])->assertSessionHas('success', 'Saved. 2 corrections logged in the history.');

        $e->refresh();
        $this->assertSame('Sara Ali', $e->full_name);
        $this->assertSame('AB1234567', $e->passport_number, 'blank passport number kept');
        $ni = EmployeeChange::where('field', 'ni_number')->sole();
        $this->assertSame(['•••• 456A', '•••• 321B'], [$ni->old_value, $ni->new_value], 'secrets logged masked');
    }

    public function test_correcting_cannot_touch_job_pay_or_right_to_work_fields(): void
    {
        $e = $this->employee(['job_title' => 'Sales Assistant', 'salary' => 24000]);

        $this->actingAs($this->admin)->put("/app/employees/{$e->id}/personal", [
            'full_name' => $e->full_name, 'date_of_birth' => '1995-04-12', 'nationality' => 'British',
            'job_title' => 'Director', 'salary' => '99000', 'rtw_basis' => 'ilr', 'visa_expiry' => '2020-01-01',
        ])->assertSessionHas('success', 'Nothing changed.');

        $fresh = $e->fresh();
        $this->assertSame(['Sales Assistant', '24000.00', 'british_irish'], [$fresh->job_title, $fresh->salary, $fresh->rtw_basis->value]);
    }

    public function test_history_tab_shows_changes_newest_first_and_marks_reportable_ones(): void
    {
        $e = $this->employee(['job_title' => 'Sales Assistant'], sponsored: true);
        $this->actingAs($this->admin)->post("/app/employees/{$e->id}/changes", ['type' => 'phone', 'value' => '07700 900111']);
        $this->travel(1)->days();
        $this->post("/app/employees/{$e->id}/changes", ['type' => 'job_title', 'value' => 'Store Supervisor']);

        $this->get("/app/employees/{$e->id}")->assertInertia(fn (Assert $p) => $p->component('App/Employees/Show')
            ->has('history', 2)
            ->where('history.0.label', 'Job title')
            ->where('history.0.homeOffice.text', fn ($t) => str_ends_with($t, 'working days left'))
            ->where('history.1.homeOffice', ['text' => 'Not reportable', 'tone' => 'grey'])
            ->where('history.0.by', 'Nadia Khan'));
    }

    public function test_admins_cannot_see_or_change_another_business_employees(): void
    {
        $other = Employee::factory()->create();

        $this->actingAs($this->admin)->get("/app/employees/{$other->id}")->assertNotFound();
        $this->put("/app/employees/{$other->id}/personal", ['full_name' => 'Hacked'])->assertNotFound();
        $this->post("/app/employees/{$other->id}/changes", ['type' => 'job_title', 'value' => 'Hacked'])->assertNotFound();
        $this->post("/app/employees/{$other->id}/invite")->assertNotFound();
        $this->get('/app/employees')->assertInertia(fn (Assert $p) => $p->where('employees.total', 0));
        $this->assertNotSame('Hacked', $other->fresh()->full_name);
    }

    public function test_employees_cannot_open_the_admin_employee_pages(): void
    {
        $employee = User::factory()->create(['business_id' => $this->admin->business_id]);
        $this->actingAs($employee)->get('/app/employees')->assertRedirect('/me');
    }

    public function test_list_shows_status_and_expiry(): void
    {
        $this->travelTo('2026-09-28');
        $this->employee(['full_name' => 'Aisha Rahman', 'visa_expiry' => '2026-12-10'], sponsored: true);
        $this->employee(['full_name' => 'James Carter']);

        $this->actingAs($this->admin)->get('/app/employees')->assertInertia(fn (Assert $p) => $p
            ->where('employees.data.0.name', 'Aisha Rahman')
            ->where('employees.data.0.status', 'Skilled Worker (sponsored)')
            ->where('employees.data.0.expiry', ['text' => '10 Dec 2026 · 73 days', 'tone' => 'amber'])
            ->where('employees.data.1.expiry', ['text' => 'No time limit', 'tone' => 'grey'])
            ->where('plan', ['used' => 2, 'limit' => 15, 'reached' => false,
                'message' => 'Your plan covers up to 15 employees. Contact us about a Corporate package to add more.', 'canUpgrade' => false]));
    }

    public function test_list_searches_sorts_and_filters_on_the_server(): void
    {
        $this->employee(['full_name' => 'Aisha Rahman', 'job_title' => 'Sales Assistant'], sponsored: true);
        $this->employee(['full_name' => 'James Carter', 'job_title' => 'Stock Assistant']);
        $this->employee(['full_name' => 'Old Leaver', 'ended_on' => '2026-01-31', 'end_reason' => 'Resigned']);
        $this->actingAs($this->admin);

        $this->get('/app/employees?q=stock')->assertInertia(fn (Assert $p) => $p->where('employees.total', 1)->where('employees.data.0.name', 'James Carter'));
        $this->get('/app/employees?sort=name&dir=desc')->assertInertia(fn (Assert $p) => $p->where('employees.data.0.name', 'James Carter')->where('table.dir', 'desc'));
        $this->get('/app/employees?basis=sponsored')->assertInertia(fn (Assert $p) => $p->where('employees.total', 1));
        $this->get('/app/employees?status=left')->assertInertia(fn (Assert $p) => $p->where('employees.data.0.name', 'Old Leaver'));
        $this->get('/app/employees?sort=password&status=hacked')->assertOk()->assertInertia(fn (Assert $p) => $p->where('table.sort', 'name')->where('employees.total', 2));
    }

    private function employee(array $attributes = [], bool $sponsored = false): Employee
    {
        $factory = Employee::factory();

        return ($sponsored ? $factory->sponsored() : $factory)->create([
            'business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, ...$attributes,
        ]);
    }
}
