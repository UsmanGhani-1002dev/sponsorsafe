<?php

namespace App\Models;

use App\Services\WorkingDays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** A Home Office report task (compliance-rules §4): report it on the SMS, then tick it here. */
class ReportTask extends Model
{
    public const PENDING = 'pending';
    public const REPORTED = 'reported';
    public const NOT_REQUIRED = 'not_required';

    public const WORKER = 'worker';
    public const COMPANY = 'company';

    public const SOURCES = [
        'absence' => 'Absence log',
        'change' => 'Employee record',
        'work_site' => 'Work sites',
        'key_personnel' => 'Key personnel',
        'business' => 'Business details',
        'leaver' => 'End of employment',
        'manual' => 'Added by hand',
    ];

    protected $fillable = [
        'business_id', 'level', 'employee_id', 'event', 'trigger_on', 'deadline', 'source', 'subject_type', 'subject_id',
        'status', 'reported_on', 'reported_by', 'notes', 'completed_by', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'trigger_on' => 'date',
            'deadline' => 'date',
            'reported_on' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /**
     * §4 badges: overdue and due today (red), due within 5 working days (amber), pending (blue),
     * reported (green), not required (grey).
     */
    public function badge(WorkingDays $wd, ?CarbonInterface $today = null): array
    {
        if ($this->status === self::REPORTED) {
            return ['text' => 'Reported to Home Office', 'tone' => 'green'];
        }
        if ($this->status === self::NOT_REQUIRED) {
            return ['text' => 'Not required', 'tone' => 'grey'];
        }
        $left = $wd->until(($today ?? today())->format('Y-m-d'), $this->deadline->format('Y-m-d'));
        $days = fn (int $n) => $n.' working '.($n === 1 ? 'day' : 'days');

        return match (true) {
            $left < 0 => ['text' => 'Overdue by '.$days(-$left), 'tone' => 'red'],
            $left === 0 => ['text' => 'Due today', 'tone' => 'red'],
            $left <= 5 => ['text' => 'Due in '.$days($left), 'tone' => 'amber'],
            default => ['text' => $days($left).' left', 'tone' => 'blue'],
        };
    }

    /** "On 15 Jun 2026 by Nadia Khan · SMS-4471920", or the reason it was not required. */
    public function doneText(): ?string
    {
        return match ($this->status) {
            self::REPORTED => 'On '.$this->reported_on?->format('j M Y').' by '.$this->reported_by.($this->notes ? ' · '.$this->notes : ''),
            self::NOT_REQUIRED => 'Reason: '.$this->notes,
            default => null,
        };
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
