<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** role:admin or role:employee — sends people to their own home instead of a 403. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();
        if ($user && $user->role !== $role) {
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
