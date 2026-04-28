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
        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('issue_country');
            $table->string('dob')->nullable()->after('gender');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flight_passengers', function (Blueprint $table) {
            $table->dropColumn(['gender', 'dob']);
        });
    }
};
