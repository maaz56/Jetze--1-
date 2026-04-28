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
        Schema::create('agent_charges', function (Blueprint $table) {
            $table->id();
            $table->date(column: 'date');
            $table->unsignedBigInteger('user_id'); // Foreign key
            $table->decimal('amount', 15, 2);
            $table->string('payment_type');
            $table->text('additional_details')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_charges');
    }
};
