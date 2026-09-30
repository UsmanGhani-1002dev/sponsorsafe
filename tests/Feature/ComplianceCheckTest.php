<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Services\ComplianceCheck;
use App\Services\WorkingDays;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** compliance-rules §8 and acceptance test 13 (compliance pack). */
class ComplianceCheckTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo('2026-09-28 10:00');
        $this->setUpBusiness();
    }

    private function employee(bool $sponsored, array $attributes = []): Employee
    {
        $factory = $sponsored ? Employee::factory()->sponsored() : Employee::factory();

        return $factory->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id,
            'address' => '1 High Street', 'phone' => '07700 900111', ...$attributes]);
    }

    private function file(Employee $e, string $category, string $uploaded = '2026-09-01', ?string $review = null): void
    {
        $doc = Document::create(['business_id' => $e->business_id, 'employee_id' => $e->id, 'category' => $category, 'original_name' => "{$category}.pdf",
            'path' => "documents/test/{$category}.enc", 'mime' => 'application/pdf', 'size' => 100, 'review_status' => $review]);
        $doc->forceFill(['created_at' => $uploaded.' 10:00:00'])->save();
    }

    private function rows(Employee $e): array
    {
        $e = $e->fresh(['documents', 'reportTasks', 'business']);

        return collect((new ComplianceCheck(WorkingDays::fromDatabase()))->rows($e))->mapWithKeys(fn ($r) => [$r['key'] => $r['status']])->all();
    }

    public function test_everything_on_file_for_a_non_sponsored_worker_is_all_done(): void
    {
        $e = $this->employee(false);
        foreach (['rtw', 'passport', 'contract'] as $c) {
            $this->file($e, $c);
        }

        $this->assertSame(['rtw' => 'done', 'passport' => 'done', 'contact' => 'done', 'contract' => 'done'], $this->rows($e));
        $this->actingAs($this->admin)->get("/app/employees/{$e->id}")->assertInertia(fn (Assert $p) => $p
            ->where('compliance.summary', ['text' => 'Compliance: all done', 'tone' => 'green']));
    }

    public function test_missing_items_and_a_late_right_to_work_check(): void
    {
        $e = $this->employee(false, ['rtw_check_date' => '2025-01-20', 'start_date' => '2025-01-13', 'phone' => null]);
        $this->file($e, 'rtw');
        $this->file($e, 'passport', review: Document::PENDING_REVIEW); // waiting for HR: not on file yet

        $this->assertSame(['rtw' => 'missing', 'passport' => 'missing', 'contact' => 'missing', 'contract' => 'missing'], $this->rows($e));
        $this->actingAs($this->admin)->get("/app/employees/{$e->id}")->assertInertia(fn (Assert $p) => $p
            ->where('compliance.summary', ['text' => 'Compliance: 4 to fix', 'tone' => 'red'])
            ->where('compliance.rows.0.detail', 'The check date (20 Jan 2025) is after the start date (13 Jan 2025).'));
    }

    public function test_follow_up_check_is_done_then_check_within_90_days_then_missing_when_overdue(): void
    {
        $e = $this->employee(true, ['follow_up_check_due' => '2027-06-01']);
        $this->assertSame('done', $this->rows($e)['follow_up']);

        $e->update(['follow_up_check_due' => '2026-12-10']);
        $this->assertSame('check', $this->rows($e)['follow_up'], '73 days');

        $e->update(['follow_up_check_due' => '2026-09-01']);
        $this->assertSame('missing', $this->rows($e)['follow_up']);

        $e->update(['ended_on' => '2026-08-31', 'end_reason' => 'Resigned']);
        $this->assertArrayNotHasKey('follow_up', $this->rows($e), 'not for leavers');
    }

    public function test_sponsored_workers_also_need_cos_jd_recruitment_payslips_and_reports(): void
    {
        $e = $this->employee(true);
        foreach (['rtw', 'passport', 'contract', 'cos', 'jd', 'recruit'] as $c) {
            $this->file($e, $c);
        }
        $this->file($e, 'payroll', '2026-09-01');

        $rows = $this->rows($e);
        $this->assertSame(['done', 'done', 'done', 'done', 'manual', 'done'], [$rows['cos'], $rows['jd'], $rows['recruit'], $rows['payslip'], $rows['pay'], $rows['reports']]);

        $this->travelTo('2026-10-20');   // payslip now 49 days old (limit 35)
        $this->assertSame('check', $this->rows($e)['payslip']);
    }

    public function test_home_office_reports_row_follows_pending_and_overdue_tasks(): void
    {
        $e = $this->employee(true);
        $task = ReportTask::create(['business_id' => $e->business_id, 'level' => 'worker', 'employee_id' => $e->id, 'event' => 'Job title changed',
            'trigger_on' => '2026-09-25', 'deadline' => '2026-10-09', 'source' => 'manual']);
        $this->assertSame('check', $this->rows($e)['reports']);

        $task->update(['deadline' => '2026-09-24']);
        $this->assertSame('missing', $this->rows($e)['reports']);

        $task->update(['status' => ReportTask::REPORTED, 'reported_on' => '2026-09-25', 'reported_by' => 'Nadia']);
        $this->assertSame('done', $this->rows($e)['reports']);
    }

    public function test_acceptance_13_the_compliance_pack_exports_for_one_worker(): void
    {
        $e = $this->employee(true, ['full_name' => 'Aisha Rahman', 'ni_number' => 'QQ104512A', 'passport_number' => 'BK4821093']);
        $this->file($e, 'rtw');

        $response = $this->actingAs($this->admin)->get("/app/employees/{$e->id}/compliance-pack")->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('compliance-pack-aisha-rahman-2026-09-28.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(AuditLog::where('action', 'employee.pack_exported')->where('subject_id', $e->id)->exists());

        // The content (rendered as HTML here, since the PDF text is compressed).
        $html = view('pdf.compliance-pack', app(\App\Services\CompliancePack::class)->data($e->fresh(), $this->admin))->render();
        foreach (['Compliance pack: Aisha Rahman', '1. Compliance check', 'Right-to-work check before the first day', '3. Documents on file', 'rtw.pdf', '4. Absence log', '5. Home Office reports', '6. Change history', '•••• 512A', '•••• 1093'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('QQ104512A', $html, 'secrets never in full');
        $this->assertStringNotContainsString('BK4821093', $html);
    }

    public function test_other_businesses_cannot_export_a_pack(): void
    {
        $other = Employee::factory()->create();
        $this->actingAs($this->admin)->get("/app/employees/{$other->id}/compliance-pack")->assertNotFound();
    }
}
