<?php

namespace Tests\Feature;

use App\Billing\Gateways;
use App\Billing\StripeGateway;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Employee;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\FakeStripeGateway;
use Tests\TestCase;

/** Plans by size: Starter £20 / 5, Standard £35 / 10, Corporate agreed per business. Upgrades, downgrades, Corporate. */
class PlanChangeTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeGateway $stripe;
    private string $ops;
    /** The plan PayPal reports for the subscription after the customer approves. */
    private string $paypalPlan = 'P-OLD';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        $this->stripe = new FakeStripeGateway;
        $this->app->instance(StripeGateway::class, $this->stripe);

        Http::preventStrayRequests();
        Http::fake(function (HttpRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $path === '/v1/oauth2/token' => Http::response(['access_token' => 'A21-token', 'expires_in' => 32400]),
                $path === '/v1/catalogs/products' => Http::response(['id' => 'PROD-1'], 201),
                $path === '/v1/billing/plans' => Http::response(['id' => 'P-PLAN'.$request['billing_cycles'][0]['pricing_scheme']['fixed_price']['value']], 201),
                $request->method() === 'GET' && str_starts_with($path, '/v1/billing/plans/') => Http::response(['id' => substr($path, 18), 'status' => 'ACTIVE',
                    'billing_cycles' => [['pricing_scheme' => ['fixed_price' => ['value' => rtrim(substr($path, 24), '0'), 'currency_code' => 'GBP']]]]]),
                $request->method() === 'GET' && str_starts_with($path, '/v1/catalogs/products/') => Http::response(['id' => substr($path, 22)]),
                str_ends_with($path, '/revise') => Http::response(['plan_id' => $request['plan_id'], 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/webapps/billing/subscriptions/update?ba_token=BA-9']]]),
                str_starts_with($path, '/v1/billing/subscriptions/') => Http::response(['id' => 'I-PP1', 'status' => 'ACTIVE', 'plan_id' => $this->paypalPlan, 'custom_id' => '1']),
                default => Http::response(['message' => 'Not found'], 404),
            };
        });
    }

    private function business(string $plan, array $attributes = [], int $employees = 0): Business
    {
        [$pence, $limit] = ['starter' => [2000, 5], 'standard' => [3500, 10], 'corporate' => [9900, 40]][$plan];
        $business = Business::factory()->create(['plan' => $plan, 'plan_price_pence' => $pence, 'employee_limit' => $limit, ...$attributes]);
        User::factory()->admin()->create(['business_id' => $business->id]);
        Employee::factory()->count($employees)->create(['business_id' => $business->id]);

        return $business;
    }

    private function admin(Business $business): User
    {
        return $business->admins()->sole();
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1', 'two_factor_confirmed_at' => now()]);
    }

    // ---- Self-service: Settings ----

    public function test_settings_show_the_plans_with_what_can_be_chosen(): void
    {
        $business = $this->business('standard', [], employees: 7);

        $this->actingAs($this->admin($business))->get('/app/settings')->assertInertia(fn (Assert $p) => $p
            ->where('plan.name', 'Standard')
            ->where('plan.options', [
                ['key' => 'starter', 'name' => 'Starter', 'price' => '20', 'limit' => 5, 'current' => false, 'allowed' => false, 'reason' => 'You have 7 current employees; this plan covers up to 5.'],
                ['key' => 'standard', 'name' => 'Standard', 'price' => '35', 'limit' => 10, 'current' => true, 'allowed' => false, 'reason' => null],
            ]));
    }

    public function test_a_card_customer_upgrades_the_limit_now_and_the_price_from_the_next_payment(): void
    {
        $business = $this->business('starter', ['payment_provider' => 'stripe', 'stripe_id' => 'cus_1'], employees: 5);

        $this->actingAs($this->admin($business))->post('/app/settings/plan', ['plan' => 'standard'])
            ->assertSessionHas('success', "You're now on Standard: up to 10 employees. The new price, £35 a month, applies from your next payment.");

        $this->assertSame([$business->id => 3500], $this->stripe->priceChanges); // swapped without proration
        $this->assertSame(['standard', 3500, 10], [$business->fresh()->plan, $business->fresh()->plan_price_pence, $business->fresh()->employee_limit]);
        $this->assertTrue(AuditLog::where('action', 'billing.plan_changed')->where('business_id', $business->id)->exists());

        // The 6th employee can now be added.
        $this->assertFalse($business->fresh()->employeeLimitReached());
    }

    public function test_downgrading_needs_few_enough_employees(): void
    {
        $business = $this->business('standard', ['payment_provider' => 'stripe', 'stripe_id' => 'cus_1'], employees: 6);
        $admin = $this->admin($business);

        $this->actingAs($admin)->post('/app/settings/plan', ['plan' => 'starter'])->assertSessionHas('error', 'You have 6 current employees; this plan covers up to 5.');
        $this->assertSame('standard', $business->fresh()->plan);
        $this->assertSame([], $this->stripe->priceChanges);

        $business->employees()->first()->forceFill(['ended_on' => today()->subDay()])->save(); // a leaver
        $this->post('/app/settings/plan', ['plan' => 'starter'])->assertSessionHas('success');
        $this->assertSame(['starter', 2000, 5], [$business->fresh()->plan, $business->fresh()->plan_price_pence, $business->fresh()->employee_limit]);

        $this->post('/app/settings/plan', ['plan' => 'starter'])->assertSessionHas('error', "You're already on Starter.");
        $this->post('/app/settings/plan', ['plan' => 'corporate'])->assertSessionHasErrors('plan');
    }

    public function test_an_invoiced_business_changes_plan_without_a_gateway(): void
    {
        $business = $this->business('starter');

        $this->actingAs($this->admin($business))->post('/app/settings/plan', ['plan' => 'standard'])->assertSessionHas('success');
        $this->assertSame('standard', $business->fresh()->plan);
        $this->assertSame([], $this->stripe->priceChanges);
    }

    public function test_a_corporate_business_changes_plan_through_us(): void
    {
        $business = $this->business('corporate');

        $this->actingAs($this->admin($business))->post('/app/settings/plan', ['plan' => 'standard'])
            ->assertSessionHas('error', 'You are on a Corporate package. Contact us to change it.');
        $this->assertSame('corporate', $business->fresh()->plan);
    }

    public function test_a_paypal_customer_approves_the_new_price_on_paypal_first(): void
    {
        Gateways::savePaypal(['mode' => 'sandbox', 'client_id' => 'AclientABCD', 'secret' => 'EsecretWXYZ', 'status' => 'connected']);
        $business = $this->business('starter', ['payment_provider' => 'paypal', 'paypal_subscription_id' => 'I-PP1', 'paypal_plan_id' => 'P-OLD']);
        $admin = $this->admin($business);

        $this->actingAs($admin)->post('/app/settings/plan', ['plan' => 'standard'])->assertRedirect('https://www.sandbox.paypal.com/webapps/billing/subscriptions/update?ba_token=BA-9');
        $this->assertSame('starter', $business->fresh()->plan); // not until PayPal confirms
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/I-PP1/revise') && $r['plan_id'] === 'P-PLAN35.00'
            && str_contains($r['application_context']['return_url'], '/app/settings/plan/paypal?plan=standard'));

        // Back from PayPal, but the subscription still shows the old plan: nothing changes.
        $this->get('/app/settings/plan/paypal?plan=standard&subscription_id=I-PP1')->assertRedirect('/app/settings')->assertSessionHas('error');
        $this->assertSame('starter', $business->fresh()->plan);

        $this->paypalPlan = 'P-PLAN35.00';
        $this->get('/app/settings/plan/paypal?plan=standard&subscription_id=I-PP1')->assertRedirect('/app/settings')->assertSessionHas('success');
        $this->assertSame(['standard', 3500, 10, 'P-PLAN35.00'], [$business->fresh()->plan, $business->fresh()->plan_price_pence, $business->fresh()->employee_limit, $business->fresh()->paypal_plan_id]);
    }

    // ---- Super admin: Set plan (Corporate) ----

    public function test_super_admin_sets_a_corporate_price_and_limit(): void
    {
        $business = $this->business('standard', ['payment_provider' => 'stripe', 'stripe_id' => 'cus_1'], employees: 9);
        $this->actingAs($this->superAdmin(), 'ops');

        $this->post("{$this->ops}/businesses/{$business->id}/plan", ['plan' => 'corporate'])->assertSessionHasErrors(['price' => 'Add the agreed monthly price.', 'limit' => 'Add the agreed employee limit.']);
        $this->post("{$this->ops}/businesses/{$business->id}/plan", ['plan' => 'corporate', 'price' => '60', 'limit' => '8'])
            ->assertSessionHasErrors(['limit' => "{$business->name} has 9 current employees; the plan must cover at least that many."]);

        $this->post("{$this->ops}/businesses/{$business->id}/plan", ['plan' => 'corporate', 'price' => '59.50', 'limit' => '25'])->assertSessionHas('success');
        $this->assertSame(['corporate', 5950, 25], [$business->fresh()->plan, $business->fresh()->plan_price_pence, $business->fresh()->employee_limit]);
        $this->assertSame([$business->id => 5950], $this->stripe->priceChanges);
        $this->assertTrue(AuditLog::where('action', 'ops.plan_set')->exists());

        $this->get($this->ops)->assertInertia(fn (Assert $p) => $p->where('businesses.0.planName', 'Corporate')->has('tiers', 2));
    }

    public function test_super_admin_cannot_change_a_paypal_price_without_the_customer(): void
    {
        $business = $this->business('starter', ['payment_provider' => 'paypal', 'paypal_subscription_id' => 'I-PP1']);
        $this->actingAs($this->superAdmin(), 'ops');

        $this->post("{$this->ops}/businesses/{$business->id}/plan", ['plan' => 'standard'])->assertSessionHas('error');
        $this->assertSame('starter', $business->fresh()->plan);
        Http::assertNothingSent();
    }
}
