<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.7: Securities trading */
    public function up(): void
    {
        Schema::create('securities_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('side', 8); // buy, sell
            $table->decimal('quantity', 18, 6);
            $table->decimal('price', 18, 4);
            $table->timestamp('trade_at');
            $table->string('reference', 128)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'trade_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('securities_trades');
    }
};
