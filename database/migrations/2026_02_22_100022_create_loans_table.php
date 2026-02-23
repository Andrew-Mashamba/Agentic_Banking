<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F5.3, F5.4: Loans, balance, repayment schedule */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->decimal('outstanding_balance', 18, 4);
            $table->decimal('interest_rate', 8, 4);
            $table->string('status', 32)->default('pending'); // pending, disbursed, active, closed, defaulted
            $table->timestamp('disbursed_at')->nullable();
            $table->date('maturity_date')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
