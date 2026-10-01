<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\User;
use App\Notifications\AccessPaused;
use App\Notifications\PaymentFailed;
use App\Notifications\PriceChangeNotice;
use App\Notifications\WelcomeSubscriber;
use App\Services\PasswordLinks;
use App\Support\Audit;
use App\Support\DashboardCounts;
use App\Support\Pricing;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * The life of a subscription, whichever gateway takes the money:
 * sign-up (pending) → paid (active, set-password email) → payment failed (grace period, email)
 * → paid again (grace cleared) or grace over / cancelled (suspended, data kept, email).
 * A business the super admin suspended by hand is never reactivated by a payment.
 * Plans: Starter / Standard (self-service, Settings) or a Corporate price set by the super admin.
 * Price moves: existing subscribers are emailed 30 days ahead, then moved on the date.
 */
class Subscriptions
{
    /** Existing subscribers are told this many days before they move to a new price. */
    public const NOTICE_DAYS = 30;

    public function __construct(private PasswordLinks $links, private StripeGateway $stripe, private PayPalGateway $paypal) {}

    /**
     * Website sign-up, before payment: a pending business and its first admin (no usable password yet), on
     * the plan they chose ($data['employees'] is starter|standard). Signing up again with the same email
     * before paying replaces the earlier, unpaid attempt.
     */
    public function start(array $data, string $provider): Business
    {
        $plan = Pricing::tier($data['employees']);
        $email = mb_strtolower(trim($data['email']));

        return DB::transaction(function () use ($data, $provider, $plan, $email) {
            $admin = User::with('business')->where('email', $email)->first();
            $business = $admin?->business?->isPending() ? $admin->business : new Business;

            $business->fill([
                'name' => $data['business'],
                'licence_number' => $data['licence'],
                'phone' => $data['phone'] ?? null,
                'employees_band' => $data['employees'],
                'status' => Business::PENDING,
                'plan' => $data['employees'],
                'plan_price_pence' => $plan['price_pence'],
                'employee_limit' => $plan['employee_limit'],
                'payment_provider' => $provider,
            ])->save();

            $admin ??= new User(['role' => User::ROLE_ADMIN, 'password' => Str::random(64)]);
            $admin->fill(['business_id' => $business->id, 'name' => $data['name'], 'email' => $email, 'active' => true])->save();

            Audit::log('billing.signup_started', $business, ['provider' => $provider], $admin, $business->id);

            return $business;
        });
    }

    /**
     * Money received for the first time (or again after suspension for non-payment). Safe to call more
     * than once: the Checkout return page and the webhook can both report the same payment.
     */
    public function paid(Business $business, string $provider, ?string $label = null, ?CarbonInterface $next = null): void
    {
        $wasPending = $business->isPending();
        $reactivate = $business->status === Business::SUSPENDED && $business->suspended_reason !== Business::SUSPENDED_MANUAL;

        $business->fill(array_filter([
            'payment_provider' => $provider,
            'payment_label' => $label,
            'next_payment_on' => $next?->toDateString(),
        ]));
        $business->fill(['payment_failed_on' => null, 'grace_ends_on' => null]);
        if ($wasPending || $reactivate) {
            $business->fill(['status' => Business::ACTIVE, 'suspended_at' => null, 'suspended_reason' => null]);
        }
        $business->save();

        if ($wasPending) {
            $admin = $business->admins()->orderBy('id')->first();
            if ($admin) {
                $admin->forceFill(['invited_at' => now()])->save();
                $admin->notify(new WelcomeSubscriber($this->links->issueInvite($admin), $business->name));
            }
            Audit::log('billing.activated', $business, ['provider' => $provider], $admin, $business->id);
        } elseif ($reactivate) {
            Audit::log('billing.reactivated', $business, ['provider' => $provider], businessId: $business->id);
        }
        DashboardCounts::forget($business->id);
    }

