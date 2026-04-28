<?php
namespace App\Transformers;

use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;


class TravelPortFlightTransformer_copy
{


    public function fromTravelPort($flightData, $airlineParams = [])
    {
        $result = [];

        // Extract the root response
        $response = $flightData['CatalogProductOfferingsResponse'] ?? $flightData;
        if (!isset($response['CatalogProductOfferings'])) {
            return $result; // Empty if no offerings
        }

        // Loop through each CatalogProductOffering (each represents a full journey/leg combination)
        foreach ($response['CatalogProductOfferings'] as $offering) {
            $catalogOffering = $offering['CatalogProductOffering'] ?? [];

            // We assume each CatalogProductOffering contains at least one ProductBrandOffering
            foreach ($catalogOffering['ProductBrandOffering'] ?? [] as $brandOffering) {
                // Get the product references (these link to actual flights)
                $productRefs = $brandOffering['ProductRef'] ?? [];
                if (empty($productRefs)) {
                    continue;
                }

                // Find the actual Product objects from ReferenceList
                $products = $this->getProductsByRefs($response, $productRefs);

                // Group segments by leg (Travelport often returns one leg per offering in simple searches)
                $leg = [
                    'ref_id' => $this->generateRefId(), // You can use UUID or custom logic
                    'trip_nature' => 'one_way', // Default; adjust if you have round-trip data
                    'fares_on_request' => false,
                    'flights' => []
                ];

                foreach ($products as $product) {
                    // Extract flight details
                    $flightRef = $product['FlightRef'] ?? null;
                    if (!$flightRef) {
                        continue;
                    }

                    $flight = $this->findFlightByRef($response, $flightRef);
                    if (!$flight) {
                        continue;
                    }

                    // Build segment data
                    $segment = [
                        'ref_id' => $this->generateRefId(),
                        'segID' => 0, // Single segment flight for now
                        'operating_carrier' => [
                            'name' => $flight['Carrier'] ?? '',
                            'iata' => $flight['Carrier'] ?? '',
                            'logo' => $airlineParams['logos'][$flight['Carrier']] ?? 'https://www.sooperfare.com/assets/client/images/airlines/' . ($flight['Carrier'] ?? 'default') . '.png'
                        ],
                        'departure_at' => $this->convertToISO8601($flight['Departure']['ScheduledTimeLocal'] ?? ''),
                        'arrival_at' => $this->convertToISO8601($flight['Arrival']['ScheduledTimeLocal'] ?? ''),
                        'from' => $this->buildAirportData($flight['Departure']),
                        'to' => $this->buildAirportData($flight['Arrival']),
                        'from_terminal' => ['gate' => $flight['Departure']['Terminal'] ?? null],
                        'to_terminal' => ['gate' => $flight['Arrival']['Terminal'] ?? null],
                        'cabin_class' => $product['Cabin'] ?? 'economy',
                        'flight_time' => $this->convertDurationToMinutes($flight['Duration'] ?? 'PT0H0M'),
                        'layover_time' => 0,
                        'flight_number' => $flight['FlightNumber'] ?? '',
                        'distance' => $flight['Distance'] ?? 0,
                        'aircraft' => [
                            'manufacturer' => substr($flight['Equipment'] ?? '7M', 0, 2), // e.g., 7M for Boeing
                            'model' => $flight['Equipment'] ?? '7M8'
                        ]
                    ];

                    // Build full flight object
                    $fullFlight = [
                        'ref_id' => $this->generateRefId(),
                        'ondID' => 0,
                        'flight_operation' => 'self',
                        'is_recommended' => 1,
                        'is_refundable' => $brandOffering['Refundable'] ?? true,
                        'recommended_priority' => 3,
                        'has_layovers' => false,
                        'layovers_count' => 0,
                        'layovers_time' => 0,
                        'change_of_plane' => false,
                        'travel_time' => $this->convertDurationToMinutes($flight['Duration'] ?? 'PT0H0M'),
                        'distance' => $flight['Distance'] ?? 0,
                        'marketing_carrier' => [
                            'name' => $flight['Carrier'] ?? '',
                            'iata' => $flight['Carrier'] ?? '',
                            'logo' => $airlineParams['logos'][$flight['Carrier']] ?? 'https://www.sooperfare.com/assets/client/images/airlines/' . ($flight['Carrier'] ?? 'default') . '.png'
                        ],
                        'departure_at' => $segment['departure_at'],
                        'arrival_at' => $segment['arrival_at'],
                        'from' => $segment['from'],
                        'to' => $segment['to'],
                        'segments' => [$segment],
                        'ancillaries' => [
                            'baggages' => [],
                            'meals' => [],
                            'seatplans' => [],
                            'ssrs' => []
                        ],
                        'fares' => $this->buildFares($brandOffering, $response)
                    ];

                    $leg['flights'][] = $fullFlight;
                }

                $result[] = $leg;
            }
        }
        Log::info("Transformed TravelPort Flights: " . json_encode($result));
        return $result;
    }

