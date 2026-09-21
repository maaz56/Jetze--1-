<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->foreignId('price_snapshot_id')->constrained('booking_price_snapshots')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('reference_id', 100)->unique();
            $table->string('status', 32)->default('initiating');
            $table->decimal('amount', 20, 8);
            $table->char('currency', 3);
            $table->string('provider_checkout_id', 128)->nullable()->unique();
            $table->string('provider_charge_id', 128)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('checkout_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'provider', 'status']);
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('event_id', 128)->unique();
            $table->string('event_type', 128);
            $table->boolean('signature_verified')->default(false);
            $table->string('processing_status', 32)->default('received');
            // The model encrypts this at rest because webhook payloads contain customer PII.
            $table->longText('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
        Schema::dropIfExists('payment_attempts');
    }
};
