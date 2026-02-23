<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.10: Dividend reinvestment */
    public function up(): void
    {
        Schema::create('dividend_reinvestment_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('investable_type', 128);
            $table->unsignedBigInteger('investable_id');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'investable_type', 'investable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividend_reinvestment_settings');
    }
};
