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
            'one_bill_charges' => 0,
            'one_bill_fixed_charge' => 0,
            'one_bill_percentage_charge' => 0,
            'void_charges' => 0,
        ]);
    }
}
