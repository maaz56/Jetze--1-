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
            $table->string('language', 10)->nullable();
            $table->string('currency', 10)->nullable()->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents_data', function (Blueprint $table) {
                        $table->dropColumn(['language', 'currency']);

        });
    }
};
