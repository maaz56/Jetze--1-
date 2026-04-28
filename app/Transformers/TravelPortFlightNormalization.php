<?php
namespace App\Transformers;

use App\Enums\PenaltyTypes;
use App\Models\Airline;
use App\Models\Airport;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;
use Log;
use Str;

class TravelPortFlightNormalization
{

    protected $defaultBaggage = [
        'freeAllowance' => 0,
        'unitQualifier' => 'PC(s)',
        'quantityCode' => 'N',
    ];
    protected $passengerArr = ['ADT', 'CH', 'INF'];
    protected $itinCount;
    protected $sessionFile = [];
    protected $contentSource;
    protected $CatalogProductOfferingsIdentifier;


    public function fromTravelPort($searchResponse, $params)
    {   
        if($searchResponse === []) {
            Log::error('Error response received from TravelPort', [
                'searchId' => $this->sessionFile['searchId'] ?? null,
                'response' => $searchResponse,
            ]);
            return [];
        }


        $this->itinCount = $params['flight_type'] == 'one-way' ? 1 : 2;
        $this->itinCount = $params['flight_type'] == 'multi-city' ? count($params['trips']) : $this->itinCount;
        $offerResponse = $searchResponse['CatalogProductOfferingsResponse'] ?? null;
        if (empty($offerResponse) || empty($offerResponse['CatalogProductOfferings'])) {
            Log::warning('Missing CatalogProductOfferings in TravelPort response', [
                'searchId' => $this->sessionFile['searchId'] ?? null,
            ]);
            return [];
        }
        $this->sessionFile['productOfferingIdentifier'] = $offerResponse['CatalogProductOfferings']['Identifier']['value'] ?? null;
        $references = $offerResponse['ReferenceList'] ?? [];

        $singleFlights = [];
        $productList = [];
        $tncList = [];
        $brandList = [];
        foreach ($references as $reference) {
            if ($reference['@type'] == 'ReferenceListFlight') {
                $singleFlights = $this->processFlightInformation($reference['Flight']); // List of all flight segments
            } elseif ($reference['@type'] == 'ReferenceListProduct') {
                $productList = $reference['Product'];
            } elseif ($reference['@type'] == 'ReferenceListTermsAndConditions') {
                $tncList = $this->processTNCList($reference['TermsAndConditions']);
            } elseif ($reference['@type'] == 'ReferenceListBrand') {
                if (!empty($reference['Brand'])) {
                    $brandList = $this->processBrandInfo($reference['Brand']);
                }
            }
        }
        $flightDetails = $this->processFlightSegments($singleFlights, $productList);
        $catalogOfferings = $offerResponse['CatalogProductOfferings']['CatalogProductOffering'] ?? [];
        if (empty($catalogOfferings)) {
            Log::warning('Missing CatalogProductOffering list in TravelPort response', [
                'searchId' => $this->sessionFile['searchId'] ?? null,
            ]);
            return [];
        }
        $results = $this->getResults($catalogOfferings, $flightDetails, $tncList, $brandList);
        // $output['resultCount'] = count($results);
        
        return $results;
    }

