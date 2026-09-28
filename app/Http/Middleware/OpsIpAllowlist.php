<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Super-admin area is only reachable from allowed IPs. Anyone else gets a plain 404. */
class OpsIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('sponsorsafe.ops_allowed_ips', []);
        if ($allowed && ! in_array($request->ip(), $allowed, true)) {
            abort(404);
        }

        return $next($request);
    }
}
