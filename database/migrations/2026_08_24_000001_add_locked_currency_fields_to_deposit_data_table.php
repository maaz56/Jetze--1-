<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the immutable AED accounting values for each submitted deposit. */
    public function up(): void
    {
        Schema::table('deposit_data', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('agent_id')->constrained('banks')->nullOnDelete();
            $table->decimal('rate_to_aed', 20, 8)->nullable()->after('currency');
            $table->decimal('aed_amount', 20, 8)->nullable()->after('rate_to_aed');
            $table->foreignId('approved_by')->nullable()->after('deposit_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /** Remove the locked-currency fields if this migration is rolled back. */
    public function down(): void
    {
        Schema::table('deposit_data', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['bank_id', 'rate_to_aed', 'aed_amount', 'approved_by', 'approved_at']);
        });
    }
};
