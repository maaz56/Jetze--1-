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
        $table->decimal('agent_discount', 10, 2)->default(0)->after('margin_amount');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents_data', function (Blueprint $table) {
                        $table->dropColumn('agent_discount');

        });
    }
};
