<?php

namespace App\Services;

use App\Enums\AbsenceType;
use App\Enums\DocumentCategory;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every absence is written through here: the §3 check runs on the server again at save time, and the
 * result (trigger, deadline, event) is stored with the absence. Stage 4 creates the report task here.
 */
class AbsenceRecorder
{
    public function __construct(private DocumentVault $vault) {}

    /** The live check for the form, against this employee's other absences. */
    public function check(Employee $employee, AbsenceType $type, string $start, string $end, ?Absence $ignore = null): AbsenceCheck
    {
        $others = $employee->absences()->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->where('end_date', '>=', Carbon::parse($start)->subYears(2))->get()->map->forRules();

        return AbsenceRules::forBusiness($employee->business)->check($type, $start, $end, (float) $employee->days_per_week, $employee->isSponsored(), $others);
    }

    /**
     * @throws ValidationException when the dates are not valid for this employee
     */
    public function record(Employee $employee, AbsenceType $type, string $start, string $end, ?string $reason, ?UploadedFile $fitNote, User $by, string $source = 'hr'): Absence
    {
        $check = $this->check($employee, $type, $start, $end);
        if (! $check->valid()) {
            throw ValidationException::withMessages(['start_date' => $check->detail]);
        }

        return DB::transaction(function () use ($employee, $type, $start, $end, $reason, $fitNote, $by, $source, $check) {
            $note = $fitNote ? $this->vault->store($employee, $fitNote, DocumentCategory::Absence, null, $by) : null;
            $absence = $employee->absences()->create([
                'business_id' => $employee->business_id,
                'type' => $type,
                'start_date' => $start,
                'end_date' => $end,
                'working_days' => $check->days,
                'reason' => $reason,
                'fit_note_id' => $note?->id,
                'check_status' => $check->status,
                'report_trigger_on' => $check->trigger,
                'report_deadline' => $check->deadline,
                'report_event' => $check->event,
                'recorded_by' => $by->id,
                'source' => $source,
            ]);
            Audit::log('absence.recorded', $absence, ['employee_id' => $employee->id, 'type' => $type->value, 'days' => $check->days, 'check' => $check->status], $by);

            return $absence;
        });
    }

    public function attachFitNote(Absence $absence, UploadedFile $file, User $by): void
    {
        $note = $this->vault->store($absence->employee, $file, DocumentCategory::Absence, null, $by);
        $absence->update(['fit_note_id' => $note->id]);
        Audit::log('absence.fit_note_added', $absence, ['document_id' => $note->id], $by);
    }

    public function delete(Absence $absence, User $by): void
    {
        $absence->delete();
        Audit::log('absence.deleted', $absence, [
            'employee_id' => $absence->employee_id, 'type' => $absence->type->value,
            'start' => $absence->start_date->format('Y-m-d'), 'end' => $absence->end_date->format('Y-m-d'),
        ], $by);
    }
}
