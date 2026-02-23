<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.8, F3.9: Save beneficiaries, bulk beneficiary upload */
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('account_number', 64)->nullable();
            $table->string('bank_code', 32)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('type', 32); // same_bank, interbank, mobile_wallet, international
            $table->string('mobile_wallet_provider', 64)->nullable();
            $table->string('mobile_number', 32)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
