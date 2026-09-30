<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ReportTask;
use App\Services\ReportTasks;
use App\Services\WorkingDays;
use App\Support\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Home Office reports (compliance-rules §4): report on the SMS, then tick it off here. */
class ReportTaskController extends Controller
{
    /** Events the rules cannot see, for "Create Home Office report". */
    public const MANUAL_EVENTS = [
        ReportTask::WORKER => ['Suspected breach of visa conditions', 'Other change to a sponsored worker'],
        ReportTask::COMPANY => ['Registered or trading address changed', 'Business name changed', 'Change of ownership', 'Directors changed', 'Business stopped trading', 'Other change to the business'],
    ];

    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $table = Table::from($request, sorts: ['deadline' => 'report_tasks.deadline', 'trigger' => 'report_tasks.trigger_on'], default: 'deadline')
            ->filters(['status' => ['pending', 'done', 'all'], 'level' => [ReportTask::WORKER, ReportTask::COMPANY]]);

        $query = $business->reportTasks()->with('employee')
            ->leftJoin('employees', 'employees.id', '=', 'report_tasks.employee_id')->select('report_tasks.*')
            // Pending first (soonest deadline at the top), then completed ones.
            ->orderByRaw("case when report_tasks.status = 'pending' then 0 else 1 end");
        $table->search($query, ['report_tasks.event', 'employees.full_name']);
        match ($table->filter('status', 'all')) {
            'pending' => $query->where('report_tasks.status', ReportTask::PENDING),
            'done' => $query->where('report_tasks.status', '!=', ReportTask::PENDING),
            default => null,
        };
        if ($level = $table->filter('level')) {
            $query->where('report_tasks.level', $level);
        }
        $wd = WorkingDays::fromDatabase();

        return Inertia::render('App/Reports/Index', [
            'tasks' => $table->paginate($query, fn (ReportTask $t) => self::row($t, $wd)),
            'table' => $table->state(),
            'employees' => $business->employees()->current()->orderBy('full_name')->get()->filter->isSponsored()
                ->map(fn ($e) => ['value' => (string) $e->id, 'label' => $e->full_name])->values(),
            'events' => self::MANUAL_EVENTS,
            'reporter' => $request->user()->name,
            'today' => today()->format('Y-m-d'),
            'openTask' => (int) $request->query('task') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $data = $request->validate([
            'level' => ['required', Rule::in([ReportTask::WORKER, ReportTask::COMPANY])],
            'employee_id' => ['required_if:level,worker', 'nullable', Rule::exists('employees', 'id')->where('business_id', $business->id)],
            'event' => ['required', 'string', 'max:120'],
            'details' => ['nullable', 'string', 'max:120'],
            'trigger_on' => ['required', 'date', 'before_or_equal:today'],
        ], ['employee_id.required_if' => 'Choose the sponsored worker.', 'trigger_on.before_or_equal' => 'Use the date it happened (today or earlier).']);

        $employee = $data['level'] === ReportTask::WORKER ? $business->employees()->findOrFail($data['employee_id'])->setRelation('business', $business) : null;
        $event = trim($data['event'].(($data['details'] ?? '') !== '' ? ': '.$data['details'] : ''));
        $task = ReportTasks::manual($business, $data['level'], $employee, $event, $data['trigger_on'], $request->user());

        return back()->with('success', "Home Office report task created. Report it on the Sponsor Management System by {$task->deadline->format('j M Y')}.");
    }

    public function reported(Request $request, int $task): RedirectResponse
    {
        $t = $this->task($request, $task);
        $data = $request->validate([
            'reported_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$t->trigger_on->format('Y-m-d')],
            'reported_by' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'reported_on.required' => 'Enter the date it was reported on the SMS.',
            'reported_on.before_or_equal' => 'The date reported cannot be in the future.',
            'reported_on.after_or_equal' => 'The date reported cannot be before the event.',
            'reported_by.required' => 'Enter who reported it on the SMS.',
        ]);

        return $this->complete(fn () => ReportTasks::markReported($t, $data['reported_on'], $data['reported_by'], $data['notes'] ?? null, $request->user()), 'Marked as reported to the Home Office.');
    }

    public function notRequired(Request $request, int $task): RedirectResponse
    {
        $t = $this->task($request, $task);
        $data = $request->validate(['notes' => ['required', 'string', 'max:500']], ['notes.required' => 'Enter the reason it is not required in the notes field.']);

        return $this->complete(fn () => ReportTasks::markNotRequired($t, $data['notes'], $request->user()), 'Marked as not required.');
    }

    public function reopen(Request $request, int $task): RedirectResponse
    {
        $t = $this->task($request, $task);
        if ($t->isPending()) {
            return back();
        }
        ReportTasks::reopen($t, $request->user());

        return back()->with('success', 'Task reopened.');
    }

    /** One task for the reports list, the profile tab and the dashboard. */
    public static function row(ReportTask $t, WorkingDays $wd): array
    {
        return [
            'id' => $t->id,
            'event' => $t->event,
            'level' => $t->level,
            'who' => $t->level === ReportTask::COMPANY ? 'Company' : ($t->employee?->full_name ?? '—'),
            'employeeId' => $t->employee_id,
            'source' => $t->sourceLabel(),
            'trigger' => $t->trigger_on->format('j M Y'),
            'triggerIso' => $t->trigger_on->format('Y-m-d'),
            'deadline' => $t->deadline->format('j M Y'),
            'badge' => $t->badge($wd),
            'pending' => $t->isPending(),
            'done' => $t->doneText(),
        ];
    }

    private function complete(\Closure $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', $message);
    }

    private function task(Request $request, int $id): ReportTask
    {
        return $request->user()->business->reportTasks()->findOrFail($id);
    }
}
