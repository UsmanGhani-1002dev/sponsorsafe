<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * The public plans, set by the super admin (Plans and pricing) and shown on the website straight away;
 * falls back to config/sponsorsafe.php:
 *   Starter  — £20 a month, up to 5 employees
 *   Standard — £35 a month, up to 10 employees
 *   Corporate — more than 10: a price agreed per business (stored on that business only)
 * Also the 1-to-1 training price and the grace period after a failed payment (days).
 * Existing subscribers keep the price and limit stored on their business until they are moved.
 */
class Pricing
{
    public const KEY = 'plan';

    public const STARTER = 'starter';
    public const STANDARD = 'standard';
    public const CORPORATE = 'corporate';

    /** Self-service plans, smallest first. */
    public const TIERS = [self::STARTER => 'Starter', self::STANDARD => 'Standard'];

    /**
     * @return array{tiers: array<string, array{price_pence: int, employee_limit: int}>, training_price_pence: int, grace_days: int}
     */
    public static function current(): array
    {
        $defaults = config('sponsorsafe.plan');
        $saved = (array) PlatformSetting::get(self::KEY, []);

        $tiers = [];
        foreach (array_keys(self::TIERS) as $key) {
            $tiers[$key] = [
                'price_pence' => (int) ($saved['tiers'][$key]['price_pence'] ?? $defaults['tiers'][$key]['price_pence']),
                'employee_limit' => (int) ($saved['tiers'][$key]['employee_limit'] ?? $defaults['tiers'][$key]['employee_limit']),
            ];
        }

        return [
            'tiers' => $tiers,
            'training_price_pence' => (int) ($saved['training_price_pence'] ?? $defaults['training_price_pence']),
            'grace_days' => (int) ($saved['grace_days'] ?? $defaults['grace_days']),
        ];
    }

    /** @return array{price_pence: int, employee_limit: int} */
    public static function tier(string $key): array
    {
        return self::current()['tiers'][$key];
    }

    /** The smallest plan that covers this many employees, or null when it needs the Corporate package. */
    public static function tierFor(int $employees): ?string
    {
        foreach (self::current()['tiers'] as $key => $tier) {
            if ($employees <= $tier['employee_limit']) {
                return $key;
            }
        }

        return null;
    }

    /** The largest self-service limit (10): above it is Corporate. */
    public static function largestLimit(): int
    {
        return max(array_column(self::current()['tiers'], 'employee_limit'));
    }

    /** Plan name for a business's `plan` column (null = the original single plan). */
    public static function name(?string $plan): string
    {
        return match ($plan) {
            null => 'Original plan',
            self::CORPORATE => 'Corporate',
            default => self::TIERS[$plan] ?? ucfirst($plan),
        };
    }

    /**
     * For pages: whole pounds show without pence ("20"), otherwise two decimals ("19.50").
     *
     * @return array{tiers: list<array{key: string, name: string, price: string, limit: int, from: int}>, training: string, corporateFrom: int}
     */
    public static function forDisplay(): array
    {
        $p = self::current();
        $tiers = [];
        $from = 1;
        foreach ($p['tiers'] as $key => $tier) {
            $tiers[] = ['key' => $key, 'name' => self::TIERS[$key], 'price' => self::pounds($tier['price_pence']), 'limit' => $tier['employee_limit'], 'from' => $from];
            $from = $tier['employee_limit'] + 1;
        }

        return ['tiers' => $tiers, 'training' => self::pounds($p['training_price_pence']), 'corporateFrom' => $from];
    }

    public static function pounds(int $pence): string
    {
        return $pence % 100 === 0 ? (string) intdiv($pence, 100) : number_format($pence / 100, 2);
    }
}
