<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PasswordLinks;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email-first sign-in: 1) email → Next shows the account name, 2) password.
 * The looked-up email lives in the session, never in the page URL.
 */
class LoginController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($user = $request->user()) {
            return redirect($user->homeRoute());
        }

        $account = null;
        if ($email = $request->session()->get('login.email')) {
            $user = User::with('business')->where('email', $email)->first();
            if ($user) {
                $account = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'initials' => $user->initials(),
                    'business' => $user->business?->name,
                    'role' => $user->isAdmin() ? 'Admin' : 'Employee',
                ];
            }
        }

        return Inertia::render('Auth/Login', ['account' => $account]);
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $key = 'login-lookup:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please wait a minute and try again.']);
        }
        RateLimiter::hit($key, 60);

        $email = mb_strtolower(trim($data['email']));
        if (! User::where('email', $email)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['email' => 'We could not find an account with that email. Check it, or ask your employer to invite you.']);
        }

        $request->session()->put('login.email', $email);

        return redirect()->route('login');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->session()->forget(['login.email', SignIn::PENDING]);

        return redirect()->route('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $email = $request->session()->get('login.email');
        if (! $email) {
            return redirect()->route('login');
        }

        $key = 'login:'.$email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $user = User::with('business')->where('email', $email)->where('active', true)->first();
        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['password' => 'That password is not right. Try again or reset it.']);
        }
        RateLimiter::clear($key);

        if (! $user->business?->isActive()) {
            throw ValidationException::withMessages(['password' => "Access for {$user->business?->name} is paused. Please contact support to reactivate the subscription."]);
        }

        return SignIn::afterPassword($request, $user, $request->boolean('remember'));
    }

    /** "Forgot password?" on the password step: emails a reset link to the account being signed in to. */
    public function forgot(Request $request, PasswordLinks $links): RedirectResponse
    {
        $email = $request->session()->get('login.email');
        if (! $email) {
            return redirect()->route('login');
        }
        $key = 'password-reset:'.$email;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'We have already sent a link. Check your inbox and spam folder, or try again in a few minutes.');
        }
        RateLimiter::hit($key, 600);
        $links->reset($email);

        return back()->with('success', "We have emailed a link to {$email} to choose a new password. It expires in ".PasswordLinks::RESET_MINUTES.' minutes.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
