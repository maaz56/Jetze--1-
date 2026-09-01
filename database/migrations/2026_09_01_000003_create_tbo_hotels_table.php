<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbo_hotels', function (Blueprint $table) {
            $table->id();
            $table->string('hotel_code')->unique();
            $table->string('hotel_name');
            $table->string('hotel_rating')->nullable();
            $table->string('address')->nullable();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country_name')->nullable();
            $table->string('city_code')->nullable()->index();
            $table->string('city_name')->nullable();
            $table->string('map')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('images')->nullable();
            $table->json('facilities')->nullable();
            $table->longText('description')->nullable();
            $table->json('raw_response')->nullable();
            $table->text('search_text')->nullable();
            $table->timestamps();

            $table->index(['city_code', 'hotel_name']);
            $table->index(['country_code', 'hotel_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbo_hotels');
    }
};
