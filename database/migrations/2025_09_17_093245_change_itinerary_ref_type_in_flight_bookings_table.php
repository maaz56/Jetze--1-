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
    Schema::table('flight_bookings', function (Blueprint $table) {
        $table->text('itinerary_ref')->nullable()->change();
    });
}

public function down(): void
{
    Schema::table('flight_bookings', function (Blueprint $table) {
        $table->string('itinerary_ref')->nullable()->change();
    });
}

};
