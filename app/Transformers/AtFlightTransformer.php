<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use DateTime;
use GuzzleHttp\Psr7\Request;
use Log;
use Str;

class AtFlightTransformer
{

    public function fromAT($flightData, $params)
    {
        $flightData = is_string($flightData) ? json_decode($flightData, true) : $flightData;
        $processed = $this->atFlightProcessor($flightData);
        $results = [];

        $provider = [
            "name" => "at",
            "identifier" => "AT",
            "TUI" => $flightData['TUI'] ?? null,
            "contentSource" => "AT",
        ];

        foreach ($processed['flights'] ?? [] as $item) {
            $currency = $processed['meta']['CurrencyCode'] ?? 'PKR';
            
            // Handle different journey types
            if ($item['type'] === 'return') {
                // Return journey (2 legs)
                $legs = [$item['onward'], $item['return']];
            } elseif ($item['type'] === 'multicity') {
                // Multi-city journey (3+ legs)
                $legs = $item['legs'];
            } else {
                // One-way journey (1 leg)
                $legs = [$item['legs']];
            }

            $transformedLegs = [];
            
            foreach ($legs as $legData) {
                $segments = [];
                $flight = $legData['flight'];
                $deptAirport = $flight['From'] ?? null;
                $journeyKey = $flight['JourneyKey'] ?? null;

                if ($journeyKey) {
                    // Split segments by ~
                    $segmentStrings = explode('~', $journeyKey);

                    foreach ($segmentStrings as $segStr) {
                        $fields = array_map('trim', explode(',', $segStr));

                        $fromAirport = Airport::where('iata_code', $fields[2] ?? '')->first();
                        $toAirport = Airport::where('iata_code', $fields[3] ?? '')->first();
                        $airline = Airline::where('iata_code', $fields[0] ?? '')->first();
                        
                        $segments[] = [
                            "ref_id" => (string) \Str::uuid(),
                            "from" => $this->buildAirportData($fromAirport),
                            "to" => $this->buildAirportData($toAirport),
                            "aircraft" => $fields[8] ?? $flight['AirCraft'] ?? null,
                            "arrival_at" => $fields[5] ?? null,
                            "departure_at" => $fields[4] ?? null,
                            "flight_number" => ($fields[0] ?? '') . ($fields[1] ?? ''),
                            "flight_time" => $fields[8] ?? null,
                            "cabin_class" => $flight['Cabin'] ?? 'E',
                            "operating_carrier" => [
                                "iata" => $fields[0] ?? $flight['VAC'] ?? null,
                                "name" => $airline['name'] ?? $flight['VAC'] ?? null,
                                "logo" => isset($fields[0]) ? "https://assets.duffel.com/img/airlines/for-light-background/full-color-logo/{$fields[0]}.svg" : null,
                            ],
                        ];

                        $deptAirport = $fields[3] ?? $deptAirport;
                    }
                } else {
                    // fallback to existing Connections array
                    foreach ($flight['Connections'] ?? [] as $seg) {
                        $fromAirport = Airport::where('iata_code', $deptAirport)->first();
                        $toAirport = Airport::where('iata_code', $seg['Airport'])->first();
                        $connectionDeparture = $flight['DepartureTime'];
                        $connectionDuration = $seg['Duration'];
                        $connectionArrival = $this->addDuration($connectionDeparture, $connectionDuration);
                        $airline = Airline::where('iata_code', $seg['VAC'])->first();
                        
                        $segments[] = [
                            "ref_id" => (string) \Str::uuid(),
                            "from" => $this->buildAirportData($fromAirport),
                            "to" => $this->buildAirportData($toAirport),
                            "aircraft" => $seg['Equipment'] ?? null,
                            "arrival_at" => $connectionArrival,
                            "departure_at" => $connectionDeparture,
                            "flight_number" => $seg['VAC'] . $seg['FlightNo'],
                            "flight_time" => $connectionDuration,
                            "cabin_class" => $seg['Cabin'] ?? 'E',
                            "operating_carrier" => [
                                "iata" => $seg['VAC'],
                                "name" => $airline['name'] ?? $seg['VAC'],
                            ],
                        ];
                        $deptAirport = $seg['Airport'];
                    }
                    
                    $airline = Airline::where('iata_code', $flight['VAC'])->first();

                    // Push main flight as last segment
                    $segments[] = [
                        "ref_id" => (string) \Str::uuid(),
                        "from" => $this->buildAirportData(Airport::where('iata_code', $deptAirport)->first()),
                        "to" => $this->buildAirportData(Airport::where('iata_code', $flight['To'])->first()),
                        "aircraft" => $flight['Equipment'] ?? null,
                        "arrival_at" => $flight['ArrivalTime'] ?? null,
                        "departure_at" => $flight['DepartureTime'] ?? null,
                        "flight_number" => $flight['VAC'] . $flight['FlightNo'],
                        "flight_time" => $flight['Duration'] ?? 0,
                        "cabin_class" => $flight['Cabin'] ?? 'E',
                        "operating_carrier" => [
                            "iata" => $flight['VAC'],
                            "name" => $airline['name'] ?? $flight['VAC'],
                        ],
                    ];
                }

                $connections = max(count($segments) - 1, 0);
                $airline = Airline::where('iata_code', $segments[0]['operating_carrier']['iata'] ?? null)->first();

                // Process fares
                $fares = [];
                foreach ($legData['fares'] as $fare) {
                    $baggagePolicies = [];
                    $baggageText = $fare['Inclusions']['Baggage'] ?? null;
                    $pieceDescription = strtolower($fare['Inclusions']['PieceDescription'] ?? '');

                    $pieces = 0;
                    $weight = null;

                    if ($baggageText) {
                        if (stripos($baggageText, 'kg') !== false || str_contains($pieceDescription, 'weight')) {
                            preg_match('/(\d+)/', $baggageText, $matches);
                            $weight = isset($matches[1]) ? (int) $matches[1] : null;
                        } elseif (stripos($baggageText, 'piece') !== false || str_contains($pieceDescription, 'piece')) {
                            preg_match('/(\d+)/', $baggageText, $matches);
                            $pieces = isset($matches[1]) ? (int) $matches[1] : 0;
                        }
                    }

                    $travelerTypes = ['ADT'];

                    foreach ($travelerTypes as $travelerType) {
                        foreach ($segments as $segment) {
                            $baggagePolicies[] = [
                                "type" => "checkIn",
                                "pieces" => $pieces,
                                "weight" => $weight,
                                "description" => $baggageText ? $fare['Inclusions']['Baggage'] . " allowed" : "No checked baggage",
                                "traveler_type" => $travelerType,
                                "segment_ref_id" => $segment['ref_id'],
                            ];
                        }
                    }
                    
                    $fares[] = [
                        "ref_id" => (string) \Str::uuid(),
                        'index' => $fare['index'] ?? null,
                        'return_identifier' => $fare['ReturnIdentifier'] ?? null,
                        "name" => $fare['FareClass'] ?? 'Economy',
                        "name_class" => $fare['FCType'] ?? 'Economy',
                        "brand_tier" => $fare['FCGroup'] ?? 'Economy',
                        "currency" => [
                            "code" => $currency,
                            "name" => $currency,
                            "symbol" => $currency,
                            "decimal" => 0
                        ],
                        "base_price" => $fare['NetFare'] ?? 0,
                        "taxes" => 0,
                        "total_price" => $fare['NetFare'] ?? 0,
                        "amount_type" => "amount",
                        "margin_type" => "markup",
                        "margin_amount" => 0,
                        "billable_price" => $fare['NetFare'] ?? 0,
                        "is_refundable" => (bool) ($fare['Refundable'] ?? false),
                        "fare_policies" => [],
                        "passenger_fares" => [
                            [
                                "type" => "ADT",
                                "count" => 1,
                                "base_price" => $fare['NetFare'] ?? 0,
                                "taxes" => ($fare['GrossFare'] ?? 0) - ($fare['NetFare'] ?? 0),
                                "total_price" => $fare['GrossFare'] ?? 0,
                                "currency" => "PKR",
                                "total_base_fare" => $fare['NetFare'] ?? 0,
                                "fees" => 0,
                            ]
                        ],
                        "baggage_policies" => $baggagePolicies,
                    ];
                }

                $transformedLegs[] = [
                    "flight_index" => $flight['Index'] ?? null,
                    "ref_id" => (string) \Str::uuid(),
                    "from" => $segments[0]['from'],
                    "to" => end($segments)['to'],
                    "segments" => $segments,
                    "fares" => $fares,
                    "departure_at" => $segments[0]['departure_at'] ?? null,
                    "arrival_at" => end($segments)['arrival_at'] ?? null,
                    "travel_time" => array_sum(array_map(function ($s) {
                        if (preg_match('/(\d+)h\s*(\d+)m/', $s['flight_time'], $m)) {
                            return $m[1] * 60 + $m[2];
                        }
                        return 0;
                    }, $segments)),
                    "has_layovers" => $connections > 0,
                    "layovers_count" => $connections,
                    "change_of_plane" => $connections > 0,
                    "marketing_carrier" => $segments[0]['operating_carrier']
                ];
            }

            $results[] = [
                "provider" => array_merge($provider, [
                    'travel_date' => $transformedLegs[0]['departure_at'] ?? null,
                ]),
                "currencyCode" => $currency,
                "leg" => [
                    "ref_id" => (string) \Str::uuid(),
                    "flights" => $transformedLegs,
                    "trip_nature" => $this->detectTripNature($transformedLegs)
                ]
            ];
        }
        
        Log::info('final transformed results: ' . json_encode($results));
        return $results;
    }

