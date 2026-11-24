<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Business;
use App\Services\ActivityLog;
use App\Support\Table;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Super admin Activity log: the audit log across every business and the super admin area, newest first. */
class ActivityController extends Controller
{
    public function __invoke(Request $request, ActivityLog $activity): Response
    {
        $businesses = Business::orderBy('name')->get(['id', 'name']);
        $table = Table::from($request, sorts: ['when' => 'audit_logs.id'], default: 'when', dir: 'desc')
            ->filters(['type' => array_keys(ActivityLog::types(ops: true)), 'business' => $businesses->pluck('id')->map(fn ($id) => (string) $id)->all()]);

        $query = AuditLog::query();
        if ($business = $table->filter('business')) {
            $query->where('business_id', (int) $business);
        }
        $activity->applyType($query, $table->filter('type'), ops: true);
        $activity->applyDates($query, $table->date('from'), $table->date('to'));
        $activity->search($query, $table->state()['q'], null);

        $page = $table->paginate($query, fn (AuditLog $l) => $l);
        $page['data'] = $activity->rows($page['data'], ops: true)->values();

        return Inertia::render('Ops/Activity', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'entries' => $page,
            'table' => $table->state(),
            'types' => collect(ActivityLog::types(ops: true))->map(fn ($t, $k) => ['value' => $k, 'label' => $t[0]])->values(),
            'businesses' => $businesses->map(fn (Business $b) => ['value' => (string) $b->id, 'label' => $b->name])->values(),
        ]);
    }
}
