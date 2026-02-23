<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F1.8: Document upload for KYC */
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_submission_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 64); // id, proof_of_address, etc.
            $table->string('file_path', 1024);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index('kyc_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_documents');
    }
};
