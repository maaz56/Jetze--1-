<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MobileCountryCodeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/mobile_country_codes.json');

        if (! file_exists($path)) {
            throw new RuntimeException("Mobile country codes JSON file not found: {$path}");
        }

        $countryCodes = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $now = now();

        $rows = collect($countryCodes)->map(function (array $country) use ($now) {
            foreach (['name', 'dial_code', 'code'] as $field) {
                if (empty($country[$field])) {
                    throw new RuntimeException("Missing '{$field}' in mobile country codes JSON.");
                }
            }

            return [
                'name' => trim($country['name']),
                'dial_code' => trim($country['dial_code']),
                'code' => strtoupper(trim($country['code'])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        });

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('mobile_country_codes')->upsert(
                $chunk->all(),
                ['code'],
                ['name', 'dial_code', 'updated_at']
            );
        }
    }
}
