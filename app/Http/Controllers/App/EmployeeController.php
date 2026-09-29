<?php

namespace App\Http\Controllers\App;

use App\Enums\AbsenceType;
use App\Enums\ChangeType;
use App\Enums\DocumentCategory;
use App\Enums\RightToWorkBasis;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Services\AbsenceRules;
use App\Services\EmployeeRecorder;
use App\Services\EmployeeRules;
use App\Services\PasswordLinks;
use App\Services\WorkingDays;
use App\Support\Badges;
use App\Support\Table;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeRecorder $recorder, private PasswordLinks $links) {}

    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $table = Table::from($request, sorts: ['name' => 'full_name', 'job' => 'job_title', 'start' => 'start_date', 'expiry' => 'visa_expiry'], default: 'name')
            ->filters(['status' => ['current', 'left', 'all'], 'basis' => array_column(RightToWorkBasis::cases(), 'value')]);

        $query = $business->employees()->with(['workSite', 'user', 'documents:id,employee_id,category']);
        $table->search($query, ['full_name', 'job_title', 'email']);
        match ($table->filter('status', 'current')) {
            'current' => $query->current(),
            'left' => $query->whereNotNull('ended_on'),
            default => null,
        };
        if ($basis = $table->filter('basis')) {
            $query->where('rtw_basis', $basis);
        }
        $page = $table->paginate($query, fn (Employee $e) => [
            'id' => $e->id,
            'name' => $e->full_name,
            'site' => $e->workSite?->name,
            'jobTitle' => $e->job_title,
            'status' => $e->ended_on ? 'Left '.Employee::formatDate($e->ended_on) : $e->statusLabel(),
            'expiry' => $e->ended_on ? ['text' => 'Left', 'tone' => 'grey'] : Badges::expiry($e->visa_expiry),
            'portal' => $e->portalStatus(),
            'documents' => $e->documentsOnFile(),
        ]);

        return Inertia::render('App/Employees/Index', [
            'employees' => $page,
            'table' => $table->state(),
            'bases' => array_map(fn ($b) => ['value' => $b->value, 'label' => $b->label()], RightToWorkBasis::cases()),
            'plan' => $this->plan($business),
        ]);
    }

    public function create(Request $request): Response
    {
        $business = $request->user()->business;

        return Inertia::render('App/Employees/Create', [
            'options' => [
                'bases' => RightToWorkBasis::options(),
                'otherVisaTypes' => RightToWorkBasis::OTHER_VISA_TYPES,
                'checkMethods' => Employee::CHECK_METHODS,
                'contractTypes' => Employee::CONTRACT_TYPES,
                'nationalities' => Employee::NATIONALITIES,
                'sites' => $business->workSites()->open()->orderBy('name')->get(['id', 'name']),
                'requiredDocs' => [
                    'standard' => array_map(fn ($c) => $c->label(), DocumentCategory::requiredFor(false)),
                    'sponsored' => array_map(fn ($c) => $c->label(), DocumentCategory::requiredFor(true)),
                ],
            ],
            'plan' => $this->plan($business),
            'canFillDemo' => ! app()->isProduction(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        if ($business->employeeLimitReached()) {
            return back()->withErrors(['form' => "Your plan covers up to {$business->employee_limit} employees. Contact us to add more."]);
        }
        $invite = $request->boolean('portal_invite');
        $data = EmployeeRules::validate($request->except('portal_invite'), $business, $invite);
        $employee = $this->recorder->create($business, $data, $request->user(), $invite);

        return redirect()->route('app.employees.show', $employee)->with('success', $employee->full_name.' added.'
            .($invite ? " Portal invite sent to {$employee->email}, with a request to upload their passport / ID." : ''));
    }

    public function show(Request $request, int $employee): Response
    {
        $e = $this->find($request, $employee)->load([
            'workSite', 'user', 'changes.changedBy', 'documents.uploader', 'absences' => fn ($q) => $q->orderByDesc('start_date'),
            'documentRequests' => fn ($q) => $q->where('status', DocumentRequest::STATUS_AWAITING),
        ]);
        $e->absences->each->setRelation('employee', $e);

        return Inertia::render('App/Employees/Show', [
            'employee' => [
                'id' => $e->id,
                'name' => $e->full_name,
                'jobTitle' => $e->job_title,
                'site' => $e->workSite?->name,
                'start' => Employee::formatDate($e->start_date),
                'status' => $e->statusLabel(),
                'sponsored' => $e->isSponsored(),
                'expiry' => Badges::expiry($e->visa_expiry),
                'portal' => $e->portalStatus(),
                'email' => $e->email,
                'left' => $e->ended_on !== null,
                'documents' => $e->documentsOnFile(),
            ],
            'documents' => $this->documentCategories($e),
            'absence' => $this->absenceSummary($e),
            'upload' => ['maxMb' => config('sponsorsafe.documents.max_kb') / 1024, 'categories' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], DocumentCategory::cases())],
            'sections' => $this->sections($e),
            'history' => $e->changes->map(fn ($c) => [
                'id' => $c->id,
                'date' => Employee::formatDate($c->created_at),
                'label' => $c->label,
                'from' => $c->old_value,
                'to' => $c->new_value,
                'by' => $c->changedBy?->name ?? 'System',
                'reportable' => $e->isSponsored() && collect(ChangeType::cases())->contains(fn (ChangeType $t) => $t->label() === $c->label && $t->reportableIfSponsored()),
            ]),
            'waitingFor' => $e->documentRequests->map(fn (DocumentRequest $r) => [
                'label' => $r->category->label(),
                'since' => Employee::formatDate($r->created_at),
            ]),
            'changeTypes' => array_map(fn ($t) => [...$t, 'current' => Employee::displayValue($t['field'], $e->{$t['field']})], ChangeType::options($e->isSponsored())),
            'personal' => [
                'values' => [
                    'full_name' => $e->full_name,
                    'date_of_birth' => $e->date_of_birth?->format('Y-m-d') ?? '',
                    'nationality' => $e->nationality ?? '',
                    'ni_number' => '',
                    'passport_number' => '',
                    'passport_expiry' => $e->passport_expiry?->format('Y-m-d') ?? '',
                ],
                'masked' => ['ni_number' => $e->masked('ni_number'), 'passport_number' => $e->masked('passport_number')],
                'nationalities' => Employee::NATIONALITIES,
            ],
            'reportDeadlineDays' => (int) $e->business->rule('worker_report_deadline_days'),
        ]);
    }

    /** Correct a mistake in personal details. Logged, never reportable. */
    public function correct(Request $request, int $employee): RedirectResponse
    {
        $e = $this->find($request, $employee);
        $changes = $this->recorder->update($e, EmployeeRules::validatePersonal($request->all(), $e), $request->user());

        return back()->with('success', $changes
            ? 'Saved. '.count($changes).' '.str('correction')->plural(count($changes)).' logged in the history.'
            : 'Nothing changed.');
    }

    /** Record a change (§5). Tells HR straight away if a sponsored worker's change must be reported. */
    public function recordChange(Request $request, int $employee): RedirectResponse
    {
        $e = $this->find($request, $employee);
        [$type, $data] = EmployeeRules::validateChange($request->all(), $e);
        $changes = $this->recorder->update($e, $data, $request->user(), $type->label());

        if (! $changes) {
            return back()->withErrors(['value' => 'That is the same as the current value.']);
        }
        if ($e->isSponsored() && $type->reportableIfSponsored()) {
            $days = (int) $e->business->rule('worker_report_deadline_days');
            $deadline = Employee::formatDate(Carbon::parse(WorkingDays::fromDatabase()->add(today(), $days)));

            return back()->with('success', "Saved and logged. Sponsored worker: report this change on the Sponsor Management System by {$deadline} ({$days} working days).");
        }

        return back()->with('success', 'Saved and logged in the history. No Home Office report needed.');
    }

    public function invite(Request $request, int $employee): RedirectResponse
    {
        $e = $this->find($request, $employee);
        if ($e->ended_on) {
            return back()->with('error', 'This person has left, so they cannot have portal access.');
        }
        $this->links->invite($e, $request->user());

        return back()->with('success', "Set-password link sent to {$e->email}. It expires in ".PasswordLinks::INVITE_DAYS.' days.');
    }

    /** Employees are always looked up through the signed-in admin's business. */
    private function find(Request $request, int $id): Employee
    {
        $business = $request->user()->business;

        return $business->employees()->findOrFail($id)->setRelation('business', $business);
    }

    private function plan(Business $business): array
    {
        $used = $business->employees()->current()->count();

        return ['used' => $used, 'limit' => $business->employee_limit, 'reached' => $used >= $business->employee_limit];
    }

    /** Documents tab: every category with its status, files and any open request (§2). */
    private function documentCategories(Employee $e): array
    {
        $required = array_map(fn ($c) => $c->value, $e->requiredDocuments());

        return array_map(function (DocumentCategory $c) use ($e, $required) {
            $files = $e->documents->where('category', $c)->sortByDesc('created_at')->values();
            $request = $e->documentRequests->firstWhere('category', $c);
            $isRequired = in_array($c->value, $required, true);
            $status = match (true) {
                $files->isNotEmpty() => ['text' => 'On file', 'tone' => 'green'],
                $request !== null => ['text' => 'Requested from employee', 'tone' => 'amber'],
                $isRequired => ['text' => 'Missing', 'tone' => 'red'],
                default => ['text' => 'Optional', 'tone' => 'grey'],
            };

            return [
                'value' => $c->value,
                'label' => $c->label(),
                'required' => $isRequired,
                'status' => $status,
                'request' => $request ? ['id' => $request->id, 'since' => Employee::formatDate($request->created_at)] : null,
                'files' => $files->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->original_name,
                    'size' => $d->humanSize(),
                    'uploaded' => Employee::formatDate($d->created_at),
                    'by' => $d->uploaded_via === 'portal' ? $e->full_name.' (portal)' : ($d->uploader?->name ?? 'Unknown'),
                    'expiry' => $d->expires_on ? ['text' => 'Expires '.Badges::expiry($d->expires_on)['text'], 'tone' => Badges::expiry($d->expires_on)['tone']] : null,
                ])->all(),
            ];
        }, DocumentCategory::cases());
    }

    /** Absence tab: unpaid days against the limit, annual leave left and this person's absences. */
    private function absenceSummary(Employee $e): array
    {
        $year = (string) today()->year;
        $usage = AbsenceRules::forBusiness($e->business)->unpaidUsage(today()->format('Y-m-d'), (float) $e->days_per_week, $e->absences->map->forRules());
        // Statutory 5.6 weeks pro rata, capped at 28 days.
        $allowance = min(28, round((float) $e->business->rule('annual_leave_weeks') * (float) $e->days_per_week, 1));
        $taken = $e->absences->filter(fn ($a) => $a->type === AbsenceType::Annual && $a->start_date->format('Y') === $year)->sum('working_days');
        $num = fn ($n) => rtrim(rtrim(number_format((float) $n, 1, '.', ''), '0'), '.');

        return [
            'year' => $year,
            'unpaid' => ['used' => $usage['used'], 'limit' => $num($usage['limit'])],
            'annual' => ['allowance' => $num($allowance), 'taken' => $taken, 'left' => $num($allowance - $taken)],
            'rows' => $e->absences->map(fn ($a) => AbsenceController::row($a))->values(),
        ];
    }

    /** The Details tab, laid out as in the prototype. */
    private function sections(Employee $e): array
    {
        $f = fn (string $label, ?string $value, ?array $badge = null) => ['label' => $label, 'value' => $value ?: '—', 'badge' => $badge];
        $d = fn ($date) => Employee::formatDate($date);
        $basis = $e->rtw_basis;

        $rtw = [$f('Right-to-work basis', $basis->label()), $f('Nationality', $e->nationality)];
        if ($basis->timeLimited()) {
            array_push($rtw,
                $f('Visa / status', $e->visa_type),
                $f('Visa start date', $d($e->visa_start)),
                $f('Visa / permission expiry', $d($e->visa_expiry), Badges::expiryWarning($e->visa_expiry)),
                $f('Work restrictions', $e->work_restrictions ?: 'None recorded'),
            );
        } else {
            $rtw[] = $f('Permission', 'No time limit');
        }
        if ($basis->usesShareCode()) {
            $rtw[] = $f('Share code', $e->masked('share_code'));
        }
        array_push($rtw,
            $f('Check method', $e->rtw_check_method),
            $f('Check date', $d($e->rtw_check_date)),
            $f('Checked by', $e->rtw_checked_by),
            $f('Follow-up check due', $basis->timeLimited() ? $d($e->follow_up_check_due) : 'Not required', $basis->timeLimited() ? Badges::expiryWarning($e->follow_up_check_due) : null),
        );

        $job = [$f('Sponsored worker', $e->isSponsored() ? 'Yes – Skilled Worker' : 'No')];
        if ($e->isSponsored()) {
            array_push($job, $f('CoS number', $e->cos_number), $f('CoS assigned', $d($e->cos_assigned_on)), $f('SOC code', $e->soc_code));
        }
        array_push($job,
            $f('Job title', $e->job_title),
            $f($e->isSponsored() ? 'Salary on CoS' : 'Salary', Employee::displayValue('salary', $e->salary)),
            $f('Contracted hours', Employee::displayValue('contracted_hours', $e->contracted_hours).' per week'),
            $f('Work site', $e->workSite?->name),
        );

        $keep = (int) $e->business->rule('retention_years');
        $keepRtw = (int) $e->business->rule('rtw_retention_years');
        $years = fn (int $n) => $n.' '.str('year')->plural($n);
        $portal = match ($e->portalStatus()) {
            'active' => 'Active',
            'invited' => 'Invite sent '.$d($e->user->invited_at).', not signed in yet',
            default => 'No access',
        };

        return [
            ['title' => 'Right to work and immigration', 'fields' => $rtw],
            ['title' => $e->isSponsored() ? 'Sponsorship and job (must match the CoS)' : 'Job', 'fields' => $job],
            ['title' => 'Personal and contact', 'fields' => [
                $f('Date of birth', $d($e->date_of_birth)),
                $f('Home address', $e->address),
                $f('Phone', $e->phone),
                $f('Email', $e->email),
                $f('National Insurance number', $e->masked('ni_number')),
                $f('Passport number', $e->masked('passport_number')),
                $f('Passport expiry', $d($e->passport_expiry), Badges::expiryWarning($e->passport_expiry)),
            ]],
            ['title' => 'Employment and retention', 'fields' => [
                $f('Start date', $d($e->start_date)),
                $f('Working pattern', Employee::displayValue('days_per_week', $e->days_per_week).' per week'),
                $f('Contract type', $e->contract_type),
                $f('Employment end date', $e->ended_on ? $d($e->ended_on).' ('.$e->end_reason.')' : 'Still employed'),
                $f('Employee portal', $portal),
                $f('Delete records after', $e->ended_on
                    ? $d($e->ended_on->copy()->addYears($keep)).' (right-to-work evidence: '.$d($e->ended_on->copy()->addYears($keepRtw)).')'
                    : 'End date + '.$years($keep).' (right-to-work evidence: + '.$years($keepRtw).')'),
            ]],
        ];
    }
}
