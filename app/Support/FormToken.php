<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Bot protection for public forms: the page carries a signed start time, and a form sent back in
 * under 3 seconds (or over a day later, or tampered with) is refused. Used with a honeypot field.
 */
class FormToken
{
    private const MIN_SECONDS = 3;

    public static function issue(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public static function human(?string $token): bool
    {
        try {
            $elapsed = now()->timestamp - (int) Crypt::decryptString((string) $token);
        } catch (DecryptException) {
            return false;
        }

        return $elapsed >= self::MIN_SECONDS && $elapsed <= 86400;
    }
}
