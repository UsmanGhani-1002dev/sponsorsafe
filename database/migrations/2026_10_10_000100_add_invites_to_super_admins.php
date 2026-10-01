<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Super admins can add other super admins from the super admin area: an emailed set-password link. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            $table->string('password_token', 64)->nullable()->index()->after('password');
            $table->timestamp('password_token_expires_at')->nullable()->after('password_token');
            $table->timestamp('invited_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn(['password_token', 'password_token_expires_at', 'invited_at']);
        });
    }
};
