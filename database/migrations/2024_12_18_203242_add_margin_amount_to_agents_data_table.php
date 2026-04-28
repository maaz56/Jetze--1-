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
        Schema::table('agents_data', function (Blueprint $table) {
            $table->decimal('margin_amount', 10, 2)->nullable()->after('agent_id'); // Replace 'existing_column' with the actual column name you want this to follow
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents_data', function (Blueprint $table) {
            $table->dropColumn('margin_amount');
        });
    }
};
