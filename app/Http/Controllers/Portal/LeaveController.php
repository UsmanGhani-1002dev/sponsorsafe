<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AbsenceType;
use App\Models\EmployeeRequest;
use App\Services\AbsenceRecorder;
use App\Services\EmployeeRequests;
use App\Services\WorkingDays;
use App\Support\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** "Leave and sickness": ask for leave or report sickness; HR approves it in Requests. */
class LeaveController extends PortalController
{
    public function index(Request $request): Response
    {
        $e = $this->employee($request);
        $year = today()->year;

        return Inertia::render('Portal/Leave', [
            'types' => [
                ...array_map(fn (AbsenceType $t) => ['value' => $t->value, 'label' => $t->label()], EmployeeRequest::LEAVE_TYPES),
                ['value' => 'sick', 'label' => 'Report sickness'],
            ],
            'preselect' => $request->query('type') === 'sick' ? 'sick' : 'annual',
            'leave' => LeaveBalance::for($e, WorkingDays::fromDatabase()),
            'absences' => $e->absences()->whereYear('start_date', $year)->orderByDesc('start_date')->get()
                ->map(fn ($a) => ['id' => $a->id, 'type' => $a->type->label(), 'dates' => $a->dates(), 'days' => $a->working_days]),
            'maxMb' => config('sponsorsafe.documents.max_kb') / 1024,
            'selfCertDays' => (int) $e->business->rule('self_cert_max_days'),
        ]);
    }

    /** Live information for the form: working days, leave left afterwards, and warnings. Nothing is saved. */
    public function check(Request $request, AbsenceRecorder $absences): JsonResponse
    {
        $e = $this->employee($request);
        $data = $request->validate(['type' => ['required', Rule::in($this->typeValues())], 'start_date' => ['required', 'date'], 'end_date' => ['required', 'date']]);
        $wd = WorkingDays::fromDatabase();
        $sick = $data['type'] === 'sick';
        $type = $sick ? AbsenceType::SickSelf : AbsenceType::from($data['type']);
        $check = $absences->check($e, $type, $data['start_date'], $data['end_date']);
        if (! $check->valid()) {
            return response()->json(['valid' => false, 'info' => $check->detail, 'notes' => []]);
        }

        $info = $check->days.' working '.($check->days === 1 ? 'day' : 'days').'.';
        $notes = [];
        $calendarDays = (int) (new \DateTimeImmutable($data['start_date']))->diff(new \DateTimeImmutable($data['end_date']))->days + 1;
        $selfCert = (int) $e->business->rule('self_cert_max_days');
        if ($type === AbsenceType::Annual) {
            $left = (float) LeaveBalance::for($e, $wd)['left'] - $check->days;
            $info .= ' You would have '.LeaveBalance::num($left).' days of annual leave left.';
            if ($left < 0) {
                $notes[] = 'This is more annual leave than you have left.';
            }
        }
        if ($type === AbsenceType::Unpaid && $e->isSponsored()) {
            $notes[] = 'Long periods of unpaid leave can affect your visa sponsorship. HR may need to talk to you before approving.';
        }
        if ($sick && $calendarDays > $selfCert) {
            $notes[] = "For more than {$selfCert} days off sick you need a fit note from your GP. You can attach it here or send it later under My documents.";
        }

        return response()->json(['valid' => true, 'info' => $info, 'notes' => $notes, 'needsFitNote' => $sick && $calendarDays > $selfCert]);
    }

    public function store(Request $request, EmployeeRequests $requests): RedirectResponse
    {
        $e = $this->employee($request);
        $data = $request->validate([
            'type' => ['required', Rule::in($this->typeValues())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:300'],
            'fit_note' => ['nullable', 'file', 'max:'.config('sponsorsafe.documents.max_kb'), 'mimes:'.implode(',', config('sponsorsafe.documents.mimes'))],
        ], [
            'end_date.after_or_equal' => 'The last day must be on or after the first day.',
            'fit_note.mimes' => 'Send the fit note as a PDF, JPG or PNG file.',
        ]);

        if ($data['type'] === 'sick') {
            $requests->sickness($e, $data['start_date'], $data['end_date'], $data['note'] ?? null, $request->file('fit_note'), $request->user());
        } else {
            $requests->leave($e, AbsenceType::from($data['type']), $data['start_date'], $data['end_date'], $data['note'] ?? null, $request->user());
        }

        return redirect()->route('portal.requests')->with('success', 'Sent to HR. You can follow it here.');
    }

    private function typeValues(): array
    {
        return [...array_map(fn ($t) => $t->value, EmployeeRequest::LEAVE_TYPES), 'sick'];
    }
}
