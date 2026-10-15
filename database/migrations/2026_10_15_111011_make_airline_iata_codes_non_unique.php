<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AT can identify different airlines using the same IATA code. The
     * airline name is therefore part of the application-level resolution.
     */
    public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropUnique(['iata_code']);
            $table->index('iata_code');
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropIndex(['iata_code']);
            $table->unique('iata_code');
        });
    }
};
