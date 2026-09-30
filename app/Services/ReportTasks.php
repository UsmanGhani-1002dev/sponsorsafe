<?php

namespace App\Services;

use App\Enums\ChangeType;
use App\Models\Absence;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\ReportTask;
use App\Models\User;
use App\Models\WorkSite;
use App\Support\Audit;
use App\Support\DashboardCounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Home Office report tasks (compliance-rules §4). Every task is created here, from the recorders that
 * save absences, employee changes, work sites, key personnel and leavers, or by hand.
 * Worker events apply to sponsored workers only; for everyone else the change is logged, never reported.
 */
class ReportTasks
{
    /** Absence: unpaid limit crossed or unauthorised streak reached (from the stored check). */
    public static function forAbsence(Absence $absence, ?User $by = null): ?ReportTask
    {
        if (! $absence->isReportable() || ! $absence->report_trigger_on) {
            return null;
        }

        return self::create($absence->employee->business, ReportTask::WORKER, $absence->employee, $absence->report_event,
            $absence->report_trigger_on, $absence->report_deadline, 'absence', $absence, $by);
    }

    /** "Record a change": job title, SOC code, salary reduction or hours for a sponsored worker. */
    public static function forChange(Employee $employee, EmployeeChange $change, ChangeType $type, ?User $by = null, ?string $on = null): ?ReportTask
    {
        if (! $employee->isSponsored() || ! $type->reportableIfSponsored()) {
            return null;
        }
        $event = match ($type) {
            ChangeType::JobTitle => 'Job title changed',
            ChangeType::Soc => 'SOC code / duties changed',
            ChangeType::SalaryReduction => 'Salary reduced',
            ChangeType::Hours => 'Contracted hours changed',
            default => $type->label().' changed',
        };

        return self::workerTask($employee, "{$event} ({$change->old_value} to {$change->new_value})", $on ?? today(), 'change', $change, $by);
    }

    /** A sponsored worker moved to another work site. */
    public static function forSiteMove(Employee $employee, EmployeeChange $change, ?User $by = null): ?ReportTask
    {
        return $employee->isSponsored()
            ? self::workerTask($employee, "Work location changed to {$change->new_value}", today(), 'change', $change, $by)
            : null;
    }

    /** New work address added, or a work address closed (company level). */
    public static function forSite(WorkSite $site, string $what, ?User $by = null): ReportTask
    {
        $event = $what === 'closed' ? "Work address closed: {$site->name}" : "New work address added: {$site->name}";

        return self::companyTask($site->business, $event, today(), 'work_site', $site, $by);
    }

    /** Authorising Officer, Key Contact or Level 1 User added, changed or removed. */
    public static function forKeyPerson(KeyPerson $person, string $what, ?User $by = null): ReportTask
    {
        $event = match ($what) {
            'added' => "{$person->roleLabel()} added: {$person->name}",
            'removed' => "{$person->roleLabel()} removed: {$person->name}",
            default => "{$person->roleLabel()} details changed: {$person->name}",
        };

        return self::companyTask(Business::findOrFail($person->business_id), $event, today(), 'key_personnel', $what === 'removed' ? null : $person, $by);
    }

    /** End of employment for a sponsored worker: 10 working days from the last day (§10). */
    public static function forLeaver(Employee $employee, ?User $by = null): ?ReportTask
    {
        if (! $employee->isSponsored() || ! $employee->ended_on) {
            return null;
        }
        $event = $employee->end_reason === 'Did not start'
            ? 'Did not start work'
            : "Left: {$employee->end_reason} (last working day {$employee->ended_on->format('j M Y')})";

        return self::workerTask($employee, $event, $employee->ended_on, 'leaver', $employee, $by);
    }

    /** "Create Home Office report" for events the rules cannot see. */
    public static function manual(Business $business, string $level, ?Employee $employee, string $event, string $triggerOn, User $by): ReportTask
    {
        return $level === ReportTask::WORKER
            ? self::workerTask($employee, $event, Carbon::parse($triggerOn), 'manual', null, $by)
            : self::companyTask($business, $event, Carbon::parse($triggerOn), 'manual', null, $by);
    }

    public static function markReported(ReportTask $task, string $on, string $by, ?string $notes, User $user): void
    {
        self::requirePending($task);
        $task->update(['status' => ReportTask::REPORTED, 'reported_on' => $on, 'reported_by' => $by, 'notes' => $notes, 'completed_by' => $user->id, 'completed_at' => now()]);
        Audit::log('report_task.reported', $task, ['on' => $on, 'by' => $by, 'reference' => $notes], $user);
        DashboardCounts::forget($task->business_id);
    }

