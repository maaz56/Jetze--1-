<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Create immutable void settlement and wallet ledger records. */
    public function up(): void
    {
        Schema::create('booking_void_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained('flight_bookings')->cascadeOnDelete();
            $table->foreignId('original_price_snapshot_id')->constrained('booking_price_snapshots')->restrictOnDelete();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 30);
            $table->decimal('original_aed_amount', 20, 8);
            $table->decimal('void_charge_aed', 20, 8);
            $table->decimal('refund_aed', 20, 8);
            $table->char('currency', 3)->default('AED');
            $table->date('effective_date');
            $table->text('description');
            $table->string('status', 30)->default('settled');
            $table->string('idempotency_key')->unique();
            $table->timestamp('voided_at');
            $table->timestamps();
        });

        Schema::create('agent_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->foreignId('booking_void_snapshot_id')->constrained('booking_void_snapshots')->cascadeOnDelete();
            $table->string('entry_type', 50);
            $table->string('direction', 10);
            $table->decimal('aed_amount', 20, 8);
            $table->char('currency', 3)->default('AED');
            $table->text('description');
            $table->date('effective_date');
            $table->string('idempotency_key')->unique();
            $table->timestamps();
        });
    }

    /** Remove void settlement tables. */
    public function down(): void
    {
        Schema::dropIfExists('agent_ledger_entries');
        Schema::dropIfExists('booking_void_snapshots');
    }
};
