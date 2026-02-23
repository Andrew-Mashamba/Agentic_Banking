<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F5: Loan product definitions */
    public function up(): void
    {
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('min_amount', 18, 4);
            $table->decimal('max_amount', 18, 4);
            $table->decimal('interest_rate', 8, 4);
            $table->unsignedSmallInteger('tenor_months');
            $table->json('eligibility_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
