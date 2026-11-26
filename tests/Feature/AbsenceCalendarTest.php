<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** Absence → Calendar: who is off each day of the month. */
class AbsenceCalendarTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo('2026-12-10 10:00');
        $this->setUpBusiness();
    }

    private function person(string $name, array $attributes = [], bool $sponsored = false): Employee
    {
        $factory = $sponsored ? Employee::factory()->sponsored() : Employee::factory();

        return $factory->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => $name,
            'start_date' => '2025-01-06', 'days_per_week' => 5, ...$attributes]);
    }

    private function absence(Employee $e, string $type, string $start, string $end, int $days): void
    {
        Absence::create(['business_id' => $e->business_id, 'employee_id' => $e->id, 'type' => $type, 'start_date' => $start, 'end_date' => $end,
            'working_days' => $days, 'check_status' => 'none']);
    }

    public function test_the_month_shows_each_person_employed_that_month_day_by_day(): void
    {
        $aisha = $this->person('Aisha Rahman', [], sponsored: true);
        $this->absence($aisha, 'unpaid', '2026-06-01', '2026-06-05', 5);     // earlier in the leave year
        $this->absence($aisha, 'unauthorised', '2026-12-03', '2026-12-03', 1);
        $this->absence($aisha, 'annual', '2026-12-14', '2026-12-18', 5);
        $kasia = $this->person('Kasia Nowak', ['work_days' => ['mon', 'tue', 'wed']]);
        $this->absence($kasia, 'sick_self', '2026-12-07', '2026-12-10', 3); // Thursday is her day off
        $ben = $this->person('Ben Ode', ['start_date' => '2026-12-15']);
        $mo = $this->person('Mo Patel', ['ended_on' => '2026-12-04']);
        $this->person('Lee Hart', ['ended_on' => '2026-11-20']);             // left before December: not shown
        Employee::factory()->create(['full_name' => 'Someone Else']);        // another business

        $this->actingAs($this->admin)->get('/app/absence/calendar')->assertInertia(fn (Assert $p) => $p
            ->component('App/Absence/Calendar')
            ->where('month', ['value' => '2026-12', 'label' => 'December 2026', 'prev' => '2026-11', 'next' => '2027-01', 'isCurrent' => true])
            ->has('days', 31)
            ->where('days.9.today', true)
            ->where('days.24.bankHoliday', fn ($title) => is_string($title) && $title !== '')
            ->where('days.4.weekend', true)
            ->where('people', fn ($people) => collect($people)->pluck('name')->all() === ['Aisha Rahman', 'Ben Ode', 'Kasia Nowak', 'Mo Patel'])
            // Aisha: unauthorised on the 3rd, a week's leave from the 14th, Saturdays and Christmas Day off.
            ->where('people.0.cells.2', ['state' => 'absence', 'kind' => 'unauthorised', 'scheduled' => true, 'title' => 'Unauthorised absence · 3 Dec 2026'])
            ->where('people.0.cells.13.kind', 'leave')
            ->where('people.0.cells.4', ['state' => 'off'])
            ->where('people.0.cells.24', ['state' => 'off'])
            ->where('people.0.cells.21', ['state' => 'work'])
            ->where('people.0.counts', ['leave' => 5, 'unauthorised' => 1])
            ->where('people.0.unpaid', ['text' => 'Unpaid 6 of 20', 'tone' => 'grey'])
            // Ben started on the 15th.
            ->where('people.1.cells.0', ['state' => 'out'])
            ->where('people.1.cells.14', ['state' => 'work'])
            ->where('people.1.unpaid', null)
            // Kasia works Monday to Wednesday: her sickness runs into her Thursday off.
            ->where('people.2.cells.9.kind', 'sick')
            ->where('people.2.cells.9.scheduled', false)
            ->where('people.2.cells.10', ['state' => 'off'])
            ->where('people.2.counts', ['sick' => 3])
            // Mo left on the 4th.
            ->where('people.3.current', false)
            ->where('people.3.cells.3', ['state' => 'work'])
            ->where('people.3.cells.4', ['state' => 'out']));

        $this->get('/app/absence/calendar?month=2027-01')->assertInertia(fn (Assert $p) => $p->where('month.label', 'January 2027')->where('month.isCurrent', false));
        $this->get('/app/absence/calendar?month=2026-13')->assertInertia(fn (Assert $p) => $p->where('month.value', '2026-12'));
    }

    public function test_an_empty_day_opens_record_absence_for_that_person_and_date_and_returns_to_the_month(): void
    {
        $aisha = $this->person('Aisha Rahman');

        $this->actingAs($this->admin)->get("/app/absence/create?employee={$aisha->id}&date=2027-01-11&from=calendar")
            ->assertInertia(fn (Assert $p) => $p->where('preselect', $aisha->id)->where('calendar', ['date' => '2027-01-11', 'month' => '2027-01']));
        $this->get("/app/absence/create?employee={$aisha->id}&date=nonsense&from=calendar")->assertInertia(fn (Assert $p) => $p->where('calendar', null));

        $this->post('/app/absence', ['employee_id' => $aisha->id, 'type' => 'annual', 'start_date' => '2027-01-11', 'end_date' => '2027-01-11', 'return' => 'calendar:2027-01'])
            ->assertRedirect('/app/absence/calendar?month=2027-01');
        $this->assertSame(1, $aisha->absences()->count());
    }

    public function test_only_admins_see_the_calendar(): void
    {
        $employee = User::factory()->create(['business_id' => $this->admin->business_id]);
        $this->actingAs($employee)->get('/app/absence/calendar')->assertRedirect('/me');
    }
}
