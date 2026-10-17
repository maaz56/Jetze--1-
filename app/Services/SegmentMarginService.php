<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\SegmentMargin;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Class UtilityService
 * 
 * Central utility service for handling flight itineraries. Its primary function is to match, score,
 * and apply segment-based pricing margins (markups or discounts) to search/booking results
 * based on specific rule criteria such as airlines, origin/destination countries, sale channels, and RBD codes.
 */
class SegmentMarginService
{
    private ?Collection $activeMargins = null;

    private ?array $airlineIdToIata = null;

    public function __construct(
        private readonly CurrencyConversionService $currencyConversionService,
    ) {
    }

    /** Resolve the matching provider margin rule without changing provider fare amounts. */
    public function commercialRuleForFare(array $provider, array $flight): ?array
    {
        $margin = $this->resolveBestMargin(
            $this->activeMarginRules(),
            $this->buildFlightContext(
                $flight,
                $this->airlineIdToIata(),
                $this->buildProviderChannels($provider),
            ),
        );

        if (!$margin) {
            return null;
        }

        return [
            'rule_id' => $margin->id,
            'title' => $margin->title,
            'direction' => strtolower((string) $margin->margin_type) === 'discount' ? 'discount' : 'markup',
            'calculation_type' => 'amount',
            'configured_value' => (string) $margin->margin_value,
            'passenger_count' => $this->getPassengerCount($flight),
            'segment_count' => max(count($flight['segments'] ?? []), 1),
            'application_scope' => 'per_segment_per_passenger',
            'snapshot' => $margin->only([
                'id', 'title', 'sale_channel', 'margin_type', 'margin_value',
                'value_currency', 'disabled_airline_ids', 'is_active', 'priority',
            ]),
        ];
    }

    /** Resolve one margin using every eligible physical segment in the selected quote. */
    public function commercialRuleForItinerary(
        array $provider,
        array $itinerary,
        array $fareReferences = [],
    ): ?array {
        $flights = $this->selectedFlights($itinerary, $fareReferences);
        if ($flights === []) {
            return null;
        }

        $channels = $this->buildProviderChannels($provider);
        $airlineMap = $this->airlineIdToIata();
        $bestMargin = null;
        $bestRank = null;
        $bestEligibleSegmentCount = 0;

        foreach ($this->activeMarginRules() as $margin) {
            $marginChannel = strtoupper((string) ($margin->sale_channel ?? ''));
            if ($marginChannel !== '' && !in_array($marginChannel, array_map('strtoupper', $channels), true)) {
                continue;
            }

            $eligibleSegmentCount = $this->eligibleSegmentCount($flights, $margin, $airlineMap);
            if ($eligibleSegmentCount === 0) {
                continue;
            }

            $rank = [(int) ($margin->priority ?? 0), (int) $margin->id];
            if ($bestRank === null || $rank > $bestRank) {
                $bestMargin = $margin;
                $bestRank = $rank;
                $bestEligibleSegmentCount = $eligibleSegmentCount;
            }
        }

        if (!$bestMargin) {
            return null;
        }

        $passengerCount = $this->passengerCountForSelectedFares($flights, $fareReferences);

        return [
            'rule_id' => $bestMargin->id,
            'title' => $bestMargin->title,
            'direction' => strtolower((string) $bestMargin->margin_type) === 'discount' ? 'discount' : 'markup',
            'calculation_type' => 'amount',
            'configured_value' => (string) $bestMargin->margin_value,
            'passenger_count' => $passengerCount,
            'segment_count' => $bestEligibleSegmentCount,
            'application_scope' => 'per_segment_per_passenger',
            'snapshot' => array_merge($bestMargin->only([
                'id', 'title', 'sale_channel', 'margin_type', 'margin_value',
                'value_currency', 'disabled_airline_ids', 'is_active', 'priority',
            ]), [
                'total_physical_segments' => $this->physicalSegmentCount($flights),
                'eligible_segment_count' => $bestEligibleSegmentCount,
            ]),
        ];
    }

