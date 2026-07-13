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
                Schema::rename('airline_margins', 'segment_margins');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
                Schema::rename('segment_margins', 'airline_margins');

    }
};
