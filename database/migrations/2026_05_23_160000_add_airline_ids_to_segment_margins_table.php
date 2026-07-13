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
        Schema::table('segment_margins', function (Blueprint $table) {
            $table->json('airline_ids')->nullable()->after('airline_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('segment_margins', function (Blueprint $table) {
            $table->dropColumn('airline_ids');
        });
    }
};

