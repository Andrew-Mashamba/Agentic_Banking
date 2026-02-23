<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Open Banking style: purpose, direct_benefit, data_requested, duration, explicit agreement.
     */
    public function up(): void
    {
        Schema::table('whatsapp_user_preferences', function (Blueprint $table) {
            $table->json('consent_parameters')->nullable()->after('consent_version');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_user_preferences', function (Blueprint $table) {
            $table->dropColumn('consent_parameters');
        });
    }
};
