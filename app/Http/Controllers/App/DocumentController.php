<?php

namespace App\Http\Controllers\App;

use App\Enums\DocumentCategory;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Services\DocumentVault;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Employee documents for business admins. Every view and download is written to the audit log.
 * Files are never reachable by URL on their own: each request is signed in, scoped to the admin's business
 * and decrypted here.
 */
class DocumentController extends Controller
{
    public function __construct(private DocumentVault $vault) {}

    public function store(Request $request, int $employee): RedirectResponse
    {
        $e = $this->employee($request, $employee);
        $data = $request->validate([
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'file' => ['required', 'file', 'max:'.config('sponsorsafe.documents.max_kb'), 'mimes:'.implode(',', config('sponsorsafe.documents.mimes'))],
            'expires_on' => ['nullable', 'date'],
        ], [
            'file.required' => 'Choose a file to upload.',
            'file.max' => 'The file is too big. The limit is '.(config('sponsorsafe.documents.max_kb') / 1024).' MB.',
            'file.mimes' => 'Upload a PDF, JPG or PNG file.',
        ]);
        $category = DocumentCategory::from($data['category']);
        $this->vault->store($e, $request->file('file'), $category, $data['expires_on'] ?? null, $request->user());

        return back()->with('success', 'Uploaded to '.$category->label().'.');
    }

    /** Open in the browser (PDF and images). */
    public function show(Request $request, int $document): Response
    {
        return $this->serve($request, $document, 'inline', 'document.viewed');
    }

    public function download(Request $request, int $document): Response
    {
        return $this->serve($request, $document, 'attachment', 'document.downloaded');
    }

    public function destroy(Request $request, int $document): RedirectResponse
    {
        $doc = $this->document($request, $document);
        $this->vault->delete($doc, $request->user());

        return back()->with('success', "{$doc->original_name} removed.");
    }

    /** "Request from employee": the request appears in their portal (Stage 5). */
    public function requestFromEmployee(Request $request, int $employee): RedirectResponse
    {
        $e = $this->employee($request, $employee);
        $category = DocumentCategory::from($request->validate(['category' => ['required', Rule::enum(DocumentCategory::class)]])['category']);
        if ($e->documentRequests()->where('category', $category->value)->where('status', DocumentRequest::STATUS_AWAITING)->exists()) {
            return back()->with('error', 'This has already been requested.');
        }
        $req = $e->documentRequests()->create(['business_id' => $e->business_id, 'category' => $category, 'requested_by' => $request->user()->id]);
        Audit::log('document.requested', $req, ['employee_id' => $e->id, 'category' => $category->value]);

        return back()->with('success', $category->label().' requested from '.strtok($e->full_name, ' ').'.'
            .($e->user_id ? ' It will appear in their portal.' : ' They do not have portal access yet, so send them an invite too.'));
    }

    public function cancelRequest(Request $request, int $documentRequest): RedirectResponse
    {
        $req = DocumentRequest::where('business_id', $request->user()->business_id)->where('status', DocumentRequest::STATUS_AWAITING)->findOrFail($documentRequest);
        $req->update(['status' => DocumentRequest::STATUS_CANCELLED]);
        Audit::log('document.request_cancelled', $req, ['employee_id' => $req->employee_id, 'category' => $req->category->value]);

        return back()->with('success', 'Request cancelled.');
    }

    private function serve(Request $request, int $id, string $disposition, string $action): Response
    {
        $doc = $this->document($request, $id);
        $contents = $this->vault->contents($doc);
        Audit::log($action, $doc, ['employee_id' => $doc->employee_id, 'category' => $doc->category->value, 'name' => $doc->original_name]);

        return response($contents, 200, [
            'Content-Type' => $doc->mime,
            'Content-Disposition' => $disposition.'; filename="'.addcslashes($doc->original_name, '"\\').'"',
            'Content-Length' => strlen($contents),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', // only PDF, JPG and PNG are accepted at upload
        ]);
    }

    private function employee(Request $request, int $id): Employee
    {
        return $request->user()->business->employees()->findOrFail($id);
    }

    private function document(Request $request, int $id): Document
    {
        return Document::where('business_id', $request->user()->business_id)->findOrFail($id);
    }
}