    public static function buildAirportData($airport)
    {
        if (!$airport) {
            return [
                'name' => null,
                'iata' => null,
                'city' => ['name' => null, 'code' => null, 'country' => ['name' => null, 'code' => null]],
                'country' => ['name' => null, 'code' => null],
            ];
        }

        return [
            'name' => $airport['name'] ?? null,
            'iata' => $airport['iata_code'] ?? null,
            'city' => [
                'name' => $airport['city_name'] ?? null,
                'code' => $airport['iata_city_code'] ?? null,
                'country' => [
                    'name' => $airport['iata_country_code'] ?? null,
                    'code' => $airport['iata_country_code'] ?? null,
                ],
            ],
            'country' => [
                'name' => $airport['iata_country_code'] ?? null,
                'code' => $airport['iata_country_code'] ?? null,
            ],
        ];
    }

    public function atFlightProcessor(array $apiResponse): array
    {
        Log::info('AT Flights Raw API Response: ' . json_encode($apiResponse));

        $trips = $apiResponse['Trips'] ?? [];
        $final = [];

        $tripCount = count($trips);
        
        if ($tripCount === 0) {
            return [
                'meta' => [
                    'TUI' => $apiResponse['TUI'] ?? null,
                    'CurrencyCode' => $apiResponse['CurrencyCode'] ?? null,
                    'Completed' => $apiResponse['Completed'] ?? null,
                    'Notices' => $apiResponse['Notices'] ?? [],
                ],
                'flights' => []
            ];
        }
        
        // ONE WAY (1 trip)
        if ($tripCount === 1) {
            $final = $this->groupAllFares($trips[0]['Journey']);
        }
        
        // RETURN (2 trips)
        elseif ($tripCount === 2) {
            $final = $this->processReturnJourney($trips[0]['Journey'], $trips[1]['Journey']);
        }
        
        // MULTI-CITY (3+ trips)
        else {
            $final = $this->processMultiCityJourney($trips);
        }

        return [
            'meta' => [
                'TUI' => $apiResponse['TUI'] ?? null,
                'CurrencyCode' => $apiResponse['CurrencyCode'] ?? null,
                'Completed' => $apiResponse['Completed'] ?? null,
                'Notices' => $apiResponse['Notices'] ?? [],
            ],
            'flights' => $final
        ];
    }

