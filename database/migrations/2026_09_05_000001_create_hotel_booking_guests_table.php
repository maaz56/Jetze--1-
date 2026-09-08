<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_booking_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_booking_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('room_index');
            $table->unsignedTinyInteger('guest_index');
            $table->string('type', 16);
            $table->string('title', 16);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // MySQL limits identifier names to 64 characters.
            $table->unique(['hotel_booking_id', 'room_index', 'guest_index'], 'hotel_bkg_room_guest_uq');
            $table->index(['hotel_booking_id', 'last_name', 'first_name'], 'hotel_bkg_guest_name_idx');
        });

        $now = now();

        DB::table('hotel_bookings')
            ->select(['id', 'customer_details'])
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($now) {
                $guests = [];

                foreach ($bookings as $booking) {
                    $customerDetails = json_decode($booking->customer_details ?? '[]', true);

                    if (! is_array($customerDetails)) {
                        continue;
                    }

                    foreach ($customerDetails as $roomIndex => $room) {
                        $customerNames = $room['CustomerNames'] ?? $room['customer_names'] ?? [];

                        if (! is_array($customerNames)) {
                            continue;
                        }

                        foreach ($customerNames as $guestIndex => $guest) {
                            $firstName = trim((string) ($guest['FirstName'] ?? $guest['first_name'] ?? ''));
                            $lastName = trim((string) ($guest['LastName'] ?? $guest['last_name'] ?? ''));

                            if ($firstName === '' || $lastName === '') {
                                continue;
                            }

                            $guests[] = [
                                'hotel_booking_id' => $booking->id,
                                'room_index' => $roomIndex,
                                'guest_index' => $guestIndex,
                                'type' => $guest['Type'] ?? $guest['type'] ?? 'Adult',
                                'title' => $guest['Title'] ?? $guest['title'] ?? 'Mr',
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'raw_data' => json_encode($guest),
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }
                }

                if ($guests) {
                    DB::table('hotel_booking_guests')->insert($guests);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_booking_guests');
    }
};
