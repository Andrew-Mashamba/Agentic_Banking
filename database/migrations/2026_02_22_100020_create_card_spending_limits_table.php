<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F4.3: Card spending category controls */
    public function up(): void
    {
        Schema::create('card_spending_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('category', 32); // online, international, pos, atm
            $table->decimal('limit_amount', 18, 4)->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['card_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_spending_limits');
    }
};
