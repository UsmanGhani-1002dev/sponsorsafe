<?php

namespace App\Http\Controllers\Ops;

use App\Billing\Gateways;
use App\Billing\StripeGateway;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Payment gateways: Stripe keys (PayPal next). Keys are encrypted at rest; only the last 4 characters come back. */
class GatewayController extends Controller
{
    /** Stripe events the webhook endpoint must be subscribed to. */
    public const STRIPE_EVENTS = [
        'checkout.session.completed', 'invoice.paid', 'invoice.payment_failed',
        'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted',
        'customer.updated', 'customer.deleted',
    ];

    public function show(): Response
    {
        $s = Gateways::stripe();

        return Inertia::render('Ops/Gateways', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'stripe' => [
                'mode' => $s['mode'],
                'publishable' => Gateways::mask($s['publishable']),
                'secret' => Gateways::mask($s['secret']),
                'webhookSecret' => Gateways::mask($s['webhook_secret']),
                'status' => $s['status'],
                'checkedAt' => $s['checked_at'],
                'error' => $s['error'],
                'webhookUrl' => route('stripe.webhook'),
                'events' => self::STRIPE_EVENTS,
            ],
        ]);
    }

    public function updateStripe(Request $request, StripeGateway $stripe): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:test,live'],
            'publishable' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);
        $current = Gateways::stripe();
        $publishable = trim($data['publishable'] ?? '') ?: $current['publishable'];
        $secret = trim($data['secret'] ?? '') ?: $current['secret'];
        $webhook = trim($data['webhook_secret'] ?? '') ?: $current['webhook_secret'];

        // Test keys only in test mode, live keys only in live mode: a mix-up takes real money by mistake.
        $prefix = $data['mode'] === 'live' ? 'live' : 'test';
        $errors = array_filter([
            'publishable' => blank($publishable) ? 'Please add the publishable key.'
                : (! str_starts_with($publishable, "pk_{$prefix}_") ? "The publishable key should start with pk_{$prefix}_." : null),
            'secret' => blank($secret) ? 'Please add the secret key.'
                : (! preg_match("/^(sk|rk)_{$prefix}_/", $secret) ? "The secret key should start with sk_{$prefix}_ (or rk_{$prefix}_)." : null),
            'webhook_secret' => filled($webhook) && ! str_starts_with($webhook, 'whsec_') ? 'The webhook signing secret should start with whsec_.' : null,
        ]);
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $error = $stripe->check($secret);
        Gateways::saveStripe([
            'mode' => $data['mode'],
            'publishable' => $data['publishable'] ?? null,
            'secret' => $data['secret'] ?? null,
            'webhook_secret' => $data['webhook_secret'] ?? null,
            'status' => $error ? 'failed' : 'connected',
            'checked_at' => now()->format('j M Y, H:i'),
            'error' => $error,
        ]);
        Audit::log('ops.gateway_changed', null, [
            'gateway' => 'stripe', 'mode' => $data['mode'], 'connected' => ! $error,
            'changed' => array_keys(array_filter(array_intersect_key($data, array_flip(['publishable', 'secret', 'webhook_secret'])))),
        ]);

        return back()->with($error ? 'error' : 'success', $error
            ? "Saved, but the connection test failed. {$error}"
            : 'Saved. Stripe is connected'.(blank($webhook) ? ', but add the webhook signing secret so payments and failures reach us.' : '.'));
    }
}
