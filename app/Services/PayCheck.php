<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Employee;

/**
 * Is a sponsored worker paid at least the minimum for their visa? The minimum is the worker's own `min_salary` when
 * set, otherwise the business's general threshold (`sponsored_min_salary`, Settings → Compliance rules). A warning,
 * never a block: the business decides, but it is told before it saves and the Compliance check shows it until fixed.
 */
class PayCheck
{
    public static function minimum(Business $business, mixed $workerMinimum = null): float
    {
        return $workerMinimum !== null && $workerMinimum !== '' ? (float) $workerMinimum : (float) $business->rule('sponsored_min_salary');
    }

    public static function minimumFor(Employee $e): float
    {
        return self::minimum($e->business, $e->min_salary);
    }

    /** The warning for a salary below the minimum, or null when it is fine (or the worker is not sponsored). */
    public static function warning(Business $business, bool $sponsored, mixed $salary, mixed $workerMinimum = null): ?string
    {
        if (! $sponsored || $salary === null || $salary === '' || ! is_numeric($salary)) {
            return null;
        }
        $min = self::minimum($business, $workerMinimum);
        if ((float) $salary >= $min) {
            return null;
        }

        return self::money((float) $salary).' a year is below the minimum salary of '.self::money($min).' for this worker\'s visa. Paying a sponsored worker '
            .'less than the minimum can lead to your sponsor licence being suspended. Check with an immigration adviser, and report any pay cut on the '
            .'Sponsor Management System.';
    }

    public static function warningFor(Employee $e): ?string
    {
        return self::warning($e->business, $e->isSponsored(), $e->salary, $e->min_salary);
    }

    /** "£41,700" or "£41,700.50". */
    public static function money(float $amount): string
    {
        return '£'.number_format($amount, fmod($amount, 1.0) === 0.0 ? 0 : 2);
    }
}
