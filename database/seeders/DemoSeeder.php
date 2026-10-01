<?php

namespace Database\Seeders;

use App\Enums\AbsenceType;
use App\Enums\ChangeType;
use App\Enums\DocumentCategory;
use App\Enums\RightToWorkBasis as B;
use App\Models\Business;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\ReportTask;
use App\Models\EmployeeRequest;
use App\Models\Enquiry;
use App\Services\AbsenceRecorder;
use App\Services\EmployeeRecorder;
use App\Services\EmployeeRequests;
use App\Services\ReportTasks;
use App\Services\DocumentVault;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
        // Its last payment failed: in the grace period (banner in the app, amber in the super admin list).
        $catering->update(['payment_failed_on' => today()->subDays(2), 'grace_ends_on' => today()->addDays(5)]);
        // Plans: Retail on Standard (£35, up to 10); Catering and the cafe on Starter (£20, up to 5).
        $retail->update(['plan' => 'standard', 'plan_price_pence' => 3500, 'employee_limit' => 10,
            'phone' => '023 8000 4521', 'registered_address' => "14 Above Bar Street\nSouthampton SO14 7DU"]);
        $catering->update(['plan' => 'starter', 'plan_price_pence' => 2000, 'employee_limit' => 5,
            'phone' => '023 8000 7310', 'registered_address' => "Unit 7, Millbrook Trading Estate\nSouthampton SO15 0LD"]);
        $cafe = $this->business('Demo Cafe Ltd', 'suspended', 'Card ending 1881', 'stripe', null, 'Leo Grant', 'hr@demo-cafe.example',
            sites: ['Cafe' => '22 Oxford Street, Southampton SO14 3DJ'],
            people: [['authorising_officer', 'Leo Grant', 'hr@demo-cafe.example']]);
        $cafe->update(['plan' => 'starter', 'plan_price_pence' => 2000, 'employee_limit' => 5]);

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
        $james = $this->employee($retail, 'Second shop', 'James Carter', 'Stock Assistant', B::BritishIrish, [
            'date_of_birth' => '1999-06-21', 'passport_expiry' => '2030-01-09', 'rtw_check_method' => self::MANUAL, 'rtw_check_date' => '2023-02-06',
            'salary' => 19800, 'start_date' => '2023-02-13', 'days_per_week' => 4, 'contracted_hours' => 30,
            'address' => '19 Bitterne Road West, Southampton SO18 1AR', 'phone' => '07700 900103', 'ni_number' => 'QQ553190C', 'passport_number' => '539210877',
        ]);
        $kasia = $this->employee($retail, 'Second shop', 'Kasia Nowak', 'Sales Assistant', B::EussPreSettled, [
            'nationality' => 'Polish', 'date_of_birth' => '1998-03-15', 'passport_expiry' => '2032-07-30', 'rtw_check_method' => self::SHARE, 'rtw_check_date' => '2025-03-03',
            'visa_start' => '2022-02-14', 'visa_expiry' => '2027-02-14', 'salary' => 23900, 'start_date' => '2025-03-10',
            'address' => '5 Lodge Road, Southampton SO14 6RG', 'phone' => '07700 900104', 'ni_number' => 'QQ661024D', 'passport_number' => 'EP7730412',
        ]);
        $daniel = $this->employee($retail, 'Main shop', 'Daniel Okafor', 'Part-time Sales Assistant', B::OtherVisa, [
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
        $tom = $this->employee($catering, 'Central kitchen', 'Tom Richards', 'Kitchen Porter', B::BritishIrish, [
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

        // Documents on file (prototype): [employee, category, file name, uploaded, expiry].
        // Remove only this database's own demo files (never whole folders: another database,
        // e.g. the test one, can use the same business IDs on the same disk).
        Document::whereIn('business_id', [$retail->id, $catering->id])->each(function (Document $d) {
            Storage::disk('local')->delete($d->path);
            $d->delete();
        });
        $files = [
            [$aisha, 'rtw', 'rtw-share-code-check.pdf', '2025-10-28'], [$aisha, 'passport', 'passport-scan.pdf', '2025-10-28', '2031-05-20'],
            [$aisha, 'cos', 'certificate-of-sponsorship.pdf', '2025-09-15'], [$aisha, 'contract', 'employment-contract-signed.pdf', '2025-11-03'],
            [$aisha, 'jd', 'job-description.pdf', '2025-11-03'], [$aisha, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$rahul, 'rtw', 'rtw-share-code-check.pdf', '2024-05-28'], [$rahul, 'passport', 'passport-scan.pdf', '2024-05-28', '2029-08-14'],
            [$rahul, 'cos', 'certificate-of-sponsorship.pdf', '2024-04-10'], [$rahul, 'contract', 'employment-contract-signed.pdf', '2024-06-03'],
            [$rahul, 'jd', 'job-description-store-supervisor.pdf', '2026-09-10'], [$rahul, 'recruit', 'job-advert-and-interview-notes.pdf', '2024-04-02'],
            [$rahul, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$james, 'rtw', 'passport-check-copy-signed-dated.pdf', '2023-02-06'], [$james, 'passport', 'passport-scan.pdf', '2023-02-06', '2030-01-09'],
            [$james, 'contract', 'employment-contract-signed.pdf', '2023-02-13'], [$james, 'jd', 'job-description.pdf', '2023-02-13'], [$james, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$kasia, 'rtw', 'rtw-share-code-check.pdf', '2025-03-03'], [$kasia, 'contract', 'employment-contract-signed.pdf', '2025-03-10'],
            [$kasia, 'jd', 'job-description.pdf', '2025-03-10'], [$kasia, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$daniel, 'rtw', 'rtw-share-code-check.pdf', '2025-08-20'], [$daniel, 'passport', 'passport-scan.pdf', '2025-08-20', '2030-10-11'],
            [$daniel, 'contract', 'employment-contract-signed.pdf', '2025-09-01'], [$daniel, 'jd', 'job-description.pdf', '2025-09-01'], [$daniel, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$fatima, 'rtw', 'rtw-share-code-check.pdf', '2024-09-16'], [$fatima, 'passport', 'passport-scan.pdf', '2024-09-16', '2028-12-01'],
            [$fatima, 'cos', 'certificate-of-sponsorship.pdf', '2024-08-01'], [$fatima, 'contract', 'employment-contract-signed.pdf', '2024-09-23'],
            [$fatima, 'jd', 'job-description-chef.pdf', '2024-09-23'], [$fatima, 'recruit', 'job-advert-and-interview-notes.pdf', '2024-07-15'],
            [$fatima, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
            [$tom, 'rtw', 'idvt-check-report.pdf', '2025-01-06'], [$tom, 'passport', 'passport-scan.pdf', '2025-01-06', '2033-03-18'],
            [$tom, 'contract', 'employment-contract-signed.pdf', '2025-01-13'], [$tom, 'jd', 'job-description.pdf', '2025-01-13'], [$tom, 'payroll', 'payslips-2026.pdf', '2026-09-01'],
        ];
        foreach ($files as $f) {
            $this->document($f[0], $f[1], $f[2], $f[3], $f[4] ?? null);
        }

        // Absences (prototype). Any reportable one creates its Home Office task as it is recorded.
        ReportTask::whereIn('business_id', [$retail->id, $catering->id])->delete();
        $absences = [
            [$rahul, AbsenceType::Unauthorised, '2026-06-01', '2026-06-12', 'No contact, later returned'],
            [$aisha, AbsenceType::Unpaid, '2026-03-02', '2026-03-09', 'Family matter'],
            [$aisha, AbsenceType::Annual, '2026-08-10', '2026-08-14', 'Holiday'],
            [$rahul, AbsenceType::SickSelf, '2026-09-14', '2026-09-15', 'Unwell'],
            [$james, AbsenceType::Annual, '2026-07-20', '2026-07-24', 'Holiday'],
            [$kasia, AbsenceType::Annual, '2026-04-07', '2026-04-10', 'Easter break'],
            [$kasia, AbsenceType::SickFitNote, '2026-05-11', '2026-05-22', 'Fit note received', 'fit-note-may-2026.pdf'],
            [$daniel, AbsenceType::Annual, '2026-09-07', '2026-09-08', 'Graduation'],
            [$fatima, AbsenceType::Unpaid, '2026-02-02', '2026-02-13', 'Family visit abroad'],
            [$fatima, AbsenceType::Annual, '2026-07-06', '2026-07-10', 'Holiday'],
            [$tom, AbsenceType::Annual, '2026-08-17', '2026-08-21', 'Holiday'],
        ];
        $recorder = app(AbsenceRecorder::class);
        foreach ($absences as $a) {
            [$employee, $type, $start, $end, $reason] = $a;
            $employee->absences()->delete();
        }
        foreach ($absences as $a) {
            [$employee, $type, $start, $end, $reason] = $a;
            $admin = $employee->business->admins()->first();
            $fitNote = isset($a[5]) ? $this->upload($a[5], 'Fit note') : null;
            $absence = $recorder->record($employee, $type, $start, $end, $reason, $fitNote, $admin);
            $absence->fitNote?->forceFill(['created_at' => $end.' 16:00:00', 'updated_at' => $end.' 16:00:00'])->save();
        }

        // Home Office tasks from the prototype: Rahul's streak was reported on time; his promotion is still to report.
        $streak = ReportTask::where('employee_id', $rahul->id)->where('source', 'absence')->sole();
        ReportTasks::markReported($streak, '2026-06-15', 'Nadia Khan', 'SMS-4471920', $retailAdmin);
        $promotion = $rahul->changes()->where('field', 'job_title')->sole();
        ReportTasks::forChange($rahul->setRelation('business', $retail), $promotion, ChangeType::JobTitle, $retailAdmin, '2026-09-10');
        ReportTasks::manual($catering, ReportTask::COMPANY, null, 'Registered or trading address changed', '2026-09-01', $cateringAdmin);

        // A leaver from last year, now due for deletion (shows the retention review).
        $priya = $this->employee($retail, 'Second shop', 'Priya Shah', 'Sales Assistant', B::BritishIrish, [
            'date_of_birth' => '1994-02-17', 'rtw_check_method' => self::MANUAL, 'rtw_check_date' => '2023-05-02', 'start_date' => '2023-05-08',
            'salary' => 21000, 'address' => '4 Lodge Road, Southampton SO14 6RG', 'phone' => '07700 900108',
        ]);
        foreach ([['rtw', 'passport-check-copy-signed-dated.pdf', '2023-05-02'], ['contract', 'employment-contract-signed.pdf', '2023-05-08'], ['payroll', 'payslips-2025.pdf', '2025-06-30']] as [$cat, $file, $date]) {
            $this->document($priya, $cat, $file, $date, null);
        }
        app(EmployeeRecorder::class)->end($priya->setRelation('business', $retail), '2025-06-30', 'Resigned', $retailAdmin);

        // Employee portal requests waiting in HR's inbox (prototype).
        EmployeeRequest::whereIn('business_id', [$retail->id, $catering->id])->delete();
        $requests = app(EmployeeRequests::class);
        $sent = [
            ['2026-09-21', fn () => $requests->leave($kasia, AbsenceType::Annual, '2026-10-12', '2026-10-16', 'Family visit', $kasia->user)],
            ['2026-09-22', fn () => $requests->change($rahul, 'address', '22 Portswood Road, Southampton SO17 2EY', 'Moving on 1 October', $rahul->user)],
            ['2026-09-23', fn () => $requests->change($aisha, 'visa', '2029-12-10', 'Extension granted. New share code: W8K 2PQ 7RT', $aisha->user)],
            ['2026-09-20', fn () => $requests->leave($fatima, AbsenceType::Unpaid, '2026-10-19', '2026-11-06', 'Family wedding abroad', $fatima->user)],
        ];
        foreach ($sent as [$date, $send]) {
            $send()->forceFill(['created_at' => $date.' 09:15:00', 'updated_at' => $date.' 09:15:00'])->save();
        }
        // An earlier request, already approved: Aisha's August holiday.
        EmployeeRequest::forceCreate([
            'business_id' => $retail->id, 'employee_id' => $aisha->id, 'kind' => 'leave', 'leave_type' => AbsenceType::Annual,
            'start_date' => '2026-08-10', 'end_date' => '2026-08-14', 'note' => 'Holiday', 'status' => EmployeeRequest::APPROVED,
            'hr_note' => 'Approved', 'decided_by' => $retailAdmin->id, 'decided_at' => '2026-07-22 14:00:00',
            'absence_id' => $aisha->absences()->where('start_date', '2026-08-10')->value('id'), 'created_at' => '2026-07-20 08:30:00',
        ]);

        $owner = SuperAdmin::updateOrCreate(['email' => 'owner@sponsorsafe.example'], ['name' => 'Platform owner', 'password' => 'password']);

        // Website enquiries (prototype super admin).
        Enquiry::query()->delete();
        foreach ([
            ['Tom Hughes', 'tom@example.co.uk', 'General question', 'Does it work for Health and Care Worker visas?', '2026-09-18 11:20', true],
            ['Priya Shah', 'office@demo-care.example', '1-to-1 training', 'Training for 2 people please (office manager and me).', '2026-09-21 09:05', false],
            ['Imran Ali', 'imran@example.co.uk', 'Book a free demo', 'We have 6 staff, 3 sponsored. Can we see it working?', '2026-09-23 16:40', false],
        ] as [$name, $email, $topic, $message, $at, $handled]) {
            Enquiry::forceCreate([
                'name' => $name, 'email' => $email, 'topic' => $topic, 'message' => $message, 'source' => 'website',
                'status' => $handled ? Enquiry::HANDLED : Enquiry::NEW, 'handled_by' => $handled ? $owner->id : null,
                'handled_at' => $handled ? '2026-09-18 15:00' : null, 'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    private function document(Employee $e, string $category, string $name, string $uploaded, ?string $expires): void
    {
        $admin = $e->business->admins()->first();
        $doc = app(DocumentVault::class)->store($e, $this->upload($name, DocumentCategory::from($category)->label()), DocumentCategory::from($category), $expires, $admin);
        $doc->forceFill(['created_at' => $uploaded.' 10:00:00', 'updated_at' => $uploaded.' 10:00:00'])->save();
    }

    /** A small real PDF, so demo documents open in the browser. */
    private function upload(string $name, string $title): UploadedFile
    {
        $text = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        $stream = 'BT /F1 18 Tf 72 720 Td ('.$text($title).") Tj ET\nBT /F1 11 Tf 72 696 Td (".$text($name.' - demo document, SponsorSafe sample data').') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn ($o) => sprintf("%010d 00000 n \n", $o), $offsets));
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        $path = tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($path, $pdf);

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function business(string $name, string $status, string $label, string $provider, ?string $next, string $adminName, string $adminEmail, array $sites, array $people): Business
    {
        $b = Business::updateOrCreate(['name' => $name], [
            'licence_number' => 'SL'.strtoupper(substr(md5($name), 0, 8)), 'authorising_officer' => $people[0][1],
            'status' => $status, 'payment_label' => $label, 'payment_provider' => $provider, 'next_payment_on' => $next,
            'suspended_at' => $status === 'suspended' ? now() : null, 'suspended_reason' => $status === 'suspended' ? Business::SUSPENDED_MANUAL : null,
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
