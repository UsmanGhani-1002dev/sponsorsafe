<?php

namespace App\Services;

use App\Models\Business;
use App\Models\User;
use App\Notifications\AdminInvite;
use App\Support\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * More than one admin login per business (Settings → Admin logins). A new admin gets an invite email with
 * a 7-day set-password link and sets up an authenticator app on first sign-in (2FA is required for admins).
 * Nobody can remove themselves or the last admin, so a business is never locked out.
 */
class BusinessAdmins
{
    public function __construct(private PasswordLinks $links) {}

    public function invite(Business $business, string $name, string $email, User $by): User
    {
        $email = mb_strtolower(trim($email));
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has a login. Use a different email.']);
        }
        $admin = User::create([
            'business_id' => $business->id,
            'name' => trim($name),
            'email' => $email,
            'role' => User::ROLE_ADMIN,
            'active' => true,
            'password' => Str::random(64), // unusable until they set their own
        ]);
        $this->sendInvite($admin, $business, $by);
        Audit::log('admin.invited', $admin, ['email' => $email], $by);

        return $admin;
    }

    /** A new link for an admin who has not signed in yet (the old link stops working). */
    public function resend(User $admin, User $by): void
    {
        if ($admin->last_login_at) {
            throw ValidationException::withMessages(['admin' => "{$admin->name} has already signed in. They can use \"Forgot password\" if needed."]);
        }
        $this->sendInvite($admin, $admin->business, $by);
        Audit::log('admin.invite_resent', $admin, [], $by);
    }

    public function remove(User $admin, User $by): void
    {
        if ($admin->is($by)) {
            throw ValidationException::withMessages(['admin' => 'You cannot remove your own login. Ask another admin to do it.']);
        }
        if (User::where('business_id', $admin->business_id)->where('role', User::ROLE_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages(['admin' => 'A business needs at least one admin.']);
        }
        Audit::log('admin.removed', $admin, ['email' => $admin->email], $by);
        $admin->delete();
    }

    private function sendInvite(User $admin, Business $business, User $by): void
    {
        $admin->forceFill(['invited_at' => now()])->save();
        $admin->notify(new AdminInvite($this->links->issueInvite($admin), $business->name, $by->name));
    }
}
