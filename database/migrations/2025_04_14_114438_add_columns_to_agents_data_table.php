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
        Schema::table('agents_data', function (Blueprint $table) {
            $table->string('trade_license')->nullable()->after('logo');
            $table->string('e_id')->nullable()->after('trade_license');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents_data', function (Blueprint $table) {
            $table->dropColumn(['trade_license', 'e_id']);
        });
    }
};
