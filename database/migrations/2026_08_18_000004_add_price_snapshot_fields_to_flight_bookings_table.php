<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->foreignId('price_quote_id')->nullable()->after('id')
                ->constrained('price_quotes')->nullOnDelete();
            $table->foreignId('price_snapshot_id')->nullable()->after('price_quote_id')
                ->constrained('booking_price_snapshots')->nullOnDelete();
            $table->char('selling_currency', 3)->nullable()->after('amount');
            $table->decimal('selling_amount', 20, 8)->nullable()->after('selling_currency');
            $table->decimal('aed_amount', 20, 8)->nullable()->after('selling_amount');
        });
    }

    public function down(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_snapshot_id');
            $table->dropConstrainedForeignId('price_quote_id');
            $table->dropColumn(['selling_currency', 'selling_amount', 'aed_amount']);
        });
    }
};
