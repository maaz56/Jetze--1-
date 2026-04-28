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
        // flights
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('id')->on('bookings');
            $table->string('supplier');
            $table->string('external_id');
            $table->decimal('price_margin')->default(0);
            $table->json('price');
            $table->timestamps();
        });

        // slices
        Schema::create('slices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('id')->on('flights');
            $table->string('duration');
            $table->timestamps();
        });

        // segments table
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slice_id')->constrained('id')->on('slices');
            $table->string('origin_terminal')->nullable();
            $table->string('destination_terminal')->nullable();
            $table->timestamp('departure_at')->nullable();
            $table->timestamp('arriving_at')->nullable();
            $table->foreignId('aircraft_id')->nullable()->constrained('aircrafts');
            $table->foreignId('airline_id')->nullable()->constrained('airlines');
            $table->string('duration');
            $table->foreignId('origin_airport_id')->nullable()->constrained('airports');
            $table->foreignId('destination_airport_id')->nullable()->constrained('airports');
            $table->string('flight_number')->nullable();
            $table->timestamps();
        });

        // baggages table
        Schema::create('baggages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('id')->on('segments');
            $table->enum('type', ['checked_in', 'carry_on']);
            $table->integer('quantity')->nullable();
            $table->string('weight')->nullable();
            $table->timestamps();
        });

        // Passengers Table
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_id')->constrained('id')->on('flights');
            $table->string('type');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
        Schema::dropIfExists('slices');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('baggages');
        Schema::dropIfExists('passengers');
    }
};
