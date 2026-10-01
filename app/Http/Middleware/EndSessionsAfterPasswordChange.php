<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Each session remembers a fingerprint of the password it signed in with (business users and super admins
 * separately). When the password changes — Change password, a reset link, ops:create-admin — every other
 * session is signed out on its next request. The session that made the change gets the new fingerprint.
 */
class EndSessionsAfterPasswordChange
{
    private const GUARDS = ['web', 'ops'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::GUARDS as $guard) {
            $auth = Auth::guard($guard);
            $stored = $request->session()->get($this->key($guard));
            if ($stored !== null && $auth->check() && ! hash_equals($stored, $this->fingerprint($auth->user()->getAuthPassword()))) {
                $auth->logout();
                $request->session()->forget($this->key($guard));
            }
        }

        $response = $next($request);

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();
            $user
                ? $request->session()->put($this->key($guard), $this->fingerprint($user->getAuthPassword()))
                : $request->session()->forget($this->key($guard));
        }

        return $response;
    }

    private function key(string $guard): string
    {
        return "auth.password_fingerprint.{$guard}";
    }

    private function fingerprint(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) config('app.key'));
    }
}
