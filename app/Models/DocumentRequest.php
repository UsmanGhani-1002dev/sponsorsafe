<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A document HR has asked the employee to upload in the portal. */
class DocumentRequest extends Model
{
    public const STATUS_AWAITING = 'awaiting_employee';

    protected $fillable = ['business_id', 'employee_id', 'category', 'status', 'requested_by'];

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
