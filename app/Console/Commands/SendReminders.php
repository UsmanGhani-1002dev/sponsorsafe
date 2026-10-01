<?php

namespace App\Console\Commands;

use App\Services\Reminders;
use Illuminate\Console\Command;

/** Daily: email each business a digest of expiries, follow-up checks and Home Office deadlines coming up. */
class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Email business admins their new compliance reminders (expiries, follow-up checks, deadlines)';

    public function handle(Reminders $reminders): int
    {
        [$businesses, $sent] = $reminders->send();
        $this->info("Sent {$sent} reminder(s) to {$businesses} business(es).");

        return self::SUCCESS;
    }
}
