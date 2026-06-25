<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Promotion;
use Carbon\Carbon;
use Log;

class PromotionService
{
    public function applyPromotions(array $transformedFlights, array $context = []): array
    {

        if (empty($transformedFlights)) {
            return $transformedFlights;
        }

        $promotions = Promotion::query()->get();
        if ($promotions->isEmpty()) {
            return $transformedFlights;
        }

        $airlineIdToIata = Airline::query()
            ->pluck('iata_code', 'id')
            ->map(fn ($iata) => strtoupper((string) $iata))
            ->toArray();

        foreach ($transformedFlights as &$itinerary) {
            $provider = data_get($itinerary, 'provider', []);
            $channels = $this->buildProviderChannels($provider);
            $flights = data_get($itinerary, 'leg.flights', []);

            if (!is_array($flights)) {
                continue;
            }

            foreach ($flights as &$flight) {
                $segments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];
                $passengerCount = $this->getPassengerCount($flight);
                $flightContext = $this->buildFlightContext($flight, $airlineIdToIata, $channels);

                $bestPromotion = $this->resolveBestPromotion($promotions, $flightContext);

                if (!$bestPromotion) {
                    continue;
                }

                $fares = $flight['fares'] ?? [];
                if (!is_array($fares)) {
                    continue;
                }

                foreach ($fares as &$fare) {
                    $applied = $this->applyPromotionToFare($fare, $bestPromotion, $passengerCount);

                    $fare['promotion'] = [
                        'id' => $bestPromotion->id,
                        'title' => $bestPromotion->title,
                        'price_option' => $bestPromotion->price_option ?? 'markup',
                        'commission_type' => $bestPromotion->commission_type,
                        'commission_value' => (float) $bestPromotion->commission_value,
                        'applied_amount' => round($applied, 2),
                        'passenger_count' => $passengerCount,
                        'multiplier_applied' => $passengerCount,
                    ];
                }
                unset($fare);

                $flight['fares'] = $fares;
            }
            unset($flight);

            data_set($itinerary, 'leg.flights', $flights);
        }
        unset($itinerary);

