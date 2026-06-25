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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('sale_channel');
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();
            $table->string('reservation_type');
            $table->string('price_option')->default('markup');
            $table->string('commission_type');
            $table->decimal('commission_value', 10, 2);
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
        Schema::dropIfExists('promotions');
    }
};
