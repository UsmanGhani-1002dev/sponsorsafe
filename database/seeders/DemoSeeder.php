<?php

namespace Database\Seeders;

use App\Enums\DocumentCategory;
use App\Enums\RightToWorkBasis as B;
use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data matching the agreed prototype. Every demo password is "password". Never run in production.
 * Business admins set up an authenticator app on first sign-in (2FA is required for admins).
 */
class DemoSeeder extends Seeder
{
    private const SHARE = 'Home Office online check (share code)';
    private const MANUAL = 'Manual check of original passport';
    private const IDVT = 'IDVT – certified identity provider';

    public function run(): void
    {
        $retail = $this->business('Demo Retail Ltd', 'active', 'Card ending 4242', 'stripe', '2026-10-10', 'Nadia Khan', 'hr@demo-retail.example',
            sites: ['Main shop' => '14 Above Bar Street, Southampton SO14 7DU', 'Second shop' => '3 Portswood Road, Southampton SO17 2ES'],
            people: [['authorising_officer', 'Imran Shah', 'imran.shah@demo-retail.example'], ['key_contact', 'Nadia Khan', 'hr@demo-retail.example'], ['level1_user', 'Nadia Khan', 'hr@demo-retail.example']]);
        $catering = $this->business('Demo Catering Ltd', 'active', 'PayPal', 'paypal', '2026-10-02', 'Mark Evans', 'hr@demo-catering.example',
            sites: ['Central kitchen' => 'Unit 7, Millbrook Trading Estate, Southampton SO15 0LD'],
            people: [['authorising_officer', 'Mark Evans', 'hr@demo-catering.example'], ['key_contact', 'Mark Evans', 'hr@demo-catering.example'], ['level1_user', 'Mark Evans', 'hr@demo-catering.example']]);
        $cafe = $this->business('Demo Cafe Ltd', 'suspended', 'Card ending 1881', 'stripe', null, 'Leo Grant', 'hr@demo-cafe.example',
            sites: ['Cafe' => '22 Oxford Street, Southampton SO14 3DJ'],
            people: [['authorising_officer', 'Leo Grant', 'hr@demo-cafe.example']]);

        // Demo Retail: the five people in the prototype.
        $aisha = $this->employee($retail, 'Main shop', 'Aisha Rahman', 'Sales Assistant', B::Sponsored, [
            'nationality' => 'Pakistani', 'date_of_birth' => '1996-04-12', 'passport_expiry' => '2031-05-20', 'rtw_check_method' => self::SHARE,
            'rtw_check_date' => '2025-10-28', 'visa_start' => '2025-11-01', 'visa_expiry' => '2026-12-10', 'work_restrictions' => 'Only the sponsored job',
            'cos_number' => 'C4X9R27316K', 'cos_assigned_on' => '2025-09-15', 'soc_code' => '7111', 'salary' => 38700, 'start_date' => '2025-11-03',
            'address' => '41 Shirley High Street, Southampton SO15 3NN', 'phone' => '07700 900101', 'ni_number' => 'QQ104512A', 'passport_number' => 'BK4821093',
        ]);
        $rahul = $this->employee($retail, 'Main shop', 'Rahul Mehta', 'Store Supervisor', B::Sponsored, [
            'nationality' => 'Indian', 'date_of_birth' => '1991-11-02', 'passport_expiry' => '2029-08-14', 'rtw_check_method' => self::SHARE,
            'rtw_check_date' => '2024-05-28', 'visa_start' => '2024-06-01', 'visa_expiry' => '2028-03-01', 'work_restrictions' => 'Only the sponsored job',
            'cos_number' => 'C7M2P58820D', 'cos_assigned_on' => '2024-04-10', 'soc_code' => '7132', 'salary' => 42500, 'start_date' => '2024-06-03', 'contracted_hours' => 40,
            'address' => '8 Winchester Road, Southampton SO16 6TE', 'phone' => '07700 900102', 'ni_number' => 'QQ209871B', 'passport_number' => 'U71936244',
        ]);
        $this->employee($retail, 'Second shop', 'James Carter', 'Stock Assistant', B::BritishIrish, [
            'date_of_birth' => '1999-06-21', 'passport_expiry' => '2030-01-09', 'rtw_check_method' => self::MANUAL, 'rtw_check_date' => '2023-02-06',
            'salary' => 19800, 'start_date' => '2023-02-13', 'days_per_week' => 4, 'contracted_hours' => 30,
            'address' => '19 Bitterne Road West, Southampton SO18 1AR', 'phone' => '07700 900103', 'ni_number' => 'QQ553190C', 'passport_number' => '539210877',
        ]);
        $kasia = $this->employee($retail, 'Second shop', 'Kasia Nowak', 'Sales Assistant', B::EussPreSettled, [
            'nationality' => 'Polish', 'date_of_birth' => '1998-03-15', 'passport_expiry' => '2032-07-30', 'rtw_check_method' => self::SHARE, 'rtw_check_date' => '2025-03-03',
            'visa_start' => '2022-02-14', 'visa_expiry' => '2027-02-14', 'salary' => 23900, 'start_date' => '2025-03-10',
            'address' => '5 Lodge Road, Southampton SO14 6RG', 'phone' => '07700 900104', 'ni_number' => 'QQ661024D', 'passport_number' => 'EP7730412',
        ]);
        $this->employee($retail, 'Main shop', 'Daniel Okafor', 'Part-time Sales Assistant', B::OtherVisa, [
            'nationality' => 'Nigerian', 'date_of_birth' => '2000-01-28', 'passport_expiry' => '2030-10-11', 'rtw_check_method' => self::SHARE, 'rtw_check_date' => '2025-08-20',
            'visa_type' => 'Graduate', 'visa_start' => '2025-07-01', 'visa_expiry' => '2027-06-30', 'work_restrictions' => 'None on Graduate route',
            'salary' => 13400, 'start_date' => '2025-09-01', 'days_per_week' => 3, 'contracted_hours' => 20, 'contract_type' => 'Part-time permanent',
            'address' => '27 Avenue Road, Southampton SO14 6TR', 'phone' => '07700 900105', 'ni_number' => 'QQ718365A', 'passport_number' => 'A09152877',
        ]);

        // Demo Catering.
        $fatima = $this->employee($catering, 'Central kitchen', 'Fatima Hussain', 'Chef', B::Sponsored, [
            'nationality' => 'Bangladeshi', 'date_of_birth' => '1989-08-09', 'passport_expiry' => '2028-12-01', 'rtw_check_method' => self::SHARE, 'rtw_check_date' => '2024-09-16',
            'visa_start' => '2024-09-20', 'visa_expiry' => '2027-09-30', 'work_restrictions' => 'Only the sponsored job',
            'cos_number' => 'C1B8T40672Q', 'cos_assigned_on' => '2024-08-01', 'soc_code' => '5434', 'salary' => 41800, 'start_date' => '2024-09-23', 'contracted_hours' => 40,
            'address' => '63 Millbrook Road East, Southampton SO15 1HN', 'phone' => '07700 900106', 'ni_number' => 'QQ824406B', 'passport_number' => 'EH0417726',
        ]);
        $this->employee($catering, 'Central kitchen', 'Tom Richards', 'Kitchen Porter', B::BritishIrish, [
            'date_of_birth' => '2002-12-05', 'passport_expiry' => '2033-03-18', 'rtw_check_method' => self::IDVT, 'rtw_check_date' => '2025-01-06',
            'salary' => 22100, 'start_date' => '2025-01-13',
            'address' => '2 Regents Park Road, Southampton SO15 8NY', 'phone' => '07700 900107', 'ni_number' => 'QQ390157C', 'passport_number' => '561038294',
        ]);

        // Demo Cafe (suspended: shows the paused message at sign-in).
        $this->employee($cafe, 'Cafe', 'Chloe Martin', 'Barista', B::BritishIrish, ['rtw_check_method' => self::MANUAL, 'rtw_check_date' => '2025-04-01', 'start_date' => '2025-04-07', 'salary' => 21400]);
        $this->employee($cafe, 'Cafe', 'Omar Siddiqui', 'Cafe Supervisor', B::Ilr, ['nationality' => 'Other', 'rtw_check_method' => self::SHARE, 'rtw_check_date' => '2024-11-18', 'start_date' => '2024-11-25', 'salary' => 26000]);
        $this->employee($cafe, 'Cafe', 'Beth Walker', 'Barista', B::BritishIrish, ['rtw_check_method' => self::MANUAL, 'rtw_check_date' => '2025-06-02', 'start_date' => '2025-06-09', 'salary' => 21400, 'days_per_week' => 3, 'contracted_hours' => 22.5, 'contract_type' => 'Part-time permanent']);

        // Change history from the prototype.
        $retailAdmin = User::where('email', 'hr@demo-retail.example')->first();
        $cateringAdmin = User::where('email', 'hr@demo-catering.example')->first();
        $this->change($rahul, $retailAdmin, '2026-09-10', 'job_title', 'Job title', 'Sales Assistant', 'Store Supervisor');
        $this->change($aisha, $retailAdmin, '2026-04-12', 'address', 'Home address', '9 Onslow Road, Southampton SO14 0JD', '41 Shirley High Street, Southampton SO15 3NN');
        $this->change($fatima, $cateringAdmin, '2026-04-01', 'salary', 'Salary – increase', '£40,200.00', '£41,800.00');

        // Kasia has not uploaded her passport yet.
        DocumentRequest::updateOrCreate(
            ['employee_id' => $kasia->id, 'category' => DocumentCategory::Passport],
            ['business_id' => $retail->id, 'status' => DocumentRequest::STATUS_AWAITING, 'requested_by' => $retailAdmin->id],
        )->forceFill(['created_at' => '2026-09-15 10:00:00'])->save();

        SuperAdmin::updateOrCreate(['email' => 'owner@sponsorsafe.example'], ['name' => 'Platform owner', 'password' => 'password']);
    }

