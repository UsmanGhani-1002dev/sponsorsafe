<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Support\Audit;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Plans and pricing: the price, employee limit and training price shown on the website. */
class PricingController extends Controller
{
    public function show(): Response
    {
        $p = Pricing::current();

        return Inertia::render('Ops/Pricing', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'values' => [
                'price' => number_format($p['price_pence'] / 100, 2, '.', ''),
                'limit' => (string) $p['employee_limit'],
                'training' => number_format($p['training_price_pence'] / 100, 2, '.', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'max:1000'],
            'limit' => ['required', 'integer', 'min:1', 'max:500'],
            'training' => ['required', 'numeric', 'min:0', 'max:5000'],
        ], [], ['price' => 'monthly price', 'limit' => 'employee limit', 'training' => 'training price']);

        $before = Pricing::current();
        $after = [
            'price_pence' => (int) round($data['price'] * 100),
            'employee_limit' => (int) $data['limit'],
            'training_price_pence' => (int) round($data['training'] * 100),
        ];
        if ($before === $after) {
            return back()->with('success', 'Nothing changed.');
        }
        PlatformSetting::put(Pricing::KEY, $after);
        Audit::log('ops.pricing_changed', null, ['from' => $before, 'to' => $after]);

        return back()->with('success', 'Pricing saved. The website shows the new prices now. Existing subscribers keep their current price.');
    }
}
