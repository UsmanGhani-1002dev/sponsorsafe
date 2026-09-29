<?php

namespace Database\Factories;

use App\Enums\RightToWorkBasis;
use App\Models\Business;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Defaults to a British citizen; use sponsored() for a Skilled Worker. */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'work_site_id' => fn (array $a) => WorkSite::factory()->create(['business_id' => $a['business_id']])->id,
            'full_name' => fake()->name(),
            'date_of_birth' => '1995-04-12',
            'nationality' => 'British',
            'email' => fake()->unique()->safeEmail(),
            'rtw_basis' => RightToWorkBasis::BritishIrish,
            'rtw_check_method' => 'Manual check of original passport',
            'rtw_check_date' => '2025-01-06',
            'rtw_checked_by' => 'HR admin',
            'job_title' => 'Sales Assistant',
            'salary' => 24000,
            'start_date' => '2025-01-13',
            'days_per_week' => 5,
            'contracted_hours' => 37.5,
            'contract_type' => 'Permanent',
        ];
    }

    public function sponsored(): static
    {
        return $this->state([
            'nationality' => 'Indian',
            'rtw_basis' => RightToWorkBasis::Sponsored,
            'rtw_check_method' => 'Home Office online check (share code)',
            'share_code' => 'W7X9KP2QR',
            'visa_type' => 'Skilled Worker',
            'visa_start' => '2025-01-01',
            'visa_expiry' => '2028-01-01',
            'follow_up_check_due' => '2028-01-01',
            'cos_number' => 'C2G7K19400X',
            'cos_assigned_on' => '2024-11-20',
            'soc_code' => '7132',
            'salary' => 42000,
        ]);
    }

    public function left(string $on = '2026-06-30'): static
    {
        return $this->state(['ended_on' => $on, 'end_reason' => 'Resigned']);
    }
}