    private function processFlightInformation($stops)
    {
        $flightInformation = [];
        foreach ($stops as $stop) {
            $id = $stop['id'];
            $departureInfo = $stop['Departure'];
            $arrivalInfo = $stop['Arrival'];
            $productDateTime = [
                'dateOfDeparture' => Carbon::createFromFormat('Y-m-d', $departureInfo['date'])->format('dmy'),
                'timeOfDeparture' => Carbon::createFromFormat('H:i:s', $departureInfo['time'])->format('Hi'),
                'dateOfArrival' => Carbon::createFromFormat('Y-m-d', $arrivalInfo['date'])->format('dmy'),
                'timeOfArrival' => Carbon::createFromFormat('H:i:s', $arrivalInfo['time'])->format('Hi'),
            ];
            $location = [
                [
                    'locationId' => $departureInfo['location'],
                    'terminal' => $departureInfo['terminal'] ?? '',
                ],
                [
                    'locationId' => $arrivalInfo['location'],
                    'terminal' => $arrivalInfo['terminal'] ?? '',
                ],
            ];
            $companyId = [
                'marketingCarrier' => $stop['carrier'],
                'operatingCarrier' => $stop['operatingCarrier'] ?? $stop['carrier'],
            ];
            $flightNumber = $stop['number'];
            $productDetail = [
                'equipmentType' => $stop['equipment'],
            ];
            $addProductDetail = [
                'cabinClass' => '',
                'availableSeats' => '',
            ];
            $flightInformation = [
                'segmentRef' => $id,
                'productDateTime' => $productDateTime,
                'duration' => $stop['duration'],
                'location' => $location,
                'companyId' => $companyId,
                'flightOrtrainNumber' => $flightNumber,
                'productDetail' => $productDetail,
                'addProductDetail' => $addProductDetail,
                'AvailabilitySourceCode' => $stop['AvailabilitySourceCode'] ?? '',
            ];
            $flightInformationRefs[$id] = $flightInformation;
        }

        return $flightInformationRefs;
    }

    private function processFlightSegments($singleFlights, $productList)
    {
        $flightDetails = [];
        foreach ($productList as $segment) {
           
            $id = $segment['id'];
            $fligt_total_duration = $segment['totalDuration'];
            $flightDetails[$id] = $flightDetails[$id] ?? [];
            $segmentCabinRbdMap = [];
            $defaultRbd = null;
            $defaultCabin = null;
            $passengerFlights = $segment['PassengerFlight'] ?? [];
            $passengerFlights = is_array($passengerFlights) ? $passengerFlights : [$passengerFlights];
            foreach ($passengerFlights as $passengerFlight) {
                $flightProducts = $passengerFlight['FlightProduct'] ?? [];
                $flightProducts = is_array($flightProducts) ? $flightProducts : [$flightProducts];
                foreach ($flightProducts as $flightProduct) {
                    $segmentRefKey =
                        $flightProduct['FlightSegmentRef'] ??
                        $flightProduct['flightSegmentRef'] ??
                        $flightProduct['segmentRef'] ??
                        $passengerFlight['FlightSegmentRef'] ??
                        $passengerFlight['flightSegmentRef'] ??
                        null;
                    $rbd = $flightProduct['classOfService'] ?? null;
                    $cabin = $flightProduct['cabin'] ?? null;

                    if ($segmentRefKey) {
                        $segmentCabinRbdMap[$segmentRefKey] = [
                            'rbd' => $rbd,
                            'cabin' => $cabin,
                        ];
                    }
                    $defaultRbd = $defaultRbd ?? $rbd;
                    $defaultCabin = $defaultCabin ?? $cabin;
                }
            }
            $fallbackRbd = $defaultRbd ?? ($segment['PassengerFlight'][0]['FlightProduct'][0]['classOfService'] ?? null);
            $fallbackCabin = $defaultCabin ?? ($segment['PassengerFlight'][0]['FlightProduct'][0]['cabin'] ?? null);
            $rbds = [];
            $segmentRefs = '';
            $segmentRoutesArr = [];
            foreach ($segment['FlightSegment'] as $stop) {
                $segmentRef = $stop['Flight']['FlightRef'];
                $segmentRefs .= $segmentRef;
                $flightInfo = $singleFlights[$segmentRef];
                $segmentCabin = $segmentCabinRbdMap[$segmentRef]['cabin'] ?? $fallbackCabin ?? '';
                $segmentRbd = $segmentCabinRbdMap[$segmentRef]['rbd'] ?? $fallbackRbd ?? '';
                $flightInfo['addProductDetail']['cabinClass'] = $segmentCabin;
                $flightInfo['addProductDetail']['rbdCode'] = $segmentRbd;
                $flightDetails[$id]['flightDetails'][] = [
                    'flightInformation' => $flightInfo,
                ];  
                $rbds[] = $segmentRbd;
                $segmentRoutesArr[] = $flightInfo['location'][0]['locationId'] . '-' . $flightInfo['location'][1]['locationId'];
                $segmentCabinRbdMap[$segmentRef] = [
                    'rbd' => $segmentRbd,
                    'cabin' => $segmentCabin,
                ];
            }

            $flightDetails[$id]['segmentRoutes'] = implode('|', $segmentRoutesArr);
            $flightDetails[$id]['segmentRefs'] = $segmentRefs;
            $flightDetails[$id]['rbds'] = $rbds;
            $flightDetails[$id]['cabin'] = $fallbackCabin;
            $flightDetails[$id]['segmentCabinRbd'] = $segmentCabinRbdMap;
            $flightDetails[$id]['flight_total_duration'] = $fligt_total_duration;

        }
        // Log::info('processFlightSegemnt'. json_encode($flightDetails));
        return $flightDetails;
    }

