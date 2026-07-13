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
    Schema::create('hot_deals', function (Blueprint $table) {
        $table->id();
        $table->string('from_airport');     // IATA code (e.g., "KHI")
        $table->string('to_airport');       // IATA code (e.g., "DXB")
        $table->string('title');
        $table->string('tag')->nullable();
        $table->decimal('original_price', 10, 2);
        $table->decimal('discounted_price', 10, 2);
        $table->string('image_url')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('display_order')->default(0);
        $table->timestamp('start_date')->nullable();
        $table->timestamp('end_date')->nullable();
        $table->timestamps();
        
        $table->index('is_active');
        $table->index('display_order');
        $table->index(['from_airport', 'to_airport']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hot_deals');
    }
};
