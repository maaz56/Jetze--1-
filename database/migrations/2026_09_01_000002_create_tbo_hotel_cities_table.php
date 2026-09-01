<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbo_hotel_cities', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->index();
            $table->string('city_code')->unique();
            $table->string('name');
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->index(['country_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbo_hotel_cities');
    }
};