    public static function markNotRequired(ReportTask $task, string $reason, User $user): void
    {
        self::requirePending($task);
        $task->update(['status' => ReportTask::NOT_REQUIRED, 'reported_on' => null, 'reported_by' => $user->name, 'notes' => $reason, 'completed_by' => $user->id, 'completed_at' => now()]);
        Audit::log('report_task.not_required', $task, ['reason' => $reason], $user);
        DashboardCounts::forget($task->business_id);
    }

    /** Undo "Reported" or "Not required" made by mistake. */
    public static function reopen(ReportTask $task, User $user): void
    {
        $previous = ['status' => $task->status, 'reported_on' => $task->reported_on?->format('Y-m-d'), 'reported_by' => $task->reported_by, 'notes' => $task->notes];
        $task->update(['status' => ReportTask::PENDING, 'reported_on' => null, 'reported_by' => null, 'notes' => null, 'completed_by' => null, 'completed_at' => null]);
        Audit::log('report_task.reopened', $task, ['previous' => $previous], $user);
        DashboardCounts::forget($task->business_id);
    }

    /** Removing the record behind a pending task removes the task; a reported one blocks removal. */
    public static function guardRemoval(Model $subject, User $by): void
    {
        $task = ReportTask::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->first();
        if (! $task) {
            return;
        }
        if (! $task->isPending()) {
            throw ValidationException::withMessages(['task' => 'This has already been marked as '.($task->status === ReportTask::REPORTED ? 'reported to the Home Office' : 'not required').'. Reopen the Home Office task first if it was a mistake.']);
        }
        $task->delete();
        Audit::log('report_task.removed', $task, ['event' => $task->event, 'because' => 'source record removed'], $by);
        DashboardCounts::forget($task->business_id);
    }

    /** One-off, when this stage is installed: tasks for reportable absences and changes recorded earlier. */
    public static function backfill(): void
    {
        Absence::with('employee.business')->where('check_status', AbsenceCheck::REPORT)->get()
            ->filter(fn (Absence $a) => ! ReportTask::where('subject_type', $a->getMorphClass())->where('subject_id', $a->id)->exists())
            ->each(fn (Absence $a) => self::forAbsence($a));

        EmployeeChange::with('employee.business')->whereNull('report_task_id')->get()->each(function (EmployeeChange $c) {
            $type = collect(ChangeType::cases())->first(fn (ChangeType $t) => $t->label() === $c->label);
            if ($type) {
                self::forChange($c->employee, $c, $type, null, $c->created_at->format('Y-m-d'));
            } elseif ($c->field === 'work_site_id' && $c->employee->isSponsored()) {
                self::workerTask($c->employee, "Work location changed to {$c->new_value}", $c->created_at, 'change', $c, null);
            }
        });
    }

    private static function workerTask(Employee $employee, string $event, Carbon|string $triggerOn, string $source, ?Model $subject, ?User $by): ReportTask
    {
        $business = $employee->relationLoaded('business') ? $employee->business : Business::findOrFail($employee->business_id);
        $trigger = Carbon::parse($triggerOn);
        $deadline = WorkingDays::fromDatabase()->add($trigger, (int) $business->rule('worker_report_deadline_days'));

        return self::create($business, ReportTask::WORKER, $employee, $event, $trigger, Carbon::parse($deadline), $source, $subject, $by);
    }

    private static function companyTask(Business $business, string $event, Carbon|string $triggerOn, string $source, ?Model $subject, ?User $by): ReportTask
    {
        $trigger = Carbon::parse($triggerOn);
        $deadline = WorkingDays::fromDatabase()->add($trigger, (int) $business->rule('company_report_deadline_days'));

        return self::create($business, ReportTask::COMPANY, null, $event, $trigger, Carbon::parse($deadline), $source, $subject, $by);
    }

    private static function create(Business $business, string $level, ?Employee $employee, string $event, $triggerOn, $deadline, string $source, ?Model $subject, ?User $by): ReportTask
    {
        $task = ReportTask::create([
            'business_id' => $business->id,
            'level' => $level,
            'employee_id' => $employee?->id,
            'event' => mb_substr($event, 0, 255),
            'trigger_on' => $triggerOn,
            'deadline' => $deadline,
            'source' => $source,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'created_by' => $by?->id,
        ]);
        if ($subject instanceof EmployeeChange) {
            $subject->forceFill(['report_task_id' => $task->id])->save();
        }
        Audit::log('report_task.created', $task, ['event' => $task->event, 'deadline' => $task->deadline->format('Y-m-d'), 'source' => $source], $by, $business->id);
        DashboardCounts::forget($business->id);

        return $task;
    }

    private static function requirePending(ReportTask $task): void
    {
        if (! $task->isPending()) {
            throw ValidationException::withMessages(['task' => 'This task has already been completed.']);
        }
    }
}
