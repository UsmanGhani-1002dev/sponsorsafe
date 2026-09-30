<?php

namespace App\Http\Controllers\Billing;

use App\Billing\StripeGateway;
use App\Billing\Subscriptions;
use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Laravel\Cashier\Http\Middleware\VerifyWebhookSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe → us. Cashier keeps the subscriptions table in sync; on top of that:
 * checkout.session.completed / invoice.paid activate or clear a grace period, invoice.payment_failed
 * starts the grace period, customer.subscription.deleted suspends. The keys come from the `stripe`
 * route middleware, which runs before the signature check.
 */
class StripeWebhookController extends WebhookController
{
    public function __construct(private StripeGateway $gateway, private Subscriptions $subscriptions)
    {
        // Unlike Cashier's default, never accept an unsigned webhook.
        $this->middleware(VerifyWebhookSignature::class);
    }

    public function handleWebhook(Request $request)
    {
        // With no secret saved, anyone could "sign" with an empty key.
        abort_if(blank(config('cashier.webhook.secret')), 403, 'Webhook secret not set.');

        return parent::handleWebhook($request);
    }

    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'];
        if (($session['mode'] ?? null) === 'subscription' && ($result = $this->gateway->checkoutResult($session['id']))) {
            if ($business = Business::find($result['business_id'])) {
                $this->subscriptions->paid($business, 'stripe', $result['label'], $result['next']);
            }
        }

        return $this->successMethod();
    }

    protected function handleInvoicePaid(array $payload): Response
    {
        $invoice = $payload['data']['object'];
        if (($invoice['amount_paid'] ?? 0) > 0 && ($business = $this->business($invoice['customer'] ?? null))) {
            $end = $invoice['lines']['data'][0]['period']['end'] ?? null;
            $this->subscriptions->paid($business, 'stripe', null, $end ? CarbonImmutable::createFromTimestamp($end) : null);
        }

        return $this->successMethod();
    }

    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        if ($business = $this->business($payload['data']['object']['customer'] ?? null)) {
            $this->subscriptions->failed($business);
        }

        return $this->successMethod();
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        parent::handleCustomerSubscriptionDeleted($payload);
        if ($business = $this->business($payload['data']['object']['customer'] ?? null)) {
            $this->subscriptions->cancelled($business);
        }

        return $this->successMethod();
    }

    private function business(?string $customer): ?Business
    {
        $billable = $customer ? Cashier::findBillable($customer) : null;

        return $billable instanceof Business ? $billable : null;
    }
}
