<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** compliance-rules.md §1: validation on the Add employee form. */
class AddEmployeeRulesTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    private function add(array $payload)
    {
        return $this->actingAs($this->admin)->from('/app/employees/create')->post('/app/employees', $payload);
    }

    public static function bases(): array
    {
        return [['british_irish'], ['euss_settled'], ['euss_presettled'], ['ilr'], ['sponsored'], ['other_visa']];
    }

    #[DataProvider('bases')]
    public function test_every_basis_can_be_saved_with_its_fields(string $basis): void
    {
        $this->add($this->payload($basis))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($basis, Employee::sole()->rtw_basis->value);
    }

    public function test_check_date_must_be_on_or_before_the_start_date(): void
    {
        $this->add($this->payload('british_irish', ['rtw_check_date' => '2026-10-06', 'start_date' => '2026-10-05']))
            ->assertSessionHasErrors(['rtw_check_date' => 'The right-to-work check must be done on or before the start date.']);

        $this->add($this->payload('british_irish', ['rtw_check_date' => '2026-10-05', 'start_date' => '2026-10-05']))->assertSessionHasNoErrors();
    }

    public function test_visa_expiry_must_be_after_the_start_date(): void
    {
        $this->add($this->payload('sponsored', ['visa_expiry' => '2026-10-05', 'start_date' => '2026-10-05']))
            ->assertSessionHasErrors(['visa_expiry' => 'The visa expires before the start date. They cannot start work.']);
        $this->assertSame(0, Employee::count());
    }

    #[DataProvider('bases')]
    public function test_share_code_is_required_only_when_the_basis_uses_one(string $basis): void
    {
        $response = $this->add($this->payload($basis, ['share_code' => '']));

        if ($basis === 'british_irish') {
            $response->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors('share_code');
        }
    }

    public function test_share_code_must_be_nine_characters(): void
    {
        $this->add($this->payload('ilr', ['share_code' => 'ABC123']))->assertSessionHasErrors('share_code');
    }

    public function test_time_limited_permission_needs_visa_start_and_expiry(): void
    {
        $this->add($this->payload('euss_presettled', ['visa_start' => '', 'visa_expiry' => '']))
            ->assertSessionHasErrors(['visa_start', 'visa_expiry']);
    }

    public function test_sponsored_worker_needs_cos_number_date_soc_code_and_salary(): void
    {
        $this->add($this->payload('sponsored', ['cos_number' => '', 'cos_assigned_on' => '', 'soc_code' => '', 'salary' => '']))
            ->assertSessionHasErrors(['cos_number', 'cos_assigned_on', 'soc_code', 'salary']);
    }

    public function test_other_visa_needs_a_visa_type(): void
    {
        $this->add($this->payload('other_visa', ['visa_type' => '']))->assertSessionHasErrors('visa_type');
    }

    public function test_fields_that_do_not_apply_to_the_basis_are_not_saved(): void
    {
        // A British citizen sent with leftover visa and CoS fields from a changed form.
        $this->add($this->payload('british_irish', [
            'share_code' => 'W7X9KP2QR', 'visa_expiry' => '2029-01-01', 'cos_number' => 'C123', 'soc_code' => '7132',
        ]))->assertSessionHasNoErrors();

        $e = Employee::sole();
        $this->assertNull($e->share_code);
        $this->assertNull($e->visa_expiry);
        $this->assertNull($e->cos_number);
        $this->assertNull($e->follow_up_check_due);
    }

    public function test_follow_up_check_due_is_the_visa_expiry(): void
    {
        $this->add($this->payload('sponsored'))->assertSessionHasNoErrors();

        $e = Employee::sole();
        $this->assertSame('2029-09-30', $e->follow_up_check_due->format('Y-m-d'));
        $this->assertSame('Skilled Worker', $e->visa_type);
    }

    public function test_work_site_must_belong_to_this_business_and_be_open(): void
    {
        $other = WorkSite::factory()->create();
        $this->add($this->payload('british_irish', ['work_site_id' => $other->id]))->assertSessionHasErrors('work_site_id');

        $this->site->update(['closed_on' => '2026-01-01']);
        $this->add($this->payload('british_irish'))->assertSessionHasErrors('work_site_id');
    }

    public function test_the_plan_employee_limit_is_enforced(): void
    {
        // Starter covers 5: the 6th is blocked, with the way to upgrade.
        $this->admin->business->update(['plan' => 'starter', 'plan_price_pence' => 2000, 'employee_limit' => 5]);
        Employee::factory()->count(5)->create(['business_id' => $this->admin->business_id]);

        $this->add($this->payload('british_irish'))->assertSessionHasErrors(['form' => 'Your plan covers up to 5 employees. Upgrade to Standard (£35 a month, up to 10) in Settings to add more.']);
        $this->assertSame(5, Employee::count());
    }

    public function test_standard_at_its_limit_points_to_the_corporate_package(): void
    {
        $this->admin->business->update(['plan' => 'standard', 'plan_price_pence' => 3500, 'employee_limit' => 10]);
        Employee::factory()->count(10)->create(['business_id' => $this->admin->business_id]);

        $this->add($this->payload('british_irish'))->assertSessionHasErrors(['form' => 'Your plan covers up to 10 employees. Contact us about a Corporate package to add more.']);
        $this->assertSame(10, Employee::count());
    }

    public function test_leavers_do_not_count_towards_the_limit(): void
    {
        $this->admin->business->update(['employee_limit' => 2]);
        Employee::factory()->create(['business_id' => $this->admin->business_id]);
        Employee::factory()->left()->create(['business_id' => $this->admin->business_id]);

        $this->add($this->payload('british_irish'))->assertSessionHasNoErrors();
    }

    public function test_the_same_email_cannot_be_added_twice_in_one_business(): void
    {
        $this->add($this->payload('british_irish'))->assertSessionHasNoErrors();
        $this->add($this->payload('british_irish'))->assertSessionHasErrors('email');
    }
}
