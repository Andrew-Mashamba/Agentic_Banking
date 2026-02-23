<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F7.4: Request money */
    public function up(): void
    {
        Schema::create('money_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('status', 32)->default('pending'); // pending, accepted, rejected
            $table->text('message')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->index(['to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('money_requests');
    }
};
