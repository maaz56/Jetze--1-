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
        Schema::create('airline_margins', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('sale_channel');
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->onDelete('cascade');
            $table->string('reservation_type');
            $table->enum('margin_type', ['amount', 'percentage'])->default('amount');
            $table->decimal('margin_value', 10, 2);
            $table->date('travel_start_date')->nullable();
            $table->date('travel_end_date')->nullable();
            $table->date('ticketing_start_date')->nullable();
            $table->date('ticketing_end_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('airline_margins');
    }
};
