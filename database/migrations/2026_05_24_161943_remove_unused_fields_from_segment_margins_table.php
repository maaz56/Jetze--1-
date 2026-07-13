<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('segment_margins', function (Blueprint $table) {
            $table->dropColumn([
                'origins',
                'destinations',
                'rbd_codes',
                'departure_date',
                'price_option',
                'price_value',
                'reservation_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('segment_margins', function (Blueprint $table) {
            $table->json('origins')->nullable();
            $table->json('destinations')->nullable();
            $table->json('rbd_codes')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('price_option')->nullable();
            $table->decimal('price_value', 10, 2)->nullable();
            $table->string('reservation_type')->nullable();
        });
    }
};
