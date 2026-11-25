<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Services\ComplianceCheck;
use App\Services\WorkingDays;
use App\Support\ComplianceScore;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** Sponsored workers paid below the minimum for their visa, and the dashboard's compliance score. */
class PayAndComplianceScoreTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private const LOW = '£38,700 a year is below the minimum salary of £41,700 for this worker\'s visa. Paying a sponsored worker less than the minimum can lead to '
        .'your sponsor licence being suspended. Check with an immigration adviser, and report any pay cut on the Sponsor Management System.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo('2026-11-25 10:00');
        $this->setUpBusiness();
    }

    private function sponsored(array $attributes = []): Employee
    {
        return Employee::factory()->sponsored()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id,
            'address' => '1 High Street', 'phone' => '07700 900111', ...$attributes]);
    }

    private function pay(Employee $e): array
    {
        $row = collect((new ComplianceCheck(WorkingDays::fromDatabase()))->rows($e->fresh(['documents', 'reportTasks', 'business'])))->firstWhere('key', 'pay');

        return [$row['status'], $row['detail']];
    }

    private function file(Employee $e, string $category): void
    {
        Document::create(['business_id' => $e->business_id, 'employee_id' => $e->id, 'category' => $category, 'original_name' => "{$category}.pdf",
            'path' => "documents/test/{$e->id}-{$category}.enc", 'mime' => 'application/pdf', 'size' => 100]);
    }

    public function test_the_compliance_check_flags_pay_below_the_minimum_for_the_visa(): void
    {
        $e = $this->sponsored(['salary' => 38700]);
        [$status, $detail] = $this->pay($e);
        $this->assertSame(ComplianceCheck::MISSING, $status);
        $this->assertStringStartsWith('Salary £38,700 is below the minimum of £41,700 (general threshold).', $detail);

        // A lower minimum that applies to this worker (e.g. a new entrant) clears it.
        $e->update(['min_salary' => 33400]);
        $this->assertSame([ComplianceCheck::DONE, 'Salary £38,700, minimum £33,400 (set for this worker). Compare each payslip with it; sponsorship costs must not be deducted from pay.'], $this->pay($e));

        // The general threshold is a business setting.
        $e->update(['min_salary' => null]);
        $this->admin->business->update(['settings' => ['sponsored_min_salary' => 38000]]);
        $this->assertSame(ComplianceCheck::DONE, $this->pay($e)[0]);
    }

    public function test_record_a_change_warns_when_a_pay_cut_goes_below_the_minimum(): void
    {
        $e = $this->sponsored(['salary' => 42000]);

        $this->actingAs($this->admin)->get("/app/employees/{$e->id}")
            ->assertInertia(fn (Assert $p) => $p->where('pay', ['minimum' => 41700, 'general' => 41700, 'salary' => 42000])
                ->where('changeTypes', fn ($types) => collect($types)->contains(fn ($t) => $t['value'] === 'min_salary' && $t['current'] === 'Not set: the general threshold of £41,700 applies')));

        $this->post("/app/employees/{$e->id}/changes", ['type' => 'salary_reduction', 'value' => '£38,700'])
            ->assertSessionHas('error', self::LOW)
            ->assertSessionHas('success', fn ($s) => str_contains($s, 'A Home Office report task was created'));
        $this->assertSame(1, ReportTask::count(), 'the pay cut is still reportable');

        // Recording a lower minimum that applies to them: logged, not reportable, and no warning.
        $this->post("/app/employees/{$e->id}/changes", ['type' => 'min_salary', 'value' => '33,400'])
            ->assertSessionHas('success', 'Saved and logged in the history. No Home Office report needed.')->assertSessionMissing('error');
        $this->assertSame('33400.00', $e->fresh()->min_salary);
        $this->assertTrue($e->changes()->where('field', 'min_salary')->exists());
        $this->assertSame(1, ReportTask::count());

        $this->post("/app/employees/{$e->id}/changes", ['type' => 'salary_increase', 'value' => '45000'])->assertSessionMissing('error');
    }

    public function test_add_employee_warns_about_low_pay_and_keeps_a_worker_minimum_only_for_sponsored_workers(): void
    {
        $this->actingAs($this->admin)->get('/app/employees/create')->assertInertia(fn (Assert $p) => $p->where('payMinimum', 41700));

        $this->post('/app/employees', $this->payload('sponsored', ['salary' => '38700']))->assertSessionHas('error', self::LOW);
        $this->post('/app/employees', $this->payload('sponsored', ['salary' => '38700', 'min_salary' => '£33,400', 'email' => 'b@example.com', 'full_name' => 'Ben Ode']))
            ->assertSessionMissing('error');
        $this->assertSame('33400.00', Employee::where('full_name', 'Ben Ode')->value('min_salary'));

        $this->post('/app/employees', $this->payload('british_irish', ['salary' => '20000', 'min_salary' => '50000', 'email' => 'c@example.com', 'full_name' => 'Cara Lee']))
            ->assertSessionMissing('error');
        $this->assertNull(Employee::where('full_name', 'Cara Lee')->value('min_salary'));
    }

    public function test_the_dashboard_score_adds_up_every_employee_and_lists_what_to_fix_first(): void
    {
        $this->assertNull(app(ComplianceScore::class)->for($this->admin->business), 'no card before there are employees');

        $complete = $this->sponsored(['full_name' => 'Rahul Mehta', 'rtw_check_date' => '2024-05-28', 'start_date' => '2024-06-03']);
        foreach (['rtw', 'passport', 'contract', 'cos', 'jd', 'recruit', 'payroll'] as $c) {
            $this->file($complete, $c);
        }
        $aisha = $this->sponsored(['full_name' => 'Aisha Rahman', 'salary' => 38700, 'rtw_check_date' => '2025-10-28', 'start_date' => '2025-11-03']);
        foreach (['rtw', 'passport', 'contract', 'cos', 'jd', 'recruit', 'payroll'] as $c) {
            $this->file($aisha, $c);
        }
        Document::where('employee_id', $aisha->id)->where('category', 'passport')->delete();

        $this->actingAs($this->admin)->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('compliance.employees', 2)
            ->where('compliance.toFix', 2)
            ->where('compliance.tone', 'red')
            ->where('compliance.score', (int) floor(20 / 22 * 100)) // 11 checkable items each, 2 to fix
            ->where('compliance.fixes.0.name', 'Aisha Rahman')
            ->where('compliance.fixes.0.label', 'Passport / ID copy on file')
            ->where('compliance.fixes.0.href', "/app/employees/{$aisha->id}?tab=docs")
            ->where('compliance.fixes.1.label', 'Salary at or above the visa minimum')
            ->where('compliance.fixes.1.href', "/app/employees/{$aisha->id}?tab=history")
            ->where('compliance.people.0', ['id' => $aisha->id, 'name' => 'Aisha Rahman', 'text' => '2 to fix', 'tone' => 'red'])
            ->where('compliance.people.1', ['id' => $complete->id, 'name' => 'Rahul Mehta', 'text' => 'all done', 'tone' => 'green']));
    }
}
