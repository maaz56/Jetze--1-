<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabby only settles in its region currency, so the amount actually sent to
     * the provider (and the rate used) is snapshotted next to the booking-currency
     * amount. Tabby also separates authorisation from capture.
     */
    public function up(): void
    {
        foreach (['payment_attempts', 'hotel_payment_attempts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('provider_amount', 20, 8)->nullable()->after('currency');
                $table->char('provider_currency', 3)->nullable()->after('provider_amount');
                $table->decimal('exchange_rate', 24, 12)->nullable()->after('provider_currency');
                $table->string('provider_status', 32)->nullable()->after('provider_charge_id');
                $table->string('provider_capture_id', 128)->nullable()->after('provider_status');
                $table->timestamp('captured_at')->nullable()->after('paid_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['payment_attempts', 'hotel_payment_attempts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'provider_amount',
                    'provider_currency',
                    'exchange_rate',
                    'provider_status',
                    'provider_capture_id',
                    'captured_at',
                ]);
            });
        }
    }
};
