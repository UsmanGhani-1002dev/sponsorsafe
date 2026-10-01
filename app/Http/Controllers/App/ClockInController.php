<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\UnexplainedAbsence;
use App\Services\UnexplainedAbsences;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Clock-in check (compliance-rules §11): switch it on, upload the clock-in system's CSV, resolve alerts. */
class ClockInController extends Controller
{
    public function __construct(private UnexplainedAbsences $unexplained) {}

    public function toggle(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $on = $request->boolean('enabled');
        $business->update(['settings' => [...(array) $business->settings, 'clock_in_check' => $on]]);
        Audit::log($on ? 'clock_in_check.on' : 'clock_in_check.off', $business);

        return back()->with('success', $on
            ? 'Clock-in check is on. Upload your clock-in export to check those days.'
            : 'Clock-in check is off. No new unexplained absences will be flagged.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']], [
            'file.required' => 'Choose the CSV file from your clock-in system.',
            'file.mimes' => 'Upload a CSV file.',
            'file.max' => 'The file must be 2 MB or smaller.',
        ]);
        $business = $request->user()->business;
        if (! UnexplainedAbsences::enabled($business)) {
            return back()->with('error', 'Switch the clock-in check on first.');
        }
        $result = $this->unexplained->importCsv($business, $request->file('file'), $request->user());

        $message = "Imported {$result['imported']} clock-in(s) for {$result['days']} day(s). "
            .($result['alerts'] ? "{$result['alerts']} unexplained absence(s) found: see the dashboard." : 'No unexplained absences found.');

        return back()->with($result['skipped'] ? 'error' : 'success', $result['skipped']
            ? $message.' Some rows were skipped: '.implode(' ', array_slice($result['skipped'], 0, 3)).(count($result['skipped']) > 3 ? ' …' : '')
            : $message);
    }

    /** "Worked – clock-in missed". */
    public function worked(Request $request, int $alert): RedirectResponse
    {
        $a = UnexplainedAbsence::open()->where('business_id', $request->user()->business_id)->with('employee:id,full_name')->findOrFail($alert);
        $this->unexplained->markWorked($a, $request->user());

        return back()->with('success', "{$a->employee->full_name} worked on {$a->date->format('j M Y')}: noted. Nothing goes in the absence log.");
    }
}
