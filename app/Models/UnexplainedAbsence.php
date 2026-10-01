<?php

namespace App\Models;

use App\Services\WorkingDays;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Compliance-rules §11: a scheduled working day with no clock-in and no absence entry. */
class UnexplainedAbsence extends Model
{
    public const OPEN = 'open';
    public const ABSENCE = 'absence'; // an absence was recorded for the day
    public const WORKED = 'worked';   // "Worked – clock-in missed"

    protected $fillable = ['business_id', 'employee_id', 'date', 'status', 'absence_id', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'resolved_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', self::OPEN);
    }

    /** Amber when new; red once it has stayed unclassified for `unexplained_red_after_days` working days. */
    public function badge(WorkingDays $wd, int $redAfter): array
    {
        $waiting = max(0, $wd->until($this->date->format('Y-m-d'), today()->format('Y-m-d')));

        return $waiting >= $redAfter
            ? ['text' => "Unclassified for {$waiting} working days", 'tone' => 'red']
            : ['text' => 'Needs classifying', 'tone' => 'amber'];
    }
}