    private function processBrandInfo($brandInfo)
    {
        $brandList = [];
        if (empty($brandInfo) || !is_array($brandInfo)) {
            return $brandList;
        }
        foreach ($brandInfo as $brandDetails) {
            $id = $brandDetails['id'];
            $bundleName = $brandDetails['name'];
            $includedServices = [];
            if (isset($brandDetails['BrandAttribute'])) {
                foreach ($brandDetails['BrandAttribute'] as $brandAttribute) {
                    $includedServices[] = sprintf('%s %s', $brandAttribute['classification'], $brandAttribute['inclusion']);
                }
            }
            if (isset($brandDetails['AdditionalBrandAttribute'])) {
                foreach ($brandDetails['AdditionalBrandAttribute'] as $brandAttribute) {
                    $includedServices[] = sprintf('%s %s', $brandAttribute['Classification'], $brandAttribute['Inclusion']);
                }
            }
            $brandList[$id] = [
                'bundledServiceName' => $bundleName,
                'tier' => isset($brandDetails['tier']) ? $brandDetails['tier'] : null,
                'fareFamilyName' => $bundleName,
                'includedServices' => $includedServices,
                'totalPaxBundledFee' => 0,
                'description' => $bundleName,
            ];
        }

        return $brandList;
    }

    private function processTNCList($tncArr)
    {
        $tncList = [];
        foreach ($tncArr as $tncDetails) {
            $id = $tncDetails['id'];
            $tncList[$id] = [];
            if (isset($tncDetails['BaggageAllowance'])) {
                $tncList[$id]['baggage'] = $this->processTncBaggageInfo($tncDetails['BaggageAllowance']);
            }
            if (isset($tncDetails['Penalties'])) {
                [$tncList[$id]['fareRules'], $tncList[$id]['refundable']] = $this->processTncPenaltyInfo($tncDetails['Penalties']);
            }
        }

        return $tncList;
    }

    private function processTncBaggageInfo($baggageDetails)
    {
        $baggageArr = [];
        foreach ($baggageDetails as $baggageDetail) {
            if ($baggageDetail['@type'] == 'BaggageAllowanceDetail' && in_array($baggageDetail['baggageType'], ['FirstCheckedBag', 'SecondCheckedBag'])) {
                $baggageItems = $baggageDetail['BaggageItem'] ?? [];
                $paxTypes = $baggageDetail['passengerTypeCodes'];
                $baggage = $this->defaultBaggage;
                foreach ($baggageItems as $baggageItem) {
                    if (!is_null($baggageItem)) {
                        if ($baggageItem['@type'] == 'BaggageItem') {
                            if (
                                isset($baggageItem['soldByWeightInd'])
                                && $baggageItem['soldByWeightInd']
                                && isset($baggageItem['Measurement'])
                            ) {
                                foreach ($baggageItem['Measurement'] as $measurement) {
                                    if ($measurement['measurementType'] == 'Weight') {
                                        $baggage['freeAllowance'] = $measurement['value'];
                                        $baggage['unitQualifier'] = $measurement['unit'] == 'Kilograms' ? 'Kg(s)' : 'lb(s)';

                                        continue;
                                    }
                                }
                            } elseif (
                                isset($baggageItem['quantity'])
                                && (!isset($baggageItem['BaggageFee'])
                                    || (isset($baggageItem['BaggageFee']) && $baggageItem['BaggageFee']['value'] == 0))
                            ) {
                                $baggage['freeAllowance'] = $baggageDetail['baggageType'] == 'SecondCheckedBag' ? 2 : 1;
                                $baggage['unitQualifier'] = 'PC(s)';
                                $baggage['quantityCode'] = 'N';

                                continue;
                            }
                        }
                    }
                }
                foreach ($paxTypes as $paxType) {
                    $paxType = in_array($paxType, ['CHD', 'CNN']) ? 'CH' : $paxType;
                    $baggageArr[$paxType] = $baggage;
                }
            }
        }

        return $baggageArr;
    }

