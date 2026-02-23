<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail for banking actions (transfers, card actions, loan applications, etc.)
     * used by compliance and AI-driven actions from WhatsApp or API.
     */
    public function up(): void
    {
        Schema::create('banking_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 64); // e.g. transfer, card_freeze, card_block, loan_application
            $table->string('resource_type', 64)->nullable(); // e.g. transfer, card, loan_application
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('channel', 32)->default('api'); // whatsapp, api
            $table->string('session_id', 64)->nullable();
            $table->json('metadata')->nullable(); // amount, reference, etc. (no PII)
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banking_audit_logs');
    }
};
