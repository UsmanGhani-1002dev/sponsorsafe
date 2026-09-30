<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\EmployeeRequest;
use App\Services\EmployeeRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Requests inbox (compliance-rules §6): approve or decline what employees send from the portal. */
class RequestController extends Controller
{
    public function __construct(private EmployeeRequests $requests) {}

    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $with = ['employee.business', 'document'];
        $row = fn (EmployeeRequest $r) => [
            'id' => $r->id,
            'employeeId' => $r->employee_id,
            'who' => $r->employee->full_name,
            'kind' => $r->kind === 'document' && $r->document_request_id ? 'Requested document' : $r->kindLabel(),
            'summary' => $r->summary(),
            'sent' => $r->created_at->format('j M Y'),
            'note' => $r->note,
            'documentId' => $r->document?->id,
            'status' => $r->statusBadge(),
            'hrNote' => $r->hr_note,
        ];

        $pending = EmployeeRequest::with($with)->where('business_id', $business->id)->pending()->oldest()->get();
        foreach ($pending as $r) {
            // The employee on each request shares the business already loaded (no extra queries per row).
            $r->employee->setRelation('business', $business);
        }

        return Inertia::render('App/Requests/Index', [
            'waiting' => $pending->map(fn ($r) => [...$row($r), 'preview' => $this->requests->preview($r)]),
            'awaiting' => DocumentRequest::with('employee')->where('business_id', $business->id)->where('status', DocumentRequest::STATUS_AWAITING)->oldest()->get()
                ->map(fn (DocumentRequest $d) => ['id' => $d->id, 'employeeId' => $d->employee_id, 'who' => $d->employee->full_name, 'summary' => $d->category->label(), 'since' => $d->created_at->format('j M Y')]),
            'decided' => EmployeeRequest::with($with)->where('business_id', $business->id)->where('status', '!=', EmployeeRequest::PENDING)
                ->latest('decided_at')->limit(15)->get()->map($row),
        ]);
    }

    public function approve(Request $request, int $employeeRequest): RedirectResponse
    {
        $r = $this->find($request, $employeeRequest);
        $note = $request->validate(['hr_note' => ['nullable', 'string', 'max:300']])['hr_note'] ?? null;
        try {
            $message = $this->requests->approve($r, $request->user(), $note);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "{$r->employee->full_name}: {$message}");
    }

    public function decline(Request $request, int $employeeRequest): RedirectResponse
    {
        $r = $this->find($request, $employeeRequest);
        $note = $request->validate(['hr_note' => ['nullable', 'string', 'max:300']])['hr_note'] ?? null;
        try {
            $this->requests->decline($r, $request->user(), $note);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Declined. {$r->employee->full_name} can see your note in their portal.");
    }

    private function find(Request $request, int $id): EmployeeRequest
    {
        $business = $request->user()->business;
        $r = EmployeeRequest::with(['employee', 'document'])->where('business_id', $business->id)->findOrFail($id);
        $r->employee->setRelation('business', $business);

        return $r;
    }
}
