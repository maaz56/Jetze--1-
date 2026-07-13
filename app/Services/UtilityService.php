<?php

namespace App\Services;

use App\Models\Airline;
use App\Models\Promotion;
use App\Models\SegmentMargin;
use Carbon\Carbon;
use Log;

class UtilityService
{

    public function updateTravelportPriceResponse($response, array $requestData = []): array
    {
        if (!is_array($response) || empty($response['OfferListResponse']['OfferID'])) {
            return is_array($response) ? $response : [];
        }

        $flightData = $requestData['flight_data'] ?? [];
        $provider = $flightData['provider'] ?? [];
        $channels = $this->buildProviderChannels($provider);
        $airlineIataToId = Airline::query()
            ->pluck('id', 'iata_code')
            ->mapWithKeys(fn ($id, $iata) => [strtoupper((string) $iata) => (int) $id])
            ->toArray();

        $selectedFlightContext = $this->buildSelectedFlightContext(
            $flightData,
            $requestData['selectedFares'] ?? [],
            $channels,
            $airlineIataToId
        );

        $segmentMargins = SegmentMargin::query()->get();
        $promotions = Promotion::query()->get();

        $offers = $this->normalizeList($response['OfferListResponse']['OfferID']);
        foreach ($offers as &$offer) {
            if (!is_array($offer)) {
                continue;
            }

            $offerContext = $this->buildOfferContext($offer, $selectedFlightContext, $channels, $airlineIataToId);
            $bestSegmentMargin = $this->resolveBestSegmentMargin($segmentMargins, $offerContext);
            $bestPromotion = $this->resolveBestPromotion($promotions, $offerContext);

            if (!$bestSegmentMargin && !$bestPromotion) {
                continue;
            }

            $basePrice = (float) data_get($offer, 'Price.Base', 0);
            $passengerCount = max((int) ($offerContext['passenger_count'] ?? 1), 1);
            $adjustments = [];
            $totalDelta = 0.0;

            if ($bestSegmentMargin) {
                $segmentDelta = $this->calculateSegmentMarginDelta(
                    $bestSegmentMargin,
                    (int) ($offerContext['segment_count'] ?? 1),
                    $passengerCount
                );
                $totalDelta += $segmentDelta;
                $adjustments['segment_margin'] = [
                    'id' => $bestSegmentMargin->id,
                    'title' => $bestSegmentMargin->title,
                    'margin_type' => $bestSegmentMargin->margin_type,
                    'margin_value' => (float) $bestSegmentMargin->margin_value,
                    'applied_amount' => round(abs($segmentDelta), 2),
                    'segment_count' => (int) ($offerContext['segment_count'] ?? 1),
                    'passenger_count' => $passengerCount,
                    'multiplier_applied' => ((int) ($offerContext['segment_count'] ?? 1)) * $passengerCount,
                ];
            }

            if ($bestPromotion) {
                $promotionDelta = $this->calculatePromotionDelta($bestPromotion, $basePrice, $passengerCount);
                $totalDelta += $promotionDelta;
                $adjustments['promotion'] = [
                    'id' => $bestPromotion->id,
                    'title' => $bestPromotion->title,
                    'price_option' => $bestPromotion->price_option ?? 'markup',
                    'commission_type' => $bestPromotion->commission_type,
                    'commission_value' => (float) $bestPromotion->commission_value,
                    'applied_amount' => round(abs($promotionDelta), 2),
                    'passenger_count' => $passengerCount,
                    'multiplier_applied' => $passengerCount,
                ];
            }

            if (abs($totalDelta) < 0.01) {
                continue;
            }

            $this->applyTravelportPriceDelta($offer, $totalDelta, $passengerCount, $adjustments);
        }
        unset($offer);

        $response['OfferListResponse']['OfferID'] = $this->restoreListShape(
            $offers,
            $response['OfferListResponse']['OfferID']
        );

        return $response;
    }

