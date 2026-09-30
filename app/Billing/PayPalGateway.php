<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\PlatformSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Every call to PayPal (REST API, subscriptions) goes through here, with plain HTTP (tests use Http::fake).
 * Credentials come from Gateways::paypal(). Failures throw PayPalException with a plain-English message.
 */
class PayPalGateway
{
    /** PayPal billing plans we created, per mode and amount: {sandbox: {product, plans: {"2000": "P-…"}}}. */
    private const PLANS = 'paypal_plans';

    public static function baseUrl(string $mode): string
    {
        return $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    /** Null when the client ID and secret work, otherwise a plain-English reason. */
    public function check(string $clientId, string $secret, string $mode): ?string
    {
        try {
            $response = Http::baseUrl(self::baseUrl($mode))->asForm()->withBasicAuth($clientId, $secret)->timeout(15)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException) {
            return 'Could not reach PayPal. Please try again.';
        }

        return match (true) {
            $response->successful() => null,
            $response->status() === 401 => 'PayPal did not accept the client ID and secret (check the mode matches: sandbox or live).',
            default => 'PayPal error: '.($response->json('error_description') ?? $response->status()),
        };
    }

    /**
     * Start a subscription for the business's plan. Returns [subscription id, approve URL, plan id] — the
     * visitor approves it on PayPal and comes back to $returnUrl with ?subscription_id=I-….
     *
     * @return array{string, string, string}
     */
    public function createSubscription(Business $business, string $returnUrl, string $cancelUrl): array
    {
        $plan = $this->planId($business->plan_price_pence);
        $admin = $business->admins()->orderBy('id')->first();
        [$given, $surname] = array_pad(explode(' ', (string) $admin?->name, 2), 2, '');

        $data = $this->call('post', '/v1/billing/subscriptions', [
            'plan_id' => $plan,
            'custom_id' => (string) $business->id,
            'subscriber' => array_filter(['name' => array_filter(['given_name' => $given, 'surname' => $surname]), 'email_address' => $admin?->email]),
            'application_context' => [
                'brand_name' => config('app.name'),
                'locale' => 'en-GB',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);
        $approve = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;
        if (! $approve || empty($data['id'])) {
            throw new PayPalException('PayPal did not return an approval link.');
        }

        return [$data['id'], $approve, $plan];
    }

    /**
     * The subscription as PayPal sees it: status (APPROVAL_PENDING, APPROVED, ACTIVE, SUSPENDED, CANCELLED,
     * EXPIRED), our business id and the next billing date.
     *
     * @return array{id: string, status: string, business_id: int, next: ?CarbonImmutable, email: ?string}
     */
    public function subscription(string $id): array
    {
        $data = $this->call('get', '/v1/billing/subscriptions/'.rawurlencode($id));
        $next = $data['billing_info']['next_billing_time'] ?? null;

        return [
            'id' => (string) $data['id'],
            'status' => (string) $data['status'],
            'business_id' => (int) ($data['custom_id'] ?? 0),
            'next' => $next ? CarbonImmutable::parse($next) : null,
            'email' => $data['subscriber']['email_address'] ?? null,
        ];
    }

    /** PayPal's own check that a webhook really came from PayPal for our webhook ID. */
    public function verifyWebhook(Request $request): bool
    {
        $webhookId = Gateways::paypal()['webhook_id'];
        $event = json_decode($request->getContent(), true);
        if (blank($webhookId) || ! is_array($event) || blank($request->header('PAYPAL-TRANSMISSION-SIG'))) {
            return false;
        }
        try {
            $data = $this->call('post', '/v1/notifications/verify-webhook-signature', [
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'webhook_id' => $webhookId,
                'webhook_event' => $event,
            ]);
        } catch (PayPalException) {
            return false;
        }

        return ($data['verification_status'] ?? null) === 'SUCCESS';
    }

    /**
     * Change the price of a plan for everyone on it (used for a scheduled price change). PayPal applies it
     * from the next billing cycle; we have already emailed the subscribers 30 days ahead.
     */
    public function changePlanPrice(string $planId, int $pence): void
    {
        $this->call('post', '/v1/billing/plans/'.rawurlencode($planId).'/update-pricing-schemes', [
            'pricing_schemes' => [[
                'billing_cycle_sequence' => 1,
                'pricing_scheme' => ['fixed_price' => ['value' => number_format($pence / 100, 2, '.', ''), 'currency_code' => 'GBP']],
            ]],
        ]);
        // The plan now charges the new amount: new sign-ups at that price can use it too.
        $this->remember(function (array $saved) use ($planId, $pence) {
            $plans = array_filter($saved['plans'], fn ($id) => $id !== $planId); // no longer the old amount
            $plans[(string) $pence] ??= $planId;

            return ['product' => $saved['product'], 'plans' => $plans];
        });
    }

    /** Where a PayPal customer manages or cancels automatic payments (PayPal has no per-merchant portal). */
    public function manageUrl(): string
    {
        return Gateways::paypal()['mode'] === 'live'
            ? 'https://www.paypal.com/myaccount/autopay/'
            : 'https://www.sandbox.paypal.com/myaccount/autopay/';
    }

    /** The monthly GBP plan for this amount, created in PayPal the first time it is needed. */
    public function planId(int $pence): string
    {
        $saved = $this->saved();
        if ($id = $saved['plans'][(string) $pence] ?? null) {
            return $id;
        }

        $product = $saved['product'] ?? $this->call('post', '/v1/catalogs/products', [
            'name' => config('app.name').' plan',
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ])['id'];
        $plan = $this->call('post', '/v1/billing/plans', [
            'product_id' => $product,
            'name' => config('app.name').' monthly',
            'status' => 'ACTIVE',
            'billing_cycles' => [[
                'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0,
                'pricing_scheme' => ['fixed_price' => ['value' => number_format($pence / 100, 2, '.', ''), 'currency_code' => 'GBP']],
            ]],
            'payment_preferences' => ['auto_bill_outstanding' => true, 'payment_failure_threshold' => 3],
        ])['id'];

        $this->remember(fn (array $s) => ['product' => $product, 'plans' => [(string) $pence => $plan] + $s['plans']]);

        return $plan;
    }

    private function saved(): array
    {
        $all = (array) PlatformSetting::get(self::PLANS, []);

        return $all[Gateways::paypal()['mode']] ?? ['product' => null, 'plans' => []];
    }

    private function remember(callable $update): void
    {
        $all = (array) PlatformSetting::get(self::PLANS, []);
        $mode = Gateways::paypal()['mode'];
        $all[$mode] = $update($all[$mode] ?? ['product' => null, 'plans' => []]);
        PlatformSetting::put(self::PLANS, $all);
    }

    /** One authenticated API call; returns the decoded JSON body. */
    private function call(string $method, string $path, array $body = []): array
    {
        try {
            $response = $this->client()->{$method}($path, $method === 'get' ? null : $body)->throw();
        } catch (RequestException $e) {
            throw new PayPalException('PayPal error: '.($e->response->json('message') ?? $e->response->status()), previous: $e);
        } catch (ConnectionException $e) {
            throw new PayPalException('Could not reach PayPal. Please try again.', previous: $e);
        }

        return $response->json() ?? [];
    }

    private function client(): PendingRequest
    {
        $p = Gateways::paypal();
        if (blank($p['client_id']) || blank($p['secret'])) {
            throw new PayPalException('PayPal is not set up.');
        }
        $token = Cache::remember('paypal_token_'.md5($p['mode'].$p['client_id']), 3000, function () use ($p) {
            $response = Http::baseUrl(self::baseUrl($p['mode']))->asForm()->withBasicAuth($p['client_id'], $p['secret'])->timeout(15)
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            if (! $response->successful()) {
                throw new PayPalException('PayPal did not accept the client ID and secret.');
            }

            return $response->json('access_token');
        });

        return Http::baseUrl(self::baseUrl($p['mode']))->withToken($token)->acceptJson()->asJson()->timeout(20)
            ->withHeaders(['Prefer' => 'return=representation']);
    }
}
