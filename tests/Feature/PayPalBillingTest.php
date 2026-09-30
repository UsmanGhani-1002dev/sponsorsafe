<?php

namespace Tests\Feature;

use App\Billing\Gateways;
use App\Billing\StripeGateway;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PriceChangeNotice;
use App\Notifications\WelcomeSubscriber;
use App\Support\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakeStripeGateway;
use Tests\TestCase;

/** Stage 7b part 2: PayPal sign-up, webhooks and Manage billing; moving subscribers to a new price (30 days' notice). */
class PayPalBillingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api-m.sandbox.paypal.com';

    private string $ops;
    private FakeStripeGateway $stripe;
    /** What PayPal answers for GET /v1/billing/subscriptions/{id}. */
    private array $subscription = ['status' => 'ACTIVE'];
    private string $verification = 'SUCCESS';
    private int $tokenStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        $this->stripe = new FakeStripeGateway;
        $this->app->instance(StripeGateway::class, $this->stripe);

        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            $path = substr($request->url(), strlen(self::API));

            return match (true) {
                $path === '/v1/oauth2/token' => Http::response($this->tokenStatus === 200 ? ['access_token' => 'A21-token', 'expires_in' => 32400] : ['error_description' => 'Client Authentication failed'], $this->tokenStatus),
                $path === '/v1/catalogs/products' => Http::response(['id' => 'PROD-1'], 201),
                $path === '/v1/billing/plans' => Http::response(['id' => 'P-PLAN'.$request['billing_cycles'][0]['pricing_scheme']['fixed_price']['value']], 201),
                $path === '/v1/billing/subscriptions' => Http::response(['id' => 'I-SUB'.$request['custom_id'], 'status' => 'APPROVAL_PENDING', 'links' => [
                    ['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=BA-'.$request['custom_id'], 'method' => 'GET'],
                ]], 201),
                str_starts_with($path, '/v1/billing/subscriptions/') => Http::response(['id' => substr($path, 26), 'custom_id' => substr($path, 31), // I-SUB{business id}
                    'subscriber' => ['email_address' => 'payer@example.com'], 'billing_info' => ['next_billing_time' => '2026-10-30T10:00:00Z'], ...$this->subscription]),
                $path === '/v1/notifications/verify-webhook-signature' => Http::response(['verification_status' => $this->verification]),
                str_ends_with($path, '/update-pricing-schemes') => Http::response(null, 204),
                default => Http::response(['message' => 'Not found'], 404),
            };
        });
    }

    private function connectPaypal(): void
    {
        Gateways::savePaypal(['mode' => 'sandbox', 'client_id' => 'AclientABCD', 'secret' => 'EsecretWXYZ', 'webhook_id' => 'WH12345678', 'status' => 'connected']);
    }

    private function signup(array $overrides = [])
    {
        return $this->post('/signup', [
            'business' => 'Harbour Dental Ltd', 'licence' => 'PQ8R3T1', 'name' => 'Sara Jones', 'email' => 'sara@harbour.example',
            'phone' => '', 'employees' => '1-5', 'pay' => 'paypal', 'agree' => true,
            'form_token' => Crypt::encryptString((string) (now()->timestamp - 30)), 'website' => '',
            ...$overrides,
        ]);
    }

    private function webhook(string $type, array $resource, bool $signed = true)
    {
        $headers = $signed ? [
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA', 'PAYPAL-CERT-URL' => 'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-1',
            'PAYPAL-TRANSMISSION-ID' => 'tx-1', 'PAYPAL-TRANSMISSION-SIG' => 'sig', 'PAYPAL-TRANSMISSION-TIME' => now()->toIso8601String(),
        ] : [];

        return $this->flushHeaders()->withHeaders($headers)->postJson('/paypal/webhook', ['id' => 'WH-EVT-1', 'event_type' => $type, 'resource' => $resource]);
    }

    private function paypalCustomer(array $attributes = []): Business
    {
        $business = Business::factory()->create(['payment_provider' => 'paypal', 'payment_label' => 'PayPal', 'paypal_subscription_id' => 'I-LIVE1', 'paypal_plan_id' => 'P-PLAN20.00', ...$attributes]);
        User::factory()->admin()->create(['business_id' => $business->id]);

        return $business;
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1', 'two_factor_confirmed_at' => now()]);
    }

    // ---- Sign-up with PayPal ----

    public function test_paypal_is_offered_once_connected_and_signup_goes_to_paypal_to_approve(): void
    {
        $this->get('/signup')->assertInertia(fn (Assert $p) => $p->where('gateways.paypal', false));
        $this->connectPaypal();
        $this->get('/signup')->assertInertia(fn (Assert $p) => $p->where('gateways.paypal', true));

        $this->signup()->assertRedirect(); // to PayPal
        $business = Business::sole();
        $this->assertSame([Business::PENDING, 'paypal', 'I-SUB'.$business->id, 'P-PLAN20.00'],
            [$business->status, $business->payment_provider, $business->paypal_subscription_id, $business->paypal_plan_id]);

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/billing/subscriptions')
            && $r['plan_id'] === 'P-PLAN20.00' && $r['custom_id'] === (string) $business->id
            && $r['subscriber']['email_address'] === 'sara@harbour.example'
            && str_contains($r['application_context']['return_url'], '/signup/paypal/done')
            && $r['application_context']['shipping_preference'] === 'NO_SHIPPING');

        // The plan is created once per price and reused.
        $this->signup(['email' => 'second@harbour.example']);
        Http::assertSentCount(5); // token, product, plan, subscription; then only the second subscription
        $this->assertSame(['P-PLAN20.00'], Business::pluck('paypal_plan_id')->unique()->values()->all());
    }

    public function test_returning_from_paypal_activates_only_an_active_subscription_created_for_that_business(): void
    {
        $this->connectPaypal();
        $this->signup();
        $business = Business::sole();

        $this->subscription = ['status' => 'APPROVED']; // approved but not running yet
        $this->get('/signup/paypal/done?subscription_id=I-SUB'.$business->id)->assertInertia(fn (Assert $p) => $p->where('confirmed', false));
        $this->assertSame(Business::PENDING, $business->fresh()->status);

        $this->subscription = ['status' => 'ACTIVE'];
        $this->get('/signup/paypal/done?subscription_id=I-SUB999')->assertInertia(fn (Assert $p) => $p->where('confirmed', false));
        $this->get('/signup/paypal/done?subscription_id=nope')->assertRedirect('/signup');

        $this->get('/signup/paypal/done?subscription_id=I-SUB'.$business->id)->assertInertia(fn (Assert $p) => $p->component('Website/SignupDone')
            ->where('confirmed', true)->where('first', 'Sara')->where('email', 'sara@harbour.example'));
        $business->refresh();
        $this->assertSame([Business::ACTIVE, 'PayPal', '2026-10-30'], [$business->status, $business->payment_label, $business->next_payment_on->toDateString()]);
        Notification::assertSentTo($business->admins()->sole(), WelcomeSubscriber::class);
    }

    public function test_paypal_trouble_during_signup_shows_a_plain_message(): void
    {
        $this->connectPaypal();
        $this->tokenStatus = 401;

        $this->signup()->assertSessionHasErrors(['form' => 'We could not reach PayPal. Please try again in a moment, or pay by card.']);
        $this->assertSame(Business::PENDING, Business::sole()->status);
    }

    // ---- Webhooks ----

    public function test_paypal_webhooks_are_checked_with_paypal_first(): void
    {
        $business = $this->paypalCustomer();

        $this->webhook('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-LIVE1'])->assertForbidden(); // PayPal not set up
        $this->connectPaypal();
        $this->webhook('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-LIVE1'], signed: false)->assertForbidden();
        $this->verification = 'FAILURE';
        $this->webhook('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-LIVE1'])->assertForbidden();
        $this->assertNull($business->fresh()->grace_ends_on);

        $this->verification = 'SUCCESS';
        $this->webhook('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-LIVE1'])->assertOk();
        $this->assertNotNull($business->fresh()->grace_ends_on);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), 'verify-webhook-signature') && $r['webhook_id'] === 'WH12345678' && $r['transmission_sig'] === 'sig');
    }

    public function test_paypal_payment_failed_then_paid_then_cancelled(): void
    {
        $this->connectPaypal();
        $business = $this->paypalCustomer();
        $admin = $business->admins()->sole();

        $this->webhook('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-LIVE1'])->assertOk();
        $this->assertTrue($business->fresh()->inGrace());
        Notification::assertSentTo($admin, PaymentFailed::class);

        $this->webhook('PAYMENT.SALE.COMPLETED', ['id' => 'SALE-1', 'billing_agreement_id' => 'I-LIVE1'])->assertOk();
        $this->assertFalse($business->fresh()->inGrace());

        $this->webhook('BILLING.SUBSCRIPTION.CANCELLED', ['id' => 'I-LIVE1'])->assertOk();
        $this->assertSame([Business::SUSPENDED, Business::SUSPENDED_CANCELLED], [$business->fresh()->status, $business->fresh()->suspended_reason]);

        // Unknown subscriptions are ignored.
        $this->webhook('BILLING.SUBSCRIPTION.CANCELLED', ['id' => 'I-SOMEONE-ELSE'])->assertOk();
    }

    public function test_the_activated_webhook_activates_a_pending_signup(): void
    {
        $this->connectPaypal();
        $this->signup();
        $business = Business::sole();

        $this->webhook('BILLING.SUBSCRIPTION.ACTIVATED', ['id' => $business->paypal_subscription_id, 'billing_info' => ['next_billing_time' => '2026-10-30T10:00:00Z']])->assertOk();
        $this->assertSame([Business::ACTIVE, '2026-10-30'], [$business->fresh()->status, $business->fresh()->next_payment_on->toDateString()]);
        Notification::assertSentToTimes($business->admins()->sole(), WelcomeSubscriber::class, 1);
    }

    // ---- Manage billing and gateway keys ----

    public function test_manage_billing_sends_paypal_customers_to_paypal(): void
    {
        $this->connectPaypal();
        $admin = $this->paypalCustomer()->admins()->sole();

        $this->actingAs($admin)->get('/app/settings')->assertInertia(fn (Assert $p) => $p->where('plan.canManage', true)->where('plan.provider', 'paypal'));
        $this->post('/app/settings/billing')->assertRedirect('https://www.sandbox.paypal.com/myaccount/autopay/');
    }

    public function test_super_admin_saves_paypal_keys_encrypted_and_they_are_tested(): void
    {
        $this->actingAs($this->superAdmin(), 'ops');
        $this->put("{$this->ops}/gateways/paypal", ['mode' => 'sandbox'])->assertSessionHasErrors(['client_id' => 'Please add the client ID.', 'secret' => 'Please add the client secret.']);
        $this->put("{$this->ops}/gateways/paypal", ['mode' => 'sandbox', 'client_id' => 'Aid', 'secret' => 'Esec', 'webhook_id' => 'not valid!'])->assertSessionHasErrors('webhook_id');

        $this->put("{$this->ops}/gateways/paypal", ['mode' => 'sandbox', 'client_id' => 'AclientABCD', 'secret' => 'EsecretWXYZ', 'webhook_id' => 'WH12345678'])
            ->assertSessionHas('success', 'Saved. PayPal is connected.');
        $this->assertStringNotContainsString('EsecretWXYZ', json_encode(PlatformSetting::get(Gateways::PAYPAL)));
        $this->get("{$this->ops}/gateways")->assertInertia(fn (Assert $p) => $p->where('paypal.status', 'connected')
            ->where('paypal.clientId', '••••ABCD')->where('paypal.secret', '••••WXYZ')->where('paypal.webhookId', '••••5678')
            ->where('paypal.webhookUrl', route('paypal.webhook')));

        $this->tokenStatus = 401;
        $this->put("{$this->ops}/gateways/paypal", ['mode' => 'live'])->assertSessionHas('error');
        $this->assertFalse(Gateways::paypalReady());
    }

    // ---- Moving existing subscribers to a new price ----

    public function test_existing_subscribers_are_emailed_30_days_ahead_then_moved_on_the_date(): void
    {
        $this->connectPaypal();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));
        $card = Business::factory()->create(['name' => 'Card Ltd', 'payment_provider' => 'stripe', 'stripe_id' => 'cus_1']);
        $pp1 = $this->paypalCustomer(['name' => 'PayPal One Ltd']);
        $pp2 = $this->paypalCustomer(['name' => 'PayPal Two Ltd', 'paypal_subscription_id' => 'I-LIVE2']);
        User::factory()->admin()->create(['business_id' => $card->id]);
        PlatformSetting::put(Pricing::KEY, ['price_pence' => 2500, 'employee_limit' => 20, 'training_price_pence' => 4900]);
        $this->actingAs($this->superAdmin(), 'ops');

        $this->get("{$this->ops}/pricing")->assertInertia(fn (Assert $p) => $p->where('subscribers.older', 3)->where('subscribers.waiting', 3)
            ->where('subscribers.plans', ['£20 · 15 employees (3)'])->where('subscribers.moveOn', '31 Oct 2026')
            ->has('subscribers.list', 3)
            ->where('subscribers.list.0', ['id' => $card->id, 'name' => 'Card Ltd', 'admin' => $card->admins()->sole()->email, 'plan' => '£20 · 15 employees', 'payment' => null, 'suspended' => false, 'movesOn' => null]));

        $this->post("{$this->ops}/pricing/move")->assertSessionHas('success', 'Emailed 3 subscriber(s). They move to the current plan on 31 Oct 2026.');
        Notification::assertSentTo($card->admins()->sole(), PriceChangeNotice::class, fn (PriceChangeNotice $n) => $n->oldPence === 2000 && $n->newPence === 2500
            && $n->newLimit === 20 && $n->on->toDateString() === '2026-10-31');
        $this->assertSame('2026-10-31', $card->fresh()->price_change_on->toDateString());
        $this->post("{$this->ops}/pricing/move")->assertSessionHas('success', 'Everyone is already on the current plan or has been told about it.');
        $this->get("{$this->ops}/pricing")->assertInertia(fn (Assert $p) => $p->where('subscribers.waiting', 0)->where('subscribers.scheduled', 3)->where('subscribers.scheduledOn', '31 Oct 2026')
            ->where('subscribers.list.0.name', 'Card Ltd')->where('subscribers.list.0.movesOn', '31 Oct 2026')->where('subscribers.list.2.payment', 'PayPal'));

        // The admin sees it coming in Settings.
        $this->actingAs($card->admins()->sole(), 'web')->get('/app/settings')->assertInertia(fn (Assert $p) => $p->where('plan.priceChange', ['on' => '31 Oct 2026', 'price' => '25', 'limit' => 20]));

        // Nothing moves before the date.
        $this->travelTo(CarbonImmutable::parse('2026-10-30 09:00'));
        $this->artisan('billing:check')->expectsOutputToContain('moved 0 to a new price');

        $this->travelTo(CarbonImmutable::parse('2026-10-31 06:00'));
        $this->artisan('billing:check')->expectsOutputToContain('moved 3 to a new price');
        $this->assertSame([$card->id => 2500], $this->stripe->priceChanges);
        // Both PayPal businesses share one plan: its price is changed once.
        $updates = Http::recorded(fn (HttpRequest $r) => str_ends_with($r->url(), '/update-pricing-schemes'))->values();
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('/v1/billing/plans/P-PLAN20.00/', $updates[0][0]->url());
        $this->assertSame('25.00', $updates[0][0]['pricing_schemes'][0]['pricing_scheme']['fixed_price']['value']);
        $this->assertSame([2500, 20, null], [$card->fresh()->plan_price_pence, $card->fresh()->employee_limit, $card->fresh()->price_change_on]);
        $this->assertSame([2500, 2500], [$pp1->fresh()->plan_price_pence, $pp2->fresh()->plan_price_pence]);
        $this->assertSame(3, AuditLog::where('action', 'billing.price_changed')->count());
    }

    public function test_a_gateway_error_leaves_the_price_change_scheduled_for_tomorrow(): void
    {
        $card = Business::factory()->create(['payment_provider' => 'stripe', 'stripe_id' => 'cus_1', 'price_change_pence' => 2500, 'price_change_limit' => 15, 'price_change_on' => today()]);
        $this->stripe->failPriceChange = true;

        $this->artisan('billing:check')->expectsOutputToContain('moved 0 to a new price');
        $this->assertSame([2000, today()->toDateString()], [$card->fresh()->plan_price_pence, $card->fresh()->price_change_on->toDateString()]);

        $this->stripe->failPriceChange = false;
        $this->artisan('billing:check')->expectsOutputToContain('moved 1 to a new price');
        $this->assertSame(2500, $card->fresh()->plan_price_pence);
    }
}
