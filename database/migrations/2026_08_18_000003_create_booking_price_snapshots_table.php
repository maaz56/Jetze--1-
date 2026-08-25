<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('flight_bookings')->cascadeOnDelete();
            $table->foreignId('price_quote_id')->nullable()->constrained('price_quotes')->nullOnDelete();
            $table->uuid('quote_uuid')->nullable()->index();
            $table->string('provider');
            $table->decimal('provider_amount', 20, 8);
            $table->char('provider_currency', 3);
            $table->decimal('provider_rate_to_aed', 20, 8);
            $table->decimal('selling_amount', 20, 8);
            $table->char('selling_currency', 3);
            $table->decimal('selling_rate_to_aed', 20, 8);
            $table->decimal('aed_amount', 20, 8);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_price_snapshots');
    }
};
