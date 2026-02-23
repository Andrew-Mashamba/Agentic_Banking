<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F14.2: Assign approval rights */
    public function up(): void
    {
        Schema::create('user_approval_rights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corporate_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->decimal('max_approvable_amount', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['corporate_user_id', 'approval_workflow_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_approval_rights');
    }
};