    /**
     * Applies segment margins to an array of transformed flight itineraries.
     *
     * This method acts as the entry point for margin applications:
     * 1. Fetches all configured SegmentMargin rules.
     * 2. Maps airline database IDs to their uppercase IATA codes.
     * 3. Loops through each itinerary, parsing its provider channel identifier.
     * 4. For each flight in the itinerary, resolves segment/passenger counts and builds a context.
     * 5. Resolves the most specific matching margin and applies it to the itinerary fares.
     *
     * @param array $transformedFlights Array of flight itineraries to be evaluated and modified.
     * @param array $context Optional additional execution context.
     * @return array The updated flight itineraries with adjusted pricing.
     */
    public function applySegmentMargins(array $transformedFlights, array $context = []): array
    {
        // 1. Return early if no flight itineraries are provided
        if (empty($transformedFlights)) {
            return $transformedFlights;
        }

        // 2. Fetch all segment margin rules from the database
        $margins = $this->activeMarginRules();
        if ($margins->isEmpty()) {
            return $transformedFlights;
        }

        // 3. Create a map of airline IDs to IATA codes (e.g. [1 => "PK", 2 => "EK"]) in uppercase for easy matching
        $airlineIdToIata = $this->airlineIdToIata();

        // 4. Iterate through each itinerary in the search results
        foreach ($transformedFlights as &$itinerary) {
            $provider = data_get($itinerary, 'provider', []);
            if ($this->usesCommercialMoney($itinerary)) {
                continue;
            }
            
            // Resolve the standard channels for this provider (e.g., SABRE, FLYDUBAI-NDC, GDS, NDC)
            $channels = $this->buildProviderChannels($provider);
            $flights = data_get($itinerary, 'leg.flights', []);
            if (!is_array($flights)) {
                continue;
            }

            // Iterate through individual flight options within the itinerary
            foreach ($flights as &$flight) {
                // Get the list of individual flight segments
                $segments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];
                $segmentCount = count($segments);
                
                // Get the total passenger count based on passenger_fares records
                $passengerCount = $this->getPassengerCount($flight);
                
                // Build a flight context containing all parameters required for rule matching
                $flightContext = $this->buildFlightContext($flight, $airlineIdToIata, $channels);
                // Resolve the highest-scoring matching segment margin rule
                $bestMargin = $this->resolveBestMargin($margins, $flightContext);
                // If no matching rules are found, skip updating pricing for this flight
                if (!$bestMargin) {
                    continue;
                }
                $fares = $flight['fares'] ?? [];
                if (!is_array($fares)) {
                    continue;
                }
                $marginPayload = $this->buildMarginPayload($bestMargin, $segmentCount, $passengerCount);

                // Apply the resolved margin to each fare option
                foreach ($fares as &$fare) {
                    // Update prices inside the fare array (by reference) and retrieve the final applied amount
                    $applied = $this->applyMarginToFare($fare, $bestMargin, $segmentCount, $passengerCount);
                    
                    // Attach information about the applied margin rule to the fare for auditing/UI display
                    $fare['segment_margin'] = array_merge($marginPayload, [
                        'applied_amount' => round(abs($applied), 2),
                        'signed_amount' => round($applied, 2),
                    ]);
                    $fare['segment_margin_amount'] = $fare['segment_margin']['applied_amount'];
                    $fare['segment_margin_signed_amount'] = $fare['segment_margin']['signed_amount'];
                    $fare['segment_margin_data'] = $fare['segment_margin'];
                }
                unset($fare); // Unset reference pointer
                $flight['fares'] = $fares;
            }
            unset($flight); // Unset reference pointer
            data_set($itinerary, 'leg.flights', $flights);
        }
        unset($itinerary); // Unset reference pointer
        return $transformedFlights;
    }

    /**
     * Standardizes a provider's fields into lookup keys representing sale channels.
     *
     * Example input: ['identifier' => 'FLYDUBAI-NDC', 'name' => 'FlyDubai', 'contentSource' => 'NDC']
     * Example output: ["FLYDUBAI-NDC", "FLYDUBAI", "FLYDUBAI-NDC", "NDC"] (uniqued to unique uppercase channels)
     *
     * @param array $provider The provider data dictionary.
     * @return array List of normalized channel identifiers.
     */
    private function buildProviderChannels(array $provider): array
    {
        $identifier = strtoupper((string) data_get($provider, 'identifier', ''));
        $name = strtoupper((string) data_get($provider, 'name', ''));
        $contentSource = strtoupper((string) data_get($provider, 'contentSource', ''));
        $contentSource = $contentSource === 'NDC' ? 'NDC' : $contentSource;

        $channels = [];

        // Add identifier and its base (e.g., "SABRE-GDS" -> add both "SABRE-GDS" and "SABRE")
        if ($identifier !== '') {
            $channels[] = $identifier;
            $identifierBase = preg_replace('/-(GDS|NDC)$/', '', $identifier);
            if (!empty($identifierBase)) {
                $channels[] = $identifierBase;
            }
        }

        // Add GDS/NDC source identifiers linked with names
        if ($name !== '') {
            $channels[] = $name;
            if ($contentSource !== '') {
                $channels[] = $name . '-' . $contentSource;
            }
        }

        // Add pure content source identifier (GDS or NDC)
        if ($contentSource !== '') {
            $channels[] = $contentSource;
        }

        // AT is priced through the same commercial margin configuration as
        // Travelport. Keep AT's own channel above, while also exposing every
        // channel a Travelport GDS/NDC result would expose so existing rules
        // continue to work without being duplicated for AT.
        if (in_array('AT', [$identifier, $name, $contentSource], true)) {
            $channels = array_merge($channels, [
                'TRAVELPORT',
                'TRAVELPORT-GDS',
                'TRAVELPORT-NDC',
                'GDS',
                'NDC',
            ]);
        }

        // Filter out empty strings, retrieve unique values, and reset array index keys
        return array_values(array_unique(array_filter($channels)));
    }

    /**
     * Resolves the passenger count associated with a flight's pricing package.
     *
     * Determines how many passenger fares are structured (Adults, Children, Infants)
     * to ensure the segment margin is correctly multiplied by passenger count.
     *
     * @param array $flight Single flight dataset.
     * @return int Total number of passengers (defaulting to at least 1).
     */
    private function getPassengerCount(array $flight): int
    {
        $fares = $flight['fares'] ?? [];
        if (empty($fares) || !is_array($fares)) {
            return 1; // Default fallback to 1 passenger
        }

        $passengerCounts = $fares[0]['passenger_counts'] ?? null;
        if (is_array($passengerCounts)) {
            return max(array_sum(array_map('intval', $passengerCounts)), 1);
        }

        $passengerFares = $fares[0]['passenger_fares'] ?? [];
        if (!is_array($passengerFares)) {
            return 1;
        }

        $totalPassengers = 0;
        foreach ($passengerFares as $passengerFare) {
            $totalPassengers += $this->getPassengerFareQuantity($passengerFare);
        }

        return max($totalPassengers, 1); // Ensure returning at least 1 passenger
    }

    private function getPassengerFareQuantity(array $passengerFare): int
    {
        $quantity =
            $passengerFare['total_passenger'] ??
            $passengerFare['passengerNumber'] ??
            data_get($passengerFare, 'PassengerTypeQuantity.@attributes.Quantity') ??
            data_get($passengerFare, 'PassengerTypeQuantity.Quantity') ??
            1;

        return max((int) $quantity, 1);
    }

    /** Keep only the flights represented by selected fare references. */
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

    /** Read the passenger total from the actual selected fare. */
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

    /** Count physical segments after removing those belonging to excluded airlines. */
    private function eligibleSegmentCount(array $flights, SegmentMargin $margin, array $airlineMap): int
    {
        $excludedIatas = collect($margin->disabled_airline_ids ?? [])
            ->map(fn ($id) => strtoupper((string) ($airlineMap[(int) $id] ?? '')))
            ->filter()
            ->unique()
            ->all();
        $count = 0;

        foreach ($flights as $flight) {
            $flightMarketing = strtoupper((string) data_get($flight, 'marketing_carrier.iata', ''));

            foreach ($flight['segments'] ?? [] as $segment) {
                $segmentAirlines = array_values(array_filter(array_unique([
                    $flightMarketing,
                    strtoupper((string) data_get($segment, 'marketing_carrier.iata', '')),
                    strtoupper((string) data_get($segment, 'operating_carrier.iata', '')),
                ])));

                if ($excludedIatas !== [] && array_intersect($excludedIatas, $segmentAirlines)) {
                    continue;
                }

                $count++;
            }
        }

        return $count;
    }

    /** Count the actual flight chunks represented by the selected itinerary. */
    private function physicalSegmentCount(array $flights): int
    {
        return array_sum(array_map(
            static fn (array $flight): int => count($flight['segments'] ?? []),
            $flights,
        ));
    }

    /**
     * Extracts and builds a structured context mapping the physical traits of a flight option.
     *
     * This context includes: IATA carrier details and sale channels for margin rule matching.
     *
     * @param array $flight Single flight dataset.
     * @param array $airlineIdToIata Map of airline ID to IATA code.
     * @param array $channels Evaluated sale channels for this provider.
     * @return array Standardized flight context dictionary.
     */
    private function buildFlightContext(array $flight, array $airlineIdToIata, array $channels): array
    {
        $segments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];

        // 1. Resolve marketing carrier IATA
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

        // 2. Match all flight airlines (operating + marketing) with standard database IDs
        $allAirlineIatas = array_values(array_unique(array_filter($segmentAirlines)));
        $airlineIds = [];
        foreach ($airlineIdToIata as $id => $iata) {
            if (in_array($iata, $allAirlineIatas, true)) {
                $airlineIds[] = (int) $id;
            }
        }

        return [
            'channels'    => array_map('strtoupper', $channels),
            'airline_ids' => $airlineIds,
        ];
    }

    /**
     * Resolves the single highest-scoring segment margin that matches a flight's context.
     *
     * Scores all matching margins according to specific criteria specificity. In case
     * of a tie, the margin with the higher database primary key (ID) is chosen.
     *
     * @param iterable $margins List of all segment margins.
     * @param array $flightContext Flight context dataset.
     * @return SegmentMargin|null The resolved margin rule or null if none match.
     */
    private function resolveBestMargin($margins, array $flightContext): ?SegmentMargin
    {
        $best = null;
        $bestRank = null;

        foreach ($margins as $margin) {
            // Check if the current rule meets all flight context criteria
            if (!$this->marginMatches($margin, $flightContext)) {
                continue;
            }

            // Calculate the specificity score of the rule
            $rank = [
                (int) ($margin->priority ?? 0),
                (int) $margin->id,
            ];

            // Explicit priority wins; the latest rule breaks an equal-priority tie.
            if ($bestRank === null || $rank > $bestRank) {
                $best = $margin;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * Evaluates whether a SegmentMargin rule fits a specific flight context.
     *
     * Filters evaluated:
     * - Sale Channel (TravelPort-GDS / TravelPort-NDC)
     * - Airline (matching list of blocked airline IDs or individual airline_id)
     *
     * @param SegmentMargin $margin The margin rule database model.
     * @param array $flightContext Current flight traits.
     * @return bool True if the rule is matching; false otherwise.
     */
    private function marginMatches(SegmentMargin $margin, array $flightContext): bool
    {
        // 1. Check Sale Channel match
        $marginChannel = strtoupper((string) ($margin->sale_channel ?? ''));
        if ($marginChannel !== '' && !in_array($marginChannel, $flightContext['channels'] ?? [], true)) {
            return false;
        }

        // 2. Excluded airlines check using the canonical exclusion list.
        $disabledAirlineIds = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($margin->disabled_airline_ids) ? $margin->disabled_airline_ids : [],
        ))));
        if (!empty($disabledAirlineIds) && !empty(array_intersect($disabledAirlineIds, $flightContext['airline_ids'] ?? []))) {
            return false;
        }

        return true;
    }

    /**
     * Builds the normalized metadata that is mapped into every matched fare.
     *
     * @param SegmentMargin $margin Matched segment margin rule.
     * @param int $segmentCount Number of itinerary segments.
     * @param int $passengerCount Number of passengers.
     * @return array Payload suitable for frontend display and booking persistence.
     */
    private function buildMarginPayload(SegmentMargin $margin, int $segmentCount, int $passengerCount): array
    {
        $multiplier = max($segmentCount, 1) * max($passengerCount, 1);
        $marginValue = (float) ($margin->margin_value ?? 0);

        return [
            'id' => $margin->id,
            'title' => $margin->title,
            'sale_channel' => $margin->sale_channel,
            'margin_type' => $margin->margin_type,
            'amount_type' => 'amount',
            'value_currency' => 'AED',
            'margin_value' => $marginValue,
            'margin_per_unit' => round($marginValue, 2),
            'segment_count' => max($segmentCount, 1),
            'passenger_count' => max($passengerCount, 1),
            'multiplier_applied' => $multiplier,
            'disabled_airline_ids' => is_array($margin->disabled_airline_ids) ? array_values($margin->disabled_airline_ids) : [],
            'excluded_airline_ids' => is_array($margin->disabled_airline_ids)
                ? array_values($margin->disabled_airline_ids)
                : [],
        ];
    }

    /**
     * Calculates and applies a segment-based margin markup or discount directly to a fare package.
     *
     * The pricing margin is calculated as:
     * margin = (Base Margin Value) * (Number of Flight Segments) * (Number of Passengers)
     *
     * @param array $fare The fare package configuration to modify (passed by reference).
     * @param SegmentMargin $margin The resolved margin rule model containing values/types.
     * @param int $segmentCount Count of segments in this flight.
     * @param int $passengerCount Count of passengers in this flight.
     * @return float The total calculated margin adjustment delta.
     */
    private function applyMarginToFare(array &$fare, SegmentMargin $margin, int $segmentCount, int $passengerCount): float
    {
        $segmentCount = max($segmentCount, 1);
        $passengerCount = max($passengerCount, 1);
        $basePrice = (float) ($fare['base_price'] ?? 0);
        $marginValue = (float) ($margin->margin_value ?? 0);
        $priceOption = strtolower((string) ($margin->margin_type ?? 'markup')); // can be: discount or markup
        $fareCurrency = strtoupper((string) data_get($fare, 'currency.code', 'AED'));

        $computed = (float) $this->currencyConversionService->convertMoney(
            $marginValue,
            'AED',
            $fareCurrency,
        )['amount'];

        // 2. Calculate the multiplier (segments × passengers) because margins apply per segment per traveler
        $multiplier = $segmentCount * $passengerCount;
        
        // 3. Obtain total delta adjustments
        $totalComputed = $computed * $multiplier;

        // Determine if delta should represent a discount (subtraction) or markup (addition)
        $isDiscount = $priceOption === 'discount';
        $effectiveAmount = $isDiscount
            ? min(abs($totalComputed), max($basePrice, 0))
            : abs($totalComputed);
        $delta = $isDiscount ? -$effectiveAmount : $effectiveAmount;

        // Store the original base price for audit tracking
        $originalBasePrice = $basePrice;
        
        // 4. Update the parent fare price fields in-place
        foreach (['base_price', 'total_price', 'billable_price'] as $field) {
            if (isset($fare[$field]) && is_numeric($fare[$field])) {
                $fare[$field] = round(max(((float) $fare[$field]) + $delta, 0), 2);
            }
        }

        // 5. Recalculate and update nested sub-passenger fares (e.g. per Adult/Child)
        if (!empty($fare['passenger_fares']) && is_array($fare['passenger_fares'])) {
            $perPassengerDelta = $delta / $passengerCount; // Pricing segment share per traveler
            
            foreach ($fare['passenger_fares'] as &$passengerFare) {
                $passengerFareQuantity = $this->getPassengerFareQuantity($passengerFare);
                $passengerFareDelta = $perPassengerDelta * $passengerFareQuantity;

                foreach (['base_price', 'total_price'] as $field) {
                    if (isset($passengerFare[$field]) && is_numeric($passengerFare[$field])) {
                        $passengerFare[$field] = round(max(((float) $passengerFare[$field]) + $passengerFareDelta, 0), 2);
                    }
                }
                // Log the final calculated pricing adjustment per traveler
                $passengerFare['applied_margin_per_passenger'] = round($perPassengerDelta, 2);
                $passengerFare['applied_segment_margin_amount'] = round(abs($passengerFareDelta), 2);
                $passengerFare['applied_segment_margin_signed_amount'] = round($passengerFareDelta, 2);
            }
            unset($passengerFare); // Unset reference pointer
        }

        // 6. Record metadata for audit trail auditing and transparency in the database/JSON logging
        $fare['segment_margin_amount'] = round(abs($effectiveAmount), 2);
        $fare['segment_margin_signed_amount'] = round($delta, 2);
        $fare['margin_per_unit'] = round(abs($computed), 2);
        $fare['segment_amount_type'] = 'amount';
        $fare['segment_margin_currency'] = 'AED';
        $fare['segment_margin_applied_currency'] = $fareCurrency;
        $fare['segment_margin_type'] = $isDiscount ? 'discount' : 'markup';
        $fare['multiplier'] = $multiplier;
        $fare['segment_count'] = $segmentCount;
        $fare['passenger_count'] = $passengerCount;
        $fare['original_base_price'] = $originalBasePrice;

        return $delta;
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
    private function activeMarginRules(): Collection
    {
        return $this->activeMargins
            ??= SegmentMargin::query()->where('is_active', true)->get();
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

    /**
     * Determines a flight's reservation classification based on route boundaries and relationships.
     *
     * Categories evaluated:
     * - CODE-SHARE: Different operating and marketing carriers.
     * - INTERLINE: Multiple different operating airlines.
     * - DOMESTIC: Route is fully inside Pakistan boundaries (PK -> PK).
     * - EX-PAKISTAN: Flight originates from Pakistan to an international port (PK -> foreign country).
     * - SOTO: Origin is outside Pakistan (foreign -> PK or foreign -> foreign, e.g., DXB -> LHE / DXB -> JED).
     *
     * @param string $originCountry ISO country code of the origin port.
     * @param string $destinationCountry ISO country code of the destination port.
     * @param bool $isInterline Boolean checking if itinerary is interline.
     * @param bool $isCodeShare Boolean checking if itinerary represents a codeshare flight.
     * @return string Normalized reservation classification.
     */
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
