<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_charges', function (Blueprint $table) {
            $table->string('receipt')->nullable()->after('additional_details');
            $table->boolean('is_approved')->default(false)->after('receipt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_charges', function (Blueprint $table) {
            $table->dropColumn(['receipt', 'is_approved']);

        });
    }
};
