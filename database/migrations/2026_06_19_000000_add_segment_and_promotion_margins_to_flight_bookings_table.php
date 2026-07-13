<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->decimal('segment_margin', 12, 2)->default(0)->after('add_ones_amount');
            $table->decimal('promotion_margin', 12, 2)->default(0)->after('segment_margin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropColumn(['segment_margin', 'promotion_margin']);
        });
    }
};
