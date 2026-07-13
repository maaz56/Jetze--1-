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
        Schema::table('popular_routes', function (Blueprint $table) {
            $table->enum('type', ['domestic', 'international'])->default('domestic')->after('to_airport');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('popular_routes', function (Blueprint $table) {
            //
        });
    }
};
