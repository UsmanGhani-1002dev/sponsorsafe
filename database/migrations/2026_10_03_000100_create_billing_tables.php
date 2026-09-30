<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The business is the Stripe customer (Laravel Cashier's billable model), not the user.
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('phone', 50)->nullable()->after('authorising_officer');
            $table->string('employees_band', 10)->nullable()->after('employee_limit'); // from sign-up: 1-5 | 6-10 | 11-15
            $table->string('suspended_reason', 20)->nullable()->after('suspended_at'); // manual | payment | cancelled
            $table->date('payment_failed_on')->nullable()->after('next_payment_on');
            $table->date('grace_ends_on')->nullable()->after('payment_failed_on');
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->string('meter_id')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('meter_event_name')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'stripe_price']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['stripe_id']);
            $table->dropColumn(['phone', 'employees_band', 'suspended_reason', 'payment_failed_on', 'grace_ends_on', 'stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
