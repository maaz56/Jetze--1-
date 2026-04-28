<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class FlydubaiFlightTransformer
{


    // public static function fromFlydubai($flightData, $params)
    // {
    // Log::info("Flydubai Raw Data: " . json_encode($flightData));
    //     $flightType = $params['flight_type'] ?? 'one-way';
    //     $result = [];

    //     $root = $flightData['RetrieveFareQuoteDateRangeResponse']['RetrieveFareQuoteDateRangeResult'] ?? null;
    //     if (!$root || empty($root['FlightSegments']['FlightSegment'])) {
    //         return $result;
    //     }

    //     $currency = $root['CurrencyOfFareQuote'] ?? 'PKR';
    //     $taxDetails = collect($root['TaxDetails']['TaxDetail'] ?? [])
    //         ->keyBy('TaxID')
    //         ->toArray();

    //     $previousArrival = null;
    //     $isFirstLeg = true;
    //     $pendingOneLegs = []; // Store one-leg flights for later pairing

    //     foreach ($root['FlightSegments']['FlightSegment'] as $index => $segment) {
    //         $segmentId = $segment['LFID'];
    //         $legCount = intval($segment['LegCount'] ?? 1);
    //         $segmentDetail = collect($root['SegmentDetails']['SegmentDetail'] ?? [])->firstWhere('LFID', $segmentId);

    //         $segments = [];
    //         $previousArrival = null;
    //         $isFirstLeg = true;

    //         // --- Build individual leg segments with time & layover ---
    //         foreach ($segment['FlightLegDetails']['FlightLegDetail'] ?? [] as $leg) {
    //             $legDetail = collect($root['LegDetails']['LegDetail'] ?? [])->firstWhere('PFID', $leg['PFID'] ?? null);

    //             $airline = Airline::where('iata_code', $legDetail['MarketingCarrier'] ?? 'FZ')->first();
    //             $operatingCarrier = [
    //                 "name" => $airline->name ?? 'Flydubai',
    //                 "iata" => $airline->iata_code ?? 'FZ',
    //                 "logo" => $airline->logo_url,
    //             ];

    //             $fromAirport = Airport::where('iata_code', $legDetail['Origin'] ?? null)->first();
    //             $toAirport = Airport::where('iata_code', $legDetail['Destination'] ?? null)->first();

    //             $from = [
    //                 'name' => $fromAirport['name'] ?? null,
    //                 'iata' => $fromAirport['iata_code'] ?? null,
    //                 'city' => [
    //                     'name' => $fromAirport['city_name'] ?? null,
    //                     'iata' => $fromAirport['iata_city_code'] ?? null,
    //                     'country' => [
    //                         'name' => $fromAirport['iata_country_code'] ?? null,
    //                         'code' => $fromAirport['iata_country_code'] ?? null,
    //                     ],
    //                 ],
    //                 'country' => [
    //                     'name' => $fromAirport['iata_country_code'] ?? null,
    //                     'code' => $fromAirport['iata_country_code'] ?? null,
    //                 ],
    //             ];

    //             $to = [
    //                 'name' => $toAirport['name'] ?? null,
    //                 'iata' => $toAirport['iata_code'] ?? null,
    //                 'city' => [
    //                     'name' => $toAirport['city_name'] ?? null,
    //                     'iata' => $toAirport['iata_city_code'] ?? null,
    //                     'country' => [
    //                         'name' => $toAirport['iata_country_code'] ?? null,
    //                         'code' => $toAirport['iata_country_code'] ?? null,
    //                     ],
    //                 ],
    //                 'country' => [
    //                     'name' => $toAirport['iata_country_code'] ?? null,
    //                     'code' => $toAirport['iata_country_code'] ?? null,
    //                 ],
    //             ];

    //             // --- Layover Time ---
    //             $layoverTime = null;
    //             if (!$isFirstLeg && $previousArrival) {
    //                 $currentDeparture = Carbon::parse($legDetail['DepartureDate']);
    //                 $layoverTime = $previousArrival->diffInMinutes($currentDeparture);
    //             }

    //             $previousArrival = isset($legDetail['ArrivalDate'])
    //                 ? Carbon::parse($legDetail['ArrivalDate'])
    //                 : null;

    //             $segments[] = [
    //                 "ref_id" => $legDetail['PFID'] ?? 'FZ_LEG_' . uniqid(),
    //                 "marketing_carrier" => $legDetail['MarketingCarrier'] ?? 'FZ',
    //                 "operating_carrier" => $operatingCarrier,
    //                 "flight_number" => $legDetail['FlightNum'] ?? '',
    //                 "aircraft" => $legDetail['Equipment'] ?? '',
    //                 "departure_at" => $legDetail['DepartureDate'] ?? '',
    //                 "arrival_at" => $legDetail['ArrivalDate'] ?? '',
    //                 "from" => $from,
    //                 "to" => $to,
    //                 "layover_time" => $layoverTime,
    //                 "duration" => $legDetail['FlightTime'] ?? 0,
    //                 "operating_flight_number" => $legDetail['FlightNum'] ?? '',
    //             ];

    //             $isFirstLeg = false;
    //         }

    //         // --- Fare Mapping ---
    //         $fares = [];
    //         foreach ($segment['FareTypes']['FareType'] ?? [] as $fareType) {
    //             $fareFamily = $fareType['FareTypeName'] ?? '';
    //             foreach ($fareType['FareInfos']['FareInfo'] ?? [] as $fareInfo) {
    //                 foreach ($fareInfo['Pax'] ?? [] as $pax) {
    //                     $fareCode = $pax['FBCode'] ?? '';
    //                     $baseFare = $pax['BaseFareAmt'] ?? 0;
    //                     $totalFare = $pax['FareAmtInclTax'] ?? 0;
    //                     $taxTotal = $pax['DisplayTaxSum'] ?? 0;
    //                     $cabin = $pax['Cabin'] ?? 'ECONOMY';
    //                     $refundable = ($fareType['Refundable'] ?? 0) == 1;

    //                     // Baggage Policies
    //                     $baggagePolicies = [];
    //                     foreach (($pax['ApplicableTaxDetails']['ApplicableTaxDetail'] ?? []) as $taxItem) {
    //                         $taxInfo = $taxDetails[$taxItem['TaxID']] ?? null;
    //                         if ($taxInfo && str_starts_with($taxInfo['TaxCode'], 'BAG')) {
    //                             $baggagePolicies[] = [
    //                                 "segment_ref_id" => $segmentDetail['FlightNum'] ?? '',
    //                                 "title" => $taxInfo['TaxDesc'] ?? 'Baggage',
    //                                 "description" => $taxInfo['TaxDesc'] ?? '',
    //                                 "weight_limit" => intval(preg_replace('/[^0-9]/', '', $taxInfo['TaxDesc'] ?? '')) ?: 0,
    //                                 "weight_unit" => 'KG',
    //                                 "pieces" => 1,
    //                                 "type" => 'baggage',
    //                                 "traveler_type" => 'ADT',
    //                             ];
    //                         }
    //                     }

    //                     // Fare & Change Policies
    //                     $farePolicies = [];
    //                     foreach (($pax['Penalties']['ChangeFees']['ChangeFee'] ?? []) as $pen) {
    //                         $penType = $pen['Type'] ?? 'Unknown';
    //                         $toTime = $pen['ToTime'] ?? 'N/A';
    //                         $percentage = $pen['Percentage'] ?? 'N/A';
    //                         $currencyCode = $pen['Currency'] ?? 'AED';
    //                         $amount = $pen['Amount'] ?? 0;
    //                         $farePolicies[] = ["segment_ref_id" => $segmentDetail['FlightNum'] ?? '', "title" => "Change Fee ({$penType})", "description" => "Change before {$toTime}h - {$percentage}% or {$currencyCode} {$amount}", "type" => "change_fee", "price" => $amount, "currency" => $currencyCode, "traveler_type" => 'ADT',];
    //                     }
    //                     foreach (($pax['Penalties']['CancellationFees']['RefundPenalty'] ?? []) as $pen) {
    //                         $penType = $pen['Type'] ?? 'Unknown';
    //                         $toTime = $pen['ToTime'] ?? 'N/A';
    //                         $percentage = $pen['Percentage'] ?? 'N/A';
    //                         $currencyCode = $pen['Currency'] ?? 'AED';
    //                         $amount = $pen['Amount'] ?? 0;
    //                         $farePolicies[] = ["segment_ref_id" => $segmentDetail['FlightNum'] ?? '', "title" => "Cancellation Fee ({$penType})", "description" => "Refund before {$toTime}h - {$percentage}% or {$currencyCode} {$amount}", "type" => "refund_fee", "price" => $amount, "currency" => $currencyCode, "traveler_type" => 'ADT',];
    //                     }

    //                     $passengerFares = [
    //                         [
    //                             "traveler_type" => "ADT",
    //                             "total_passenger" => $pax['PaxCount'] ?? 1,
    //                             "base_price" => $baseFare,
    //                             "taxes" => $taxTotal,
    //                             "fees" => 0,
    //                             "total_price" => $totalFare,
    //                             "currency" => $currency,
    //                         ],
    //                     ];

    //                     $fares[] = [
    //                         "ref_id" => $fareCode,
    //                         "is_refundable" => $refundable,
    //                         "name_class" => $fareFamily,
    //                         "class" => $cabin,
    //                         "available_seats" => $pax['SeatsAvailable'] ?? 0,
    //                         "passenger_fares" => $passengerFares,
    //                         "fare_policies" => $farePolicies,
    //                         "baggage_policies" => $baggagePolicies,
    //                         "currency" => $currency,
    //                         "base_price" => $baseFare,
    //                         "taxes" => $taxTotal,
    //                         "total_price" => $totalFare,
    //                     ];
    //                 }
    //             }
    //         }

    //         // --- Build the Flight Object ---
    //         $airline = Airline::where('iata_code', $segmentDetail['CarrierCode'] ?? 'FZ')->first();
    //         $marketingCarrier = [
    //             "name" => $airline->name ?? 'Flydubai',
    //             "iata" => $airline->iata_code ?? 'FZ',
    //             "logo" => $airline->logo_url,
    //         ];
    //         // Get origin airport details
    //         $fromAirportDetails = Airport::where('iata_code', $segmentDetail['Origin'] ?? null)->first();
    //         $from = [
    //             'name' => $fromAirportDetails['name'] ?? null,
    //             'iata' => $fromAirportDetails['iata_code'] ?? null,
    //             'city' => [
    //                 'name' => $fromAirportDetails['city_name'] ?? null,
    //                 'code' => $fromAirportDetails['iata_city_code'] ?? null,
    //                 'country' => [
    //                     'name' => $fromAirportDetails['iata_country_code'] ?? null,
    //                     'code' => $fromAirportDetails['iata_country_code'] ?? null,
    //                 ],
    //             ],
    //             'country' => [
    //                 'name' => $fromAirportDetails['iata_country_code'] ?? null,
    //                 'code' => $fromAirportDetails['iata_country_code'] ?? null,
    //             ],
    //         ];

    //         // Get destination airport details
    //         $toAirportDetails = Airport::where('iata_code', $segmentDetail['Destination'] ?? null)->first();
    //         $to = [
    //             'name' => $toAirportDetails['name'] ?? null,
    //             'iata' => $toAirportDetails['iata_code'] ?? null,
    //             'city' => [
    //                 'name' => $toAirportDetails['city_name'] ?? null,
    //                 'code' => $toAirportDetails['iata_city_code'] ?? null,
    //                 'country' => [
    //                     'name' => $toAirportDetails['iata_country_code'] ?? null,
    //                     'code' => $toAirportDetails['iata_country_code'] ?? null,
    //                 ],
    //             ],
    //             'country' => [
    //                 'name' => $toAirportDetails['iata_country_code'] ?? null,
    //                 'code' => $toAirportDetails['iata_country_code'] ?? null,
    //             ],
    //         ];

    //         // Build the leg flights array
    //         $legFlights = [
    //             [
    //                 "ref_id" => $segmentDetail['FlightNum'] ?? 'FZ_SEG_' . uniqid(),
    //                 "flight_operation" => ($segmentDetail['Stops'] ?? 0) > 0 ? 'In-Direct' : 'Direct',
    //                 "is_recommended" => true,
    //                 "is_refundable" => false,
    //                 "recommended_priority" => 1,
    //                 "has_layovers" => ($segmentDetail['Stops'] ?? 0) > 0,
    //                 "layovers_count" => $segmentDetail['Stops'] ?? 0,
    //                 "travel_time" => $segmentDetail['FlightTime'] ?? 0,
    //                 "marketing_carrier" => $marketingCarrier,
    //                 "departure_at" => $segmentDetail['DepartureDate'] ?? '',
    //                 "arrival_at" => $segmentDetail['ArrivalDate'] ?? '',
    //                 "from" => $from,
    //                 "to" => $to,
    //                 "segments" => $segments,
    //                 "ancillaries" => [],
    //                 "fares" => $fares,
    //             ]
    //         ];
    //         $mappedSegment = [
    //             "ref_id" => "FZ_" . $segmentId,
    //             "provider" => ["name" => "flydubai", "code" => "FZ"],
    //             "leg" => [
    //                 "ref_id" => "LEG_" . $segmentId,
    //                 "trip_nature" => "OneWay",
    //                 "flights" => $legFlights,
    //             ],
    //         ];

    //         // --- Combine logic for Return flights ---
    //         if ($flightType === 'return') {
    //             if ($legCount == 1) {
    //                 // store this one-leg flight temporarily
    //                 $pendingOneLegs[$index] = $mappedSegment;
    //             } elseif ($legCount == 2) {
    //                 // find an unpaired one-leg flight
    //                 $unpairedKey = array_key_first($pendingOneLegs);
    //                 if ($unpairedKey !== null) {
    //                     $outbound = $pendingOneLegs[$unpairedKey];
    //                     $combined = [
    //                         "ref_id" => "FZ_COMBINED_" . uniqid(),
    //                         "provider" => ["name" => "flydubai", "code" => "FZ","source" => "FlyDubai"],
    //                         "leg" => [
    //                             "ref_id" => "LEG_COMBINED_" . uniqid(),
    //                             "trip_nature" => "Return",
    //                             "flights" => array_merge(
    //                                 $outbound['leg']['flights'],
    //                                 $mappedSegment['leg']['flights']
    //                             ),
    //                         ],
    //                     ];
    //                     $result[] = $combined;
    //                     unset($pendingOneLegs[$unpairedKey]);
    //                 } else {
    //                     $result[] = $mappedSegment;
    //                 }
    //             } else {
    //                 $result[] = $mappedSegment;
    //             }
    //         } else {
    //             $result[] = $mappedSegment;
    //         }
    //     }

    //     // add remaining one-legs if not paired
    //     foreach ($pendingOneLegs as $remaining) {
    //         $result[] = $remaining;
    //     }

    // Log::info("Mapped Flydubai Result: " . json_encode($result, JSON_PRETTY_PRINT));
    //     return $result;
    // }

    public function fromFlydubai($flightData, $params)
    {
        $flightType = $params['flight_type'] ?? 'one-way';
        $result = [];

        $root = $flightData['RetrieveFareQuoteDateRangeResponse']['RetrieveFareQuoteDateRangeResult'] ?? null;
        if (!$root || empty($root['FlightSegments']['FlightSegment'])) {
            return $result;
        }

        $currency = $root['CurrencyOfFareQuote'] ?? 'PKR';
        $taxDetails = collect($root['TaxDetails']['TaxDetail'] ?? [])
            ->keyBy('TaxID')
            ->toArray();

        // Build lookup tables for efficient access
        $segmentDetails = collect($root['SegmentDetails']['SegmentDetail'] ?? [])->keyBy('LFID');
        $legDetails = collect($root['LegDetails']['LegDetail'] ?? [])->keyBy('PFID');

        // Build combinability pairs - each SolnRef contains [outboundSolnId, inboundSolnId] pairs
        $combinabilityPairs = [];
        foreach ($root['Combinability']['BS'] ?? [] as $combo) {
            $solnRefs = $combo['SolnRef'] ?? [];
            // Each SolnRef should contain pairs like [outboundSolnId, inboundSolnId]
            if (count($solnRefs) >= 2) {
                $combinabilityPairs[] = [
                    'outbound' => $solnRefs[0],
                    'inbound' => $solnRefs[1]
                ];
            }
        }

        // Log::info("Combinability Pairs: " . json_encode($combinabilityPairs));

        $outboundSegments = [];
        $inboundSegments = [];

        // First, separate outbound (legCount=1) and inbound (legCount=2) segments
        foreach ($root['FlightSegments']['FlightSegment'] as $segment) {
            $segmentId = $segment['LFID'];
            $legCount = intval($segment['LegCount'] ?? 1);
            $segmentDetail = $segmentDetails->get($segmentId);
            $rootSegment = $segment; // Preserve for inner scope
            // --- Build individual leg segments ---
            $segments = [];
            $segmentPFIDs = []; // Track PFIDs for this segment
            $previousArrival = null;
            $isFirstLeg = true;

            foreach ($segment['FlightLegDetails']['FlightLegDetail'] ?? [] as $leg) {
                $pfid = $leg['PFID'] ?? null;
                $segmentPFIDs[] = $pfid; // Store for fare mapping
                $legDetail = $legDetails->get($pfid);

                if (!$legDetail)
                    continue;

                $airline = Airline::where('iata_code', $legDetail['MarketingCarrier'] ?? 'FZ')->first();
                $operatingCarrier = [
                    "name" => $airline->name ?? 'Flydubai',
                    "iata" => $airline->iata_code ?? 'FZ',
                    "logo" => $airline->logo_url,
                ];

                $fromAirport = Airport::where('iata_code', $legDetail['Origin'] ?? null)->first();
                $toAirport = Airport::where('iata_code', $legDetail['Destination'] ?? null)->first();

                $from = $this->buildAirportData($fromAirport);
                $to = $this->buildAirportData($toAirport);

                // Layover Time
                $layoverTime = null;
                if (!$isFirstLeg && $previousArrival) {
                    $currentDeparture = Carbon::parse($legDetail['DepartureDate']);
                    $layoverTime = $previousArrival->diffInMinutes($currentDeparture);
                }

                $previousArrival = isset($legDetail['ArrivalDate'])
                    ? Carbon::parse($legDetail['ArrivalDate'])
                    : null;

                $segments[] = [
                    "ref_id" => $pfid,
                    "marketing_carrier" => $legDetail['MarketingCarrier'] ?? 'FZ',
                    "operating_carrier" => $operatingCarrier,
                    "flight_number" => $legDetail['FlightNum'] ?? '',
                    "aircraft" => $legDetail['EQP'] ?? '',
                    "departure_at" => $legDetail['DepartureDate'] ?? '',
                    "arrival_at" => $legDetail['ArrivalDate'] ?? '',
                    "from" => $from,
                    "to" => $to,
                    "layover_time" => $layoverTime,
                    "duration" => $legDetail['FlightTime'] ?? 0,
                    "operating_flight_number" => $legDetail['FlightNum'] ?? '',
                ];

                $isFirstLeg = false;
            }

            // --- CORRECTED: Map fares to segments using BookingCodes ---
            $fares = [];
            $groupedFares = []; // To collect fares grouped by FareTypeId

            foreach ($segment['FareTypes']['FareType'] ?? [] as $fareType) {
                $fareTypeId = $fareType['FareTypeID'] ?? ($fareType['FareTypeName'] ?? uniqid('FT_'));
                $fareFamily = $fareType['FareTypeName'] ?? '';
                $refundable = ($fareType['Refundable'] ?? 0) == 1;
                $solnId = $fareType['SolnId'] ?? null;

                // Initialize fare accumulators
                $totalBaseFare = 0;
                $totalTax = 0;
                $totalFare = 0;
                $availableSeats = 0;
                $allFarePolicies = [];
                $allBaggagePolicies = [];
                $allFareSegments = [];
                $cabin = 'ECONOMY';
                $currencyCode = $currency ?? 'AED';

                foreach ($fareType['FareInfos']['FareInfo'] ?? [] as $fareInfo) {
                    $passengerFares = [];
                    foreach ($fareInfo['Pax'] ?? [] as $pax) {
                        $paxCount = $pax['PaxCount'] ?? 1;

                        $baseFare = ($pax['DisplayFareAmt'] ?? 0) * $paxCount;
                        $displayFare = ($pax['FareAmtInclTax'] ?? 0) * $paxCount;
                        $displayTax = ($pax['DisplayTaxSum'] ?? 0) * $paxCount;

                        $totalBaseFare += $baseFare;
                        $totalTax += $displayTax;
                        $totalFare += $displayFare;
                        $availableSeats = max($availableSeats, $pax['SeatsAvailable'] ?? 0);

                        $cabin = $pax['Cabin'] ?? $cabin;
                        $fareCode = $pax['FBCode'] ?? '';

                        // --- Fare Segments ---
                        $bookingCodes = $pax['BookingCodes']['Bookingcode'] ?? [];
                        foreach ($bookingCodes as $bookingCode) {
                            $departureDate = $bookingCode['DepartureDate'] ?? '';
                            $rbd = $bookingCode['RBD'] ?? '';
                            $cabin = $bookingCode['Cabin'] ?? $cabin;

                            foreach ($segments as $seg) {
                                if ($seg['departure_at'] === $departureDate) {
                                    $allFareSegments[] = [
                                        "segment_ref_id" => $seg['ref_id'],
                                        "PaxID" => $pax['ID'] ?? '',
                                        "fare_class" => $rbd,
                                        "cabin" => $cabin,
                                        "booking_class" => $rbd,
                                    ];
                                    break;
                                }
                            }
                        }

                        // --- Baggage Policies ---
                        foreach (($pax['ApplicableTaxDetails']['ApplicableTaxDetail'] ?? []) as $taxItem) {
                            $taxInfo = $taxDetails[$taxItem['TaxID']] ?? null;
                            if ($taxInfo && str_starts_with($taxInfo['TaxCode'], 'BAG')) {
                                $allBaggagePolicies[] = [
                                    "segment_ref_id" => $segmentPFIDs[0] ?? '',
                                    "title" => $taxInfo['TaxDesc'] ?? 'Baggage',
                                    "description" => $taxInfo['TaxDesc'] ?? '',
                                    "weight_limit" => intval(preg_replace('/[^0-9]/', '', $taxInfo['TaxDesc'] ?? '')) ?: 0,
                                    "weight_unit" => 'KG',
                                    "pieces" => 1,
                                    "type" => 'baggage',
                                    "traveler_type" => 'ADT',
                                ];
                            }
                        }

                        // --- Fare Policies ---
                        foreach (($pax['Penalties']['ChangeFees']['ChangeFee'] ?? []) as $pen) {
                            $penType = $pen['Type'] ?? 'Unknown';
                            $toTime = $pen['ToTime'] ?? 'N/A';
                            $percentage = $pen['Percentage'] ?? 'N/A';
                            $amount = $pen['Amount'] ?? 0;
                            $curr = $pen['Currency'] ?? 'AED';

                            foreach ($segmentPFIDs as $pfid) {
                                $allFarePolicies[] = [
                                    "segment_ref_id" => $pfid,
                                    "title" => "Change Fee ({$penType})",
                                    "description" => "Change before {$toTime}h - {$percentage}% or {$curr} {$amount}",
                                    "type" => "change_fee",
                                    "price" => $amount,
                                    "currency" => $curr,
                                    "traveler_type" => 'ADT'
                                ];
                            }
                        }

                        foreach (($pax['Penalties']['CancellationFees']['RefundPenalty'] ?? []) as $pen) {
                            $penType = $pen['Type'] ?? 'Unknown';
                            $toTime = $pen['ToTime'] ?? 'N/A';
                            $percentage = $pen['Percentage'] ?? 'N/A';
                            $amount = $pen['Amount'] ?? 0;
                            $curr = $pen['Currency'] ?? 'AED';

                            foreach ($segmentPFIDs as $pfid) {
                                $allFarePolicies[] = [
                                    "segment_ref_id" => $pfid,
                                    "title" => "Cancellation Fee ({$penType})",
                                    "description" => "Refund before {$toTime}h - {$percentage}% or {$curr} {$amount}",
                                    "type" => "refund_fee",
                                    "price" => $amount,
                                    "currency" => $curr,
                                    "traveler_type" => 'ADT'
                                ];
                            }
                        }
                        $applicableTaxes = [];
                        foreach($pax['ApplicableTaxDetails']['ApplicableTaxDetail'] ?? [] as $taxItem) {
                            $taxInfo = $taxDetails[$taxItem['TaxID']] ;
                            if ($taxInfo) {
                                $applicableTaxes[] = [
                                    'TaxID' => $taxInfo['TaxID'] ?? '',
                                    'TaxCode' => $taxInfo['TaxCode'] ?? '',
                                    'Amt' => $taxItem['Amt'] ?? 0,
                                ];
                            }
                        }
                        $passengerFares = [
                            [
                                'ID' =>$pax['ID'] ?? '',
                                'FareTypeID' =>$fareTypeId,
                                'FareID' =>$pax['FareID'] ?? '',
                                'FCCode' =>$pax['FCCode'] ?? '',
                                'FBCode' =>$pax['FBCode'] ?? '',
                                'RuleId' =>$pax['RuleId'] ?? '',
                                'PTCID' =>$pax['PTCID'] ?? '',
                                'hashCode' =>$pax['hashcode'] ?? '',
                                'OriginalFare' =>$pax['OriginalFare'] ?? '',
                                'total_passenger' => $pax['PaxCount'], // You can adjust if you have per PaxCount sum
                                "base_price" => $pax['DisplayFareAmt'] ?? 0,
                                "taxes" => $pax['DisplayTaxSum'] ?? 0,
                                'applicableTaxes' => $applicableTaxes,
                                "fees" => 0,
                                'service_charges' => $pax['ServiceCharges'] ?? 0,
                                'surchage'=>$pax['Surchage'] ?? 0,
                                'seatsAvailable' => $pax['SeatsAvailable'] ?? 0,
                                'InfantSeatsAvailable' => $pax['InfantSeatsAvailable'] ?? 0,
                                "total_price" => $pax['FareAmtInclTax'] ?? 0,
                                "currency" => $currencyCode,
                            ]
                        ];
                    }
                }

                // --- Now add final grouped fare ---

                $airline = Airline::where('iata_code', 'FZ')->first();
                $margin_amount = $airline->margin_amount ?? 0;
                $amount_type = $airline->amount_type ?? 'amount';
                $margin_type = $airline->margin_type ?? 'markup';
                // --- Store grouped result ---
                if (!isset($groupedFares[$fareTypeId])) {
                    $groupedFares[$fareTypeId] = [
                        "ref_id" => "FARE_" . $segmentId . "_" . $fareTypeId,
                        "fare_type_id" => $fareTypeId,
                        "name_class" => $fareFamily,
                        "class" => $cabin,
                        "soln_ids" => [$solnId],
                        "available_seats" => $availableSeats,
                        "passenger_fares" => $passengerFares,
                        "fare_policies" => $allFarePolicies,
                        "baggage_policies" => $allBaggagePolicies,
                        "fare_segments" => $allFareSegments,
                        "currency" => $currencyCode,
                        "base_price" => $totalBaseFare,
                        "taxes" => $totalTax,
                        "total_price" => $totalFare,
                        "billable_price" => $totalFare,
                        "margin_type" => $margin_type,
                        "margin_amount" => $margin_amount,
                        "amount_type" => $amount_type,
                    ];
                } else {
                    // Merge SolnId if new
                    if (!in_array($solnId, $groupedFares[$fareTypeId]['soln_ids'])) {
                        $groupedFares[$fareTypeId]['soln_ids'][] = $solnId;
                    }
                }
            }

            // Finally convert grouped fares to array
            $fares = array_values($groupedFares);

            // --- Build the Flight Object ---
            $airline = Airline::where('iata_code', $segmentDetail['CarrierCode'] ?? 'FZ')->first();
            $marketingCarrier = [
                "name" => $airline->name ?? 'Flydubai',
                "iata" => $airline->iata_code ?? 'FZ',
                "logo" => $airline->logo_url,
            ];

            $fromAirportDetails = Airport::where('iata_code', $segmentDetail['Origin'] ?? null)->first();
            $toAirportDetails = Airport::where('iata_code', $segmentDetail['Destination'] ?? null)->first();

            $from = $this->buildAirportData($fromAirportDetails);
            $to = $this->buildAirportData($toAirportDetails);
            $legFlights = [
                [
                    "ref_id" => $segmentId,
                    "flight_operation" => ($segmentDetail['Stops'] ?? 0) > 0 ? 'In-Direct' : 'Direct',
                    "is_recommended" => true,
                    "is_refundable" => $rootSegment['FareTypes']['FareType'][0]['Refundable'] == 1 ?? false,
                    "recommended_priority" => 1,
                    "has_layovers" => ($segmentDetail['Stops'] ?? 0) > 0,
                    "layovers_count" => $segmentDetail['Stops'] ?? 0,
                    "travel_time" => $segmentDetail['FlightTime'] ?? 0,
                    "marketing_carrier" => $marketingCarrier,
                    "departure_at" => $segmentDetail['DepartureDate'] ?? '',
                    "arrival_at" => $segmentDetail['ArrivalDate'] ?? '',
                    "from" => $from,
                    "to" => $to,
                    "segments" => $segments,
                    "ancillaries" => [],
                    "fares" => $fares,
                ]
            ];

            $mappedSegment = [
                "ref_id" => "FZ_" . $segmentId,
                "provider" => ["name" => "flydubai", "code" => "FZ", "source" => "FlyDubai"],
                "leg" => [
                    "ref_id" => "LEG_" . $segmentId,
                    "trip_nature" => $legCount == 1 ? "Outbound" : "Inbound",
                    "flights" => $legFlights,
                ],
            ];

            // Separate outbound and inbound segments
            if ($legCount == 1) {
                $outboundSegments[] = $mappedSegment;
            } elseif ($legCount == 2) {
                $inboundSegments[] = $mappedSegment;
            } else {
                // For other leg counts, add directly to result
                $result[] = $mappedSegment;
            }
        }

        // --- COMBINE SEGMENTS USING COMBINABILITY PAIRS ---
        if ($flightType === 'return') {
            // Log::info("Processing return flight with combinability pairs");
            // Log::info("Outbound segments count: " . count($outboundSegments));
            // Log::info("Inbound segments count: " . count($inboundSegments));
            // Log::info("Combinability pairs count: " . count($combinabilityPairs));

            foreach ($outboundSegments as $outbound) {
                $compatibleResults = $this->findCompatibleInboundByPairs($outbound, $inboundSegments, $combinabilityPairs);

                if (!empty($compatibleResults['matchedInbound'])) {
                    // Create combined return itinerary with only matching fares
                    $combined = $this->createCombinedItineraryWithMatchingFares(
                        $outbound,
                        $compatibleResults['matchedInbound'],
                        $compatibleResults['matchingPairs']
                    );
                    $result[] = $combined;

                    // Remove the used inbound segment
                    $inboundSegments = array_filter($inboundSegments, function ($inbound) use ($compatibleResults) {
                        return $inbound['ref_id'] !== $compatibleResults['matchedInbound']['ref_id'];
                    });
                } else {
                    // No compatible inbound found, add as one-way
                    $result[] = $outbound;
                }
            }

            // Add any remaining inbound segments as one-way
            foreach ($inboundSegments as $inbound) {
                $result[] = $inbound;
            }
        } else {
            // For one-way flights, just combine all segments
            $result = array_merge($result, $outboundSegments, $inboundSegments);
        }

        return $result;
    }

    // UPDATED: Find compatible inbound and return matching fare pairs
    public function findCompatibleInboundByPairs($outbound, $inboundSegments, $combinabilityPairs)
    {
        // Get all solnIds from outbound fares
        $outboundSolnIds = [];
        foreach ($outbound['leg']['flights'][0]['fares'] ?? [] as $fare) {
            $outboundSolnIds = array_merge($outboundSolnIds, $fare['soln_ids'] ?? []);
        }

        // Log::info("Outbound Segment: " . $outbound['ref_id']);
        // Log::info("Outbound Soln IDs: " . json_encode($outboundSolnIds));

        $allMatchingPairs = [];
        $matchedInbound = null;

        foreach ($inboundSegments as $inbound) {
            // Get all solnIds from inbound fares
            $inboundSolnIds = [];
            foreach ($inbound['leg']['flights'][0]['fares'] ?? [] as $fare) {
                $inboundSolnIds = array_merge($inboundSolnIds, $fare['soln_ids'] ?? []);
            }

            // Log::info("Checking Inbound Segment: " . $inbound['ref_id']);
            // Log::info("Inbound Soln IDs: " . json_encode($inboundSolnIds));

            $segmentMatchingPairs = [];

            // Check if there's any combinability pair that matches
            foreach ($combinabilityPairs as $pair) {
                $outboundPairId = $pair['outbound'];
                $inboundPairId = $pair['inbound'];

                // Check if this outbound segment has the outbound solnId AND inbound segment has the inbound solnId
                $hasOutboundMatch = in_array($outboundPairId, $outboundSolnIds);
                $hasInboundMatch = in_array($inboundPairId, $inboundSolnIds);

                if ($hasOutboundMatch && $hasInboundMatch) {
                    $segmentMatchingPairs[] = [
                        'outbound_soln_id' => $outboundPairId,
                        'inbound_soln_id' => $inboundPairId,
                        'pair' => $pair
                    ];
                    //  Log::info("✅ FOUND COMPATIBLE PAIR: Outbound " . $outbound['ref_id'] .
                    //     " (Soln: {$outboundPairId}) with Inbound " . $inbound['ref_id'] .
                    //     " (Soln: {$inboundPairId})");
                }
            }

            // Keep the inbound segment with the most matching pairs
            if (count($segmentMatchingPairs) > count($allMatchingPairs)) {
                $allMatchingPairs = $segmentMatchingPairs;
                $matchedInbound = $inbound;
            }
        }

        if ($matchedInbound) {
            // Log::info("🎯 Selected Inbound: " . $matchedInbound['ref_id'] . " with " . count($allMatchingPairs) . " matching pairs");
            return [
                'matchedInbound' => $matchedInbound,
                'matchingPairs' => $allMatchingPairs
            ];
        }

        // Log::info("❌ No compatible inbound found for outbound: " . $outbound['ref_id']);
        return [];
    }

    // UPDATED: Create combined itinerary with only matching fares
    public function createCombinedItineraryWithMatchingFares($outbound, $inbound, $matchingPairs)
    {
        // Extract unique matching solnIds to avoid duplicates
        $matchingOutboundSolnIds = array_unique(array_column($matchingPairs, 'outbound_soln_id'));
        $matchingInboundSolnIds = array_unique(array_column($matchingPairs, 'inbound_soln_id'));

        // Log::info("Matching Outbound Soln IDs: " . json_encode($matchingOutboundSolnIds));
        // Log::info("Matching Inbound Soln IDs: " . json_encode($matchingInboundSolnIds));

        // Filter outbound fares to include only matching ones
        $filteredOutboundFares = [];
        $seenFareTypes = [];

        foreach ($outbound['leg']['flights'][0]['fares'] ?? [] as $fare) {
            $fareSolnIds = $fare['soln_ids'] ?? [];
            $hasMatchingSolnId = !empty(array_intersect($fareSolnIds, $matchingOutboundSolnIds));

            // Also check if we've already included this fare type to avoid duplicates
            $fareTypeId = $fare['fare_type_id'] ?? $fare['name_class'];

            if ($hasMatchingSolnId && !in_array($fareTypeId, $seenFareTypes)) {
                $filteredOutboundFares[] = $fare;
                $seenFareTypes[] = $fareTypeId;
            }
        }

        // Filter inbound fares to include only matching ones
        $filteredInboundFares = [];
        $seenFareTypes = [];

        foreach ($inbound['leg']['flights'][0]['fares'] ?? [] as $fare) {
            $fareSolnIds = $fare['soln_ids'] ?? [];
            $hasMatchingSolnId = !empty(array_intersect($fareSolnIds, $matchingInboundSolnIds));

            // Also check if we've already included this fare type to avoid duplicates
            $fareTypeId = $fare['fare_type_id'] ?? $fare['name_class'];

            if ($hasMatchingSolnId && !in_array($fareTypeId, $seenFareTypes)) {
                $filteredInboundFares[] = $fare;
                $seenFareTypes[] = $fareTypeId;
            }
        }

        // Log::info("Filtered Outbound Fares: " . count($filteredOutboundFares));
        // Log::info("Filtered Inbound Fares: " . count($filteredInboundFares));

        // Create copies of flights with filtered fares
        $outboundFlight = $outbound['leg']['flights'][0];
        $inboundFlight = $inbound['leg']['flights'][0];

        $outboundFlight['fares'] = $filteredOutboundFares;
        $inboundFlight['fares'] = $filteredInboundFares;

        // Calculate total prices from filtered fares
        $totalBasePrice = 0;
        $totalTaxes = 0;
        $totalPrice = 0;

        foreach ($filteredOutboundFares as $fare) {
            $totalBasePrice += $fare['base_price'] ?? 0;
            $totalTaxes += $fare['taxes'] ?? 0;
            $totalPrice += $fare['total_price'] ?? 0;
        }

        foreach ($filteredInboundFares as $fare) {
            $totalBasePrice += $fare['base_price'] ?? 0;
            $totalTaxes += $fare['taxes'] ?? 0;
            $totalPrice += $fare['total_price'] ?? 0;
        }

        return [
            "ref_id" => "FZ_COMBINED_" . uniqid(),
            "provider" => ["name" => "flydubai", "code" => "FZ", "source" => "FlyDubai"],
            "leg" => [
                "ref_id" => "LEG_COMBINED_" . uniqid(),
                "trip_nature" => "Return",
                "flights" => [$outboundFlight, $inboundFlight],
                "total_base_price" => $totalBasePrice,
                "total_taxes" => $totalTaxes,
                "total_price" => $totalPrice,
                "currency" => $outboundFlight['fares'][0]['currency'] ?? 'AED',
                "matching_pairs_count" => count($matchingPairs),
            ],
        ];
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






}

















