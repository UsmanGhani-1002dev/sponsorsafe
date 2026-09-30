<?php

namespace App\Billing;

use App\Models\Business;
use App\Models\User;
use App\Notifications\AccessPaused;
use App\Notifications\PaymentFailed;
use App\Notifications\WelcomeSubscriber;
use App\Services\PasswordLinks;
use App\Support\Audit;
use App\Support\DashboardCounts;
use App\Support\Pricing;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * The life of a subscription, whichever gateway takes the money:
 * sign-up (pending) → paid (active, set-password email) → payment failed (grace period, email)
 * → paid again (grace cleared) or grace over / cancelled (suspended, data kept, email).
 * A business the super admin suspended by hand is never reactivated by a payment.
 */
class Subscriptions
{
    public function __construct(private PasswordLinks $links) {}

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

    /**
     * Daily (`billing:check`): suspend businesses whose grace period is over, and remove sign-ups
     * that never paid. Returns [suspended, removed].
     *
     * @return array{int, int}
     */
    public function daily(): array
    {
        $late = Business::query()->where('status', Business::ACTIVE)->whereDate('grace_ends_on', '<', today())->get();
        $late->each(fn (Business $b) => $this->suspend($b, Business::SUSPENDED_PAYMENT));

        $abandoned = Business::query()->where('status', Business::PENDING)
            ->where('updated_at', '<', now()->subDays(config('sponsorsafe.abandoned_signup_days')))->get();
        $abandoned->each(function (Business $b) {
            Audit::log('billing.signup_abandoned', $b, businessId: null);
            $b->delete(); // cascades to the admin login; nothing else exists before payment
        });

        return [$late->count(), $abandoned->count()];
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
