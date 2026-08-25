<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->json('provider_pricing_data')
                ->nullable()
                ->after('selected_fare_references');
        });
    }

    public function down(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->dropColumn('provider_pricing_data');
        });
    }
};
