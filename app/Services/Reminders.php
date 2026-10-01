<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Employee;
use App\Models\Reminder;
use App\Models\UnexplainedAbsence;
use App\Notifications\ComplianceDigest;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Alerts and reminders (compliance-rules §12), shown on the dashboard and emailed once per stage:
 *   visa / permission expiry   — at each lead time in `expiry_alert_days` (90, 60, 30), and when expired
 *   follow-up right-to-work check — `follow_up_alert_days` (30) before, and when overdue
 *   passport expiry            — `passport_alert_days` (90) before, and when expired
 *   Home Office task deadline  — `task_alert_working_days` (5) working days before, and when overdue
 *   leavers' records due for deletion — once a month while any are due (the Stage 6 review)
 *   unexplained absence (§11) — the morning after it is found
 * A stage includes the date it is about ("60@2026-12-10"), so a new visa expiry starts the reminders again.
 */
class Reminders
{
    public const VISA = 'visa_expiry';
    public const FOLLOW_UP = 'follow_up_check';
    public const PASSPORT = 'passport_expiry';
    public const TASK = 'task_deadline';
    public const RETENTION = 'retention_due';
    public const UNEXPLAINED = 'unexplained_absence';

    /**
     * Everything that needs attention now, soonest first.
     *
     * @return Collection<int, array{type: string, subject: string, stage: string, date: CarbonInterface, text: string, tone: string, href: string}>
     */
    public function current(Business $business): Collection
    {
        $items = collect();
        $today = today();
        $visaStages = collect((array) $business->rule('expiry_alert_days'))->map(fn ($d) => (int) $d)->sortDesc()->values();
        $followUp = (int) $business->rule('follow_up_alert_days');
        $passport = (int) $business->rule('passport_alert_days');

        $employees = $business->employees()->current()
            ->where(fn ($q) => $q->whereNotNull('visa_expiry')->orWhereNotNull('follow_up_check_due')->orWhereNotNull('passport_expiry'))
            ->get(['id', 'full_name', 'visa_expiry', 'follow_up_check_due', 'passport_expiry']);

        foreach ($employees as $e) {
            $subject = "employee:{$e->id}";
            $href = "/app/employees/{$e->id}";

            if ($e->visa_expiry) {
                $days = (int) $today->diffInDays($e->visa_expiry, false);
                $reached = $visaStages->filter(fn ($s) => $days <= $s);
                if ($days < 0) {
                    $items->push($this->item(self::VISA, $subject, 'expired', $e->visa_expiry, "{$e->full_name}'s visa or permission expired on {$this->date($e->visa_expiry)}", 'red', $href));
                } elseif ($reached->isNotEmpty()) {
                    $items->push($this->item(self::VISA, $subject, (string) $reached->min(), $e->visa_expiry,
                        "{$e->full_name}'s visa or permission ends on {$this->date($e->visa_expiry)} ({$this->days($days)})", $days <= 30 ? 'red' : 'amber', $href));
                }
            }

            if ($e->follow_up_check_due) {
                $days = (int) $today->diffInDays($e->follow_up_check_due, false);
                if ($days < 0) {
                    $items->push($this->item(self::FOLLOW_UP, $subject, 'overdue', $e->follow_up_check_due, "Follow-up right-to-work check for {$e->full_name} was due on {$this->date($e->follow_up_check_due)}", 'red', $href));
                } elseif ($days <= $followUp) {
                    $items->push($this->item(self::FOLLOW_UP, $subject, (string) $followUp, $e->follow_up_check_due,
                        "Follow-up right-to-work check for {$e->full_name} due on {$this->date($e->follow_up_check_due)} ({$this->days($days)})", 'amber', $href));
                }
            }

            if ($e->passport_expiry) {
                $days = (int) $today->diffInDays($e->passport_expiry, false);
                if ($days < 0) {
                    $items->push($this->item(self::PASSPORT, $subject, 'expired', $e->passport_expiry, "{$e->full_name}'s passport expired on {$this->date($e->passport_expiry)}", 'red', $href));
                } elseif ($days <= $passport) {
                    $items->push($this->item(self::PASSPORT, $subject, (string) $passport, $e->passport_expiry,
                        "{$e->full_name}'s passport expires on {$this->date($e->passport_expiry)} ({$this->days($days)})", 'amber', $href));
                }
            }
        }

        $soon = (int) $business->rule('task_alert_working_days');
        $wd = WorkingDays::fromDatabase();
        $tasks = $business->reportTasks()->pending()->with('employee:id,full_name')->orderBy('deadline')->get();
        foreach ($tasks as $t) {
            $left = $wd->until($today->format('Y-m-d'), $t->deadline->format('Y-m-d'));
            $who = $t->employee ? " ({$t->employee->full_name})" : '';
            if ($left < 0) {
                $items->push($this->item(self::TASK, "report_task:{$t->id}", 'overdue', $t->deadline, "Overdue Home Office report: {$t->event}{$who}, deadline was {$this->date($t->deadline)}", 'red', '/app/reports'));
            } elseif ($left <= $soon) {
                $items->push($this->item(self::TASK, "report_task:{$t->id}", 'soon', $t->deadline,
                    "Home Office report due ".($left === 0 ? 'today' : "on {$this->date($t->deadline)}").": {$t->event}{$who}", $left <= 1 ? 'red' : 'amber', '/app/reports'));
            }
        }

        // §11/§12: an unexplained absence is in the next morning's email (it turns red on the dashboard later).
        foreach (UnexplainedAbsence::open()->where('business_id', $business->id)->with('employee:id,full_name')->orderBy('date')->get() as $u) {
            $items->push($this->item(self::UNEXPLAINED, "employee:{$u->employee_id}", 'open', $u->date,
                "{$u->employee->full_name} had no clock-in and no absence recorded on {$this->date($u->date)}: please classify it", 'amber', '/app'));
        }

        $due = Retention::due($business)->count();
        if ($due > 0) {
            $items->push([
                'type' => self::RETENTION, 'subject' => 'business', 'stage' => $today->format('Y-m'), 'date' => $today,
                'text' => $due === 1 ? "1 leaver's record is due for deletion: please review it" : "{$due} leavers' records are due for deletion: please review them",
                'tone' => 'grey', 'href' => '/app/retention',
            ]);
        }

        return $items->sortBy(fn ($i) => $i['date']->format('Y-m-d'))->values();
    }

