<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_lookup_shows_the_account_name(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Nadia Khan', 'email' => 'hr@demo.example']);

        $this->post('/login/lookup', ['email' => 'HR@demo.example'])->assertRedirect('/login');
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->component('Auth/Login')
            ->where('account.name', 'Nadia Khan')
            ->where('account.initials', 'NK')
            ->where('account.business', $user->business->name)
            ->where('account.role', 'Admin'));
    }

    public function test_unknown_email_gets_a_clear_error(): void
    {
        $this->post('/login/lookup', ['email' => 'nobody@nowhere.example'])->assertSessionHasErrors('email');
    }

    public function test_not_you_clears_the_account(): void
    {
        User::factory()->create(['email' => 'a@demo.example']);
        $this->post('/login/lookup', ['email' => 'a@demo.example']);
        $this->post('/login/reset')->assertRedirect('/login');
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->where('account', null));
    }

    public function test_admin_signs_in_to_the_business_area(): void
    {
        User::factory()->admin()->create(['email' => 'hr@demo.example', 'password' => 'secret123']);
        $this->post('/login/lookup', ['email' => 'hr@demo.example']);
        $this->post('/login', ['password' => 'secret123'])->assertRedirect('/app');
        $this->get('/app')->assertOk()->assertInertia(fn (Assert $p) => $p->component('App/Dashboard'));
    }

    public function test_employee_signs_in_to_the_portal_and_cannot_open_the_admin_area(): void
    {
        User::factory()->create(['email' => 'aisha@demo.example', 'password' => 'secret123']);
        $this->post('/login/lookup', ['email' => 'aisha@demo.example']);
        $this->post('/login', ['password' => 'secret123'])->assertRedirect('/me');
        $this->get('/app')->assertRedirect('/me');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'a@demo.example', 'password' => 'secret123']);
        $this->post('/login/lookup', ['email' => 'a@demo.example']);
        $this->post('/login', ['password' => 'nope'])->assertSessionHasErrors('password');
        $this->assertGuest('web');
    }

    public function test_suspended_business_cannot_sign_in(): void
    {
        $b = Business::factory()->suspended()->create(['name' => 'Paused Ltd']);
        User::factory()->admin()->create(['business_id' => $b->id, 'email' => 'hr@paused.example', 'password' => 'secret123']);
        $this->post('/login/lookup', ['email' => 'hr@paused.example']);
        $this->post('/login', ['password' => 'secret123'])->assertSessionHasErrors(['password' => 'Access for Paused Ltd is paused. Please contact support to reactivate the subscription.']);
        $this->assertGuest('web');
    }

    public function test_signed_in_users_are_signed_out_when_their_business_is_suspended(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get('/app')->assertOk();
        $user->business->update(['status' => 'suspended']);
        $this->actingAs($user)->get('/app')->assertRedirect('/login');
    }

    public function test_admin_only_sees_their_own_business(): void
    {
        $mine = User::factory()->admin()->create();
        User::factory()->count(3)->create(['business_id' => $mine->business_id]);
        User::factory()->count(5)->create(); // other businesses

        $this->actingAs($mine)->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('business.name', $mine->business->name)
            ->where('business.employees', 3));
    }
}
