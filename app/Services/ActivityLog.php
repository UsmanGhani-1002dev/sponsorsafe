<?php

namespace App\Services;

use App\Enums\AbsenceType;
use App\Enums\DocumentCategory;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Employee;
use App\Models\KeyPerson;
use App\Models\ReportTask;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The audit log in plain English: Settings → Activity log for a business's admins, and Activity log for super admins.
 * Every row is read from `audit_logs` (written by `Audit::log`); nothing here writes to it.
 */
class ActivityLog
{
    /** Filter "What": label and the action prefixes it covers. */
    public const TYPES = [
        'signin' => ['Sign-in and passwords', ['auth.']],
        'documents' => ['Documents', ['document.']],
        'employees' => ['Employee records', ['employee.']],
        'requests' => ['Employee requests', ['employee_request.']],
        'absence' => ['Absence and clock-ins', ['absence.', 'unexplained_absence.', 'clock_ins.', 'clock_in_check.']],
        'reports' => ['Home Office reports', ['report_task.']],
        'settings' => ['Business settings and admins', ['business.', 'work_site.', 'key_person.', 'admin.']],
        'billing' => ['Subscription', ['billing.', 'ops.business.', 'ops.plan_set']],
        'retention' => ['Records deleted', ['retention.']],
    ];

    /** Extra type for the super admin view only. */
    public const OPS_TYPE = ['ops' => ['Super admin area', ['ops.login', 'ops.password_set', 'ops.gateway_changed', 'ops.pricing_changed', 'ops.subscribers_moved', 'ops.enquiry_handled', 'ops.super_admin']]];

    /** Filter "Who" for a business: values of the `who` filter. */
    public const WHO = ['admin' => 'Admins', 'employee' => 'Employees', 'sponsorsafe' => 'SponsorSafe (automatic or support)'];

    public static function types(bool $ops = false): array
    {
        return $ops ? self::TYPES + self::OPS_TYPE : self::TYPES;
    }

    public function applyType(Builder $query, ?string $type, bool $ops = false): void
    {
        $prefixes = self::types($ops)[$type][1] ?? null;
        if ($prefixes) {
            $query->where(fn ($q) => collect($prefixes)->each(fn ($p) => $q->orWhere('action', 'like', addcslashes($p, '%_\\').'%')));
        }
    }

    public function applyWho(Builder $query, ?string $who, Business $business): void
    {
        match ($who) {
            'admin', 'employee' => $query->where('actor_type', 'user')->whereIn('actor_id', User::where('business_id', $business->id)->where('role', $who)->select('id')),
            'sponsorsafe' => $query->whereIn('actor_type', ['super_admin', 'system']),
            default => null,
        };
    }

    public function applyDates(Builder $query, ?string $from, ?string $to): void
    {
        $from && $query->where('created_at', '>=', $from.' 00:00:00');
        $to && $query->where('created_at', '<=', $to.' 23:59:59');
    }

