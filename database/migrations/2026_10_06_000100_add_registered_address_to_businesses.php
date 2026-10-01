<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings → Business: a change of registered or trading address is a company-level Home Office event (§4).
        Schema::table('businesses', function (Blueprint $table) {
            $table->text('registered_address')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('registered_address');
        });
    }
};
