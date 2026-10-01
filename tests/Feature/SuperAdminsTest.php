<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Notifications\SuperAdminInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Super admins add, re-invite and remove other super admins from the super admin area. */
class SuperAdminsTest extends TestCase
{
    use RefreshDatabase;

    private string $ops;

    private SuperAdmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        Notification::fake();
        $this->owner = SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1', 'two_factor_confirmed_at' => now(), 'last_login_at' => now()]);
    }

    private function inviteSara(): SuperAdmin
    {
        $this->actingAs($this->owner, 'ops')->post("{$this->ops}/super-admins", ['name' => 'Sara Malik', 'email' => 'Sara@Enovtec.example'])
            ->assertSessionHas('success', 'Invite sent to sara@enovtec.example.');

        return SuperAdmin::where('email', 'sara@enovtec.example')->sole();
    }

    public function test_a_super_admin_invites_another_who_sets_a_password_then_signs_in_with_an_authenticator(): void
    {
        $sara = $this->inviteSara();
        $this->assertTrue(AuditLog::where('action', 'ops.super_admin_invited')->where('subject_id', $sara->id)->exists());

        $token = null;
        Notification::assertSentTo($sara, SuperAdminInvite::class, function (SuperAdminInvite $n) use (&$token) {
            $token = $n->token;

            return $n->invitedBy === 'Platform owner';
        });
        $this->assertNotSame($token, $sara->password_token); // only the hash is stored

        $this->get("{$this->ops}/super-admins")->assertInertia(fn (Assert $p) => $p->component('Ops/SuperAdmins')
            ->has('admins', 2)
            ->where('admins.0.you', true)
            ->where('admins.1.email', 'sara@enovtec.example')
            ->where('admins.1.invited', true));

        // Sara opens the link (not signed in) and chooses a password.
        auth('ops')->logout();
        $this->get("{$this->ops}/set-password/{$token}")->assertInertia(fn (Assert $p) => $p->component('Ops/SetPassword')->where('account.name', 'Sara Malik'));
        $this->post("{$this->ops}/set-password/{$token}", ['password' => 'short-pass', 'password_confirmation' => 'short-pass'])->assertSessionHasErrors('password');
        $this->post("{$this->ops}/set-password/{$token}", ['password' => 'saras-long-password', 'password_confirmation' => 'saras-long-password'])
            ->assertRedirect("{$this->ops}/login");

        $this->assertTrue(Hash::check('saras-long-password', $sara->fresh()->password));
        $this->get("{$this->ops}/set-password/{$token}")->assertInertia(fn (Assert $p) => $p->where('account', null)); // single use

        $this->post("{$this->ops}/login", ['email' => 'sara@enovtec.example', 'password' => 'saras-long-password'])->assertRedirect("{$this->ops}/verify");
        $this->get("{$this->ops}/verify")->assertInertia(fn (Assert $p) => $p->component('Ops/TwoFactor')->has('setup.secret'));
    }

    public function test_an_email_can_only_be_a_super_admin_once(): void
    {
        $this->actingAs($this->owner, 'ops')->post("{$this->ops}/super-admins", ['name' => 'Again', 'email' => 'OWNER@example.com'])
            ->assertSessionHasErrors(['email' => 'This email is already a super admin.']);
        $this->post("{$this->ops}/super-admins", ['name' => '', 'email' => 'nope'])->assertSessionHasErrors(['name', 'email']);
        $this->assertSame(1, SuperAdmin::count());
    }

    public function test_an_invite_can_be_resent_until_they_sign_in_and_old_links_stop_working(): void
    {
        $sara = $this->inviteSara();
        $first = $sara->password_token;
        $this->post("{$this->ops}/super-admins/{$sara->id}/resend")->assertSessionHas('success');
        $this->assertNotSame($first, $sara->fresh()->password_token);
        Notification::assertSentToTimes($sara, SuperAdminInvite::class, 2);

        $sara->forceFill(['last_login_at' => now()])->save();
        $this->post("{$this->ops}/super-admins/{$sara->id}/resend")->assertSessionHasErrors(['admin' => 'Sara Malik has already signed in.']);
    }

    public function test_expired_links_do_not_work(): void
    {
        $sara = $this->inviteSara();
        $token = null;
        Notification::assertSentTo($sara, SuperAdminInvite::class, function (SuperAdminInvite $n) use (&$token) {
            $token = $n->token;

            return true;
        });
        $this->travel(8)->days();
        auth('ops')->logout();
        $this->post("{$this->ops}/set-password/{$token}", ['password' => 'saras-long-password', 'password_confirmation' => 'saras-long-password'])
            ->assertRedirect("{$this->ops}/set-password/{$token}");
        $this->assertFalse(Hash::check('saras-long-password', $sara->fresh()->password));
    }

    public function test_super_admins_can_be_removed_but_never_yourself_or_the_last(): void
    {
        $sara = $this->inviteSara();
        $this->delete("{$this->ops}/super-admins/{$this->owner->id}")->assertSessionHasErrors(['admin' => 'You cannot remove your own login. Ask another super admin to do it.']);

        $this->delete("{$this->ops}/super-admins/{$sara->id}")->assertSessionHas('success', 'Sara Malik can no longer sign in.');
        $this->assertNull($sara->fresh());
        $this->assertTrue(AuditLog::where('action', 'ops.super_admin_removed')->exists());
    }

    public function test_only_super_admins_manage_super_admins(): void
    {
        $this->post("{$this->ops}/super-admins", ['name' => 'Intruder', 'email' => 'x@example.com'])->assertRedirect("{$this->ops}/login");
        $this->assertSame(1, SuperAdmin::count());
    }
}
