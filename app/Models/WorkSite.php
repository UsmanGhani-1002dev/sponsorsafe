<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkSite extends Model
{
    use HasFactory;

    protected $fillable = ['business_id', 'name', 'address', 'closed_on'];

    protected function casts(): array
    {
        return ['closed_on' => 'date'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** Home Office tasks for adding or closing this address. */
    public function reportTasks(): MorphMany
    {
        return $this->morphMany(ReportTask::class, 'subject');
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_on');
    }
}
