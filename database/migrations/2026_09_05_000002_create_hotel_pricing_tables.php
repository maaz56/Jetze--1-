<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_price_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_prebook_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('tbo');
            $table->decimal('provider_amount', 20, 8);
            $table->char('provider_currency', 3);
            $table->decimal('provider_rate_to_aed', 20, 8);
            $table->decimal('provider_aed_amount', 20, 8);
            $table->decimal('selling_amount', 20, 8);
            $table->char('selling_currency', 3);
            $table->decimal('selling_rate_to_aed', 20, 8);
            $table->decimal('aed_amount', 20, 8);
            $table->json('adjustments_snapshot')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('retain_until')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });

        Schema::create('hotel_booking_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_price_quote_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('quote_uuid')->nullable()->index();
            $table->string('provider', 32);
            $table->decimal('provider_amount', 20, 8);
            $table->char('provider_currency', 3);
            $table->decimal('provider_rate_to_aed', 20, 8);
            $table->decimal('provider_aed_amount', 20, 8);
            $table->decimal('selling_amount', 20, 8);
            $table->char('selling_currency', 3);
            $table->decimal('selling_rate_to_aed', 20, 8);
            $table->decimal('aed_amount', 20, 8);
            $table->json('adjustments_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_price_snapshots');
        Schema::dropIfExists('hotel_price_quotes');
    }
};
