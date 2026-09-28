<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('licence_number')->nullable();
            $table->string('authorising_officer')->nullable();
            $table->string('status')->default('active'); // active | suspended
            $table->unsignedInteger('plan_price_pence')->default(2000);
            $table->unsignedSmallInteger('employee_limit')->default(15);
            $table->string('payment_provider')->nullable(); // stripe | paypal
            $table->string('payment_label')->nullable();    // e.g. "Card ending 4242"
            $table->date('next_payment_on')->nullable();
            $table->json('settings')->nullable();          // compliance rule overrides
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('employee')->after('email'); // admin | employee
            $table->boolean('active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable();
            $table->index(['business_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropIndex(['business_id', 'role']);
            $table->dropColumn(['role', 'active', 'last_login_at']);
        });
        Schema::dropIfExists('businesses');
    }
};
