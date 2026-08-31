<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->decimal('provider_aed_amount', 20, 8)->nullable()->after('provider_rate_to_aed');
        });

        Schema::create('price_quote_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_quote_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->string('title')->nullable();
            $table->string('direction', 20);
            $table->string('calculation_type', 20);
            $table->decimal('configured_value', 20, 8);
            $table->unsignedInteger('passenger_count')->default(1);
            $table->unsignedInteger('segment_count')->default(1);
            $table->decimal('aed_amount', 20, 8);
            $table->json('rule_snapshot');
            $table->timestamps();

            $table->index(['price_quote_id', 'type']);
        });

        Schema::table('booking_price_snapshots', function (Blueprint $table) {
            $table->decimal('provider_aed_amount', 20, 8)->nullable()->after('provider_rate_to_aed');
            $table->json('adjustments_snapshot')->nullable()->after('aed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('booking_price_snapshots', function (Blueprint $table) {
            $table->dropColumn(['provider_aed_amount', 'adjustments_snapshot']);
        });
        Schema::dropIfExists('price_quote_adjustments');
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->dropColumn('provider_aed_amount');
        });
    }
};
