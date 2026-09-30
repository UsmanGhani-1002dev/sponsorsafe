<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
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
                'price' => $b->plan_price_pence / 100,
                'payment' => $b->payment_label ?? ($b->payment_provider === 'paypal' ? 'PayPal' : null),
                'next_payment' => $b->next_payment_on?->format('j M Y'),
                'joined' => $b->created_at->format('j M Y'),
            ]);

        $active = $businesses->where('status', Business::ACTIVE);

        return Inertia::render('Ops/Businesses', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'businesses' => $businesses->values(),
            'stats' => [
                'active' => $active->count(),
                'suspended' => $businesses->where('status', Business::SUSPENDED)->count(),
                'revenue' => $active->sum('price'),
                'employees' => $businesses->sum('employees'),
            ],
        ]);
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
