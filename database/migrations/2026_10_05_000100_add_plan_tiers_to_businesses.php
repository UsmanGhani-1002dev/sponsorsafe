<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // starter | standard | corporate; null = the original single plan (£20, up to 15), kept until moved.
            $table->string('plan', 20)->nullable()->after('status');
            // The plan a scheduled move (30 days' notice) goes to, next to price_change_pence/limit.
            $table->string('price_change_plan', 20)->nullable()->after('price_change_limit');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['plan', 'price_change_plan']);
        });
    }
};