    private function processTncPenaltyInfo($penaltyDetails)
    {
        $refundable = false;
        $fareRulesString = '';
        foreach ($penaltyDetails as $penaltyInfo) {
            $paxTypes = $penaltyInfo['PassengerTypeCodes'];
            $changeRules = [];
            if (isset($penaltyInfo['Change'])) {
                foreach ($penaltyInfo['Change'] as $changeInfo) {
                    if ($changeInfo['@type'] == 'ChangeNotPermitted') {
                        $changeRules[] = 'Change Not Permitted';
                    } else {
                        $penaltyTypes = $changeInfo['penaltyTypes'] ?? [];
                        $penaltyTypeArr = [];
                        foreach ($penaltyTypes as $penaltyType) {
                            $penaltyTypeArr[] = PenaltyTypes::type($penaltyType);
                        }
                        $penaltyTextArr[] = implode(' ', $penaltyTypeArr);
                        foreach ($changeInfo['Penalty'] as $penaltyAmount) {
                            if ($penaltyAmount['@type'] == 'PenaltyAmount') {
                                $currency = $penaltyAmount['Amount']['code']?? "PKR";
                                $amount = $penaltyAmount['Amount']['value'];
                                if ($amount > 0) {
                                    $refundable = true;
                                }
                                $penaltyTextArr[] = sprintf('%s %s', $currency, $amount);
                            } elseif ($penaltyAmount['@type'] == 'PenaltyPercent') {
                                $penaltyTextArr[] = sprintf('Minimum %s%% of fare', $penaltyAmount['Percent']);
                            }
                        }
                        $changeRules[] = implode(' | ', $penaltyTextArr);
                    }
                }
            }
            $refundRules = [];
            if (isset($penaltyInfo['Cancel'])) {
                foreach ($penaltyInfo['Cancel'] as $cancelInfo) {
                    $penaltyTextArr = [];
                    if ($cancelInfo['@type'] == 'CancelNotPermitted') {
                        $refundRules[] = 'Cancel Not Permitted';
                    } else {
                        $penaltyTypes = $changeInfo['penaltyTypes'] ?? [];
                        $penaltyTypeArr = [];
                        foreach ($penaltyTypes as $penaltyType) {
                            $penaltyTypeArr[] = PenaltyTypes::type($penaltyType);
                        }
                        $penaltyTextArr[] = implode(' ', $penaltyTypeArr);
                        if (isset($changeInfo['Penalty'])) {
                            foreach ($changeInfo['Penalty'] as $penaltyAmount) {
                                if ($penaltyAmount['@type'] == 'PenaltyAmount') {
                                    $penaltyTextArr[] = sprintf('%s %s', $penaltyAmount['Amount']['code']?? "PKR", $penaltyAmount['Amount']['value']);
                                } elseif ($penaltyAmount['@type'] == 'PenaltyPercent') {
                                    $penaltyTextArr[] = sprintf('Minimum %s%% of fare', $penaltyAmount['Percent']);
                                }
                            }
                        }
                        $refundRules[] = implode(' | ', $penaltyTextArr);
                    }
                }
            }
            foreach ($paxTypes as $paxType) {
                $paxWiseFareRules = sprintf('<h3>%s</h3><p><b>Reissue</b></p><p>%s</p><p><b>Refund</b></p><p>%s</p>', $paxType, implode('</p><p>', $changeRules), implode('</p><p>', $refundRules));
                $fareRulesString .= $paxWiseFareRules;
            }
        }

        return [$fareRulesString, $refundable];
    }

