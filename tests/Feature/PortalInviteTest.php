<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\PortalInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

class PortalInviteTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $employee;
    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness();
        Notification::fake();
        $this->employee = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'Kasia Nowak', 'email' => 'kasia@example.com']);
    }

    private function invite(): void
    {
        $this->actingAs($this->admin)->post("/app/employees/{$this->employee->id}/invite")->assertSessionHas('success');
        Notification::assertSentTo($this->employee->fresh()->user, PortalInvite::class, function (PortalInvite $n) {
            $this->token = $n->token;

            return true;
        });
        auth('web')->logout();
    }

    public function test_the_link_shows_who_it_is_for_and_setting_a_password_signs_them_in(): void
    {
        $this->invite();

        $this->get("/set-password/{$this->token}")->assertInertia(fn (Assert $p) => $p->component('Auth/SetPassword')
            ->where('account.name', 'Kasia Nowak')
            ->where('account.business', $this->admin->business->name));

        $this->post("/set-password/{$this->token}", ['password' => 'a-good-password', 'password_confirmation' => 'a-good-password'])->assertRedirect('/me');
        $this->assertAuthenticatedAs($this->employee->fresh()->user);
        $this->assertSame('active', $this->employee->fresh()->portalStatus());
    }

    public function test_only_a_hash_of_the_token_is_stored(): void
    {
        $this->invite();
        $this->assertNotSame($this->token, $this->employee->fresh()->user->password_token);
        $this->assertSame(hash('sha256', $this->token), $this->employee->fresh()->user->password_token);
    }

    public function test_the_link_works_once(): void
    {
        $this->invite();
        $this->post("/set-password/{$this->token}", ['password' => 'a-good-password', 'password_confirmation' => 'a-good-password']);
        auth('web')->logout();

        $this->get("/set-password/{$this->token}")->assertInertia(fn (Assert $p) => $p->where('account', null));
        $this->post("/set-password/{$this->token}", ['password' => 'another-password', 'password_confirmation' => 'another-password']);
        $this->assertGuest();
    }

    public function test_the_link_expires_after_seven_days(): void
    {
        $this->invite();
        $this->travel(8)->days();

        $this->get("/set-password/{$this->token}")->assertInertia(fn (Assert $p) => $p->where('account', null));
    }

    public function test_password_must_be_confirmed_and_at_least_8_characters(): void
    {
        $this->invite();
        $this->post("/set-password/{$this->token}", ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post("/set-password/{$this->token}", ['password' => 'a-good-password', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
    }

    public function test_resending_replaces_the_old_link_and_keeps_one_login(): void
    {
        $this->invite();
        $first = $this->token;
        $this->invite();

        $this->assertNotSame($first, $this->token);
        $this->get("/set-password/{$first}")->assertInertia(fn (Assert $p) => $p->where('account', null));
        $this->assertSame(1, User::where('email', 'kasia@example.com')->count());
    }

    public function test_leavers_cannot_be_invited(): void
    {
        $this->employee->update(['ended_on' => '2026-06-30']);
        $this->actingAs($this->admin)->post("/app/employees/{$this->employee->id}/invite")->assertSessionHas('error');
        $this->assertNull($this->employee->fresh()->user_id);
    }
}
