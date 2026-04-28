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
        Schema::table('flight_bookings', function (Blueprint $table) {
            // Drop the existing foreign key constraint first
            $table->dropForeign(['agent_id']);

            // Modify columns to be nullable
            $table->string('agency_email')->nullable()->change();
            $table->string('agency_phone')->nullable()->change();
            $table->unsignedBigInteger('agent_id')->nullable()->change();
            $table->decimal('agent_markup', 10, 2)->nullable()->change(); // Make agent_markup nullable

            // Re-add the foreign key with nullable support
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('flight_bookings', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);

            // Revert columns back to NOT NULL
            $table->string('agency_email')->nullable(false)->change();
            $table->string('agency_phone')->nullable(false)->change();
            $table->unsignedBigInteger('agent_id')->nullable(false)->change();
            $table->decimal('agent_markup', 10, 2)->nullable(false)->change(); // Revert agent_markup to NOT NULL

            // Re-add the foreign key
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
