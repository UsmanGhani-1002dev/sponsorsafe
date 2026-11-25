<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Employee;
use App\Services\ComplianceCheck;

/**
 * The dashboard's "Compliance" card: every current employee's Compliance check (compliance-rules §8) added up.
 * Score = items done out of all items that can be checked (manual checks are left out). "Fix next" lists what is
 * missing first, then what needs checking, each linking to the right tab on the employee's profile.
 */
class ComplianceScore
{
    /** Where each Compliance check row is fixed on the employee's profile. */
    private const TABS = [
        'rtw' => 'docs', 'passport' => 'docs', 'contract' => 'docs', 'cos' => 'docs', 'jd' => 'docs', 'recruit' => 'docs', 'payslip' => 'docs',
        'reports' => 'reports', 'pay' => 'history',
    ];

    public const FIX_NEXT = 6;

    public function __construct(private ComplianceCheck $check) {}

    /** @return array{score: int, tone: string, toFix: int, employees: int, fixes: list<array>, people: list<array>}|null (null when there are no current employees) */
    public function for(Business $business): ?array
    {
        $employees = $business->employees()->current()->with(['documents', 'reportTasks'])->orderBy('full_name')->get()->each->setRelation('business', $business);
        if ($employees->isEmpty()) {
            return null;
        }

        [$done, $total, $fixes, $people] = [0, 0, collect(), collect()];
        foreach ($employees as $e) {
            $rows = collect($this->check->rows($e))->where('status', '!=', ComplianceCheck::MANUAL);
            $done += $rows->where('status', ComplianceCheck::DONE)->count();
            $total += $rows->count();
            foreach ($rows->whereIn('status', [ComplianceCheck::MISSING, ComplianceCheck::CHECK]) as $row) {
                $fixes->push([
                    'key' => "{$e->id}-{$row['key']}",
                    'name' => $e->full_name,
                    'label' => $row['label'],
                    'detail' => $row['detail'],
                    'badge' => ComplianceCheck::badge($row['status']),
                    'missing' => $row['status'] === ComplianceCheck::MISSING,
                    'href' => "/app/employees/{$e->id}?tab=".(self::TABS[$row['key']] ?? 'check'),
                ]);
            }
            $summary = ComplianceCheck::summary($rows->all());
            $people->push(['id' => $e->id, 'name' => $e->full_name, 'text' => str_replace('Compliance: ', '', $summary['text']), 'tone' => $summary['tone']]);
        }

        $missing = $fixes->where('missing', true)->count();

        return [
            'score' => $total ? (int) floor($done / $total * 100) : 100,
            'tone' => $missing ? 'red' : ($fixes->isNotEmpty() ? 'amber' : 'green'),
            'toFix' => $fixes->count(),
            'employees' => $employees->count(),
            // Missing first (stable within each group: employees in name order).
            'fixes' => $fixes->sortBy(fn ($f) => $f['missing'] ? 0 : 1)->take(self::FIX_NEXT)->values()->all(),
            'people' => $people->sortBy(fn ($p) => ['red' => 0, 'amber' => 1, 'green' => 2][$p['tone']] ?? 3)->values()->all(),
        ];
    }
}
