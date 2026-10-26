<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table): void {
            $table->string('transaction_id')->nullable()->after('pnr')->index();
        });

        // Backfill AT bookings from the CreateItinerary response.
        DB::table('flight_bookings')
            ->where('flight_provider', 'at')
            ->whereNotNull('pnr_response')
            ->orderBy('id')
            ->select(['id', 'pnr_response'])
            ->chunkById(200, function ($bookings): void {
                foreach ($bookings as $booking) {
                    $response = json_decode((string) $booking->pnr_response, true);
                    $transactionId = is_array($response) ? ($response['TransactionID'] ?? null) : null;

                    if (is_scalar($transactionId) && (string) $transactionId !== '') {
                        DB::table('flight_bookings')
                            ->where('id', $booking->id)
                            ->update(['transaction_id' => (string) $transactionId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table): void {
            $table->dropColumn('transaction_id');
        });
    }
};
