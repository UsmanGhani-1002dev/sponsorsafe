<?php

namespace App\Console\Commands;

use App\Billing\Subscriptions;
use Illuminate\Console\Command;

/** Daily: suspend businesses whose grace period after a failed payment is over; remove unpaid sign-ups. */
class BillingCheck extends Command
{
    protected $signature = 'billing:check';

    protected $description = 'Suspend businesses past their payment grace period and remove abandoned sign-ups';

    public function handle(Subscriptions $subscriptions): int
    {
        [$suspended, $removed] = $subscriptions->daily();
        $this->info("Suspended {$suspended} business(es) for non-payment; removed {$removed} unpaid sign-up(s).");

        return self::SUCCESS;
    }
}
