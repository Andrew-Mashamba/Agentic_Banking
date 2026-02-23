<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.1–F3.7, F3.12–F3.18: All transfer & payment types */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('beneficiary_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('type', 32); // own, same_bank, interbank, swift, mobile_wallet, utility, bill, merchant, cardless, loan, tax, insurance, bulk
            $table->string('status', 32)->default('pending'); // pending, approved, executed, failed, cancelled
            $table->string('reference', 128)->nullable();
            $table->json('swift_details')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->foreignId('approval_workflow_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['from_account_id', 'created_at']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
