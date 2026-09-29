<?php

namespace App\Services;

use App\Enums\DocumentCategory;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Employee documents on the private disk (storage/app/private), never under public/.
 * File contents are encrypted with the app key, so a copied storage folder is unreadable on its own.
 */
class DocumentVault
{
    private const DISK = 'local';

    public function store(Employee $employee, UploadedFile $file, DocumentCategory $category, ?string $expiresOn, User $by, string $via = 'hr'): Document
    {
        $path = "documents/{$employee->business_id}/{$employee->id}/".Str::uuid().'.enc';
        Storage::disk(self::DISK)->put($path, Crypt::encryptString($file->get()));

        return DB::transaction(function () use ($employee, $file, $category, $expiresOn, $by, $via, $path) {
            $document = $employee->documents()->create([
                'business_id' => $employee->business_id,
                'category' => $category,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'expires_on' => $expiresOn,
                'uploaded_by' => $by->id,
                'uploaded_via' => $via,
            ]);

            // Anything HR asked the employee for in this category is now received.
            $employee->documentRequests()->where('category', $category->value)->where('status', DocumentRequest::STATUS_AWAITING)
                ->update(['status' => DocumentRequest::STATUS_RECEIVED, 'document_id' => $document->id]);

            Audit::log('document.uploaded', $document, ['employee_id' => $employee->id, 'category' => $category->value, 'name' => $document->original_name], $by);

            return $document;
        });
    }

    /** Decrypted file contents. Callers must write the audit entry (view or download). */
    public function contents(Document $document): string
    {
        return Crypt::decryptString(Storage::disk(self::DISK)->get($document->path));
    }

    public function delete(Document $document, User $by): void
    {
        Storage::disk(self::DISK)->delete($document->path);
        $document->delete();
        Audit::log('document.deleted', $document, ['employee_id' => $document->employee_id, 'category' => $document->category->value, 'name' => $document->original_name], $by);
    }
}
