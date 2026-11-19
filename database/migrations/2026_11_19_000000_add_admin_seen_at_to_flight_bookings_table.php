<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('flight_bookings') || Schema::hasColumn('flight_bookings', 'admin_seen_at')) {
            return;
        }

        Schema::table('flight_bookings', function (Blueprint $table): void {
            $table->timestamp('admin_seen_at')->nullable()->index();
        });

        // Existing bookings predate this feature and should not suddenly be
        // presented as new. Bookings created after this migration stay null
        // until an administrator opens their details.
        DB::table('flight_bookings')->update([
            'admin_seen_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('flight_bookings') && Schema::hasColumn('flight_bookings', 'admin_seen_at')) {
            Schema::table('flight_bookings', function (Blueprint $table): void {
                $table->dropColumn('admin_seen_at');
            });
        }
    }
};
