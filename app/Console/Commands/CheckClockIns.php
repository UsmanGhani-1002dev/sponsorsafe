<?php

namespace App\Console\Commands;

use App\Services\UnexplainedAbsences;
use Illuminate\Console\Command;

/** Each evening: today's scheduled working days with no clock-in and no absence become unexplained-absence alerts. */
class CheckClockIns extends Command
{
    protected $signature = 'absences:check-clock-ins';

    protected $description = 'Flag scheduled working days with no clock-in and no absence (businesses with the clock-in check on)';

    public function handle(UnexplainedAbsences $unexplained): int
    {
        [$businesses, $alerts] = $unexplained->nightly();
        $this->info("Checked {$businesses} business(es); {$alerts} new unexplained absence(s).");

        return self::SUCCESS;
    }
}
