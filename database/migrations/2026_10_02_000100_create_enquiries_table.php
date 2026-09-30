<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Messages from the website contact form (and, in Stage 7c, leads from the AI chat).
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 50)->nullable();
            $table->string('topic');
            $table->text('message');
            $table->string('source')->default('website');   // website | ai_chat
            $table->json('transcript')->nullable();          // AI chat conversation (Stage 7c)
            $table->string('status')->default('new');        // new | handled
            $table->foreignId('handled_by')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
