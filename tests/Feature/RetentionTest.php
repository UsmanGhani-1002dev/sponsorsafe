<?php

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\MakesEmployees;
use Tests\TestCase;

/** Retention: end + 1 year for sponsor records, end + 2 years for right-to-work evidence. */
class RetentionTest extends TestCase
{
    use MakesEmployees, RefreshDatabase;

    private Employee $leaver;
    private User $login;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-09-28 10:00');
        $this->setUpBusiness();
        $this->login = User::factory()->create(['business_id' => $this->admin->business_id, 'active' => false]);
        $this->leaver = Employee::factory()->create(['business_id' => $this->admin->business_id, 'work_site_id' => $this->site->id, 'user_id' => $this->login->id,
            'full_name' => 'Old Leaver', 'ended_on' => '2025-06-30', 'end_reason' => 'Resigned', 'delete_after' => '2026-06-30', 'rtw_delete_after' => '2027-06-30']);
        foreach (['rtw', 'contract', 'payroll'] as $c) {
            $doc = Document::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->leaver->id, 'category' => $c, 'original_name' => "{$c}.pdf",
                'path' => "documents/1/{$this->leaver->id}/{$c}.enc", 'mime' => 'application/pdf', 'size' => 10]);
            Storage::disk('local')->put($doc->path, 'x');
        }
        Absence::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->leaver->id, 'type' => 'annual', 'start_date' => '2025-05-05', 'end_date' => '2025-05-09', 'working_days' => 5, 'check_status' => 'none']);
        EmployeeChange::create(['business_id' => $this->admin->business_id, 'employee_id' => $this->leaver->id, 'field' => 'ended_on', 'label' => 'Employment ended', 'new_value' => 'Resigned']);
        $this->actingAs($this->admin);
    }

    public function test_after_one_year_everything_but_right_to_work_evidence_is_deleted(): void
    {
        $this->get('/app/retention')->assertInertia(fn (Assert $p) => $p->component('App/Retention/Index')
            ->has('due', 1)->where('due.0.step', 'records')->where('due.0.counts', ['documents' => 2, 'absences' => 1, 'requests' => 0, 'tasks' => 0]));
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('retentionDue', 1));

        $this->delete("/app/retention/{$this->leaver->id}")->assertSessionHas('success', 'Records for Old Leaver deleted. Right-to-work evidence is kept until 30 Jun 2027.');

        $this->assertSame(['rtw'], Document::pluck('category')->map->value->all());
        Storage::disk('local')->assertMissing("documents/1/{$this->leaver->id}/contract.enc");
        Storage::disk('local')->assertExists("documents/1/{$this->leaver->id}/rtw.enc");
        $this->assertSame(0, Absence::count());
        $this->assertNotNull($this->leaver->fresh());
        $this->get('/app/retention')->assertInertia(fn (Assert $p) => $p->has('due', 0));
    }

    public function test_after_two_years_the_whole_record_and_login_are_deleted_without_personal_data_in_the_audit(): void
    {
        $this->travelTo('2027-07-01');
        $this->get('/app/retention')->assertInertia(fn (Assert $p) => $p->where('due.0.step', 'all')->where('due.0.counts.documents', 3));

        $this->delete("/app/retention/{$this->leaver->id}")->assertSessionHas('success', 'All records for Old Leaver have been permanently deleted.');

        $this->assertNull(Employee::find($this->leaver->id));
        $this->assertNull(User::find($this->login->id));
        $this->assertSame(0, Document::count());
        $this->assertSame(0, EmployeeChange::count());
        Storage::disk('local')->assertMissing("documents/1/{$this->leaver->id}/rtw.enc");
        $audit = AuditLog::where('action', 'retention.employee_deleted')->sole();
        $this->assertStringNotContainsString('Old Leaver', json_encode($audit->meta));
        $this->assertSame($this->leaver->id, $audit->meta['employee_id']);
    }

    public function test_nothing_is_due_before_the_date_or_for_current_employees(): void
    {
        $this->travelTo('2026-06-29');
        $this->get('/app/retention')->assertInertia(fn (Assert $p) => $p->has('due', 0));
        $this->delete("/app/retention/{$this->leaver->id}")->assertStatus(422);

        $current = Employee::factory()->create(['business_id' => $this->admin->business_id]);
        $this->delete("/app/retention/{$current->id}")->assertStatus(422);
        $this->assertSame(3, Document::count());
    }

    public function test_other_businesses_cannot_delete(): void
    {
        $this->actingAs(User::factory()->admin()->create())->delete("/app/retention/{$this->leaver->id}")->assertNotFound();
        $this->assertSame(3, Document::count());
    }
}
