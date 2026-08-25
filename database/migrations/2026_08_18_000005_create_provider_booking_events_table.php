<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->string('provider');
            $table->string('stage');
            $table->string('provider_reference')->nullable();
            $table->decimal('provider_amount', 20, 8)->nullable();
            $table->char('provider_currency', 3)->nullable();
            $table->json('response_data')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'stage', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_booking_events');
    }
};
