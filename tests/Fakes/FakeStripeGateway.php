<?php

namespace Tests\Fakes;

use App\Billing\StripeGateway;
use App\Models\Business;
use Carbon\CarbonImmutable;

/** Stands in for Stripe in tests: no network calls, and records what was asked. */
class FakeStripeGateway extends StripeGateway
{
    public ?string $checkError = null;
    /** @var array{business_id: int, customer: string, label: ?string, next: ?CarbonImmutable}|null */
    public ?array $result = null;
    public array $checkouts = [];

    public function check(string $secret): ?string
    {
        return $this->checkError;
    }

    public function checkoutUrl(Business $business, string $successUrl, string $cancelUrl): string
    {
        $business->forceFill(['stripe_id' => 'cus_fake'.$business->id])->save();
        $this->checkouts[] = compact('successUrl', 'cancelUrl') + ['business' => $business->id];

        return 'https://checkout.stripe.test/c/pay/cs_test_123';
    }

    public function checkoutResult(string $sessionId): ?array
    {
        return $this->result;
    }

    public function portalUrl(Business $business, string $returnUrl): string
    {
        return 'https://billing.stripe.test/p/session/test_123';
    }

    /** @var array<int, int> business id => new price */
    public array $priceChanges = [];
    public bool $failPriceChange = false;

    public function changePrice(Business $business, int $pence): void
    {
        if ($this->failPriceChange) {
            throw new \RuntimeException('Stripe is down');
        }
        $this->priceChanges[$business->id] = $pence;
    }

    public function priceId(int $pence): string
    {
        return 'price_fake_'.$pence;
    }
}
