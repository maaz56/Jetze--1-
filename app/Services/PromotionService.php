<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PromotionService
{
    private ?Collection $activePromotions = null;

    private ?array $airlineIdToIata = null;

    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /** Resolve the matching provider promotion rule without changing provider fare amounts. */
    public function commercialRuleForFare(array $provider, array $flight, array $itineraryContext = []): ?array
    {
        $promotion = $this->resolveBestPromotion(
            $this->activePromotionRules(),
            $this->buildFlightContext(
                $flight,
                $this->airlineIdToIata(),
                $this->buildProviderChannels($provider),
                $itineraryContext,
            ),
        );

        if (!$promotion) {
            return null;
        }

        return $this->commercialRulePayload(
            $promotion,
            $this->getPassengerCount($flight),
            strtolower((string) $promotion->commission_type) === 'percentage'
                ? 'per_leg'
                : 'per_leg_per_passenger',
        );
    }

    /** Resolve one promotion for the complete selected quote, regardless of trip price layout. */
    public function commercialRuleForItinerary(
        array $provider,
        array $itinerary,
        array $fareReferences = [],
    ): ?array {
        $flights = $this->selectedFlights($itinerary, $fareReferences);
        if ($flights === []) {
            return null;
        }

        $airlineMap = $this->airlineIdToIata();
        $channels = $this->buildProviderChannels($provider);
        $itineraryContext = $this->itineraryContext($itinerary);
        $flightContexts = array_map(
            fn (array $flight): array => $this->buildFlightContext(
                $flight,
                $airlineMap,
                $channels,
                $itineraryContext,
            ),
            $flights,
        );

        $context = [
            'channels' => array_values(array_unique(array_merge(...array_column($flightContexts, 'channels')))),
            'airline_ids' => array_values(array_unique(array_merge(...array_column($flightContexts, 'airline_ids')))),
            'travel_departure_date' => collect($flightContexts)->pluck('travel_departure_date')->filter()->min(),
            'travel_arrival_date' => collect($flightContexts)->pluck('travel_arrival_date')->filter()->max(),
            'ticketing_date' => now()->toDateString(),
            'reservation_type' => $itineraryContext['reservation_type'] ?? 'ALL-SECTORS',
        ];
        $promotion = $this->resolveBestPromotion($this->activePromotionRules(), $context);

        if (!$promotion) {
            return null;
        }

        return $this->commercialRulePayload(
            $promotion,
            $this->passengerCountForSelectedFares($flights, $fareReferences),
            strtolower((string) $promotion->commission_type) === 'percentage'
                ? 'per_quote'
                : 'per_quote_per_passenger',
            $context,
        );
    }

    /** Build one reservation classification shared by every leg in an itinerary. */
    public function itineraryContext(array $itinerary): array
    {
        $flights = data_get($itinerary, 'leg.flights', []);
        if (!is_array($flights) || empty($flights)) {
            return [];
        }

        $outbound = $flights[0];
        $allAirlines = [];
        $isCodeShare = false;

        foreach ($flights as $flight) {
            $marketingIata = strtoupper((string) data_get($flight, 'marketing_carrier.iata', ''));
            if ($marketingIata !== '') {
                $allAirlines[] = $marketingIata;
            }

            foreach ($flight['segments'] ?? [] as $segment) {
                $segmentMarketing = strtoupper((string) data_get($segment, 'marketing_carrier.iata', $marketingIata));
                $operatingIata = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));

                if ($operatingIata !== '') {
                    $allAirlines[] = $operatingIata;
                }
                if ($segmentMarketing !== '' && $operatingIata !== '' && $segmentMarketing !== $operatingIata) {
                    $isCodeShare = true;
                }
            }
        }

        $originCountry = strtoupper((string) data_get($outbound, 'from.country.code', data_get($outbound, 'from.city.country.code', '')));
        $destinationCountry = strtoupper((string) data_get($outbound, 'to.country.code', data_get($outbound, 'to.city.country.code', '')));

        return [
            'reservation_type' => $this->detectReservationType(
                $originCountry,
                $destinationCountry,
                count(array_unique(array_filter($allAirlines))) > 1,
                $isCodeShare,
            ),
        ];
    }

    public function applyPromotions(array $transformedFlights, array $context = []): array
    {

        if (empty($transformedFlights)) {
            return $transformedFlights;
        }

        $promotions = $this->activePromotionRules();
        if ($promotions->isEmpty()) {
            return $transformedFlights;
        }

        $airlineIdToIata = $this->airlineIdToIata();

        foreach ($transformedFlights as &$itinerary) {
            $provider = data_get($itinerary, 'provider', []);
            if ($this->usesCommercialMoney($itinerary)) {
                continue;
            }
            $channels = $this->buildProviderChannels($provider);
            $itineraryContext = $this->itineraryContext($itinerary);
            $flights = data_get($itinerary, 'leg.flights', []);

            if (!is_array($flights)) {
                continue;
            }

            foreach ($flights as &$flight) {
                $segments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];
                $passengerCount = $this->getPassengerCount($flight);
                $flightContext = $this->buildFlightContext($flight, $airlineIdToIata, $channels, $itineraryContext);

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
                        'value_currency' => 'AED',
                        'applied_amount' => round($applied, 2),
                        'passenger_count' => $passengerCount,
                        'multiplier_applied' => strtolower((string) $bestPromotion->commission_type) === 'percentage'
                            ? 1
                            : $passengerCount,
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

        // AT shares Travelport's promotion configuration. Add the complete
        // Travelport channel set for AT results, allowing existing generic,
        // GDS, and NDC promotions to match without creating duplicate rules.
        if (in_array('AT', [$identifier, $name, $contentSource], true)) {
            $channels = array_merge($channels, [
                'TRAVELPORT',
                'TRAVELPORT-GDS',
                'TRAVELPORT-NDC',
                'GDS',
                'NDC',
            ]);
        }

        return array_values(array_unique(array_filter($channels)));
    }

    private function getPassengerCount(array $flight): int
    {
        $fares = $flight['fares'] ?? [];
        if (empty($fares) || !is_array($fares)) {
            return 1;
        }

        $passengerCounts = $fares[0]['passenger_counts'] ?? null;
        if (is_array($passengerCounts)) {
            return max(array_sum(array_map('intval', $passengerCounts)), 1);
        }

        $passengerFares = $fares[0]['passenger_fares'] ?? [];
        $count = 0;

        foreach (is_array($passengerFares) ? $passengerFares : [] as $passengerFare) {
            $count += max((int) ($passengerFare['count'] ?? $passengerFare['total_passenger'] ?? 1), 1);
        }

        return max($count, 1);
    }

    /** Build the immutable calculation payload shared by fare and quote pricing. */
    private function commercialRulePayload(
        Promotion $promotion,
        int $passengerCount,
        string $scope,
        array $matchedContext = [],
    ): array {
        return [
            'rule_id' => $promotion->id,
            'title' => $promotion->title,
            'direction' => strtolower((string) ($promotion->price_option ?? 'markup')) === 'discount' ? 'discount' : 'markup',
            'calculation_type' => strtolower((string) $promotion->commission_type) === 'percentage' ? 'percentage' : 'amount',
            'configured_value' => (string) $promotion->commission_value,
            'passenger_count' => max($passengerCount, 1),
            'segment_count' => 1,
            'application_scope' => $scope,
            'snapshot' => array_merge($promotion->only([
                'id', 'title', 'sale_channel', 'reservation_type', 'price_option',
                'commission_type', 'commission_value', 'value_currency', 'airline_id',
                'travel_start_date', 'travel_end_date', 'ticketing_start_date',
                'ticketing_end_date', 'is_active', 'priority',
            ]), $matchedContext === [] ? [] : ['matched_context' => $matchedContext]),
        ];
    }

    /** Keep only flights containing the selected fares; use all flights for search-level pricing. */
    private function selectedFlights(array $itinerary, array $fareReferences): array
    {
        $flights = data_get($itinerary, 'leg.flights', []);
        if (!is_array($flights)) {
            return [];
        }

        $references = array_flip(array_values(array_filter($fareReferences, 'is_string')));
        if ($references === []) {
            return array_values($flights);
        }

        return array_values(array_filter($flights, static function (array $flight) use ($references): bool {
            foreach ($flight['fares'] ?? [] as $fare) {
                if (isset($references[$fare['ref_id'] ?? ''])) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Read passenger count from the selected fare instead of unrelated alternatives. */
    private function passengerCountForSelectedFares(array $flights, array $fareReferences): int
    {
        $references = array_flip(array_values(array_filter($fareReferences, 'is_string')));

        foreach ($flights as $flight) {
            foreach ($flight['fares'] ?? [] as $fare) {
                if ($references !== [] && !isset($references[$fare['ref_id'] ?? ''])) {
                    continue;
                }

                return $this->getPassengerCount(array_merge($flight, ['fares' => [$fare]]));
            }
        }

        return $this->getPassengerCount($flights[0] ?? []);
    }

    private function buildFlightContext(
        array $flight,
        array $airlineIdToIata,
        array $channels,
        array $itineraryContext = [],
    ): array
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
            'reservation_type' => $itineraryContext['reservation_type']
                ?? $this->detectReservationType($originCountry, $destinationCountry, $isInterline, $isCodeShare),
        ];
    }

    private function resolveBestPromotion($promotions, array $flightContext): ?Promotion
    {
        $best = null;
        $bestRank = null;

        foreach ($promotions as $promotion) {
            if (!$this->promotionMatches($promotion, $flightContext)) {
                continue;
            }

            $rank = [
                (int) ($promotion->priority ?? 0),
                $this->specificityScore($promotion),
                (int) $promotion->id,
            ];

            if ($bestRank === null || $rank > $bestRank) {
                $best = $promotion;
                $bestRank = $rank;
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
        $fareCurrency = strtoupper((string) data_get($fare, 'currency.code', 'AED'));

        $flatAmountInFareCurrency = $commissionType === 'amount'
            ? (float) $this->currencyConversionService->convertMoney(
                $commissionValue,
                'AED',
                $fareCurrency,
            )['amount']
            : 0.0;

        // Percentage applies to the total fare once. A flat amount applies per passenger.
        $multiplier = $commissionType === 'percentage' ? 1 : max($passengerCount, 1);
        $perPassengerAmount = $commissionType === 'percentage'
            ? ($basePrice * $commissionValue) / 100
            : $flatAmountInFareCurrency;
        $totalAmount = $perPassengerAmount * $multiplier;
        
        $isDiscount = $priceOption === 'discount';
        $effectiveAmount = $isDiscount
            ? min(abs($totalAmount), max($basePrice, 0))
            : abs($totalAmount);
        $delta = $isDiscount ? -$effectiveAmount : $effectiveAmount;

        // Apply to fare level fields
        foreach (['base_price', 'total_price', 'billable_price'] as $field) {
            if (isset($fare[$field]) && is_numeric($fare[$field])) {
                $fare[$field] = round(max(((float) $fare[$field]) + $delta, 0), 2);
            }
        }

        // Apply to individual passenger fares
        if (!empty($fare['passenger_fares']) && is_array($fare['passenger_fares'])) {
            $perPassengerDelta = $passengerCount > 0 ? $delta / $passengerCount : 0;

            foreach ($fare['passenger_fares'] as &$passengerFare) {
                foreach (['base_price', 'total_price'] as $field) {
                    if (isset($passengerFare[$field]) && is_numeric($passengerFare[$field])) {
                        $passengerFare[$field] = round(max(((float) $passengerFare[$field]) + $perPassengerDelta, 0), 2);
                    }
                }
                $passengerFare['applied_promotion_per_passenger'] = round(abs($perPassengerDelta), 2);
            }
            unset($passengerFare);
        }

        // Store promotion metadata
        $fare['promotion_amount'] = round(abs($effectiveAmount), 2);
        $fare['promotion_per_unit'] = round(abs($perPassengerAmount), 2);
        $fare['promotion_type'] = $isDiscount ? 'discount' : 'markup';
        $fare['promotion_value_type'] = $commissionType;
        $fare['promotion_value_currency'] = 'AED';
        $fare['promotion_applied_currency'] = $fareCurrency;
        $fare['promotion_multiplier'] = $multiplier;

        return $effectiveAmount;
    }

    /** Detect fares handled by the normalized AED commercial-pricing pipeline. */
    private function usesCommercialMoney(array $itinerary): bool
    {
        foreach (data_get($itinerary, 'leg.flights', []) as $flight) {
            foreach ($flight['fares'] ?? [] as $fare) {
                if (isset($fare['provider_booking_money']['amount'], $fare['provider_booking_money']['currency'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Load active rules once so large streamed searches do not query per fare. */
    private function activePromotionRules(): Collection
    {
        return $this->activePromotions
            ??= Promotion::query()->where('is_active', true)->get();
    }

    /** Cache the airline lookup used by rule matching for this request. */
    private function airlineIdToIata(): array
    {
        return $this->airlineIdToIata
            ??= Airline::query()
                ->pluck('iata_code', 'id')
                ->map(fn ($iata) => strtoupper((string) $iata))
                ->toArray();
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
