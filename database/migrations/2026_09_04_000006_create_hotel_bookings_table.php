<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_prebook_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 32)->default('processing');
            $table->unsignedSmallInteger('provider_status_code')->nullable();
            $table->string('client_reference_id')->unique();
            $table->string('booking_reference_id')->unique();
            $table->string('confirmation_number')->nullable()->index();
            $table->string('hotel_confirmation_number')->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('total_fare', 14, 2)->nullable();
            $table->string('email');
            $table->string('phone_number', 64);
            $table->json('customer_details');
            $table->json('tbo_request');
            $table->json('tbo_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_bookings');
    }
};
