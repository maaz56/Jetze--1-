<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->integer('rating');
            $table->text('message');
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Insert initial reviews (already approved)
        DB::table('reviews')->insert([
            [
                'name' => 'Qurat ul Ain',
                'email' => 'qurat@example.com',
                'rating' => 5,
                'message' => 'Booking with Jetze was incredibly easy and hassle-free. Their user-friendly website made finding and confirming my travel options quick and seamless, providing peace of mind throughout the process.',
                'is_approved' => true,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Nauman Shahzad',
                'email' => 'nauman@example.com',
                'rating' => 5,
                'message' => 'Jetze provides unparalleled service with a user-friendly platform, exceptional customer support, and unwavering reliability. It’s my top choice for hassle-free travel arrangements.',
                'is_approved' => true,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Abdul Rehman',
                'email' => 'abdul@example.com',
                'rating' => 5,
                'message' => 'As a frequent traveler, I confidently declare Jetze as the best tour operator. Their seamless booking process, responsive customer service, and unwavering reliability make them my go-to choice for all my travel needs.',
                'is_approved' => true,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ayesha Khan',
                'email' => 'ayesha@example.com',
                'rating' => 5,
                'message' => 'Excellent service! Got my tickets instantly and the support team was available 24/7. Highly recommended for anyone traveling from Pakistan.',
                'is_approved' => true,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mohammad Ali',
                'email' => 'ali@example.com',
                'rating' => 5,
                'message' => 'Best prices and super fast booking. I always book my international flights through Jetze now. Trusted and reliable!',
                'is_approved' => true,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
