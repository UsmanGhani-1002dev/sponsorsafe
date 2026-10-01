<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\SuperAdmins;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/** Super-admin sign-in: password, then a mandatory authenticator-app code. */
class OpsAuthController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (auth('ops')->check()) {
            return redirect()->route('ops.businesses');
        }

        return Inertia::render('Ops/Login', ['base' => '/'.config('sponsorsafe.ops_path')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'ops-login:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again later.']);
        }

        $admin = SuperAdmin::where('email', mb_strtolower($data['email']))->first();
        if (! $admin || ! Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => 'Those details are not right.']);
        }

        $request->session()->put('ops.pending_id', $admin->id);

        return redirect()->route('ops.two-factor');
    }

    public function twoFactor(Request $request, Google2FA $g2fa): Response|RedirectResponse
    {
        $admin = SuperAdmin::find($request->session()->get('ops.pending_id'));
        if (! $admin) {
            return redirect()->route('ops.login');
        }

        $setup = null;
        if (! $admin->two_factor_confirmed_at) {
            if (! $admin->two_factor_secret) {
                $admin->forceFill(['two_factor_secret' => $g2fa->generateSecretKey(32)])->save();
            }
            $setup = [
                'secret' => $admin->two_factor_secret,
                'otpauth' => $g2fa->getQRCodeUrl(config('app.name').' Super admin', $admin->email, $admin->two_factor_secret),
            ];
        }

        return Inertia::render('Ops/TwoFactor', ['setup' => $setup, 'base' => '/'.config('sponsorsafe.ops_path')]);
    }

    public function verify(Request $request, Google2FA $g2fa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $admin = SuperAdmin::find($request->session()->get('ops.pending_id'));
        if (! $admin || ! $admin->two_factor_secret) {
            return redirect()->route('ops.login');
        }

        $key = 'ops-2fa:'.$admin->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Start again.']);
        }

        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        if (! $g2fa->verifyKey($admin->two_factor_secret, $code, 1)) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['code' => 'That code is not right. Use the current code from your authenticator app.']);
        }
        RateLimiter::clear($key);

        if (! $admin->two_factor_confirmed_at) {
            $admin->forceFill(['two_factor_confirmed_at' => now()])->save();
        }

        $request->session()->forget('ops.pending_id');
        $request->session()->regenerate();
        Auth::guard('ops')->login($admin);
        $admin->forceFill(['last_login_at' => now()])->save();
        Audit::log('ops.login', $admin, actor: $admin);

        return redirect()->route('ops.businesses');
    }

    /** The emailed link for a new super admin: choose a password, then sign in with the authenticator setup. */
    public function setPassword(string $token): Response
    {
        $admin = SuperAdmins::findByToken($token);

        return Inertia::render('Ops/SetPassword', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'token' => $token,
            'account' => $admin ? ['name' => $admin->name, 'email' => $admin->email] : null,
        ]);
    }

    public function storePassword(Request $request, string $token, SuperAdmins $admins): RedirectResponse
    {
        $admin = SuperAdmins::findByToken($token);
        if (! $admin) {
            return redirect()->route('ops.password.set', $token);
        }
        $request->validate(['password' => ['required', 'confirmed', Password::min(12)]], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);
        $admins->setPassword($admin, $request->input('password'));

        return redirect()->route('ops.login')->with('success', 'Password saved. Sign in to set up your authenticator app.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('ops')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('ops.login');
    }
}
