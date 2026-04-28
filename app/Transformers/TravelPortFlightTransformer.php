<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class TravelPortFlightTransformer
{

    protected $contentSource;
    protected $catalogueRefId;
    protected $airportTimezones = [];

    public function transformTravelPort($flightData, $airlineParams)
    {
        $results = [];
        $tripType = $airlineParams['flight_type']; // one-way | return
        // Log::info("FlightDate before transformations:" . json_encode($flightData));
        /* -------------------------------------------------bundle
         | NORMALIZE FLIGHT BY COMBINATION INDEX
         -------------------------------------------------*/
        $normalizeFlight = function ($flight, int $combinationIndex) {

            $combination = $flight['flightCombination'][$combinationIndex];
            $totalItineraries = count($flight['flightCombination']);
            $segments = [];
            $totalFlightTime = $this->convertDurationToMinutes($combination['total_flight_duration'] ?? null);
            $previousArrival = null;
            $this->contentSource = $combination['contentSource'];
            $this->catalogueRefId = $flight['productOfferingIdentifier'];


            // $interval = new DateInterval($totalFlightTime);

            // $hours = $interval->h;
            // $minutes = $interval->i;

            // // Total minutes
            // $totalFlightTime = ($hours * 60) + $minutes;

            // // Total hours (decimal)
            // $totalHoursDecimal = $hours + ($minutes / 60);



            foreach ($combination['flightDetails'] as $seg) {

                $info = $seg['flightInformation'];

                $departureAirportCode = $info['location'][0]['locationId'] ?? null;
                $arrivalAirportCode = $info['location'][1]['locationId'] ?? null;

                $dep = $this->parseTravelportLocalDateTime(
                    $info['productDateTime']['dateOfDeparture'] ?? null,
                    $info['productDateTime']['timeOfDeparture'] ?? null,
                    $departureAirportCode
                );

                $arr = $this->parseTravelportLocalDateTime(
                    $info['productDateTime']['dateOfArrival'] ?? null,
                    $info['productDateTime']['timeOfArrival'] ?? null,
                    $arrivalAirportCode
                );

                $depUtc = $dep ? $dep->copy()->utc() : null;
                $arrUtc = $arr ? $arr->copy()->utc() : null;

                // Handle overnight/multi-day arrival when timezones differ.
                if ($depUtc && $arrUtc && $arrUtc->lt($depUtc)) {
                    $attempts = 0;
                    while ($attempts < 3 && $arrUtc->lt($depUtc)) {
                        $arr->addDay();
                        $arrUtc = $arr->copy()->utc();
                        $attempts++;
                    }
                }

                $segmentFlightTime = 0;
                if ($depUtc && $arrUtc) {
                    $segmentFlightTime = max(0, $depUtc->diffInMinutes($arrUtc, false));
                }

                // ✅ Layover calculation
                $layoverTime = 0;
                if ($previousArrival && $depUtc) {
                    $layoverTime = max(0, $previousArrival->diffInMinutes($depUtc, false));
                }

                // Update previous arrival for next segment
                $previousArrival = $arrUtc;
                $fromAirport = Airport::where(
                    'iata_code',
                    $departureAirportCode
                )->first();
                $toAirport = Airport::where(
                    'iata_code',
                    $arrivalAirportCode
                )->first();

                $airline = Airline::where(
                    'iata_code',
                    $info['companyId']['marketingCarrier'] ?? null
                )->first();
                $from_terminal = [
                    'Gate' => $info['location'][0]['terminal']
                ];
                $to_terminal = [
                    'Gate' => $info['location'][1]['terminal']

                ];

                $segments[] = [
                    'ref_id' => (string) \Str::uuid(),
                    'segment_ref' => $info['segmentRef'] ?? null,
                    'from' => $this->buildAirportData($fromAirport),
                    'to' => $this->buildAirportData($toAirport),
                    'from_terminal' => $from_terminal,
                    'to_terminal' => $to_terminal,
                    'departure_at' => $dep ? $dep->toIso8601String() : null,
                    'arrival_at' => $arr ? $arr->toIso8601String() : null,
                    'flight_number' => $info['flightOrtrainNumber'],
                    'cabin_class' => $info['addProductDetail']['cabinClass'] ?? null,
                    'rbd_code' => $info['addProductDetail']['rbdCode'] ?? null,
                    'aircraft' => $info['productDetail']['equipmentType'],
                    'availability_source_code' => $info['AvailabilitySourceCode'] ?? null,
                    'flight_time' => $segmentFlightTime,
                    'layover_time' => $layoverTime, // ✅ minutes
                    'operating_carrier' => [
                        'name' => $airline->name ?? null,
                        'iata' => $airline->iata_code ?? null,
                        'logo' => $airline->logo_url ?? null,
                    ],
                ];
            }


            /* -------------------------------------------------
             | FARES (SAME FOR BOTH LEGS)
             -------------------------------------------------*/
            $fares = [];

            $marketingCarrier = Airline::where(
                'iata_code',
                $combination['flightDetails'][0]['flightInformation']['companyId']['marketingCarrier'] ?? null
            )->first();

            foreach ($flight['bundleServices'] ?? [] as $bundle) {

                $summary = $bundle['fareSummary'];
                $passengerFares = [];

                foreach ($summary['breakdown'] ?? [] as $paxType => $paxData) {
                    $passengerFares[] = [
                        'traveler_type' => $paxType,
                        'total_passenger' => $paxData['paxCount'] ?? 0,
                        'currency' => $paxData['currency'] ?? null,
                        'base_price' => $paxData['totalBaseFareAmount'] / $totalItineraries ?? 0,
                        'service_charges' => 0,
                        'surchage' => 0,
                        'total_base_fare' => $paxData['totalBaseFareAmount'] / $totalItineraries ?? 0,
                        'total_price' => $paxData['totalFareAmount'] / $totalItineraries ?? 0,
                        'taxes' => $paxData['totalTaxAmount'] / $totalItineraries ?? 0,
                        'fees' => $paxData['totalFeesAmount']['TotalFees'] / $totalItineraries ?? 0,
                    ];
                }
                $segmentRefIds = array_column($segments, 'ref_id');

                $baggageForThisFlight = $bundle['baggage'][$combinationIndex] ?? null;
                $segmentCabinRbd = $bundle['segmentCabinRbd'][$combinationIndex] ?? [];
                $segmentCabins = [];
                $segmentRbds = [];
                foreach ($segments as $segmentItem) {
                    $segmentRef = $segmentItem['segment_ref'] ?? null;
                    if ($segmentRef && isset($segmentCabinRbd[$segmentRef])) {
                        $segmentCabins[$segmentRef] = $segmentCabinRbd[$segmentRef]['cabin'] ?? null;
                        $segmentRbds[$segmentRef] = $segmentCabinRbd[$segmentRef]['rbd'] ?? null;
                    }
                }
                if (empty($segmentCabins) && isset($bundle['cabin'])) {
                    $segmentCabins = $bundle['cabin'];
                }
                if (empty($segmentRbds) && isset($bundle['rbds'])) {
                    $segmentRbds = $bundle['rbds'];
                }

                /* -------- BAGGAGE -------- */
                $baggage_policies = [];

                if ($baggageForThisFlight && !empty($segmentRefIds)) {
                    foreach ($segmentRefIds as $segmentRefId) {
                        foreach ($baggageForThisFlight as $paxType => $baggageInfo) {

                            // Carry-on
                            $baggage_policies[] = [
                                'segment_ref_id' => $segmentRefId,
                                'traveler_type' => strtoupper($paxType),
                                'type' => 'carry',
                                'pieces' => 1,
                                'weight' => '7kg',
                                'description' => '1 hand bag upto 7kg',
                            ];

                            // Checked baggage
                            if (!empty($baggageInfo['freeAllowance'])) {
                                $baggage_policies[] = [
                                    'segment_ref_id' => $segmentRefId,
                                    'traveler_type' => strtoupper($paxType),
                                    'type' => 'checked',
                                    'pieces' => 1,
                                    'weight' => $baggageInfo['freeAllowance'] . ' ' . ($baggageInfo['unitQualifier'] ?? ''),
                                    'description' =>
                                        "{$baggageInfo['freeAllowance']} {$baggageInfo['unitQualifier']} allowed",
                                ];
                            }
                        }
                    }
                }

                $fares[] = [
                    'ref_id' => $bundle['combinableCode'],
                    'product_identifier' => $bundle['bundleOptions'][$combinationIndex]['productIdentifier'] ?? null,
                    'offer_identifier' => $bundle['bundleOptions'][$combinationIndex]['offerIdentifier'] ?? null,
                    'name' => $bundle['bundleOptions'][0]['bundledServiceName'] ?? 'Economy',
                    'name_class' => $bundle['bundleOptions'][0]['fareFamilyName'] ?? 'Economy',
                    'brand_tier' => $bundle['bundleOptions'][0]['tier'] ?? 'Economy',
                    'refundable' => $bundle['fareSummary']['refundable'],
                    'cabin_class' => $segmentCabins,
                    'rbd_code' => $segmentRbds,
                    'passenger_fares' => $passengerFares,
                    'baggage_policies' => $baggage_policies,
                    'fare_policies' => $bundle['bundleOptions'][$combinationIndex]['includedServices'] ?? [],
                    'base_price' => $summary['totalBaseFareAmount'] / $totalItineraries,
                    'taxes' => $summary['totalTaxAmount'] / $totalItineraries,
                    'fees' => $summary['totalFeesAmount'] / $totalItineraries,
                    'total_price' => $summary['totalFareAmount'] / $totalItineraries,
                    'billable_price' => $bundle['totalPaxBundledFee'] / $totalItineraries ?? null,
                    'currency' => $summary['currency'],
                    'is_refundable' => $summary['refundable'],
                    'margin_amount' => $marketingCarrier->margin_amount ?? 0,
                    'amount_type' => $marketingCarrier->amount_type ?? 'amount',
                    'margin_type' => $marketingCarrier->margin_type ?? 'markup',
                ];
            }

            return [

                'ref_id' => (string) \Str::uuid(),
                'catalogue_ref_id' => $combination['CatalogProductOfferingsIdentifier'] ?? null,
                'segments' => $segments,
                'from' => $segments[0]['from'],
                'to' => end($segments)['to'],
                'departure_at' => $segments[0]['departure_at'],
                'arrival_at' => end($segments)['arrival_at'],
                'travel_time' => $totalFlightTime,
                'has_layovers' => count($segments) > 1,
                'layovers_count' => count($segments) - 1,
                'flight_number' => $segments[0]['flight_number'],
                'change_of_plane' => count($segments) > 1,
                'is_refundable' => $flight['fareSummary']['refundable'],
                'marketing_carrier' => [
                    'name' => $marketingCarrier->name ?? null,
                    'iata' => $marketingCarrier->iata_code ?? null,
                    'logo' => $marketingCarrier->logo_url ?? null,
                ],
                'fares' => $fares,
            ];
        };

        /* -------------------------------------------------
         | BUILD RESULT
         -------------------------------------------------*/
        foreach ($flightData as $flight) {
            $flights = [];
            $combinationCount = count($flight['flightCombination'] ?? []);

            for ($i = 0; $i < $combinationCount; $i++) {
                $flights[] = $normalizeFlight($flight, $i);
            }
            $sectorParts = [];

            foreach ($flights as $flight) {
                $sectorParts[] = $flight['from']['iata'];
                $sectorParts[] = $flight['to']['iata'];
            }

            $sector = implode('-', $sectorParts);
            $results[] = [
                'leg' => [
                    'ref_id' => (string) Str::uuid(),
                    'flights' => $flights,
                    'trip_nature' => 'international',
                ],
                'provider' => [
                    'name' => 'travelport',
                    'identifier' => 'TravelPort',
                    'contentSource' => $this->contentSource,
                    'catalogueRefId' => $this->catalogueRefId,
                    'sector' => $sector, // ✅ combined journey sector
                    'travel_date' => $flights[0]['departure_at'] ?? null, // ✅ first departure date

                ],
            ];
        }

        Log::info('Transformed TravelPort Flights'.json_encode( $results) ?? []);
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

    private function convertDurationToMinutes($duration): int
    {
        if (is_numeric($duration)) {
            return (int) round((float) $duration);
        }

        if (!is_string($duration) || trim($duration) === '') {
            return 0;
        }

        try {
            $interval = new DateInterval($duration);

            return ($interval->d * 24 * 60)
                + ($interval->h * 60)
                + $interval->i
                + (int) round($interval->s / 60);
        } catch (\Exception $exception) {
            return 0;
        }
    }

    private function parseTravelportLocalDateTime(?string $dateOf, ?string $timeOf, ?string $airportCode): ?Carbon
    {
        if (empty($dateOf) || empty($timeOf)) {
            return null;
        }

        $timezone = $this->getAirportTimezone($airportCode);

        try {
            return $timezone
                ? Carbon::createFromFormat('dmy Hi', "{$dateOf} {$timeOf}", $timezone)
                : Carbon::createFromFormat('dmy Hi', "{$dateOf} {$timeOf}");
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function getAirportTimezone(?string $airportCode): ?string
    {
        if (empty($airportCode)) {
            return null;
        }

        if (array_key_exists($airportCode, $this->airportTimezones)) {
            return $this->airportTimezones[$airportCode];
        }

        $timezone = Airport::where('iata_code', $airportCode)->value('time_zone');
        $this->airportTimezones[$airportCode] = $timezone ?: null;

        return $this->airportTimezones[$airportCode];
    }


}
