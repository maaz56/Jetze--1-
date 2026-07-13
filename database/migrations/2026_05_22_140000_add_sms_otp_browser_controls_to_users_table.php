<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('sms_otp_disabled')->default(false)->after('is_card_allowed');
            $table->string('sms_otp_disabled_browser_id', 191)->nullable()->after('sms_otp_disabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['sms_otp_disabled', 'sms_otp_disabled_browser_id']);
        });
    }
};
