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
        Schema::table('airlines', function (Blueprint $table) {
            $table->string('margin_type')->nullable()->after('carrier_condition_url');
            $table->decimal('margin_amount', 8, 2)->nullable()->after('margin_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropColumn(columns: ['margin_type', 'margin_amount']);
        });
    }
};
