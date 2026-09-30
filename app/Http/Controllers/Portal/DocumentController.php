<?php

namespace App\Http\Controllers\Portal;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\EmployeeRequest;
use App\Services\DocumentVault;
use App\Services\EmployeeRequests;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** "My documents": what HR has asked for, what is on file, and sending another document. */
class DocumentController extends PortalController
{
    public function index(Request $request): Response
    {
        $e = $this->employee($request);

        return Inertia::render('Portal/Documents', [
            'requested' => $this->awaiting($e)->map(fn ($d) => ['id' => $d->id, 'label' => $d->category->label(), 'since' => $d->created_at->format('j M Y')]),
            'onFile' => $e->documents()->latest()->get()->filter->visibleToEmployee()->values()->map(fn (Document $d) => [
                'id' => $d->id,
                'category' => $d->category->label(),
                'name' => $d->original_name,
                'date' => $d->created_at->format('j M Y'),
                'waiting' => $d->isPendingReview(),
            ]),
            'categories' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c === DocumentCategory::Absence ? 'Fit note or absence evidence' : $c->label()], EmployeeRequest::UPLOAD_CATEGORIES),
            'maxMb' => config('sponsorsafe.documents.max_kb') / 1024,
        ]);
    }

    public function store(Request $request, EmployeeRequests $requests): RedirectResponse
    {
        $e = $this->employee($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.config('sponsorsafe.documents.max_kb'), 'mimes:'.implode(',', config('sponsorsafe.documents.mimes'))],
            'document_request_id' => ['nullable', 'integer'],
            'category' => ['required_without:document_request_id', 'nullable', Rule::in(array_map(fn ($c) => $c->value, EmployeeRequest::UPLOAD_CATEGORIES))],
            'note' => ['nullable', 'string', 'max:300'],
        ], [
            'file.required' => 'Choose a file first.',
            'file.mimes' => 'Send a PDF, JPG or PNG file (a photo of the document is fine).',
            'file.max' => 'The file is too big. The limit is '.(config('sponsorsafe.documents.max_kb') / 1024).' MB.',
        ]);
        $for = isset($data['document_request_id']) ? $this->awaiting($e)->firstWhere('id', (int) $data['document_request_id']) : null;
        abort_if(isset($data['document_request_id']) && ! $for, 404);

        $requests->document($e, $request->file('file'), $for?->category ?? DocumentCategory::from($data['category']), $for, $data['note'] ?? null, $request->user());

        return back()->with('success', 'Sent. HR will check it and file it.');
    }

    /** Open one of your own documents. Audited like every document view. */
    public function show(Request $request, int $document, DocumentVault $vault): HttpResponse
    {
        $doc = $this->employee($request)->documents()->findOrFail($document);
        abort_unless($doc->visibleToEmployee(), 404);
        $contents = $vault->contents($doc);
        Audit::log('document.viewed', $doc, ['employee_id' => $doc->employee_id, 'category' => $doc->category->value, 'by' => 'employee']);

        return response($contents, 200, [
            'Content-Type' => $doc->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($doc->original_name, '"\\').'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
