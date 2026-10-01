<?php

namespace App\Http\Controllers\App;

use App\Enums\AbsenceType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KeyPerson;
use App\Models\ReportTask;
use App\Services\ReportTasks;
use App\Services\Retention;
use App\Services\WorkingDays;
use App\Models\WorkSite;
use App\Services\EmployeeRecorder;
use App\Billing\PayPalException;
use App\Billing\PayPalGateway;
use App\Billing\StripeGateway;
use App\Billing\Subscriptions;
use App\Models\Business;
use App\Support\Audit;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Settings: business details, plan, and work sites (add, rename, close, move an employee). */
class SettingsController extends Controller
{
    public function index(Request $request, Subscriptions $subscriptions): Response
    {
        $business = $request->user()->business;
        $sites = $business->workSites()->with(['employees' => fn ($q) => $q->current()->orderBy('full_name'), 'reportTasks' => fn ($q) => $q->latest('id')])->orderByRaw('closed_on is not null')->orderBy('name')->get();
        $wd = WorkingDays::fromDatabase();
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
                'key' => $business->plan,
                'name' => $business->planName(),
                'options' => $subscriptions->planOptions($business, $employees->count()),
                'price' => number_format($business->plan_price_pence / 100, 2),
                'limit' => $business->employee_limit,
                'used' => $employees->count(),
                'nextPayment' => Employee::formatDate($business->next_payment_on),
                'method' => $business->payment_label,
                'graceEnds' => Employee::formatDate($business->grace_ends_on),
                'canManage' => self::canManageBilling($business),
                'provider' => $business->payment_provider,
                'priceChange' => $business->price_change_on ? ['on' => Employee::formatDate($business->price_change_on), 'price' => Pricing::pounds($business->price_change_pence), 'limit' => $business->price_change_limit] : null,
                'training' => Pricing::forDisplay()['training'],
            ],
            'sites' => $sites->map(fn (WorkSite $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'address' => $s->address,
                'closed' => Employee::formatDate($s->closed_on),
                'staff' => $s->employees->pluck('full_name'),
                'sms' => self::smsStatus($s, $wd),
            ]),
            'employees' => $employees->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name, 'siteId' => $e->work_site_id]),
            'retentionDue' => Retention::due($business)->count(),
            'rules' => [
                'values' => [
                    ...collect(['unpaid_limit_weeks', 'unpaid_leave_year', 'unauthorised_trigger_days', 'worker_report_deadline_days', 'company_report_deadline_days',
                        'self_cert_max_days', 'payslip_freshness_days', 'retention_years', 'rtw_retention_years', 'annual_leave_weeks'])
                        ->mapWithKeys(fn ($k) => [$k => (string) $business->rule($k)])->all(),
                    'exempt_absence_types' => array_values((array) $business->rule('exempt_absence_types')),
                    'expiry_alert_days' => implode(', ', (array) $business->rule('expiry_alert_days')),
                ],
                'reducedPayTypes' => array_values(array_map(fn ($t) => ['value' => $t->value, 'label' => $t->label()], array_filter(AbsenceType::cases(), fn ($t) => $t->mayReducePay()))),
            ],
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $data = $this->validateSite($request);
        $site = $request->user()->business->workSites()->create($data);
        Audit::log('work_site.created', $site, ['name' => $site->name]);
        $task = ReportTasks::forSite($site->setRelation('business', $request->user()->business), 'added', $request->user());

        return back()->with('success', "{$site->name} added. A Home Office report task was created: add the work address on the Sponsor Management System by {$task->deadline->format('j M Y')}.");
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
        $task = ReportTasks::forSite($site->setRelation('business', $request->user()->business), 'closed', $request->user());

        return back()->with('success', "{$site->name} closed. A Home Office report task was created: remove the work address on the Sponsor Management System by {$task->deadline->format('j M Y')}.");
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
        $change = $recorder->update($employee, ['work_site_id' => (int) $data['work_site_id']], $request->user())[0];
        $task = $change->report_task_id ? ReportTask::findOrFail($change->report_task_id) : null;

        return back()->with('success', "{$employee->full_name} moved and logged in their change history."
            .($task ? ' Sponsored worker: a Home Office report task was created, deadline '.$task->deadline->format('j M Y').'.' : ' Not a sponsored worker, so no Home Office report.'));
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
        $task = ReportTasks::forKeyPerson($person, 'added', $request->user());

        return back()->with('success', $person->roleLabel().' added. '.self::smsNote($task));
    }

    public function updatePerson(Request $request, int $person): RedirectResponse
    {
        $person = $request->user()->business->keyPersonnel()->findOrFail($person);
        $person->fill(collect($this->validatePerson($request))->except('role')->all());
        if ($person->isDirty()) {
            $meta = collect($person->getDirty())->map(fn ($new, $field) => ['from' => $person->getOriginal($field), 'to' => $new])->all();
            $person->save();
            Audit::log('key_person.changed', $person, $meta);

            return back()->with('success', $person->roleLabel().' saved. '.self::smsNote(ReportTasks::forKeyPerson($person, 'changed', $request->user())));
        }

        return back()->with('success', 'Nothing changed.');
    }

    public function destroyPerson(Request $request, int $person): RedirectResponse
    {
        $person = $request->user()->business->keyPersonnel()->findOrFail($person);
        $person->delete();
        Audit::log('key_person.removed', $person, ['role' => $person->role, 'name' => $person->name]);
        $task = ReportTasks::forKeyPerson($person, 'removed', $request->user());

        return back()->with('success', "{$person->name} removed as ".$person->roleLabel().'. '.self::smsNote($task));
    }

    /** "Listed on SMS", or "Add to SMS by …" while the site's Home Office task is pending. */
    private static function smsStatus(WorkSite $site, WorkingDays $wd): array
    {
        $task = $site->reportTasks->first();
        if ($task?->isPending()) {
            $tone = $task->badge($wd)['tone'];

            return ['text' => ($site->closed_on ? 'Remove from SMS by ' : 'Add to SMS by ').$task->deadline->format('j M Y'), 'tone' => $tone === 'blue' ? 'amber' : $tone, 'taskId' => $task->id];
        }

        return ['text' => $site->closed_on ? 'Removed from SMS' : 'Listed on SMS', 'tone' => $site->closed_on ? 'grey' : 'green', 'taskId' => null];
    }

    private static function smsNote(ReportTask $task): string
    {
        return 'A Home Office report task was created: update the Sponsor Management System by '.$task->deadline->format('j M Y').'.';
    }

    /**
     * Manage billing: Stripe's own portal (change card, invoices, cancel), then back to Settings.
     * PayPal has no per-merchant portal: PayPal customers go to their PayPal automatic payments page.
     */
    public function billing(Request $request, StripeGateway $stripe, PayPalGateway $paypal): HttpResponse
    {
        $business = $request->user()->business;
        if (! self::canManageBilling($business)) {
            return back()->with('error', 'Online billing is not set up for this subscription. Please contact us to change payment details.');
        }
        if ($business->payment_provider === 'paypal') {
            Audit::log('billing.portal_opened', $business, ['provider' => 'paypal'], businessId: $business->id);

            return Inertia::location($paypal->manageUrl());
        }
        try {
            $url = $stripe->portalUrl($business, route('app.settings'));
        } catch (ApiErrorException $e) {
            Log::warning('Stripe billing portal failed', ['business' => $business->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'We could not open billing just now. Please try again in a moment.');
        }
        Audit::log('billing.portal_opened', $business, ['provider' => 'stripe'], businessId: $business->id);

        return Inertia::location($url);
    }

    /**
     * Switch between Starter and Standard. The limit changes now; the new price applies from the next
     * payment. PayPal customers approve the new price on PayPal first and come back to paypalPlan().
     */
    public function changePlan(Request $request, Subscriptions $subscriptions): HttpResponse
    {
        $business = $request->user()->business;
        $data = $request->validate(['plan' => ['required', Rule::in(array_keys(Pricing::TIERS))]]);
        $option = collect($subscriptions->planOptions($business, $business->currentEmployeeCount()))->firstWhere('key', $data['plan']);
        if (! $option['allowed']) {
            return back()->with('error', $option['reason'] ?? "You're already on {$option['name']}.");
        }

        try {
            $approve = $subscriptions->changePlan($business, $data['plan'], route('app.plan.paypal', ['plan' => $data['plan']]), route('app.settings'));
        } catch (PayPalException|ApiErrorException $e) {
            Log::warning('Plan change failed', ['business' => $business->id, 'error' => $e->getMessage()]);

            return back()->with('error', 'We could not change your plan just now. Please try again in a moment.');
        }
        if ($approve) {
            return Inertia::location($approve);
        }

        return back()->with('success', "You're now on {$option['name']}: up to {$option['limit']} employees. The new price, £{$option['price']} a month, applies from your next payment.");
    }

    /** Back from approving a plan change on PayPal. Applied only once PayPal shows the new plan. */
    public function paypalPlan(Request $request, Subscriptions $subscriptions): RedirectResponse
    {
        $business = $request->user()->business;
        $tier = $request->query('plan');
        if (! in_array($tier, array_keys(Pricing::TIERS), true) || $business->payment_provider !== 'paypal') {
            return redirect()->route('app.settings');
        }
        try {
            $done = $subscriptions->confirmPaypalPlan($business, $tier);
        } catch (PayPalException $e) {
            Log::warning('PayPal plan change could not be confirmed', ['business' => $business->id, 'error' => $e->getMessage()]);
            $done = false;
        }
        $tierInfo = Pricing::tier($tier);

        return redirect()->route('app.settings')->with($done ? 'success' : 'error', $done
            ? "You're now on ".Pricing::TIERS[$tier].": up to {$tierInfo['employee_limit']} employees. The new price applies from your next PayPal payment."
            : "PayPal hasn't confirmed the plan change yet. If you approved it, please check back in a few minutes.");
    }

    private static function canManageBilling(Business $business): bool
    {
        return match ($business->payment_provider) {
            'stripe' => filled($business->stripe_id),
            'paypal' => filled($business->paypal_subscription_id),
            default => false,
        };
    }

    /** Compliance rule settings (compliance-rules §9). Stored per business; defaults come from config. */
    public function updateRules(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $data = $request->validate([
            'unpaid_limit_weeks' => ['required', 'numeric', 'min:1', 'max:12'],
            'unpaid_leave_year' => ['required', Rule::in(['calendar', 'rolling'])],
            'unauthorised_trigger_days' => ['required', 'integer', 'min:1', 'max:30'],
            'worker_report_deadline_days' => ['required', 'integer', 'min:1', 'max:30'],
            'company_report_deadline_days' => ['required', 'integer', 'min:1', 'max:60'],
            'exempt_absence_types' => ['array'],
            'exempt_absence_types.*' => [Rule::in(array_map(fn ($t) => $t->value, array_filter(AbsenceType::cases(), fn ($t) => $t->mayReducePay())))],
            'self_cert_max_days' => ['required', 'integer', 'min:1', 'max:28'],
            'expiry_alert_days' => ['required', 'string', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'payslip_freshness_days' => ['required', 'integer', 'min:7', 'max:90'],
            'retention_years' => ['required', 'integer', 'min:1', 'max:10'],
            'rtw_retention_years' => ['required', 'integer', 'min:1', 'max:10'],
            'annual_leave_weeks' => ['required', 'numeric', 'min:0', 'max:10'],
        ], ['expiry_alert_days.regex' => 'Enter days as numbers separated by commas, like 90, 60, 30.']);

        $data['expiry_alert_days'] = collect(explode(',', $data['expiry_alert_days']))->map(fn ($d) => (int) trim($d))->unique()->sortDesc()->values()->all();
        $data['exempt_absence_types'] = array_values($data['exempt_absence_types'] ?? []);
        foreach (['unpaid_limit_weeks', 'annual_leave_weeks'] as $f) {
            $data[$f] = (float) $data[$f];
        }
        foreach (['unauthorised_trigger_days', 'worker_report_deadline_days', 'company_report_deadline_days', 'self_cert_max_days', 'payslip_freshness_days', 'retention_years', 'rtw_retention_years'] as $f) {
            $data[$f] = (int) $data[$f];
        }

        $changed = collect($data)->filter(fn ($v, $k) => $v != $business->rule($k))->map(fn ($v, $k) => ['from' => $business->rule($k), 'to' => $v])->all();
        if ($changed) {
            $business->update(['settings' => [...($business->settings ?? []), ...$data]]);
            Audit::log('business.rules_changed', $business, $changed);
        }

        return back()->with('success', $changed ? 'Compliance rules saved. New checks use them from now on.' : 'Nothing changed.');
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
