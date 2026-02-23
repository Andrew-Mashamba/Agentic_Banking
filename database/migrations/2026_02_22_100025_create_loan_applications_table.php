<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F5.1, F5.2, F5.7: Loan application, eligibility check */
    public function up(): void
    {
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_requested', 18, 4);
            $table->unsignedSmallInteger('tenor_months');
            $table->string('purpose')->nullable();
            $table->string('status', 32)->default('pending'); // pending, approved, rejected, disbursed
            $table->json('eligibility_result')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
