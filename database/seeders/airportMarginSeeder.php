<?php

namespace Database\Seeders;

use App\Models\AirportMargin;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class airportMarginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('airport_margins')->insert([
            [
                'domestic' => '0',        // example margin
                'international' => '0',  // example margin
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);
    }
}
