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
        Schema::table('deposit_data', function (Blueprint $table) {
            $table->string('invoice_id')->nullable()->after('id');
            $table->string('t_id')->nullable()->after('invoice_id');
            $table->string('t_status')->nullable()->after('t_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposit_data', function (Blueprint $table) {
            $table->dropColumn(['invoice_id', 't_id', 't_status']);
        });
    }
};
