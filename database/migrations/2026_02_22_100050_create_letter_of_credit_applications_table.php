<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F13.1: Letter of Credit application */
    public function up(): void
    {
        Schema::create('letter_of_credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('currency', 3)->default('USD');
            $table->json('details')->nullable();
            $table->string('status', 32)->default('pending'); // pending, approved, issued, rejected
            $table->timestamps();
            $table->index(['corporate_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letter_of_credit_applications');
    }
};
