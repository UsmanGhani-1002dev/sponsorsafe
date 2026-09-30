<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The compliance pack for one worker: a single PDF with their details, the compliance check, the
 * document list, absence log, change history and Home Office reports — what a Home Office compliance
 * visit asks to see. Secrets are shown as the last 4 characters only.
 */
class CompliancePack
{
    public function __construct(private ComplianceCheck $check) {}

    public function data(Employee $e, User $by): array
    {
        $e->loadMissing(['workSite', 'documents.uploader', 'absences' => fn ($q) => $q->orderBy('start_date'), 'changes.changedBy',
            'reportTasks' => fn ($q) => $q->orderBy('trigger_on')]);
        $d = fn ($date) => $date?->format('j M Y') ?? '—';
        $wd = WorkingDays::fromDatabase();
        $rows = $this->check->rows($e);

        return [
            'business' => $e->business,
            'employee' => $e,
            'generated' => now()->format('j M Y, H:i'),
            'by' => $by->name,
            'summary' => ComplianceCheck::summary($rows)['text'],
            'details' => [
                'Right to work' => [
                    'Right-to-work basis' => $e->rtw_basis->label(),
                    'Nationality' => $e->nationality ?? '—',
                    'Visa / status' => $e->rtw_basis->timeLimited() ? ($e->visa_type ?? '—') : 'No time limit',
                    'Visa / permission' => $e->rtw_basis->timeLimited() ? $d($e->visa_start).' to '.$d($e->visa_expiry) : '—',
                    'Work restrictions' => $e->work_restrictions ?? '—',
                    'Share code' => $e->masked('share_code') ?? '—',
                    'Check' => $e->rtw_check_method.', '.$d($e->rtw_check_date).', by '.$e->rtw_checked_by,
                    'Follow-up check due' => $e->rtw_basis->timeLimited() ? $d($e->follow_up_check_due) : 'Not required',
                ],
                $e->isSponsored() ? 'Sponsorship and job (must match the CoS)' : 'Job' => array_filter([
                    'CoS number' => $e->isSponsored() ? $e->cos_number : null,
                    'CoS assigned' => $e->isSponsored() ? $d($e->cos_assigned_on) : null,
                    'SOC code' => $e->isSponsored() ? $e->soc_code : null,
                    'Job title' => $e->job_title,
                    'Salary' => Employee::displayValue('salary', $e->salary) ?? '—',
                    'Working pattern' => Employee::displayValue('days_per_week', $e->days_per_week).' per week, '.Employee::displayValue('contracted_hours', $e->contracted_hours).' per week',
                    'Contract type' => $e->contract_type,
                    'Work site' => $e->workSite ? $e->workSite->name.', '.$e->workSite->address : '—',
                    'Start date' => $d($e->start_date),
                    'Employment ended' => $e->ended_on ? $d($e->ended_on).' ('.$e->end_reason.')' : 'Still employed',
                ], fn ($v) => $v !== null),
                'Personal and contact' => [
                    'Date of birth' => $d($e->date_of_birth),
                    'Home address' => $e->address ?? '—',
                    'Phone' => $e->phone ?? '—',
                    'Email' => $e->email,
                    'National Insurance number' => $e->masked('ni_number') ?? '—',
                    'Passport' => ($e->masked('passport_number') ?? '—').($e->passport_expiry ? ', expires '.$d($e->passport_expiry) : ''),
                ],
            ],
            'check' => array_map(fn ($r) => [...$r, 'badge' => ComplianceCheck::badge($r['status'])['text']], $rows),
            'documents' => $e->documents->reject->isPendingReview()->sortBy(fn ($doc) => $doc->category->label())->map(fn ($doc) => [
                'category' => $doc->category->label(), 'name' => $doc->original_name, 'uploaded' => $d($doc->created_at),
                'by' => $doc->uploaded_via === 'portal' ? 'Employee (portal)' : ($doc->uploader?->name ?? '—'), 'expires' => $d($doc->expires_on),
            ])->values(),
            'absences' => $e->absences->map(fn ($a) => [
                'type' => $a->type->label(), 'dates' => $a->dates(), 'days' => $a->working_days, 'pay' => $a->type->pay(), 'reason' => $a->reason ?? '—',
            ]),
            'changes' => $e->changes->sortBy('created_at')->map(fn ($c) => [
                'date' => $d($c->created_at), 'label' => $c->label, 'from' => $c->old_value ?? '—', 'to' => $c->new_value ?? '—', 'by' => $c->changedBy?->name ?? 'System',
            ])->values(),
            'tasks' => $e->reportTasks->map(fn ($t) => [
                'event' => $t->event, 'trigger' => $d($t->trigger_on), 'deadline' => $d($t->deadline),
                'status' => $t->badge($wd)['text'], 'done' => $t->doneText(),
            ]),
        ];
    }

    public function download(Employee $e, User $by): \Illuminate\Http\Response
    {
        $name = 'compliance-pack-'.str($e->full_name)->slug().'-'.now()->format('Y-m-d').'.pdf';

        return Pdf::loadView('pdf.compliance-pack', $this->data($e, $by))->setOption('isFontSubsettingEnabled', true)->setPaper('a4')->download($name);
    }
}
