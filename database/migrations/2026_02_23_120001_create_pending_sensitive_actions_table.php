<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending sensitive actions (transfer, card_block, loan_application) requiring OTP confirmation.
     */
    public function up(): void
    {
        Schema::create('pending_sensitive_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 32); // transfer, card_block, loan_application
            $table->json('payload'); // action parameters
            $table->string('otp_hash', 64); // hashed 6-digit OTP
            $table->string('token', 64)->unique(); // public token for confirm request
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'used', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_sensitive_actions');
    }
};
