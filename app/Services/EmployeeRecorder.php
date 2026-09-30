<?php

namespace App\Services;

use App\Enums\ChangeType;
use App\Enums\DocumentCategory;
use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\ReportTask;
use App\Models\User;
use App\Support\Audit;
use App\Support\DashboardCounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an employee record goes through here, so each change is logged
 * field by field (old, new, who, when) in change history and in the audit log.
 */
class EmployeeRecorder
{
    public function __construct(private PasswordLinks $links) {}

    /** Add an employee (§1 "On save"): log the creation; with a portal invite, request their passport / ID. */
    public function create(Business $business, array $data, User $by, bool $portalInvite): Employee
    {
        return DB::transaction(function () use ($business, $data, $by, $portalInvite) {
            $employee = $business->employees()->create($data);

            EmployeeChange::create([
                'business_id' => $business->id, 'employee_id' => $employee->id, 'field' => 'record',
                'label' => 'Employee record created', 'new_value' => $employee->rtw_basis->label(), 'changed_by' => $by->id,
            ]);
            Audit::log('employee.created', $employee, ['basis' => $employee->rtw_basis->value], $by);
            DashboardCounts::forget($business->id);

            if ($portalInvite) {
                $this->links->invite($employee, $by);
                DocumentRequest::create([
                    'business_id' => $business->id, 'employee_id' => $employee->id,
                    'category' => DocumentCategory::Passport, 'requested_by' => $by->id,
                ]);
            }

            return $employee;
        });
    }

    /**
     * Save edits and log each changed field. Returns the changes made (empty if nothing changed).
     * With a "Record a change" type, the history uses its label (e.g. "Salary – reduction") and a sponsored
     * worker's reportable change creates its Home Office task. A move to another work site does too.
     *
     * @return list<EmployeeChange>
     */
    public function update(Employee $employee, array $data, User $by, ?ChangeType $type = null): array
    {
        $label = $type?->label();

        return DB::transaction(function () use ($employee, $data, $by, $label, $type) {
            $employee->fill($data);
            $changes = [];
            foreach (array_keys(Employee::TRACKED) as $field) {
                if (! $employee->isDirty($field)) {
                    continue;
                }
                $changes[] = [
                    'business_id' => $employee->business_id, 'employee_id' => $employee->id, 'field' => $field,
                    'label' => $label ?? Employee::TRACKED[$field],
                    'old_value' => Employee::displayValue($field, $employee->getOriginal($field)),
                    'new_value' => Employee::displayValue($field, $employee->{$field}),
                    'changed_by' => $by->id,
                ];
            }
            if (! $changes) {
                return [];
            }

            $employee->save();
            $rows = array_map(fn ($c) => EmployeeChange::create($c), $changes);
            Audit::log('employee.updated', $employee, ['fields' => array_column($changes, 'field')], $by);

            // Keep the portal login's name and email in step with the record.
            if ($employee->user && ($employee->wasChanged('full_name') || $employee->wasChanged('email'))) {
                $employee->user->update(['name' => $employee->full_name, 'email' => $employee->email]);
            }

            foreach ($rows as $row) {
                match (true) {
                    $type !== null => ReportTasks::forChange($employee, $row, $type, $by),
                    $row->field === 'work_site_id' => ReportTasks::forSiteMove($employee, $row, $by),
                    default => null,
                };
            }
            DashboardCounts::forget($employee->business_id);

            return $rows;
        });
    }

    /**
     * End of employment (compliance-rules §10): last working day and reason, portal access off,
     * delete-after dates set, open document requests cancelled, and a Home Office task for a sponsored worker.
     */
    public function end(Employee $employee, string $lastDay, string $reason, User $by): ?ReportTask
    {
        return DB::transaction(function () use ($employee, $lastDay, $reason, $by) {
            $business = $employee->business;
            $employee->fill([
                'ended_on' => $lastDay,
                'end_reason' => $reason,
                'delete_after' => Carbon::parse($lastDay)->addYears((int) $business->rule('retention_years')),
                'rtw_delete_after' => Carbon::parse($lastDay)->addYears((int) $business->rule('rtw_retention_years')),
            ])->save();

            EmployeeChange::create([
                'business_id' => $employee->business_id, 'employee_id' => $employee->id, 'field' => 'ended_on',
                'label' => 'Employment ended', 'old_value' => 'Employed', 'new_value' => "{$reason}, last working day ".Employee::formatDate($employee->ended_on),
                'changed_by' => $by->id,
            ]);
            $employee->user?->forceFill(['active' => false, 'password_token' => null, 'password_token_expires_at' => null])->save();
            $employee->documentRequests()->where('status', DocumentRequest::STATUS_AWAITING)->update(['status' => DocumentRequest::STATUS_CANCELLED]);
            Audit::log('employee.ended', $employee, ['last_day' => $lastDay, 'reason' => $reason], $by);
            DashboardCounts::forget($employee->business_id);

            return ReportTasks::forLeaver($employee, $by);
        });
    }
}
