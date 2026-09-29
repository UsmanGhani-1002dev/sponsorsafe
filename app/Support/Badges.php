<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\CarbonInterface;

/** Status badges shared by several screens. Tones match the UI kit: red, amber, green, grey, blue. */
class Badges
{
    /** Visa / permission expiry: expired (red), within 90 days (amber), later (blue), none (grey). */
    public static function expiry(?CarbonInterface $date): array
    {
        if (! $date) {
            return ['text' => 'No time limit', 'tone' => 'grey'];
        }
        $days = (int) today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => ['text' => 'Expired '.Employee::formatDate($date), 'tone' => 'red'],
            $days <= 90 => ['text' => Employee::formatDate($date).' · '.$days.' days', 'tone' => 'amber'],
            default => ['text' => Employee::formatDate($date), 'tone' => 'blue'],
        };
    }

    /** Only warn when a date is close: null when more than 90 days away. */
    public static function expiryWarning(?CarbonInterface $date): ?array
    {
        if (! $date) {
            return null;
        }
        $days = (int) today()->diffInDays($date, false);

        return match (true) {
            $days < 0 => ['text' => 'Expired', 'tone' => 'red'],
            $days <= 90 => ['text' => $days.' days left', 'tone' => 'amber'],
            default => null,
        };
    }
}
