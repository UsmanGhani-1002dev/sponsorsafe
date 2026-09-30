<?php

use App\Services\ReportTasks;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Home Office report tasks (compliance-rules §4). The business reports on the SMS itself and ticks it off here.
        Schema::create('report_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('level');                              // worker | company
            $table->foreignId('employee_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->date('trigger_on');
            $table->date('deadline');
            $table->string('source');                             // absence | change | work_site | key_personnel | leaver | manual
            $table->nullableMorphs('subject');                    // the record that caused it
            $table->string('status')->default('pending');         // pending | reported | not_required
            $table->date('reported_on')->nullable();
            $table->string('reported_by')->nullable();            // name typed by the admin (may differ from who ticked it)
            $table->string('notes', 500)->nullable();             // SMS reference, or the reason it is not required
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['business_id', 'status', 'deadline']);
            $table->index(['employee_id', 'status']);
        });

        // End of employment (§10): delete-after dates for the retention review.
        Schema::table('employees', function (Blueprint $table) {
            $table->date('delete_after')->nullable()->after('end_reason');
            $table->date('rtw_delete_after')->nullable()->after('delete_after');
        });

        // Anything reportable recorded before this stage gets its task now, so nothing is missed.
        ReportTasks::backfill();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['delete_after', 'rtw_delete_after']);
        });
        Schema::dropIfExists('report_tasks');
    }
};
