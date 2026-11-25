<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The minimum salary allowed for a sponsored worker's visa, when it differs from the business's general threshold
 * (Settings → Compliance rules → `sponsored_min_salary`): a higher going rate for their SOC code, or a lower minimum
 * that applies to them (for example a new entrant). Null = the general threshold applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('min_salary', 10, 2)->nullable()->after('salary');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('min_salary');
        });
    }
};
