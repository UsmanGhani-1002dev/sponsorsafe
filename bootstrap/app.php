<?php

use App\Http\Middleware\ApplyStripeKeys;
use App\Http\Middleware\EndSessionsAfterPasswordChange;
use App\Http\Middleware\EnsureBusinessActive;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\OpsAuthenticated;
use App\Http\Middleware\OpsIpAllowlist;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class, EndSessionsAfterPasswordChange::class]);
        // Sign out a session whose password changed elsewhere before `auth` decides who is signed in.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EndSessionsAfterPasswordChange::class);
        $middleware->alias([
            'role' => EnsureRole::class,
            'business.active' => EnsureBusinessActive::class,
            'ops.ip' => OpsIpAllowlist::class,
            'ops.auth' => OpsAuthenticated::class,
            'stripe' => ApplyStripeKeys::class,
        ]);
        // Stripe and PayPal sign their webhooks instead (checked in the webhook controllers).
        $middleware->validateCsrfTokens(except: ['stripe/webhook', 'paypal/webhook']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->homeRoute() ?? '/');
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
