<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Signs out anyone whose business is suspended or whose own account is disabled. */
class EnsureBusinessActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && (! $user->active || ! $user->business?->isActive())) {
            $name = $user->business?->name;
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', $user->active && $name
                ? "Access for {$name} is paused. Please contact support to reactivate the subscription."
                : 'Your account is not active. Please contact your employer.');
        }

        return $next($request);
    }
}
