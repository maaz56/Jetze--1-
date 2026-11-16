<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Convert the retired `issued` status to its canonical `ticketed` equivalent. */
    public function up(): void
    {
        DB::table('flight_bookings')
            ->whereRaw('LOWER(status) = ?', ['issued'])
            ->update(['status' => 'ticketed']);

        DB::table('flight_passengers')
            ->whereRaw('LOWER(status) = ?', ['issued'])
            ->update(['status' => 'ticketed']);
    }

    /** The merge is intentionally irreversible: old ticketed rows cannot be distinguished. */
    public function down(): void
    {
        // No-op.
    }
};