    /**
     * Process return journey (2 trips)
     */
    private function processReturnJourney(array $onwardJourneys, array $returnJourneys): array
    {
        $final = [];
        $processedPairs = [];
        
        $onwardFlights = $this->groupAllFares($onwardJourneys);
        $returnFlights = $this->groupAllFares($returnJourneys);

        foreach ($onwardFlights as $onward) {
            foreach ($returnFlights as $return) {
                $oFlight = $onward['legs']['flight'];
                $rFlight = $return['legs']['flight'];

                // Check if airlines match for pairing
                if ($oFlight['VAC'] === $rFlight['VAC'] && $oFlight['Provider'] === $rFlight['Provider']) {
                    
                    $onwardFares = $onward['legs']['fares'];
                    $returnFares = $return['legs']['fares'];

                    $onwardIdentifiers = collect($onwardFares)
                        ->pluck('ReturnIdentifier')
                        ->filter();

                    $returnIdentifiers = collect($returnFares)
                        ->pluck('ReturnIdentifier')
                        ->filter();

                    $commonIdentifiers = $onwardIdentifiers
                        ->intersect($returnIdentifiers)
                        ->values();

                    if ($commonIdentifiers->isEmpty()) {
                        continue;
                    }

                    $filteredOnwardFares = collect($onwardFares)
                        ->filter(function ($fare) use ($commonIdentifiers) {
                            return in_array($fare['ReturnIdentifier'], $commonIdentifiers->toArray());
                        })
                        ->values()
                        ->toArray();

                    $filteredReturnFares = collect($returnFares)
                        ->filter(function ($fare) use ($commonIdentifiers) {
                            return in_array($fare['ReturnIdentifier'], $commonIdentifiers->toArray());
                        })
                        ->values()
                        ->toArray();

                    $pairKey = $oFlight['Provider'] . '_' . $oFlight['FlightNo'] . '_' . $rFlight['FlightNo'];

                    if (isset($processedPairs[$pairKey])) {
                        continue;
                    }

                    $processedPairs[$pairKey] = true;

                    $final[] = [
                        'type' => 'return',
                        'index' => $pairKey,
                        'onward' => [
                            'flight' => $oFlight,
                            'fares' => $filteredOnwardFares,
                        ],
                        'return' => [
                            'flight' => $rFlight,
                            'fares' => $filteredReturnFares,
                        ],
                    ];
                }
            }
        }
        
        return $final;
    }

