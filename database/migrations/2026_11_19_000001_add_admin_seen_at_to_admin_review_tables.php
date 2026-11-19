<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'hotel_bookings',
        'deposit_data',
        'booking_requests',
        'modify_requests',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'admin_seen_at')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestamp('admin_seen_at')->nullable()->index();
            });

            // Only records created after this feature is deployed should be new.
            DB::table($tableName)->update([
                'admin_seen_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'admin_seen_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('admin_seen_at');
                });
            }
        }
    }
};
