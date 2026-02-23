<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F6.5, F6.6: Investment product definitions */
    public function up(): void
    {
        Schema::create('investment_products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32); // tbills, bond, mutual_fund
            $table->string('name');
            $table->string('code', 64)->nullable();
            $table->decimal('min_amount', 18, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_products');
    }
};
