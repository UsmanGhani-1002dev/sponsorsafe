<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row of an employee's change history (compliance-rules.md §5). Never edited or deleted. */
class EmployeeChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'employee_id', 'field', 'label', 'old_value', 'new_value', 'changed_by', 'report_task_id'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reportTask(): BelongsTo
    {
        return $this->belongsTo(ReportTask::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
