<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.15: Cardless ATM withdrawal */
    public function up(): void
    {
        Schema::create('cardless_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('code', 32);
            $table->timestamp('expires_at');
            $table->string('status', 32)->default('pending'); // pending, used, expired, cancelled
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['code', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardless_withdrawals');
    }
};
