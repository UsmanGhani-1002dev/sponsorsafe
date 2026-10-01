<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reminders already emailed (compliance-rules §12), so each stage goes out once:
        // e.g. visa expiry "60@2026-12-10" for employee 12 is never sent twice.
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);        // visa_expiry | follow_up_check | passport_expiry | task_deadline | retention_due
            $table->string('subject', 40);     // "employee:12", "report_task:5", "business"
            $table->string('stage', 40);       // "90@2026-12-10", "overdue@2026-10-02", "2026-10"
            $table->timestamp('sent_at');
            $table->unique(['business_id', 'type', 'subject', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
