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
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('flight_bookings')->cascadeOnDelete();
            $table->string('type')->default('void'); // void, refund
            $table->string('status')->default('pending'); // pending, processing, approved, rejected, failed, cancelled
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('customer_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_remarks')->nullable();
            $table->string('booking_status_before')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('ticket_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->nullable();
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
