<?php

namespace Database\Seeders;

use App\Models\CustomerSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CustomerSetting::create([
            'is_card_allowed' => true,
            'is_booking_allowed' => true,
        ]);
    }
}
