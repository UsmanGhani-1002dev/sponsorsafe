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
use Illuminate\Database\Eloquent\Builder;
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
 * Price moves: existing subscribers are emailed 30 days ahead, then moved on the date.
 */
class Subscriptions
{
    /** Existing subscribers are told this many days before they move to a new price. */
    public const NOTICE_DAYS = 30;

    public function __construct(private PasswordLinks $links, private StripeGateway $stripe, private PayPalGateway $paypal) {}

    /**
     * Website sign-up, before payment: a pending business and its first admin (no usable password yet).
     * Signing up again with the same email before paying replaces the earlier, unpaid attempt.
     */
    public function start(array $data, string $provider): Business
    {
        $plan = Pricing::current();
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

    /** Subscribers still on an older price or employee limit than the current plan. */
    public function onOlderPlan(): Builder
    {
        $plan = Pricing::current();

        return Business::query()->whereIn('status', [Business::ACTIVE, Business::SUSPENDED])
            ->where(fn ($q) => $q->where('plan_price_pence', '!=', $plan['price_pence'])->orWhere('employee_limit', '!=', $plan['employee_limit']));
    }

    /**
     * Super admin "Move to the current plan": email every subscriber on an older plan now, and schedule the
     * change for 30 days' time (applied by `billing:check`). Returns how many were notified.
     */
    public function schedulePriceChange(): int
    {
        $plan = Pricing::current();
        $on = today()->addDays(self::NOTICE_DAYS);
        $businesses = $this->onOlderPlan()
            ->where(fn ($q) => $q->whereNull('price_change_on')
                ->orWhere('price_change_pence', '!=', $plan['price_pence'])->orWhere('price_change_limit', '!=', $plan['employee_limit']))
            ->get();

        foreach ($businesses as $business) {
            $business->update(['price_change_pence' => $plan['price_pence'], 'price_change_limit' => $plan['employee_limit'], 'price_change_on' => $on]);
            Notification::send($business->admins()->where('active', true)->get(), new PriceChangeNotice(
                $business->name, $business->plan_price_pence, $plan['price_pence'], $business->employee_limit, $plan['employee_limit'], $on,
            ));
            Audit::log('billing.price_change_scheduled', $business, [
                'from' => [$business->plan_price_pence, $business->employee_limit], 'to' => [$plan['price_pence'], $plan['employee_limit']], 'on' => $on->toDateString(),
            ], businessId: $business->id);
        }

        return $businesses->count();
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

            $from = [$business->plan_price_pence, $business->employee_limit];
            $business->update([
                'plan_price_pence' => $business->price_change_pence,
                'employee_limit' => $business->price_change_limit ?? $business->employee_limit,
                'price_change_pence' => null, 'price_change_limit' => null, 'price_change_on' => null,
            ]);
            Audit::log('billing.price_changed', $business, ['from' => $from, 'to' => [$business->plan_price_pence, $business->employee_limit]], businessId: $business->id);
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
