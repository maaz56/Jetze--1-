<?php
namespace App\Transformers;

use App\Models\Airline;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class AirsialFlightTransformer
{


    public static function fromAirSial($flightData,$airlineParams)
    {

        Log::info("AirSial Flight Data: " . json_encode($airlineParams));
        $result = [];
        $provider = [
            "name" => "airsial",
            "source" => "AirSial",
            "identifier" => "PF",
        ];

        $data = $flightData['Response']['Data'] ?? [];
        $outbounds = $data['outbound'] ?? [];
        $inbounds = $data['inbound'] ?? [];

        $flightCount = max(count($outbounds), count($inbounds));

        for ($i = 0; $i < $flightCount; $i++) {
            $outbound = $outbounds[$i] ?? null;
            $inbound = $inbounds[$i] ?? null;

            $flights = [];

            // process outbound (always exists)
            if ($outbound) {
                $flights[] = self::buildAirSialFlight($outbound, 'outbound',$airlineParams);
            }

            // process inbound (if available)
            if ($inbound) {
                $flights[] = self::buildAirSialFlight($inbound, 'inbound',$airlineParams);
            }

            $result[] = [
                "ref_id" => uniqid(),
                "provider" => $provider,
                "leg" => [
                    "ref_id" => uniqid(),
                    "trip_nature" => count($inbounds) ? "international" : "domestic",
                    "fares_on_request" => false,
                    "flights" => $flights,
                ],
            ];
        }

        return $result;
    }

private static function buildAirSialFlight($flight, $direction, $params)
{
    $departureAt = self::formatToIso($flight['DEPARTURE_DATE'].' '.$flight['DEPARTURE_TIME']);
    $arrivalAt   = self::formatToIso($flight['DEPARTURE_DATE'].' '.$flight['ARRIVAL_TIME']);
    $segmentRefId = uniqid();

    // Extract pax counts from params (convert strings to ints)
    $adultCount  = (int)($params['adults'] ?? 1);
    $childCount  = (int)($params['children'] ?? 0);
    $infantCount = (int)($params['infants'] ?? 0);

    $segments = [
        [
            "ref_id" => $segmentRefId,
            "operating_carrier" => [
                "name" => "AirSial",
                "iata" => "PF",
                "logo" => "https://api.sooperfare.com/assets/client/images/airlines/PF.png",
            ],
            "departure_at" => $departureAt,
            "arrival_at" => $arrivalAt,
            "from" => [
                "name" => $flight['ORGN'],
                "iata" => $flight['ORGN'],
                "city" => ["name" => $flight['ORGN'], "iata" => $flight['ORGN'], "code" => $flight['ORGN']],
            ],
            "to" => [
                "name" => $flight['DEST'],
                "iata" => $flight['DEST'],
                "city" => ["name" => $flight['DEST'], "iata" => $flight['DEST'], "code" => $flight['DEST']],
            ],
            "from_terminal" => ["gate" => null],
            "to_terminal" => ["gate" => null],
            "cabin_class" => "Y",
            "flight_time" => self::parseDurationMinutes($flight['DURATION']),
            "layover_time" => 0,
            "journey_code" => $flight['JOURNEY_CODE'],
            "class_code" => $flight['CLASS_CODE'],
            "flight_number" => $flight['FLIGHT_NO'],
            "distance" => null,
            "aircraft" => ["manufacturer" => "", "model" => ""],
        ]
    ];

    $fares = [];
    $airline = Airline::where('iata_code', 'PF')->first();
    $margin_amount = $airline->margin_amount ?? 0;
    $amount_type   = $airline->amount_type ?? 'amount';
    $margin_type   = $airline->margin_type ?? 'markup';

    foreach ($flight['BAGGAGE_FARE'] as $fare) {
        $paxWise = $fare['FARE_PAX_WISE'];
        $passengerFares = [];
        $baseTotal = $surchargeTotal = $taxTotal = $feeTotal = $totalPrice = 0;

        // Map counts by type (for ADULT, CHILD, INFANT)
        $paxCountMap = [
            'ADULT' => $adultCount,
            'CHILD' => $childCount,
            'INFANT' => $infantCount,
        ];

        // Loop through all passenger types in the fare
        foreach ($paxWise as $type => $data) {
            $count = $paxCountMap[strtoupper($type)] ?? 0;

            // Skip if no passengers of this type exist
            if ($count <= 0) continue;

            // Get per-passenger prices
            $base = (float) $data['BASIC_FARE'];
            $surcharge = (float) $data['SURCHARGE'];
            $fees = (float) $data['FEES'];
            $tax = (float) $data['TAX'];
            $total = (float) $data['TOTAL'];

            // Add to per-type fare info
            $passengerFares[] = [
                "traveler_type" => strtolower($type),
                "total_passenger" => $count,
                "base_price" => $base * $count, 
                "surchage" => $surcharge * $count,
                "taxes" => $tax * $count,
                "fees" => $fees * $count,
                "service_charges" => 0,
                "ancillaries_charges" => 0,
                "total_price" => $total * $count,
            ];

            // Multiply per passenger totals by number of that pax
            $baseTotal += ($base * $count) ;
            $surchargeTotal += $surcharge * $count;
            $taxTotal += $tax * $count;
            $feeTotal += $fees * $count;
            $totalPrice += ($total * $count);
        }

        // 🧳 Baggage Mapping
        $pieces = (int) $fare['piece'];
        $weight = (int) $fare['weight'];
        $subDesc = trim($fare['sub_class_desc']);

        if ($pieces > 0 && $weight > 0) {
            $baggageDesc = "{$pieces} Piece(s), up to {$weight} KG";
        } elseif ($pieces > 0) {
            $baggageDesc = "{$pieces} Piece(s)";
        } elseif ($weight > 0) {
            $baggageDesc = "Up to {$weight} KG";
        } else {
            $baggageDesc = "Checked baggage not included";
        }

        $fares[] = [
            "ref_id" => uniqid(),
            "sub_class_id" => $fare['sub_class_id'],
            "is_refundable" => true,
            "name" => $subDesc,
            "class" => "Y",
            "name_class" => $subDesc,
            "available_seats" => 9,
            "passenger_fares" => $passengerFares,
            "fare_policies" => [],
            "baggage_policies" => [
                [
                    "segment_ref_id" => $segmentRefId,
                    "traveler_type" => 'adult',
                    "type" => 'checked',
                    "pieces" => $pieces,
                    "weight" => $weight,
                    "description" => $baggageDesc,
                ]
            ],
            "currency" => [
                "name" => "Currency",
                "code" => $flight['CURRENCY'],
                "symbol" => $flight['CURRENCY'],
                "decimal" => 0,
                "flag" => "https://www.sooperfare.com/assets/client/images/flags/currency/PKR.png",
            ],
            "base_price" => $baseTotal,
            "surchage" => $surchargeTotal,
            "taxes" => $taxTotal,
            "fees" => $feeTotal,
            "total_price" => $baseTotal + $surchargeTotal + $taxTotal + $feeTotal,
            "billable_price" => $totalPrice,
            "margin_type" => $margin_type,
            "margin_amount" => $margin_amount,
            "amount_type" => $amount_type,
        ];
    }

    return [
        "ref_id" => uniqid(),
        "flight_operation" => "self",
        "is_recommended" => 1,
        "is_refundable" => true,
        "recommended_priority" => 1,
        "has_layovers" => false,
        "layovers_count" => 0,
        "layovers_time" => 0,
        "change_of_plane" => false,
        "travel_time" => self::parseDurationMinutes($flight['DURATION']),
        "distance" => null,
        "marketing_carrier" => [
            "name" => "AirSial",
            "iata" => "PF",
            "logo" => "https://api.sooperfare.com/assets/client/images/airlines/PF.png",
        ],
        "departure_at" => $departureAt,
        "arrival_at" => $arrivalAt,
        "from" => [
            "name" => $flight['ORGN'],
            "iata" => $flight['ORGN'],
            "city" => ["name" => $flight['ORGN'], "iata" => $flight['ORGN'], "code" => $flight['ORGN']],
        ],
        "to" => [
            "name" => $flight['DEST'],
            "iata" => $flight['DEST'],
            "city" => ["name" => $flight['DEST'], "iata" => $flight['DEST'], "code" => $flight['DEST']],
        ],
        "segments" => $segments,
        "ancillaries" => ["baggages" => [], "meals" => [], "seatplans" => [], "ssrs" => []],
        "fares" => $fares,
    ];
}



    // Helper: convert AirSial datetime (e.g. "28-12-2025 11:30") to ISO 8601
    private static function formatToIso($datetime)
    {
        $dt = DateTime::createFromFormat('d-m-Y H:i', $datetime, new DateTimeZone('UTC'));
        return $dt ? $dt->format('Y-m-d\TH:i:sP') : null;
    }

    // Helper: convert "2h 55m" to minutes
    private static function parseDurationMinutes($duration)
    {
        if (preg_match('/(?:(\d+)h)?\s*(?:(\d+)m)?/', $duration, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            return $hours * 60 + $minutes;
        }
        return 0;
    }






}

















