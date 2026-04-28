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
        Schema::create('offline_booking_travellers', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('offline_booking_id')->nullable();

            $table->string('type', 10)->comment('ADT, CHD, INF');
            $table->string('title', 10)->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nationality')->nullable();

            $table->string('document_type')->nullable();
            $table->string('document_no')->nullable();
            $table->string('expiry_date')->nullable();
            $table->string('issue_country')->nullable();

            $table->string('dob')->nullable();
            $table->string('gender')->nullable();

            $table->timestamps();

            $table->foreign('offline_booking_id')->references('id')->on('offline_bookings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_booking_travellers');
    }
};
