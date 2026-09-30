<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\PasswordReset;
use App\Notifications\PortalInvite;
use App\Support\Audit;
use Illuminate\Support\Str;

/**
 * Single-use set-password links, for portal invites (7 days) and self-service resets (60 minutes).
 * Only a SHA-256 hash of the token is stored, and a new link replaces any earlier one.
 */
class PasswordLinks
{
    public const INVITE_DAYS = 7;
    public const RESET_MINUTES = 60;

    /** Give an employee portal access: create their login if needed and email a set-password link. */
    public function invite(Employee $employee, User $by): void
    {
        $user = $employee->user ?? User::create([
            'business_id' => $employee->business_id,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'role' => User::ROLE_EMPLOYEE,
            'password' => Str::random(64), // unusable until they set their own
        ]);
        if (! $employee->user_id) {
            $employee->user()->associate($user)->save();
        }
        $user->forceFill(['active' => true, 'invited_at' => now()]);

        $token = $this->issue($user, now()->addDays(self::INVITE_DAYS));
        $user->notify(new PortalInvite($token, $employee->business->name));
        Audit::log('employee.invited', $employee, [], $by);
    }

    /** A set-password link valid for the invite period, for a login created some other way (a new subscriber). */
    public function issueInvite(User $user): string
    {
        return $this->issue($user, now()->addDays(self::INVITE_DAYS));
    }

    /** Forgotten password: email a short-lived link. Silently does nothing for unknown or inactive accounts. */
    public function reset(string $email): void
    {
        $user = User::where('email', mb_strtolower(trim($email)))->where('active', true)->first();
        if (! $user) {
            return;
        }
        $token = $this->issue($user, now()->addMinutes(self::RESET_MINUTES));
        $user->notify(new PasswordReset($token));
        Audit::log('auth.password_reset_requested', $user, actor: $user);
    }

    /** The user a link belongs to, or null if the link is unknown, used or expired. */
    public static function findUser(string $token): ?User
    {
        $user = User::with('business')->where('password_token', self::hash($token))->first();

        return $user && $user->password_token_expires_at?->isFuture() ? $user : null;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function issue(User $user, \DateTimeInterface $expires): string
    {
        $token = Str::random(64);
        $user->forceFill(['password_token' => self::hash($token), 'password_token_expires_at' => $expires])->save();

        return $token;
    }
}
