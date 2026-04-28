<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            // Transaction ID (TID)
            $table->string('tid')
                ->nullable()
                ->after('id');

            // Transaction Status
            $table->string('t_status', 20)
                ->nullable()
                ->after('tid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropColumn(['tid', 't_status']);
        });
    }
};