    /**
     * Search by a person's name or email: what they did (their login) and what was done to their record.
     * `$business` null = across all businesses (super admin), which also matches business names.
     */
    public function search(Builder $query, string $term, ?Business $business): void
    {
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $employees = Employee::query()->when($business, fn ($q) => $q->where('business_id', $business->id))->where('full_name', 'like', $like)->pluck('id')->all();
        $users = User::query()->when($business, fn ($q) => $q->where('business_id', $business->id))
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))->pluck('id')->all();
        $admins = $business ? [] : SuperAdmin::where('name', 'like', $like)->orWhere('email', 'like', $like)->pluck('id')->all();
        $businesses = $business ? [] : Business::where('name', 'like', $like)->pluck('id')->all();

        $query->where(function ($q) use ($employees, $users, $admins, $businesses) {
            $q->where(fn ($q) => $q->where('actor_type', 'user')->whereIn('actor_id', $users))
                ->orWhere(fn ($q) => $q->where('actor_type', 'super_admin')->whereIn('actor_id', $admins))
                ->orWhere(fn ($q) => $q->where('subject_type', 'Employee')->whereIn('subject_id', $employees))
                ->orWhereIn('meta->employee_id', $employees)
                ->orWhere(fn ($q) => $q->where('subject_type', 'ReportTask')->whereIn('subject_id', ReportTask::whereIn('employee_id', $employees)->select('id')))
                ->orWhereIn('business_id', $businesses);
        });
    }

    /**
     * Rows for the table. Names are loaded once for the whole page (no N+1).
     * In a business's view a super admin shows as "SponsorSafe support"; super admins see who it was.
     *
     * @param  Collection<int, AuditLog>  $logs
     */
    public function rows(Collection $logs, bool $ops = false): Collection
    {
        $users = User::whereIn('id', $logs->where('actor_type', 'user')->pluck('actor_id')->unique())->get(['id', 'name', 'role'])->keyBy('id');
        $supers = SuperAdmin::whereIn('id', $logs->where('actor_type', 'super_admin')->pluck('actor_id')->unique())->get(['id', 'name'])->keyBy('id');
        $tasks = ReportTask::whereIn('id', $logs->where('subject_type', 'ReportTask')->pluck('subject_id')->unique())->get(['id', 'event', 'employee_id'])->keyBy('id');
        $employeeIds = $logs->map(fn (AuditLog $l) => $l->meta['employee_id'] ?? ($l->subject_type === 'Employee' ? $l->subject_id : null))
            ->merge($tasks->pluck('employee_id'))->filter()->unique();
        $employees = Employee::whereIn('id', $employeeIds)->pluck('full_name', 'id');
        $businesses = $ops ? Business::whereIn('id', $logs->pluck('business_id')->filter()->unique())->pluck('name', 'id') : collect();

        return $logs->map(function (AuditLog $l) use ($users, $supers, $tasks, $employees, $businesses, $ops) {
            $task = $l->subject_type === 'ReportTask' ? $tasks->get($l->subject_id) : null;
            $employeeId = $l->meta['employee_id'] ?? ($l->subject_type === 'Employee' ? $l->subject_id : $task?->employee_id);

            return [
                'id' => $l->id,
                'when' => $l->created_at->format('j M Y, H:i'),
                'who' => $this->actor($l, $users, $supers, $ops),
                'what' => $this->describe($l, $task),
                'employee' => $employeeId ? ['id' => $employees->has($employeeId) ? (int) $employeeId : null, 'name' => $employees->get($employeeId) ?? 'A former employee'] : null,
                'business' => $ops ? ($l->business_id ? ($businesses->get($l->business_id) ?? 'A removed business') : null) : null,
                'ip' => $l->ip,
            ];
        });
    }

    /** @return array{name: string, role: string} */
    private function actor(AuditLog $l, Collection $users, Collection $supers, bool $ops): array
    {
        return match ($l->actor_type) {
            'user' => ($u = $users->get($l->actor_id))
                ? ['name' => $u->name, 'role' => $u->role === 'admin' ? 'Admin' : 'Employee']
                : ['name' => 'A removed login', 'role' => ''],
            'super_admin' => $ops
                ? ['name' => $supers->get($l->actor_id)?->name ?? 'A removed super admin', 'role' => 'Super admin']
                : ['name' => 'SponsorSafe support', 'role' => 'SponsorSafe'],
            default => ['name' => 'SponsorSafe', 'role' => 'Automatic'],
        };
    }

    /** One plain-English line per action. */
    public function describe(AuditLog $l, ?ReportTask $task = null): string
    {
        $m = $l->meta ?? [];
        $category = isset($m['category']) ? (DocumentCategory::tryFrom($m['category'])?->label() ?? $m['category']) : 'a document';
        $file = isset($m['name']) && is_string($m['name']) ? "{$m['name']} ({$category})" : $category;
        $absence = isset($m['type']) && is_string($m['type']) ? (AbsenceType::tryFrom($m['type'])?->label() ?? $m['type']) : 'an absence';
        $event = $task?->event ?? ($m['event'] ?? 'a Home Office report');
        $date = fn ($d) => is_string($d) && strtotime($d) ? date('j M Y', strtotime($d)) : null;
        $kind = fn () => match ($m['kind'] ?? null) {
            'leave' => 'leave', 'sickness' => 'sickness', 'change' => 'change of details', 'document' => 'document', default => '',
        };
        $named = fn () => is_array($m['name'] ?? null) ? ($m['name']['to'] ?? '') : ($m['name'] ?? '');
        $keys = fn (array $a) => collect($a)->keys()->map(fn ($k) => str_replace('_', ' ', $k))->implode(', ');

        return match ($l->action) {
            'auth.login' => 'Signed in',
            'auth.password_set' => 'Set their password',
            'auth.password_reset_requested' => 'Asked for a password reset link',
            'auth.password_changed' => 'Changed their password',
            'auth.two_factor_enabled' => 'Turned on two-step sign-in',
            'auth.two_factor_disabled' => 'Turned off two-step sign-in',

            'document.viewed' => 'Opened '.$file,
            'document.downloaded' => 'Downloaded '.$file,
            'document.uploaded' => ($m['via'] ?? null) === 'portal' ? "Sent {$file} from the portal" : 'Uploaded '.$file,
            'document.filed' => "Accepted {$category} sent from the portal",
            'document.deleted' => 'Removed '.$file,
            'document.requested' => "Asked the employee for {$category}",
            'document.request_cancelled' => "Cancelled the request for {$category}",

            'employee.created' => 'Added the employee',
            'employee.updated' => 'Changed '.(collect($m['fields'] ?? [])->map(fn ($f) => self::lower(Employee::TRACKED[$f] ?? str_replace('_', ' ', $f)))->implode(', ') ?: 'the record'),
            'employee.ended' => 'Ended employment'.(($d = $date($m['last_day'] ?? null)) ? " (last day {$d}".(isset($m['reason']) ? ", {$m['reason']}" : '').')' : ''),
            'employee.invited' => 'Sent a portal invite',
            'employee.pack_exported' => 'Downloaded the compliance pack',

            'employee_request.sent' => trim('Sent a '.$kind().' request'),
            'employee_request.approved' => trim('Approved a '.$kind().' request'),
            'employee_request.declined' => trim('Declined a '.$kind().' request'),

            'absence.recorded' => 'Recorded '.self::lower($absence),
            'absence.fit_note_added' => 'Added a fit note',
            'absence.deleted' => 'Removed '.self::lower($absence).(($s = $date($m['start'] ?? null)) ? " ({$s} to ".$date($m['end'] ?? null).')' : ''),
            'absence.exported' => 'Exported the absence log ('.strtoupper($m['format'] ?? '').', '.($m['rows'] ?? 0).' rows)',
            'unexplained_absence.worked' => 'Marked '.($date($m['date'] ?? null) ?? 'a day').' as worked (clock-in missed)',
            'clock_ins.imported' => 'Imported clock-ins ('.($m['rows'] ?? 0).' rows)',
            'clock_in_check.on' => 'Turned the clock-in check on',
            'clock_in_check.off' => 'Turned the clock-in check off',

            'report_task.created' => "Home Office report task created: {$event}".(($d = $date($m['deadline'] ?? null)) ? " (deadline {$d})" : ''),
            'report_task.reported' => "Marked as reported on the SMS: {$event}",
            'report_task.not_required' => "Marked as not required: {$event}",
            'report_task.reopened' => "Reopened: {$event}",
            'report_task.removed' => "Report task removed with its record: {$event}",

            'work_site.created' => 'Added work site '.$named(),
            'work_site.updated' => 'Changed work site '.($named() ?: '('.$keys($m).')'),
            'work_site.closed' => 'Closed work site '.$named(),
            'key_person.added' => 'Added '.(KeyPerson::ROLES[$m['role'] ?? ''] ?? 'key personnel').' '.$named(),
            'key_person.changed' => 'Changed key personnel ('.$keys($m).')',
            'key_person.removed' => 'Removed '.(KeyPerson::ROLES[$m['role'] ?? ''] ?? 'key personnel').' '.$named(),
            'business.details_updated' => 'Changed business details ('.$keys($m['to'] ?? []).')',
            'business.rules_changed' => 'Changed compliance rules ('.$keys($m).')',
            'admin.invited' => 'Invited admin '.($m['email'] ?? ''),
            'admin.invite_resent' => 'Resent an admin invite',
            'admin.removed' => 'Removed admin '.($m['email'] ?? ''),

            'billing.signup_started' => 'Signed up',
            'billing.activated' => 'Subscription started',
            'billing.reactivated' => 'Subscription restarted',
            'billing.payment_failed' => 'Payment failed'.(($d = $date($m['grace_ends_on'] ?? null)) ? " (access until {$d})" : ''),
            'billing.cancelled' => 'Subscription cancelled'.(($d = $date($m['access_until'] ?? null)) ? " (access until {$d})" : ''),
            'billing.suspended' => 'Account paused',
            'billing.portal_opened' => 'Opened billing',
            'billing.plan_changed' => 'Changed plan',
            'billing.price_change_scheduled' => 'Price change scheduled'.(($d = $date($m['on'] ?? null)) ? " for {$d}" : ''),
            'billing.price_changed' => 'New price applied',
            'ops.business.suspended' => 'Account paused by SponsorSafe',
            'ops.business.activated' => 'Account reopened by SponsorSafe',
            'ops.plan_set' => 'Plan set by SponsorSafe',

            'retention.records_deleted' => "Deleted a leaver's records (retention review)",
            'retention.employee_deleted' => "Deleted a leaver's whole record (retention review)",

            'ops.login' => 'Signed in to the super admin area',
            'ops.password_set' => 'Set their super admin password',
            'ops.gateway_changed' => 'Changed '.ucfirst($m['gateway'] ?? 'payment').' settings ('.($m['mode'] ?? '').')',
            'ops.pricing_changed' => 'Changed plans and pricing',
            'ops.subscribers_moved' => 'Emailed '.($m['count'] ?? 0).' subscriber(s) about a price move',
            'ops.enquiry_handled' => 'Marked an enquiry as handled',
            'ops.super_admin_invited' => 'Invited super admin '.($m['email'] ?? ''),
            'ops.super_admin_invite_resent' => 'Resent a super admin invite',
            'ops.super_admin_removed' => 'Removed super admin '.($m['email'] ?? ''),

            default => ucfirst(str_replace(['.', '_'], [': ', ' '], $l->action)),
        };
    }

    /** "Annual leave" → "annual leave", but "CoS number" and "SOC code" stay as they are. */
    private static function lower(string $label): string
    {
        return preg_match('/^\p{Lu}\p{Ll}*(?!\p{L})/u', $label) &&! preg_match('/^National Insurance/', $label) ? lcfirst($label) : $label;
    }
}