    /** A renewal failed: start the grace period (once) and tell the admins how to fix it. */
    public function failed(Business $business): void
    {
        if (! $business->isActive() || $business->grace_ends_on) {
            return; // not live yet, already suspended, or grace already running (Stripe retries)
        }
        $business->update([
            'payment_failed_on' => today(),
            'grace_ends_on' => today()->addDays(Pricing::current()['grace_days']),
        ]);
        Notification::send($business->admins()->where('active', true)->get(), new PaymentFailed($business->name, $business->grace_ends_on));
        Audit::log('billing.payment_failed', $business, ['grace_ends_on' => $business->grace_ends_on->toDateString()], businessId: $business->id);
    }

    /** The subscription ended (cancelled by the customer, or Stripe gave up retrying). */
    public function cancelled(Business $business): void
    {
        $this->suspend($business, Business::SUSPENDED_CANCELLED);
    }

    // ---- Changing plan ----

    /**
     * The self-service plans for this business's Settings: price, limit, and whether it can switch now.
     * Corporate businesses change plan through us; a downgrade needs few enough current employees.
     *
     * @return list<array{key: string, name: string, price: string, limit: int, current: bool, allowed: bool, reason: ?string}>
     */
    public function planOptions(Business $business, int $employees): array
    {
        $options = [];
        foreach (Pricing::current()['tiers'] as $key => $tier) {
            $current = $business->plan === $key && $business->plan_price_pence === $tier['price_pence'] && $business->employee_limit === $tier['employee_limit'];
            $reason = match (true) {
                $current => null,
                $business->plan === Pricing::CORPORATE => 'You are on a Corporate package. Contact us to change it.',
                $employees > $tier['employee_limit'] => "You have {$employees} current employees; this plan covers up to {$tier['employee_limit']}.",
                default => null,
            };
            $options[] = [
                'key' => $key, 'name' => Pricing::TIERS[$key], 'price' => Pricing::pounds($tier['price_pence']), 'limit' => $tier['employee_limit'],
                'current' => $current, 'allowed' => ! $current && $reason === null, 'reason' => $reason,
            ];
        }

        return $options;
    }

    /**
     * Admin switches between Starter and Standard. The limit changes straight away; the new price applies
     * from the next payment (Stripe: swap without proration). PayPal asks the customer to approve the new
     * price first: this returns PayPal's approval URL, and confirmPaypalPlan() finishes on the way back.
     * A business without online billing (invoiced) just changes.
     */
    public function changePlan(Business $business, string $tier, string $returnUrl, string $cancelUrl): ?string
    {
        $plan = Pricing::tier($tier);

        if ($business->payment_provider === 'paypal' && filled($business->paypal_subscription_id)) {
            [$approve] = $this->paypal->revise($business->paypal_subscription_id, $plan['price_pence'], $returnUrl, $cancelUrl);

            return $approve;
        }
        if ($business->payment_provider === 'stripe' && filled($business->stripe_id)) {
            $this->stripe->changePrice($business, $plan['price_pence']);
        }
        $this->applyPlan($business, $tier, $plan['price_pence'], $plan['employee_limit'], 'billing.plan_changed');

        return null;
    }

    /** Back from approving a plan change on PayPal: applied only once PayPal shows the subscription on the new plan. */
    public function confirmPaypalPlan(Business $business, string $tier): bool
    {
        $plan = Pricing::tier($tier);
        $planId = $this->paypal->planId($plan['price_pence']);
        if ($this->paypal->subscription((string) $business->paypal_subscription_id)['plan_id'] !== $planId) {
            return false;
        }
        $business->update(['paypal_plan_id' => $planId]);
        $this->applyPlan($business, $tier, $plan['price_pence'], $plan['employee_limit'], 'billing.plan_changed');

        return true;
    }