    private function getResults($resultFeeds, $flightDetails, $tncList, $brandList)
    {
        $segmentBaggage = [];
        foreach ($this->passengerArr as $paxType) {
            $segmentBaggage[$paxType] = $this->defaultBaggage;
        }
        $combinedSegments = $this->combineSegmentsByCode($resultFeeds, $flightDetails, $tncList, $brandList);
        $results = $this->groupResultsByProduct($combinedSegments);

        return $results;
    }

    private function combineSegmentsByCode($resultFeeds, $flightDetails, $tncList, $brandList)
    {
        $combinedSegments = [];
        foreach ($resultFeeds as $key => $feed) {
        
            $sequence = $feed['sequence'] - 1;
            $offerIdentifier = $feed['id'];
            foreach ($feed['ProductBrandOptions'] as $productBrandOptions) {
                foreach ($productBrandOptions['ProductBrandOffering'] as $productBrandOffering) {
                    $combinabilityCodes = $productBrandOffering['CombinabilityCode'];
                    $this->contentSource = $productBrandOffering['ContentSource'];
                    $fareSummary = $this->fareSummary($productBrandOffering['BestCombinablePrice']);
                    $tncRef = $productBrandOffering['TermsAndConditions']['termsAndConditionsRef'];
                    $tncDetails = $tncList[$tncRef] ?? [];
                    $fareSummary['refundable'] = $tncDetails['refundable'] ?? false;
                    $baggageInfo = $tncDetails['baggage'] ?? [];
                    foreach ($baggageInfo as $paxType => $baggage) {
                        $segmentBaggage[$paxType] = $baggage;
                    }
                    $productObj = $productBrandOffering['Product'][0];
                    if ($productObj['@type'] != 'ProductID') {
                        continue;
                    }
                    $segmentRef = $productObj['productRef'];
                    $segment = $flightDetails[$segmentRef];
                    $marketingCarrierCode = $segment['flightDetails'][0]['flightInformation']['companyId']['marketingCarrier'] ?? null;
                   
                    $airlineName = Airline::where('iata_code', $marketingCarrierCode)->value('name') ?? $marketingCarrierCode ?? 'Unknown Airline';
                    
                   
                    $brandRef = $productBrandOffering['Brand']['BrandRef'] ?? null;
                    if ($brandRef && isset($brandList[$brandRef])) {
                        $bundleInfo = $brandList[$brandRef];
                    } else {
                        // Some offers don't include a Brand block. Keep them instead of skipping.
                        $bundleInfo = [
                            'bundledServiceName' => $brandRef ?? 'Economy',
                            'tier' => null,
                            'fareFamilyName' => $brandRef ?? 'Economy',
                            'includedServices' => [],
                            'totalPaxBundledFee' => 0,
                            'description' => $brandRef ?? 'Economy',
                        ];
                    }
                    $bundleInfo['segment'] = $segment['segmentRoutes'];
                    $bundleInfo['productIdentifier'] = $segmentRef;
                    $bundleInfo['offerIdentifier'] = $offerIdentifier;
                    $bundleInfo['bundleIdentifier'] = $productBrandOffering['Identifier']['value']?? '';
                    foreach ($combinabilityCodes as $combinableCode) {
                        if (!isset($combinedSegments[$combinableCode])) {
                            $combinedSegments[$combinableCode] = [
                               
                                'flightCombination' => [],
                                'fareSummary' => $fareSummary,
                                'baggage' => [],
                                'bundleServices' => [
                                    'bundleOptions' => [],
                                    'combinableCode' => $combinableCode,
                                    'baggage' => [],
                                    'fareSummary' => $fareSummary,
                                    'rbds' => [],
                                    'segmentCabinRbd' => [],
                                ],
                                'productRef' => '',
                            ];
                        }
                        $combinedSegments[$combinableCode]['flightCombination'][$sequence] = [
                            'CatalogProductOfferingsIdentifier' => $feed['Identifier']['value'] ?? null,
                            'total_flight_duration' => $segment['flight_total_duration'],
                            'contentSource' => $this->contentSource,
                            'flightDetails' => $segment['flightDetails'],
                            

                        ];
                       // $combinedSegments[$combinableCode]['totalDuration'] = $flightDetails[$segment]['flight_total_duration'];
                        $combinedSegments[$combinableCode]['baggage'][$sequence] = $segmentBaggage;
                        $combinedSegments[$combinableCode]['productRef'] .= $segment['segmentRefs'];
                        $combinedSegments[$combinableCode]['bundleServices']['baggage'][$sequence] = $segmentBaggage;
                        $combinedSegments[$combinableCode]['bundleServices']['bundleOptions'][$sequence] = $bundleInfo;
                        $combinedSegments[$combinableCode]['bundleServices']['rbds'][] = $segment['rbds'];
                        $combinedSegments[$combinableCode]['bundleServices']['cabin'][] = $segment['cabin'];
                        $combinedSegments[$combinableCode]['bundleServices']['segmentCabinRbd'][$sequence] = $segment['segmentCabinRbd'] ?? [];
                       
                    }
                }
            }
        }
       
    //    Log::info('combineSegmentsByCode'. json_encode($combinedSegments));
        return array_values($combinedSegments);
    }

