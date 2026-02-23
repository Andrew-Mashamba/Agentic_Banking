<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F4: Cards management */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('last_four', 4);
            $table->date('expiry_date');
            $table->string('type', 32)->default('physical'); // physical, virtual
            $table->string('status', 32)->default('pending_activation'); // pending_activation, active, frozen, blocked
            $table->timestamp('pin_set_at')->nullable();
            $table->decimal('daily_limit_amount', 18, 4)->nullable();
            $table->boolean('online_enabled')->default(true);
            $table->boolean('international_enabled')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
