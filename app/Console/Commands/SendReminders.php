<?php

namespace App\Console\Commands;

use App\Services\EmployeeReminders;
use App\Services\Reminders;
use Illuminate\Console\Command;

/** Daily: email each business a digest of what's coming up, and each employee what they need to do themselves. */
class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Email business admins their new compliance reminders, and employees their own (new visa, documents HR asked for)';

    public function handle(Reminders $reminders, EmployeeReminders $employeeReminders): int
    {
        [$businesses, $sent] = $reminders->send();
        $employees = $employeeReminders->send();
        $this->info("Sent {$sent} reminder(s) to {$businesses} business(es), and reminders to {$employees} employee(s).");

        return self::SUCCESS;
    }
}