    private function groupResultsByProduct($combinedSegments)
    {
        $combinedSegments = collect($combinedSegments)->groupBy('productRef')->toArray();
        $results = [];
        foreach ($combinedSegments as $bundleWiseResult) {
            $bundleWiseResult = collect($bundleWiseResult)->sortBy('bundleServices.fareSummary.totalFareAmount');
            $bundleServices = $bundleWiseResult->pluck('bundleServices');
            $cheapestBundle = $bundleWiseResult->first();
            if (count($cheapestBundle['flightCombination']) != $this->itinCount) {
                continue;
            }
            $fareSummary = $cheapestBundle['fareSummary'];
            $baseFee = $fareSummary['totalFareAmount'];
            $count = 0;
            $bundleServices = collect($bundleServices)->map(function ($item) use ($baseFee, &$count) {
                $item['totalPaxBundledFee'] = $item['fareSummary']['totalFareAmount'] - $baseFee;
                $item['bundleServicesId'] = $count;
                $count++;

                return $item;
            })->values()->toArray();

            $results[] = [
                'flightCombination' => $cheapestBundle['flightCombination'],
                'productOfferingIdentifier' =>$this->sessionFile['productOfferingIdentifier'],          
                'contentSource' => $this->contentSource,
                'fareSummary' => $fareSummary,
                'baggage' => $cheapestBundle['baggage'],
                'bundleServices' => $bundleServices,
            ];
        }

        return $results;
    }
    public function fareSummary($price): array
    {
        $fareSummary = [];
        $fareSummary['totalBaseFareAmount'] = $price['Base'];
        $fareSummary['totalTaxAmount'] = $price['TotalTaxes'] + ($price['TotalFees'] ?? 0);
        $fareSummary['totalFeesAmount'] = $price['TotalFees'] ?? 0;
        $fareSummary['totalFareAmount'] = $price['TotalPrice'];
        $fareSummary['currency'] = $price['CurrencyCode']['value'];
        $fareSummary['breakdown'] = $this->fareBreakdown($price['PriceBreakdown']);

        return $fareSummary;
    }

