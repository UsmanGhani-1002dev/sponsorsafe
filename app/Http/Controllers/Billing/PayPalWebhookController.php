<?php

namespace App\Http\Controllers\Billing;

use App\Billing\PayPalGateway;
use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * PayPal → us. Every webhook is first checked with PayPal (verify-webhook-signature, using the saved webhook
 * ID); anything else gets 403. Then: activated / payment completed → paid, payment failed / suspended →
 * grace period, cancelled / expired → suspended.
 */
class PayPalWebhookController extends Controller
{
    public const EVENTS = [
        'BILLING.SUBSCRIPTION.ACTIVATED', 'PAYMENT.SALE.COMPLETED', 'BILLING.SUBSCRIPTION.PAYMENT.FAILED',
        'BILLING.SUBSCRIPTION.SUSPENDED', 'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED',
    ];

    public function __invoke(Request $request, PayPalGateway $paypal, Subscriptions $subscriptions): Response
    {
        abort_unless($paypal->verifyWebhook($request), 403);

        $event = $request->json()->all();
        $resource = $event['resource'] ?? [];
        $type = $event['event_type'] ?? '';
        // Subscription events carry the subscription itself; a sale carries it as billing_agreement_id.
        $business = $this->business($type === 'PAYMENT.SALE.COMPLETED' ? ($resource['billing_agreement_id'] ?? null) : ($resource['id'] ?? null));

        if ($business) {
            match ($type) {
                'BILLING.SUBSCRIPTION.ACTIVATED' => $subscriptions->paid($business, 'paypal', 'PayPal', $this->next($resource)),
                'PAYMENT.SALE.COMPLETED' => $subscriptions->paid($business, 'paypal', 'PayPal'),
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED', 'BILLING.SUBSCRIPTION.SUSPENDED' => $subscriptions->failed($business),
                'BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED' => $subscriptions->cancelled($business),
                default => null,
            };
        }

        return response('Webhook handled', 200);
    }

    private function business(?string $subscriptionId): ?Business
    {
        return $subscriptionId ? Business::where('paypal_subscription_id', $subscriptionId)->first() : null;
    }

    private function next(array $resource): ?CarbonImmutable
    {
        $next = $resource['billing_info']['next_billing_time'] ?? null;

        return $next ? CarbonImmutable::parse($next) : null;
    }
}
