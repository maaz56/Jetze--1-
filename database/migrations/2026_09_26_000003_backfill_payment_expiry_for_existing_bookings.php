<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('flight_bookings', 'payment_expires_at')) {
            return;
        }
        DB::statement('UPDATE flight_bookings SET payment_expires_at = DATE_ADD(created_at, INTERVAL 20 MINUTE) WHERE payment_expires_at IS NULL AND created_at IS NOT NULL');
    }
    public function down(): void
    {
        // Existing booking deadlines are intentionally retained on rollback.
    }
};
