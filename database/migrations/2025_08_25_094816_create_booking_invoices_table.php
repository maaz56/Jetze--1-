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
        Schema::create('booking_invoices', function (Blueprint $table) {
           $table->id();
            $table->unsignedBigInteger('booking_id');
            $table->string('invoice_id')->nullable();
            $table->string('amount')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('invoice_url')->nullable();
            $table->timestamps();
            $table->foreign('booking_id')->references('id')->on('flight_bookings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_invoices');
    }
};
