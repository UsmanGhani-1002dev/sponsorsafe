<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Private files (compliance-rules §2). Contents are encrypted on the private disk; only the path is stored.
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedInteger('size'); // bytes, before encryption
            $table->date('expires_on')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_via')->default('hr'); // hr | portal
            $table->timestamps();
            $table->index(['employee_id', 'category']);
        });

        // Absence log (§3). The Home Office check result is stored so Stage 4 can create the report task.
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('working_days');
            $table->string('reason', 150)->nullable(); // short text only: no medical detail
            $table->foreignId('fit_note_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->string('check_status');            // none | not_yet | report
            $table->date('report_trigger_on')->nullable();
            $table->date('report_deadline')->nullable();
            $table->string('report_event')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('hr');   // hr | portal (Stage 5) | clock-in (Stage 8)
            $table->timestamps();
            $table->index(['employee_id', 'start_date']);
            $table->index(['business_id', 'start_date']);
        });

        Schema::table('document_requests', function (Blueprint $table) {
            $table->foreignId('document_id')->nullable()->after('status')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('document_id');
        });
        Schema::dropIfExists('absences');
        Schema::dropIfExists('documents');
    }
};