    /**
     * Super admin sets a business's plan: a standard tier, or a Corporate package with an agreed price and
     * limit. Card subscriptions move to the new price from the next payment. PayPal needs the customer's own
     * approval for a new price, so a PayPal price change is refused (they can change tier in Settings).
     * Returns null when done, or the reason it could not be done.
     */
    public function setPlan(Business $business, string $plan, int $pence, int $limit): ?string
    {
        if ($pence !== $business->plan_price_pence && $business->payment_provider === 'paypal' && filled($business->paypal_subscription_id)) {
            return "{$business->name} pays by PayPal, and PayPal needs the customer to approve a new price. Ask them to change plan in their Settings, or to pay by card.";
        }
        if ($pence !== $business->plan_price_pence && $business->payment_provider === 'stripe' && filled($business->stripe_id)) {
            $this->stripe->changePrice($business, $pence);
        }
        $this->applyPlan($business, $plan, $pence, $limit, 'ops.plan_set');

        return null;
    }

    private function applyPlan(Business $business, string $plan, int $pence, int $limit, string $action): void
    {
        $from = [$business->plan, $business->plan_price_pence, $business->employee_limit];
        $business->update([
            'plan' => $plan, 'plan_price_pence' => $pence, 'employee_limit' => $limit,
            'price_change_pence' => null, 'price_change_limit' => null, 'price_change_plan' => null, 'price_change_on' => null,
        ]);
        Audit::log($action, $business, ['from' => $from, 'to' => [$plan, $pence, $limit]], businessId: $business->id);
        DashboardCounts::forget($business->id);
    }

    // ---- Moving existing subscribers to the current prices (30 days' notice) ----

