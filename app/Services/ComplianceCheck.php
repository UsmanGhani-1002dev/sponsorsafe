<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\Employee;
use App\Models\ReportTask;
use Illuminate\Support\Carbon;

/**
 * Compliance check for one employee (compliance-rules §8): everything the law and the sponsor duties
 * expect to be on file. Each row is Done, Check (due soon or needs attention), Missing, or Manual check.
 *
 * Needs the employee's documents, reportTasks and business loaded.
 */
class ComplianceCheck
{
    public const DONE = 'done';
    public const CHECK = 'check';
    public const MISSING = 'missing';
    public const MANUAL = 'manual';

    public function __construct(private WorkingDays $wd) {}

    /** @return list<array{key: string, label: string, detail: string, status: string}> */
    public function rows(Employee $e, ?Carbon $today = null): array
    {
        $today ??= today();
        $rows = [];
        $add = function (string $key, string $label, string $status, string $detail) use (&$rows) {
            $rows[] = ['key' => $key, 'label' => $label, 'detail' => $detail, 'status' => $status];
        };
        $docs = $e->documents->reject->isPendingReview();
        $has = fn (DocumentCategory $c) => $docs->contains(fn (Document $d) => $d->category === $c);
        $date = fn (?Carbon $d) => $d?->format('j M Y');

        // Right-to-work check done, and on or before the first day.
        $checkedInTime = $e->rtw_check_date && $e->rtw_check_date->lte($e->start_date);
        $add('rtw', 'Right-to-work check before the first day', $has(DocumentCategory::RightToWork) && $checkedInTime ? self::DONE : self::MISSING, match (true) {
            ! $has(DocumentCategory::RightToWork) => 'No check result on file. Upload it under Documents.',
            ! $checkedInTime => 'The check date ('.$date($e->rtw_check_date).') is after the start date ('.$date($e->start_date).').',
            default => 'Checked '.$date($e->rtw_check_date).' by '.$e->rtw_checked_by.' ('.$e->rtw_check_method.')',
        });

        // Follow-up check before time-limited permission ends.
        if ($e->rtw_basis->timeLimited() && ! $e->ended_on && $e->follow_up_check_due) {
            $days = (int) $today->diffInDays($e->follow_up_check_due, false);
            $add('follow_up', 'Follow-up right-to-work check', match (true) {
                $days < 0 => self::MISSING,
                $days <= 90 => self::CHECK,
                default => self::DONE,
            }, $days < 0 ? 'Overdue since '.$date($e->follow_up_check_due).'. Their permission may have ended.' : 'Due by '.$date($e->follow_up_check_due).($days <= 90 ? " ({$days} days)" : ''));
        }

        $add('passport', 'Passport / ID copy on file', $has(DocumentCategory::Passport) ? self::DONE : self::MISSING,
            $has(DocumentCategory::Passport) ? 'On file' : 'Request it from the employee under Documents.');

        $contact = $e->address && $e->phone && $e->email;
        $add('contact', 'Current UK address and contact details', $contact ? self::DONE : self::MISSING,
            $contact ? 'Employees keep these up to date through the portal.' : 'Missing '.implode(' and ', array_filter([! $e->address ? 'home address' : null, ! $e->phone ? 'phone' : null])).'.');

        $add('contract', 'Contract of employment on file', $has(DocumentCategory::Contract) ? self::DONE : self::MISSING,
            $has(DocumentCategory::Contract) ? 'On file' : 'Upload it under Documents.');

        if ($e->isSponsored()) {
            $add('cos', 'Certificate of Sponsorship on file', $has(DocumentCategory::Cos) ? self::DONE : self::MISSING,
                $has(DocumentCategory::Cos) ? 'On file (CoS '.$e->cos_number.')' : 'Upload it under Documents.');
            $add('jd', 'Job description matches the CoS', $has(DocumentCategory::JobDescription) ? self::DONE : self::MISSING,
                $has(DocumentCategory::JobDescription) ? "On file: job title {$e->job_title}, SOC {$e->soc_code}" : 'Upload it under Documents.');
            $add('recruit', 'Recruitment evidence kept', $has(DocumentCategory::Recruitment) ? self::DONE : self::MISSING,
                $has(DocumentCategory::Recruitment) ? 'Advert and interview notes on file' : 'Keep the job advert, shortlist and interview notes.');

            $payslip = $docs->filter(fn (Document $d) => $d->category === DocumentCategory::Payroll)->sortByDesc('created_at')->first();
            $fresh = (int) $e->business->rule('payslip_freshness_days');
            $age = $payslip ? (int) $payslip->created_at->startOfDay()->diffInDays($today) : null;
            $add('payslip', "Payslip uploaded in the last {$fresh} days", match (true) {
                ! $payslip => self::MISSING,
                $age > $fresh => self::CHECK,
                default => self::DONE,
            }, $payslip ? 'Latest uploaded '.$date($payslip->created_at) : 'Upload the payslips your accountant sends, every month.');

            $add('pay', 'Pay matches the CoS salary', self::MANUAL,
                'Compare each payslip with the CoS salary ('.Employee::displayValue('salary', $e->salary).'). Sponsorship costs must not be deducted from pay.');

            $pending = $e->reportTasks->filter->isPending();
            $overdue = $pending->filter(fn (ReportTask $t) => $this->wd->until($today->format('Y-m-d'), $t->deadline->format('Y-m-d')) < 0)->count();
            $add('reports', 'Home Office reports up to date', match (true) {
                $pending->isEmpty() => self::DONE,
                $overdue > 0 => self::MISSING,
                default => self::CHECK,
            }, match (true) {
                $pending->isEmpty() => 'Nothing pending',
                $overdue > 0 => "{$overdue} overdue. See the Home Office tab.",
                default => $pending->count().' pending. See the Home Office tab.',
            });
        }

        return $rows;
    }

    /** Header badge: "Compliance: all done" (green), "N to fix" (amber, or red when anything is missing). */
    public static function summary(array $rows): array
    {
        $missing = collect($rows)->where('status', self::MISSING)->count();
        $issues = $missing + collect($rows)->where('status', self::CHECK)->count();

        return [
            'text' => 'Compliance: '.($issues ? "{$issues} to fix" : 'all done'),
            'tone' => $missing ? 'red' : ($issues ? 'amber' : 'green'),
        ];
    }

    public static function badge(string $status): array
    {
        return match ($status) {
            self::DONE => ['text' => 'Done', 'tone' => 'green'],
            self::CHECK => ['text' => 'Check', 'tone' => 'amber'],
            self::MISSING => ['text' => 'Missing', 'tone' => 'red'],
            default => ['text' => 'Manual check', 'tone' => 'blue'],
        };
    }
}
