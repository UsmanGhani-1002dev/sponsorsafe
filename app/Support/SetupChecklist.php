<?php

namespace App\Support;

use App\Enums\DocumentCategory;
use App\Models\Business;
use App\Models\Document;

/**
 * "Get started" on the dashboard: the five things a new business sets up before SponsorSafe can do its job.
 * Shown until every step is done, then it goes away by itself (and comes back if one is undone, e.g. a new
 * employee without a right-to-work check).
 */
class SetupChecklist
{
    /** @return array{steps: list<array{key: string, title: string, detail: string, done: bool, href: string, action: string}>, done: int, total: int}|null */
    public static function for(Business $business): ?array
    {
        $roles = $business->keyPersonnel()->pluck('role')->all();
        $missingRoles = collect(['authorising_officer' => 'Authorising Officer', 'key_contact' => 'Key Contact'])->except($roles);
        $sites = $business->workSites()->open()->count();
        $employees = $business->employees()->current()->count();
        $withCheck = $business->employees()->current()->whereHas('documents', fn ($q) => $q->where('category', DocumentCategory::RightToWork->value)
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', '!=', Document::PENDING_REVIEW)))->count();

        $steps = [
            [
                'key' => 'details', 'title' => 'Add your business address',
                'detail' => 'Your registered or trading address and sponsor licence number, as they are on your licence.',
                'done' => filled($business->registered_address) && filled($business->licence_number), 'href' => '/app/settings#business', 'action' => 'Business details',
            ],
            [
                'key' => 'people', 'title' => 'Add your key personnel',
                'detail' => $missingRoles->isEmpty() ? 'Authorising Officer and Key Contact added.' : 'Still needed: '.$missingRoles->implode(' and ').', as named on your sponsor licence.',
                'done' => $missingRoles->isEmpty(), 'href' => '/app/settings#people', 'action' => 'Key personnel',
            ],
            [
                'key' => 'sites', 'title' => 'Add your work sites',
                'detail' => 'Every address where your staff work. Each employee is linked to one.',
                'done' => $sites > 0, 'href' => '/app/settings#sites', 'action' => 'Work sites',
            ],
            [
                'key' => 'employees', 'title' => 'Add your employees',
                'detail' => 'Everyone you employ, not only sponsored workers: the Home Office expects right-to-work records for all staff.',
                'done' => $employees > 0, 'href' => '/app/employees/create', 'action' => 'Add employee',
            ],
            [
                'key' => 'rtw', 'title' => "Upload each employee's right-to-work check",
                'detail' => $employees === 0 ? 'Once employees are added, upload the result of each right-to-work check under their Documents tab.'
                    : "{$withCheck} of {$employees} ".($employees === 1 ? 'employee has' : 'employees have').' their right-to-work check uploaded.',
                'done' => $employees > 0 && $withCheck === $employees, 'href' => '/app/employees', 'action' => 'Employees',
            ],
        ];
        $done = count(array_filter($steps, fn ($s) => $s['done']));

        return $done === count($steps) ? null : ['steps' => $steps, 'done' => $done, 'total' => count($steps)];
    }
}
