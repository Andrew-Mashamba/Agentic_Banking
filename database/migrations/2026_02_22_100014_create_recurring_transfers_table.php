<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.7: Recurring transfer / standing orders. F2.11: Upcoming payments */
    public function up(): void
    {
        Schema::create('recurring_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('frequency', 32); // daily, weekly, monthly
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index(['user_id', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_transfers');
    }
};
