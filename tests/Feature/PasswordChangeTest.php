<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Change password while signed in: business admins (Settings), employees (My details), super admins (My account). */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private string $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        Notification::fake();
    }

    private function withAuthenticator(User|SuperAdmin $person): string
    {
        $secret = (new Google2FA())->generateSecretKey(32);
        $person->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return (new Google2FA())->getCurrentOtp($secret);
    }

    private function change(string $url, array $overrides = [])
    {
        return $this->put($url, ['current_password' => 'password', 'password' => 'a-new-password-99', 'password_confirmation' => 'a-new-password-99', 'code' => '', ...$overrides]);
    }

    public function test_a_business_admin_changes_their_password_with_their_authenticator_code(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'nadia@demo-retail.example']);
        $code = $this->withAuthenticator($admin);
        $this->actingAs($admin)->get('/app/settings')->assertInertia(fn (Assert $p) => $p->where('login', ['email' => 'nadia@demo-retail.example', 'needsCode' => true]));

        $this->change('/app/settings/password')->assertSessionHasErrors('code');
        $this->change('/app/settings/password', ['code' => $code])->assertSessionHas('success', 'Password changed. Any other devices have been signed out.');

        $this->assertTrue(Hash::check('a-new-password-99', $admin->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'auth.password_changed')->where('subject_id', $admin->id)->exists());
        Notification::assertSentTo($admin, PasswordChanged::class);
        $this->get('/app/settings')->assertOk(); // this device stays signed in
    }

    public function test_an_employee_changes_their_password_from_my_details(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee)->put('/me/security/password', ['current_password' => 'wrong', 'password' => 'a-new-password-99', 'password_confirmation' => 'a-new-password-99'])
            ->assertSessionHasErrors(['current_password' => 'That password is not right.']);
        $this->change('/me/security/password')->assertSessionHas('success');

        $this->assertTrue(Hash::check('a-new-password-99', $employee->fresh()->password));
        Notification::assertSentTo($employee, PasswordChanged::class, fn (PasswordChanged $n) => ! $n->superAdmin);
    }

    public function test_the_new_password_is_checked(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        $this->change('/me/security/password', ['password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->change('/me/security/password', ['password_confirmation' => 'something-else'])->assertSessionHasErrors(['password' => 'The two new passwords do not match.']);
        $this->change('/me/security/password', ['password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasErrors(['password' => 'Choose a new password, not the one you use now.']);
        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
        Notification::assertNothingSent();
    }

    public function test_wrong_passwords_are_limited(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);
        foreach (range(1, 5) as $i) {
            $this->change('/me/security/password', ['current_password' => "wrong-{$i}"])->assertSessionHasErrors('current_password');
        }
        $this->change('/me/security/password')->assertSessionHasErrors(['current_password' => 'Too many attempts. Try again in a few minutes.']);
        $this->assertTrue(Hash::check('password', $employee->fresh()->password));
    }

    public function test_other_devices_are_signed_out_when_the_password_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/app/settings')->assertOk();

        // Changed somewhere else (another device, or a reset link).
        $admin->forceFill(['password' => 'changed-elsewhere-1'])->save();

        $this->get('/app/settings')->assertRedirect('/login');
        $this->assertGuest('web');
    }

    public function test_a_super_admin_changes_their_password_from_my_account(): void
    {
        $owner = SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1']);
        $code = $this->withAuthenticator($owner);
        $this->actingAs($owner, 'ops')->get("{$this->ops}/account")->assertInertia(fn (Assert $p) => $p->component('Ops/Account')
            ->where('account', ['name' => 'Platform owner', 'email' => 'owner@example.com', 'needsCode' => true, 'minLength' => 12]));

        $new = ['current_password' => 'long-password-1', 'code' => $code];
        $this->put("{$this->ops}/account/password", [...$new, 'password' => 'only-ten-c', 'password_confirmation' => 'only-ten-c'])->assertSessionHasErrors('password');
        $this->put("{$this->ops}/account/password", [...$new, 'password' => 'a-much-longer-password', 'password_confirmation' => 'a-much-longer-password'])->assertSessionHas('success');

        $this->assertTrue(Hash::check('a-much-longer-password', $owner->fresh()->password));
        Notification::assertSentTo($owner, PasswordChanged::class, fn (PasswordChanged $n) => $n->superAdmin);
        $this->get("{$this->ops}/account")->assertOk();

        $owner->forceFill(['password' => 'reset-on-the-server-1'])->save(); // e.g. ops:create-admin
        $this->get("{$this->ops}/account")->assertRedirect("{$this->ops}/login");
    }

    public function test_change_password_needs_the_right_sign_in(): void
    {
        $this->put('/app/settings/password')->assertRedirect('/login');
        $this->put('/me/security/password')->assertRedirect('/login');
        $this->put("{$this->ops}/account/password")->assertRedirect("{$this->ops}/login");
        $this->actingAs(User::factory()->admin()->create())->put("{$this->ops}/account/password")->assertRedirect("{$this->ops}/login");
    }
}
