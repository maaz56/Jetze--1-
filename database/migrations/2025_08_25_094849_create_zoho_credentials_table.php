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
        Schema::create('zoho_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('client_id')->unique();
            $table->string('client_secret');
            $table->string('redirect_uri')->nullable(); // Uncomment if you want to store
            $table->string('access_token')->nullable(); // Uncomment if you want to store
            $table->string('refresh_token')->nullable(); // Uncomment if you want to store
            $table->string('expires_in')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zoho_credentials');
    }
};
