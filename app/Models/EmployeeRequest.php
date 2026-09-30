<?php

namespace App\Models;

use App\Enums\AbsenceType;
use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request from the employee portal to HR (compliance-rules §6). */
class EmployeeRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const DECLINED = 'declined';

    /** Leave an employee can ask for; sickness is its own request. */
    public const LEAVE_TYPES = [AbsenceType::Annual, AbsenceType::Unpaid, AbsenceType::Compassionate, AbsenceType::Training];

    /** Changes of details an employee can send. */
    public const CHANGE_KINDS = [
        'address' => ['label' => 'Change of home address', 'field' => 'New address and postcode'],
        'phone' => ['label' => 'Change of phone number', 'field' => 'New phone number'],
        'email' => ['label' => 'Change of email', 'field' => 'New email'],
        'name' => ['label' => 'Change of name', 'field' => 'New full legal name'],
        'visa' => ['label' => 'New visa or visa extension', 'field' => 'New visa expiry date'],
    ];

    /** Documents an employee can send without being asked. */
    public const UPLOAD_CATEGORIES = [DocumentCategory::Passport, DocumentCategory::Absence, DocumentCategory::Other];

    protected $fillable = [
        'business_id', 'employee_id', 'kind', 'leave_type', 'start_date', 'end_date', 'change_kind', 'value',
        'document_id', 'document_request_id', 'note', 'status', 'hr_note', 'decided_by', 'decided_at', 'absence_id',
    ];

    protected function casts(): array
    {
        return [
            'leave_type' => AbsenceType::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function documentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** "Leave request", "Change of home address", "Document"… */
    public function kindLabel(): string
    {
        return match ($this->kind) {
            'leave' => 'Leave request',
            'sickness' => 'Sickness',
            'document' => 'Document',
            default => self::CHANGE_KINDS[$this->change_kind]['label'] ?? 'Change',
        };
    }

    /** One line describing what was asked for. */
    public function summary(): string
    {
        $dates = fn () => $this->start_date->format('j M Y').($this->end_date->equalTo($this->start_date) ? '' : ' – '.$this->end_date->format('j M Y'));

        return match ($this->kind) {
            'leave' => $this->leave_type->label().': '.$dates(),
            'sickness' => 'Off sick: '.$dates(),
            'document' => ($this->document?->category->label() ?? 'Document').($this->document ? ': '.$this->document->original_name : ''),
            default => $this->change_kind === 'visa' ? 'New expiry '.date('j M Y', strtotime($this->value)) : (string) $this->value,
        };
    }

    /** The status as the employee sees it: Waiting for HR, Approved, Declined. */
    public function statusBadge(): array
    {
        return match ($this->status) {
            self::APPROVED => ['text' => 'Approved', 'tone' => 'green'],
            self::DECLINED => ['text' => 'Declined', 'tone' => 'red'],
            default => ['text' => 'Waiting for HR', 'tone' => 'blue'],
        };
    }
}
