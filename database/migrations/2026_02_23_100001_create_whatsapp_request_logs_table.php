<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prompt versioning: log each AI request with version/hash for audit.
     */
    public function up(): void
    {
        Schema::create('whatsapp_request_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('phone_number', 32);
            $table->string('prompt_version', 64)->nullable();
            $table->string('prompt_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_request_logs');
    }
};