    public function updateOneApiPriceResponse($response, array $requestData = [])
    {
        $returnAsJson = is_string($response);
        $priceResponse = $returnAsJson ? json_decode($response, true) : $response;

        if (!is_array($priceResponse)) {
            return $response;
        }

        $pricedItineraryPath = 'Body.OTA_AirPriceRS.PricedItineraries.PricedItinerary';
        $originalPricedItineraries = data_get($priceResponse, $pricedItineraryPath, []);
        $pricedItineraries = $this->normalizeList($originalPricedItineraries);
        $hasPricedItinerary = collect($pricedItineraries)->contains(
            fn ($itinerary) => is_array($itinerary) && !empty($itinerary['AirItineraryPricingInfo'])
        );

        if (!$hasPricedItinerary) {
            return $response;
        }

        $flightData = $requestData['flight'] ?? $requestData['flight_data'] ?? [];
        $provider = $flightData['provider'] ?? [];
        $channels = $this->buildProviderChannels($provider);
        $airlineIataToId = Airline::query()
            ->pluck('id', 'iata_code')
            ->mapWithKeys(fn ($id, $iata) => [strtoupper((string) $iata) => (int) $id])
            ->toArray();

        $context = $this->buildSelectedFlightContext(
            $flightData,
            $requestData['selectedFares'] ?? [],
            $channels,
            $airlineIataToId
        );
        $context = $this->mergeOneApiPriceContext($pricedItineraries, $context, $airlineIataToId);

        $promotion = $this->resolveBestPromotion(Promotion::query()->get(), $context);
        if (!$promotion) {
            return $response;
        }

        $promotionPayload = [
            'id' => $promotion->id,
            'title' => $promotion->title,
            'price_option' => $promotion->price_option ?? 'markup',
            'commission_type' => $promotion->commission_type,
            'commission_value' => (float) $promotion->commission_value,
        ];

        $hasAppliedAdjustment = false;
        foreach ($pricedItineraries as &$pricedItinerary) {
            if (!is_array($pricedItinerary) || empty($pricedItinerary['AirItineraryPricingInfo'])) {
                continue;
            }

            $basePrice = (float) data_get($pricedItinerary, 'AirItineraryPricingInfo.ItinTotalFare.BaseFare.@attributes.Amount', 0);
            $passengerCount = $this->getOneApiPassengerCount($pricedItinerary, (int) ($context['passenger_count'] ?? 1));
            $delta = $this->calculatePromotionDelta($promotion, $basePrice, $passengerCount);

            if (abs($delta) < 0.01) {
                continue;
            }

            $this->applyOneApiPromotionDelta($pricedItinerary, $delta, $passengerCount, array_merge($promotionPayload, [
                'applied_amount' => round(abs($delta), 2),
                'signed_amount' => round($delta, 2),
                'passenger_count' => $passengerCount,
                'multiplier_applied' => $passengerCount,
            ]));
            $hasAppliedAdjustment = true;
        }
        unset($pricedItinerary);

        if (!$hasAppliedAdjustment) {
            return $response;
        }

        data_set($priceResponse, $pricedItineraryPath, $this->restoreListShape($pricedItineraries, $originalPricedItineraries));

        return $returnAsJson ? json_encode($priceResponse) : $priceResponse;
    }

    private function buildProviderChannels(array $provider): array
    {
        $identifier = strtoupper((string) data_get($provider, 'identifier', ''));
        $name = strtoupper((string) data_get($provider, 'name', ''));
        $contentSource = strtoupper((string) data_get($provider, 'contentSource', ''));

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

    private function buildSelectedFlightContext(
        array $flightData,
        array $selectedFares,
        array $channels,
        array $airlineIataToId
    ): array {
        $flights = data_get($flightData, 'leg.flights', []);
        $segments = [];
        $airlineIatas = [];
        $selectedFareItems = [];

        foreach ($flights as $flightIndex => $flight) {
            $flightSegments = is_array($flight['segments'] ?? null) ? $flight['segments'] : [];
            $segments = array_merge($segments, $flightSegments);

            $marketingIata = strtoupper((string) data_get($flight, 'marketing_carrier.iata', ''));
            if ($marketingIata !== '') {
                $airlineIatas[] = $marketingIata;
            }

            foreach ($flightSegments as $segment) {
                $operatingIata = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));
                if ($operatingIata !== '') {
                    $airlineIatas[] = $operatingIata;
                }
            }

            $selectedFareRef = $selectedFares[$flightIndex] ?? null;
            foreach (($flight['fares'] ?? []) as $fare) {
                if ($selectedFareRef === null || ($fare['ref_id'] ?? null) === $selectedFareRef) {
                    $selectedFareItems[] = $fare;
                    break;
                }
            }
        }

