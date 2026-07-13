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
            $table->decimal('one_bill_fixed_charge', 10, 2)
                ->nullable()
                ->after('one_bill_charges');
            $table->decimal('one_bill_percentage_charge', 8, 4)
                ->nullable()
                ->after('one_bill_fixed_charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_settings', function (Blueprint $table) {
            $table->dropColumn(['one_bill_fixed_charge', 'one_bill_percentage_charge']);
        });
    }
};
