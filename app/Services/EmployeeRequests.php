<?php

namespace App\Services;

use App\Enums\AbsenceType;
use App\Enums\ChangeType;
use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Support\Audit;
use App\Support\DashboardCounts;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Employee portal requests → HR inbox (compliance-rules §6). Approving goes through the same recorders
 * HR uses, so absences, change history, audit entries and Home Office tasks all follow the usual rules.
 */
class EmployeeRequests
{
    public function __construct(private AbsenceRecorder $absences, private EmployeeRecorder $records, private DocumentVault $vault) {}

    // ---- From the portal ----

    public function leave(Employee $employee, AbsenceType $type, string $start, string $end, ?string $note, User $by): EmployeeRequest
    {
        $this->checkDates($employee, $type, $start, $end);

        return $this->create($employee, ['kind' => 'leave', 'leave_type' => $type, 'start_date' => $start, 'end_date' => $end, 'note' => $note], $by);
    }

    public function sickness(Employee $employee, string $start, string $end, ?string $note, ?UploadedFile $fitNote, User $by): EmployeeRequest
    {
        $this->checkDates($employee, $this->sicknessType($employee, $start, $end), $start, $end);
        $doc = $fitNote ? $this->vault->store($employee, $fitNote, DocumentCategory::Absence, null, $by, 'portal') : null;

        return $this->create($employee, ['kind' => 'sickness', 'start_date' => $start, 'end_date' => $end, 'note' => $note, 'document_id' => $doc?->id], $by);
    }

    public function change(Employee $employee, string $kind, string $value, ?string $note, User $by): EmployeeRequest
    {
        return $this->create($employee, ['kind' => 'change', 'change_kind' => $kind, 'value' => $value, 'note' => $note], $by);
    }

    /** A document, either one HR asked for ($for) or sent unprompted. It waits for HR before it counts as on file. */
    public function document(Employee $employee, UploadedFile $file, DocumentCategory $category, ?DocumentRequest $for, ?string $note, User $by): EmployeeRequest
    {
        return DB::transaction(function () use ($employee, $file, $category, $for, $note, $by) {
            $doc = $this->vault->store($employee, $file, $for?->category ?? $category, null, $by, 'portal');

            return $this->create($employee, ['kind' => 'document', 'document_id' => $doc->id, 'document_request_id' => $for?->id, 'note' => $note], $by);
        });
    }

    // ---- In the HR inbox ----

    /** What approving would do, shown before HR decides. */
    public function preview(EmployeeRequest $r): ?array
    {
        return match ($r->kind) {
            'leave', 'sickness' => $this->absencePreview($r),
            'change' => $r->change_kind === 'visa'
                ? ['text' => 'Do a new right-to-work check with their share code before approving.', 'tone' => 'amber']
                : ['text' => 'Updates their record and change history. Not reportable.', 'tone' => 'grey'],
            'document' => ['text' => 'Approving files it under '.$r->document?->category->label().'.', 'tone' => 'grey'],
            default => null,
        };
    }

    /** @throws ValidationException when the request can no longer be applied (e.g. overlapping dates) */
    public function approve(EmployeeRequest $r, User $by, ?string $hrNote = null): string
    {
        $this->requirePending($r);
        $employee = $r->employee;

        return DB::transaction(function () use ($r, $employee, $by, $hrNote) {
            [$note, $message] = match ($r->kind) {
                'leave' => $this->approveAbsence($r, $employee, $r->leave_type, $by),
                'sickness' => $this->approveAbsence($r, $employee, $this->sicknessType($employee, $r->start_date->format('Y-m-d'), $r->end_date->format('Y-m-d')), $by),
                'change' => $this->approveChange($r, $employee, $by),
                'document' => $this->approveDocument($r, $by),
            };
            $r->update(['status' => EmployeeRequest::APPROVED, 'hr_note' => $hrNote ?: $note, 'decided_by' => $by->id, 'decided_at' => now()]);
            Audit::log('employee_request.approved', $r, ['kind' => $r->kind, 'employee_id' => $employee->id], $by);
            DashboardCounts::forget($r->business_id);

            return $message;
        });
    }

    public function decline(EmployeeRequest $r, User $by, ?string $hrNote = null): void
    {
        $this->requirePending($r);
        DB::transaction(function () use ($r, $by, $hrNote) {
            if ($r->kind === 'document' || ($r->kind === 'sickness' && $r->document)) {
                // Not accepted: remove the file. A request HR made goes back to "Action needed".
                if ($r->document) {
                    $this->vault->delete($r->document, $by);
                }
            }
            $r->update(['status' => EmployeeRequest::DECLINED, 'hr_note' => $hrNote ?: 'Declined – HR will contact you.', 'decided_by' => $by->id, 'decided_at' => now()]);
            Audit::log('employee_request.declined', $r, ['kind' => $r->kind, 'employee_id' => $r->employee_id], $by);
            DashboardCounts::forget($r->business_id);
        });
    }

    // ---- Internals ----

