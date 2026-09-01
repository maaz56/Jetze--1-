<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_search_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('destination_type');
            $table->string('destination_code');
            $table->string('destination_label')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->string('guest_nationality', 2);
            $table->json('pax_rooms');
            $table->json('tbo_request');
            $table->json('tbo_response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['destination_type', 'destination_code']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_search_sessions');
    }
};
