<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Employee;
use App\Models\ReportTask;
use App\Models\User;
use App\Notifications\AdminInvite;
use App\Services\PasswordLinks;
use Database\Seeders\BankHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Polish part 2: editable business details (§4 company tasks), more admin logins, the employee privacy notice. */
class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BankHolidaySeeder::class);
        Notification::fake();
        $this->admin = User::factory()->admin()->create([
            'name' => 'Nadia Khan',
            'business_id' => Business::factory()->create(['name' => 'Demo Retail Ltd', 'licence_number' => 'SL1234567'])->id,
        ]);
    }

    private function details(array $overrides = [])
    {
        return $this->actingAs($this->admin)->put('/app/settings/business', [
            'name' => 'Demo Retail Ltd', 'licence' => 'SL1234567', 'phone' => '', 'address' => '', ...$overrides,
        ]);
    }

    // ---- Business details ----

    public function test_a_new_business_name_creates_a_company_task_due_in_20_working_days(): void
    {
        $this->travelTo('2026-10-01 10:00'); // a Thursday

        $this->details(['name' => 'Demo Retail Group Ltd'])->assertSessionHas('success', 'Business details saved. Report the change on the Sponsor Management System: a Home Office task has been added.');

        $task = ReportTask::sole();
        $this->assertSame([ReportTask::COMPANY, 'Business name changed (Demo Retail Ltd to Demo Retail Group Ltd)', 'business', '2026-10-01', '2026-10-29'],
            [$task->level, $task->event, $task->source, $task->trigger_on->toDateString(), $task->deadline->toDateString()]);
        $this->assertSame('Demo Retail Group Ltd', $this->admin->business->fresh()->name);
        $this->assertTrue(AuditLog::where('action', 'business.details_updated')->exists());
    }

    public function test_a_first_address_licence_or_phone_is_not_reported_but_a_changed_address_is(): void
    {
        $this->details(['address' => "14 Above Bar Street\nSouthampton SO14 7DU", 'phone' => '023 8000 1111', 'licence' => 'SL7654321'])
            ->assertSessionHas('success', 'Business details saved.');
        $this->assertSame(0, ReportTask::count());
        $this->assertSame(['023 8000 1111', 'SL7654321'], [$this->admin->business->fresh()->phone, $this->admin->business->fresh()->licence_number]);

        $this->details(['address' => "3 Portswood Road\nSouthampton SO17 2ES", 'phone' => '023 8000 1111', 'licence' => 'SL7654321']);
        $this->assertSame('Registered or trading address changed', ReportTask::sole()->event);

        $this->details(['address' => "3 Portswood Road\nSouthampton SO17 2ES", 'phone' => '023 8000 1111', 'licence' => 'SL7654321'])->assertSessionHas('success', 'Nothing changed.');
        $this->details(['name' => ''])->assertSessionHasErrors(['name' => 'Please add the business name.']);
    }

    public function test_settings_show_the_business_details_and_admin_logins(): void
    {
        $other = User::factory()->admin()->create(['business_id' => $this->admin->business_id, 'name' => 'Imran Shah', 'invited_at' => '2026-09-30 09:00']);

        $this->actingAs($this->admin)->get('/app/settings')->assertInertia(fn (Assert $p) => $p
            ->where('business', ['name' => 'Demo Retail Ltd', 'licence' => 'SL1234567', 'phone' => null, 'address' => null])
            ->has('admins', 2)
            ->where('admins.0.you', true)
            ->where('admins.1', ['id' => $other->id, 'name' => 'Imran Shah', 'email' => $other->email, 'you' => false, 'status' => 'Invite sent 30 Sep 2026', 'invited' => true]));
    }

    // ---- Admin logins ----

    public function test_an_admin_invites_another_admin_who_gets_a_set_password_link(): void
    {
        $this->actingAs($this->admin)->post('/app/settings/admins', ['name' => 'Imran Shah', 'email' => 'Imran.Shah@demo-retail.example'])
            ->assertSessionHas('success');

        $new = User::where('email', 'imran.shah@demo-retail.example')->sole();
        $this->assertSame([User::ROLE_ADMIN, $this->admin->business_id, true], [$new->role, $new->business_id, $new->active]);
        Notification::assertSentTo($new, AdminInvite::class, fn (AdminInvite $n) => PasswordLinks::findUser($n->token)?->is($new) && $n->invitedBy === 'Nadia Khan');
        $this->assertTrue(AuditLog::where('action', 'admin.invited')->exists());

        // An email that already has a login is refused.
        $this->post('/app/settings/admins', ['name' => 'Copy', 'email' => 'imran.shah@demo-retail.example'])->assertSessionHasErrors(['email' => 'This email already has a login. Use a different email.']);
        $this->post('/app/settings/admins', ['name' => '', 'email' => 'nope'])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_an_invite_can_be_resent_until_they_sign_in(): void
    {
        $new = User::factory()->admin()->create(['business_id' => $this->admin->business_id]);

        $this->actingAs($this->admin)->post("/app/settings/admins/{$new->id}/resend")->assertSessionHas('success');
        Notification::assertSentToTimes($new, AdminInvite::class, 1);

        $new->update(['last_login_at' => now()]);
        $this->post("/app/settings/admins/{$new->id}/resend")->assertSessionHasErrors('admin');
    }

    public function test_admins_can_be_removed_but_never_yourself(): void
    {
        $other = User::factory()->admin()->create(['business_id' => $this->admin->business_id]);

        $this->actingAs($this->admin)->delete("/app/settings/admins/{$this->admin->id}")->assertSessionHasErrors(['admin' => 'You cannot remove your own login. Ask another admin to do it.']);
        $this->delete("/app/settings/admins/{$other->id}")->assertSessionHas('success');
        $this->assertModelMissing($other);
        $this->assertTrue(AuditLog::where('action', 'admin.removed')->exists());
    }

    public function test_admin_logins_of_other_businesses_and_employees_are_out_of_reach(): void
    {
        $stranger = User::factory()->admin()->create();
        $employee = User::factory()->create(['business_id' => $this->admin->business_id]);

        $this->actingAs($this->admin)->delete("/app/settings/admins/{$stranger->id}")->assertNotFound();
        $this->post("/app/settings/admins/{$stranger->id}/resend")->assertNotFound();
        $this->delete("/app/settings/admins/{$employee->id}")->assertNotFound();
        $this->assertModelExists($stranger);

        $this->actingAs($employee)->post('/app/settings/admins', ['name' => 'Sneaky', 'email' => 'sneaky@example.com'])->assertRedirect('/me');
        $this->assertFalse(User::where('email', 'sneaky@example.com')->exists());
    }

    // ---- Employee privacy notice ----

    public function test_employees_see_the_privacy_notice_with_their_employers_retention_periods(): void
    {
        $user = User::factory()->create(['business_id' => $this->admin->business_id]);
        Employee::factory()->create(['business_id' => $this->admin->business_id, 'user_id' => $user->id]);

        $this->actingAs($user)->get('/me/privacy')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Portal/Privacy')
            ->where('business', 'Demo Retail Ltd')
            ->where('contacts', [$this->admin->email])
            ->where('retentionYears', 1)
            ->where('rtwRetentionYears', 2));

        $this->actingAs($this->admin)->get('/me/privacy')->assertRedirect('/app');
    }
}
