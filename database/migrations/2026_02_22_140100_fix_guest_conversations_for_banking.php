<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate without FK constraint (guest_id is reused as user_id)
        Schema::dropIfExists('guest_conversations');

        Schema::create('guest_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('guest_id'); // reused as user_id, no FK
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->string('message_type')->default('text');
            $table->timestamps();

            $table->index(['guest_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_conversations');
    }
};
