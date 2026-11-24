<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Employee;
use App\Models\KeyPerson;
use App\Models\ReportTask;
use App\Models\User;
use App\Models\WorkSite;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * "Prepare for a Home Office visit": one PDF for the whole business. A summary first (sponsor details, key personnel,
 * work sites, every current employee's compliance status, open Home Office reports), then each current employee's
 * compliance pack (sponsored workers first). Secrets are shown as the last 4 characters only, as in the single pack.
 */
class VisitPack
{
    public function __construct(private CompliancePack $pack, private ComplianceCheck $check) {}

    public function data(Business $business, User $by): array
    {
        $employees = $business->employees()->current()
            ->with(['workSite', 'documents.uploader', 'absences' => fn ($q) => $q->orderBy('start_date'), 'changes.changedBy', 'reportTasks' => fn ($q) => $q->orderBy('trigger_on')])
            ->orderBy('full_name')->get()
            ->each->setRelation('business', $business)
            ->sortBy(fn (Employee $e) => $e->isSponsored() ? 0 : 1)->values();
        $d = fn ($date) => $date?->format('j M Y') ?? '—';
        $wd = WorkingDays::fromDatabase();

        $overview = $employees->map(function (Employee $e) use ($d) {
            $rows = $this->check->rows($e);
            $summary = ComplianceCheck::summary($rows);

            return [
                'name' => $e->full_name,
                'job' => $e->job_title,
                'basis' => $e->statusLabel(),
                'expiry' => $e->rtw_basis->timeLimited() ? $d($e->visa_expiry) : 'No time limit',
                'checked' => $d($e->rtw_check_date),
                'status' => $summary['text'],
                'tone' => $summary['tone'],
                'fix' => collect($rows)->whereIn('status', [ComplianceCheck::MISSING, ComplianceCheck::CHECK])->pluck('label')->implode('; '),
            ];
        });

        return [
            'business' => $business,
            'generated' => now()->format('j M Y, H:i'),
            'by' => $by->name,
            'sponsor' => [
                'Business name' => $business->name,
                'Sponsor licence number' => $business->licence_number ?? '—',
                'Registered or trading address' => $business->registered_address ?? '—',
                'Phone' => $business->phone ?? '—',
            ],
            'people' => $business->keyPersonnel()->get()->sortBy(fn (KeyPerson $p) => array_search($p->role, array_keys(KeyPerson::ROLES), true))
                ->map(fn (KeyPerson $p) => ['role' => $p->roleLabel(), 'name' => $p->name, 'email' => $p->email ?? '—', 'phone' => $p->phone ?? '—'])->values(),
            'sites' => $business->workSites()->orderByRaw('closed_on is not null')->orderBy('name')->get()
                ->map(fn (WorkSite $s) => ['name' => $s->name, 'address' => $s->address, 'status' => $s->closed_on ? 'Closed '.$d($s->closed_on) : 'Open']),
            'counts' => [
                'employees' => $employees->count(),
                'sponsored' => $employees->filter->isSponsored()->count(),
                'allDone' => $overview->where('tone', 'green')->count(),
            ],
            'overview' => $overview,
            'tasks' => $business->reportTasks()->pending()->with('employee:id,full_name')->orderBy('deadline')->get()->map(fn (ReportTask $t) => [
                'event' => $t->event, 'who' => $t->employee?->full_name ?? 'The business', 'trigger' => $d($t->trigger_on), 'deadline' => $d($t->deadline), 'status' => $t->badge($wd)['text'],
            ]),
            'packs' => $employees->map(fn (Employee $e) => $this->pack->data($e, $by)),
        ];
    }

    public function download(Business $business, User $by): \Illuminate\Http\Response
    {
        $name = 'home-office-visit-pack-'.str($business->name)->slug().'-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('pdf.visit-pack', $this->data($business, $by))->setOption('isFontSubsettingEnabled', true)->setPaper('a4')->download($name);
    }
}
