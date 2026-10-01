<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Billing\Subscriptions;
use App\Models\Business;
use App\Support\Audit;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function index(): Response
    {
        $businesses = Business::query()
            ->withCount(['employees as employees_count' => fn ($q) => $q->current()])
            ->with(['admins' => fn ($q) => $q->orderBy('id')])
            ->orderByRaw("status = 'pending'")
            ->orderBy('name')
            ->get()
            ->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'status' => $b->status,
                'suspendedReason' => $b->suspended_reason,
                'graceEnds' => $b->grace_ends_on?->format('j M Y'),
                'admin' => $b->admins->first()?->only(['name', 'email']),
                'employees' => $b->employees_count,
                'limit' => $b->employee_limit,
                'plan' => $b->plan,
                'planName' => $b->planName(),
                'price' => $b->plan_price_pence / 100,
                'payment' => $b->payment_label ?? ($b->payment_provider === 'paypal' ? 'PayPal' : null),
                'next_payment' => $b->next_payment_on?->format('j M Y'),
                'joined' => $b->created_at->format('j M Y'),
            ]);

        $active = $businesses->where('status', Business::ACTIVE);

        return Inertia::render('Ops/Businesses', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'tiers' => collect(Pricing::current()['tiers'])->map(fn ($t, $key) => [
                'key' => $key, 'name' => Pricing::TIERS[$key], 'price' => Pricing::pounds($t['price_pence']), 'limit' => $t['employee_limit'],
            ])->values(),
            'businesses' => $businesses->values(),
            'stats' => [
                'active' => $active->count(),
                'suspended' => $businesses->where('status', Business::SUSPENDED)->count(),
                'revenue' => $active->sum('price'),
                'employees' => $businesses->sum('employees'),
            ],
        ]);
    }

    /**
     * Set a business's plan: Starter or Standard at today's price, or a Corporate package with the price
     * and employee limit agreed with them. Card subscriptions follow from the next payment.
     */
    public function setPlan(Request $request, Business $business, Subscriptions $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in([...array_keys(Pricing::TIERS), Pricing::CORPORATE])],
            'price' => ['required_if:plan,'.Pricing::CORPORATE, 'nullable', 'numeric', 'min:1', 'max:10000'],
            'limit' => ['required_if:plan,'.Pricing::CORPORATE, 'nullable', 'integer', 'min:1', 'max:1000'],
        ], ['price.required_if' => 'Add the agreed monthly price.', 'limit.required_if' => 'Add the agreed employee limit.']);

        [$pence, $limit] = $data['plan'] === Pricing::CORPORATE
            ? [(int) round($data['price'] * 100), (int) $data['limit']]
            : array_values(Pricing::tier($data['plan']));
        $employees = $business->currentEmployeeCount();
        if ($limit < $employees) {
            throw ValidationException::withMessages(['limit' => "{$business->name} has {$employees} current employees; the plan must cover at least that many."]);
        }

        try {
            $problem = $subscriptions->setPlan($business, $data['plan'], $pence, $limit);
        } catch (\Throwable $e) {
            report($e);
            $problem = "Stripe could not change the price for {$business->name}, so nothing was changed: {$e->getMessage()}";
        }
        if ($problem) {
            return back()->with('error', $problem);
        }

        return back()->with('success', "{$business->name} is now on ".Pricing::name($data['plan']).': £'.Pricing::pounds($pence)." a month for up to {$limit} employees. The new price applies from their next payment.");
    }

    /** Suspend or activate by hand. A manual suspension is never lifted by a payment arriving. */
    public function toggle(Business $business): RedirectResponse
    {
        abort_if($business->isPending(), 422, 'This sign-up has not paid yet.');
        $suspend = $business->isActive();
        $business->update($suspend
            ? ['status' => Business::SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => Business::SUSPENDED_MANUAL]
            : ['status' => Business::ACTIVE, 'suspended_at' => null, 'suspended_reason' => null, 'payment_failed_on' => null, 'grace_ends_on' => null]);
        Audit::log($suspend ? 'ops.business.suspended' : 'ops.business.activated', $business, businessId: $business->id);

        return back()->with('success', $suspend
            ? "{$business->name} suspended. Their admins and employees can no longer sign in; data is kept."
            : "{$business->name} activated. Sign-in restored.");
    }
}
