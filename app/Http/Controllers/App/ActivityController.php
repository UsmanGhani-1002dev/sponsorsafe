<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\ActivityLog;
use App\Support\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Settings → Activity log: who did what in this business, newest first (read from the audit log). */
class ActivityController extends Controller
{
    public function __invoke(Request $request, ActivityLog $activity): Response
    {
        $business = $request->user()->business;
        $table = Table::from($request, sorts: ['when' => 'audit_logs.id'], default: 'when', dir: 'desc')
            ->filters(['type' => array_keys(ActivityLog::TYPES), 'who' => array_keys(ActivityLog::WHO)]);

        $query = AuditLog::query()->where('business_id', $business->id);
        $activity->applyType($query, $table->filter('type'));
        $activity->applyWho($query, $table->filter('who'), $business);
        $activity->applyDates($query, $table->date('from'), $table->date('to'));
        $activity->search($query, $table->state()['q'], $business);

        $page = $table->paginate($query, fn (AuditLog $l) => $l);
        $page['data'] = $activity->rows($page['data'])->values();

        return Inertia::render('App/Activity/Index', [
            'entries' => $page,
            'table' => $table->state(),
            'types' => collect(ActivityLog::TYPES)->map(fn ($t, $k) => ['value' => $k, 'label' => $t[0]])->values(),
            'who' => collect(ActivityLog::WHO)->map(fn ($label, $k) => ['value' => $k, 'label' => $label])->values(),
        ]);
    }
}
