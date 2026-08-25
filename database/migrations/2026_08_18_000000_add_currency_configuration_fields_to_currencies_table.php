<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->unsignedTinyInteger('decimal_places')->default(2)->after('symbol');
            $table->decimal('exchange_rate', 20, 8)->nullable()->change();
            $table->boolean('is_enabled')->default(true)->after('exchange_rate');
            $table->boolean('is_base')->default(false)->after('is_enabled');
        });

        DB::table('currencies')
            ->whereRaw('UPPER(code) = ?', ['AED'])
            ->update([
                'exchange_rate' => '1.00000000',
                'is_enabled' => true,
                'is_base' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['decimal_places', 'is_enabled', 'is_base']);
            $table->decimal('exchange_rate', 8, 2)->nullable()->change();
        });
    }
};
