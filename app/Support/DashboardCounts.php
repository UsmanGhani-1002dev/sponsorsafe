<?php

namespace App\Support;

use App\Models\Business;
use App\Services\WorkingDays;
use Illuminate\Support\Facades\Cache;

/** Dashboard stat tiles, cached briefly per business and cleared whenever tasks or employees change. */
class DashboardCounts
{
    private const TTL = 300;

    public static function for(Business $business): array
    {
        return Cache::remember(self::key($business->id), self::TTL, function () use ($business) {
            $wd = WorkingDays::fromDatabase();
            $today = today()->format('Y-m-d');
            $deadlines = $business->reportTasks()->pending()->pluck('deadline');

            return [
                'pending' => $deadlines->count(),
                // Due within 5 working days, including overdue.
                'urgent' => $deadlines->filter(fn ($d) => $wd->until($today, $d->format('Y-m-d')) <= 5)->count(),
                'expiring' => $business->employees()->current()->whereNotNull('visa_expiry')->where('visa_expiry', '<=', today()->addDays(90))->count(),
                'employees' => $business->employees()->current()->count(),
                'day' => $today,
            ];
        });
    }

    public static function forget(int $businessId): void
    {
        Cache::forget(self::key($businessId));
    }

    private static function key(int $businessId): string
    {
        // The date is part of the key so "due within 5 working days" rolls over at midnight.
        return "dashboard:{$businessId}:".today()->format('Y-m-d');
    }
}
