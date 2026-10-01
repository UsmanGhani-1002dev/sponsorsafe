<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which days a person normally works ("mon".."sun"); null = Monday to Friday.
        Schema::table('employees', function (Blueprint $table) {
            $table->json('work_days')->nullable()->after('days_per_week');
        });

        // Clock-in data (compliance-rules §11): only used to spot unexplained absences; the absence log stays the record.
        Schema::create('clock_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('first_in')->nullable();
            $table->string('source', 20); // csv | integration
            $table->timestamp('imported_at');
            $table->unique(['employee_id', 'date']);
            $table->index(['business_id', 'date']);
        });

        // A scheduled working day with no clock-in and no absence. Open until classified.
        Schema::create('unexplained_absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('open'); // open | absence | worked
            $table->foreignId('absence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unexplained_absences');
        Schema::dropIfExists('clock_ins');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('work_days');
        });
    }
};
