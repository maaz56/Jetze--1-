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
        Schema::create('customer_margins', function (Blueprint $table) {
            $table->id();
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('other_charges', 10, 2)->default(0);
            $table->decimal('margin_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_margins');
    }
};
