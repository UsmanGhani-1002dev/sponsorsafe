<?php

namespace Tests\Feature\Concerns;

use App\Models\User;
use App\Models\WorkSite;

trait MakesEmployees
{
    protected User $admin;
    protected WorkSite $site;

    protected function setUpBusiness(): void
    {
        $this->admin = User::factory()->admin()->create(['name' => 'Nadia Khan']);
        $this->site = WorkSite::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'Main shop']);
    }

    /** A valid Add employee form for the given basis. */
    protected function payload(string $basis = 'sponsored', array $overrides = []): array
    {
        $base = [
            'full_name' => 'Sara Ali', 'date_of_birth' => '1997-05-14', 'nationality' => 'Pakistani',
            'email' => 'sara.ali@example.com', 'phone' => '07700 900123', 'address' => '12 High Street, Southampton SO14 2AA',
            'ni_number' => 'qq 12 34 56 c', 'passport_number' => 'ab1234567', 'passport_expiry' => '2032-02-01',
            'rtw_basis' => $basis, 'rtw_check_method' => 'Home Office online check (share code)',
            'rtw_check_date' => '2026-09-24', 'rtw_checked_by' => 'Nadia Khan',
            'job_title' => 'Sales Assistant', 'salary' => '£41,700', 'start_date' => '2026-10-05',
            'work_site_id' => $this->site->id, 'days_per_week' => '5', 'contracted_hours' => '37.5', 'contract_type' => 'Permanent',
            'portal_invite' => false,
        ];
        $extra = match ($basis) {
            'sponsored' => ['share_code' => 'W7X 9KP 2QR', 'visa_start' => '2026-09-28', 'visa_expiry' => '2029-09-30',
                'work_restrictions' => 'Only the sponsored job', 'cos_number' => 'C2G7K19400X', 'cos_assigned_on' => '2026-08-12', 'soc_code' => '7132'],
            'euss_presettled' => ['share_code' => 'W7X9KP2QR', 'visa_start' => '2022-02-14', 'visa_expiry' => '2027-02-14'],
            'other_visa' => ['share_code' => 'W7X9KP2QR', 'visa_type' => 'Graduate', 'visa_start' => '2025-07-01', 'visa_expiry' => '2027-06-30'],
            'euss_settled', 'ilr' => ['share_code' => 'W7X9KP2QR'],
            default => ['rtw_check_method' => 'Manual check of original passport'],
        };

        return [...$base, ...$extra, ...$overrides];
    }
}
