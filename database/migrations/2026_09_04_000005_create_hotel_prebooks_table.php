<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_prebooks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('hotel_search_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hotel_code');
            $table->string('booking_code', 1024);
            $table->string('payment_mode', 32);
            $table->unsignedSmallInteger('provider_status_code')->nullable();
            $table->json('search_room');
            $table->json('tbo_response');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_search_session_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_prebooks');
    }
};
