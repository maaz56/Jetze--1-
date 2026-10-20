<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_booking_price_snapshot_id')
                ->constrained('hotel_booking_price_snapshots')
                ->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('reference_id', 100)->unique();
            $table->string('status', 32)->default('initiating');
            $table->string('fulfilment_status', 32)->default('pending');
            $table->unsignedInteger('fulfilment_attempts')->default(0);
            $table->decimal('amount', 20, 8);
            $table->decimal('base_amount', 20, 8);
            $table->decimal('percentage_fee', 20, 8)->default(0);
            $table->decimal('fixed_fee', 20, 8)->default(0);
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->char('currency', 3);
            $table->string('provider_checkout_id', 128)->nullable()->unique();
            $table->string('provider_charge_id', 128)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->string('return_origin', 255)->nullable();
            $table->timestamp('checkout_created_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('fulfilment_error')->nullable();
            $table->timestamp('fulfilment_started_at')->nullable();
            $table->timestamp('fulfilment_completed_at')->nullable();
            $table->timestamp('fulfilment_failed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['hotel_booking_id', 'provider', 'status'],
                'hotel_payment_booking_provider_status_index',
            );
            $table->index(
                ['status', 'fulfilment_status'],
                'hotel_payment_status_fulfilment_index',
            );
        });

        Schema::create('hotel_payment_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hotel_payment_attempt_id')
                ->nullable()
                ->constrained('hotel_payment_attempts')
                ->nullOnDelete();
            $table->string('provider', 32);
            $table->string('event_id', 128)->unique();
            $table->string('event_type', 128);
            $table->boolean('signature_verified')->default(false);
            $table->string('processing_status', 32)->default('received');
            // Encrypted by the model because provider events may contain PII.
            $table->longText('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(
                ['provider', 'event_type'],
                'hotel_payment_webhook_provider_event_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_payment_webhook_events');
        Schema::dropIfExists('hotel_payment_attempts');
    }
};
