<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F2: Account Management & Dashboard */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // current, savings, fixed_deposit
            $table->string('account_number', 64)->unique();
            $table->string('currency', 3)->default('USD');
            $table->decimal('balance', 18, 4)->default(0);
            $table->string('nickname')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status', 32)->default('active'); // active, dormant, closed
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
