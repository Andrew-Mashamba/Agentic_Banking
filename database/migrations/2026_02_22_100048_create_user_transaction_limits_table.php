<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F12.4: Transaction limit settings */
    public function up(): void
    {
        Schema::create('user_transaction_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('limit_type', 32); // daily, per_transaction
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
            $table->unique(['user_id', 'limit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_transaction_limits');
    }
};
