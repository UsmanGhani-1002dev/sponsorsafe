<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Services\AbsenceCheck;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in the absence log (compliance-rules §3), with its Home Office check result. */
class Absence extends Model
{
    protected $fillable = [
        'business_id', 'employee_id', 'type', 'start_date', 'end_date', 'working_days', 'reason', 'fit_note_id',
        'check_status', 'report_trigger_on', 'report_deadline', 'report_event', 'recorded_by', 'source',
    ];

    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'report_trigger_on' => 'date',
            'report_deadline' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fitNote(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'fit_note_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isReportable(): bool
    {
        return $this->check_status === AbsenceCheck::REPORT;
    }

    public function needsFitNote(): bool
    {
        return $this->type === AbsenceType::SickFitNote && ! $this->fit_note_id;
    }

    /** The shape AbsenceRules expects for "other absences". */
    public function forRules(): array
    {
        return ['type' => $this->type, 'start' => $this->start_date->format('Y-m-d'), 'end' => $this->end_date->format('Y-m-d')];
    }

    /** "5 Oct 2026" or "5 Oct – 23 Oct 2026" */
    public function dates(): string
    {
        $s = $this->start_date;
        $e = $this->end_date;
        if ($s->equalTo($e)) {
            return $s->format('j M Y');
        }

        return $s->format($s->year === $e->year ? 'j M' : 'j M Y').' – '.$e->format('j M Y');
    }
}
