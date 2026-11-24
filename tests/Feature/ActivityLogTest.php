<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Document;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\ReportTasks;
use Carbon\CarbonImmutable;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Settings → Activity log for business admins, and the super admin's Activity log (both read from audit_logs). */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private User $admin;
    private Employee $aisha;
    private Employee $rahul;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
        $this->business = Business::factory()->create(['name' => 'Demo Retail Ltd']);
        $this->admin = User::factory()->admin()->create(['business_id' => $this->business->id, 'name' => 'Nadia Khan']);
        $aishaLogin = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Aisha Rahman', 'email' => 'aisha@example.com']);
        $this->aisha = Employee::factory()->sponsored()->create(['business_id' => $this->business->id, 'full_name' => 'Aisha Rahman', 'user_id' => $aishaLogin->id]);
        $this->rahul = Employee::factory()->sponsored()->create(['business_id' => $this->business->id, 'full_name' => 'Rahul Mehta']);
    }

    /** Some real activity: HR opens Aisha's passport, Aisha signs in, a report task for Rahul, a super admin pauses billing. */
    private function activity(): void
    {
        $doc = Document::create(['business_id' => $this->business->id, 'employee_id' => $this->aisha->id, 'category' => 'passport', 'original_name' => 'passport-scan.pdf',
            'path' => 'documents/x/p.enc', 'mime' => 'application/pdf', 'size' => 10]);
        Storage::disk('local')->put($doc->path, encrypt('pdf'));
        $this->actingAs($this->admin)->get("/app/documents/{$doc->id}")->assertOk();

        AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'user', 'actor_id' => $this->aisha->user_id, 'action' => 'auth.login', 'subject_type' => 'User', 'subject_id' => $this->aisha->user_id, 'ip' => '81.2.69.160']);
        ReportTasks::manual($this->business, ReportTask::WORKER, $this->rahul->setRelation('business', $this->business), 'Suspected breach of visa conditions', '2026-11-20', $this->admin);
        $owner = SuperAdmin::create(['name' => 'Platform owner', 'email' => 'owner@example.com', 'password' => 'long-password-1']);
        AuditLog::create(['business_id' => $this->business->id, 'actor_type' => 'super_admin', 'actor_id' => $owner->id, 'action' => 'ops.business.suspended', 'subject_type' => 'Business', 'subject_id' => $this->business->id]);

        // Another business's activity is never shown to this one.
        $other = Business::factory()->create(['name' => 'Demo Catering Ltd']);
        AuditLog::create(['business_id' => $other->id, 'actor_type' => 'user', 'actor_id' => User::factory()->admin()->create(['business_id' => $other->id])->id, 'action' => 'auth.login']);
    }

    public function test_admins_see_their_business_activity_in_plain_english_newest_first(): void
    {
        $this->activity();

        $this->actingAs($this->admin)->get('/app/activity')->assertInertia(fn (Assert $p) => $p->component('App/Activity/Index')
            ->where('entries.total', 4)
            ->where('entries.data.0.what', 'Account paused by SponsorSafe')
            ->where('entries.data.0.who', ['name' => 'SponsorSafe support', 'role' => 'SponsorSafe'])
            ->where('entries.data.1.what', 'Home Office report task created: Suspected breach of visa conditions (deadline 4 Dec 2026)')
            ->where('entries.data.1.employee', ['id' => $this->rahul->id, 'name' => 'Rahul Mehta'])
            ->where('entries.data.2.what', 'Signed in')
            ->where('entries.data.2.who', ['name' => 'Aisha Rahman', 'role' => 'Employee'])
            ->where('entries.data.2.ip', '81.2.69.160')
            ->where('entries.data.3.what', 'Opened passport-scan.pdf (Passport / identity)')
            ->where('entries.data.3.who', ['name' => 'Nadia Khan', 'role' => 'Admin'])
            ->where('entries.data.3.employee.name', 'Aisha Rahman'));
    }

    public function test_filter_by_what_who_dates_and_search_by_person(): void
    {
        $this->activity();
        $this->actingAs($this->admin);
        $whats = fn (string $query) => collect($this->get("/app/activity?$query")->viewData('page')['props']['entries']['data'])->pluck('what')->all();

        $this->assertSame(['Opened passport-scan.pdf (Passport / identity)'], $whats('type=documents'));
        $this->assertSame(['Signed in'], $whats('who=employee'));
        $this->assertSame(['Account paused by SponsorSafe'], $whats('who=sponsorsafe'));
        // Searching for Rahul finds the report task about him; searching for Aisha finds what she did and what was done to her record.
        $this->assertSame(['Home Office report task created: Suspected breach of visa conditions (deadline 4 Dec 2026)'], $whats('q=rahul'));
        $this->assertSame(['Signed in', 'Opened passport-scan.pdf (Passport / identity)'], $whats('q=aisha'));
        $this->assertSame([], $whats('from=2026-11-25'));
        $this->assertCount(4, $whats('from=2026-11-24&to=2026-11-24&type=nonsense'));
    }

    public function test_only_business_admins_can_open_it(): void
    {
        $this->actingAs($this->aisha->user)->get('/app/activity')->assertRedirect('/me');
        auth()->logout();
        $this->get('/app/activity')->assertRedirect('/login');
    }

    public function test_super_admins_see_every_business_and_can_filter_by_one(): void
    {
        $this->activity();
        $owner = SuperAdmin::sole();
        $ops = '/'.config('sponsorsafe.ops_path');

        $this->actingAs($owner, 'ops')->get("$ops/activity")->assertInertia(fn (Assert $p) => $p->component('Ops/Activity')
            ->where('entries.total', 5)
            ->where('entries.data.0.business', 'Demo Catering Ltd')
            ->where('entries.data.1.who', ['name' => 'Platform owner', 'role' => 'Super admin']));

        $this->get("$ops/activity?business={$this->business->id}")->assertInertia(fn (Assert $p) => $p->where('entries.total', 4));
        $this->get("$ops/activity?q=catering")->assertInertia(fn (Assert $p) => $p->where('entries.total', 1));
    }
}
