<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function index(): Response
    {
        $businesses = Business::query()
            ->withCount(['users as employees_count' => fn ($q) => $q->where('role', User::ROLE_EMPLOYEE)->where('active', true)])
            ->with(['admins' => fn ($q) => $q->orderBy('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (Business $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'status' => $b->status,
                'admin' => $b->admins->first()?->only(['name', 'email']),
                'employees' => $b->employees_count,
                'limit' => $b->employee_limit,
                'price' => $b->plan_price_pence / 100,
                'payment' => $b->payment_label,
                'next_payment' => $b->next_payment_on?->format('j M Y'),
                'joined' => $b->created_at->format('j M Y'),
            ]);

        $active = $businesses->where('status', 'active');

        return Inertia::render('Ops/Businesses', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'businesses' => $businesses->values(),
            'stats' => [
                'active' => $active->count(),
                'suspended' => $businesses->count() - $active->count(),
                'revenue' => $active->sum('price'),
                'employees' => $businesses->sum('employees'),
            ],
        ]);
    }

    public function toggle(Business $business): RedirectResponse
    {
        $suspend = $business->isActive();
        $business->update([
            'status' => $suspend ? 'suspended' : 'active',
            'suspended_at' => $suspend ? now() : null,
        ]);
        Audit::log($suspend ? 'ops.business.suspended' : 'ops.business.activated', $business, businessId: $business->id);

        return back()->with('success', $suspend
            ? "{$business->name} suspended. Their admins and employees can no longer sign in; data is kept."
            : "{$business->name} activated. Sign-in restored.");
    }
}
