<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F14.3: Approval workflow steps */
    public function up(): void
    {
        Schema::create('approval_workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('role_required', 64)->nullable();
            $table->decimal('min_amount', 18, 4)->nullable();
            $table->decimal('max_amount', 18, 4)->nullable();
            $table->timestamps();
            $table->index('approval_workflow_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_steps');
    }
};
