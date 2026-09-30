<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Retention;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Due for deletion" review: leavers whose retention period has ended. The admin confirms each deletion. */
class RetentionController extends Controller
{
    public function index(Request $request): Response
    {
        $business = $request->user()->business;

        return Inertia::render('App/Retention/Index', [
            'due' => Retention::due($business)->map(fn ($row) => [
                'id' => $row['employee']->id,
                'name' => $row['employee']->full_name,
                'left' => $row['employee']->ended_on->format('j M Y').' · '.$row['employee']->end_reason,
                'step' => $row['step'],
                'deleteAfter' => $row['employee']->delete_after?->format('j M Y'),
                'rtwDeleteAfter' => $row['employee']->rtw_delete_after?->format('j M Y'),
                'counts' => [
                    'documents' => $row['step'] === Retention::STEP_ALL ? $row['employee']->documents_count : $row['employee']->documents_count - $row['employee']->rtw_documents_count,
                    'absences' => $row['employee']->absences_count,
                    'requests' => $row['employee']->requests_count,
                    'tasks' => $row['employee']->report_tasks_count,
                ],
            ]),
            'rules' => ['records' => (int) $business->rule('retention_years'), 'rtw' => (int) $business->rule('rtw_retention_years')],
        ]);
    }

    public function destroy(Request $request, int $employee): RedirectResponse
    {
        $e = $request->user()->business->employees()->findOrFail($employee);
        $name = $e->full_name;
        $result = Retention::purge($e, $request->user());

        return back()->with('success', $result['step'] === Retention::STEP_ALL
            ? "All records for {$name} have been permanently deleted."
            : "Records for {$name} deleted. Right-to-work evidence is kept until {$e->rtw_delete_after->format('j M Y')}.");
    }
}
