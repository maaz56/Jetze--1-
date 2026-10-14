<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->json('fare_breakdown_data')
                ->nullable()
                ->after('provider_pricing_data');
        });
    }

    public function down(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->dropColumn('fare_breakdown_data');
        });
    }
};
