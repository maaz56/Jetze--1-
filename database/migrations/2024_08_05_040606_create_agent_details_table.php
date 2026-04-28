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
        Schema::create('agent_details', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 10, 2)->default(0);
            $table->foreignId('user_id')->constrained('id')->on('users');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('preferred_currency')->nullable();
            $table->string('company_name');
            $table->string('company_reg_no');
            $table->string('business_nature');
            $table->string('country');
            $table->string('city');
            $table->string('postal_code');
            $table->longText('address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_details');
    }
};
