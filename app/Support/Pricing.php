<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * The public plan: monthly price, employee limit and 1-to-1 training price. Set by the super admin
 * (Plans and pricing) and shown on the website straight away; falls back to config/sponsorsafe.php.
 * Existing subscribers keep the price stored on their business until they are moved.
 * Also holds the grace period after a failed payment (days).
 */
class Pricing
{
    public const KEY = 'plan';

    public static function current(): array
    {
        $defaults = config('sponsorsafe.plan');
        $saved = (array) PlatformSetting::get(self::KEY, []);

        return [
            'price_pence' => (int) ($saved['price_pence'] ?? $defaults['price_pence']),
            'employee_limit' => (int) ($saved['employee_limit'] ?? $defaults['employee_limit']),
            'training_price_pence' => (int) ($saved['training_price_pence'] ?? $defaults['training_price_pence']),
            'grace_days' => (int) ($saved['grace_days'] ?? $defaults['grace_days']),
        ];
    }

    /** For pages: whole pounds show without pence ("20"), otherwise two decimals ("19.50"). */
    public static function forDisplay(): array
    {
        $p = self::current();

        return ['price' => self::pounds($p['price_pence']), 'limit' => $p['employee_limit'], 'training' => self::pounds($p['training_price_pence'])];
    }

    public static function pounds(int $pence): string
    {
        return $pence % 100 === 0 ? (string) intdiv($pence, 100) : number_format($pence / 100, 2);
    }
}