    private function business(string $name, string $status, string $label, string $provider, ?string $next, string $adminName, string $adminEmail, array $sites, array $people): Business
    {
        $b = Business::updateOrCreate(['name' => $name], [
            'licence_number' => 'SL'.strtoupper(substr(md5($name), 0, 8)), 'authorising_officer' => $people[0][1],
            'status' => $status, 'payment_label' => $label, 'payment_provider' => $provider, 'next_payment_on' => $next,
            'suspended_at' => $status === 'suspended' ? now() : null,
        ]);
        User::updateOrCreate(['email' => $adminEmail], ['business_id' => $b->id, 'name' => $adminName, 'role' => User::ROLE_ADMIN, 'password' => 'password']);
        foreach ($sites as $site => $address) {
            $b->workSites()->updateOrCreate(['name' => $site], ['address' => $address]);
        }
        foreach ($people as [$role, $person, $email]) {
            KeyPerson::updateOrCreate(['business_id' => $b->id, 'role' => $role, 'name' => $person], ['email' => $email]);
        }

        return $b;
    }

    private function employee(Business $b, string $site, string $name, string $job, B $basis, array $details): Employee
    {
        $email = strtolower(str_replace(' ', '.', $name)).'@'.strtolower(str_replace([' Ltd', ' '], ['', '-'], $b->name)).'.example';
        $checkDate = $details['rtw_check_date'];
        $user = User::updateOrCreate(['email' => $email], [
            'business_id' => $b->id, 'name' => $name, 'role' => User::ROLE_EMPLOYEE, 'password' => 'password',
        ]);
        $user->forceFill(['invited_at' => $checkDate, 'last_login_at' => now()->subDays(2)])->save();

        $employee = Employee::updateOrCreate(['business_id' => $b->id, 'email' => $email], [
            'user_id' => $user->id,
            'work_site_id' => $b->workSites()->where('name', $site)->value('id'),
            'full_name' => $name,
            'job_title' => $job,
            'rtw_basis' => $basis,
            'nationality' => 'British',
            'rtw_checked_by' => $b->admins()->value('name'),
            'share_code' => $basis->usesShareCode() ? strtoupper(substr(md5($name), 0, 9)) : null,
            'visa_type' => $basis->fixedVisaType(),
            'days_per_week' => 5,
            'contracted_hours' => 37.5,
            'contract_type' => 'Permanent',
            ...$details,
            'follow_up_check_due' => $basis->timeLimited() ? $details['visa_expiry'] : null,
        ]);

        $employee->changes()->delete();
        EmployeeChange::forceCreate([
            'business_id' => $b->id, 'employee_id' => $employee->id, 'field' => 'record', 'label' => 'Employee record created',
            'new_value' => $basis->label(), 'changed_by' => $b->admins()->value('id'), 'created_at' => $checkDate.' 09:00:00',
        ]);

        return $employee;
    }

    private function change(Employee $e, User $by, string $date, string $field, string $label, string $from, string $to): void
    {
        EmployeeChange::forceCreate([
            'business_id' => $e->business_id, 'employee_id' => $e->id, 'field' => $field, 'label' => $label,
            'old_value' => $from, 'new_value' => $to, 'changed_by' => $by->id, 'created_at' => $date.' 11:30:00',
        ]);
    }
}
