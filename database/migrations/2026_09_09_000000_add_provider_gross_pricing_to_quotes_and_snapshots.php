<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Store AT's gross fare separately from its provider booking NetAmount. */
    public function up(): void
    {
        Schema::table('price_quotes', function (Blueprint $table) {
            $table->decimal('provider_gross_amount', 20, 8)->nullable()->after('provider_amount');
            $table->decimal('provider_gross_aed_amount', 20, 8)->nullable()->after('provider_aed_amount');
        });

        Schema::table('booking_price_snapshots', function (Blueprint $table) {
            $table->decimal('provider_gross_amount', 20, 8)->nullable()->after('provider_amount');
            $table->char('provider_gross_currency', 3)->nullable()->after('provider_currency');
            $table->decimal('provider_gross_aed_amount', 20, 8)->nullable()->after('provider_aed_amount');
        });
    }

    /** Remove the gross-fare audit values if this migration is rolled back. */
    public function down(): void
    {
        Schema::table('booking_price_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'provider_gross_amount',
                'provider_gross_currency',
                'provider_gross_aed_amount',
            ]);
        });

        Schema::table('price_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'provider_gross_amount',
                'provider_gross_aed_amount',
            ]);
        });
    }
};
