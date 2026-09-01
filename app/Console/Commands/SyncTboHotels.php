<?php

namespace App\Console\Commands;

use App\Models\TboHotel;
use App\Models\TboHotelCity;
use App\Models\TboHotelCountry;
use App\Services\TboHotelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncTboHotels extends Command
{
    protected $signature = 'tbo-hotels:sync
        {--country=* : Sync only these TBO country codes, for example --country=AE}
        {--city=* : Sync only these TBO city codes}
        {--skip-countries : Do not call CountryList}
        {--skip-cities : Do not call CityList}
        {--skip-hotels : Do not call TBOHotelCodeList}
        {--hotel-limit=0 : Maximum number of cities to sync hotels for. 0 means no limit}';

    protected $description = 'Sync TBO hotel countries, cities, and hotel static data for local hotel search suggestions.';

    public function __construct(protected TboHotelService $tboHotelService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->option('skip-countries')) {
            $this->syncCountries();
        }

        if (!$this->option('skip-cities')) {
            $this->syncCities();
        }

        if (!$this->option('skip-hotels')) {
            $this->syncHotels();
        }

        $this->info('TBO hotel sync completed.');

        return self::SUCCESS;
    }

    protected function syncCountries(): void
    {
        $this->info('Syncing TBO hotel countries...');
        $response = $this->tboHotelService->countryList();
        $countries = $response['CountryList'] ?? [];

        foreach ($countries as $country) {
            if (empty($country['Code']) || empty($country['Name'])) {
                continue;
            }

            TboHotelCountry::updateOrCreate(
                ['code' => strtoupper($country['Code'])],
                [
                    'name' => $country['Name'],
                    'raw_response' => $country,
                ]
            );
        }

        $this->info('Countries synced: ' . count($countries));
    }

    protected function syncCities(): void
    {
        $countryCodes = collect($this->option('country'))
            ->filter()
            ->map(fn ($code) => strtoupper($code))
            ->values();

        $countries = TboHotelCountry::query()
            ->when($countryCodes->isNotEmpty(), fn ($query) => $query->whereIn('code', $countryCodes))
            ->orderBy('code')
            ->get();

        $this->info('Syncing TBO hotel cities for ' . $countries->count() . ' countries...');

        foreach ($countries as $country) {
            try {
                $response = $this->tboHotelService->cityList($country->code);
                $cities = $response['CityList'] ?? [];

                foreach ($cities as $city) {
                    if (empty($city['Code']) || empty($city['Name'])) {
                        continue;
                    }

                    TboHotelCity::updateOrCreate(
                        ['city_code' => (string) $city['Code']],
                        [
                            'country_code' => $country->code,
                            'name' => $city['Name'],
                            'raw_response' => $city,
                        ]
                    );
                }

                $this->line($country->code . ' cities synced: ' . count($cities));
            } catch (Throwable $e) {
                Log::error('TBO city sync failed', [
                    'country_code' => $country->code,
                    'message' => $e->getMessage(),
                ]);
                $this->error($country->code . ' city sync failed: ' . $e->getMessage());
            }
        }
    }

    protected function syncHotels(): void
    {
        $cityCodes = collect($this->option('city'))->filter()->map(fn ($code) => (string) $code)->values();
        $countryCodes = collect($this->option('country'))->filter()->map(fn ($code) => strtoupper($code))->values();
        $hotelLimit = (int) $this->option('hotel-limit');

        $cities = TboHotelCity::query()
            ->when($cityCodes->isNotEmpty(), fn ($query) => $query->whereIn('city_code', $cityCodes))
            ->when($cityCodes->isEmpty() && $countryCodes->isNotEmpty(), fn ($query) => $query->whereIn('country_code', $countryCodes))
            ->orderBy('country_code')
            ->orderBy('name')
            ->when($hotelLimit > 0, fn ($query) => $query->limit($hotelLimit))
            ->get();

        $this->info('Syncing TBO hotels for ' . $cities->count() . ' cities...');

        foreach ($cities as $city) {
            try {
                $response = $this->tboHotelService->tboHotelCodeList($city->city_code, true);
                $hotels = $response['Hotels'] ?? [];

                foreach ($hotels as $hotel) {
                    $this->upsertHotel($hotel, $city);
                }

                $this->line($city->name . ' hotels synced: ' . count($hotels));
            } catch (Throwable $e) {
                Log::error('TBO hotel sync failed', [
                    'city_code' => $city->city_code,
                    'message' => $e->getMessage(),
                ]);
                $this->error($city->name . ' hotel sync failed: ' . $e->getMessage());
            }
        }
    }

    protected function upsertHotel(array $hotel, TboHotelCity $city): void
    {
        $hotelCode = (string) ($hotel['HotelCode'] ?? '');

        if ($hotelCode === '') {
            return;
        }

        [$latitude, $longitude] = $this->parseMap($hotel['Map'] ?? null);
        $hotelName = $hotel['HotelName'] ?? 'Hotel ' . $hotelCode;
        $countryCode = strtoupper((string) ($hotel['CountryCode'] ?? $city->country_code));
        $countryName = $hotel['CountryName'] ?? $city->country?->name;
        $cityName = $hotel['CityName'] ?? $city->name;

        TboHotel::updateOrCreate(
            ['hotel_code' => $hotelCode],
            [
                'hotel_name' => $hotelName,
                'hotel_rating' => isset($hotel['HotelRating']) ? (string) $hotel['HotelRating'] : null,
                'address' => $hotel['Address'] ?? null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'city_code' => (string) ($hotel['CityId'] ?? $city->city_code),
                'city_name' => $cityName,
                'map' => $hotel['Map'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'images' => $hotel['Images'] ?? null,
                'facilities' => $hotel['HotelFacilities'] ?? null,
                'description' => $hotel['Description'] ?? null,
                'raw_response' => $hotel,
                'search_text' => trim(implode(' ', array_filter([
                    $hotelName,
                    $cityName,
                    $countryName,
                    $hotel['Address'] ?? null,
                    $hotel['HotelRating'] ?? null,
                ]))),
            ]
        );
    }

    protected function parseMap(?string $map): array
    {
        if (!$map || !str_contains($map, '|')) {
            return [null, null];
        }

        [$latitude, $longitude] = explode('|', $map, 2);

        return [
            is_numeric($latitude) ? (float) $latitude : null,
            is_numeric($longitude) ? (float) $longitude : null,
        ];
    }
}
