<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_attempts', 'is_test_mode')) {
            return;
        }

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['is_test_mode', 'status']);
            $table->dropColumn('is_test_mode');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('payment_attempts', 'is_test_mode')) {
            return;
        }

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->boolean('is_test_mode')->default(false)->after('provider');
            $table->index(['is_test_mode', 'status']);
        });
    }
};
