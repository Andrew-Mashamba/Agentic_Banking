<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F5.4: Repayment schedule instalments */
    public function up(): void
    {
        Schema::create('loan_repayment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->date('due_date');
            $table->decimal('principal_amount', 18, 4);
            $table->decimal('interest_amount', 18, 4)->default(0);
            $table->string('status', 32)->default('pending'); // pending, paid, overdue
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_repayment_schedules');
    }
};
