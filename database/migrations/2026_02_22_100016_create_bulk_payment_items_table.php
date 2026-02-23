<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.10: Bulk payment line items */
    public function up(): void
    {
        Schema::create('bulk_payment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_payment_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('beneficiary_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('status', 32)->default('pending');
            $table->foreignId('transfer_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('bulk_payment_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_payment_items');
    }
};
