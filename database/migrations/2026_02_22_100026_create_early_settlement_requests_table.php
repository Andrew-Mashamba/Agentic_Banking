<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F5.5: Early settlement request */
    public function up(): void
    {
        Schema::create('early_settlement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->decimal('settlement_amount', 18, 4);
            $table->string('status', 32)->default('pending'); // pending, approved, processed, rejected
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('early_settlement_requests');
    }
};
