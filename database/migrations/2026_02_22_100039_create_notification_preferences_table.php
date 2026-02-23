<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F8: Alerts & notification preferences */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32); // push, sms, email
            $table->boolean('transaction_alerts')->default(true);
            $table->boolean('low_balance_alerts')->default(true);
            $table->decimal('low_balance_threshold', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
