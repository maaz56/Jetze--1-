<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->json('tbo_cancel_response')->nullable()->after('tbo_booking_detail_response');
            $table->timestamp('cancelled_at')->nullable()->after('booking_details_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('hotel_bookings', function (Blueprint $table) {
            $table->dropColumn(['tbo_cancel_response', 'cancelled_at']);
        });
    }
};