    private function fareBreakDown($priceBreakDown): array
    {
        $fareBreakdown = [];

        foreach ($priceBreakDown as $breakdown) {
            $paxType = in_array($breakdown['requestedPassengerType'], ['CHD', 'CNN']) ? 'CH' : $breakdown['requestedPassengerType'];
            $fareBreakdown[$paxType] = [
                'paxCount' => $breakdown['quantity'],
                'currency' => $breakdown['Amount']['CurrencyCode']['value'],
                'baseFareAmount' => $breakdown['Amount']['Base'],
                'totalBaseFareAmount' => $breakdown['Amount']['Base'] * $breakdown['quantity'],
                'fareAmount' => $breakdown['Amount']['Total'],
                'totalFareAmount' => $breakdown['Amount']['Total'] * $breakdown['quantity'],
                'feesAmount' => ($breakdown['Amount']['Fees'] ?? 0),
                'totalFeesAmount' => $breakdown['Amount']['Fees'] ?? 0,
                'taxAmount' => ($breakdown['Amount']['Taxes']['TotalTaxes']),
                'totalTaxAmount' => $breakdown['Amount']['Taxes']['TotalTaxes'] * $breakdown['quantity'],
                // 'taxBreakdown' => $breakdown['Amount']['Taxes']['Tax'] ?? [],
            ];
        }

        return $fareBreakdown;
    }
    public function baggageDetails($baggageAllowance): array
    {
        $baggageList = [];
        $passengerTypeCodes = $baggageAllowance[0]['passengerTypeCodes'];
        foreach ($passengerTypeCodes as $passengerType) {
            $baggages = [];
            foreach ($baggageAllowance as $key => $baggage) {
                $baggages[] = [
                    'unitQualifier' => isset($baggage['BaggageItem'][0]['Measurement'][0]['unit']) ? $baggage['BaggageItem'][0]['Measurement'][0]['unit'] : 'PC(s)',
                    'value' => isset($baggage['BaggageItem'][0]['Measurement'][0]['value']) ? $baggage['BaggageItem'][0]['Measurement'][0]['value'] . ' ' . $baggage['BaggageItem'][0]['Measurement'][0]['unit'] : $baggage['BaggageItem'][0]['quantity'] . ' PC(s)',
                    'baggageType' => $baggage['baggageType'],
                    'text' => $baggage['Text'] ?? '',
                    'key' => $key,
                ];
            }
            $baggageList[$passengerType] = $baggages;
        }

        return $baggageList;
    }

    public function penaltyDetails($penalties): array
    {
        $penaltyArr = [
            'change' => [],
            'cancel' => [],
            'refundable' => false,
        ];

        foreach ($penalties as $penalty) {
            $penaltyArr['change'] = $this->processPenaltyType($penalty, 'Change', $penaltyArr['refundable']);
            $penaltyArr['cancel'] = $this->processPenaltyType($penalty, 'Cancel', $penaltyArr['refundable']);
            $penaltyArr['passengerTypeCodes'] = $penalty['PassengerTypeCodes'];
        }

        return $penaltyArr;
    }

    private function processPenaltyType($penalty, $type, &$refundable): array
    {
        $penaltyTypeArr = [];

        if (isset($penalty[$type])) {
            foreach ($penalty[$type] as $penaltyDetail) {

                if ($penaltyDetail['@type'] == $type . 'NotPermitted') {
                    $penaltyTypeArr[] = [
                        'allowed' => false,
                    ];

                    continue;
                }

                $penaltyInfo = $penaltyDetail['Penalty'][0];
                $value = $penaltyInfo['@type'] === 'PenaltyAmount' ? $penaltyInfo['Amount']['value'] : $penaltyInfo['Percent'];
                if ($type == 'Cancel') {
                    $refundable = true;
                }
                $penaltyTypeArr[] = [
                    'allowed' => true,
                    'penaltyTypes' => $penaltyDetail['penaltyTypes'],
                    'type' => $penaltyInfo['@type'] === 'PenaltyAmount' ? 'amount' : 'percent',
                    'value' => $value,
                ];
            }
        }

        return $penaltyTypeArr;
    }
}










