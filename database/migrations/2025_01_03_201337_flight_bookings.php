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
        Schema::create('flight_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('main_email');
            $table->string('main_phone');
            $table->string('main_country');
            $table->json('flight_data'); // Storing JSON flight data
            $table->json('pnr_response');
            $table->string(column: 'agency_email');
            $table->string(column: 'agency_phone');
            $table->string('flight_id');
            $table->string('status')->default('booked'); // Status of the booking
            $table->string('pnr')->nullable(); // PNR for approved bookings
            $table->unsignedBigInteger('agent_id');
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->timestamps();
            $table->foreign(columns: 'agent_id')->references( 'id')->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_bookings');
    }
};