<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_prebook_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('stage', 32);
            $table->unsignedSmallInteger('provider_status_code')->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['hotel_prebook_id', 'stage', 'occurred_at']);
            $table->index(['hotel_booking_id', 'stage', 'occurred_at']);
        });

        $now = now();

        DB::table('hotel_bookings')
            ->select(['id', 'confirmation_number', 'booking_reference_id', 'provider_status_code', 'tbo_response', 'created_at', 'updated_at'])
            ->whereNotNull('tbo_response')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($now) {
                $events = [];

                foreach ($bookings as $booking) {
                    $response = json_decode($booking->tbo_response, true);

                    if (! is_array($response)) {
                        continue;
                    }

                    $events[] = [
                        'hotel_booking_id' => $booking->id,
                        'provider' => 'tbo',
                        'stage' => 'book',
                        'provider_status_code' => $booking->provider_status_code,
                        'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
                        'request_data' => null,
                        'response_data' => json_encode($response),
                        'occurred_at' => $booking->updated_at ?? $booking->created_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($events) {
                    DB::table('hotel_booking_events')->insert($events);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_events');
    }
};
