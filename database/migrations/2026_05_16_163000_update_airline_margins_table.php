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
        Schema::table('airline_margins', function (Blueprint $table) {
            $table->dropColumn(['travel_start_date', 'travel_end_date', 'ticketing_start_date', 'ticketing_end_date']);
            
            $table->json('origins')->nullable();
            $table->json('destinations')->nullable();
            $table->json('rbd_codes')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('price_option')->default('Net Net');
            $table->decimal('price_value', 10, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airline_margins', function (Blueprint $table) {
            $table->date('travel_start_date')->nullable();
            $table->date('travel_end_date')->nullable();
            $table->date('ticketing_start_date')->nullable();
            $table->date('ticketing_end_date')->nullable();

            $table->dropColumn(['origins', 'destinations', 'rbd_codes', 'departure_date', 'price_option', 'price_value']);
        });
    }
};