    private function create(Employee $employee, array $data, User $by): EmployeeRequest
    {
        $request = $employee->requests()->create(['business_id' => $employee->business_id, ...$data]);
        Audit::log('employee_request.sent', $request, ['kind' => $request->kind], $by);
        DashboardCounts::forget($employee->business_id);

        return $request;
    }

    private function absencePreview(EmployeeRequest $r): array
    {
        $type = $r->kind === 'sickness' ? $this->sicknessType($r->employee, $r->start_date->format('Y-m-d'), $r->end_date->format('Y-m-d')) : $r->leave_type;
        $check = $this->absences->check($r->employee, $type, $r->start_date->format('Y-m-d'), $r->end_date->format('Y-m-d'));

        return match ($check->status) {
            AbsenceCheck::INVALID => ['text' => 'Cannot be approved: '.$check->detail, 'tone' => 'red'],
            AbsenceCheck::REPORT => ['text' => 'Approving needs a Home Office report: '.mb_strtolower($check->event).' (report by '.date('j M Y', strtotime($check->deadline)).').', 'tone' => 'red'],
            default => ['text' => $check->days.' working '.($check->days === 1 ? 'day' : 'days').'. No Home Office report needed.'.($check->warnings ? ' '.$check->warnings[0] : ''), 'tone' => $check->warnings ? 'amber' : 'grey'],
        };
    }

    private function approveAbsence(EmployeeRequest $r, Employee $employee, AbsenceType $type, User $by): array
    {
        $absence = $this->absences->record($employee, $type, $r->start_date->format('Y-m-d'), $r->end_date->format('Y-m-d'),
            $r->note ? mb_substr($r->note, 0, 150) : null, $r->document, $by, 'portal');
        $r->absence_id = $absence->id;

        $note = match (true) {
            $r->kind === 'sickness' && $type === AbsenceType::SickFitNote && ! $absence->fit_note_id => 'Recorded. Please upload your fit note under My documents.',
            $r->kind === 'sickness' => 'Recorded. Get well soon.',
            $absence->isReportable() => 'Approved. HR will report this to the Home Office.',
            default => 'Approved',
        };
        $message = 'Approved and added to the absence log.'.($absence->isReportable()
            ? ' Sponsored worker: a Home Office report task was created, deadline '.$absence->report_deadline->format('j M Y').'.' : '');

        return [$note, $message];
    }

    private function approveChange(EmployeeRequest $r, Employee $employee, User $by): array
    {
        if ($r->change_kind === 'visa') {
            if (! $employee->rtw_basis->timeLimited()) {
                throw ValidationException::withMessages(['request' => "{$employee->full_name}'s permission has no time limit, so there is no visa expiry to update."]);
            }
            $this->records->update($employee, ['visa_expiry' => $r->value, 'follow_up_check_due' => $r->value], $by);

            return ['Updated. HR will do a new right-to-work check with your share code.',
                'Visa expiry and follow-up check updated. Now do a new right-to-work check with their share code and upload the result.'];
        }

        [$type, $data] = match ($r->change_kind) {
            'name' => [null, EmployeeRules::validatePersonal(['full_name' => $r->value] + $this->personal($employee), $employee)],
            default => EmployeeRules::validateChange(['type' => ['address' => 'address', 'phone' => 'phone', 'email' => 'email'][$r->change_kind], 'value' => $r->value], $employee),
        };
        $this->records->update($employee, $data, $by, $type instanceof ChangeType ? $type : null);

        return ['Updated', 'Record updated and logged in the change history.'];
    }

    private function approveDocument(EmployeeRequest $r, User $by): array
    {
        if (! $r->document) {
            throw ValidationException::withMessages(['request' => 'The file is no longer available. Decline this request.']);
        }
        $this->vault->file($r->document, $by);

        return ['Received and filed. Thank you.', 'Filed under '.$r->document->category->label().'.'];
    }

    /** Self-certification covers up to 7 calendar days (a setting); longer sickness needs a fit note. */
    private function sicknessType(Employee $employee, string $start, string $end): AbsenceType
    {
        $days = (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;

        return $days > (int) $employee->business->rule('self_cert_max_days') ? AbsenceType::SickFitNote : AbsenceType::SickSelf;
    }

    private function checkDates(Employee $employee, AbsenceType $type, string $start, string $end): void
    {
        $check = $this->absences->check($employee, $type, $start, $end);
        if (! $check->valid()) {
            throw ValidationException::withMessages(['start_date' => $check->detail]);
        }
    }

    /** The other personal fields, unchanged, so only the name is validated as new. */
    private function personal(Employee $e): array
    {
        return ['date_of_birth' => $e->date_of_birth?->format('Y-m-d'), 'nationality' => $e->nationality, 'passport_expiry' => $e->passport_expiry?->format('Y-m-d')];
    }

    private function requirePending(EmployeeRequest $r): void
    {
        if (! $r->isPending()) {
            throw ValidationException::withMessages(['request' => 'This request has already been decided.']);
        }
    }
}
