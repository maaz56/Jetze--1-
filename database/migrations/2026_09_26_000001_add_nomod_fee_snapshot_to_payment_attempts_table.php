<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->decimal('base_amount', 20, 8)->nullable()->after('amount');
            $table->decimal('percentage_fee', 20, 8)->default(0)->after('base_amount');
            $table->decimal('fixed_fee', 20, 8)->default(0)->after('percentage_fee');
            $table->decimal('fee_amount', 20, 8)->default(0)->after('fixed_fee');
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropColumn(['base_amount', 'percentage_fee', 'fixed_fee', 'fee_amount']);
        });
    }
};
