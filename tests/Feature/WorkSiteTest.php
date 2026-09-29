<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\WorkSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

class WorkSiteTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
    }

    public function test_admin_adds_a_work_site(): void
    {
        $this->actingAs($this->admin)->post('/app/settings/sites', ['name' => 'Second shop', 'address' => '4 Bargate Street, Southampton SO14 1HF'])
            ->assertSessionHas('success');

        $this->assertTrue($this->admin->business->workSites()->where('name', 'Second shop')->exists());
        $this->assertTrue(AuditLog::where('action', 'work_site.created')->exists());
        $this->get('/app/settings')->assertInertia(fn (Assert $p) => $p->component('App/Settings/Index')->has('sites', 2));
    }

    public function test_site_needs_a_name_and_address(): void
    {
        $this->actingAs($this->admin)->post('/app/settings/sites', ['name' => '', 'address' => ''])->assertSessionHasErrors(['name', 'address']);
    }

    public function test_a_site_with_current_employees_cannot_be_closed(): void
    {
        Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id]);

        $this->actingAs($this->admin)->post("/app/settings/sites/{$this->site->id}/close")->assertSessionHas('error');
        $this->assertNull($this->site->fresh()->closed_on);
    }

    public function test_an_empty_site_can_be_closed(): void
    {
        $this->actingAs($this->admin)->post("/app/settings/sites/{$this->site->id}/close")->assertSessionHas('success');
        $this->assertNotNull($this->site->fresh()->closed_on);
    }

    public function test_moving_an_employee_logs_the_change(): void
    {
        $second = WorkSite::factory()->create(['business_id' => $this->admin->business_id, 'name' => 'Second shop']);
        $e = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id]);

        $this->actingAs($this->admin)->post('/app/settings/move', ['employee_id' => $e->id, 'work_site_id' => $second->id])->assertSessionHas('success');

        $this->assertSame($second->id, $e->fresh()->work_site_id);
        $change = EmployeeChange::sole();
        $this->assertSame(['Work site', 'Main shop', 'Second shop'], [$change->label, $change->old_value, $change->new_value]);
    }

    public function test_sites_of_other_businesses_are_out_of_reach(): void
    {
        $other = WorkSite::factory()->create();
        $e = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id]);

        $this->actingAs($this->admin)->put("/app/settings/sites/{$other->id}", ['name' => 'Hacked', 'address' => 'x'])->assertNotFound();
        $this->post("/app/settings/sites/{$other->id}/close")->assertNotFound();
        $this->post('/app/settings/move', ['employee_id' => $e->id, 'work_site_id' => $other->id])->assertSessionHasErrors('work_site_id');
        $this->assertNotSame('Hacked', $other->fresh()->name);
    }

    public function test_key_personnel_are_added_changed_and_removed_with_an_audit_trail(): void
    {
        $this->actingAs($this->admin);
        $this->post('/app/settings/people', ['role' => 'authorising_officer', 'name' => 'Nadia Khan', 'email' => 'nadia@example.com'])->assertSessionHas('success');
        $this->post('/app/settings/people', ['role' => 'level1_user', 'name' => 'Imran Shah'])->assertSessionHas('success');
        $this->post('/app/settings/people', ['role' => 'level1_user', 'name' => 'Leila Ahmed'])->assertSessionHas('success');

        $ao = KeyPerson::where('role', 'authorising_officer')->sole();
        $this->put("/app/settings/people/{$ao->id}", ['role' => 'authorising_officer', 'name' => 'Nadia Khan', 'email' => 'nadia.khan@example.com'])->assertSessionHas('success');
        $this->delete('/app/settings/people/'.KeyPerson::where('name', 'Leila Ahmed')->value('id'))->assertSessionHas('success');

        $this->get('/app/settings')->assertInertia(fn (Assert $p) => $p->has('people', 2)->where('people.0.roleLabel', 'Authorising Officer'));
        $this->assertSame(['key_person.added', 'key_person.added', 'key_person.added', 'key_person.changed', 'key_person.removed'],
            AuditLog::where('action', 'like', 'key_person.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_there_is_only_one_authorising_officer_and_one_key_contact(): void
    {
        $this->actingAs($this->admin);
        $this->post('/app/settings/people', ['role' => 'key_contact', 'name' => 'Nadia Khan']);
        $this->post('/app/settings/people', ['role' => 'key_contact', 'name' => 'Someone Else'])->assertSessionHasErrors('role');
        $this->assertSame(1, KeyPerson::count());
    }

    public function test_key_personnel_of_other_businesses_are_out_of_reach(): void
    {
        $other = KeyPerson::create(['business_id' => WorkSite::factory()->create()->business_id, 'role' => 'key_contact', 'name' => 'Theirs']);

        $this->actingAs($this->admin)->delete("/app/settings/people/{$other->id}")->assertNotFound();
        $this->put("/app/settings/people/{$other->id}", ['role' => 'key_contact', 'name' => 'Hacked'])->assertNotFound();
        $this->assertSame('Theirs', $other->fresh()->name);
    }
}
