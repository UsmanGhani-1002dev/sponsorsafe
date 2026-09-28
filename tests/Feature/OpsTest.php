<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OpsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = '/'.config('sponsorsafe.ops_path');
    }

    private function admin(bool $withTwoFactor = true): SuperAdmin
    {
        $g = new Google2FA();

        return SuperAdmin::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'long-password-1',
            'two_factor_secret' => $g->generateSecretKey(32), 'two_factor_confirmed_at' => $withTwoFactor ? now() : null,
        ]);
    }

    public function test_super_admin_area_needs_password_and_authenticator_code(): void
    {
        $admin = $this->admin();
        $this->get($this->path)->assertRedirect($this->path.'/login');

        $this->post($this->path.'/login', ['email' => 'owner@example.com', 'password' => 'long-password-1'])->assertRedirect($this->path.'/verify');
        $this->get($this->path)->assertRedirect($this->path.'/login'); // not signed in yet

        $this->post($this->path.'/verify', ['code' => '000000'])->assertSessionHasErrors('code');

        $code = (new Google2FA())->getCurrentOtp($admin->two_factor_secret);
        $this->post($this->path.'/verify', ['code' => $code])->assertRedirect($this->path);
        $this->get($this->path)->assertOk()->assertInertia(fn (Assert $p) => $p->component('Ops/Businesses'));
    }

    public function test_first_sign_in_shows_authenticator_setup(): void
    {
        $this->admin(withTwoFactor: false);
        $this->post($this->path.'/login', ['email' => 'owner@example.com', 'password' => 'long-password-1']);
        $this->get($this->path.'/verify')->assertInertia(fn (Assert $p) => $p->component('Ops/TwoFactor')->has('setup.secret')->has('setup.otpauth'));
    }

    public function test_business_users_cannot_use_the_super_admin_area(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get($this->path)->assertRedirect($this->path.'/login');
        $this->post($this->path.'/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_ip_allowlist_hides_the_area(): void
    {
        config(['sponsorsafe.ops_allowed_ips' => ['10.0.0.1']]);
        $this->get($this->path.'/login')->assertNotFound();
    }

    public function test_super_admin_can_suspend_and_reactivate_a_business(): void
    {
        $admin = $this->admin();
        $business = Business::factory()->create(['name' => 'Acme Ltd']);

        $this->actingAs($admin, 'ops')->post($this->path.'/businesses/'.$business->id.'/toggle')->assertSessionHas('success');
        $this->assertSame('suspended', $business->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ops.business.suspended', 'subject_id' => $business->id]);

        $this->actingAs($admin, 'ops')->post($this->path.'/businesses/'.$business->id.'/toggle');
        $this->assertSame('active', $business->fresh()->status);
    }
}
