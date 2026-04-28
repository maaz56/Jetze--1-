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
        Schema::create('popular_routes', function (Blueprint $table) {
            $table->id();

            // Airports
            $table->string('from_airport', 10); // store IATA code
            $table->string('to_airport', 10);   // store IATA code


            // Image
            $table->string('image')->nullable();

            // Airline
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();

            // Journey & class
            $table->string('journey_type');  // was enum('round', 'one_way')
            $table->string('travel_class');  // was enum('economy', 'business')


            // Dates & price
            $table->integer('departure_plus_days');
            $table->integer('stay_duration_days')->nullable();

            $table->enum('price_type', ['dynamic', 'static']);
            $table->integer('dynamic_refresh_hours')->nullable();
            $table->decimal('static_price', 10, 2)->nullable();

            // Destination names
            $table->string('destination_name_en');
            $table->string('destination_name_ar');

           

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popular_routes');
    }
};
