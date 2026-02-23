<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F7.5: Bill split members */
    public function up(): void
    {
        Schema::create('bill_split_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_split_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_owed', 18, 4);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['bill_split_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_split_members');
    }
};
