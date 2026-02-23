<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F13, F14: Corporate banking */
    public function up(): void
    {
        Schema::create('corporate_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('primary_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('company_name');
            $table->string('registration_number', 64)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index('primary_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_accounts');
    }
};
