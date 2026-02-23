<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.2, F6.3: Fixed deposit, break FD */
    public function up(): void
    {
        Schema::create('fixed_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4);
            $table->unsignedSmallInteger('tenor_months');
            $table->decimal('interest_rate', 8, 4);
            $table->date('maturity_date');
            $table->string('status', 32)->default('active'); // active, matured, broken
            $table->timestamp('break_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposits');
    }
};
