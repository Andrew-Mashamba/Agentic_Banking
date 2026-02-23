<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.11: Multi-level approval for transfers */
    public function up(): void
    {
        Schema::create('transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('level');
            $table->string('status', 32)->default('pending'); // pending, approved, rejected
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['transfer_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_approvals');
    }
};
