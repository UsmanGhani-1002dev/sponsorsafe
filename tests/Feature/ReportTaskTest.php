<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Models\User;
use App\Models\WorkSite;
use App\Services\AbsenceCheck;
use App\Services\ReportTasks;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** compliance-rules §4 and go-live acceptance tests 3–6 (§13). */
class ReportTaskTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $aisha;   // sponsored
    private Employee $james;   // not sponsored

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo('2026-09-28 10:00'); // a Monday
        $this->setUpBusiness();
        $this->aisha = Employee::factory()->sponsored()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'Aisha Rahman', 'salary' => 42000, 'job_title' => 'Sales Assistant']);
        $this->james = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'James Carter', 'salary' => 24000]);
        $this->actingAs($this->admin);
    }

    // ---- Acceptance tests ----

    public function test_acceptance_3_adding_a_work_site_creates_a_company_task_due_in_20_working_days(): void
    {
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street, Southampton SO14 1AA'])
            ->assertSessionHas('success', 'Third shop added. A Home Office report task was created: add the work address on the Sponsor Management System by 26 Oct 2026.');

        $task = ReportTask::sole();
        $this->assertSame([ReportTask::COMPANY, null, 'New work address added: Third shop', '2026-09-28', '2026-10-26', 'work_site'],
            [$task->level, $task->employee_id, $task->event, $task->trigger_on->format('Y-m-d'), $task->deadline->format('Y-m-d'), $task->source]);
    }

    public function test_acceptance_4_moving_a_sponsored_worker_creates_a_10_day_task_and_a_non_sponsored_move_is_logged_only(): void
    {
        $second = WorkSite::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'Second shop']);

        $this->post('/app/settings/move', ['employee_id' => $this->aisha->id, 'work_site_id' => $second->id])
            ->assertSessionHas('success', 'Aisha Rahman moved and logged in their change history. Sponsored worker: a Home Office report task was created, deadline 12 Oct 2026.');
        $task = ReportTask::sole();
        $this->assertSame(['Work location changed to Second shop', '2026-10-12', $this->aisha->id], [$task->event, $task->deadline->format('Y-m-d'), $task->employee_id]);

        $this->post('/app/settings/move', ['employee_id' => $this->james->id, 'work_site_id' => $second->id])->assertSessionHas('success');
        $this->assertSame(1, ReportTask::count(), 'no task for a non-sponsored worker');
        $this->assertSame(2, $this->james->changes()->count() + $this->aisha->changes()->count(), 'both moves logged');
    }

    public function test_acceptance_5_a_salary_reduction_creates_a_task_and_an_increase_does_not(): void
    {
        $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => 'salary_increase', 'value' => '45000'])->assertSessionHas('success', 'Saved and logged in the history. No Home Office report needed.');
        $this->assertSame(0, ReportTask::count());

        $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => 'salary_reduction', 'value' => '40000'])
            ->assertSessionHas('success', 'Saved and logged. A Home Office report task was created: report this change on the Sponsor Management System by 12 Oct 2026.');
        $task = ReportTask::sole();
        $this->assertSame('Salary reduced (£45,000.00 to £40,000.00)', $task->event);
        $this->assertSame($task->id, $this->aisha->changes()->where('label', 'Salary – reduction')->value('report_task_id'));
    }

    public function test_acceptance_6_mark_reported_requires_date_and_reported_by_and_turns_the_absence_badge_green(): void
    {
        $this->post('/app/absence', ['employee_id' => $this->aisha->id, 'type' => 'unauthorised', 'start_date' => '2026-08-24', 'end_date' => '2026-09-07']);
        $task = ReportTask::sole();
        $this->get('/app/absence')->assertInertia(fn (Assert $p) => $p->where('absences.data.0.homeOffice', ['text' => 'Report by 21 Sep 2026', 'tone' => 'red']));

        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '', 'reported_by' => ''])->assertSessionHasErrors(['reported_on', 'reported_by']);
        $this->assertTrue($task->fresh()->isPending());

        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '2026-09-15', 'reported_by' => 'Nadia Khan', 'notes' => 'SMS-4471920'])
            ->assertSessionHas('success', 'Marked as reported to the Home Office.');
        $this->assertSame([ReportTask::REPORTED, 'On 15 Sep 2026 by Nadia Khan · SMS-4471920'], [$task->fresh()->status, $task->fresh()->doneText()]);
        $this->get('/app/absence')->assertInertia(fn (Assert $p) => $p->where('absences.data.0.homeOffice', ['text' => 'Reported to Home Office', 'tone' => 'green']));
        $this->assertTrue(AuditLog::where('action', 'report_task.reported')->exists());
    }

    // ---- Triggers ----

    public function test_absence_tasks_use_the_stored_trigger_and_deadline_and_only_for_sponsored_workers(): void
    {
        $this->post('/app/absence', ['employee_id' => $this->james->id, 'type' => 'unauthorised', 'start_date' => '2026-08-24', 'end_date' => '2026-09-07']);
        $this->assertSame(0, ReportTask::count());

        $this->post('/app/absence', ['employee_id' => $this->aisha->id, 'type' => 'unpaid', 'start_date' => '2026-06-01', 'end_date' => '2026-06-29']);
        $task = ReportTask::sole();
        $this->assertSame(['Unpaid leave over 4 weeks in 2026', '2026-06-29', '2026-07-13', 'absence'],
            [$task->event, $task->trigger_on->format('Y-m-d'), $task->deadline->format('Y-m-d'), $task->source]);
        $this->assertTrue($task->subject->is(Absence::where('employee_id', $this->aisha->id)->sole()));
    }

    public function test_reportable_record_changes_create_tasks_and_others_do_not(): void
    {
        foreach ([['job_title', 'Store Supervisor'], ['soc', '7111'], ['hours', '40']] as [$type, $value]) {
            $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => $type, 'value' => $value])->assertSessionHasNoErrors();
        }
        foreach ([['phone', '07700 900999'], ['address', '1 New Road, Southampton'], ['email', 'aisha.new@example.com']] as [$type, $value]) {
            $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => $type, 'value' => $value])->assertSessionHasNoErrors();
        }
        $this->post("/app/employees/{$this->james->id}/changes", ['type' => 'job_title', 'value' => 'Supervisor']);

        $this->assertSame(['Job title changed (Sales Assistant to Store Supervisor)', 'SOC code / duties changed (7132 to 7111)', 'Contracted hours changed (37.5 hours to 40 hours)'],
            ReportTask::orderBy('id')->pluck('event')->all());
    }

    public function test_closing_a_site_and_key_personnel_changes_create_company_tasks(): void
    {
        $empty = WorkSite::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'Old shop']);
        $this->post("/app/settings/sites/{$empty->id}/close");
        $this->post('/app/settings/people', ['role' => 'authorising_officer', 'name' => 'Imran Shah']);
        $person = $this->admin->business->keyPersonnel()->sole();
        $this->put("/app/settings/people/{$person->id}", ['role' => 'authorising_officer', 'name' => 'Imran Shah', 'email' => 'imran@example.com']);
        $this->put("/app/settings/people/{$person->id}", ['role' => 'authorising_officer', 'name' => 'Imran Shah', 'email' => 'imran@example.com']); // no change, no task
        $this->delete("/app/settings/people/{$person->id}");

        $this->assertSame(['Work address closed: Old shop', 'Authorising Officer added: Imran Shah', 'Authorising Officer details changed: Imran Shah', 'Authorising Officer removed: Imran Shah'],
            ReportTask::orderBy('id')->pluck('event')->all());
        $this->assertSame([ReportTask::COMPANY], ReportTask::distinct()->pluck('level')->all());
        $this->assertSame(['2026-10-26'], ReportTask::distinct()->pluck('deadline')->map->format('Y-m-d')->all(), '20 working days');
    }

    public function test_the_company_deadline_is_a_setting(): void
    {
        $this->admin->business->update(['settings' => ['company_report_deadline_days' => 5]]);
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street']);
        $this->assertSame('2026-10-05', ReportTask::sole()->deadline->format('Y-m-d'));
    }

    // ---- End of employment (§10) ----

    public function test_ending_a_sponsored_workers_employment_creates_a_task_from_the_last_day(): void
    {
        $user = User::factory()->create(['business_id' => $this->admin->business_id]);
        $this->aisha->update(['user_id' => $user->id]);
        DocumentRequest::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'category' => 'passport']);

        $this->post("/app/employees/{$this->aisha->id}/end", ['last_day' => '2026-09-30', 'reason' => 'Resigned'])
            ->assertSessionHas('success', 'Employment ended for Aisha Rahman. Portal access is off. Remember to give them their P45 and final payslip. Sponsored worker: report this on the Sponsor Management System by 14 Oct 2026.');

        $a = $this->aisha->fresh();
        $this->assertSame(['2026-09-30', 'Resigned', '2027-09-30', '2028-09-30'], [$a->ended_on->format('Y-m-d'), $a->end_reason, $a->delete_after->format('Y-m-d'), $a->rtw_delete_after->format('Y-m-d')]);
        $this->assertFalse($user->fresh()->active, 'portal access off');
        $this->assertSame(DocumentRequest::STATUS_CANCELLED, DocumentRequest::sole()->status);
        $task = ReportTask::sole();
        $this->assertSame(['Left: Resigned (last working day 30 Sep 2026)', '2026-09-30', '2026-10-14', 'leaver'], [$task->event, $task->trigger_on->format('Y-m-d'), $task->deadline->format('Y-m-d'), $task->source]);
        $this->assertSame('Employment ended', $a->changes()->latest('id')->value('label'));

        $this->post("/app/employees/{$this->aisha->id}/end", ['last_day' => '2026-09-30', 'reason' => 'Resigned'])->assertSessionHas('error', 'Employment has already ended.');
    }

    public function test_did_not_start_and_non_sponsored_leavers(): void
    {
        $this->aisha->update(['start_date' => '2026-10-05']);
        $this->post("/app/employees/{$this->aisha->id}/end", ['last_day' => '2026-09-28', 'reason' => 'Resigned'])->assertSessionHasErrors('last_day');
        $this->post("/app/employees/{$this->aisha->id}/end", ['last_day' => '2026-10-05', 'reason' => 'Did not start'])->assertSessionHasNoErrors();
        $this->assertSame('Did not start work', ReportTask::sole()->event);

        $this->post("/app/employees/{$this->james->id}/end", ['last_day' => '2026-09-28', 'reason' => 'Redundancy'])->assertSessionHasNoErrors();
        $this->assertSame(1, ReportTask::count(), 'no task for a non-sponsored leaver');
        $this->get('/app/employees')->assertInertia(fn (Assert $p) => $p->where('plan.used', 0));
    }

    // ---- Completing tasks ----

    public function test_not_required_needs_a_reason_and_tasks_can_be_reopened(): void
    {
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street']);
        $task = ReportTask::sole();

        $this->post("/app/reports/{$task->id}/not-required", ['notes' => ''])->assertSessionHasErrors(['notes' => 'Enter the reason it is not required in the notes field.']);
        $this->post("/app/reports/{$task->id}/not-required", ['notes' => 'Site never opened'])->assertSessionHas('success');
        $this->assertSame([ReportTask::NOT_REQUIRED, 'Reason: Site never opened'], [$task->fresh()->status, $task->fresh()->doneText()]);

        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '2026-09-28', 'reported_by' => 'Nadia'])->assertSessionHas('error', 'This task has already been completed.');
        $this->post("/app/reports/{$task->id}/reopen")->assertSessionHas('success', 'Task reopened.');
        $this->assertTrue($task->fresh()->isPending());
        $this->assertTrue(AuditLog::where('action', 'report_task.reopened')->exists());
    }

    public function test_the_reported_date_cannot_be_in_the_future_or_before_the_event(): void
    {
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street']);
        $task = ReportTask::sole();

        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '2026-09-29', 'reported_by' => 'Nadia'])->assertSessionHasErrors('reported_on');
        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '2026-09-25', 'reported_by' => 'Nadia'])->assertSessionHasErrors('reported_on');
    }

    public function test_removing_an_absence_removes_its_pending_task_but_not_a_reported_one(): void
    {
        $this->post('/app/absence', ['employee_id' => $this->aisha->id, 'type' => 'unauthorised', 'start_date' => '2026-08-24', 'end_date' => '2026-09-07']);
        $this->delete('/app/absence/'.Absence::sole()->id)->assertSessionHas('success');
        $this->assertSame(0, ReportTask::count());

        $this->post('/app/absence', ['employee_id' => $this->aisha->id, 'type' => 'unauthorised', 'start_date' => '2026-08-24', 'end_date' => '2026-09-07']);
        $this->post('/app/reports/'.ReportTask::sole()->id.'/reported', ['reported_on' => '2026-09-15', 'reported_by' => 'Nadia']);
        $this->delete('/app/absence/'.Absence::sole()->id)->assertSessionHas('error');
        $this->assertSame(1, Absence::count());
    }

    public function test_manual_tasks_for_events_the_rules_cannot_see(): void
    {
        $this->post('/app/reports', ['level' => 'worker', 'event' => 'Suspected breach of visa conditions', 'trigger_on' => '2026-09-28'])->assertSessionHasErrors('employee_id');
        $this->post('/app/reports', ['level' => 'worker', 'employee_id' => $this->aisha->id, 'event' => 'Suspected breach of visa conditions', 'details' => 'Working a second job', 'trigger_on' => '2026-09-28'])
            ->assertSessionHas('success', 'Home Office report task created. Report it on the Sponsor Management System by 12 Oct 2026.');
        $this->post('/app/reports', ['level' => 'company', 'event' => 'Registered or trading address changed', 'trigger_on' => '2026-09-28'])->assertSessionHasNoErrors();

        $this->assertSame([['Suspected breach of visa conditions: Working a second job', 'worker', '2026-10-12'], ['Registered or trading address changed', 'company', '2026-10-26']],
            ReportTask::orderBy('id')->get()->map(fn ($t) => [$t->event, $t->level, $t->deadline->format('Y-m-d')])->all());
    }

    // ---- Screens ----

    public function test_reports_list_puts_pending_first_by_deadline_and_filters(): void
    {
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street']);          // due 26 Oct
        $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => 'hours', 'value' => '40']);  // due 12 Oct
        $this->post('/app/settings/sites', ['name' => 'Fourth shop', 'address' => '2 High Street']);
        $this->post('/app/reports/'.ReportTask::where('event', 'like', '%Fourth%')->value('id').'/not-required', ['notes' => 'Duplicate']);

        $this->get('/app/reports')->assertInertia(fn (Assert $p) => $p->component('App/Reports/Index')
            ->where('tasks.total', 3)
            ->where('tasks.data.0.event', 'Contracted hours changed (37.5 hours to 40 hours)')
            ->where('tasks.data.0.who', 'Aisha Rahman')
            ->where('tasks.data.0.badge', ['text' => '10 working days left', 'tone' => 'blue'])
            ->where('tasks.data.1.who', 'Company')
            ->where('tasks.data.2.pending', false));
        $this->get('/app/reports?status=pending')->assertInertia(fn (Assert $p) => $p->where('tasks.total', 2));
        $this->get('/app/reports?level=company')->assertInertia(fn (Assert $p) => $p->where('tasks.total', 2));
        $this->get('/app/reports?q=aisha')->assertInertia(fn (Assert $p) => $p->where('tasks.total', 1));
    }

    public function test_profile_list_and_dashboard_show_the_tasks(): void
    {
        $this->post("/app/employees/{$this->aisha->id}/changes", ['type' => 'job_title', 'value' => 'Store Supervisor']);
        $this->aisha->update(['visa_expiry' => '2026-12-10']);

        $this->get("/app/employees/{$this->aisha->id}")->assertInertia(fn (Assert $p) => $p
            ->has('tasks', 1)
            ->where('employee.homeOffice', ['text' => 'Home Office: 1 pending', 'tone' => 'amber'])
            ->where('history.0.homeOffice.tone', 'blue'));
        $this->get('/app/employees')->assertInertia(fn (Assert $p) => $p->where('employees.data.0.pendingReports', 1));

        $this->get('/app')->assertInertia(fn (Assert $p) => $p->component('App/Dashboard')
            ->where('stats.pending', 1)
            ->where('stats.urgent', 0)
            ->where('stats.expiring', 1)
            ->has('deadlines', 1)
            ->where('watchlist.0.name', 'Aisha Rahman'));

        // Counts are cached but cleared when a task is completed.
        $this->post('/app/reports/'.ReportTask::sole()->id.'/reported', ['reported_on' => '2026-09-28', 'reported_by' => 'Nadia']);
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('stats.pending', 0));
    }

    public function test_other_businesses_cannot_see_or_complete_tasks(): void
    {
        $this->post('/app/settings/sites', ['name' => 'Third shop', 'address' => '1 High Street']);
        $task = ReportTask::sole();
        $other = User::factory()->admin()->create();

        $this->actingAs($other)->get('/app/reports')->assertInertia(fn (Assert $p) => $p->where('tasks.total', 0));
        $this->post("/app/reports/{$task->id}/reported", ['reported_on' => '2026-09-28', 'reported_by' => 'X'])->assertNotFound();
        $this->post("/app/reports/{$task->id}/reopen")->assertNotFound();
        $this->post('/app/reports', ['level' => 'worker', 'employee_id' => $this->aisha->id, 'event' => 'X', 'trigger_on' => '2026-09-28'])->assertSessionHasErrors('employee_id');
        $this->assertTrue($task->fresh()->isPending());
    }

    public function test_backfill_creates_tasks_for_reportable_records_made_before_this_stage(): void
    {
        $absence = Absence::create([
            'business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'type' => 'unauthorised', 'start_date' => '2026-08-24', 'end_date' => '2026-09-07',
            'working_days' => 10, 'check_status' => AbsenceCheck::REPORT, 'report_trigger_on' => '2026-09-07', 'report_deadline' => '2026-09-21',
            'report_event' => 'Unauthorised absence reached 10 consecutive working days',
        ]);
        $this->aisha->changes()->forceCreate(['business_id' => $this->admin->business_id, 'employee_id' => $this->aisha->id, 'field' => 'job_title', 'label' => 'Job title', 'old_value' => 'A', 'new_value' => 'B', 'created_at' => '2026-09-10 10:00:00']);

        ReportTasks::backfill();
        ReportTasks::backfill(); // safe to run twice

        $this->assertSame(2, ReportTask::count());
        $this->assertTrue(ReportTask::where('source', 'absence')->sole()->subject->is($absence));
        $this->assertSame('2026-09-24', ReportTask::where('source', 'change')->sole()->deadline->format('Y-m-d'));
    }
}
