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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_approved')->default(false);
            $table->foreignId('user_id')->constrained('id')->on('users');
            $table->string('image_name');
            $table->string('image_path');
            $table->string('receipt_no');
            $table->string('bank')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('payment_type');
            $table->longText('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