    // Helper: Find product objects by reference IDs
    private function getProductsByRefs($response, $refs)
    {
        $products = [];
        $refList = $response['ReferenceList']['ReferenceListProduct'] ?? [];
        foreach ($refs as $ref) {
            foreach ($refList as $product) {
                if ($product['id'] === $ref) {
                    $products[] = $product['Product'] ?? $product;
                }
            }
        }
        return $products;
    }

    // Helper: Find flight by reference ID
    private function findFlightByRef($response, $ref)
    {
        $flights = $response['ReferenceList']['ReferenceListFlight'] ?? [];
        foreach ($flights as $f) {
            if ($f['id'] === $ref) {
                return $f['Flight'] ?? $f;
            }
        }
        return null;
    }

    // Helper: Build airport/city/country structure
    private function buildAirportData($airportData)
    {
        $airportCode = $airportData['AirportCode'] ?? '';
        return [
            'name' => $airportData['AirportName'] ?? $airportCode . ' Airport',
            'iata' => $airportCode,
            'city' => [
                'name' => $airportData['CityName'] ?? '',
                'code' => $airportCode,
                'country' => [
                    'name' => $airportData['CountryName'] ?? '',
                    'alpha_2' => $airportData['CountryCode'] ?? '',
                    'flag' => $this->getCountryFlag($airportData['CountryCode'] ?? '')
                ]
            ],
            'country' => [
                'name' => $airportData['CountryName'] ?? '',
                'alpha_2' => $airportData['CountryCode'] ?? '',
                'flag' => $this->getCountryFlag($airportData['CountryCode'] ?? '')
            ]
        ];
    }

    // Helper: Build fares array
    private function buildFares($brandOffering, $response)
    {
        $fares = [];
        // Example: Extract pricing from PriceBreakdownAir or similar
        $price = $brandOffering['PriceBreakdownAir'] ?? [];
        $fare = [
            'ref_id' => $this->generateRefId(),
            'is_refundable' => true,
            'name' => $brandOffering['BrandRef'] ?? 'Standard',
            'class' => 'Y',
            'name_class' => $brandOffering['BrandRef'] ?? 'Y',
            'available_seats' => '9',
            'passenger_fares' => [
                [
                    'traveler_type' => 'adt',
                    'fareBasis' => $brandOffering['BrandRef'] ?? 'Y',
                    'total_passenger' => 1,
                    'base_price' => $price['BaseFare']['Amount'] ?? 0,
                    'surchage' => 0,
                    'taxes' => $price['Taxes']['Amount'] ?? 0,
                    'fees' => 0,
                    'service_charges' => 0,
                    'ancillaries_charges' => 0,
                    'total_price' => $price['Total']['Amount'] ?? 0
                ]
            ],
            'fare_policies' => [],
            'baggage_policies' => $this->buildBaggagePolicies($brandOffering),
            'currency' => [
                'name' => $price['Total']['Currency'] ?? 'PKR',
                'code' => $price['Total']['Currency'] ?? 'PKR',
                'symbol' => $price['Total']['Currency'] ?? 'PKR',
                'decimal' => 0,
                'flag' => 'https://www.sooperfare.com/assets/client/images/flags/currency/PKR.png'
            ],
            // Add actual_ and billable_ fields if needed
            'actual_base_price' => 0,
            'base_price' => $price['BaseFare']['Amount'] ?? 0,
            'actual_taxes' => 0,
            'taxes' => $price['Taxes']['Amount'] ?? 0,
            'actual_total_price' => $price['Total']['Amount'] ?? 0,
            'total_price' => $price['Total']['Amount'] ?? 0,
            'total_discount' => 0,
            'actual_billable_price' => $price['Total']['Amount'] ?? 0,
            'billable_price' => $price['Total']['Amount'] ?? 0,
            'margin_amount' => '0.00',
            'amount_type' => 'flat',
            'margin_type' => 'markup'
        ];

        $fares[] = $fare;
        return $fares;
    }

    // Helper: Extract baggage policies
    private function buildBaggagePolicies($brandOffering)
    {
        $policies = [];
        // Parse from Brand or TermsAndConditions if available
        // This is simplified - extend based on your actual data
        $policies[] = [
            'segment_ref_id' => '', // Link to segment
            'title' => 'Checked Baggage',
            'description' => '1 checked bag(s), up to 30 KG',
            'weight_limit' => 30,
            'weight_unit' => 'kg',
            'pieces' => 1,
            'type' => 'checked',
            'traveler_type' => 'adult'
        ];
        return $policies;
    }

    // Helpers: Utility functions
    private function generateRefId()
    {
        return bin2hex(random_bytes(8)); // Simple random ID; use UUID library if preferred
    }

    private function convertToISO8601($dateTime)
    {
        if (empty($dateTime)) return '';
        // Travelport often uses local time without offset; adjust if needed
        return date('c', strtotime($dateTime));
    }

    private function convertDurationToMinutes($duration)
    {
        if (empty($duration)) return 0;
        preg_match('/PT(\d+)H(\d+)M/', $duration, $matches);
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        return $hours * 60 + $minutes;
    }

    private function getCountryFlag($countryCode)
    {
        return 'https://www.sooperfare.com/assets/client/images/flags/png/' . strtolower($countryCode) . '.png';
    }






}

















