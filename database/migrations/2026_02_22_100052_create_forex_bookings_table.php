<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F13.3: Forex booking */
    public function up(): void
    {
        Schema::create('forex_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_user_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('amount', 18, 4);
            $table->decimal('rate', 18, 6);
            $table->string('status', 32)->default('pending'); // pending, confirmed, executed, cancelled
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
            $table->index(['corporate_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forex_bookings');
    }
};
