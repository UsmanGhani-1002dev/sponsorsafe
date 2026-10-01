<?php

namespace App\Http\Controllers\Ops;

use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\Audit;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plans and pricing: the Starter and Standard plans shown on the website, the training price, the grace
 * period, and moving existing subscribers to today's prices (emailed 30 days ahead).
 */
class PricingController extends Controller
{
    public function show(Subscriptions $subscriptions): Response
    {
        $p = Pricing::current();
        $candidates = $subscriptions->moveCandidates();
        $label = fn (int $pence, int $limit) => '£'.Pricing::pounds($pence).' · '.$limit.' employees';

        return Inertia::render('Ops/Pricing', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'tierNames' => Pricing::TIERS,
            'values' => [
                'tiers' => collect($p['tiers'])->map(fn ($t) => [
                    'price' => number_format($t['price_pence'] / 100, 2, '.', ''),
                    'limit' => (string) $t['employee_limit'],
                ])->all(),
                'training' => number_format($p['training_price_pence'] / 100, 2, '.', ''),
                'grace' => (string) $p['grace_days'],
            ],
            'subscribers' => [
                'older' => $candidates->count(),
                'waiting' => $candidates->filter(fn ($c) => $c['target'] && ! Subscriptions::isScheduled($c['business'], $c['target']))->count(),
                'scheduled' => $candidates->filter(fn ($c) => Subscriptions::isScheduled($c['business'], $c['target']))->count(),
                'corporate' => $candidates->whereNull('target')->count(),
                'moveOn' => today()->addDays(Subscriptions::NOTICE_DAYS)->format('j M Y'),
                // Who is on an older plan, where they would move, and whether they have been told.
                'list' => $candidates->map(fn ($c) => [
                    'id' => $c['business']->id,
                    'name' => $c['business']->name,
                    'admin' => $c['business']->admins->first()?->email,
                    'employees' => $c['employees'],
                    'now' => $c['business']->planName().' · '.$label($c['business']->plan_price_pence, $c['business']->employee_limit),
                    'moveTo' => $c['target'] ? Pricing::TIERS[$c['target']['plan']].' · '.$label($c['target']['price_pence'], $c['target']['employee_limit']) : null,
                    'payment' => $c['business']->payment_label ?? ($c['business']->payment_provider === 'paypal' ? 'PayPal' : null),
                    'suspended' => $c['business']->status === 'suspended',
                    'movesOn' => Subscriptions::isScheduled($c['business'], $c['target']) ? $c['business']->price_change_on->format('j M Y') : null,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'training' => ['required', 'numeric', 'min:0', 'max:5000'],
            'grace' => ['required', 'integer', 'min:0', 'max:30'],
        ];
        $names = ['training' => 'training price', 'grace' => 'grace period'];
        foreach (Pricing::TIERS as $key => $name) {
            $rules["tiers.$key.price"] = ['required', 'numeric', 'min:1', 'max:1000'];
            $rules["tiers.$key.limit"] = ['required', 'integer', 'min:1', 'max:500'];
            $names["tiers.$key.price"] = "$name price";
            $names["tiers.$key.limit"] = "$name employee limit";
        }
        $data = $request->validate($rules, [], $names);

        $tiers = [];
        foreach (array_keys(Pricing::TIERS) as $key) {
            $tiers[$key] = ['price_pence' => (int) round($data['tiers'][$key]['price'] * 100), 'employee_limit' => (int) $data['tiers'][$key]['limit']];
        }
        // Bigger plans must cover more people and cost more, or "the smallest plan that fits" breaks.
        $previous = null;
        foreach ($tiers as $key => $tier) {
            if ($previous && $tier['employee_limit'] <= $previous['employee_limit']) {
                throw ValidationException::withMessages(["tiers.$key.limit" => Pricing::TIERS[$key].' must cover more employees than the plan before it.']);
            }
            $previous = $tier;
        }

        $before = Pricing::current();
        $after = ['tiers' => $tiers, 'training_price_pence' => (int) round($data['training'] * 100), 'grace_days' => (int) $data['grace']];
        if ($before === $after) {
            return back()->with('success', 'Nothing changed.');
        }
        PlatformSetting::put(Pricing::KEY, $after);
        Audit::log('ops.pricing_changed', null, ['from' => $before, 'to' => $after]);

        return back()->with('success', 'Pricing saved. The website shows the new prices now. Existing subscribers keep their current price until you move them.');
    }

    /** Email existing subscribers now and move them to today's price for their plan in 30 days. */
    public function move(Subscriptions $subscriptions): RedirectResponse
    {
        $count = $subscriptions->schedulePriceChange();
        Audit::log('ops.subscribers_moved', null, ['count' => $count, 'to' => Pricing::current()['tiers']]);

        return back()->with('success', $count
            ? "Emailed {$count} subscriber(s). They move on ".today()->addDays(Subscriptions::NOTICE_DAYS)->format('j M Y').'.'
            : 'Everyone who can move has already been told.');
    }
}
