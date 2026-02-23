<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate the table without restaurant foreign keys
        Schema::dropIfExists('whatsapp_sessions');

        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('state')->default('AI_CONVERSATION');
            $table->json('data')->nullable();
            $table->unsignedBigInteger('guest_id')->nullable(); // reused as user_id, no FK constraint
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('guest_id');
            $table->index('state');
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
