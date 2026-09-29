<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The last step of every sign-in: after the password (and authenticator code, when needed). */
class SignIn
{
    /** Session key holding a user who has passed the password step but still owes a 2FA code. */
    public const PENDING = 'login.2fa';

    public static function complete(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        $request->session()->forget(['login.email', self::PENDING]);
        $request->session()->regenerate();
        Auth::guard('web')->login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $user, actor: $user);

        return redirect()->intended($user->homeRoute());
    }

    /** Password is right: sign in, or ask for the authenticator code first. */
    public static function afterPassword(Request $request, User $user, bool $remember = false): RedirectResponse
    {
        if (! $user->needsTwoFactor()) {
            return self::complete($request, $user, $remember);
        }
        $request->session()->put(self::PENDING, ['id' => $user->id, 'remember' => $remember]);

        return redirect()->route('login.two-factor');
    }
}
