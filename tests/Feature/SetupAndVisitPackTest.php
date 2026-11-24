<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Document;
use App\Models\Employee;
use App\Models\KeyPerson;
use App\Models\ReportTask;
use App\Models\User;
use App\Models\WorkSite;
use App\Services\ReportTasks;
use App\Services\VisitPack;
use Carbon\CarbonImmutable;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Dashboard "Get started" checklist, and "Prepare for a Home Office visit" (one PDF for the whole business). */
class SetupAndVisitPackTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
        $this->business = Business::factory()->create(['name' => 'Test Shop Ltd', 'licence_number' => 'TS1234567']);
        $this->admin = User::factory()->admin()->create(['business_id' => $this->business->id, 'name' => 'Nadia Khan']);
    }

    private function rtw(Employee $e, ?string $review = null): void
    {
        Document::create(['business_id' => $e->business_id, 'employee_id' => $e->id, 'category' => 'rtw', 'original_name' => 'rtw.pdf',
            'path' => "documents/x/{$e->id}.enc", 'mime' => 'application/pdf', 'size' => 10, 'review_status' => $review]);
    }

    private function checklist(): ?array
    {
        $this->admin->unsetRelation('business'); // the test reuses one User object across requests

        return $this->actingAs($this->admin)->get('/app')->viewData('page')['props']['setup'];
    }

    public function test_a_new_business_sees_get_started_until_every_step_is_done(): void
    {
        $steps = fn () => collect($this->checklist()['steps'])->mapWithKeys(fn ($s) => [$s['key'] => $s['done']])->all();
        $this->assertSame(['details' => false, 'people' => false, 'sites' => false, 'employees' => false, 'rtw' => false], $steps());
        $this->assertSame('Still needed: Authorising Officer and Key Contact, as named on your sponsor licence.', $this->checklist()['steps'][1]['detail']);

        $this->business->update(['registered_address' => '1 High Street, Southampton SO14 1AA']);
        KeyPerson::create(['business_id' => $this->business->id, 'role' => 'authorising_officer', 'name' => 'Nadia Khan']);
        $this->assertSame('Still needed: Key Contact, as named on your sponsor licence.', $this->checklist()['steps'][1]['detail']);
        KeyPerson::create(['business_id' => $this->business->id, 'role' => 'key_contact', 'name' => 'Nadia Khan']);
        $site = WorkSite::factory()->create(['business_id' => $this->business->id]);
        $aisha = Employee::factory()->sponsored()->create(['business_id' => $this->business->id, 'work_site_id' => $site->id]);
        $rahul = Employee::factory()->create(['business_id' => $this->business->id, 'work_site_id' => $site->id]);
        $this->rtw($aisha);
        $this->rtw($rahul, Document::PENDING_REVIEW); // sent from the portal, not accepted yet

        $this->assertSame(['details' => true, 'people' => true, 'sites' => true, 'employees' => true, 'rtw' => false], $steps());
        $this->assertSame('1 of 2 employees have their right-to-work check uploaded.', $this->checklist()['steps'][4]['detail']);
        $this->assertSame(4, $this->checklist()['done']);

        Document::where('employee_id', $rahul->id)->update(['review_status' => null]);
        $this->assertNull($this->checklist(), 'the card goes away once everything is done');
    }

    public function test_the_visit_pack_is_one_pdf_for_the_whole_business_and_is_audited(): void
    {
        $this->business->update(['registered_address' => '1 High Street, Southampton SO14 1AA']);
        KeyPerson::create(['business_id' => $this->business->id, 'role' => 'authorising_officer', 'name' => 'Nadia Khan', 'email' => 'nadia@example.com']);
        $site = WorkSite::factory()->create(['business_id' => $this->business->id, 'name' => 'Main shop']);
        $aisha = Employee::factory()->sponsored()->create(['business_id' => $this->business->id, 'work_site_id' => $site->id, 'full_name' => 'Aisha Rahman',
            'ni_number' => 'QQ104512A', 'visa_expiry' => '2027-06-30']);
        $james = Employee::factory()->create(['business_id' => $this->business->id, 'work_site_id' => $site->id, 'full_name' => 'James Carter']);
        Employee::factory()->sponsored()->left('2026-01-31')->create(['business_id' => $this->business->id, 'full_name' => 'Priya Shah']);
        $this->rtw($aisha);
        ReportTasks::manual($this->business, ReportTask::WORKER, $aisha->setRelation('business', $this->business), 'Suspected breach of visa conditions', '2026-11-20', $this->admin);

        $response = $this->actingAs($this->admin)->get('/app/visit-pack')->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('home-office-visit-pack-test-shop-ltd-2026-11-24.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(AuditLog::where('action', 'employee.visit_pack_exported')->where('business_id', $this->business->id)->exists());

        $data = app(VisitPack::class)->data($this->business, $this->admin);
        $this->assertSame(['Aisha Rahman', 'James Carter'], $data['overview']->pluck('name')->all(), 'current employees, sponsored first; leavers left out');
        $html = view('pdf.visit-pack', $data)->render();
        foreach (['Home Office visit pack', 'TS1234567', '1 High Street, Southampton SO14 1AA', 'Authorising Officer', 'nadia@example.com', 'Main shop',
            '2 current employees, 1 sponsored', 'Suspected breach of visa conditions', '30 Jun 2027',
            'Compliance pack: Aisha Rahman', 'Compliance pack: James Carter', '•••• 512A'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('QQ104512A', $html, 'secrets never in full');
        $this->assertStringNotContainsString('Priya Shah', $html);

        $this->get('/app/activity')->assertInertia(fn (Assert $p) => $p->where('entries.data.0.what', 'Downloaded the Home Office visit pack (2 employees)'));
    }

    public function test_only_admins_of_the_business_can_download_it(): void
    {
        $employee = User::factory()->create(['business_id' => $this->business->id]);
        $this->actingAs($employee)->get('/app/visit-pack')->assertRedirect('/me');
    }
}