    /**
     * Process multi-city journey (3+ trips)
     */
    private function processMultiCityJourney(array $trips): array
    {
        $final = [];
        $processedCombinations = [];
        
        // Extract all legs for each trip
        $allTripLegs = [];
        foreach ($trips as $tripIndex => $trip) {
            $allTripLegs[$tripIndex] = $this->groupAllFares($trip['Journey']);
        }
        
        // If only one trip has flights, return empty
        if (empty($allTripLegs[0])) {
            return [];
        }
        
        // For multi-city, we need to find combinations where fare identifiers match across all legs
        // Start with the first trip's flights
        foreach ($allTripLegs[0] as $firstLeg) {
            $this->findMatchingMultiCityCombinations($allTripLegs, 0, [$firstLeg], $final, $processedCombinations);
        }
        
        return $final;
    }

    /**
     * Recursively find matching fare combinations for multi-city journeys
     */
    private function findMatchingMultiCityCombinations(
        array $allTripLegs, 
        int $currentIndex, 
        array $currentCombination, 
        array &$final, 
        array &$processedCombinations
    ): void {
        $nextIndex = $currentIndex + 1;
        
        // If we've processed all trips, we have a complete combination
        if ($nextIndex >= count($allTripLegs)) {
            $this->addMultiCityCombination($currentCombination, $final, $processedCombinations);
            return;
        }
        
        // Get the next trip's flights
        $nextTripLegs = $allTripLegs[$nextIndex] ?? [];
        
        // Get the current leg's fare identifiers
        $currentFlight = $currentCombination[$currentIndex]['legs']['flight'];
        $currentFares = $currentCombination[$currentIndex]['legs']['fares'];
        $currentIdentifiers = collect($currentFares)
            ->pluck('ReturnIdentifier')
            ->filter()
            ->toArray();
        
        // If no identifiers, we can't match
        if (empty($currentIdentifiers)) {
            return;
        }
        
        // Find matching flights in the next trip
        foreach ($nextTripLegs as $nextLeg) {
            $nextFlight = $nextLeg['legs']['flight'];
            $nextFares = $nextLeg['legs']['fares'];
            
            // Check if airlines/providers match for continuity (optional, adjust as needed)
            // For multi-city, we might want to allow different airlines
            $nextIdentifiers = collect($nextFares)
                ->pluck('ReturnIdentifier')
                ->filter()
                ->toArray();
            
            // Find common fare identifiers
            $commonIdentifiers = array_intersect($currentIdentifiers, $nextIdentifiers);
            
            if (!empty($commonIdentifiers)) {
                // Filter fares to only include matching ones
                $filteredNextFares = collect($nextFares)
                    ->filter(function ($fare) use ($commonIdentifiers) {
                        return in_array($fare['ReturnIdentifier'], $commonIdentifiers);
                    })
                    ->values()
                    ->toArray();
                
                if (!empty($filteredNextFares)) {
                    $newCombination = $currentCombination;
                    $newCombination[$nextIndex] = [
                        'legs' => [
                            'flight' => $nextFlight,
                            'fares' => $filteredNextFares
                        ]
                    ];
                    
                    $this->findMatchingMultiCityCombinations(
                        $allTripLegs, 
                        $nextIndex, 
                        $newCombination, 
                        $final, 
                        $processedCombinations
                    );
                }
            }
        }
    }

