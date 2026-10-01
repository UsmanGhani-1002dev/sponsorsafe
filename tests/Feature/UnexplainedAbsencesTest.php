<?php

namespace Tests\Feature;

use App\Enums\AbsenceType;
use App\Models\Business;
use App\Models\ClockIn;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\UnexplainedAbsence;
use App\Models\User;
use App\Notifications\ComplianceDigest;
use App\Services\AbsenceRecorder;
use Carbon\CarbonImmutable;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Stage 8b: unexplained absences (compliance-rules §11) — clock-in CSV, the check, classifying, work days. */
class UnexplainedAbsencesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        Notification::fake();
        $this->business = Business::factory()->create(['settings' => ['clock_in_check' => true]]);
        $this->admin = User::factory()->admin()->create(['business_id' => $this->business->id]);
        $this->travelTo(CarbonImmutable::parse('2026-10-09 18:00')); // a Friday
    }

    private function employee(string $name, array $attributes = []): Employee
    {
        return Employee::factory()->create(['business_id' => $this->business->id, 'full_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'start_date' => '2026-01-05', ...$attributes]);
    }

    private function upload(string $csv)
    {
        return $this->actingAs($this->admin)->post('/app/settings/clock-ins', ['file' => UploadedFile::fake()->createWithContent('clock.csv', $csv)]);
    }

    public function test_acceptance_a_scheduled_day_with_no_clock_in_and_no_absence_creates_an_alert(): void
    {
        $aisha = $this->employee('Aisha Rahman');                                               // clocked in
        $rahul = $this->employee('Rahul Mehta');                                                // nothing: flagged
        $kasia = $this->employee('Kasia Nowak');                                                // on leave
        $james = $this->employee('James Carter', ['work_days' => ['tue', 'wed', 'thu']]);         // does not work Mondays
        $this->employee('New Starter', ['start_date' => '2026-10-07']);                         // not started yet
        $this->employee('Gone Already', ['ended_on' => '2026-09-30', 'end_reason' => 'Resigned']);
        app(AbsenceRecorder::class)->record($kasia, AbsenceType::Annual, '2026-10-05', '2026-10-05', null, null, $this->admin);

        $this->upload("email,date,time\naisha.rahman@example.com,05/10/2026,08:57\n")
            ->assertSessionHas('success', 'Imported 1 clock-in(s) for 1 day(s). 1 unexplained absence(s) found: see the dashboard.');

        $alert = UnexplainedAbsence::sole();
        $this->assertSame([$rahul->id, '2026-10-05', UnexplainedAbsence::OPEN], [$alert->employee_id, $alert->date->toDateString(), $alert->status]);
        $this->assertSame('08:57:00', ClockIn::where('employee_id', $aisha->id)->sole()->first_in);
        $this->assertNotContains($james->id, UnexplainedAbsence::pluck('employee_id'));
    }

    public function test_only_days_with_clock_in_data_are_checked_and_bank_holidays_never(): void
    {
        $this->employee('Rahul Mehta');
        $aisha = $this->employee('Aisha Rahman');

        $this->artisan('absences:check-clock-ins')->expectsOutputToContain('Checked 1 business(es); 0 new unexplained absence(s).');
        $this->assertSame(0, UnexplainedAbsence::count()); // no clock-in data for today: nothing flagged

        $this->upload("email,date\naisha.rahman@example.com,2026-08-31\n"); // the August bank holiday
        $this->assertSame(0, UnexplainedAbsence::count());
    }

    public function test_the_check_is_off_until_switched_on(): void
    {
        $this->business->update(['settings' => ['clock_in_check' => false]]);
        $this->employee('Rahul Mehta');

        $this->upload("email,date\nrahul.mehta@example.com,2026-10-05\n")->assertSessionHas('error', 'Switch the clock-in check on first.');
        $this->actingAs($this->admin)->put('/app/settings/clock-in', ['enabled' => true])->assertSessionHas('success');
        $this->assertTrue((bool) $this->business->fresh()->rule('clock_in_check'));
        $this->get('/app/settings')->assertInertia(fn (Assert $p) => $p->where('clockIn.enabled', true));
    }

    public function test_worked_clock_in_missed_and_recording_the_absence_both_clear_the_alert(): void
    {
        $rahul = $this->employee('Rahul Mehta');
        $kasia = $this->employee('Kasia Nowak');
        $this->employee('Aisha Rahman');
        $this->upload("email,date\naisha.rahman@example.com,2026-10-05\n");
        [$r, $k] = [UnexplainedAbsence::where('employee_id', $rahul->id)->sole(), UnexplainedAbsence::where('employee_id', $kasia->id)->sole()];

        $this->actingAs($this->admin)->post("/app/unexplained/{$r->id}/worked")->assertSessionHas('success');
        $this->assertSame([UnexplainedAbsence::WORKED, $this->admin->id], [$r->fresh()->status, $r->fresh()->resolved_by]);
        $this->assertSame(0, $rahul->absences()->count());

        // "Classify absence" opens Record absence filled in for that day, as unauthorised.
        $this->get("/app/absence/create?unexplained={$k->id}")->assertInertia(fn (Assert $p) => $p
            ->where('classify.employeeId', $kasia->id)->where('classify.date', '2026-10-05'));
        $this->post('/app/absence', ['employee_id' => $kasia->id, 'type' => 'sick_self', 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'reason' => 'Unwell'])->assertRedirect();
        $this->assertSame(UnexplainedAbsence::ABSENCE, $k->fresh()->status);
        $this->assertSame($kasia->absences()->sole()->id, $k->fresh()->absence_id);
    }

    public function test_a_late_clock_in_clears_the_alert_and_alerts_turn_red_after_two_working_days(): void
    {
        $rahul = $this->employee('Rahul Mehta');
        $this->employee('Aisha Rahman');
        $this->upload("email,date\naisha.rahman@example.com,2026-10-07\n"); // Wednesday
        $this->upload("email,date\naisha.rahman@example.com,2026-10-08\n"); // Thursday

        // Friday: Wednesday's alert is 2 working days old (red), Thursday's 1 (amber).
        $this->actingAs($this->admin)->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('unexplained.enabled', true)->has('unexplained.items', 2)
            ->where('unexplained.items.0.date', 'Wed 7 Oct 2026')->where('unexplained.items.0.badge.tone', 'red')
            ->where('unexplained.items.1.badge', ['text' => 'Needs classifying', 'tone' => 'amber']));

        $this->upload("email,date,time\nrahul.mehta@example.com,08/10/2026,09:15\n");
        $this->assertSame(UnexplainedAbsence::WORKED, UnexplainedAbsence::whereDate('date', '2026-10-08')->sole()->status);
    }

    public function test_alerts_are_in_the_next_morning_email(): void
    {
        $this->employee('Rahul Mehta');
        $this->employee('Aisha Rahman');
        $this->upload("email,date\naisha.rahman@example.com,2026-10-09\n");

        $this->travelTo(CarbonImmutable::parse('2026-10-10 07:00'));
        $this->artisan('reminders:send')->assertSuccessful();
        Notification::assertSentTo($this->admin, ComplianceDigest::class, fn (ComplianceDigest $n) => in_array(
            'Rahul Mehta had no clock-in and no absence recorded on 9 Oct 2026: please classify it', array_column($n->items, 'text'), true));
    }

    public function test_the_csv_is_checked_and_unknown_rows_are_reported(): void
    {
        $this->employee('Aisha Rahman');

        $this->upload("name,when\nAisha,2026-10-05\n")->assertSessionHasErrors(['file' => 'The file needs an "email" column and a "date" column in its first row.']);
        $this->upload("Email,Date\nnobody@example.com,2026-10-05\naisha.rahman@example.com,not a date\n")
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Row 3: the date "not a date" was not recognised.') && str_contains($m, 'Row 2: no employee with the email "nobody@example.com".'));
        $this->assertSame(0, ClockIn::count());
    }

    public function test_other_businesses_alerts_are_out_of_reach(): void
    {
        $other = Business::factory()->create(['settings' => ['clock_in_check' => true]]);
        $theirs = UnexplainedAbsence::create(['business_id' => $other->id, 'employee_id' => Employee::factory()->create(['business_id' => $other->id])->id, 'date' => '2026-10-05']);

        $this->actingAs($this->admin)->post("/app/unexplained/{$theirs->id}/worked")->assertNotFound();
        $this->get("/app/absence/create?unexplained={$theirs->id}")->assertInertia(fn (Assert $p) => $p->where('classify', null));
    }

    // ---- Usual working days ----

    public function test_work_days_are_set_on_the_employee_and_changes_are_logged(): void
    {
        $e = $this->employee('James Carter');
        $this->assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $e->workDays()); // not set: Monday to Friday

        $this->actingAs($this->admin)->put("/app/employees/{$e->id}/work-days", ['work_days' => ['thu', 'tue', 'sat']])->assertSessionHas('success', 'Working days saved.');
        $this->assertSame(['tue', 'thu', 'sat'], $e->fresh()->work_days);
        $change = EmployeeChange::where('employee_id', $e->id)->where('field', 'work_days')->sole();
        $this->assertSame([null, 'Tue, Thu, Sat', 'Usual working days'], [$change->old_value, $change->new_value, $change->label]);

        $this->put("/app/employees/{$e->id}/work-days", ['work_days' => []])->assertSessionHasErrors(['work_days' => 'Tick at least one day.']);
    }
}
