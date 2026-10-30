<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->string('status')->default('booked')->after('total_amount');
        });

        DB::table('flight_passengers')
            ->join('flight_bookings', 'flight_bookings.id', '=', 'flight_passengers.booking_id')
            ->update([
                'flight_passengers.status' => DB::raw('flight_bookings.status'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
