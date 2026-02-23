<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F13.4, F14.1: Sub-users under corporate account */
    public function up(): void
    {
        Schema::create('corporate_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 64);
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->unique(['corporate_account_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_users');
    }
};
