<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F11.3: Locate ATMs */
    public function up(): void
    {
        Schema::create('atms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atms');
    }
};
