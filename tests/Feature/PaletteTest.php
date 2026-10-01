<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Ctrl+K palette's people list: this business only, current employees first, admins only. */
class PaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_this_business_employees_current_first(): void
    {
        $admin = User::factory()->admin()->create();
        Employee::factory()->create(['business_id' => $admin->business_id, 'full_name' => 'Zara Ahmed', 'job_title' => 'Chef']);
        Employee::factory()->create(['business_id' => $admin->business_id, 'full_name' => 'Aisha Rahman', 'job_title' => 'Sales Assistant']);
        Employee::factory()->create(['business_id' => $admin->business_id, 'full_name' => 'Adam Leaver', 'ended_on' => '2026-08-31']);
        Employee::factory()->create(['business_id' => Business::factory()->create()->id, 'full_name' => 'Someone Else']);

        $response = $this->actingAs($admin)->getJson('/app/palette')->assertOk();

        $this->assertSame(['Aisha Rahman', 'Zara Ahmed', 'Adam Leaver'], array_column($response->json('employees'), 'name'));
        $this->assertStringStartsWith('Sales Assistant', $response->json('employees.0.detail'));
        $this->assertSame(['Left 31 Aug 2026', true], [$response->json('employees.2.detail'), $response->json('employees.2.leaver')]);
    }

    public function test_only_signed_in_admins_can_use_it(): void
    {
        $this->getJson('/app/palette')->assertUnauthorized();

        $employee = User::factory()->create();
        $this->actingAs($employee)->get('/app/palette')->assertRedirect('/me');
    }
}
