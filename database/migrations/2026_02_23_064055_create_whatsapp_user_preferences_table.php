<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->timestamp('consent_given_at')->nullable();
            $table->string('consent_version', 32)->nullable();
            $table->boolean('prefer_human_agent')->default(false);
            $table->boolean('disable_long_term_memory')->default(false);
            $table->string('preferred_language', 16)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_user_preferences');
    }
};
