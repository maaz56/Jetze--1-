<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'Jetze.pk@gmail.com'],
            [
                'role' => 'admin',
                'is_approved' => true,
                'email_verified_at' => now(),
                'name' => 'admin',
                'password' => '901c7cfe@@',
            ]
        );

        // User::updateOrCreate(
        //     ['email' => 'admin@Jetze.pk'],
        //     [
        //         'role' => 'admin',
        //         'is_approved' => true,
        //         'name' => 'admin',
        //         'password' => bcrypt('Jetze123'),
        //     ]
        // );
        
        // User::updateOrCreate(
        //     ['email' => 'admin@gmail.com'],
        //     [
        //         'role' => 'admin',
        //         'is_approved' => true,
        //         'name' => 'admin',
        //         'password' => bcrypt('admin1234'),
        //         'email_verified_at' => now()
        //     ]
        // );



        $this->call([
            AirportSeeder::class,
            AircraftSeeder::class,
            AirlineSeeder::class,
            airportMarginSeeder::class,
            CountryStateCityTableSeeder::class,
            CustomerMarginSeeder::class,
            CustomerSettingSeeder::class,
            PermissionSeeder::class,
        ]);
    }
}
