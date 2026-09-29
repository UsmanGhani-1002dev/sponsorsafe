<?php

namespace App\Models;

use App\Enums\RightToWorkBasis;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    public const CHECK_METHODS = ['Home Office online check (share code)', 'Manual check of original passport', 'IDVT – certified identity provider'];
    public const CONTRACT_TYPES = ['Permanent', 'Fixed term', 'Part-time permanent'];
    public const NATIONALITIES = ['British', 'Irish', 'Indian', 'Pakistani', 'Bangladeshi', 'Nigerian', 'Polish', 'Romanian', 'Filipino', 'Other'];

    /** Encrypted at rest; only the last 4 characters are ever shown. */
    public const SECRET_FIELDS = ['ni_number', 'passport_number', 'share_code'];

    /** Fields tracked in change history, with the plain-English label shown to HR. */
    public const TRACKED = [
        'full_name' => 'Full legal name',
        'date_of_birth' => 'Date of birth',
        'nationality' => 'Nationality',
        'email' => 'Email',
        'phone' => 'Phone',
        'address' => 'Home address',
        'ni_number' => 'National Insurance number',
        'passport_number' => 'Passport number',
        'passport_expiry' => 'Passport expiry',
        'rtw_basis' => 'Right-to-work basis',
        'visa_type' => 'Visa / status',
        'rtw_check_method' => 'Check method',
        'rtw_check_date' => 'Check date',
        'rtw_checked_by' => 'Checked by',
        'share_code' => 'Share code',
        'visa_start' => 'Visa start date',
        'visa_expiry' => 'Visa / permission expiry',
        'work_restrictions' => 'Work restrictions',
        'follow_up_check_due' => 'Follow-up check due',
        'cos_number' => 'CoS number',
        'cos_assigned_on' => 'CoS assigned date',
        'soc_code' => 'SOC code',
        'job_title' => 'Job title',
        'salary' => 'Salary',
        'start_date' => 'Start date',
        'work_site_id' => 'Work site',
        'days_per_week' => 'Working days per week',
        'contracted_hours' => 'Contracted weekly hours',
        'contract_type' => 'Contract type',
    ];

    protected $fillable = [
        'business_id', 'user_id', 'work_site_id', 'full_name', 'date_of_birth', 'nationality', 'email', 'phone', 'address',
        'ni_number', 'passport_number', 'passport_expiry', 'rtw_basis', 'visa_type', 'rtw_check_method', 'rtw_check_date',
        'rtw_checked_by', 'share_code', 'visa_start', 'visa_expiry', 'work_restrictions', 'follow_up_check_due', 'cos_number',
        'cos_assigned_on', 'soc_code', 'job_title', 'salary', 'start_date', 'days_per_week', 'contracted_hours', 'contract_type',
        'ended_on', 'end_reason',
    ];

    protected $hidden = self::SECRET_FIELDS;

    protected function casts(): array
    {
        return [
            'rtw_basis' => RightToWorkBasis::class,
            'ni_number' => 'encrypted',
            'passport_number' => 'encrypted',
            'share_code' => 'encrypted',
            'date_of_birth' => 'date',
            'passport_expiry' => 'date',
            'rtw_check_date' => 'date',
            'visa_start' => 'date',
            'visa_expiry' => 'date',
            'follow_up_check_due' => 'date',
            'cos_assigned_on' => 'date',
            'start_date' => 'date',
            'ended_on' => 'date',
            'salary' => 'decimal:2',
            'days_per_week' => 'decimal:1',
            'contracted_hours' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workSite(): BelongsTo
    {
        return $this->belongsTo(WorkSite::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(EmployeeChange::class)->latest('created_at')->latest('id');
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /** Still employed (counts towards the plan's employee limit). */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('ended_on');
    }

    public function isSponsored(): bool
    {
        return $this->rtw_basis->sponsored();
    }

    /** "•••• 1234" for an encrypted field, or null if empty. */
    public function masked(string $field): ?string
    {
        $value = $this->{$field};

        return $value ? '•••• '.mb_substr(preg_replace('/\s+/', '', $value), -4) : null;
    }

    /** The status shown in lists: sponsored route, visa type for time-limited, otherwise the basis. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->isSponsored() => 'Skilled Worker (sponsored)',
            $this->rtw_basis->timeLimited() => $this->visa_type ?: $this->rtw_basis->label(),
            default => $this->rtw_basis->label(),
        };
    }

    /** portal: none | invited (not signed in yet) | active */
    public function portalStatus(): string
    {
        return match (true) {
            ! $this->user || ! $this->user->active => 'none',
            $this->user->last_login_at === null => 'invited',
            default => 'active',
        };
    }

    /** A value as HR should read it in change history: formatted dates and money, site names, masked secrets. */
    public static function displayValue(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            in_array($field, self::SECRET_FIELDS, true) => '•••• '.mb_substr(preg_replace('/\s+/', '', (string) $value), -4),
            $value instanceof CarbonInterface => self::formatDate($value),
            $value instanceof RightToWorkBasis => $value->label(),
            $field === 'work_site_id' => WorkSite::find($value)?->name,
            $field === 'salary' => '£'.number_format((float) $value, 2),
            $field === 'contracted_hours' => rtrim(rtrim((string) $value, '0'), '.').' hours',
            $field === 'days_per_week' => rtrim(rtrim((string) $value, '0'), '.').' days',
            default => (string) $value,
        };
    }

    /** UK display format used everywhere: "24 Sep 2026". */
    public static function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('j M Y');
    }
}
