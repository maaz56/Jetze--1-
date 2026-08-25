<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('trip_index');
            $table->unsignedTinyInteger('journey_index');
            $table->unsignedTinyInteger('segment_index');
            $table->unsignedTinyInteger('passenger_id');
            $table->string('fuid')->nullable();
            $table->unsignedBigInteger('ssid');
            $table->string('title');
            $table->json('provider_references');
            $table->decimal('provider_amount', 20, 8);
            $table->char('provider_currency', 3);
            $table->decimal('provider_rate_to_aed', 20, 8);
            $table->decimal('aed_amount', 20, 8);
            $table->decimal('display_amount', 20, 8);
            $table->char('display_currency', 3);
            $table->decimal('display_rate_to_aed', 20, 8);
            $table->json('provider_item_data');
            $table->timestamp('selected_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index(['price_quote_id', 'status']);
            $table->unique([
                'price_quote_id',
                'type',
                'trip_index',
                'journey_index',
                'segment_index',
                'passenger_id',
            ], 'price_quote_item_selection_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quote_items');
    }
};
