<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Enquiry;
use App\Models\PlatformSetting;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Notifications\NewEnquiry;
use App\Support\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Stage 7a: public website, contact form → enquiries, and the super admin's pricing and enquiries. */
class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    private string $ops;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ops = '/'.config('sponsorsafe.ops_path');
        Notification::fake();
    }

    private function contact(array $overrides = [], int $secondsAgo = 30)
    {
        return $this->post('/contact', [
            'name' => 'Imran Ali', 'email' => 'imran@example.co.uk', 'phone' => '', 'topic' => 'Book a free demo',
            'message' => 'We have 6 staff, 3 sponsored. Can we see it working?',
            'form_token' => Crypt::encryptString((string) (now()->timestamp - $secondsAgo)), 'website' => '',
            ...$overrides,
        ]);
    }

    private function superAdmin(): SuperAdmin
    {
        return SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1', 'two_factor_confirmed_at' => now()]);
    }

    // ---- Website ----

    public function test_the_home_page_shows_the_live_plan(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/Home')
            ->where('plan', ['price' => '20', 'limit' => 15, 'training' => '49'])
            ->where('topic', 'General question')
            ->has('formToken')
            ->where('signedIn', null));

        PlatformSetting::put(Pricing::KEY, ['price_pence' => 2450, 'employee_limit' => 20, 'training_price_pence' => 5900]);
        $this->get('/?topic=1-to-1%20training')->assertInertia(fn (Assert $p) => $p
            ->where('plan', ['price' => '24.50', 'limit' => 20, 'training' => '59'])
            ->where('topic', '1-to-1 training'));
    }

    public function test_signed_in_people_can_still_see_the_website_with_a_link_back(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertInertia(fn (Assert $p) => $p->where('signedIn', route('app.dashboard')));
    }

    public function test_contact_form_creates_an_enquiry_and_emails_support(): void
    {
        $this->contact()->assertRedirect(route('home').'#contact')->assertSessionHas('contactSent', ['first' => 'Imran', 'email' => 'imran@example.co.uk']);

        $e = Enquiry::sole();
        $this->assertSame(['Imran Ali', 'Book a free demo', 'website', 'new'], [$e->name, $e->topic, $e->source, $e->status]);
        Notification::assertSentTo(new AnonymousNotifiable, NewEnquiry::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === config('sponsorsafe.support_email'));
    }

    public function test_contact_form_validation(): void
    {
        $this->contact(['name' => '', 'email' => 'nope', 'message' => '', 'topic' => 'Free money'])->assertSessionHasErrors(['name', 'email', 'message', 'topic']);
        $this->assertSame(0, Enquiry::count());
    }

    public function test_bots_are_turned_away(): void
    {
        $this->contact(['website' => 'http://spam.example'])->assertSessionHasErrors('message');   // honeypot filled in
        $this->contact(secondsAgo: 1)->assertSessionHasErrors('message');                          // too fast for a person
        $this->contact(['form_token' => 'forged'])->assertSessionHasErrors('message');
        $this->assertSame(0, Enquiry::count());
        Notification::assertNothingSent();
    }

    public function test_contact_form_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->contact()->assertRedirect();
        }
        $this->contact()->assertStatus(429);
        $this->assertSame(5, Enquiry::count());
    }

    public function test_other_public_pages(): void
    {
        $this->get('/signup')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/Signup')->where('plan.price', '20'));
        $this->get('/privacy')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/Legal')->where('page', 'privacy'));
        $this->get('/terms')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Website/Legal')->where('page', 'terms'));
    }

    // ---- Super admin: plans and pricing ----

    public function test_super_admin_changes_the_website_pricing_and_existing_subscribers_keep_theirs(): void
    {
        $business = Business::factory()->create(['plan_price_pence' => 2000, 'employee_limit' => 15]);
        $this->actingAs($this->superAdmin(), 'ops');

        $this->get("{$this->ops}/pricing")->assertInertia(fn (Assert $p) => $p->component('Ops/Pricing')->where('values', ['price' => '20.00', 'limit' => '15', 'training' => '49.00', 'grace' => '7']));
        $this->put("{$this->ops}/pricing", ['price' => '25', 'limit' => '20', 'training' => '59.50', 'grace' => '10'])->assertSessionHas('success');

        $this->assertSame(['price_pence' => 2500, 'employee_limit' => 20, 'training_price_pence' => 5950, 'grace_days' => 10], Pricing::current());
        $this->assertSame([2000, 15], [$business->fresh()->plan_price_pence, $business->fresh()->employee_limit]);
        $this->assertTrue(AuditLog::where('action', 'ops.pricing_changed')->exists());
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('plan', ['price' => '25', 'limit' => 20, 'training' => '59.50']));
    }

    public function test_pricing_is_validated_and_only_for_the_super_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get("{$this->ops}/pricing")->assertRedirect("{$this->ops}/login");
        $this->put("{$this->ops}/pricing", ['price' => '1', 'limit' => '1', 'training' => '1'])->assertRedirect("{$this->ops}/login");

        $this->actingAs($this->superAdmin(), 'ops')->put("{$this->ops}/pricing", ['price' => '0', 'limit' => 'x', 'training' => '-1', 'grace' => '60'])
            ->assertSessionHasErrors(['price', 'limit', 'training', 'grace']);
    }

    // ---- Super admin: enquiries ----

    public function test_super_admin_sees_enquiries_new_first_and_marks_them_handled(): void
    {
        $old = Enquiry::create(['name' => 'Tom Hughes', 'email' => 'tom@example.co.uk', 'topic' => 'General question', 'message' => 'Health and Care Worker visas?', 'status' => 'handled']);
        $this->contact();
        $new = Enquiry::where('name', 'Imran Ali')->sole();
        $owner = $this->superAdmin();
        $this->actingAs($owner, 'ops');

        $this->get("{$this->ops}/enquiries")->assertInertia(fn (Assert $p) => $p->component('Ops/Enquiries')
            ->where('enquiries.0.name', 'Imran Ali')
            ->where('enquiries.0.handled', null)
            ->where('enquiries.1.name', 'Tom Hughes')
            ->where('ops.newEnquiries', 1));

        $this->post("{$this->ops}/enquiries/{$new->id}/handled")->assertSessionHas('success');
        $this->assertSame([Enquiry::HANDLED, $owner->id], [$new->fresh()->status, $new->fresh()->handled_by]);
        $this->assertTrue(AuditLog::where('action', 'ops.enquiry_handled')->exists());
        $this->get("{$this->ops}/enquiries")->assertInertia(fn (Assert $p) => $p->where('ops.newEnquiries', 0));
        $this->assertNotNull($old->fresh());
    }
}
