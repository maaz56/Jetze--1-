<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->json('tbo_booking_detail_response')->nullable()->after('tbo_response');
            $table->timestamp('booking_details_checked_at')->nullable()->after('tbo_booking_detail_response');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropColumn(['tbo_booking_detail_response', 'booking_details_checked_at']);
        });
    }
};
