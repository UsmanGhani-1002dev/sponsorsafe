<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\AbsenceRules;
use App\Services\Reminders;
use App\Services\Retention;
use App\Services\UnexplainedAbsences;
use App\Models\UnexplainedAbsence;
use App\Services\WorkingDays;
use App\Support\Badges;
use App\Support\DashboardCounts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Dashboard: stat tiles (cached briefly), right-to-work watchlist and the next Home Office deadlines. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, Reminders $reminders, UnexplainedAbsences $unexplained): Response
    {
        $business = $request->user()->business;
        $counts = DashboardCounts::for($business);
        $wd = WorkingDays::fromDatabase();
        $year = (string) today()->year;

        $watch = $business->employees()->current()->whereNotNull('visa_expiry')->orderBy('visa_expiry')
            ->with(['absences' => fn ($q) => $q->whereIn('type', ['unpaid', 'unauthorised'])->whereYear('end_date', '>=', (int) $year - 1)])
            ->get();
        $rules = AbsenceRules::forBusiness($business);

        return Inertia::render('App/Dashboard', [
            'business' => ['name' => $business->name, 'employees' => $counts['employees'], 'limit' => $business->employee_limit],
            'stats' => $counts,
            'watchlist' => $watch->map(function (Employee $e) use ($rules) {
                $usage = $rules->unpaidUsage(today()->format('Y-m-d'), (float) $e->days_per_week, $e->absences->map->forRules());

                return [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'status' => $e->statusLabel(),
                    'expiry' => Badges::expiry($e->visa_expiry),
                    'unpaid' => $usage['used'].' of '.rtrim(rtrim(number_format($usage['limit'], 1, '.', ''), '0'), '.'),
                ];
            }),
            'year' => $year,
            'retentionDue' => Retention::due($business)->count(),
            // §11: scheduled working days with no clock-in and no absence, waiting to be classified.
            'unexplained' => [
                'enabled' => UnexplainedAbsences::enabled($business),
                'items' => $unexplained->open($business)->map(fn (UnexplainedAbsence $u) => [
                    'id' => $u->id,
                    'name' => $u->employee->full_name,
                    'date' => $u->date->format('D j M Y'),
                    'badge' => $u->badge($wd, (int) $business->rule('unexplained_red_after_days')),
                ]),
            ],
            // Expiries and follow-up checks coming up (§12). Deadlines and deletions have their own cards.
            'reminders' => $reminders->current($business)
                ->whereIn('type', [Reminders::VISA, Reminders::FOLLOW_UP, Reminders::PASSPORT])
                ->take(8)
                ->map(fn ($i) => ['key' => "{$i['type']}-{$i['subject']}", 'text' => $i['text'], 'tone' => $i['tone'], 'href' => $i['href']])
                ->values(),
            'deadlines' => $business->reportTasks()->pending()->with('employee')->orderBy('deadline')->limit(5)->get()
                ->map(fn ($t) => ReportTaskController::row($t, $wd)),
        ]);
    }
}