    /**
     * Add a valid multi-city combination to the final results
     */
    private function addMultiCityCombination(array $combination, array &$final, array &$processedCombinations): void
    {
        // Create a unique key for this combination
        $combinationKey = implode('|', array_map(function ($leg) {
            return $leg['legs']['flight']['Provider'] . '_' . $leg['legs']['flight']['FlightNo'];
        }, $combination));
        
        if (isset($processedCombinations[$combinationKey])) {
            return;
        }
        
        $processedCombinations[$combinationKey] = true;
        
        // Find common fare identifiers across all legs
        $allIdentifiers = [];
        foreach ($combination as $leg) {
            $identifiers = collect($leg['legs']['fares'])
                ->pluck('ReturnIdentifier')
                ->filter()
                ->toArray();
            $allIdentifiers[] = $identifiers;
        }
        
        // Find identifiers that exist in all legs
        $commonIdentifiers = $allIdentifiers[0] ?? [];
        for ($i = 1; $i < count($allIdentifiers); $i++) {
            $commonIdentifiers = array_intersect($commonIdentifiers, $allIdentifiers[$i]);
        }
        
        if (empty($commonIdentifiers)) {
            return;
        }
        
        // Build the multi-city structure
        $legs = [];
        foreach ($combination as $legIndex => $leg) {
            $filteredFares = collect($leg['legs']['fares'])
                ->filter(function ($fare) use ($commonIdentifiers) {
                    return in_array($fare['ReturnIdentifier'], $commonIdentifiers);
                })
                ->values()
                ->toArray();
            
            $legs[] = [
                'flight' => $leg['legs']['flight'],
                'fares' => $filteredFares
            ];
        }
        
        $final[] = [
            'type' => 'multicity',
            'index' => $combinationKey,
            'legs' => $legs
        ];
    }

