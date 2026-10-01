<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A day someone clocked in (from a clock-in system's export or, later, an integration). */
class ClockIn extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'employee_id', 'date', 'first_in', 'source', 'imported_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'imported_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
