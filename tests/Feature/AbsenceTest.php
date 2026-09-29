<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $aisha;
    private Employee $james;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-09-29 10:00');
        $this->seed(BankHolidaySeeder::class); // 31 Aug 2026 is a bank holiday in these tests
        $this->setUpBusiness();
        $this->aisha = Employee::factory()->sponsored()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'Aisha Rahman']);
        $this->james = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'James Carter']);
    }

    private function record(Employee $e, string $type, string $start, string $end, array $extra = [])
    {
        return $this->actingAs($this->admin)->post('/app/absence', ['employee_id' => $e->id, 'type' => $type, 'start_date' => $start, 'end_date' => $end, 'reason' => 'Test', ...$extra]);
    }

    public function test_live_check_returns_the_home_office_result_without_saving(): void
    {
        $this->actingAs($this->admin)->getJson("/app/absence/check?employee_id={$this->aisha->id}&type=unpaid&start_date=2026-06-01&end_date=2026-06-29")
            ->assertOk()
            ->assertJson(['status' => 'report', 'days' => 21, 'trigger' => '2026-06-29', 'deadline' => '2026-07-13']);
        $this->assertSame(0, Absence::count());
    }

    public function test_saving_stores_the_check_result_and_says_when_to_report(): void
    {
        $this->record($this->aisha, 'unauthorised', '2026-08-24', '2026-09-07')
            ->assertRedirect('/app/absence')
            ->assertSessionHas('success', 'Saved. Sponsored worker: report this on the Sponsor Management System by 21 Sep 2026.');

        $a = Absence::sole();
        $this->assertSame(['report', 10, '2026-09-07', '2026-09-21'], [$a->check_status, $a->working_days, $a->report_trigger_on->format('Y-m-d'), $a->report_deadline->format('Y-m-d')]);
        $this->assertSame('Unauthorised absence reached 10 consecutive working days', $a->report_event);
        $this->assertTrue(AuditLog::where('action', 'absence.recorded')->exists());
    }

    public function test_earlier_absences_in_the_database_count(): void
    {
        $this->record($this->aisha, 'unpaid', '2026-03-02', '2026-03-09');
        $this->record($this->aisha, 'unpaid', '2026-10-05', '2026-10-23')->assertSessionHas('success', 'Saved. Sponsored worker: report this on the Sponsor Management System by 6 Nov 2026.');
    }

    public function test_non_sponsored_workers_are_logged_only(): void
    {
        $this->record($this->james, 'unauthorised', '2026-08-24', '2026-09-07')->assertSessionHas('success', 'Saved in the absence log.');
        $this->assertSame(['none', null], [Absence::sole()->check_status, Absence::sole()->report_deadline]);
    }

    public function test_overlapping_absences_and_bad_dates_are_rejected(): void
    {
        $this->record($this->aisha, 'annual', '2026-08-10', '2026-08-14');
        $this->record($this->aisha, 'unpaid', '2026-08-12', '2026-08-20')->assertSessionHasErrors('start_date');
        $this->record($this->aisha, 'annual', '2026-08-20', '2026-08-18')->assertSessionHasErrors('end_date');
        $this->record($this->aisha, 'annual', '2026-10-03', '2026-10-04')->assertSessionHasErrors('start_date'); // weekend
        $this->assertSame(1, Absence::count());
    }

    public function test_fit_note_is_optional_flagged_and_can_be_added_later(): void
    {
        $this->record($this->aisha, 'sick_fit', '2026-09-14', '2026-09-25')->assertSessionHas('success', 'Saved in the absence log. Upload the fit note when you have it.');
        $a = Absence::sole();
        $this->assertTrue($a->needsFitNote());
        $this->get('/app/absence')->assertInertia(fn (Assert $p) => $p->where('absences.data.0.fitNoteMissing', true));

        $this->post("/app/absence/{$a->id}/fit-note", ['fit_note' => UploadedFile::fake()->create('fit-note.pdf', 50, 'application/pdf')])->assertSessionHas('success');
        $this->assertFalse($a->fresh()->needsFitNote());
        $this->assertSame('absence', Document::sole()->category->value);
    }

    public function test_fit_note_can_be_uploaded_with_the_absence(): void
    {
        $this->record($this->aisha, 'sick_fit', '2026-09-14', '2026-09-25', ['fit_note' => UploadedFile::fake()->create('fit-note.pdf', 50, 'application/pdf')]);
        $this->assertNotNull(Absence::sole()->fit_note_id);
    }

    public function test_absence_reasons_are_short(): void
    {
        $this->record($this->aisha, 'sick_self', '2026-09-28', '2026-09-29', ['reason' => str_repeat('x', 151)])->assertSessionHasErrors('reason');
    }

    public function test_log_filters_by_employee_type_and_date_range(): void
    {
        $this->record($this->aisha, 'annual', '2026-08-10', '2026-08-14');
        $this->record($this->aisha, 'sick_self', '2026-09-14', '2026-09-15');
        $this->record($this->james, 'annual', '2026-07-20', '2026-07-24');

        $this->get('/app/absence')->assertInertia(fn (Assert $p) => $p->component('App/Absence/Index')
            ->where('absences.total', 3)->where('absences.data.0.type', 'Sickness – self-certified (1–7 days)'));
        $this->get("/app/absence?employee={$this->james->id}")->assertInertia(fn (Assert $p) => $p->where('absences.total', 1));
        $this->get('/app/absence?type=annual')->assertInertia(fn (Assert $p) => $p->where('absences.total', 2));
        $this->get('/app/absence?from=2026-08-01&to=2026-08-31')->assertInertia(fn (Assert $p) => $p->where('absences.total', 1)->where('table.from', '2026-08-01'));
        $this->get('/app/absence?q=james')->assertInertia(fn (Assert $p) => $p->where('absences.total', 1));
    }

    public function test_other_businesses_cannot_see_record_or_remove_absences(): void
    {
        $this->record($this->aisha, 'annual', '2026-08-10', '2026-08-14');
        $a = Absence::sole();
        $other = User::factory()->admin()->create();

        $this->actingAs($other)->get('/app/absence')->assertInertia(fn (Assert $p) => $p->where('absences.total', 0));
        $this->delete("/app/absence/{$a->id}")->assertNotFound();
        $this->post('/app/absence', ['employee_id' => $this->aisha->id, 'type' => 'annual', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06'])->assertSessionHasErrors('employee_id');
        $this->getJson("/app/absence/check?employee_id={$this->aisha->id}&type=annual&start_date=2026-10-05&end_date=2026-10-06")->assertStatus(422);
    }

    public function test_removing_an_absence_is_audited(): void
    {
        $this->record($this->aisha, 'annual', '2026-08-10', '2026-08-14');
        $this->delete('/app/absence/'.Absence::sole()->id)->assertSessionHas('success');
        $this->assertSame(0, Absence::count());
        $this->assertTrue(AuditLog::where('action', 'absence.deleted')->exists());
    }

    public function test_csv_and_pdf_exports_follow_the_filters_and_are_audited(): void
    {
        $this->record($this->aisha, 'unauthorised', '2026-08-24', '2026-09-07');
        $this->record($this->james, 'annual', '2026-07-20', '2026-07-24', ['reason' => '=HYPERLINK("x")']);

        $csv = $this->get('/app/absence/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('"Aisha Rahman","Unauthorised absence",2026-08-24,2026-09-07,10,Unpaid,Test,"Report by 21 Sep 2026"', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'formula injection is neutralised');

        $filtered = $this->get("/app/absence/export.csv?employee={$this->james->id}")->streamedContent();
        $this->assertStringNotContainsString('Aisha', $filtered);

        $pdf = $this->get('/app/absence/export.pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame(3, AuditLog::where('action', 'absence.exported')->count());
    }

    public function test_profile_absence_tab_shows_unpaid_days_and_annual_leave_left(): void
    {
        $this->record($this->aisha, 'unpaid', '2026-03-02', '2026-03-09');
        $this->record($this->aisha, 'annual', '2026-08-10', '2026-08-14');
        $this->record($this->aisha, 'annual', '2025-08-11', '2025-08-15'); // last year: not counted

        $this->get("/app/employees/{$this->aisha->id}")->assertInertia(fn (Assert $p) => $p
            ->where('absence.unpaid', ['used' => 6, 'limit' => '20'])
            ->where('absence.annual', ['allowance' => '28', 'taken' => 5, 'left' => '23'])
            ->has('absence.rows', 3));
    }

    public function test_rule_settings_change_the_check(): void
    {
        $this->actingAs($this->admin)->put('/app/settings/rules', [
            'unpaid_limit_weeks' => 4, 'unpaid_leave_year' => 'calendar', 'unauthorised_trigger_days' => 5, 'worker_report_deadline_days' => 10,
            'company_report_deadline_days' => 20, 'exempt_absence_types' => ['sick_self', 'sick_fit'], 'self_cert_max_days' => 7,
            'expiry_alert_days' => '90, 30, 60', 'payslip_freshness_days' => 35, 'retention_years' => 1, 'rtw_retention_years' => 2, 'annual_leave_weeks' => 5.6,
        ])->assertSessionHas('success', 'Compliance rules saved. New checks use them from now on.');

        $business = $this->admin->business->fresh();
        $this->assertSame(5, $business->rule('unauthorised_trigger_days'));
        $this->assertSame([90, 60, 30], $business->rule('expiry_alert_days'));
        $this->assertTrue(AuditLog::where('action', 'business.rules_changed')->exists());

        $this->getJson("/app/absence/check?employee_id={$this->aisha->id}&type=unauthorised&start_date=2026-09-28&end_date=2026-10-02")
            ->assertJson(['status' => 'report', 'trigger' => '2026-10-02']);
    }

    public function test_rule_settings_are_validated(): void
    {
        $this->actingAs($this->admin)->put('/app/settings/rules', ['unpaid_leave_year' => 'weekly', 'expiry_alert_days' => 'soon', 'exempt_absence_types' => ['unpaid']])
            ->assertSessionHasErrors(['unpaid_leave_year', 'expiry_alert_days', 'exempt_absence_types.0', 'unpaid_limit_weeks']);
    }
}
