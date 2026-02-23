<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.7, F6.8: Securities holdings for portfolio */
    public function up(): void
    {
        Schema::create('securities_holdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('name')->nullable();
            $table->decimal('quantity', 18, 6);
            $table->decimal('average_cost', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('securities_holdings');
    }
};
