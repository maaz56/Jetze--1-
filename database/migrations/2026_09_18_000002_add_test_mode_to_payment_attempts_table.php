<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            // Keeps a development checkout permanently distinguishable after
            // environment/config values have changed.
            $table->boolean('is_test_mode')->default(false)->after('provider');
            $table->index(['is_test_mode', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex(['is_test_mode', 'status']);
            $table->dropColumn('is_test_mode');
        });
    }
};
