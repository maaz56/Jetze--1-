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
        Schema::table('customer_settings', function (Blueprint $table) {
            $table->decimal('void_charges', 10, 2)
                ->default(0)
                ->after('one_bill_percentage_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_settings', function (Blueprint $table) {
            $table->dropColumn('void_charges');
        });
    }
};
