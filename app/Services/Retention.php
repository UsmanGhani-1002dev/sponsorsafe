<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use App\Support\DashboardCounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Retention (compliance-rules §10, product decisions §11). When employment ends, delete-after dates are set:
 * - delete_after (end + 1 year): sponsor records go — every document except right-to-work evidence,
 *   absences, requests and Home Office tasks.
 * - rtw_delete_after (end + 2 years): the rest goes — right-to-work evidence, change history, the
 *   employee record and their portal login.
 * Nothing is deleted automatically: the admin reviews the list and confirms each one.
 */
class Retention
{
    public const STEP_RECORDS = 'records';   // keep only right-to-work evidence and the core record
    public const STEP_ALL = 'all';           // delete everything

    /** Leavers with something due for deletion today, and what would go. */
    public static function due(Business $business): Collection
    {
        $today = today();

        return $business->employees()->whereNotNull('ended_on')
            ->where(fn ($q) => $q->where('delete_after', '<=', $today)->orWhere('rtw_delete_after', '<=', $today))
            ->withCount(['documents', 'absences', 'requests', 'reportTasks',
                'documents as rtw_documents_count' => fn ($q) => $q->where('category', DocumentCategory::RightToWork->value)])
            ->orderBy('ended_on')->get()
            ->map(fn (Employee $e) => ['employee' => $e, 'step' => self::step($e)])
            ->filter(fn ($row) => $row['step'] !== null)
            ->values();
    }

    /** Which deletion is due for this leaver, if any. The records step is skipped once it has been done. */
    public static function step(Employee $e): ?string
    {
        if (! $e->ended_on) {
            return null;
        }
        if ($e->rtw_delete_after?->lte(today())) {
            return self::STEP_ALL;
        }
        $leftover = ($e->documents_count ?? $e->documents()->count()) - ($e->rtw_documents_count ?? $e->documents()->where('category', DocumentCategory::RightToWork->value)->count())
            + ($e->absences_count ?? $e->absences()->count()) + ($e->requests_count ?? $e->requests()->count()) + ($e->report_tasks_count ?? $e->reportTasks()->count());

        return $e->delete_after?->lte(today()) && $leftover > 0 ? self::STEP_RECORDS : null;
    }

    /** Permanently delete what is due. Files are erased from disk. Returns what was deleted. */
    public static function purge(Employee $e, User $by): array
    {
        $step = self::step($e);
        abort_unless($step !== null, 422, 'Nothing is due for deletion for this person yet.');

        return DB::transaction(function () use ($e, $by, $step) {
            $documents = $e->documents()->when($step === self::STEP_RECORDS, fn ($q) => $q->where('category', '!=', DocumentCategory::RightToWork->value))->get();
            foreach ($documents as $doc) {
                Storage::disk('local')->delete($doc->path);
            }
            $counts = [
                'documents' => $documents->count(),
                'absences' => $e->absences()->count(),
                'requests' => $e->requests()->count(),
                'report_tasks' => $e->reportTasks()->count(),
            ];
            $e->documents()->whereKey($documents->modelKeys())->delete();
            $e->requests()->delete();
            $e->absences()->delete();
            $e->reportTasks()->delete();
            $e->documentRequests()->delete();

            if ($step === self::STEP_ALL) {
                $counts['change_history'] = $e->changes()->count();
                $user = $e->user;
                $e->delete(); // cascades change history
                $user?->delete();
            }

            // No personal data in the audit trail of a deletion: only the record id and the dates that allowed it.
            Audit::log($step === self::STEP_ALL ? 'retention.employee_deleted' : 'retention.records_deleted', null, [
                'employee_id' => $e->id, 'ended_on' => $e->ended_on->format('Y-m-d'),
                'delete_after' => $e->delete_after?->format('Y-m-d'), 'rtw_delete_after' => $e->rtw_delete_after?->format('Y-m-d'), 'deleted' => $counts,
            ], $by);
            DashboardCounts::forget($e->business_id);

            return ['step' => $step, 'counts' => $counts];
        });
    }
}
