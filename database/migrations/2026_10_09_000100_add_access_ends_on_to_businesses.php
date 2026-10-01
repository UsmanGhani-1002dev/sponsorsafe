<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A cancelled subscription stays open until the end of the period already paid for (terms of service).
        Schema::table('businesses', function (Blueprint $table) {
            $table->date('access_ends_on')->nullable()->after('grace_ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('access_ends_on');
        });
    }
};
