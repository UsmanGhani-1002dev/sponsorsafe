<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\ReportTask;
use App\Models\User;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** compliance-rules §6: employee portal requests → HR inbox. */
class PortalRequestTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $aisha;   // sponsored, time-limited
    private Employee $james;   // British, not sponsored
    private User $aishaUser;
    private User $jamesUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo('2026-09-28 10:00');
        $this->setUpBusiness();
        $this->aishaUser = User::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'Aisha Rahman', 'email' => 'aisha@example.com']);
        $this->jamesUser = User::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'James Carter', 'email' => 'james@example.com']);
        $this->aisha = Employee::factory()->sponsored()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'user_id' => $this->aishaUser->id,
            'full_name' => 'Aisha Rahman', 'email' => 'aisha@example.com', 'address' => '9 Onslow Road', 'visa_expiry' => '2026-12-10']);
        $this->james = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'user_id' => $this->jamesUser->id,
            'full_name' => 'James Carter', 'email' => 'james@example.com']);
    }

    private function asAisha()
    {
        return $this->actingAs($this->aishaUser);
    }

    private function approve(EmployeeRequest $r, array $data = [])
    {
        return $this->actingAs($this->admin)->post("/app/requests/{$r->id}/approve", $data);
    }

    // ---- Leave and sickness ----

    public function test_leave_request_goes_to_hr_and_approving_adds_the_absence(): void
    {
        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16', 'note' => 'Family visit'])
            ->assertRedirect('/me/requests')->assertSessionHas('success');
        $r = EmployeeRequest::sole();
        $this->assertSame(['leave', 'annual', 'pending'], [$r->kind, $r->leave_type->value, $r->status]);

        $this->actingAs($this->admin)->get('/app/requests')->assertInertia(fn (Assert $p) => $p->component('App/Requests/Index')
            ->where('waiting.0.who', 'Aisha Rahman')
            ->where('waiting.0.summary', 'Annual leave: 12 Oct 2026 – 16 Oct 2026')
            ->where('waiting.0.preview', ['text' => '5 working days. No Home Office report needed.', 'tone' => 'grey']));
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('stats.requests', 1));

        $this->approve($r)->assertSessionHas('success', 'Aisha Rahman: Approved and added to the absence log.');
        $a = Absence::sole();
        $this->assertSame(['annual', 5, 'portal', 'Family visit'], [$a->type->value, $a->working_days, $a->source, $a->reason]);
        $this->assertSame([EmployeeRequest::APPROVED, 'Approved', $a->id], [$r->fresh()->status, $r->fresh()->hr_note, $r->fresh()->absence_id]);
        $this->assertTrue(AuditLog::where('action', 'employee_request.approved')->exists());
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('stats.requests', 0));
    }

    public function test_unpaid_leave_over_the_limit_shows_the_report_before_approval_and_creates_the_task(): void
    {
        Absence::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'type' => 'unpaid', 'start_date' => '2026-03-02', 'end_date' => '2026-03-09', 'working_days' => 6, 'check_status' => 'not_yet']);
        $this->asAisha()->post('/me/leave', ['type' => 'unpaid', 'start_date' => '2026-10-05', 'end_date' => '2026-10-23']);
        $r = EmployeeRequest::sole();

        $this->actingAs($this->admin)->get('/app/requests')->assertInertia(fn (Assert $p) => $p
            ->where('waiting.0.preview.tone', 'red')
            ->where('waiting.0.preview.text', 'Approving needs a Home Office report: unpaid leave over 4 weeks in 2026 (report by 6 Nov 2026).'));

        $this->approve($r)->assertSessionHas('success', 'Aisha Rahman: Approved and added to the absence log. Sponsored worker: a Home Office report task was created, deadline 6 Nov 2026.');
        $this->assertSame('Approved. HR will report this to the Home Office.', $r->fresh()->hr_note);
        $this->assertSame('Unpaid leave over 4 weeks in 2026', ReportTask::sole()->event);
    }

    public function test_overlapping_leave_is_refused_when_sent_and_when_approved(): void
    {
        Absence::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16', 'working_days' => 5, 'check_status' => 'none']);
        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-10-14', 'end_date' => '2026-10-20'])->assertSessionHasErrors('start_date');

        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-11-02', 'end_date' => '2026-11-03']);
        Absence::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'type' => 'sick_self', 'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'working_days' => 1, 'check_status' => 'none']);
        $this->approve(EmployeeRequest::sole())->assertSessionHas('error');
        $this->assertTrue(EmployeeRequest::sole()->isPending());
    }

    public function test_sickness_over_7_days_becomes_fit_note_sickness_and_files_the_attached_fit_note(): void
    {
        $this->asAisha()->post('/me/leave', ['type' => 'sick', 'start_date' => '2026-09-14', 'end_date' => '2026-09-25',
            'fit_note' => UploadedFile::fake()->create('fit-note.pdf', 40, 'application/pdf')])->assertSessionHasNoErrors();
        $r = EmployeeRequest::sole();
        $this->assertTrue($r->document->isPendingReview());

        $this->approve($r)->assertSessionHas('success');
        $a = Absence::sole();
        $this->assertSame(['sick_fit', $r->document_id], [$a->type->value, $a->fit_note_id]);
        $this->assertFalse(Document::sole()->isPendingReview());
        $this->assertSame('Recorded. Get well soon.', $r->fresh()->hr_note);
    }

    public function test_short_sickness_is_self_certified(): void
    {
        $this->asAisha()->post('/me/leave', ['type' => 'sick', 'start_date' => '2026-09-28', 'end_date' => '2026-09-29']);
        $this->approve(EmployeeRequest::sole());
        $this->assertSame('sick_self', Absence::sole()->type->value);
    }

    public function test_declining_leaves_a_note_the_employee_can_see(): void
    {
        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16']);
        $this->actingAs($this->admin)->post('/app/requests/'.EmployeeRequest::sole()->id.'/decline', ['hr_note' => 'Busy week – can you take the week after?'])->assertSessionHas('success');

        $this->assertSame(0, Absence::count());
        $this->asAisha()->get('/me/requests')->assertInertia(fn (Assert $p) => $p->component('Portal/Requests')
            ->where('rows.0.status', ['text' => 'Declined', 'tone' => 'red'])
            ->where('rows.0.hrNote', 'Busy week – can you take the week after?'));
    }

    public function test_the_leave_form_check_counts_days_and_warns(): void
    {
        $this->asAisha()->getJson('/me/leave/check?type=annual&start_date=2026-10-12&end_date=2026-10-16')
            ->assertJson(['valid' => true, 'info' => '5 working days. You would have 23 days of annual leave left.']);
        $this->getJson('/me/leave/check?type=unpaid&start_date=2026-10-12&end_date=2026-10-16')
            ->assertJsonPath('notes.0', 'Long periods of unpaid leave can affect your visa sponsorship. HR may need to talk to you before approving.');
        $this->getJson('/me/leave/check?type=sick&start_date=2026-09-14&end_date=2026-09-25')->assertJson(['needsFitNote' => true]);
        $this->getJson('/me/leave/check?type=annual&start_date=2026-10-03&end_date=2026-10-04')->assertJson(['valid' => false]);
    }

    // ---- Changes of details ----

    public function test_address_change_updates_the_record_and_is_logged_not_reported(): void
    {
        $this->asAisha()->post('/me/update-details', ['kind' => 'address', 'value' => '41 Shirley High Street, Southampton SO15 3NN', 'note' => 'Moving on 1 October'])->assertRedirect('/me/requests');
        $r = EmployeeRequest::sole();
        $this->actingAs($this->admin)->get('/app/requests')->assertInertia(fn (Assert $p) => $p->where('waiting.0.kind', 'Change of home address')->where('waiting.0.note', 'Moving on 1 October'));

        $this->approve($r)->assertSessionHas('success', 'Aisha Rahman: Record updated and logged in the change history.');
        $this->assertSame('41 Shirley High Street, Southampton SO15 3NN', $this->aisha->fresh()->address);
        $change = $this->aisha->changes()->sole();
        $this->assertSame(['Home address', '9 Onslow Road'], [$change->label, $change->old_value]);
        $this->assertSame(0, ReportTask::count());
    }

    public function test_email_and_name_changes_update_the_login_too(): void
    {
        $this->asAisha()->post('/me/update-details', ['kind' => 'email', 'value' => 'aisha.new@example.com']);
        $this->asAisha()->post('/me/update-details', ['kind' => 'name', 'value' => 'Aisha Khan']);
        foreach (EmployeeRequest::orderBy('id')->get() as $r) {
            $this->approve($r)->assertSessionHas('success');
        }
        $this->assertSame(['aisha.new@example.com', 'Aisha Khan'], [$this->aishaUser->fresh()->email, $this->aishaUser->fresh()->name]);
    }

    public function test_new_visa_updates_expiry_and_follow_up_and_reminds_hr(): void
    {
        $this->asAisha()->post('/me/update-details', ['kind' => 'visa', 'value' => '2029-12-10', 'note' => 'Extension granted']);
        $r = EmployeeRequest::sole();
        $this->actingAs($this->admin)->get('/app/requests')->assertInertia(fn (Assert $p) => $p->where('waiting.0.preview.text', 'Do a new right-to-work check with their share code before approving.'));

        $this->approve($r)->assertSessionHas('success', 'Aisha Rahman: Visa expiry and follow-up check updated. Now do a new right-to-work check with their share code and upload the result.');
        $a = $this->aisha->fresh();
        $this->assertSame(['2029-12-10', '2029-12-10'], [$a->visa_expiry->format('Y-m-d'), $a->follow_up_check_due->format('Y-m-d')]);
        $this->assertSame('Updated. HR will do a new right-to-work check with your share code.', $r->fresh()->hr_note);
    }

    public function test_people_without_a_visa_cannot_send_a_visa_change_and_bad_values_are_refused(): void
    {
        $this->actingAs($this->jamesUser)->post('/me/update-details', ['kind' => 'visa', 'value' => '2029-12-10'])->assertStatus(422);
        $this->actingAs($this->jamesUser)->get('/me/update-details')->assertInertia(fn (Assert $p) => $p->component('Portal/Change')->where('kinds', fn ($k) => ! collect($k)->contains('value', 'visa')));
        $this->asAisha()->post('/me/update-details', ['kind' => 'email', 'value' => 'not-an-email'])->assertSessionHasErrors('value');
        $this->asAisha()->post('/me/update-details', ['kind' => 'visa', 'value' => '2026-01-01'])->assertSessionHasErrors('value');
        $this->assertSame(0, EmployeeRequest::count());
    }

    // ---- Documents ----

    public function test_requested_document_waits_for_hr_then_is_filed(): void
    {
        $req = DocumentRequest::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'passport']);
        $this->asAisha()->get('/me/documents')->assertInertia(fn (Assert $p) => $p->component('Portal/Documents')->where('requested.0.label', 'Passport / identity'));
        $this->get('/me/requests')->assertInertia(fn (Assert $p) => $p->where('rows.0.status', ['text' => 'Action needed', 'tone' => 'amber']));

        $this->post('/me/documents', ['document_request_id' => $req->id, 'file' => UploadedFile::fake()->create('passport.pdf', 30, 'application/pdf')])->assertSessionHas('success');
        $doc = Document::sole();
        $this->assertTrue($doc->isPendingReview());
        $this->assertSame(DocumentRequest::STATUS_AWAITING, $req->fresh()->status, 'not received until HR accepts it');
        $this->get('/me/documents')->assertInertia(fn (Assert $p) => $p->has('requested', 0)->where('onFile.0.waiting', true));
        $this->actingAs($this->admin)->get("/app/employees/{$this->aisha->id}")->assertInertia(fn (Assert $p) => $p
            ->where('employee.documents.have', 0)
            ->where('documents.1.status.text', 'Uploaded – review in Requests'));

        $this->approve(EmployeeRequest::sole())->assertSessionHas('success', 'Aisha Rahman: Filed under Passport / identity.');
        $this->assertFalse($doc->fresh()->isPendingReview());
        $this->assertSame([DocumentRequest::STATUS_RECEIVED, $doc->id], [$req->fresh()->status, $req->fresh()->document_id]);
        $this->get("/app/employees/{$this->aisha->id}")->assertInertia(fn (Assert $p) => $p->where('employee.documents.have', 1));
    }

    public function test_declining_a_document_deletes_it_and_the_request_needs_action_again(): void
    {
        $req = DocumentRequest::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'passport']);
        $this->asAisha()->post('/me/documents', ['document_request_id' => $req->id, 'file' => UploadedFile::fake()->create('blurry.pdf', 30, 'application/pdf')]);
        $path = Document::sole()->path;

        $this->actingAs($this->admin)->post('/app/requests/'.EmployeeRequest::sole()->id.'/decline', ['hr_note' => 'Too blurry, please take a clearer photo.']);
        $this->assertSame(0, Document::count());
        Storage::disk('local')->assertMissing($path);
        $this->asAisha()->get('/me/documents')->assertInertia(fn (Assert $p) => $p->has('requested', 1));
    }

    public function test_employees_can_send_documents_unprompted(): void
    {
        $this->asAisha()->post('/me/documents', ['category' => 'absence', 'file' => UploadedFile::fake()->create('fit-note.pdf', 30, 'application/pdf'), 'note' => 'For May'])->assertSessionHas('success');
        $this->asAisha()->post('/me/documents', ['category' => 'rtw', 'file' => UploadedFile::fake()->create('x.pdf', 30, 'application/pdf')])->assertSessionHasErrors('category');
        $this->asAisha()->post('/me/documents', ['category' => 'other', 'file' => UploadedFile::fake()->create('notes.txt', 3, 'text/plain')])->assertSessionHasErrors('file');
        $this->assertSame('Absence evidence (fit notes, approvals): fit-note.pdf', EmployeeRequest::with('document')->sole()->summary());
    }

    public function test_employees_see_only_their_own_documents_and_not_recruitment_evidence(): void
    {
        $contract = Document::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'contract', 'original_name' => 'contract.pdf', 'path' => 'documents/x/contract.enc', 'mime' => 'application/pdf', 'size' => 10]);
        Storage::disk('local')->put($contract->path, encrypt('%PDF contract', false));
        $recruit = Document::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'recruit', 'original_name' => 'interview-notes.pdf', 'path' => 'documents/x/r.enc', 'mime' => 'application/pdf', 'size' => 10]);
        $jamesDoc = Document::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->james->id, 'category' => 'contract', 'original_name' => 'james.pdf', 'path' => 'documents/x/j.enc', 'mime' => 'application/pdf', 'size' => 10]);

        $this->asAisha()->get('/me/documents')->assertInertia(fn (Assert $p) => $p->has('onFile', 1)->where('onFile.0.name', 'contract.pdf'));
        $this->get("/me/documents/{$contract->id}")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'document.viewed')->where('subject_id', $contract->id)->exists());
        $this->get("/me/documents/{$recruit->id}")->assertNotFound();
        $this->get("/me/documents/{$jamesDoc->id}")->assertNotFound();
    }

    // ---- Access ----

    public function test_employees_cannot_use_the_inbox_and_other_businesses_cannot_decide(): void
    {
        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16']);
        $r = EmployeeRequest::sole();

        $this->asAisha()->get('/app/requests')->assertRedirect('/me');
        $this->asAisha()->post("/app/requests/{$r->id}/approve")->assertRedirect('/me');
        $this->actingAs(User::factory()->admin()->create())->post("/app/requests/{$r->id}/approve")->assertNotFound();
        $this->assertTrue($r->fresh()->isPending());
        $this->approve($r);
        $this->approve($r)->assertSessionHas('error', 'This request has already been decided.');
    }

    public function test_portal_pages_show_the_employees_own_information(): void
    {
        DocumentRequest::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'passport']);
        $this->asAisha()->post('/me/leave', ['type' => 'annual', 'start_date' => '2026-10-12', 'end_date' => '2026-10-16']);

        $this->get('/me')->assertInertia(fn (Assert $p) => $p->component('Portal/Home')
            ->where('first', 'Aisha')
            ->where('leave', ['year' => '2026', 'allowance' => '28', 'taken' => 0, 'pending' => 5, 'left' => '23'])
            ->where('documentsNeeded', 1)
            ->where('rightToWork.title', 'Skilled Worker until 10 Dec 2026')
            ->where('rightToWork.tone', 'amber')
            ->has('recent', 1));
        $this->get('/me/leave')->assertInertia(fn (Assert $p) => $p->component('Portal/Leave')->has('types', 5));
        $this->get('/me/details')->assertInertia(fn (Assert $p) => $p->component('Portal/Details')->where('sections.0.fields.0.value', 'Aisha Rahman')->where('twoFactor.enabled', false));
        $this->actingAs($this->jamesUser)->get('/me')->assertInertia(fn (Assert $p) => $p->where('rightToWork', ['title' => 'No time limit', 'note' => 'No action needed.', 'tone' => 'green'])->where('recent', []));
    }

    public function test_a_login_without_an_employee_record_is_told_so(): void
    {
        $this->actingAs(User::factory()->create(['business_id' => $this->admin->business_id]))->get('/me')->assertForbidden();
    }

    public function test_employees_can_turn_on_two_step_sign_in(): void
    {
        $this->asAisha()->post('/me/security/two-factor')->assertRedirect();
        $secret = $this->aishaUser->fresh()->two_factor_secret;
        $this->get('/me/details')->assertInertia(fn (Assert $p) => $p->has('twoFactor.setup.secret'));
        $this->post('/me/security/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/me/security/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHas('success');
        $this->assertTrue($this->aishaUser->fresh()->needsTwoFactor());

        auth('web')->logout();
        $this->post('/login/lookup', ['email' => 'aisha@example.com']);
        $this->post('/login', ['password' => 'password'])->assertRedirect('/login/verify');

        $this->actingAs($this->aishaUser->fresh())->delete('/me/security/two-factor', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->delete('/me/security/two-factor', ['password' => 'password'])->assertSessionHas('success');
        $this->assertFalse($this->aishaUser->fresh()->needsTwoFactor());
    }
}
