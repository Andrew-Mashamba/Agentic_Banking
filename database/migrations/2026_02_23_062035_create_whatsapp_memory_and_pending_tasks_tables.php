<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Long-term memory and pending tasks for WhatsApp AI.
     * User identified by user_id (resolved from phone_number); PIN is for verification only.
     */
    public function up(): void
    {
        Schema::create('whatsapp_user_memory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->longText('memory_text')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('user_id');
        });

        Schema::create('whatsapp_pending_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone_number', 32);
            $table->string('task_type', 64);
            $table->string('step', 128)->nullable();
            $table->json('context')->nullable();
            $table->enum('status', ['pending', 'completed', 'abandoned'])->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['phone_number', 'status']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_pending_tasks');
        Schema::dropIfExists('whatsapp_user_memory');
    }
};
