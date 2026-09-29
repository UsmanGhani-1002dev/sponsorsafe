<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
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

    public function test_admin_sets_up_an_authenticator_on_first_sign_in(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'hr@demo.example', 'password' => 'secret123']);
        $this->post('/login/lookup', ['email' => 'hr@demo.example']);
        $this->post('/login', ['password' => 'secret123'])->assertRedirect('/login/verify');
        $this->assertGuest('web');

        $this->get('/login/verify')->assertInertia(fn (Assert $p) => $p->component('Auth/TwoFactor')->has('setup.secret'));
        $secret = $admin->fresh()->two_factor_secret;

        $this->post('/login/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/login/verify', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/app');
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->two_factor_confirmed_at);
        $this->get('/app')->assertOk()->assertInertia(fn (Assert $p) => $p->component('App/Dashboard'));
    }

    public function test_admin_with_an_authenticator_enters_a_code_without_setup(): void
    {
        $g2fa = new Google2FA;
        $secret = $g2fa->generateSecretKey(32);
        User::factory()->admin()->create(['email' => 'hr@demo.example', 'password' => 'secret123', 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $this->post('/login/lookup', ['email' => 'hr@demo.example']);
        $this->post('/login', ['password' => 'secret123'])->assertRedirect('/login/verify');

        $this->get('/login/verify')->assertInertia(fn (Assert $p) => $p->where('setup', null));
        $this->post('/login/verify', ['code' => $g2fa->getCurrentOtp($secret)])->assertRedirect('/app');
    }

    public function test_the_code_page_needs_the_password_step_first(): void
    {
        $this->get('/login/verify')->assertRedirect('/login');
        $this->post('/login/verify', ['code' => '123456'])->assertRedirect('/login');
    }

    public function test_forgot_password_emails_a_reset_link_to_the_account_being_signed_in(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'aisha@demo.example']);
        $this->post('/login/lookup', ['email' => 'aisha@demo.example']);

        $this->post('/login/forgot')->assertSessionHas('success');
        Notification::assertSentTo($user, PasswordReset::class, function (PasswordReset $n) {
            $this->post("/set-password/{$n->token}", ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect('/me');

            return true;
        });
        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_reset_links_expire_after_an_hour(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'aisha@demo.example']);
        $this->post('/login/lookup', ['email' => 'aisha@demo.example']);
        $this->post('/login/forgot');

        $this->travel(61)->minutes();
        Notification::assertSentTo($user, PasswordReset::class, function (PasswordReset $n) {
            $this->get("/set-password/{$n->token}")->assertInertia(fn (Assert $p) => $p->where('account', null));

            return true;
        });
    }

    public function test_an_admin_resetting_their_password_still_needs_their_code(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create(['email' => 'hr@demo.example']);
        $this->post('/login/lookup', ['email' => 'hr@demo.example']);
        $this->post('/login/forgot');

        Notification::assertSentTo($admin, PasswordReset::class, function (PasswordReset $n) {
            $this->post("/set-password/{$n->token}", ['password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])->assertRedirect('/login/verify');

            return true;
        });
        $this->assertGuest('web');
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
        Employee::factory()->count(3)->create(['business_id' => $mine->business_id]);
        Employee::factory()->left()->create(['business_id' => $mine->business_id]); // leavers are not counted
        Employee::factory()->count(5)->create(); // other businesses

        $this->actingAs($mine)->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('business.name', $mine->business->name)
            ->where('business.employees', 3));
    }
}