        $originCountry = strtoupper((string) data_get($segments, '0.from.country.code', data_get($flightData, 'leg.flights.0.from.country.code', '')));
        $lastSegment = !empty($segments) ? end($segments) : null;
        $destinationCountry = strtoupper((string) data_get($lastSegment, 'to.country.code', ''));

        return [
            'channels' => array_map('strtoupper', $channels),
            'airline_ids' => $this->airlineIdsFromIatas($airlineIatas, $airlineIataToId),
            'segment_count' => max(count($segments), 1),
            'passenger_count' => $this->getSelectedPassengerCount($selectedFareItems),
            'travel_departure_date' => data_get($segments, '0.departure_at')
                ? Carbon::parse(data_get($segments, '0.departure_at'))->toDateString()
                : null,
            'travel_arrival_date' => data_get($lastSegment, 'arrival_at')
                ? Carbon::parse(data_get($lastSegment, 'arrival_at'))->toDateString()
                : null,
            'ticketing_date' => Carbon::today()->toDateString(),
            'reservation_type' => $this->detectReservationType(
                $originCountry,
                $destinationCountry,
                count(array_unique($airlineIatas)) > 1,
                $this->isCodeShare($segments, $airlineIatas[0] ?? '')
            ),
        ];
    }

    private function buildOfferContext(array $offer, array $selectedFlightContext, array $channels, array $airlineIataToId): array
    {
        $products = $this->normalizeList(data_get($offer, 'Product', []));
        $airlineIatas = [];
        $segmentCount = 0;
        $productPassengerCount = 0;

        foreach ($products as $product) {
            foreach ($this->normalizeList($product['FlightSegment'] ?? []) as $segment) {
                $segmentCount++;
                $carrier = strtoupper((string) data_get($segment, 'Flight.carrier', ''));
                $operatingCarrier = strtoupper((string) data_get($segment, 'Flight.operatingCarrier', ''));
                if ($carrier !== '') {
                    $airlineIatas[] = $carrier;
                }
                if ($operatingCarrier !== '') {
                    $airlineIatas[] = $operatingCarrier;
                }
            }

            foreach ($this->normalizeList($product['PassengerFlight'] ?? []) as $passengerFlight) {
                $productPassengerCount += (int) ($passengerFlight['passengerQuantity'] ?? 0);
            }
        }

        $priceBreakdownPassengerCount = 0;
        $priceBreakdowns = $this->normalizeList(data_get($offer, 'Price.PriceBreakdown', []));
        foreach ($priceBreakdowns as $breakdown) {
            $priceBreakdownPassengerCount += (int) ($breakdown['quantity'] ?? 0);
        }
        $passengerCount = $priceBreakdownPassengerCount > 0 ? $priceBreakdownPassengerCount : $productPassengerCount;

        return array_merge($selectedFlightContext, [
            'channels' => array_values(array_unique(array_merge(
                $selectedFlightContext['channels'] ?? [],
                array_map('strtoupper', $channels)
            ))),
            'airline_ids' => !empty($airlineIatas)
                ? $this->airlineIdsFromIatas($airlineIatas, $airlineIataToId)
                : ($selectedFlightContext['airline_ids'] ?? []),
            'segment_count' => $segmentCount > 0 ? $segmentCount : ($selectedFlightContext['segment_count'] ?? 1),
            'passenger_count' => $passengerCount > 0 ? $passengerCount : ($selectedFlightContext['passenger_count'] ?? 1),
        ]);
    }

    private function mergeOneApiPriceContext(array $pricedItineraries, array $context, array $airlineIataToId): array
    {
        $airlineIatas = [];
        $segmentCount = 0;
        $firstDepartureAt = null;
        $lastArrivalAt = null;
        $passengerCount = 0;

        foreach ($this->normalizeList($pricedItineraries) as $pricedItinerary) {
            if (!is_array($pricedItinerary)) {
                continue;
            }

            $originDestinationOptions = data_get($pricedItinerary, 'AirItinerary.OriginDestinationOptions.OriginDestinationOption', []);

            foreach ($this->normalizeList($originDestinationOptions) as $originDestinationOption) {
                if (!is_array($originDestinationOption)) {
                    continue;
                }

                foreach ($this->normalizeList($originDestinationOption['FlightSegment'] ?? []) as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }

                    $segmentCount++;
                    $attributes = $segment['@attributes'] ?? [];
                    $flightNumber = (string) ($attributes['FlightNumber'] ?? '');
                    $marketingCode = strtoupper(substr($flightNumber, 0, 2));
                    $operatingCode = strtoupper((string) data_get($segment, 'OperatingAirline.@attributes.Code', ''));

                    if ($marketingCode !== '') {
                        $airlineIatas[] = $marketingCode;
                    }
                    if ($operatingCode !== '') {
                        $airlineIatas[] = $operatingCode;
                    }

                    $firstDepartureAt ??= $attributes['DepartureDateTime'] ?? null;
                    $lastArrivalAt = $attributes['ArrivalDateTime'] ?? $lastArrivalAt;
                }
            }

            $passengerCount += $this->getOneApiPassengerCount($pricedItinerary, 0);
        }

        return array_merge($context, [
            'airline_ids' => !empty($airlineIatas)
                ? $this->airlineIdsFromIatas($airlineIatas, $airlineIataToId)
                : ($context['airline_ids'] ?? []),
            'segment_count' => $segmentCount > 0 ? $segmentCount : ($context['segment_count'] ?? 1),
            'passenger_count' => $passengerCount > 0 ? $passengerCount : ($context['passenger_count'] ?? 1),
            'travel_departure_date' => $firstDepartureAt
                ? Carbon::parse($firstDepartureAt)->toDateString()
                : ($context['travel_departure_date'] ?? null),
            'travel_arrival_date' => $lastArrivalAt
                ? Carbon::parse($lastArrivalAt)->toDateString()
                : ($context['travel_arrival_date'] ?? null),
        ]);
    }

    private function resolveBestSegmentMargin($margins, array $context): ?SegmentMargin
    {
        $best = null;
        $bestScore = -1;

        foreach ($margins as $margin) {
            if (!$this->segmentMarginMatches($margin, $context)) {
                continue;
            }

            $score = (!empty($margin->airline_ids) || !is_null($margin->airline_id)) ? 10 : 0;
            if ($score > $bestScore || ($score === $bestScore && (int) $margin->id > (int) data_get($best, 'id', 0))) {
                $best = $margin;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function segmentMarginMatches(SegmentMargin $margin, array $context): bool
    {
        $marginChannel = strtoupper((string) ($margin->sale_channel ?? ''));
        if ($marginChannel !== '' && !in_array($marginChannel, $context['channels'] ?? [], true)) {
            return false;
        }

        $disabledAirlineIds = array_values(array_unique(array_filter(array_map(
            'intval',
            array_merge(
                is_array($margin->disabled_airline_ids) ? $margin->disabled_airline_ids : [],
                is_array($margin->airline_ids) ? $margin->airline_ids : [],
                !is_null($margin->airline_id) ? [(int) $margin->airline_id] : []
            )
        ))));

        return empty($disabledAirlineIds) || empty(array_intersect($disabledAirlineIds, $context['airline_ids'] ?? []));
    }

    private function resolveBestPromotion($promotions, array $context): ?Promotion
    {
        $best = null;
        $bestScore = -1;

        foreach ($promotions as $promotion) {
            if (!$this->promotionMatches($promotion, $context)) {
                continue;
            }

            $score = $this->promotionSpecificityScore($promotion);
            if ($score > $bestScore || ($score === $bestScore && (int) $promotion->id > (int) data_get($best, 'id', 0))) {
                $best = $promotion;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function promotionMatches(Promotion $promotion, array $context): bool
    {
        $promotionChannel = strtoupper((string) ($promotion->sale_channel ?? ''));
        if ($promotionChannel !== '' && !in_array($promotionChannel, $context['channels'] ?? [], true)) {
            return false;
        }

        if (!is_null($promotion->airline_id) && !in_array((int) $promotion->airline_id, $context['airline_ids'] ?? [], true)) {
            return false;
        }

        $reservationType = strtoupper((string) ($promotion->reservation_type ?? 'ALL-SECTORS'));
        if ($reservationType !== 'ALL-SECTORS' && $reservationType !== strtoupper((string) ($context['reservation_type'] ?? ''))) {
            return false;
        }

        if (!$this->isDateWithinRange($context['travel_departure_date'] ?? null, $promotion->travel_start_date ?? null, $promotion->travel_end_date ?? null)) {
            return false;
        }

        if (!$this->isDateWithinRange($context['travel_arrival_date'] ?? null, $promotion->travel_start_date ?? null, $promotion->travel_end_date ?? null)) {
            return false;
        }

        return $this->isDateWithinRange(
            $context['ticketing_date'] ?? null,
            $promotion->ticketing_start_date ?? null,
            $promotion->ticketing_end_date ?? null
        );
    }

    private function promotionSpecificityScore(Promotion $promotion): int
    {
        $score = 0;

        if (!is_null($promotion->airline_id)) {
            $score += 10;
        }
        if (!empty($promotion->travel_start_date) || !empty($promotion->travel_end_date)) {
            $score += 8;
        }
        if (!empty($promotion->ticketing_start_date) || !empty($promotion->ticketing_end_date)) {
            $score += 7;
        }
        if (strtoupper((string) ($promotion->reservation_type ?? 'ALL-SECTORS')) !== 'ALL-SECTORS') {
            $score += 5;
        }
        if (!empty($promotion->sale_channel)) {
            $score += 3;
        }

        return $score;
    }

    private function calculateSegmentMarginDelta(SegmentMargin $margin, int $segmentCount, int $passengerCount): float
    {
        $amount = (float) ($margin->margin_value ?? 0) * max($segmentCount, 1) * max($passengerCount, 1);
        return strtolower((string) ($margin->margin_type ?? 'markup')) === 'discount'
            ? -abs($amount)
            : abs($amount);
    }

    private function calculatePromotionDelta(Promotion $promotion, float $basePrice, int $passengerCount): float
    {
        $commissionValue = (float) ($promotion->commission_value ?? 0);
        $commissionType = strtolower((string) ($promotion->commission_type ?? 'amount'));
        $perPassengerAmount = $commissionType === 'percentage'
            ? ($basePrice * $commissionValue) / 100
            : $commissionValue;
        $amount = $perPassengerAmount * max($passengerCount, 1);

        return strtolower((string) ($promotion->price_option ?? 'markup')) === 'discount'
            ? -abs($amount)
            : abs($amount);
    }

    private function applyTravelportPriceDelta(array &$offer, float $delta, int $passengerCount, array $adjustments): void
    {
        $originalBase = data_get($offer, 'Price.Base');
        $originalTotal = data_get($offer, 'Price.TotalPrice');

        data_set($offer, 'Price.OriginalBase', $originalBase);
        data_set($offer, 'Price.OriginalTotalPrice', $originalTotal);

        foreach (['Price.Base', 'Price.TotalPrice'] as $field) {
            $value = data_get($offer, $field);
            if (is_numeric($value)) {
                data_set($offer, $field, round(((float) $value) + $delta, 2));
            }
        }

        $perPassengerDelta = $delta / max($passengerCount, 1);
        $breakdowns = $this->normalizeList(data_get($offer, 'Price.PriceBreakdown', []));
        foreach ($breakdowns as &$breakdown) {
            data_set($breakdown, 'Amount.OriginalBase', data_get($breakdown, 'Amount.Base'));
            data_set($breakdown, 'Amount.OriginalTotal', data_get($breakdown, 'Amount.Total'));

            foreach (['Amount.Base', 'Amount.Total'] as $field) {
                $value = data_get($breakdown, $field);
                if (is_numeric($value)) {
                    data_set($breakdown, $field, round(((float) $value) + $perPassengerDelta, 2));
                }
            }
            data_set($breakdown, 'Amount.AppliedPricingAdjustment', round($perPassengerDelta, 2));
        }
        unset($breakdown);

        data_set($offer, 'Price.PriceBreakdown', $this->restoreListShape(
            $breakdowns,
            data_get($offer, 'Price.PriceBreakdown', [])
        ));
        data_set($offer, 'Price.AppliedPricingAdjustment', round($delta, 2));
        data_set($offer, 'Price.PricingAdjustments', $adjustments);
    }

    private function applyOneApiPromotionDelta(array &$pricedItinerary, float $delta, int $passengerCount, array $promotion): void
    {
        $pricingPath = 'AirItineraryPricingInfo';
        $itinTotalFarePath = $pricingPath . '.ItinTotalFare';

        foreach ([
            'BaseFare',
            'TotalFare',
            'TotalFareWithCCFee',
        ] as $fareField) {
            $amountPath = $itinTotalFarePath . '.' . $fareField . '.@attributes.Amount';
            $amount = data_get($pricedItinerary, $amountPath);

            if (!is_numeric($amount)) {
                continue;
            }

            data_set($pricedItinerary, $itinTotalFarePath . '.' . $fareField . '.@attributes.OriginalAmount', $amount);
            data_set($pricedItinerary, $amountPath, $this->formatAmount(((float) $amount) + $delta));
        }

        $perPassengerDelta = $delta / max($passengerCount, 1);
        $breakdownsPath = $pricingPath . '.PTC_FareBreakdowns.PTC_FareBreakdown';
        $originalBreakdowns = data_get($pricedItinerary, $breakdownsPath, []);
        $breakdowns = $this->normalizeList($originalBreakdowns);

        foreach ($breakdowns as &$breakdown) {
            $quantity = max((int) data_get($breakdown, 'PassengerTypeQuantity.@attributes.Quantity', 1), 1);
            $breakdownDelta = $perPassengerDelta * $quantity;

            foreach (['BaseFare', 'TotalFare'] as $fareField) {
                $amountPath = 'PassengerFare.' . $fareField . '.@attributes.Amount';
                $amount = data_get($breakdown, $amountPath);

                if (!is_numeric($amount)) {
                    continue;
                }

                data_set($breakdown, 'PassengerFare.' . $fareField . '.@attributes.OriginalAmount', $amount);
                data_set($breakdown, $amountPath, $this->formatAmount(((float) $amount) + $breakdownDelta));
            }

            data_set($breakdown, 'PassengerFare.PricingAdjustments.promotion', $promotion);
            data_set($breakdown, 'PassengerFare.AppliedPricingAdjustment', $this->formatAmount($breakdownDelta));
        }
        unset($breakdown);

        data_set($pricedItinerary, $breakdownsPath, $this->restoreListShape($breakdowns, $originalBreakdowns));
        data_set($pricedItinerary, $pricingPath . '.PricingAdjustments.promotion', $promotion);
        data_set($pricedItinerary, $pricingPath . '.AppliedPricingAdjustment', $this->formatAmount($delta));
    }

    private function getOneApiPassengerCount(array $pricedItinerary, int $fallback = 1): int
    {
        $passengerCount = 0;
        $breakdowns = data_get($pricedItinerary, 'AirItineraryPricingInfo.PTC_FareBreakdowns.PTC_FareBreakdown', []);

        foreach ($this->normalizeList($breakdowns) as $breakdown) {
            $passengerCount += (int) data_get($breakdown, 'PassengerTypeQuantity.@attributes.Quantity', 0);
        }

        if ($passengerCount > 0) {
            return $passengerCount;
        }

        return $fallback > 0 ? $fallback : 0;
    }

    private function getSelectedPassengerCount(array $selectedFareItems): int
    {
        $passengerCount = 0;

        foreach ($selectedFareItems as $fare) {
            foreach (($fare['passenger_fares'] ?? []) as $passengerFare) {
                $passengerCount += (int) ($passengerFare['total_passenger'] ?? 0);
            }
        }

        return max($passengerCount, 1);
    }

    private function airlineIdsFromIatas(array $iatas, array $airlineIataToId): array
    {
        $ids = [];
        foreach (array_unique(array_filter(array_map(fn ($iata) => strtoupper((string) $iata), $iatas))) as $iata) {
            if (isset($airlineIataToId[$iata])) {
                $ids[] = $airlineIataToId[$iata];
            }
        }

        return array_values(array_unique($ids));
    }

    private function isCodeShare(array $segments, string $marketingIata): bool
    {
        $marketingIata = strtoupper($marketingIata);
        if ($marketingIata === '') {
            return false;
        }

        foreach ($segments as $segment) {
            $operatingIata = strtoupper((string) data_get($segment, 'operating_carrier.iata', ''));
            if ($operatingIata !== '' && $operatingIata !== $marketingIata) {
                return true;
            }
        }

        return false;
    }

    private function detectReservationType(string $originCountry, string $destinationCountry, bool $isInterline, bool $isCodeShare): string
    {
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

        if ($originCountry !== '') {
            return 'SOTO';
        }

        return 'ALL-SECTORS';
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

    private function normalizeList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    private function restoreListShape(array $items, $original)
    {
        if (is_array($original) && !array_is_list($original)) {
            return $items[0] ?? [];
        }

        return $items;
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
