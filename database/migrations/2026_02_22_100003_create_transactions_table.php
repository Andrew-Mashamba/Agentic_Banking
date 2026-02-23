<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F2: Mini statement, transaction history, statements & reports */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // credit, debit
            $table->decimal('amount', 18, 4);
            $table->decimal('balance_after', 18, 4)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'created_at']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
