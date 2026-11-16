<?php

namespace App\Services;

use App\Transformers\HakimiFlightTransformer;

/**
 * Aggregates provider search results for the non-streaming search endpoint.
 *
 * AT searches are streamed by FlightController::streamAtSearch, so only the
 * Hakimi inventory is searched here.
 */
class FlightAggregationService
{
    protected $hakimiApiService;
    protected $hakimiFlightTransformer;
    protected $segmentMarginService;
    protected $promotionService;
    protected $commercialPricingService;

    public function __construct(
        HakimiApiService $hakimiApiService,
        HakimiFlightTransformer $hakimiFlightTransformer,
        SegmentMarginService $segmentMarginService,
        PromotionService $promotionService,
        CommercialPricingService $commercialPricingService
    ) {
        $this->hakimiApiService = $hakimiApiService;
        $this->hakimiFlightTransformer = $hakimiFlightTransformer;
        $this->segmentMarginService = $segmentMarginService;
        $this->promotionService = $promotionService;
        $this->commercialPricingService = $commercialPricingService;
    }

    public function getFlights($params)
    {
        $transformedFlights = [];

        if (strtoupper((string) $params['airline']) === 'HAKIMI') {
            if (($params['flight_type'] ?? 'one-way') !== 'one-way') {
                throw new \InvalidArgumentException('Hakimi test inventory currently supports one-way searches only.');
            }

            $transformedFlights = $this->hakimiFlightTransformer->transform(
                $this->hakimiApiService->searchFlights($params),
                $params,
            );
        }

        $transformedFlights = $this->promotionService->applyPromotions($transformedFlights, $params);
        $transformedFlights = $this->segmentMarginService->applySegmentMargins($transformedFlights, $params);
        $transformedFlights = $this->commercialPricingService->applyToProviderFlights($transformedFlights);

        return [
            'results' => $transformedFlights,
        ];
    }
}
