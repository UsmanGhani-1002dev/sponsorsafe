<?php

namespace App\Services;

use App\Models\SuperAdmin;
use App\Notifications\SuperAdminInvite;
use App\Support\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Super admins add other super admins (Super admins page). The new one gets an emailed set-password link
 * (7 days, single use, only its hash stored) and sets up an authenticator app on first sign-in. Nobody can
 * remove themselves or the last super admin. `php artisan ops:create-admin` still works on the server.
 */
class SuperAdmins
{
    public function invite(string $name, string $email, SuperAdmin $by): SuperAdmin
    {
        $email = mb_strtolower(trim($email));
        if (SuperAdmin::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email is already a super admin.']);
        }
        $admin = SuperAdmin::create(['name' => trim($name), 'email' => $email, 'password' => Str::random(64)]); // unusable until they set their own
        $this->sendInvite($admin, $by);
        Audit::log('ops.super_admin_invited', $admin, ['email' => $email], $by);

        return $admin;
    }

    /** A new link for someone who has not signed in yet (the old link stops working). */
    public function resend(SuperAdmin $admin, SuperAdmin $by): void
    {
        if ($admin->last_login_at) {
            throw ValidationException::withMessages(['admin' => "{$admin->name} has already signed in."]);
        }
        $this->sendInvite($admin, $by);
        Audit::log('ops.super_admin_invite_resent', $admin, [], $by);
    }

    public function remove(SuperAdmin $admin, SuperAdmin $by): void
    {
        if ($admin->is($by)) {
            throw ValidationException::withMessages(['admin' => 'You cannot remove your own login. Ask another super admin to do it.']);
        }
        if (SuperAdmin::count() <= 1) {
            throw ValidationException::withMessages(['admin' => 'There must always be at least one super admin.']);
        }
        Audit::log('ops.super_admin_removed', $admin, ['email' => $admin->email], $by);
        $admin->delete();
    }

    /** The super admin a link belongs to, or null if the link is unknown, used or expired. */
    public static function findByToken(string $token): ?SuperAdmin
    {
        $admin = SuperAdmin::where('password_token', PasswordLinks::hash($token))->first();

        return $admin && $admin->password_token_expires_at?->isFuture() ? $admin : null;
    }

    public function setPassword(SuperAdmin $admin, string $password): void
    {
        $admin->forceFill(['password' => $password, 'password_token' => null, 'password_token_expires_at' => null])->save();
        Audit::log('ops.password_set', $admin, actor: $admin);
    }

    private function sendInvite(SuperAdmin $admin, SuperAdmin $by): void
    {
        $token = Str::random(64);
        $admin->forceFill([
            'password_token' => PasswordLinks::hash($token),
            'password_token_expires_at' => now()->addDays(PasswordLinks::INVITE_DAYS),
            'invited_at' => now(),
        ])->save();
        $admin->notify(new SuperAdminInvite($token, $by->name));
    }
}
