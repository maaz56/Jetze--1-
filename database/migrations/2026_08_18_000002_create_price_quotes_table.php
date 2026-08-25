<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->decimal('provider_amount', 20, 8);
            $table->char('provider_currency', 3);
            $table->decimal('provider_rate_to_aed', 20, 8);
            $table->decimal('display_amount', 20, 8);
            $table->char('display_currency', 3);
            $table->decimal('display_rate_to_aed', 20, 8);
            $table->decimal('aed_amount', 20, 8);
            $table->json('flight_data');
            $table->json('selected_fare_references');
            $table->timestamp('expires_at');
            $table->timestamp('retain_until');
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
            $table->index('retain_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quotes');
    }
};
