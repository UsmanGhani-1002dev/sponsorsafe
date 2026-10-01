<?php

namespace App\Services\ClockIns;

use App\Models\Business;
use Carbon\CarbonInterface;

/**
 * Where clock-in data comes from (compliance-rules §11). The live integration with a clock-in system is a
 * later piece of work: implement this interface and bind it in AppServiceProvider. Until then the nightly
 * check uses NoClockInSource (nothing), and businesses upload their clock-in system's CSV export instead.
 */
interface ClockInSource
{
    /**
     * Clock-ins for one day.
     *
     * @return iterable<array{email: string, time: ?string}>  employee email and first clock-in time ("08:57")
     */
    public function fetch(Business $business, CarbonInterface $date): iterable;
}
