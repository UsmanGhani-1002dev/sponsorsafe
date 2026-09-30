<?php

namespace App\Console\Commands;

use App\Billing\Gateways;
use App\Billing\Subscriptions;
use Illuminate\Console\Command;

/** Daily: suspend businesses past their payment grace period, remove unpaid sign-ups, apply due price changes. */
class BillingCheck extends Command
{
    protected $signature = 'billing:check';

    protected $description = 'Suspend businesses past their payment grace period, remove abandoned sign-ups and apply due price changes';

    public function handle(): int
    {
        Gateways::applyStripe(); // before Subscriptions (and Cashier) are built
        [$suspended, $removed, $moved] = app(Subscriptions::class)->daily();
        $this->info("Suspended {$suspended} business(es) for non-payment; removed {$removed} unpaid sign-up(s); moved {$moved} to a new price.");

        return self::SUCCESS;
    }
}
