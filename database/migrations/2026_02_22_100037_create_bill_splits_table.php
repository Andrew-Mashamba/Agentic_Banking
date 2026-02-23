<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F7.5: Split bills */
    public function up(): void
    {
        Schema::create('bill_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('total_amount', 18, 4);
            $table->string('description')->nullable();
            $table->string('status', 32)->default('pending'); // pending, settled
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['created_by_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_splits');
    }
};
