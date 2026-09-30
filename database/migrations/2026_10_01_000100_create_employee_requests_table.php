<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requests from the employee portal to HR (compliance-rules §6).
        Schema::create('employee_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kind');                        // leave | sickness | change | document
            $table->string('leave_type')->nullable();      // AbsenceType value for leave
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('change_kind')->nullable();     // address | phone | email | name | visa
            $table->string('value', 500)->nullable();      // the new value for a change
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 300)->nullable();       // from the employee
            $table->string('status')->default('pending');  // pending | approved | declined
            $table->string('hr_note', 300)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('absence_id')->nullable()->constrained()->nullOnDelete(); // created on approval
            $table->timestamps();
            $table->index(['business_id', 'status']);
            $table->index(['employee_id', 'created_at']);
        });

        // Portal uploads wait for HR before they count as "on file".
        Schema::table('documents', function (Blueprint $table) {
            $table->string('review_status')->nullable()->after('uploaded_via'); // null (filed) | pending
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });
        Schema::dropIfExists('employee_requests');
    }
};
