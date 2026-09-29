<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KeyPerson;
use App\Models\WorkSite;
use App\Services\EmployeeRecorder;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Settings: business details, plan, and work sites (add, rename, close, move an employee). */
class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $sites = $business->workSites()->with(['employees' => fn ($q) => $q->current()->orderBy('full_name')])->orderByRaw('closed_on is not null')->orderBy('name')->get();
        $employees = $business->employees()->current()->orderBy('full_name')->get(['id', 'full_name', 'work_site_id']);

        return Inertia::render('App/Settings/Index', [
            'business' => [
                'name' => $business->name,
                'licence' => $business->licence_number,
                'admins' => $business->admins()->pluck('email'),
            ],
            'people' => $business->keyPersonnel()->orderByRaw("field(role, 'authorising_officer', 'key_contact', 'level1_user')")->orderBy('name')->get()
                ->map(fn (KeyPerson $p) => ['id' => $p->id, 'role' => $p->role, 'roleLabel' => $p->roleLabel(), 'name' => $p->name, 'email' => $p->email, 'phone' => $p->phone]),
            'roles' => collect(KeyPerson::ROLES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label, 'single' => in_array($value, KeyPerson::SINGLE_ROLES, true)])->values(),
            'plan' => [
                'price' => number_format($business->plan_price_pence / 100, 2),
                'limit' => $business->employee_limit,
                'used' => $employees->count(),
                'nextPayment' => Employee::formatDate($business->next_payment_on),
                'method' => $business->payment_label,
            ],
            'sites' => $sites->map(fn (WorkSite $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'address' => $s->address,
                'closed' => Employee::formatDate($s->closed_on),
                'staff' => $s->employees->pluck('full_name'),
            ]),
            'employees' => $employees->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name, 'siteId' => $e->work_site_id]),
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $data = $this->validateSite($request);
        $site = $request->user()->business->workSites()->create($data);
        Audit::log('work_site.created', $site, ['name' => $site->name]);

        return back()->with('success', "{$site->name} added. Remember to add the new work address on the Sponsor Management System.");
    }

    public function updateSite(Request $request, int $site): RedirectResponse
    {
        $site = $request->user()->business->workSites()->findOrFail($site);
        $site->fill($this->validateSite($request));
        if ($site->isDirty()) {
            $meta = collect($site->getDirty())->map(fn ($new, $field) => ['from' => $site->getOriginal($field), 'to' => $new])->all();
            $site->save();
            Audit::log('work_site.updated', $site, $meta);
        }

        return back()->with('success', "{$site->name} saved.");
    }

    public function closeSite(Request $request, int $site): RedirectResponse
    {
        $site = $request->user()->business->workSites()->open()->findOrFail($site);
        if ($site->employees()->current()->exists()) {
            return back()->with('error', "Move everyone who works at {$site->name} to another site before closing it.");
        }
        $site->update(['closed_on' => today()]);
        Audit::log('work_site.closed', $site, ['name' => $site->name]);

        return back()->with('success', "{$site->name} closed. Remember to remove the work address on the Sponsor Management System.");
    }

    public function moveEmployee(Request $request, EmployeeRecorder $recorder): RedirectResponse
    {
        $business = $request->user()->business;
        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('business_id', $business->id)->whereNull('ended_on')],
            'work_site_id' => ['required', Rule::exists('work_sites', 'id')->where('business_id', $business->id)->whereNull('closed_on')],
        ], ['employee_id.*' => 'Choose an employee.', 'work_site_id.*' => 'Choose one of your open work sites.']);

        $employee = $business->employees()->findOrFail($data['employee_id']);
        if ((int) $employee->work_site_id === (int) $data['work_site_id']) {
            return back()->withErrors(['work_site_id' => "{$employee->full_name} already works at that site."]);
        }
        $recorder->update($employee, ['work_site_id' => (int) $data['work_site_id']], $request->user());

        return back()->with('success', "{$employee->full_name} moved and logged in their change history."
            .($employee->isSponsored() ? ' Sponsored worker: report the new work location on the Sponsor Management System.' : ''));
    }

    public function storePerson(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $data = $this->validatePerson($request);
        if (in_array($data['role'], KeyPerson::SINGLE_ROLES, true) && $business->keyPersonnel()->where('role', $data['role'])->exists()) {
            return back()->withErrors(['role' => 'There is already an '.KeyPerson::ROLES[$data['role']].'. Change that entry instead.']);
        }
        $person = $business->keyPersonnel()->create($data);
        Audit::log('key_person.added', $person, ['role' => $person->role, 'name' => $person->name]);

        return back()->with('success', $person->roleLabel().' added. Update the Sponsor Management System to match.');
    }

    public function updatePerson(Request $request, int $person): RedirectResponse
    {
        $person = $request->user()->business->keyPersonnel()->findOrFail($person);
        $person->fill(collect($this->validatePerson($request))->except('role')->all());
        if ($person->isDirty()) {
            $meta = collect($person->getDirty())->map(fn ($new, $field) => ['from' => $person->getOriginal($field), 'to' => $new])->all();
            $person->save();
            Audit::log('key_person.changed', $person, $meta);
        }

        return back()->with('success', $person->roleLabel().' saved. Update the Sponsor Management System to match.');
    }

    public function destroyPerson(Request $request, int $person): RedirectResponse
    {
        $person = $request->user()->business->keyPersonnel()->findOrFail($person);
        $person->delete();
        Audit::log('key_person.removed', $person, ['role' => $person->role, 'name' => $person->name]);

        return back()->with('success', "{$person->name} removed as ".$person->roleLabel().'. Update the Sponsor Management System to match.');
    }

    private function validatePerson(Request $request): array
    {
        return $request->validate([
            'role' => ['required', Rule::in(array_keys(KeyPerson::ROLES))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], [], ['role' => 'role']);
    }

    private function validateSite(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ], [], ['name' => 'site name', 'address' => 'full address and postcode']);
    }
}