    private function groupAllFares(array $journeys): array
    {
        $final = [];
        $processedFlights = [];

        foreach ($journeys as $baseJourney) {
            $flightKey = implode('_', [
                $baseJourney['VAC'] ?? '',
                $baseJourney['Provider'] ?? '',
                $baseJourney['OAC'] ?? '',
                $baseJourney['MAC'] ?? '',
                $baseJourney['FlightNo'] ?? '',
            ]);

            if (isset($processedFlights[$flightKey])) {
                continue;
            }

            $flight = $baseJourney;

            unset(
                $flight['FareClass'],
                $flight['GrossFare'],
                $flight['NetFare'],
                $flight['TrendFare'],
                $flight['TotalCommission'],
                $flight['ActualFare'],
                $flight['WPNetFare'],
                $flight['TotalFare'],
                $flight['FareType'],
                $flight['FBC'],
                $flight['FCType'],
                $flight['FCGroup'],
                $flight['Promo'],
                $flight['Hold'],
                $flight['RBD']
            );

            $fares = [];
            $processedFares = [];

            foreach ($journeys as $fareJourney) {
                if (
                    ($fareJourney['VAC'] ?? '') === ($baseJourney['VAC'] ?? '') &&
                    ($fareJourney['Provider'] ?? '') === ($baseJourney['Provider'] ?? '') &&
                    ($fareJourney['OAC'] ?? '') === ($baseJourney['OAC'] ?? '') &&
                    ($fareJourney['MAC'] ?? '') === ($baseJourney['MAC'] ?? '') &&
                    ($fareJourney['FlightNo'] ?? '') === ($baseJourney['FlightNo'] ?? '')
                ) {
                    $fareKey = implode('_', [
                        $fareJourney['FareClass'] ?? '',
                        $fareJourney['RBD'] ?? '',
                        $fareJourney['FBC'] ?? '',
                        $fareJourney['NetFare'] ?? '',
                    ]);
                    
                    if (!isset($processedFares[$fareKey])) {
                        $fares[] = $this->mapFare($fareJourney);
                        $processedFares[$fareKey] = true;
                    }
                }
            }

            $processedFlights[$flightKey] = true;

            $final[] = [
                'type' => 'oneway',
                'index' => $flightKey,
                'legs' => [
                    'flight' => $flight,
                    'fares' => $fares
                ]
            ];
        }

        return $final;
    }
    
    private function mapFare(array $journey): array
    {
        return [
            'FareClass' => $journey['FareClass'] ?? null,
            'ReturnIdentifier' => $journey['ReturnIdentifier'] ?? null,
            'index' => $journey['Index'] ?? null,
            'RBD' => $journey['RBD'] ?? null,
            'FBC' => $journey['FBC'] ?? null,
            'FCType' => $journey['FCType'] ?? null,
            'FCGroup' => $journey['FCGroup'] ?? null,
            'FareType' => $journey['FareType'] ?? null,
            'GrossFare' => $journey['GrossFare'] ?? null,
            'NetFare' => $journey['NetFare'] ?? null,
            'TrendFare' => $journey['TrendFare'] ?? null,
            'TotalCommission' => $journey['TotalCommission'] ?? null,
            'TotalTransactionFee' => $journey['TotalTransactionFee'] ?? null,
            'TotalVatOnTFee' => $journey['TotalVatOnTFee'] ?? null,
            'ActualFare' => $journey['ActualFare'] ?? null,
            'WPNetFare' => $journey['WPNetFare'] ?? null,
            'Promo' => $journey['Promo'] ?? null,
            'Refundable' => $journey['Refundable'] ?? null,
            'Hold' => $journey['Hold'] ?? null,
            'HoldInfo' => $journey['HoldInfo'] ?? null,
            'Notice' => $journey['Notice'] ?? null,
            'NoticeType' => $journey['NoticeType'] ?? null,
            'NoticeLink' => $journey['NoticeLink'] ?? null,
            'Inclusions' => $journey['Inclusions'] ?? null,
            'Amenities' => $journey['Amenities'] ?? null,
            'IsBusStation' => $journey['IsBusStation'] ?? null,
            'Remarks' => $journey['Remarks'] ?? null,
        ];
    }

    private function addDuration(string $dateTime, string $duration): string
    {
        $dt = new DateTime($dateTime);

        preg_match('/(\d+)h\s*(\d+)m/', $duration, $matches);

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);

        $dt->modify("+{$hours} hours +{$minutes} minutes");

        return $dt->format('Y-m-d H:i:s');
    }
    
    /**
     * Detect trip nature based on legs
     */
    private function detectTripNature(array $legs): string
    {
        if (count($legs) === 1) {
            return 'oneway';
        } elseif (count($legs) === 2) {
            // Check if it's a return journey (same cities but reversed)
            $firstOrigin = $legs[0]['from']['iata'] ?? null;
            $firstDestination = $legs[0]['to']['iata'] ?? null;
            $secondOrigin = $legs[1]['from']['iata'] ?? null;
            $secondDestination = $legs[1]['to']['iata'] ?? null;
            
            if ($firstOrigin === $secondDestination && $firstDestination === $secondOrigin) {
                return 'return';
            }
            return 'multicity';
        } else {
            return 'multicity';
        }
    }
}