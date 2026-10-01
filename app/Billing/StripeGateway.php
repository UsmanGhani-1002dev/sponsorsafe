<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\PlatformSetting;
use Carbon\CarbonImmutable;
use Laravel\Cashier\Cashier;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * Every call to Stripe goes through here (tests swap in a fake). Cashier must be pointed at the saved
 * keys first: routes use the `stripe` middleware, commands call Gateways::applyStripe().
 */
class StripeGateway
{
    /** Stripe Price IDs we created, per mode and amount: {test: {product, prices: {"2000": "price_…"}}}. */
    private const PRICES = 'stripe_prices';

    /** Null when the secret key works, otherwise a plain-English reason. */
    public function check(string $secret): ?string
    {
        try {
            (new StripeClient($secret))->balance->retrieve();

            return null;
        } catch (AuthenticationException) {
            return 'Stripe did not accept the secret key.';
        } catch (ApiErrorException $e) {
            return 'Stripe error: '.$e->getMessage();
        }
    }

    /** Hosted Stripe Checkout for the business's monthly plan. Card details never touch our server. */
    public function checkoutUrl(Business $business, string $successUrl, string $cancelUrl): string
    {
        return $business->newSubscription('default', $this->priceId($business->plan_price_pence))
            ->withMetadata(['business_id' => $business->id])
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $business->id,
                'locale' => 'en-GB',
            ])->url;
    }

    /**
     * A finished Checkout: business id, card label and next payment date. Null while it is not paid yet.
     *
     * @return array{business_id: int, customer: string, label: ?string, next: ?CarbonImmutable}|null
     */
    public function checkoutResult(string $sessionId): ?array
    {
        $session = Cashier::stripe()->checkout->sessions->retrieve($sessionId, ['expand' => ['subscription.default_payment_method']]);
        if ($session->status !== 'complete' || ! in_array($session->payment_status, ['paid', 'no_payment_required'], true)) {
            return null;
        }
        $subscription = $session->subscription;
        $card = $subscription?->default_payment_method?->card;
        // Newer Stripe API versions keep the billing period on the subscription item.
        $item = $subscription?->items?->data[0] ?? null;
        $periodEnd = $item?->current_period_end ?? $subscription?->current_period_end ?? null;

        return [
            'business_id' => (int) $session->client_reference_id,
            'customer' => (string) $session->customer,
            'label' => $card ? 'Card ending '.$card->last4 : null,
            'next' => $periodEnd ? CarbonImmutable::createFromTimestamp($periodEnd) : null,
        ];
    }

    /** Stripe's billing portal: change card, see invoices, cancel. */
    public function portalUrl(Business $business, string $returnUrl): string
    {
        return $business->billingPortalUrl($returnUrl);
    }

    /** A scheduled price change: the subscription moves to the new price from the next invoice (no proration). */
    public function changePrice(Business $business, int $pence): void
    {
        // Asks Stripe for the live subscription rather than Cashier's local copy, which only webhooks fill in:
        // a missed webhook must never leave Stripe charging the old price.
        $stripe = Cashier::stripe();
        $subscription = collect($stripe->subscriptions->all(['customer' => $business->stripe_id, 'status' => 'all', 'limit' => 10])->data)
            ->first(fn ($s) => in_array($s->status, ['active', 'trialing', 'past_due', 'unpaid'], true));
        if (! $subscription) {
            throw new \RuntimeException("Stripe has no live subscription for {$business->name}.");
        }
        $item = $subscription->items->data[0];
        $price = $this->priceId($pence);
        if ($item->price->id !== $price) {
            $stripe->subscriptions->update($subscription->id, ['items' => [['id' => $item->id, 'price' => $price]], 'proration_behavior' => 'none']);
        }
    }

    /** The monthly GBP price for this amount, created in Stripe the first time it is needed. */
    public function priceId(int $pence): string
    {
        $mode = Gateways::stripe()['mode'];
        $all = (array) PlatformSetting::get(self::PRICES, []);
        $saved = $all[$mode] ?? ['product' => null, 'prices' => []];
        $stripe = Cashier::stripe();

        // A saved ID can go stale (keys changed to another Stripe account, or deleted in the dashboard):
        // use it only while Stripe still has it, active, at this amount; otherwise create a fresh one.
        if ($id = $saved['prices'][(string) $pence] ?? null) {
            try {
                $price = $stripe->prices->retrieve($id);
                if ($price->active && $price->unit_amount === $pence && $price->currency === 'gbp') {
                    return $id;
                }
            } catch (InvalidRequestException) {
                // gone: create below
            }
        }
        if ($saved['product']) {
            try {
                if (! $stripe->products->retrieve($saved['product'])->active) {
                    $saved['product'] = null;
                }
            } catch (InvalidRequestException) {
                $saved['product'] = null;
            }
        }

        $saved['product'] ??= $stripe->products->create(['name' => config('app.name').' plan'])->id;
        $price = $stripe->prices->create([
            'product' => $saved['product'],
            'currency' => 'gbp',
            'unit_amount' => $pence,
            'recurring' => ['interval' => 'month'],
        ]);
        $saved['prices'][(string) $pence] = $price->id;
        $all[$mode] = $saved;
        PlatformSetting::put(self::PRICES, $all);

        return $price->id;
    }
}
