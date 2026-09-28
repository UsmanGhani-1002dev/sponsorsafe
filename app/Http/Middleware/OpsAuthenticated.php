<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OpsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('ops')->check()) {
            return redirect()->route('ops.login');
        }

        return $next($request);
    }
}
