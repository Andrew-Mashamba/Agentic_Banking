<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F3.11, F14.3: Multi-level approval, transaction authorization workflows */
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32); // transfer, bulk
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('corporate_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
};
