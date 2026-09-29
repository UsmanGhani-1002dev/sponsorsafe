<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\User;
use App\Support\Audit;
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
     * $label names the change in history (e.g. "Salary – reduction"); by default the field's own label.
     *
     * @return list<EmployeeChange>
     */
    public function update(Employee $employee, array $data, User $by, ?string $label = null): array
    {
        return DB::transaction(function () use ($employee, $data, $by, $label) {
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

            return $rows;
        });
    }
}
