<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // PayPal: the subscription (I-…) and the billing plan (P-…) it is on.
            $table->string('paypal_subscription_id')->nullable()->index();
            $table->string('paypal_plan_id')->nullable();
            // A move to the current plan, emailed 30 days ahead and applied by billing:check on the date.
            $table->unsignedInteger('price_change_pence')->nullable();
            $table->unsignedSmallInteger('price_change_limit')->nullable();
            $table->date('price_change_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['paypal_subscription_id']);
            $table->dropColumn(['paypal_subscription_id', 'paypal_plan_id', 'price_change_pence', 'price_change_limit', 'price_change_on']);
        });
    }
};
