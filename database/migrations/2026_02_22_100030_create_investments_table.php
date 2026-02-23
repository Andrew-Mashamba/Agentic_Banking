<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.5, F6.6: User investments in products */
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->decimal('units', 18, 6)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
