<?php

namespace App\Billing;

use App\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Payment gateway keys entered by the super admin (Payment gateways). Stored in platform_settings,
 * every key encrypted at rest; pages only ever see the last 4 characters.
 * Until Stripe keys are saved, the STRIPE_* values in .env are used (handy for local testing).
 */
class Gateways
{
    public const STRIPE = 'gateway_stripe';
    public const PAYPAL = 'gateway_paypal';

    private const STRIPE_KEYS = ['publishable', 'secret', 'webhook_secret'];
    private const PAYPAL_KEYS = ['client_id', 'secret', 'webhook_id'];
    private const META = ['mode', 'status', 'checked_at', 'error'];

    /** Decrypted Stripe settings: mode (test|live), publishable, secret, webhook_secret, status, checked_at, error. */
    public static function stripe(): array
    {
        $saved = (array) PlatformSetting::get(self::STRIPE, []);

        return [
            'mode' => $saved['mode'] ?? 'test',
            'publishable' => self::decrypt($saved['publishable'] ?? null) ?? config('cashier.key'),
            'secret' => self::decrypt($saved['secret'] ?? null) ?? config('cashier.secret'),
            'webhook_secret' => self::decrypt($saved['webhook_secret'] ?? null) ?? config('cashier.webhook.secret'),
            'status' => $saved['status'] ?? null, // connected | failed | null (never tested)
            'checked_at' => $saved['checked_at'] ?? null,
            'error' => $saved['error'] ?? null,
        ];
    }

    /** Decrypted PayPal settings: mode (sandbox|live), client_id, secret, webhook_id, status, checked_at, error. */
    public static function paypal(): array
    {
        $saved = (array) PlatformSetting::get(self::PAYPAL, []);

        return [
            'mode' => $saved['mode'] ?? 'sandbox',
            'client_id' => self::decrypt($saved['client_id'] ?? null),
            'secret' => self::decrypt($saved['secret'] ?? null),
            'webhook_id' => self::decrypt($saved['webhook_id'] ?? null),
            'status' => $saved['status'] ?? null,
            'checked_at' => $saved['checked_at'] ?? null,
            'error' => $saved['error'] ?? null,
        ];
    }

    /** Blank key values keep what is saved; mode and test results are always replaced. */
    public static function saveStripe(array $values): void
    {
        self::save(self::STRIPE, self::STRIPE_KEYS, $values);
    }

    public static function savePaypal(array $values): void
    {
        self::save(self::PAYPAL, self::PAYPAL_KEYS, $values);
    }

    /** Card payments can be taken: a secret key is set and the last connection test passed (or keys come from .env). */
    public static function stripeReady(): bool
    {
        $s = self::stripe();

        return filled($s['secret']) && filled($s['publishable']) && $s['status'] !== 'failed';
    }

    /** PayPal can be offered: credentials saved and the last connection test passed. */
    public static function paypalReady(): bool
    {
        $p = self::paypal();

        return filled($p['client_id']) && filled($p['secret']) && $p['status'] === 'connected';
    }

    /** Point Laravel Cashier at the saved keys. Called by the `stripe` middleware and the billing command. */
    public static function applyStripe(): void
    {
        $s = self::stripe();
        config([
            'cashier.key' => $s['publishable'],
            'cashier.secret' => $s['secret'],
            'cashier.webhook.secret' => $s['webhook_secret'],
        ]);
    }

    /** "••••3kQ9" for a saved value, null when nothing is saved. */
    public static function mask(?string $value): ?string
    {
        return filled($value) ? '••••'.substr($value, -4) : null;
    }

    private static function save(string $setting, array $keys, array $values): void
    {
        $current = (array) PlatformSetting::get($setting, []);
        foreach ($keys as $key) {
            if (filled($values[$key] ?? null)) {
                $current[$key] = Crypt::encryptString(trim($values[$key]));
            }
        }
        foreach (self::META as $key) {
            if (array_key_exists($key, $values)) {
                $current[$key] = $values[$key];
            }
        }
        PlatformSetting::put($setting, $current);
    }

    private static function decrypt(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null; // APP_KEY changed: the key must be entered again
        }
    }
}
