<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F7.3: Gift cards */
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchaser_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_phone', 32)->nullable();
            $table->string('recipient_email')->nullable();
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->string('code', 64)->unique();
            $table->string('status', 32)->default('active'); // active, redeemed, expired
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->index(['purchaser_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
