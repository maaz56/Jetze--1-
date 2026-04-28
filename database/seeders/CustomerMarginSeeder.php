<?php

namespace Database\Seeders;

use App\Models\CustomerMargin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerMarginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CustomerMargin::create([
            'discount' => 0.00,
            'other_charges' => 0.00,
            'margin_amount' => 0.00,
        ]);
    }
}
