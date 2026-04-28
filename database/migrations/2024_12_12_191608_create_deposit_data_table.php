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
        Schema::create('deposit_data', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->string('receipt_image')->nullable(); // Path to the uploaded file
            $table->string('payment_type');
            $table->text('additional_details')->nullable();
            $table->unsignedBigInteger('agent_id'); // Foreign key
            $table->string('deposit_status')->default('pending');
            $table->string('receipt_reference')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposit_data');
    }
};
