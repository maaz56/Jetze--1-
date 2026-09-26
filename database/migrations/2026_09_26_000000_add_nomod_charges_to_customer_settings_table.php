<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_settings', function (Blueprint $table): void {
            $table->decimal('nomod_percentage_charge', 8, 4)->default(2.5000)->after('is_booking_allowed');
            $table->decimal('nomod_fixed_charge', 20, 8)->default(0)->after('nomod_percentage_charge');
        });
    }

    public function down(): void
    {
        Schema::table('customer_settings', function (Blueprint $table): void {
            $table->dropColumn(['nomod_percentage_charge', 'nomod_fixed_charge']);
        });
    }
};
