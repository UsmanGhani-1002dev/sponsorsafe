<?php

namespace Tests\Feature;

use App\Billing\Gateways;
use App\Billing\StripeGateway;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Notifications\AccessPaused;
use App\Notifications\PaymentFailed;
use App\Notifications\WelcomeSubscriber;
use App\Services\PasswordLinks;
use App\Support\Pricing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakeStripeGateway;
use Tests\TestCase;

/** Stage 7b: sign-up with Stripe Checkout, webhooks, grace period and suspension, gateway keys, Manage billing. */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    private FakeStripeGateway $stripe;
    private string $ops;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        $this->stripe = new FakeStripeGateway;
        $this->app->instance(StripeGateway::class, $this->stripe);
        config(['cashier.key' => null, 'cashier.secret' => null, 'cashier.webhook.secret' => null]);
    }

    private function connectStripe(): void
    {
        Gateways::saveStripe(['mode' => 'test', 'publishable' => 'pk_test_abc1234', 'secret' => 'sk_test_secret9876', 'webhook_secret' => self::WEBHOOK_SECRET, 'status' => 'connected']);
    }

    private function signup(array $overrides = [])
    {
        return $this->post('/signup', [
            'business' => 'Northgate Care Ltd', 'licence' => 'KX7Q2M9P1', 'name' => 'Imran Ali', 'email' => 'Imran@Northgate.example',
            'phone' => '023 8000 0000', 'employees' => '6-10', 'pay' => 'card', 'agree' => true,
            'form_token' => Crypt::encryptString((string) (now()->timestamp - 30)), 'website' => '',
            ...$overrides,
        ]);
    }

    /** A Stripe webhook signed the way Stripe signs it (or with another secret). */
    private function webhook(string $type, array $object, string $secret = self::WEBHOOK_SECRET)
    {
        $payload = json_encode(['id' => 'evt_1', 'type' => $type, 'data' => ['object' => $object]]);
        $t = time();

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret),
        ], $payload);
    }

    private function customer(array $attributes = []): Business
    {
        $business = Business::factory()->create(['stripe_id' => 'cus_123', 'payment_provider' => 'stripe', 'payment_label' => 'Card ending 4242', ...$attributes]);
        User::factory()->admin()->create(['business_id' => $business->id, 'email' => 'hr@northgate.example']);

        return $business;
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1', 'two_factor_confirmed_at' => now()]);
    }

    // ---- Sign-up ----

    public function test_the_signup_page_only_offers_card_once_stripe_is_connected(): void
    {
        $this->get('/signup')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/Signup')
            ->where('gateways', ['card' => false, 'paypal' => false])->where('plan.price', '20')->has('bands', 4)->where('previous', null));

        $this->signup()->assertSessionHasErrors(['pay' => 'Card payments are not switched on yet. Please contact us to subscribe.']);
        $this->assertSame(0, Business::count());

        $this->connectStripe();
        $this->get('/signup')->assertInertia(fn (Assert $p) => $p->where('gateways.card', true));
    }

    public function test_signup_is_validated_like_the_prototype(): void
    {
        $this->connectStripe();
        $this->signup(['business' => '', 'licence' => '', 'name' => '', 'email' => 'nope', 'agree' => false])->assertSessionHasErrors([
            'business' => 'Please add your business name.',
            'licence' => 'Please add your sponsor licence number.',
            'name' => 'Please add your name.',
            'email' => 'Please add a valid email.',
            'agree' => 'Please agree to the terms and privacy policy.',
        ]);
        $this->signup(['employees' => '16+'])->assertSessionHasErrors(['employees' => 'The plan covers up to 15 employees. Please contact us for a larger plan.']);
        $this->signup(['pay' => 'paypal'])->assertSessionHasErrors(['pay' => 'PayPal is coming soon. Please pay by card for now.']);

        User::factory()->admin()->create(['email' => 'imran@northgate.example']);
        $this->signup()->assertSessionHasErrors(['email' => 'This email already has an account. Log in instead, or use a different email.']);

        // Bots: the hidden field, or a form sent back too quickly.
        $this->signup(['email' => 'new@northgate.example', 'website' => 'http://spam.example'])->assertSessionHasErrors('form');
        $this->signup(['email' => 'new@northgate.example', 'form_token' => Crypt::encryptString((string) now()->timestamp)])->assertSessionHasErrors('form');
        $this->assertSame(1, Business::count());
    }

    public function test_signup_creates_a_pending_business_and_sends_the_visitor_to_stripe_checkout(): void
    {
        $this->connectStripe();
        PlatformSetting::put(Pricing::KEY, ['price_pence' => 2500, 'employee_limit' => 12, 'training_price_pence' => 4900]);

        $this->signup()->assertRedirect('https://checkout.stripe.test/c/pay/cs_test_123');

        $business = Business::sole();
        $this->assertSame([Business::PENDING, 'Northgate Care Ltd', 'KX7Q2M9P1', '6-10', 2500, 12, 'stripe'],
            [$business->status, $business->name, $business->licence_number, $business->employees_band, $business->plan_price_pence, $business->employee_limit, $business->payment_provider]);
        $admin = $business->admins()->sole();
        $this->assertSame(['Imran Ali', 'imran@northgate.example', 'admin'], [$admin->name, $admin->email, $admin->role]);
        $this->assertStringContainsString('/signup/done?session_id={CHECKOUT_SESSION_ID}', $this->stripe->checkouts[0]['successUrl']);
        $this->assertStringContainsString('/signup?cancelled=1', $this->stripe->checkouts[0]['cancelUrl']);
        Notification::assertNothingSent();

        // Same email again before paying: the unpaid attempt is reused, not duplicated.
        $this->signup(['business' => 'Northgate Care Group Ltd'])->assertRedirect();
        $this->assertSame(['Northgate Care Group Ltd'], Business::pluck('name')->all());
        $this->assertSame(1, User::count());

        // Cancelled on Stripe: back to the form with their details kept.
        $this->get('/signup?cancelled=1')->assertInertia(fn (Assert $p) => $p->where('cancelled', true)
            ->where('previous.business', 'Northgate Care Group Ltd')->where('previous.email', 'imran@northgate.example'));

        // Nobody can sign in to an unpaid business.
        $this->assertFalse($business->isActive());
    }

    public function test_returning_from_a_paid_checkout_activates_the_business_and_emails_a_set_password_link(): void
    {
        $this->connectStripe();
        $this->signup();
        $business = Business::sole();
        $this->stripe->result = ['business_id' => $business->id, 'customer' => 'cus_fake', 'label' => 'Card ending 4242', 'next' => CarbonImmutable::parse('2026-10-30')];

        $this->get('/signup/done?session_id=cs_test_123')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/SignupDone')
            ->where('confirmed', true)->where('first', 'Imran')->where('business', 'Northgate Care Ltd')->where('email', 'imran@northgate.example'));

        $business->refresh();
        $this->assertSame([Business::ACTIVE, 'Card ending 4242', '2026-10-30'], [$business->status, $business->payment_label, $business->next_payment_on->toDateString()]);
        $admin = $business->admins()->sole();
        Notification::assertSentTo($admin, WelcomeSubscriber::class, function (WelcomeSubscriber $n) use ($admin) {
            return PasswordLinks::findUser($n->token)?->is($admin) && $n->business === 'Northgate Care Ltd';
        });
        $this->assertTrue(AuditLog::where('action', 'billing.activated')->where('business_id', $business->id)->exists());

        // The webhook for the same payment (or a refresh) does not send a second email.
        $this->get('/signup/done?session_id=cs_test_123')->assertOk();
        $this->webhook('checkout.session.completed', ['id' => 'cs_test_123', 'mode' => 'subscription'])->assertOk();
        Notification::assertSentToTimes($admin, WelcomeSubscriber::class, 1);
    }

    public function test_the_return_page_never_trusts_the_url_alone(): void
    {
        $this->connectStripe();
        $this->signup();
        $this->stripe->result = null; // Stripe says: not paid

        $this->get('/signup/done?session_id=cs_test_forged')->assertInertia(fn (Assert $p) => $p->where('confirmed', false));
        $this->assertSame(Business::PENDING, Business::sole()->status);
        $this->get('/signup/done')->assertRedirect('/signup');
        Notification::assertNothingSent();
    }

    // ---- Webhooks ----

    public function test_webhooks_must_be_signed_with_the_saved_secret(): void
    {
        $business = $this->customer();
        $failed = ['customer' => 'cus_123'];

        $this->webhook('invoice.payment_failed', $failed)->assertForbidden(); // no secret saved yet
        $this->connectStripe();
        $this->webhook('invoice.payment_failed', $failed, 'whsec_someone_else')->assertForbidden();
        $this->assertNull($business->fresh()->grace_ends_on);

        $this->webhook('invoice.payment_failed', $failed)->assertOk();
        $this->assertNotNull($business->fresh()->grace_ends_on);
    }

    public function test_a_failed_payment_starts_the_grace_period_once_and_emails_the_admins(): void
    {
        $this->connectStripe();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00'));
        $business = $this->customer();

        $this->webhook('invoice.payment_failed', ['customer' => 'cus_123'])->assertOk();
        $business->refresh();
        $this->assertSame(['2026-10-10', '2026-10-17', Business::ACTIVE], [$business->payment_failed_on->toDateString(), $business->grace_ends_on->toDateString(), $business->status]);
        $admin = $business->admins()->sole();
        Notification::assertSentTo($admin, PaymentFailed::class, fn (PaymentFailed $n) => $n->graceEnds->toDateString() === '2026-10-17');

        // Stripe retries: the grace period is not extended and no second email goes out.
        $this->travel(3)->days();
        $this->webhook('invoice.payment_failed', ['customer' => 'cus_123'])->assertOk();
        $this->assertSame('2026-10-17', $business->fresh()->grace_ends_on->toDateString());
        Notification::assertSentToTimes($admin, PaymentFailed::class, 1);

        // The admin sees a banner everywhere in the app.
        $this->actingAs($admin)->get('/app/settings')->assertInertia(fn (Assert $p) => $p->where('billing.graceEnds', '17 Oct 2026')
            ->where('plan.graceEnds', '17 Oct 2026')->where('plan.canManage', true));
    }

    public function test_a_payment_clears_the_grace_period_and_updates_the_next_payment_date(): void
    {
        $this->connectStripe();
        $business = $this->customer(['payment_failed_on' => today(), 'grace_ends_on' => today()->addDays(7)]);

        $end = CarbonImmutable::parse('2026-11-10')->timestamp;
        $this->webhook('invoice.paid', ['customer' => 'cus_123', 'amount_paid' => 2000, 'lines' => ['data' => [['period' => ['end' => $end]]]]])->assertOk();

        $business->refresh();
        $this->assertNull($business->grace_ends_on);
        $this->assertNull($business->payment_failed_on);
        $this->assertSame('2026-11-10', $business->next_payment_on->toDateString());
        $this->assertSame('Card ending 4242', $business->payment_label);
    }

    public function test_grace_over_suspends_and_a_later_payment_reopens_access(): void
    {
        $this->connectStripe();
        $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00'));
        $business = $this->customer();
        $this->webhook('invoice.payment_failed', ['customer' => 'cus_123']);

        $this->travelTo(CarbonImmutable::parse('2026-10-17 09:00')); // last day of grace: still open
        $this->artisan('billing:check')->assertSuccessful();
        $this->assertSame(Business::ACTIVE, $business->fresh()->status);

        $this->travelTo(CarbonImmutable::parse('2026-10-18 09:00'));
        $this->artisan('billing:check')->expectsOutputToContain('Suspended 1 business(es)')->assertSuccessful();
        $business->refresh();
        $this->assertSame([Business::SUSPENDED, Business::SUSPENDED_PAYMENT], [$business->status, $business->suspended_reason]);
        Notification::assertSentTo($business->admins()->sole(), AccessPaused::class, fn (AccessPaused $n) => $n->reason === 'payment');

        $this->actingAs($business->admins()->sole())->get('/app')->assertRedirect('/login');

        $this->webhook('invoice.paid', ['customer' => 'cus_123', 'amount_paid' => 2000])->assertOk();
        $this->assertSame([Business::ACTIVE, null], [$business->fresh()->status, $business->fresh()->suspended_reason]);
        $this->assertTrue(AuditLog::where('action', 'billing.reactivated')->exists());
    }

    public function test_a_cancelled_subscription_suspends_but_a_manual_suspension_is_never_lifted_by_payment(): void
    {
        $this->connectStripe();
        $business = $this->customer();

        $this->webhook('customer.subscription.deleted', ['id' => 'sub_1', 'customer' => 'cus_123'])->assertOk();
        $this->assertSame([Business::SUSPENDED, Business::SUSPENDED_CANCELLED], [$business->fresh()->status, $business->fresh()->suspended_reason]);

        $business->update(['suspended_reason' => Business::SUSPENDED_MANUAL]);
        $this->webhook('invoice.paid', ['customer' => 'cus_123', 'amount_paid' => 2000])->assertOk();
        $this->assertSame(Business::SUSPENDED, $business->fresh()->status);
    }

    public function test_abandoned_signups_are_removed_after_a_week(): void
    {
        $this->connectStripe();
        $this->signup();
        $this->signup(['email' => 'second@example.com', 'business' => 'Second Ltd']);
        Business::where('name', 'Northgate Care Ltd')->update(['updated_at' => now()->subDays(8)]);

        $this->artisan('billing:check')->expectsOutputToContain('removed 1 unpaid sign-up')->assertSuccessful();
        $this->assertSame(['Second Ltd'], Business::pluck('name')->all());
        $this->assertFalse(User::where('email', 'imran@northgate.example')->exists());
    }

    // ---- Manage billing ----

    public function test_manage_billing_opens_the_stripe_portal_for_card_customers_only(): void
    {
        $this->connectStripe();
        $business = $this->customer();
        $admin = $business->admins()->sole();

        $this->actingAs($admin)->post('/app/settings/billing')->assertRedirect('https://billing.stripe.test/p/session/test_123');
        $this->assertTrue(AuditLog::where('action', 'billing.portal_opened')->exists());

        $business->forceFill(['stripe_id' => null])->save();
        $this->actingAs($admin->fresh())->from('/app/settings')->post('/app/settings/billing')->assertRedirect('/app/settings')->assertSessionHas('error');

        $employee = User::factory()->create(['business_id' => $business->id]);
        $this->actingAs($employee)->post('/app/settings/billing')->assertRedirect('/me');
    }

    // ---- Super admin: payment gateways ----

    public function test_super_admin_saves_stripe_keys_encrypted_and_only_sees_the_last_four(): void
    {
        $this->actingAs($this->superAdmin(), 'ops');
        $this->get("{$this->ops}/gateways")->assertInertia(fn (Assert $p) => $p->component('Ops/Gateways')
            ->where('stripe.status', null)->where('stripe.secret', null)->where('stripe.webhookUrl', route('stripe.webhook')));

        $this->put("{$this->ops}/gateways/stripe", ['mode' => 'test', 'publishable' => 'pk_test_51Habc3kQ9', 'secret' => 'sk_test_51Hsecret7777', 'webhook_secret' => 'whsec_abcd1234'])
            ->assertSessionHas('success', 'Saved. Stripe is connected.');

        $raw = json_encode(PlatformSetting::get(Gateways::STRIPE));
        $this->assertStringNotContainsString('sk_test_51Hsecret7777', $raw);
        $this->assertStringNotContainsString('whsec_abcd1234', $raw);
        $this->assertSame('sk_test_51Hsecret7777', Gateways::stripe()['secret']);

        $this->get("{$this->ops}/gateways")->assertInertia(fn (Assert $p) => $p->where('stripe.status', 'connected')
            ->where('stripe.publishable', '••••3kQ9')->where('stripe.secret', '••••7777')->where('stripe.webhookSecret', '••••1234'));

        // Blank fields keep the saved keys.
        $this->put("{$this->ops}/gateways/stripe", ['mode' => 'test', 'publishable' => '', 'secret' => '', 'webhook_secret' => ''])->assertSessionHas('success');
        $this->assertSame('sk_test_51Hsecret7777', Gateways::stripe()['secret']);

        $log = AuditLog::where('action', 'ops.gateway_changed')->latest('id')->first();
        $this->assertStringNotContainsString('sk_test', json_encode($log->meta));
    }

    public function test_gateway_keys_must_match_the_mode_and_a_failed_test_is_shown(): void
    {
        $this->actingAs($this->superAdmin(), 'ops');
        $this->put("{$this->ops}/gateways/stripe", ['mode' => 'live', 'publishable' => 'pk_test_abc', 'secret' => 'sk_test_abc', 'webhook_secret' => 'nope'])
            ->assertSessionHasErrors([
                'publishable' => 'The publishable key should start with pk_live_.',
                'secret' => 'The secret key should start with sk_live_ (or rk_live_).',
                'webhook_secret' => 'The webhook signing secret should start with whsec_.',
            ]);
        $this->assertNull(PlatformSetting::get(Gateways::STRIPE));

        $this->stripe->checkError = 'Stripe did not accept the secret key.';
        $this->put("{$this->ops}/gateways/stripe", ['mode' => 'test', 'publishable' => 'pk_test_abc', 'secret' => 'sk_test_wrong'])
            ->assertSessionHas('error', 'Saved, but the connection test failed. Stripe did not accept the secret key.');
        $this->assertFalse(Gateways::stripeReady());
        $this->get('/signup')->assertInertia(fn (Assert $p) => $p->where('gateways.card', false));
    }

    public function test_only_the_super_admin_can_reach_payment_gateways(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get("{$this->ops}/gateways")->assertRedirect("{$this->ops}/login");
        $this->put("{$this->ops}/gateways/stripe", ['mode' => 'test'])->assertRedirect("{$this->ops}/login");
    }

    public function test_the_businesses_list_shows_billing_states(): void
    {
        $this->customer(['name' => 'Grace Ltd', 'grace_ends_on' => '2026-10-17']);
        Business::factory()->create(['name' => 'Paused Ltd', 'status' => Business::SUSPENDED, 'suspended_reason' => Business::SUSPENDED_PAYMENT]);
        Business::factory()->create(['name' => 'Unpaid Ltd', 'status' => Business::PENDING]);

        $this->actingAs($this->superAdmin(), 'ops')->get($this->ops)->assertInertia(fn (Assert $p) => $p
            ->where('stats.active', 1)->where('stats.suspended', 1)
            ->where('businesses.0.graceEnds', '17 Oct 2026')
            ->where('businesses.1.suspendedReason', 'payment')
            ->where('businesses.2.status', 'pending'));

        $unpaid = Business::where('name', 'Unpaid Ltd')->sole();
        $this->post("{$this->ops}/businesses/{$unpaid->id}/toggle")->assertStatus(422);
    }
}
