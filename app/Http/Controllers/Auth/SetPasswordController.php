<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordLinks;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** The single-use link from a portal invite or a password reset: choose a password, then sign in. */
class SetPasswordController extends Controller
{
    public function show(string $token): Response
    {
        $user = PasswordLinks::findUser($token);

        return Inertia::render('Auth/SetPassword', [
            'token' => $token,
            'account' => $user ? ['name' => $user->name, 'email' => $user->email, 'business' => $user->business?->name, 'firstTime' => $user->last_login_at === null] : null,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $user = PasswordLinks::findUser($token);
        if (! $user) {
            return redirect()->route('password.set', $token);
        }
        $request->validate(['password' => ['required', 'confirmed', Password::min(8)]], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user->forceFill(['password' => $request->input('password'), 'password_token' => null, 'password_token_expires_at' => null])->save();
        Audit::log('auth.password_set', $user, actor: $user);

        if (! $user->business?->isActive()) {
            return redirect()->route('login')->with('success', 'Password saved.');
        }

        // The emailed link proves who they are; admins still add their authenticator code.
        return SignIn::afterPassword($request, $user);
    }
}
