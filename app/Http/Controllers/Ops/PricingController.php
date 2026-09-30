<?php

namespace App\Http\Controllers\Ops;

use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Support\Audit;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plans and pricing: the price, employee limit and training price shown on the website, the grace period,
 * and moving existing subscribers to the current plan (emailed 30 days ahead).
 */
class PricingController extends Controller
{
    public function show(Subscriptions $subscriptions): Response
    {
        $p = Pricing::current();
        $older = $subscriptions->onOlderPlan()
            ->with(['admins' => fn ($q) => $q->orderBy('id')])
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'plan_price_pence', 'employee_limit', 'payment_provider', 'payment_label', 'price_change_on', 'price_change_pence', 'price_change_limit']);
        $isScheduled = fn (Business $b) => $b->price_change_on && $b->price_change_pence === $p['price_pence'] && $b->price_change_limit === $p['employee_limit'];
        $scheduled = $older->filter($isScheduled);

        return Inertia::render('Ops/Pricing', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'values' => [
                'price' => number_format($p['price_pence'] / 100, 2, '.', ''),
                'limit' => (string) $p['employee_limit'],
                'training' => number_format($p['training_price_pence'] / 100, 2, '.', ''),
                'grace' => (string) $p['grace_days'],
            ],
            'subscribers' => [
                'older' => $older->count(),
                'waiting' => $older->count() - $scheduled->count(), // not yet told about the current plan
                'scheduled' => $scheduled->count(),
                'scheduledOn' => $scheduled->min('price_change_on')?->format('j M Y'),
                'plans' => $older->groupBy(fn (Business $b) => $b->plan_price_pence.'-'.$b->employee_limit)
                    ->map(fn ($group) => '£'.Pricing::pounds($group->first()->plan_price_pence).' · '.$group->first()->employee_limit.' employees ('.$group->count().')')
                    ->values(),
                'moveOn' => today()->addDays(Subscriptions::NOTICE_DAYS)->format('j M Y'),
                // Who is on an older plan, so the super admin can see who will be emailed.
                'list' => $older->map(fn (Business $b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'admin' => $b->admins->first()?->email,
                    'plan' => '£'.Pricing::pounds($b->plan_price_pence).' · '.$b->employee_limit.' employees',
                    'payment' => $b->payment_label ?? ($b->payment_provider === 'paypal' ? 'PayPal' : null),
                    'suspended' => $b->status === Business::SUSPENDED,
                    'movesOn' => $isScheduled($b) ? $b->price_change_on->format('j M Y') : null,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'max:1000'],
            'limit' => ['required', 'integer', 'min:1', 'max:500'],
            'training' => ['required', 'numeric', 'min:0', 'max:5000'],
            'grace' => ['required', 'integer', 'min:0', 'max:30'],
        ], [], ['price' => 'monthly price', 'limit' => 'employee limit', 'training' => 'training price', 'grace' => 'grace period']);

        $before = Pricing::current();
        $after = [
            'price_pence' => (int) round($data['price'] * 100),
            'employee_limit' => (int) $data['limit'],
            'training_price_pence' => (int) round($data['training'] * 100),
            'grace_days' => (int) $data['grace'],
        ];
        if ($before === $after) {
            return back()->with('success', 'Nothing changed.');
        }
        PlatformSetting::put(Pricing::KEY, $after);
        Audit::log('ops.pricing_changed', null, ['from' => $before, 'to' => $after]);

        return back()->with('success', 'Pricing saved. The website shows the new prices now. Existing subscribers keep their current price until you move them.');
    }

    /** Email existing subscribers now and move them to the current plan in 30 days. */
    public function move(Subscriptions $subscriptions): RedirectResponse
    {
        $count = $subscriptions->schedulePriceChange();
        Audit::log('ops.subscribers_moved', null, ['count' => $count, 'to' => Pricing::current()]);

        return back()->with('success', $count
            ? "Emailed {$count} subscriber(s). They move to the current plan on ".today()->addDays(Subscriptions::NOTICE_DAYS)->format('j M Y').'.'
            : 'Everyone is already on the current plan or has been told about it.');
    }
}
