<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address', 500); // full address and postcode
            $table->date('closed_on')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete(); // portal login
            $table->foreignId('work_site_id')->nullable()->constrained()->nullOnDelete();

            // Personal
            $table->string('full_name');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('address', 500)->nullable();
            $table->text('ni_number')->nullable();        // encrypted
            $table->text('passport_number')->nullable();  // encrypted
            $table->date('passport_expiry')->nullable();

            // Right to work (compliance-rules.md §1)
            $table->string('rtw_basis');
            $table->string('visa_type')->nullable();
            $table->string('rtw_check_method');
            $table->date('rtw_check_date');
            $table->string('rtw_checked_by');
            $table->text('share_code')->nullable();       // encrypted
            $table->date('visa_start')->nullable();
            $table->date('visa_expiry')->nullable();
            $table->string('work_restrictions')->nullable();
            $table->date('follow_up_check_due')->nullable();

            // Sponsorship (must match the CoS)
            $table->string('cos_number')->nullable();
            $table->date('cos_assigned_on')->nullable();
            $table->string('soc_code')->nullable();

            // Job and pay
            $table->string('job_title');
            $table->decimal('salary', 10, 2)->nullable(); // annual, £
            $table->date('start_date');
            $table->decimal('days_per_week', 3, 1);
            $table->decimal('contracted_hours', 5, 2);
            $table->string('contract_type');

            // End of employment (Stage 6 fills these)
            $table->date('ended_on')->nullable();
            $table->string('end_reason')->nullable();

            $table->timestamps();
            $table->unique(['business_id', 'email']);
            $table->index(['business_id', 'ended_on']);
        });

        // Change history (§5): every edit, field by field.
        Schema::create('employee_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('field');           // column name, or "record" for creation
            $table->string('label');           // plain-English field name
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('report_task_id')->nullable(); // linked Home Office task (Stage 4)
            $table->timestamp('created_at')->useCurrent();
            $table->index(['employee_id', 'created_at']);
        });

        // Documents HR has asked the employee to upload in the portal (§2). Uploads arrive in Stage 3.
        Schema::create('document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('status')->default('awaiting_employee');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'status']);
        });

        // Sponsor Management System key personnel, kept for reporting changes (Settings).
        Schema::create('key_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // authorising_officer | key_contact | level1_user
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'role']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Invites and password resets: a single-use set-password link (only the SHA-256 hash is stored).
            $table->string('password_token', 64)->nullable()->unique()->after('password');
            $table->timestamp('password_token_expires_at')->nullable()->after('password_token');
            $table->timestamp('invited_at')->nullable()->after('password_token_expires_at');
            // Authenticator-app 2FA: required for business admins, optional for employees.
            $table->text('two_factor_secret')->nullable()->after('invited_at');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['password_token']);
            $table->dropColumn(['password_token', 'password_token_expires_at', 'invited_at', 'two_factor_secret', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('key_personnel');
        Schema::dropIfExists('document_requests');
        Schema::dropIfExists('employee_changes');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('work_sites');
    }
};
