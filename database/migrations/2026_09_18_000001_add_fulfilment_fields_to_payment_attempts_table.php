<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            // Payment capture and supplier fulfilment are intentionally separate.
            // A paid customer payment must never be mistaken for a ticketed booking.
            $table->string('fulfilment_status', 32)->default('pending')->after('status');
            $table->unsignedInteger('fulfilment_attempts')->default(0)->after('fulfilment_status');
            $table->text('fulfilment_error')->nullable()->after('failed_at');
            $table->timestamp('fulfilment_started_at')->nullable()->after('fulfilment_error');
            $table->timestamp('fulfilment_completed_at')->nullable()->after('fulfilment_started_at');
            $table->timestamp('fulfilment_failed_at')->nullable()->after('fulfilment_completed_at');

            $table->index(['status', 'fulfilment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex(['status', 'fulfilment_status']);
            $table->dropColumn([
                'fulfilment_status',
                'fulfilment_attempts',
                'fulfilment_error',
                'fulfilment_started_at',
                'fulfilment_completed_at',
                'fulfilment_failed_at',
            ]);
        });
    }
};
