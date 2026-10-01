<?php

namespace App\Services\ClockIns;

use App\Models\Business;
use Carbon\CarbonInterface;

/** Placeholder until a clock-in system is connected: returns nothing (the CSV upload is used instead). */
class NoClockInSource implements ClockInSource
{
    public function fetch(Business $business, CarbonInterface $date): iterable
    {
        return [];
    }
}
