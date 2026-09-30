<?php

namespace App\Http\Middleware;

use App\Billing\Gateways;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Loads the super admin's Stripe keys into Cashier for routes that talk to Stripe. */
class ApplyStripeKeys
{
    public function handle(Request $request, Closure $next): Response
    {
        Gateways::applyStripe();

        return $next($request);
    }
}
