<?php

namespace App\Http\Controllers\App;

use App\Enums\AbsenceType;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Employee;
use App\Services\AbsenceCheck;
use App\Services\AbsenceRecorder;
use App\Services\WorkingDays;
use App\Support\Audit;
use App\Support\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Absence log, "Record absence" with the live Home Office check, and exports (compliance-rules §3). */
class AbsenceController extends Controller
{
    private ?WorkingDays $wd = null;

    public function __construct(private AbsenceRecorder $recorder) {}

    public function index(Request $request): Response
    {
        [$table, $query] = $this->filtered($request);

        return Inertia::render('App/Absence/Index', [
            'absences' => $table->paginate($query, fn (Absence $a) => self::row($a, $this->wd())),
            'table' => $table->state(),
            'employees' => $request->user()->business->employees()->orderBy('full_name')->get(['id', 'full_name'])->map(fn ($e) => ['value' => (string) $e->id, 'label' => $e->full_name]),
            'types' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], AbsenceType::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        $employees = $request->user()->business->employees()->current()->orderBy('full_name')->get();

        return Inertia::render('App/Absence/Create', [
            'employees' => $employees->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name, 'sponsored' => $e->isSponsored()]),
            'types' => AbsenceType::options(),
            'preselect' => $employees->firstWhere('id', (int) $request->query('employee'))?->id,
            'maxUploadMb' => config('sponsorsafe.documents.max_kb') / 1024,
        ]);
    }

    /** Live Home Office check for the form. Same rules as saving; nothing is stored. */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules($request, withFile: false));
        $employee = $this->employee($request, (int) $data['employee_id']);

        return response()->json($this->recorder->check($employee, AbsenceType::from($data['type']), $data['start_date'], $data['end_date'])->toArray());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules($request, withFile: true), [
            'end_date.after_or_equal' => 'The last day must be on or after the first day.',
            'fit_note.mimes' => 'Upload the fit note as a PDF, JPG or PNG file.',
        ]);
        $employee = $this->employee($request, (int) $data['employee_id']);
        $absence = $this->recorder->record($employee, AbsenceType::from($data['type']), $data['start_date'], $data['end_date'],
            $data['reason'] ?? null, $request->file('fit_note'), $request->user());

        $message = match ($absence->check_status) {
            AbsenceCheck::REPORT => "Saved. Sponsored worker: report this on the Sponsor Management System by {$absence->report_deadline->format('j M Y')}.",
            default => 'Saved in the absence log.',
        };
        if ($absence->needsFitNote()) {
            $message .= ' Upload the fit note when you have it.';
        }

        return redirect($request->input('return') === 'profile' ? route('app.employees.show', [$employee, 'tab' => 'absence']) : route('app.absence.index'))
            ->with('success', $message);
    }

    public function destroy(Request $request, int $absence): RedirectResponse
    {
        $a = Absence::where('business_id', $request->user()->business_id)->findOrFail($absence);
        try {
            $this->recorder->delete($a, $request->user());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', 'Absence removed from the log.');
    }

    public function fitNote(Request $request, int $absence): RedirectResponse
    {
        $a = Absence::with('employee.business')->where('business_id', $request->user()->business_id)->findOrFail($absence);
        $request->validate(['fit_note' => $this->fileRules(required: true)], ['fit_note.mimes' => 'Upload the fit note as a PDF, JPG or PNG file.']);
        $this->recorder->attachFitNote($a, $request->file('fit_note'), $request->user());

        return back()->with('success', 'Fit note attached and filed under absence evidence.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$table, $query] = $this->filtered($request);
        $rows = $table->sorted($query)->limit(5000)->get();
        Audit::log('absence.exported', null, ['format' => 'csv', 'rows' => $rows->count(), 'filters' => $table->state()]);

        $wd = $this->wd();

        return response()->streamDownload(function () use ($rows, $wd) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Employee', 'Type', 'Start', 'End', 'Working days', 'Pay', 'Reason', 'Home Office']);
            foreach ($rows as $a) {
                $r = self::row($a, $wd);
                // Guard against spreadsheet formula injection in free text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v,
                    [$r['employee'], $r['type'], $a->start_date->format('Y-m-d'), $a->end_date->format('Y-m-d'), $r['days'], $r['pay'], $r['reason'], $r['homeOffice']['text']]));
            }
            fclose($out);
        }, 'absences-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request): \Illuminate\Http\Response
    {
        [$table, $query] = $this->filtered($request);
        $rows = $table->sorted($query)->limit(2000)->get()->map(fn ($a) => self::row($a, $this->wd()));
        $state = $table->state();
        Audit::log('absence.exported', null, ['format' => 'pdf', 'rows' => $rows->count(), 'filters' => $state]);

        $filters = array_filter([
            'Employee' => $state['filters']['employee'] ? Employee::find($state['filters']['employee'])?->full_name : null,
            'Type' => $state['filters']['type'] ? AbsenceType::from($state['filters']['type'])->label() : null,
            'From' => $state['from'] ? date('j M Y', strtotime($state['from'])) : null,
            'To' => $state['to'] ? date('j M Y', strtotime($state['to'])) : null,
            'Search' => $state['q'] ?: null,
        ]);

        return Pdf::loadView('pdf.absences', [
            'business' => $request->user()->business->name,
            'rows' => $rows,
            'filters' => $filters,
            'generated' => now()->format('j M Y, H:i'),
        ])->setOption('isFontSubsettingEnabled', true)->setPaper('a4', 'landscape')->download('absences-'.now()->format('Y-m-d').'.pdf');
    }

    /** One row for the log, the CSV and the PDF. The Home Office badge follows the absence's report task. */
    public static function row(Absence $a, WorkingDays $wd): array
    {
        $task = $a->reportTask;
        $ho = match (true) {
            $task && ! $task->isPending() => $task->badge($wd),
            $task !== null => ['text' => 'Report by '.$task->deadline->format('j M Y'), 'tone' => $task->badge($wd)['tone'] === 'blue' ? 'amber' : $task->badge($wd)['tone']],
            $a->isReportable() => ['text' => 'Report by '.$a->report_deadline->format('j M Y'), 'tone' => $a->report_deadline->isPast() ? 'red' : 'amber'],
            $a->check_status === AbsenceCheck::NOT_YET => ['text' => 'Counting – no report yet', 'tone' => 'blue'],
            default => ['text' => 'Not reportable', 'tone' => 'grey'],
        };

        return [
            'id' => $a->id,
            'employeeId' => $a->employee_id,
            'employee' => $a->employee->full_name,
            'type' => $a->type->label(),
            'dates' => $a->dates(),
            'days' => $a->working_days,
            'pay' => $a->type->pay(),
            'reason' => $a->reason,
            'homeOffice' => $ho,
            'fitNoteMissing' => $a->needsFitNote(),
            'taskId' => $task?->isPending() ? $task->id : null,
        ];
    }

    /** @return array{Table, Builder} */
    private function filtered(Request $request): array
    {
        $business = $request->user()->business;
        $table = Table::from($request, sorts: ['start' => 'absences.start_date', 'employee' => 'employees.full_name', 'days' => 'absences.working_days'], default: 'start', dir: 'desc')
            ->filters([
                'employee' => $business->employees()->pluck('id')->map(fn ($id) => (string) $id)->all(),
                'type' => array_column(AbsenceType::cases(), 'value'),
            ]);

        $query = Absence::query()->select('absences.*')->with(['employee', 'reportTask'])
            ->join('employees', 'employees.id', '=', 'absences.employee_id')
            ->where('absences.business_id', $business->id);
        $table->search($query, ['employees.full_name', 'absences.reason']);
        if ($employee = $table->filter('employee')) {
            $query->where('absences.employee_id', $employee);
        }
        if ($type = $table->filter('type')) {
            $query->where('absences.type', $type);
        }
        // Date range: any absence that overlaps it.
        if ($from = $table->date('from')) {
            $query->where('absences.end_date', '>=', $from);
        }
        if ($to = $table->date('to')) {
            $query->where('absences.start_date', '<=', $to);
        }

        return [$table, $query];
    }

    private function rules(Request $request, bool $withFile): array
    {
        return [
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('business_id', $request->user()->business_id)],
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:150'],
            ...($withFile ? ['fit_note' => $this->fileRules(required: false)] : []),
        ];
    }

    private function fileRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'max:'.config('sponsorsafe.documents.max_kb'), 'mimes:'.implode(',', config('sponsorsafe.documents.mimes'))];
    }

    private function wd(): WorkingDays
    {
        return $this->wd ??= WorkingDays::fromDatabase();
    }

    private function employee(Request $request, int $id): Employee
    {
        $business = $request->user()->business;

        return $business->employees()->findOrFail($id)->setRelation('business', $business);
    }
}
