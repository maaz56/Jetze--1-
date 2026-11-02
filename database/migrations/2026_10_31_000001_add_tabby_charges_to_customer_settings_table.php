<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_settings', function (Blueprint $table): void {
            $table->decimal('tabby_percentage_charge', 8, 4)->default(0)->after('nomod_fixed_charge');
            $table->decimal('tabby_fixed_charge', 20, 8)->default(0)->after('tabby_percentage_charge');
        });
    }

    public function down(): void
    {
        Schema::table('customer_settings', function (Blueprint $table): void {
            $table->dropColumn(['tabby_percentage_charge', 'tabby_fixed_charge']);
        });
    }
};
