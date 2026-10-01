<?php

namespace App\Services;

use App\Models\SuperAdmin;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * "Change password" for business admins (Settings), employees (My details) and super admins (My account).
 * Needs the current password, plus the authenticator code for anyone with two-step sign-in on (always for
 * admins and super admins). Other devices are signed out (EndSessionsAfterPasswordChange, and a new
 * remember-me token), the change is audited and an email tells the person, in case it was not them.
 */
class PasswordChange
{
    private const ATTEMPTS = 5;
    private const LOCK_SECONDS = 300;

    public function __construct(private Google2FA $g2fa) {}

    public static function needsCode(User|SuperAdmin $person): bool
    {
        return $person->two_factor_confirmed_at !== null && $person->two_factor_secret !== null;
    }

    /** Super admins can see every business, so their passwords are longer (as on their set-password link). */
    public static function minLength(User|SuperAdmin $person): int
    {
        return $person instanceof SuperAdmin ? 12 : 8;
    }

    public function change(Request $request, User|SuperAdmin $person): void
    {
        $needsCode = self::needsCode($person);
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(self::minLength($person)), 'different:current_password'],
            'code' => [$needsCode ? 'required' : 'nullable', 'string'],
        ], [
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a new password, not the one you use now.',
            'code.required' => 'Enter the 6-digit code from your authenticator app.',
        ], ['current_password' => 'current password', 'password' => 'new password']);

        $key = 'password-change:'.class_basename($person).':'.$person->getKey();
        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            throw ValidationException::withMessages(['current_password' => 'Too many attempts. Try again in a few minutes.']);
        }
        if (! Hash::check($data['current_password'], $person->password)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw ValidationException::withMessages(['current_password' => 'That password is not right.']);
        }
        if ($needsCode && ! $this->g2fa->verifyKey($person->two_factor_secret, preg_replace('/\D/', '', (string) $data['code']), 1)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);
            throw ValidationException::withMessages(['code' => 'That code is not right. Use the current code from your authenticator app.']);
        }
        RateLimiter::clear($key);

        $person->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();
        Audit::log('auth.password_changed', $person, actor: $person);
        $person->notify(new PasswordChanged($person instanceof SuperAdmin));
    }
}
