<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.9: Dividend tracking */
    public function up(): void
    {
        Schema::create('dividends', function (Blueprint $table) {
            $table->id();
            $table->string('investable_type', 128); // e.g. App\Models\Investment, App\Models\SecuritiesHolding
            $table->unsignedBigInteger('investable_id');
            $table->decimal('amount', 18, 4);
            $table->date('paid_at');
            $table->string('reference', 128)->nullable();
            $table->timestamps();
            $table->index(['investable_type', 'investable_id']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividends');
    }
};