    /**
     * Subscribers whose price or limit differs from today's plan for them, with where they would move:
     * Starter and Standard businesses to today's price for their tier; businesses on the original single
     * plan to the tier that fits their current employees. Original-plan businesses with more employees
     * than the largest tier get target null: they need a Corporate price (Businesses → Set plan).
     * Corporate businesses are never moved.
     *
     * @return Collection<int, array{business: Business, employees: int, target: ?array{plan: string, price_pence: int, employee_limit: int}}>
     */
    public function moveCandidates(): Collection
    {
        $tiers = Pricing::current()['tiers'];

        return Business::query()
            ->whereIn('status', [Business::ACTIVE, Business::SUSPENDED])
            ->where(fn ($q) => $q->whereNull('plan')->orWhere('plan', '!=', Pricing::CORPORATE))
            ->withCount(['employees as current_employees' => fn ($q) => $q->current()])
            ->with(['admins' => fn ($q) => $q->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(function (Business $b) use ($tiers) {
                $key = $b->plan ?? Pricing::tierFor($b->current_employees);
                $target = $key ? ['plan' => $key, ...$tiers[$key]] : null;

                return ['business' => $b, 'employees' => $b->current_employees, 'target' => $target];
            })
            ->filter(fn ($c) => $c['target'] === null || $c['business']->plan === null
                || $c['business']->plan_price_pence !== $c['target']['price_pence'] || $c['business']->employee_limit !== $c['target']['employee_limit'])
            ->values();
    }

    /** Already emailed about exactly this move. */
    public static function isScheduled(Business $business, ?array $target): bool
    {
        return $target !== null && $business->price_change_on !== null && $business->price_change_plan === $target['plan']
            && $business->price_change_pence === $target['price_pence'] && $business->price_change_limit === $target['employee_limit'];
    }

    /**
     * Super admin "Email N and move them": email every subscriber that can move and has not been told yet,
     * and schedule the change for 30 days' time (applied by `billing:check`). Returns how many were notified.
     */
    public function schedulePriceChange(): int
    {
        $on = today()->addDays(self::NOTICE_DAYS);
        $due = $this->moveCandidates()->filter(fn ($c) => $c['target'] !== null && ! self::isScheduled($c['business'], $c['target']));

        foreach ($due as ['business' => $business, 'target' => $target]) {
            $business->update(['price_change_plan' => $target['plan'], 'price_change_pence' => $target['price_pence'], 'price_change_limit' => $target['employee_limit'], 'price_change_on' => $on]);
            Notification::send($business->admins->where('active', true), new PriceChangeNotice(
                $business->name, $business->plan_price_pence, $target['price_pence'], $business->employee_limit, $target['employee_limit'], $on, Pricing::TIERS[$target['plan']],
            ));
            Audit::log('billing.price_change_scheduled', $business, [
                'from' => [$business->plan, $business->plan_price_pence, $business->employee_limit],
                'to' => [$target['plan'], $target['price_pence'], $target['employee_limit']], 'on' => $on->toDateString(),
            ], businessId: $business->id);
        }

        return $due->count();
    }

    /**
     * Daily (`billing:check`): suspend businesses whose grace period is over, remove sign-ups that never
     * paid, and apply price changes that are due. Returns [suspended, removed, moved].
     *
     * @return array{int, int, int}
     */
    public function daily(): array
    {
        $moved = $this->applyPriceChanges();

        $late = Business::query()->where('status', Business::ACTIVE)->whereDate('grace_ends_on', '<', today())->get();
        $late->each(fn (Business $b) => $this->suspend($b, Business::SUSPENDED_PAYMENT));

        $abandoned = Business::query()->where('status', Business::PENDING)
            ->where('updated_at', '<', now()->subDays(config('sponsorsafe.abandoned_signup_days')))->get();
        $abandoned->each(function (Business $b) {
            Audit::log('billing.signup_abandoned', $b, businessId: null);
            $b->delete(); // cascades to the admin login; nothing else exists before payment
        });

        return [$late->count(), $abandoned->count(), $moved];
    }

    /**
     * Move businesses whose notice period is over to their new price in the gateway, then on our side.
     * PayPal plans are shared, so each plan's price is changed once. A gateway error leaves the change
     * scheduled, and it is retried the next day.
     */
    private function applyPriceChanges(): int
    {
        $due = Business::query()->whereNotNull('price_change_on')->whereDate('price_change_on', '<=', today())->orderBy('id')->get();
        $paypalPlans = [];
        $moved = 0;

        foreach ($due as $business) {
            try {
                if ($business->payment_provider === 'stripe' && filled($business->stripe_id)) {
                    $this->stripe->changePrice($business, $business->price_change_pence);
                } elseif ($business->payment_provider === 'paypal' && filled($business->paypal_plan_id)
                    && ! in_array($business->paypal_plan_id, $paypalPlans, true)) {
                    $this->paypal->changePlanPrice($business->paypal_plan_id, $business->price_change_pence);
                    $paypalPlans[] = $business->paypal_plan_id;
                }
            } catch (Throwable $e) {
                Log::warning('Price change could not be applied', ['business' => $business->id, 'error' => $e->getMessage()]);

                continue;
            }

            $from = [$business->plan, $business->plan_price_pence, $business->employee_limit];
            $business->update([
                'plan' => $business->price_change_plan ?? $business->plan,
                'plan_price_pence' => $business->price_change_pence,
                'employee_limit' => $business->price_change_limit ?? $business->employee_limit,
                'price_change_pence' => null, 'price_change_limit' => null, 'price_change_plan' => null, 'price_change_on' => null,
            ]);
            Audit::log('billing.price_changed', $business, ['from' => $from, 'to' => [$business->plan, $business->plan_price_pence, $business->employee_limit]], businessId: $business->id);
            $moved++;
        }

        return $moved;
    }

    private function suspend(Business $business, string $reason): void
    {
        if ($business->status !== Business::ACTIVE) {
            return;
        }
        $business->update(['status' => Business::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => $reason, 'grace_ends_on' => null]);
        Notification::send($business->admins()->where('active', true)->get(), new AccessPaused($business->name, $reason));
        Audit::log('billing.suspended', $business, ['reason' => $reason], businessId: $business->id);
        DashboardCounts::forget($business->id);
    }
}
