<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.10: Bulk payments (salary / payroll) */
    public function up(): void
    {
        Schema::create('bulk_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->string('status', 32)->default('pending'); // pending, processing, completed, failed
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_payment_batches');
    }
};
