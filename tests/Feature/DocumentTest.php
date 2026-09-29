<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** compliance-rules §2 and acceptance test: documents cannot be opened by URL without signing in; every view and download is audited. */
class DocumentTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpBusiness();
        $this->employee = Employee::factory()->sponsored()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'full_name' => 'Aisha Rahman']);
    }

    private function upload(string $category = 'passport', ?UploadedFile $file = null, array $extra = [])
    {
        return $this->actingAs($this->admin)->post("/app/employees/{$this->employee->id}/documents", [
            'category' => $category, 'file' => $file ?? UploadedFile::fake()->createWithContent('passport-scan.pdf', '%PDF-1.4 secret passport scan'), ...$extra,
        ]);
    }

    public function test_upload_is_stored_privately_and_encrypted(): void
    {
        $this->upload(extra: ['expires_on' => '2031-05-20'])->assertSessionHas('success', 'Uploaded to Passport / identity.');

        $doc = Document::sole();
        $this->assertSame(['passport', 'passport-scan.pdf', '2031-05-20'], [$doc->category->value, $doc->original_name, $doc->expires_on->format('Y-m-d')]);
        $this->assertStringStartsWith("documents/{$this->admin->business_id}/{$this->employee->id}/", $doc->path);
        Storage::disk('local')->assertExists($doc->path);
        $raw = Storage::disk('local')->get($doc->path);
        $this->assertStringNotContainsString('secret passport scan', $raw, 'encrypted at rest');
        $this->assertSame('%PDF-1.4 secret passport scan', Crypt::decryptString($raw));
        $this->assertTrue(AuditLog::where('action', 'document.uploaded')->exists());
    }

    public function test_viewing_and_downloading_return_the_file_and_are_audited(): void
    {
        $this->upload();
        $doc = Document::sole();

        $view = $this->get("/app/documents/{$doc->id}")->assertOk();
        $this->assertSame('%PDF-1.4 secret passport scan', $view->getContent());
        $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $view->headers->get('Cache-Control'));

        $download = $this->get("/app/documents/{$doc->id}/download")->assertOk();
        $this->assertStringStartsWith('attachment; filename="passport-scan.pdf"', $download->headers->get('Content-Disposition'));

        $this->assertSame(1, AuditLog::where('action', 'document.viewed')->where('subject_id', $doc->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'document.downloaded')->where('subject_id', $doc->id)->count());
    }

    public function test_documents_cannot_be_opened_without_signing_in(): void
    {
        $this->upload();
        $doc = Document::sole();
        auth('web')->logout();

        $this->get("/app/documents/{$doc->id}")->assertRedirect('/login');
        $this->get("/app/documents/{$doc->id}/download")->assertRedirect('/login');
        $this->assertSame(0, AuditLog::where('action', 'like', 'document.viewed')->count());
    }

    public function test_other_businesses_and_employees_cannot_open_documents(): void
    {
        $this->upload();
        $doc = Document::sole();

        $otherAdmin = User::factory()->admin()->create();
        $this->actingAs($otherAdmin)->get("/app/documents/{$doc->id}")->assertNotFound();
        $this->delete("/app/documents/{$doc->id}")->assertNotFound();
        $this->post("/app/employees/{$this->employee->id}/documents", ['category' => 'other', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertNotFound();

        $employeeUser = User::factory()->create(['business_id' => $this->admin->business_id]);
        $this->actingAs($employeeUser)->get("/app/documents/{$doc->id}")->assertRedirect('/me');
    }

    public function test_only_pdf_jpg_and_png_up_to_10_mb(): void
    {
        $this->upload(file: UploadedFile::fake()->create('notes.txt', 5, 'text/plain'))->assertSessionHasErrors(['file' => 'Upload a PDF, JPG or PNG file.']);
        $this->upload(file: UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'))->assertSessionHasErrors('file');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
        $this->upload(file: UploadedFile::fake()->createWithContent('passport.png', $png))->assertSessionHasNoErrors();
        $this->assertSame(1, Document::count());
    }

    public function test_request_from_employee_and_upload_marks_it_received(): void
    {
        $this->actingAs($this->admin)->post("/app/employees/{$this->employee->id}/document-requests", ['category' => 'contract'])->assertSessionHas('success');
        $this->post("/app/employees/{$this->employee->id}/document-requests", ['category' => 'contract'])->assertSessionHas('error', 'This has already been requested.');

        $this->get("/app/employees/{$this->employee->id}")->assertInertia(fn (Assert $p) => $p
            ->where('documents.3.value', 'contract')
            ->where('documents.3.status.text', 'Requested from employee'));

        $this->upload('contract');
        $request = DocumentRequest::sole();
        $this->assertSame(DocumentRequest::STATUS_RECEIVED, $request->status);
        $this->assertSame(Document::sole()->id, $request->document_id);
    }

    public function test_a_request_can_be_cancelled(): void
    {
        $this->actingAs($this->admin)->post("/app/employees/{$this->employee->id}/document-requests", ['category' => 'jd']);
        $this->post('/app/document-requests/'.DocumentRequest::sole()->id.'/cancel')->assertSessionHas('success');
        $this->assertSame(DocumentRequest::STATUS_CANCELLED, DocumentRequest::sole()->status);
    }

    public function test_removing_a_document_deletes_the_file_and_is_audited(): void
    {
        $this->upload();
        $doc = Document::sole();

        $this->delete("/app/documents/{$doc->id}")->assertSessionHas('success');
        Storage::disk('local')->assertMissing($doc->path);
        $this->assertSame(0, Document::count());
        $this->assertTrue(AuditLog::where('action', 'document.deleted')->exists());
    }

    public function test_documents_tab_and_list_show_required_categories_on_file(): void
    {
        foreach (['rtw', 'passport', 'contract'] as $category) {
            $this->upload($category);
        }

        // Sponsored: rtw, passport, contract, jd, payroll, cos, recruit = 7 required.
        $this->get('/app/employees')->assertInertia(fn (Assert $p) => $p->where('employees.data.0.documents', ['have' => 3, 'need' => 7]));
        $this->get("/app/employees/{$this->employee->id}")->assertInertia(fn (Assert $p) => $p
            ->where('employee.documents', ['have' => 3, 'need' => 7])
            ->where('documents.0.status.text', 'On file')
            ->where('documents.2.label', 'Certificate of Sponsorship')
            ->where('documents.2.status.text', 'Missing')
            ->where('documents.8.status.text', 'Optional')
            ->where('documents.1.files.0.name', 'passport-scan.pdf'));
    }
}
