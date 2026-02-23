<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** F1, F12: Login PIN, transaction PIN, biometric, primary account */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('password');
            $table->string('transaction_pin_hash')->nullable()->after('pin_hash');
            $table->boolean('biometric_enabled')->default(false)->after('transaction_pin_hash');
            $table->foreignId('primary_account_id')->nullable()->after('biometric_enabled')->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['primary_account_id']);
            $table->dropColumn(['pin_hash', 'transaction_pin_hash', 'biometric_enabled', 'primary_account_id']);
        });
    }
};
