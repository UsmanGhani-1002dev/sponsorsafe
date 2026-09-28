<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $request->session()->forget('login.email');

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

        if (! Auth::guard('web')->attempt(['email' => $email, 'password' => $request->input('password'), 'active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['password' => 'That password is not right. Try again or reset it.']);
        }
        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::guard('web')->user();
        if (! $user->business?->isActive()) {
            $name = $user->business?->name;
            Auth::guard('web')->logout();
            throw ValidationException::withMessages(['password' => "Access for {$name} is paused. Please contact support to reactivate the subscription."]);
        }

        $request->session()->regenerate();
        $request->session()->forget('login.email');
        $user->forceFill(['last_login_at' => now()])->save();
        Audit::log('auth.login', $user, actor: $user);

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
