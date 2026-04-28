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
        Schema::create('flight_passengers', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // ADT, CHD, etc.
            $table->string('title'); // Mr, Mrs, etc.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nationality');
            $table->string('document_type'); // Passport, ID, etc.
            $table->string('document_no');
            $table->date('expiry_date');
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->string(column: 'issue_country');
            $table->timestamps();
            $table->foreign(columns: 'booking_id')->references('id')->on('flight_bookings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_passengers');
    }
};