        return $transformedFlights;
    }

    private function buildProviderChannels(array $provider): array
    {
        $identifier = strtoupper((string) data_get($provider, 'identifier', ''));
        $name = strtoupper((string) data_get($provider, 'name', ''));
        $contentSource = strtoupper((string) data_get($provider, 'contentSource', ''));
        $contentSource = $contentSource === 'NDC' ? 'NDC' : $contentSource;

        $channels = [];

        if ($identifier !== '') {
            $channels[] = $identifier;
            $identifierBase = preg_replace('/-(GDS|NDC)$/', '', $identifier);
            if (!empty($identifierBase)) {
                $channels[] = $identifierBase;
            }
        }

        if ($name !== '') {
            $channels[] = $name;
            if ($contentSource !== '') {
                $channels[] = $name . '-' . $contentSource;
            }
        }

        if ($contentSource !== '') {
            $channels[] = $contentSource;
        }

        return array_values(array_unique(array_filter($channels)));
    }

    private function getPassengerCount(array $flight): int
    {
        $fares = $flight['fares'] ?? [];
        if (empty($fares) || !is_array($fares)) {
            return 1;
        }

        $passengerFares = $fares[0]['passenger_fares'] ?? [];
        $count = is_array($passengerFares) ? count($passengerFares) : 0;

        return max($count, 1);
    }

    private function buildFlightContext(array $flight, array $airlineIdToIata, array $channels): array
    {
        $segments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];
        $marketingIata = strtoupper((string) data_get($flight, 'marketing_carrier.iata', ''));
        $segmentAirlines = [];

        foreach ($segments as $segment) {
            $operatingIata = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));
            if ($operatingIata !== '') {
                $segmentAirlines[] = $operatingIata;
            }
        }

        if ($marketingIata !== '') {
            $segmentAirlines[] = $marketingIata;
        }

        $allAirlines = array_values(array_unique(array_filter($segmentAirlines)));
        $airlineIds = [];
        foreach ($airlineIdToIata as $id => $iata) {
            if (in_array($iata, $allAirlines, true)) {
                $airlineIds[] = (int) $id;
            }
        }

        $overallDepartureAt = data_get($segments, '0.departure_at') ?? data_get($flight, 'departure_at');
        $lastSegment = !empty($segments) ? end($segments) : null;
        $overallArrivalAt = data_get($lastSegment, 'arrival_at') ?? data_get($flight, 'arrival_at');

        $originCountry = strtoupper((string) data_get($flight, 'from.country.code', data_get($flight, 'from.city.country.code', '')));
        $destinationCountry = strtoupper((string) data_get($flight, 'to.country.code', data_get($flight, 'to.city.country.code', '')));
        $isInterline = count($allAirlines) > 1;
        $isCodeShare = false;

        foreach ($segments as $segment) {
            $operatingIata = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));
            if ($marketingIata !== '' && $operatingIata !== '' && $operatingIata !== $marketingIata) {
                $isCodeShare = true;
                break;
            }
        }

        return [
            'channels' => array_map('strtoupper', $channels),
            'airline_ids' => $airlineIds,
            'travel_departure_date' => $overallDepartureAt ? Carbon::parse($overallDepartureAt)->toDateString() : null,
            'travel_arrival_date' => $overallArrivalAt ? Carbon::parse($overallArrivalAt)->toDateString() : null,
            'ticketing_date' => Carbon::today()->toDateString(),
            'reservation_type' => $this->detectReservationType($originCountry, $destinationCountry, $isInterline, $isCodeShare),
        ];
    }

    private function resolveBestPromotion($promotions, array $flightContext): ?Promotion
    {
        $best = null;
        $bestScore = -1;

        foreach ($promotions as $promotion) {
            if (!$this->promotionMatches($promotion, $flightContext)) {
                continue;
            }

            $score = $this->specificityScore($promotion);
            if ($score > $bestScore || ($score === $bestScore && (int) $promotion->id > (int) data_get($best, 'id', 0))) {
                $best = $promotion;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function promotionMatches(Promotion $promotion, array $flightContext): bool
    {
        $promotionChannel = strtoupper((string) ($promotion->sale_channel ?? ''));
        if ($promotionChannel !== '' && !in_array($promotionChannel, $flightContext['channels'] ?? [], true)) {
            return false;
        }

        $includedAirlineIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                is_array($promotion->airline_ids ?? null) ? $promotion->airline_ids : [],
                !is_null($promotion->airline_id ?? null) ? [(int) $promotion->airline_id] : []
            )
        ))));

        if (!empty($includedAirlineIds) && empty(array_intersect($includedAirlineIds, $flightContext['airline_ids'] ?? []))) {
            return false;
        }

        $disabledAirlineIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($promotion->disabled_airline_ids ?? null) ? $promotion->disabled_airline_ids : []
        ))));

        if (!empty($disabledAirlineIds) && !empty(array_intersect($disabledAirlineIds, $flightContext['airline_ids'] ?? []))) {
            return false;
        }

        $reservationType = strtoupper((string) ($promotion->reservation_type ?? 'ALL-SECTORS'));
        if ($reservationType !== 'ALL-SECTORS' && $reservationType !== strtoupper((string) ($flightContext['reservation_type'] ?? ''))) {
            return false;
        }

        $travelDepartureDate = $flightContext['travel_departure_date'] ?? null;
        $travelArrivalDate = $flightContext['travel_arrival_date'] ?? null;

        if (!$this->isDateWithinRange($travelDepartureDate, $promotion->travel_start_date ?? null, $promotion->travel_end_date ?? null)) {
            return false;
        }

        if (!$this->isDateWithinRange($travelArrivalDate, $promotion->travel_start_date ?? null, $promotion->travel_end_date ?? null)) {
            return false;
        }

        if (!$this->isDateWithinRange(
            $flightContext['ticketing_date'] ?? null,
            $promotion->ticketing_start_date ?? null,
            $promotion->ticketing_end_date ?? null
        )) {
            return false;
        }

        return true;
    }

    private function isDateWithinRange(?string $date, $startDate, $endDate): bool
    {
        if (empty($startDate) && empty($endDate)) {
            return true;
        }

        if (empty($date)) {
            return false;
        }

        $target = Carbon::parse($date)->startOfDay();
        $start = !empty($startDate) ? Carbon::parse($startDate)->startOfDay() : null;
        $end = !empty($endDate) ? Carbon::parse($endDate)->startOfDay() : null;

        if ($start && $target->lt($start)) {
            return false;
        }

        if ($end && $target->gt($end)) {
            return false;
        }

        return true;
    }

    private function specificityScore(Promotion $promotion): int
    {
        $score = 0;

        $hasIncludedAirlines = !empty($promotion->airline_ids) || !is_null($promotion->airline_id);
        $hasDisabledAirlines = !empty($promotion->disabled_airline_ids);
        $hasTravelWindow = !empty($promotion->travel_start_date) || !empty($promotion->travel_end_date);
        $hasTicketingWindow = !empty($promotion->ticketing_start_date) || !empty($promotion->ticketing_end_date);
        $hasReservationType = strtoupper((string) ($promotion->reservation_type ?? 'ALL-SECTORS')) !== 'ALL-SECTORS';
        $hasSaleChannel = !empty($promotion->sale_channel);

        if ($hasIncludedAirlines) {
            $score += 10;
        }
        if ($hasDisabledAirlines) {
            $score += 4;
        }
        if ($hasTravelWindow) {
            $score += 8;
        }
        if ($hasTicketingWindow) {
            $score += 7;
        }
        if ($hasReservationType) {
            $score += 5;
        }
        if ($hasSaleChannel) {
            $score += 3;
        }

        return $score;
    }

    /**
     * Apply promotion to fare - only multiplies by passenger count, NOT segment count
     */
    private function applyPromotionToFare(array &$fare, Promotion $promotion, int $passengerCount): float
    {
        $basePrice = (float) ($fare['base_price'] ?? 0);
        $commissionValue = (float) ($promotion->commission_value ?? 0);
        $commissionType = strtolower((string) ($promotion->commission_type ?? 'amount'));
        $priceOption = strtolower((string) ($promotion->price_option ?? 'markup'));

        // Calculate per passenger commission/discount amount
        $perPassengerAmount = $commissionType === 'percentage'
            ? ($basePrice * $commissionValue) / 100
            : $commissionValue;

        // Multiply by passenger count only (NOT segment count)
        $multiplier = max($passengerCount, 1);
        $totalAmount = $perPassengerAmount * $multiplier;
        
        $isDiscount = $priceOption === 'discount';
        $delta = $isDiscount ? -abs($totalAmount) : abs($totalAmount);

        // Apply to fare level fields
        foreach (['base_price', 'total_price', 'billable_price'] as $field) {
            if (isset($fare[$field]) && is_numeric($fare[$field])) {
                $fare[$field] = round(((float) $fare[$field]) + $delta, 2);
            }
        }

        // Apply to individual passenger fares
        if (!empty($fare['passenger_fares']) && is_array($fare['passenger_fares'])) {
            $perPassengerDelta = $passengerCount > 0 ? $delta / $passengerCount : 0;

            foreach ($fare['passenger_fares'] as &$passengerFare) {
                foreach (['base_price', 'total_price'] as $field) {
                    if (isset($passengerFare[$field]) && is_numeric($passengerFare[$field])) {
                        $passengerFare[$field] = round(((float) $passengerFare[$field]) + $perPassengerDelta, 2);
                    }
                }
                $passengerFare['applied_promotion_per_passenger'] = round(abs($perPassengerDelta), 2);
            }
            unset($passengerFare);
        }

        // Store promotion metadata
        $fare['promotion_amount'] = round(abs($totalAmount), 2);
        $fare['promotion_per_unit'] = round(abs($perPassengerAmount), 2);
        $fare['promotion_type'] = $isDiscount ? 'discount' : 'markup';
        $fare['promotion_value_type'] = $commissionType;
        $fare['promotion_multiplier'] = $multiplier;

        return $totalAmount;
    }

    private function detectReservationType(
        string $originCountry,
        string $destinationCountry,
        bool $isInterline,
        bool $isCodeShare
    ): string {
        $originCountry = strtoupper($originCountry);
        $destinationCountry = strtoupper($destinationCountry);

        if ($isInterline) {
            return 'INTERLINE';
        }

        if ($isCodeShare) {
            return 'CODE-SHARE';
        }

        if ($originCountry === 'PK' && $destinationCountry === 'PK') {
            return 'DOMESTIC';
        }

        if ($originCountry === 'PK' && $destinationCountry !== 'PK') {
            return 'EX-PAKISTAN';
        }

        if ($originCountry !== 'PK') {
            return 'SOTO';
        }

        return 'ALL-SECTORS';
    }
}