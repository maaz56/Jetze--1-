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
        Schema::create('segment_margins', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('sale_channel');
            $table->foreignId('airline_id')->nullable()->constrained('airlines')->nullOnDelete();
            $table->json('airline_ids')->nullable();
            $table->json('disabled_airline_ids')->nullable();
            $table->string('margin_type')->default('amount');
            $table->decimal('margin_value', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segment_margins');
    }
};