    /** The current items not emailed yet. */
    public function unsent(Business $business): Collection
    {
        $sent = Reminder::where('business_id', $business->id)->get(['type', 'subject', 'stage'])
            ->map(fn (Reminder $r) => "{$r->type}|{$r->subject}|{$r->stage}")->flip();

        return $this->current($business)->reject(fn ($i) => $sent->has("{$i['type']}|{$i['subject']}|{$i['stage']}"))->values();
    }

    /**
     * Daily (`reminders:send`): one digest email per active business with anything new, to its admins.
     * Returns [businesses emailed, reminders sent].
     *
     * @return array{int, int}
     */
    public function send(): array
    {
        [$businesses, $reminders] = [0, 0];

        Business::where('status', Business::ACTIVE)->orderBy('id')->each(function (Business $business) use (&$businesses, &$reminders) {
            $items = $this->unsent($business);
            $admins = $business->admins()->where('active', true)->get();
            if ($items->isEmpty() || $admins->isEmpty()) {
                return;
            }
            Notification::send($admins, new ComplianceDigest($business->name, $items->all()));
            Reminder::insert($items->map(fn ($i) => [
                'business_id' => $business->id, 'type' => $i['type'], 'subject' => $i['subject'], 'stage' => $i['stage'], 'sent_at' => now(),
            ])->all());
            $businesses++;
            $reminders += $items->count();
        });

        return [$businesses, $reminders];
    }

    private function item(string $type, string $subject, string $stage, CarbonInterface $date, string $text, string $tone, string $href): array
    {
        return ['type' => $type, 'subject' => $subject, 'stage' => $stage.'@'.$date->format('Y-m-d'), 'date' => $date, 'text' => $text, 'tone' => $tone, 'href' => $href];
    }

    private function date(CarbonInterface $date): string
    {
        return Employee::formatDate($date);
    }

    private function days(int $n): string
    {
        return $n === 0 ? 'today' : ($n === 1 ? '1 day' : "{$n} days");
    }
}
