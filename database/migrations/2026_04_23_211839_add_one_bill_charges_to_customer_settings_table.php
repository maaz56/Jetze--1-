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
             $table->decimal('one_bill_charges', 10, 2)
                  ->nullable()
                  ->after('id'); // change position if needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('customer_settings', function (Blueprint $table) {
            $table->dropColumn('one_bill_charges');
        });
    }
};
