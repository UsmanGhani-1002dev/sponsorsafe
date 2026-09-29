<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Second sign-in step: an authenticator-app code. Required for business admins (set up on first
 * sign-in), optional for employees.
 */
class TwoFactorController extends Controller
{
    public function show(Request $request, Google2FA $g2fa): Response|RedirectResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $setup = null;
        if (! $user->two_factor_confirmed_at) {
            if (! $user->two_factor_secret) {
                $user->forceFill(['two_factor_secret' => $g2fa->generateSecretKey(32)])->save();
            }
            $setup = [
                'secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')),
                'otpauth' => $g2fa->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret),
            ];
        }

        return Inertia::render('Auth/TwoFactor', [
            'setup' => $setup,
            'account' => ['name' => $user->name, 'email' => $user->email, 'business' => $user->business?->name],
        ]);
    }

    public function verify(Request $request, Google2FA $g2fa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $this->pending($request);
        if (! $user || ! $user->two_factor_secret) {
            return redirect()->route('login');
        }

        $key = 'login-2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please wait a few minutes and try again.']);
        }
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        if (! $g2fa->verifyKey($user->two_factor_secret, $code, 1)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => 'That code is not right. Use the current 6-digit code from your authenticator app.']);
        }
        RateLimiter::clear($key);

        if (! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_confirmed_at' => now()])->save();
            Audit::log('auth.two_factor_enabled', $user, actor: $user);
        }

        return SignIn::complete($request, $user, (bool) ($request->session()->get(SignIn::PENDING)['remember'] ?? false));
    }

    private function pending(Request $request): ?User
    {
        $id = $request->session()->get(SignIn::PENDING)['id'] ?? null;
        $user = $id ? User::with('business')->where('active', true)->find($id) : null;

        return $user && $user->business?->isActive() ? $user : null;
    }
}
