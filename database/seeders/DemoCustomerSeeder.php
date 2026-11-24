<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoCustomerSeeder extends Seeder
{
    /**
     * Demo customer for walkthroughs. It can log in, search and create
     * bookings, but is blocked from every ticket-issuing/payment path.
     *
     * Run: php artisan db:seed --class=DemoCustomerSeeder
     * Override credentials with DEMO_CUSTOMER_EMAIL / DEMO_CUSTOMER_PASSWORD.
     */
    public function run(): void
    {
        $email = env('DEMO_CUSTOMER_EMAIL', 'demo@jetze.pk');
        $password = env('DEMO_CUSTOMER_PASSWORD', 'Demo@12345');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Demo Customer',
                'role' => 'customer',
                'is_approved' => true,
                'is_demo' => true,
                'email_verified_at' => now(),
                'password' => $password,
            ]
        );

        Customer::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Demo Customer',
                'email' => $email,
                'phone' => '0000000000',
                'preferred_currency' => 'PKR',
            ]
        );

        $this->command?->info("Demo customer ready: {$email}");
    }
}
