<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.16, F5: Loan repayment records */
    public function up(): void
    {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->timestamp('paid_at');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['loan_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayments');
    }
};
