<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents_data', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('govt_number')->nullable();
            $table->string('mobile');
            $table->string('phone')->nullable();
            $table->string('ceo_name')->nullable();
            $table->string('ceo_contact')->nullable();
            $table->string('ceo_email')->nullable();
            $table->string('company_email')->nullable();
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();// Path to the logo file
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents_data');
    }
};
