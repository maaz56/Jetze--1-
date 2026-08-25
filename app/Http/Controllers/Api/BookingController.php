<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CancelBookingJob;
use App\Mail\BookingCanceledMail;
use App\Mail\BookingConfirmedMail;
use App\Mail\BookingCreatedMail;
use App\Models\AdminBooking;
use App\Models\Baggage;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\FlightBookings;
use App\Models\FlightPassenger;
use App\Models\Passenger;
use App\Models\Segment;
use App\Models\Slice;
use App\Models\User;
use App\Services\AirSialApiService;
use App\Services\FlyDubaiApiService;
use App\Services\OneApiService;
use App\Services\PIAApiService;
use App\Services\SabreApiService;
use App\Services\AtApiService;
use App\Services\BookingPricingService;
use App\Services\PriceQuoteService;
use App\Services\ProviderBookingEventService;
use App\Transformers\AtAncillaryTransformer;

// use App\Services\SafepayService;
use App\Services\SooperApiService;
use App\Services\TravelPortService;
use App\Services\UtilityService;
use Carbon\Carbon;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Mail;

class BookingController extends Controller
{
    protected $sabreApiService;

    protected $safepayService;

    protected $sooperApiService;
    protected $airsialApiService;
    protected $piaApiService;
    protected $flydubaiApiService;
    protected $oneApiService;
    protected $travelportApiService;
    protected $utilityService;
    protected $priceQuoteService;
    protected $bookingPricingService;
    protected $providerBookingEventService;

    public function __construct(
        SabreApiService $sabreApiService,
        SooperApiService $sooperApiService,
        AirSialApiService $airsialApiService,
        PIAApiService $piaApiService,
        FlyDubaiApiService $flydubaiApiService,
        TravelPortService $travelportApiService,
        OneApiService $oneApiService,
        UtilityService $utilityService,
        PriceQuoteService $priceQuoteService,
        BookingPricingService $bookingPricingService,
        ProviderBookingEventService $providerBookingEventService,
    ) {
        $this->sabreApiService = $sabreApiService;
        // $this->safepayService = $safepayService;
        $this->sooperApiService = $sooperApiService;
        $this->travelportApiService = $travelportApiService;
        $this->airsialApiService = $airsialApiService;
        $this->piaApiService = $piaApiService;
        $this->flydubaiApiService = $flydubaiApiService;
        $this->oneApiService = $oneApiService;
        $this->utilityService = $utilityService;
        $this->priceQuoteService = $priceQuoteService;
        $this->bookingPricingService = $bookingPricingService;
        $this->providerBookingEventService = $providerBookingEventService;
    }

    public function index(Request $request)
    {
        Log::info($request->all());
        $user = Auth::user();
        Log::info($user);
        $bookingMode = null;
        if ($user && $user->role == 'agent') {
            $bookingMode = 'B2B';
        } elseif ($user && $user->role == 'customer') {
            $bookingMode = 'B2C';
        } elseif ($request->booking_mode) {
            $bookingMode = $request->booking_mode;
        }
        $query = FlightBookings::with(['pessangers', 'user.agentData'])->orderBy('id', 'desc');
        if($user->role == 'admin' && $request->user_id){
            Log::info("Admin accessing bookings for user_id: " . $request->user_id);
            $query->where('agent_id', $request->user_id);
        }
        // Apply booking mode filter only if user is agent/customer or if specified in request
        if ($request->userRole == "agent" || $request->userRole == "customer" || $bookingMode) {
            $query->where('booking_mode', $bookingMode);
        }

        // Check user role
        if ($request->userRole == "agent" || $request->userRole == "customer") {
            $query->where('agent_id', $request->userId);
        }

        // Filter by booking status
        if ($request->bookingFilter && $request->bookingFilter !== "all") {
            $query->where('status', $request->bookingFilter);
        }

        if ($request->searchQuery) {
            $search = strtolower($request->searchQuery);

            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(itinerary_ref) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(id) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(main_email) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(agency_email) LIKE ?', ["%{$search}%"]);
            });
        }
        if (!empty($request->dateRange['start']) && !empty($request->dateRange['end'])) {
            $query->whereBetween('created_at', [
                $request->dateRange['start'] . ' 00:00:00',
                $request->dateRange['end'] . ' 23:59:59'
            ]);
        }


        // Get paginated bookings
        $bookings = $query->get();
        Log::info($bookings);
        // Count different statuses
        $baseQuery = FlightBookings::query();
        if ($request->userRole == "agent" || $request->userRole == "customer" || $bookingMode) {
            $baseQuery->where('booking_mode', $bookingMode);
        }
        if ($request->userRole == "agent" || $request->userRole == "customer") {
            $baseQuery->where('agent_id', $request->userId);
        }

        // Counting different statuses
        $totalCount = (clone $baseQuery)->count();
        $totalTicketed = (clone $baseQuery)->where('status', 'ticketed')->count();
        $totalCanceled = (clone $baseQuery)->where('status', 'canceled')->count();
        $totalBooked = (clone $baseQuery)->where('status', 'booked')->count();
        $totalVoided = (clone $baseQuery)->where('status', 'voided')->count();

        // Extract itinerary references
        $bookingsWithItineraryReferences = $bookings->map(function ($booking) {
            $pnrResponse = json_decode($booking->pnr_response, true);
            $itineraryReference = data_get($pnrResponse, 'CreatePassengerNameRecordRS.ItineraryRef.ID');
            $booking->itinerary_reference = $itineraryReference;
            return $booking;
        });

        return response()->json([
            'bookings' => $bookingsWithItineraryReferences,
            'total_count' => $totalCount,
            'total_canceled' => $totalCanceled,
            'total_ticketed' => $totalTicketed,
            'total_booked' => $totalBooked,
            'total_voided' => $totalVoided,
        ]);
    }



    public function getBookingDetails(Request $request)
    {


        //$bookingDetail = FlightBookings::with('pessangers')->where('id', $request->bookingId)->get();
        $bookingDetail = FlightBookings::with('pessangers', 'bookingInvoice', 'priceSnapshot')
            ->where('id', $request->bookingId)
            ->orWhere('flight_id', $request->bookingId)
            ->get(); // Assuming flight_id is in the request

        //Log::info($bookingDetail);

        return $bookingDetail;
    }

    public function getCustomerBookingDetails(Request $request)
    {


        if ($request->bookingSource == 1) {
            $bookingDetail = FlightBookings::with('pessangers', 'priceSnapshot')
                ->where('id', $request->bookingId)
                ->get(); // Assuming flight_id is in the request
        } else {
            $bookingDetail = FlightBookings::with('pessangers', 'priceSnapshot')
                ->where('id', $request->bookingId)
                ->get(); // Assuming flight_id is in the request
        }


        return $bookingDetail;
    }

    public function initiatePayment(Request $request)
    {
        Log::info($request);
        try {
            $url = $this->safepayService->createCheckoutUrl(request()->amount);
            Log::info($url);
            return response()->json([
                'url' => $url
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error creating checkout URL: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $priceQuote = $this->loadBookingQuote($request);

        $pnrResponse = null;
        $airlinePnr = null;
        $cacheKeyPrefix = auth()->id() ? 'flights_' . auth()->id() : 'flights_' . session()->getId();
        $flightData = $request['flight'];
        $fareRefernce = $request['fare_reference'];
        $sector = $flightData['provider']['sector'] ?? '';
        $travelDate = $flightData['provider']['travel_date'];
        $travelDate = substr($travelDate, 0, 10);
        $contentSource = $flightData['provider']['contentSource'];
        $expiryTime = now()->addMinutes(15);
        // Check if booking already exists for same travellers and flight
        if ($flightData['provider']['contentSource'] == 'GDS') {
            Log::info('Checking for existing bookings with content source GDS');

            $existingBooking = FlightBookings::where('content_source', $flightData['provider']['contentSource'])->where('sector', $sector)
                ->where('travel_date', $travelDate)
                ->where('status', 'booked')
                ->whereHas('pessangers', function ($query) use ($request) {

                    foreach ($request->travellers as $traveller) {

                        $query->where(function ($q) use ($traveller) {

                            if (!empty($traveller['documentNo'])) {
                                $q->where('document_no', $traveller['documentNo']);
                            } else {
                                $q->where('first_name', $traveller['firstName'])
                                    ->where('last_name', $traveller['lastName']);
                            }

                        });

                    }

                })
                ->first();


            if ($existingBooking) {
                Log::info('already_booked passenger detected', [
                    'booking_id' => $existingBooking->id
                ]);

                return response()->json([
                    'already_booked' => true,
                    'message' => 'This passenger already has a booked ticket.',
                    'booking_id' => $existingBooking->id
                ], 409);
            }
        }

        Log::info('proceeding with booking');

        $customer = $request;
        if ($request->booking_status_setting == 1) {
            $pnr = json_encode(['local_pnr' => strtoupper(Str::random(6))]);
            $itineraryRef = json_decode($pnr, true)['local_pnr'];
            $pnrArray = json_decode($pnr, true);
            $localPnr = $pnrArray['local_pnr'];
            $flightData = json_encode($flightData);

        } else {
            switch ($request->flight_provider) {
                case 'sooper':
                    $localPnr = "null";
                    $pnr = null;
                    return;
                    // $pnr = $this->sooperApiService->sooperReservation($request['travellers']);
                    $response = json_decode($pnr, true); // assuming $json contains your JSON string
                    $pnrResponse = json_encode($response);
                    // Log::info("Sooper PNR Response:", ['response' => $response]);
                    // $itineraryRef = $response['data']['providers'][0]['pnr'];
                    // $itineraryRef = $pnr;
                    // Log::info($itineraryRef);
                    $flightData = json_encode($flightData);
                    break;

                case 'sabre':
                    $localPnr = "null";
                    $pnr = null;
                    // $pnrResponse = '{"CreatePassengerNameRecordRS":{"ApplicationResults":{"status":"Complete","Success":[{"timeStamp":"2025-10-06T20:05:54.618Z"}],"Warning":[{"type":"Validation","timeStamp":"2025-10-06T20:05:52.621Z","SystemSpecificResults":[{"Message":[{"code":"WARN.SWS.CLIENT.VALIDATION_FAILED","content":"EnhancedAirBookRQ: SegmentSelect@RPH does not match any other @RPH"}]}]},{"type":"BusinessLogic","timeStamp":"2025-10-06T20:05:53.077Z","SystemSpecificResults":[{"Message":[{"code":"WARN.SWS.HOST.ERROR_IN_RESPONSE","content":"SpecialServiceLLSRQ: USE 3 ENTRY TO INPUT FACTS"}]}]}]},"ItineraryRef":{"ID":"YMNLRX"},"AirBook":{"OriginDestinationOption":{"FlightSegment":[{"ArrivalDateTime":"12-31T09:45","DepartureDateTime":"12-31T08:00","eTicket":true,"FlightNumber":"0302","NumberInParty":"001","ResBookDesigCode":"V","Status":"NN","DestinationLocation":{"LocationCode":"LHE"},"MarketingAirline":{"Code":"PK","FlightNumber":"0302"},"OriginLocation":{"LocationCode":"KHI"}},{"ArrivalDateTime":"01-05T18:45","DepartureDateTime":"01-05T17:00","eTicket":true,"FlightNumber":"0305","NumberInParty":"001","ResBookDesigCode":"U","Status":"NN","DestinationLocation":{"LocationCode":"KHI"},"MarketingAirline":{"Code":"PK","FlightNumber":"0305"},"OriginLocation":{"LocationCode":"LHE"}}]}},"TravelItineraryRead":{"TravelItinerary":{"CustomerInfo":{"Address":{"AddressLine":[{"Id":"7","type":"O","content":"FLY UNIQUE"},{"Id":"8","type":"O","content":"65-B GULFISHAN"},{"Id":"9","type":"O","content":"LAHORE, PB PK"},{"Id":"10","type":"O","content":"59300"}]},"ContactNumbers":{"ContactNumber":[{"LocationCode":"ISB","Phone":"923056074127-H-1.1","RPH":"001","Id":"6"}]},"PaymentInfo":{"Payment":{"Form":[{"RPH":"001","Id":"13","Text":["CASH"]}]}},"PersonName":[{"WithInfant":"false","NameNumber":"01.01","PassengerType":"ADT","RPH":"1","elementId":"pnr-3.1","GivenName":"ANWAR MR","Surname":"AHMAD"}]},"ItineraryInfo":{"ReservationItems":{"Item":[{"RPH":"1","FlightSegment":[{"AirMilesFlown":"0634","ArrivalDateTime":"12-31T09:45","DayOfWeekInd":"3","DepartureDateTime":"2025-12-31T08:00","SegmentBookedDate":"2025-10-06T15:05:00","ElapsedTime":"01.45","eTicket":true,"FlightNumber":"0302","NumberInParty":"01","ResBookDesigCode":"V","SegmentNumber":"0001","SmokingAllowed":false,"SpecialMeal":false,"Status":"HK","StopQuantity":"00","IsPast":false,"CodeShare":false,"Id":"11","DestinationLocation":{"LocationCode":"LHE"},"Equipment":{"AirEquipType":"320"},"MarketingAirline":{"Code":"PK","FlightNumber":"0302","ResBookDesigCode":"V","Banner":"MARKETED BY PAKISTAN INTL AIRLINES"},"OperatingAirline":[{"Code":"PK","FlightNumber":"0302","ResBookDesigCode":"V","Banner":"OPERATED BY PAKISTAN INTL AIRLINES"}],"OperatingAirlinePricing":{"Code":"PK"},"DisclosureCarrier":{"Code":"PK","DOT":false,"Banner":"PAKISTAN INTL AIRLINES"},"OriginLocation":{"LocationCode":"KHI"},"SupplierRef":{"ID":"DCPK"},"UpdatedArrivalTime":"12-31T09:45","UpdatedDepartureTime":"12-31T08:00","Cabin":{"Code":"Y","SabreCode":"Y","Name":"ECONOMY","ShortName":"ECONOMY","Lang":"EN"}}],"Product":{"ProductDetails":{"productCategory":"AIR","ProductName":{"type":"AIR","content":""},"Air":{"sequence":1,"segmentAssociationId":2,"DepartureAirport":"KHI","ArrivalAirport":"LHE","EquipmentType":"320","MarketingAirlineCode":"PK","MarketingFlightNumber":"302","MarketingClassOfService":"V","Cabin":{"code":"Y","sabreCode":"Y","name":"ECONOMY","shortName":"ECONOMY","lang":"EN"},"ElapsedTime":105,"AirMilesFlown":634,"FunnelFlight":false,"ChangeOfGauge":false,"DisclosureCarrier":{"Code":"PK","DOT":false,"Banner":"PAKISTAN INTL AIRLINES"},"AirlineRefId":"DCPK","Eticket":true,"DepartureDateTime":"2025-12-31T08:00:00","ArrivalDateTime":"2025-12-31T09:45:00","FlightNumber":"302","ClassOfService":"V","ActionCode":"HK","NumberInParty":1,"inboundConnection":false,"outboundConnection":false,"ScheduleChangeIndicator":false,"SegmentBookedDate":"2025-10-06T15:05:00"}}}},{"RPH":"2","FlightSegment":[{"AirMilesFlown":"0634","ArrivalDateTime":"01-05T18:45","DayOfWeekInd":"1","DepartureDateTime":"2026-01-05T17:00","SegmentBookedDate":"2025-10-06T15:05:00","ElapsedTime":"01.45","eTicket":true,"FlightNumber":"0305","NumberInParty":"01","ResBookDesigCode":"U","SegmentNumber":"0002","SmokingAllowed":false,"SpecialMeal":false,"Status":"HK","StopQuantity":"00","IsPast":false,"CodeShare":false,"Id":"12","DestinationLocation":{"LocationCode":"KHI"},"Equipment":{"AirEquipType":"320"},"MarketingAirline":{"Code":"PK","FlightNumber":"0305","ResBookDesigCode":"U","Banner":"MARKETED BY PAKISTAN INTL AIRLINES"},"OperatingAirline":[{"Code":"PK","FlightNumber":"0305","ResBookDesigCode":"U","Banner":"OPERATED BY PAKISTAN INTL AIRLINES"}],"OperatingAirlinePricing":{"Code":"PK"},"DisclosureCarrier":{"Code":"PK","DOT":false,"Banner":"PAKISTAN INTL AIRLINES"},"OriginLocation":{"LocationCode":"LHE"},"SupplierRef":{"ID":"DCPK"},"UpdatedArrivalTime":"01-05T18:45","UpdatedDepartureTime":"01-05T17:00","Cabin":{"Code":"Y","SabreCode":"Y","Name":"ECONOMY","ShortName":"ECONOMY","Lang":"EN"}}],"Product":{"ProductDetails":{"productCategory":"AIR","ProductName":{"type":"AIR","content":""},"Air":{"sequence":2,"segmentAssociationId":3,"DepartureAirport":"LHE","ArrivalAirport":"KHI","EquipmentType":"320","MarketingAirlineCode":"PK","MarketingFlightNumber":"305","MarketingClassOfService":"U","Cabin":{"code":"Y","sabreCode":"Y","name":"ECONOMY","shortName":"ECONOMY","lang":"EN"},"ElapsedTime":105,"AirMilesFlown":634,"FunnelFlight":false,"ChangeOfGauge":false,"DisclosureCarrier":{"Code":"PK","DOT":false,"Banner":"PAKISTAN INTL AIRLINES"},"AirlineRefId":"DCPK","Eticket":true,"DepartureDateTime":"2026-01-05T17:00:00","ArrivalDateTime":"2026-01-05T18:45:00","FlightNumber":"305","ClassOfService":"U","ActionCode":"HK","NumberInParty":1,"inboundConnection":false,"outboundConnection":false,"ScheduleChangeIndicator":false,"SegmentBookedDate":"2025-10-06T15:05:00"}}}}]},"Ticketing":[{"RPH":"01","TicketTimeLimit":"TAW/"}]},"ItineraryRef":{"AirExtras":false,"ID":"YMNLRX","InhibitCode":"U","PartitionID":"AA","PrimeHostID":"1B","Source":{"AAA_PseudoCityCode":"8BBD","CreateDateTime":"2025-10-06T15:05","CreationAgent":"AFU","HomePseudoCityCode":"8BBD","PseudoCityCode":"8BBD","ReceivedFrom":"Jetze-DEV","LastUpdateDateTime":"2025-10-06T15:05","SequenceNumber":"1"}},"RemarkInfo":{},"OpenReservationElements":{"OpenReservationElement":[{"id":"3","type":"FP","displayIndex":1,"elementId":"pnr-or-3","FormOfPayment":{"migrated":false,"Cash":{"Text":"CASH"}}}]}}}},"Links":[{"rel":"self","href":"https://api.platform.sabre.com/v2.5.0/passenger/records?mode=create"},{"rel":"linkTemplate","href":"https://api.platform.sabre.com/<version>/passenger/records?mode=<mode>"}]}';
                    $pnrResponse = $this->sabreApiService->createPNR($customer, $flightData, $fareRefernce);

                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }

                    $response = json_decode($pnrResponse, true);
                    Log::info('Sabre PNR Response:');
                    Log::info($response);

                    $itineraryRef = null;
                    if ($flightData['provider']['source'] == 'SB-NDC') {
                        $itineraryRef = $response['order']['pnrLocator'] ?? null;
                    } else {
                        $itineraryRef = $response['CreatePassengerNameRecordRS']['ItineraryRef']['ID'] ?? null;
                    }

                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }

                    $flightData = json_encode($flightData);
                    break;

                case 'airsial':
                    $localPnr = "null";
                    $pnr = null;
                    // $pnrResponse = '{"Response":{"message":"PNR successfully created ","Data":{"PNR":"19Z8GE","validTill":"11-10-2025 14:37"}},"Success":true}';
                    $pnrResponse = $this->airsialApiService->createBooking($customer, $flightData, $fareRefernce);
                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    // $pnrResponse = json_decode($pnrResponse,true);
                    $itineraryRef = $pnrResponse['Response']['Data']['PNR'] ?? null;

                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $pnrResponse = json_encode($pnrResponse);
                    $flightData = json_encode($flightData);
                    break;
                case 'PIA':
                    $localPnr = "null";
                    $pnr = null;
                    $pnrResponse = $this->piaApiService->createBooking($customer, $fareRefernce);
                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $itineraryRef = $pnrResponse['Response']['Data']['PNR'] ?? null;

                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $pnrResponse = json_encode($pnrResponse);
                    $flightData = json_encode($flightData);
                    break;
                case 'flydubai':
                    $localPnr = "null";
                    $pnr = null;

                    $pnrResponse = $this->flydubaiApiService->AddToCart($customer, $fareRefernce);
                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $itineraryRef = $pnrResponse['Response']['Data']['PNR'] ?? null;

                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $pnrResponse = json_encode($pnrResponse);
                    $flightData = json_encode($flightData);
                    break;

                case 'travelport':
                    $localPnr = "null";
                    $pnr = null;
                    $pnrResponse = $this->travelportApiService->bookFlight($customer);
                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $itineraryRef = $pnrResponse['ReservationResponse']['Reservation']['Receipt'][0]['Confirmation']['Locator']['value'] ?? null;
                    $airlinePnr = $pnrResponse['ReservationResponse']['Reservation']['Receipt'][1]['Confirmation']['Locator']['value'] ?? null;
                    if($flightData['provider']['contentSource'] == 'NDC'){
                    $itineraryRef = $pnrResponse['ReservationResponse']['Reservation']['Receipt'][2]['Confirmation']['Locator']['value'] ?? null;
                    $airlinePnr = $pnrResponse['ReservationResponse']['Reservation']['Receipt'][0]['Confirmation']['Locator']['value'] ?? null;

                    }
                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $pnrResponse = json_encode($pnrResponse);
                    $flightData = json_encode($flightData);
                    break;
                case 'at':
                    $localPnr = "null";
                    $pnr = null;
                    $service = new AtApiService();
                    $pnrResponse = $service->atBooking($request);
                    $response = $pnrResponse;
                    if (!isset($response['TransactionID']) || $response['TransactionID'] === 0) {
                        return response()->json([
                            'message' => 'AT reservation failed',
                            'error' => 'No response from AT API'
                        ], 400);
                    }
                    $itineraryRef = !empty($response['TransactionIDList'])
                        ? implode(',', $response['TransactionIDList'])
                        : $response['TransactionID'];
                    $pnrResponse = json_encode($response);
                    $flightData = json_encode($flightData);
                    break;
                case 'OneApi':
                    $localPnr = "null";
                    $pnr = null;
                    $pnrResponse = $this->oneApiService->bookFlight($customer);
                    if ($pnrResponse === null) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    if ($pnrResponse instanceof \Illuminate\Http\JsonResponse) {
                        return $pnrResponse;
                    }
                    if (isset($pnrResponse['error'])) {
                        return response()->json([
                            'message' => $pnrResponse['message'] ?? 'Failed to create booking. Please try again later.',
                            'error' => $pnrResponse['error'],
                            'raw' => $pnrResponse['raw'] ?? null,
                        ], 422);
                    }
                    // $pnrResponse = json_decode($pnrResponse, true);
                    $bookingRefId = $pnrResponse['Body']['OTA_AirBookRS']['AirReservation']['BookingReferenceID'] ?? null;
                    Log::info('OneApi Booking Reference ID:', ['bookingRefId' => $bookingRefId]);
                    $bookingRefId = is_array($bookingRefId) && isset($bookingRefId[0]) ? $bookingRefId : [$bookingRefId];
                    $itineraryRef = $bookingRefId[0]['@attributes']['ID'] ?? null;
                    $expiryTime = $pnrResponse['Body']['OTA_AirBookRS']['AirReservation']['Ticketing']['@attributes']['TicketTimeLimit'] ?? null;
                    $expiryTime = Carbon::parse($expiryTime);

                    if (!$itineraryRef) {
                        return response()->json(['message' => 'Failed to create booking. Please try again later.'], 500);
                    }
                    $pnrResponse = json_encode($pnrResponse);
                    $flightData = json_encode($flightData);
                    break;
                default:
                    return response()->json(['message' => 'Invalid flight provider specified.'], 400);
            }


        }
        Log::info('Final Booking Debug Info:', [
            'booking_status_setting' => $request->booking_status_setting,
            'localPnr' => $localPnr,
            'itineraryRef' => $itineraryRef,
            'flightData' => $flightData,
            'pnr' => $pnrResponse,
            'expiry_time' => $expiryTime
        ]);
        // $pnr = $this->sabreApiService->createPNR($customer, $flightData);

        $validated = Validator::make($request->all(), [
            'main_contact.email' => 'required|email',
            'main_contact.phone' => 'required|string',
            'main_contact.country' => 'required|string',
            'agency_mobile' => 'nullable|string',
            'agency_email' => 'nullable|email',
            'flight_id' => 'required',
            'agent_id' => 'nullable|integer',
            'amount' => 'required|numeric',
            'agent_markup' => 'nullable',
            'segment_margin' => 'nullable|numeric',
            'promotion_margin' => 'nullable|numeric',
            'travellers' => 'required|array',
            'travellers.*.type' => 'required|string',
            'travellers.*.title' => 'required|string',
            'travellers.*.firstName' => 'required|string',
            'travellers.*.lastName' => 'required|string',
            'travellers.*.nationality' => 'required|string',
            'travellers.*.documentType' => 'nullable|string',
            'travellers.*.documentNo' => 'nullable|string',
            'travellers.*.expiryDate' => 'nullable|date',
            'travellers.*.issueCountry' => 'nullable|string',
            'travellers.*.gender' => 'nullable|string',
            'travellers.*.dob' => 'nullable|string'
        ])->validate();

        // Log::info('Validated Booking Data:', [
        //     'main_contact' => $validated['main_contact'],
        //     'agency_mobile' => $validated['agency_mobile'],
        //     'agency_email' => $validated['agency_email'],
        //     'flight_id' => $validated['flight_id'],
        //     'agent_id' => $validated['agent_id'],
        //     'amount' => $validated['amount'],
        //     'agent_markup' => $validated['agent_markup'],
        //     'travellers' => $validated['travellers']
        // ]);

        $flightBooking = FlightBookings::create([
            'main_email' => $validated['main_contact']['email'] ?? 'unknown@example.com',
            'main_phone' => $validated['main_contact']['phone'] ?? 'N/A',
            'main_country' => $validated['main_contact']['country'] ?? 'Unknown',
            'flight_data' => $flightData ?? '{}', // must never be null
            'pnr_response' => $pnrResponse, // must never be null
            'pnr' => $localPnr ?? null,
            // 'airline_pnr' => $airlinePnr ?? null,
            'sector' => $sector,
            'travel_date' => $travelDate,
            'content_source' => $contentSource,
            'agency_phone' => $validated['agency_mobile'] ?? null,
            'agency_email' => $validated['agency_email'] ?? null,
            'agent_markup' => $validated['agent_markup'] ?? 0,
            'flight_id' => $validated['flight_id'] ?? 'UNKNOWN',
            'agent_id' => $validated['agent_id'] ?? null,
            'amount' => $validated['amount'] ?? 0.00,
            'booking_mode' => $request->flight_mode,
            'airport_margin_amount' => $request->airportMargin ?? 0,
            'booking_source' => $request->flight_source ?? 'web',
            'flight_provider' => $request->flight_provider,
            'agent_margin' => $request->agent_margin ?? 0,
            'agent_discount' => $request->agent_discount ?? 0,
            'add_ones_amount' => $request->add_ones_amount ?? 0,
            'segment_margin' => $validated['segment_margin'] ?? 0,
            'promotion_margin' => $validated['promotion_margin'] ?? 0,
            'fare_reference' => $request->fare_reference
                ? json_encode($request->fare_reference)
                : null,
            'itinerary_ref' => $itineraryRef ?? null,
            'expiry_time' => $expiryTime ?? null,
            'status' => $request->booking_status ?? 'booked', // falls back to safe default
        ]);

        if ($priceQuote) {
            $this->bookingPricingService->createSnapshot($flightBooking, $priceQuote);
        }

        $this->providerBookingEventService->record(
            $flightBooking,
            (string) $request->flight_provider,
            'book',
            $pnrResponse,
            $itineraryRef ?? null,
        );


        // Store travellers
        // Insert Travellers
        foreach ($validated['travellers'] as $traveller) {
            FlightPassenger::create([
                'booking_id'    => $flightBooking->id,
                'user_id'       => $validated['agent_id'],
                'type'          => $traveller['type'],
                'title'         => $traveller['title'],
                'first_name'    => $traveller['firstName'],
                'last_name'     => $traveller['lastName'],
                'nationality'   => $traveller['nationality'],
                'document_type' => $traveller['documentType'],
                'document_no'   => $traveller['documentNo'],
                'cnic'          => $traveller['cnic'] ?? null,
                'expiry_date'   => $traveller['expiryDate'],
                'issue_country' => $traveller['issueCountry'],
                'gender'        => $traveller['gender'],
                'dob'           => $traveller['dob'],
            ]);

        }
        if ($request->paymentMethod == 'hold') {

            $admin = User::where('role', 'admin')->first();

            $flightDataForMail = json_decode($flightBooking->flight_data, true) ?? [];
            $recipients = [
                $flightBooking->agency_email,
                $admin->email,
            ];

            foreach ($recipients as $email) {
                if (!empty($email)) {
                    Mail::to($email)->queue(
                        (new BookingCreatedMail($email, $flightBooking, $flightDataForMail))->afterCommit()
                    );
                }
            }
        }
        $flightBooking = FlightBookings::with(['pessangers', 'user.agentData'])->where('id', $flightBooking->id)->first();
        Log::info('Expiry Time: ' . $expiryTime);
        CancelBookingJob::dispatch($flightBooking)->delay($expiryTime);


        return response()->json([
            'message' => 'Booking confirmed successfully',
            'booking' => $flightBooking,
        ], 200);
    }

    // Helper functions

    /**
     * Replace AT checkout price data with the active server-side quote values.
     */
    private function loadBookingQuote(Request $request)
    {
        $requestedProvider = strtolower((string) $request->input('flight_provider'));

        if (!$request->filled('quote_id')) {
            if ($requestedProvider === 'at') {
                abort(422, 'A valid price quote is required before booking an AT flight.');
            }

            return null;
        }

        $quote = $this->priceQuoteService->findActive($request->string('quote_id')->toString(), $request->user());
        $flight = $quote->flight_data;
        $providerPricing = $quote->provider_pricing_data ?? [];
        $quoteAncillaryItems = $quote->items()
            ->active()
            ->orderBy('trip_index')
            ->orderBy('journey_index')
            ->orderBy('segment_index')
            ->orderBy('passenger_id')
            ->get();
        $addOnSellingAmount = $quoteAncillaryItems->reduce(
            fn (string $total, $item) => bcadd($total, (string) $item->display_amount, 8),
            '0',
        );

        if (strtolower($quote->provider) === 'at') {
            $bookingTui = data_get($providerPricing, 'tui') ?? data_get($flight, 'provider.TUI');
            $fareNetAmount = data_get($providerPricing, 'net_amount');

            if (!$bookingTui || !is_numeric($fareNetAmount)) {
                abort(422, 'The latest AT fare is unavailable. Please search again before booking.');
            }

            if ($quoteAncillaryItems->contains(
                fn ($item) => strtoupper($item->provider_currency) !== strtoupper($quote->provider_currency),
            )) {
                abort(422, 'An ancillary currency does not match the locked AT fare. Please refresh the options.');
            }
        }

        $request->merge([
            'flight' => $flight,
            'flight_id' => data_get($flight, 'leg.ref_id'),
            'flight_provider' => strtolower($quote->provider),
            'fare_reference' => $quote->selected_fare_references,
            'amount' => $quote->display_amount,
            'currency' => $quote->display_currency,
            'add_ones_amount' => $addOnSellingAmount,
            'TUI' => data_get($providerPricing, 'tui') ?? data_get($flight, 'provider.TUI'),
            'NetAmount' => $fareNetAmount,
            'fareType' => data_get($providerPricing, 'fare_type') ?? data_get($flight, 'provider.fare_type'),
            // Never pass browser ancillary fields to AT. These are the quote's locked provider references.
            'quote_ancillary_items' => $quoteAncillaryItems->map(fn ($item) => [
                'trip_index' => $item->trip_index,
                'provider_references' => $item->provider_references,
                'provider_amount' => $item->provider_amount,
            ])->values()->all(),
            'selectedExtras' => [],
        ]);

        return $quote;
    }

    /**
     * Find the local booking linked to a provider PNR/details request.
     */
    private function findBookingForProviderEvent(Request $request): ?FlightBookings
    {
        $bookingId = $request->input('booking_id') ?? $request->input('bookingId');

        if ($bookingId) {
            return FlightBookings::find($bookingId);
        }

        if ($request->filled('pnr')) {
            return FlightBookings::query()
                ->where('itinerary_ref', $request->pnr)
                ->orWhere('pnr', $request->pnr)
                ->first();
        }

        return null;
    }

    public function getMyTravellers(Request $request)
    {
        $userId = auth()->id();

        if (!$userId) {
            return response()->json(['passengers' => []]);
        }

        // Fetch all passengers for this user, newest first
        $allPassengers = FlightPassenger::where('user_id', $userId)
            ->orderBy('id', 'desc')
            ->get();

        // Deduplicate: keep only the most-recent record per (first_name + last_name + document_no) combo
        $seen = [];
        $unique = [];
        foreach ($allPassengers as $passenger) {
            $key = strtolower(trim($passenger->first_name . '|' . $passenger->last_name . '|' . $passenger->document_no));
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $passenger;
            }
        }

        return response()->json(['passengers' => array_values($unique)]);
    }

    public function getPnrData(Request $request)
    {
        Log::info($request);

        // Fetch booking
        $bookingDetail = FlightBookings::where('itinerary_ref', $request->pnr)
            ->with(['pessangers', 'user.agentData'])
            ->first();

        // If no booking found
        if (!$bookingDetail) {
            return response()->json([
                'status' => false,
                'message' => 'No booking found'
            ], 404);
        }

        $authUser = auth()->user();
        $role = $bookingDetail->user->role ?? null;

        // If booking belongs to a CUSTOMER
        // and the viewer is NOT logged in (public check allowed)
        if ($role === 'customer' && !$authUser) {
            return response()->json([
                'status' => true,
                'booking' => $bookingDetail
            ], 200);
        }

        // If booking belongs to an AGENT
        if ($role === 'agent') {
            if ($authUser && $bookingDetail->agent_id == $authUser->id) {
                return response()->json([
                    'status' => true,
                    'booking' => $bookingDetail
                ], 200);
            }
        }

        // If reached here → access denied
        return response()->json([
            'status' => false,
            'message' => 'No booking found'
        ], 404);
    }

    public function updateBookingAmount(Request $request)
    {
        Log::info($request);
        $booking = FlightBookings::where('id', $request->booking_id)->first();
        // Update status and PNR
        if ($request->add_ones_amount) {
            $booking->add_ones_amount = $request->add_ones_amount;
        }
        $booking->amount = $request->amount;
        $booking->save();

        return response()->json([
            'message' => 'Amount Updated Successfully'
        ]);
    }


     public function getPnrDetails(Request $request)
    {

        Log::info($request);
        $pnrData = null;
        if ($request->flight_provider == 'sabre') {

            $pnrData = $this->sabreApiService->getBookingDetails($request);
        } else if ($request->flight_provider == 'travelport') {
            $pnrData = $this->travelportApiService->getBookingDetails($request->pnr);

        } else if ($request->flight_provider == 'airsial') {
            $airsialApiService = new AirSialApiService();
            $pnrData = $airsialApiService->getBookingDetails($request);


        } else if ($request->flight_provider == 'OneApi') {
            $oneApiService = new OneApiService();
            $pnrData = $oneApiService->readBooking($request->pnr);
            Log::info('OneApi PNR Details:', ['pnrData' => $pnrData]);
        } else if( $request->flight_provider == 'at') {
            $atApiService = new AtApiService();
            $pnrData = $atApiService->getBookingDetails($request);
        }

        $booking = $this->findBookingForProviderEvent($request);

        if ($booking) {
            $this->providerBookingEventService->record(
                $booking,
                (string) ($request->flight_provider ?? $booking->flight_provider),
                'pnr_fetch',
                $pnrData,
                $request->pnr,
            );
        }

        return response()->json([
            'message' => 'PNR Details fetched successfully',
            'data' => $pnrData
        ]);
    }


    public function cancelPnrDetails(Request $request)
    {
        Log::info($request);
        if ($request->booking_source == 'sooper') {
            Log::info('Canceling booking via Sooper API');
            $body = [
                'booking_uuid' => $request->booking_uuid,
                'billable_price' => $request->billable_price,
                'currency' => $request->currency,
            ];
            $res = $this->sooperApiService->cancelSooperBooking($body);
            Log::info($res);
            if ($res['status'] == false) {
                return response()->json([
                    'message' => 'Booking cancellation failed',
                    'error' => $res['message']
                ], 400);

            }

            $request->validate([
                'booking_status' => 'required|string', // Example statuses
                'pnr' => 'nullable|string', // PNR can be null for certain statuses
            ]);
            $booking = FlightBookings::where('id', $request->bookingId)->first();
            // Update status and PNR
            $booking->status = $request->booking_status;
            $booking->pnr = $request->pnr;
            $booking->save();
            $this->providerBookingEventService->record(
                $booking,
                'sooper',
                'cancel',
                $res,
                $request->pnr,
            );
            $this->sendBookingCanceledMail($booking);

            Log::info($booking);
            return response()->json([
                'message' => 'Booking Canceled successfully',
                'booking' => $booking,
            ]);

        } elseif ($request->booking_source == 'sabre') {
            if ($request->flight_source == 'SB-NDC') {
                Log::info('Canceling booking via Sabre NDC API');
                $pnrStatus = $this->sabreApiService->cancelNDCOrder($request->orderId);
            } else {
                Log::info('Canceling booking via Sabre Classic API');
                $pnrStatus = $this->sabreApiService->cancelBooking($request->pnr);
            }
            if ($pnrStatus) {
                $booking = FlightBookings::where('id', $request->bookingId)->first();
                $booking->status = 'canceled';
                $booking->save();
                $this->providerBookingEventService->record(
                    $booking,
                    'sabre',
                    'cancel',
                    $pnrStatus,
                    $request->pnr,
                );
                $this->sendBookingCanceledMail($booking);
            }
            return $pnrStatus;
        } else if ($request->booking_source == 'travelport') {
            Log::info('Canceling booking via Travelport API');
            $pnrStatus = $this->travelportApiService->cancelReservation($request->pnr);
            Log::info($pnrStatus);
            if (!$pnrStatus) {
               return response()->json([
                    'message' => 'Booking cancellation failed',
                    'error' => 'Unable to cancel booking with Travelport API'
                ], 400);
            }
             $booking = FlightBookings::where('id', $request->bookingId)->first();
                $booking->status = 'canceled';
                $booking->save();
                $this->providerBookingEventService->record(
                    $booking,
                    'travelport',
                    'cancel',
                    $pnrStatus,
                    $request->pnr,
                );
                $this->sendBookingCanceledMail($booking);
            return $pnrStatus;
        }else if ($request->booking_source == 'OneApi') {
              $booking = FlightBookings::where('id', $request->bookingId)->first();
                $booking->status = 'canceled';
                $booking->save();
                $this->providerBookingEventService->record(
                    $booking,
                    'oneapi',
                    'cancel',
                    null,
                    $request->pnr,
                );
                $this->sendBookingCanceledMail($booking);
                return response()->json([
                    'message' => 'Booking Canceled successfully',
                    'booking' => $booking,
                ]);
        }
        return;

    }

    private function sendBookingCanceledMail($booking): void
    {
        if (!$booking) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $flightDataForMail = json_decode($booking->flight_data, true) ?? [];

        $recipients = array_values(array_unique(array_filter([
            $booking->main_email,
            $booking->agency_email,
            $admin->email ?? null,
        ])));

        foreach ($recipients as $email) {
            Mail::to($email)->queue(
                (new BookingCanceledMail($email, $booking, $flightDataForMail))->afterCommit()
            );
        }
    }

    private function sendBookingStatusMail($booking, ?string $status = null, ?array $recipientEmails = null): void
    {
        if (!$booking) {
            return;
        }

        $status = strtolower((string) ($status ?? $booking->status ?? ''));
        if (!in_array($status, ['booked', 'canceled', 'cancelled', 'ticketed', 'issued'], true)) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $flightDataForMail = json_decode($booking->flight_data, true) ?? [];

        $recipients = $recipientEmails ?? [
            $booking->main_email,
            $booking->agency_email,
            $admin->email ?? null,
        ];

        $recipients = array_values(array_unique(array_filter($recipients)));

        foreach ($recipients as $email) {
            $mail = match ($status) {
                'canceled', 'cancelled' => new BookingCanceledMail($email, $booking, $flightDataForMail),
                'ticketed', 'issued' => new BookingConfirmedMail($email, $booking, $flightDataForMail),
                default => new BookingCreatedMail($email, $booking, $flightDataForMail),
            };

            Mail::to($email)->queue($mail->afterCommit());
        }
    }

    public function confirmPnr(Request $request)
    {
        Log::info($request);
        $res = null;
        if ($request->flight_provider == 'sooper') {
            $res = '[]';
            // $res = $this->sooperApiService->confrimSooperBooking($request);
            Log::info($res);

            if ($res['status'] == false) {
                return response()->json([
                    'message' => 'Booking confirmation failed',
                    'error' => $res['message']
                ], 400);

            }
        } else if ($request->flight_provider == 'sabre') {
            $res = '[]';
            // $res = $this->sabreApiService->confirmTicket($request->pnr);
        } else if ($request->flight_provider == 'OneApi') {
            $res = '[]';
            $res = $this->oneApiService->confirmTicket($request);
            if ( !$res) {
                return response()->json([
                    'message' =>  'Booking confirmation failed',
                    
                ], 400);
            }
        } else if ($request->flight_provider == 'airsial') {
            $res = '[]';
            // $res = $this->airsialApiService->confirmTicket($request->pnr);
        } else if ($request->flight_provider == 'travelport') {
            $res = '[]';
            $res = $this->travelportApiService->confirmTicket($request);
            if ($res['status'] == false) {
                Log::info($res);
                return response()->json([
                    'message' => 'Booking confirmation failed',
                    'error' => $res['error']
                ], 400);

            }

        }else if ($request->flight_provider === 'at') {

            $atApiService = new AtApiService();
            Log::info('Booking held, skipping confirmation step.');
            $res = $atApiService->atPaymentRequest($request);

            Log::info($res);
            $res = json_decode($res, true);
            if ($res == null) {
                return response()->json([
                    'message ' => 'Booking confirmation failed',
                    'error ' => 'No response from NDC WT API'
                ], 400);

            }

            if (($res['status'] ?? true) === false) {
                return response()->json([
                    'message' => 'Booking confirmation failed',
                    'error' => $res['error'] ?? $res['message'] ?? 'Payment Failed !',
                ], 400);
            }
        }
        $request->validate([
            'booking_status' => 'required|string', // Example statuses
            'pnr' => 'nullable|string', // PNR can be null for certain statuses
        ]);
        if ($res !== null) {
            Log::info('Updating booking status and PNR');
            $booking = FlightBookings::where('id', $request->bookingId)->first();
            // Update status and PNR
            $booking->sooper_response = json_encode($res); // Store Sooper response if available
            $booking->status = $request->booking_status;
            $booking->pnr = $request->pnr;
            $booking->issuance_date = now();
            // $booking->issuance_date = '2026-06-28 11:58:24';

            $booking->save();

            $this->providerBookingEventService->record(
                $booking,
                (string) $request->flight_provider,
                'ticket',
                $res,
                $request->pnr,
            );

            $this->sendBookingStatusMail($booking, $booking->status);
        }

        return response()->json([
            'message' => 'Booking confirmed successfully',
            // 'booking' => $booking,
        ]);
    }

    public function approveBooking(Request $request)
    {
        Log::info($request);
        $request->validate([
            'status' => 'required|string|in:pending,approved,rejected,confirmed,canceled,ticketed,booked,issued,requested,voided',
            'airline_pnr' => 'nullable|string',
            'ticket_number' => 'nullable|string',
        ]);
        $booking = FlightBookings::where('id', $request->booking_id)->first();
        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        $previousStatus = strtolower((string) $booking->status);
        $booking->status = $request->status;
        if ($request->has('airline_pnr')) {
            $booking->airline_pnr = $request->airline_pnr;
        }
        if ($request->has('ticket_number')) {
            $booking->ticket_number = $request->ticket_number;
        }
        // A status update is a standard booking update and must retain normal ledger treatment.
        $booking->is_manually_issued = false;
        $booking->save();

        if ($previousStatus !== strtolower((string) $booking->status)) {
            $this->sendBookingStatusMail($booking, $booking->status);
        }

        return response()->json([
            'message' => 'Booking Approved successfully',
            'booking' => $booking,
        ]);
    }

    public function saveAdminBooking(Request $request)
    {
        $totalAmount = $request->bookingData['fares'][0]['totals']['total'] ?? 0;
        $bookingJsonData = json_encode($request->bookingData);

        $validatedData = $request->validate([
            'agentId' => 'required',
            'pnr' => 'required|string|unique:admin_bookings',
            'margin' => 'required|numeric|min:0',
            'bookingData' => 'required',
            'total_amount' => 'numeric|min:0|default:0',
        ]);

        $adminBooking = AdminBooking::create([
            'agent_id' => $validatedData['agentId'],
            'pnr' => $validatedData['pnr'],
            'margin' => $validatedData['margin'],
            'booking_json_data' => $bookingJsonData,
            'total_amount' => $totalAmount,
        ]);

        return response()->json([
            'message' => 'Admin Booking stored successfully',
            'adminBooking' => $adminBooking
        ], 200);

    }

    public function getAdminBookings(Request $request)
    {
        Log::info($request);
        // Check user role
        if ($request->user_role == "agent") {
            $adminBookings = AdminBooking::with('agent')
                ->where('agent_id', Auth::user()->id)
                ->get();
        } else {
            $adminBookings = AdminBooking::with('agent')->get();
        }
        return $adminBookings;
    }

    public function getAdminBooking(Request $request)
    {
        Log::info($request);

        $adminBookings = AdminBooking::with('agent')
            ->where('pnr', $request->pnr)
            ->first();

        return $adminBookings;
    }

    public function getCustomerBookings(Request $request)
    {

        Log::info($request);
        $requestedUserId = $request->user_id ?? $request->userId;

        $query = FlightBookings::with('pessangers')
            ->where('booking_mode', 'B2C')
            ->orderBy('id', 'desc');

        // Always support filtering by user_id/userId from request.
        if (!empty($requestedUserId)) {
            $query->where('agent_id', $requestedUserId);
        } elseif ($request->userRole == "agent") {
            $query->where('agent_id', $request->userId);
        }

        // Filter by booking status
        if ($request->bookingFilter && $request->bookingFilter !== "all") {
            $query->where('status', $request->bookingFilter);
        }

        // Get paginated bookings
        $bookings = $query->get();
        Log::info($bookings);
        // Count different statuses
        $baseQuery = FlightBookings::query();
        if (!empty($requestedUserId)) {
            $baseQuery->where('agent_id', $requestedUserId);
        } elseif ($request->userRole == "agent") {
            $baseQuery->where('agent_id', $request->userId);
        }

        $baseQuery->where('booking_mode', 'B2C');
        // Counting different statuses
        $totalCount = (clone $baseQuery)->count();
        $totalTicketed = (clone $baseQuery)->where('status', 'ticketed')->count();
        $totalCanceled = (clone $baseQuery)->where('status', 'canceled')->count();
        $totalBooked = (clone $baseQuery)->where('status', 'booked')->count();
        $totalVoided = (clone $baseQuery)->where('status', 'voided')->count();
        // Extract itinerary references
        $bookingsWithItineraryReferences = $bookings->map(function ($booking) {
            $pnrResponse = json_decode($booking->pnr_response, true);
            $itineraryReference = data_get($pnrResponse, 'CreatePassengerNameRecordRS.ItineraryRef.ID');
            $booking->itinerary_reference = $itineraryReference;
            return $booking;
        });

        return response()->json([
            'bookings' => $bookingsWithItineraryReferences,
            'total_count' => $totalCount,
            'total_canceled' => $totalCanceled,
            'total_ticketed' => $totalTicketed,
            'total_booked' => $totalBooked,
            'total_voided' => $totalVoided,
        ]);

    }

     public function sendPriceRequest(Request $request)
    {
        $response = null;
        if ($request->flight_provider == 'travelport') {
            Log::info('Fetching quote via Travelport API');

            $response = $this->travelportApiService->travelportPrice($request);
            // $response = json_decode($response, true);
            if (isset($response['OfferListResponse']['Result']['Error']) && count($response['OfferListResponse']['Result']['Error']) > 0) {
                $errors = $response['OfferListResponse']['Result']['Error'];
                $firstError = is_array($errors) ? ($errors[0] ?? []) : $errors;
                $errorMessage =
                    $firstError['Description'] ??
                    $firstError['Message'] ??
                    $firstError['ErrorMessage'] ??
                    'Failed Price Request';
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => $errors,
                    'price_response' => $response,
                ], 422);
            } else {

                $fareRules = $this->travelportApiService->getFareRules($response['OfferListResponse']['Identifier']['value'] ?? null);
            }
        } else if ($request->flight_provider == 'OneApi') {
            $response = $this->oneApiService->validatePriceWithBundle($request);
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return $response;
            }
            if (isset($response['error'])) {
                return response()->json([
                    'message' => $response['message'] ?? 'Failed to validate price with bundle',
                    'error' => $response['error'],
                    'raw' => $response['raw'] ?? null,
                ], 422);
            }
            if (!$response) {
                return response()->json([
                    'message' => 'Failed to validate price with bundle',
                    // 'error' => $response['error'] ?? 'Unknown error'
                ], 400);
            }
        } else if ($request->flight_provider == 'AT') {
            $atApiService = new AtApiService();
            $priceResponse = $atApiService->sendPriceRequest($request);
            if (!$priceResponse) {
                return response()->json([
                    'message' => 'Failed to retrieve price',
                ], 400);
            }
            return response()->json([
                'message' => 'Price sent successfully',
                'price_response' => $priceResponse,
            ], 200);
        }

        // Log::info($quote['data']);
        return response()->json([
            'message' => 'Price sent successfully',
            'price_response' => $response,
            'fare_rules' => $fareRules ?? null,
        ], 200);

    }

   public function getAncillaries(Request $request)
    {
        $response = null;
        $promise1 = null;
        if ($request->flight_provider == 'sooper') {
            $response = $this->sooperApiService->fetchAncillaries($request);
            // Log::info(json_encode($response, JSON_PRETTY_PRINT));
            if (!$response) {
                return response()->json([
                    'message' => 'No ancillaries found',
                ], 404);
            }
        } else if ($request->flight_provider == 'OneApi') {

            $baggages = $this->oneApiService->fetchBaggage($request);
            // $baggages = json_decode($baggages,true);

            Log::info(json_encode($baggages));

            $meals = $this->oneApiService->fetchMeals($request);
            // $meals = json_decode($meals, true);
            Log::info(json_encode($meals));
            $seats = $this->oneApiService->fetchSeats($request);
            Log::info(json_encode($seats));
            $response = [
                "baggage" => $baggages,
                "meals" => $meals,
                "seats" => $seats,
            ];

        } else if (strtolower($request->flight_provider) === 'at') {
            $atApiService = new AtApiService();
            $quote = null;

            if ($request->quote_id && $request->user()) {
                $quote = $this->priceQuoteService->findActive($request->quote_id, $request->user());
            }

            $rawResponse = $atApiService->fetchAncillaries($request->body);
            $response = app(AtAncillaryTransformer::class)->transform(
                $rawResponse,
                $quote?->display_currency ?? data_get($request->body, 'currency_code', 'AED'),
                $quote?->provider_currency ?? 'AED',
            );
        }

        return response()->json([
            'message' => 'Ancillaries fetched successfully',
            'ancillaries' => $response,
            'seatMap' => $promise1,
        ], 200);

    }

    public function patchAncillaries(Request $request)
    {

        $response = null;
        if ($request['flight_provider'] == 'sooper') {
            $response = $this->sooperApiService->patchAncillaries($request['params']);
        } else if ($request['flight_provider'] == 'OneApi') {
            $response = $this->oneApiService->priceWithBundle_ssr($request);
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                return $response;
            }
            if (isset($response['error'])) {
                return response()->json([
                    'message' => $response['message'] ?? 'Ancillaries failed to patch',
                    'error' => $response['error'],
                    'raw' => $response['raw'] ?? null,
                ], 422);
            }
        }
        Log::info(json_encode($response, JSON_PRETTY_PRINT));
        return response()->json($response, 200);
    }

    public function voidRequest(Request $request)
    {
        Log::info($request);
        $booking = FlightBookings::where('id', $request->bookingId)->first();
        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($this->isVoidWindowClosed($booking)) {
            return response()->json([
                'message' => 'Void is only available until midnight on the issuance date.',
            ], 422);
        }

        // Update booking status to voided
        $booking->status = 'requested';
        $booking->save();

        return response()->json([
            'message' => 'Booking voided successfully',
            'booking' => $booking,
        ]);


    }

    public function voidBooking(Request $request)
    {
        Log::info($request);

        $booking = FlightBookings::where('id', $request->bookingId)->first();
        if (!$booking) {
            return response()->json(['message' => 'Booking not found'], 404);
        }

        if ($this->isVoidWindowClosed($booking)) {
            return response()->json([
                'message' => 'Void is only available until midnight on the issuance date.',
            ], 422);
        }

        if ($request->flight_provider == 'sooper') {
            Log::info('Voiding booking via Sooper API', $request->all());
            $body = [
                'booking_uuid' => $request->booking_uuid,
                'billable_price' => $request->billable_price,
                'currency' => $request->currency,
            ];
            $res = $this->sooperApiService->voidSooperBooking($body);
            // Update status and PNR
            Log::info($booking->status);
            $booking->status = $request->booking_status;
            $booking->save();
            $this->providerBookingEventService->record(
                $booking,
                'sooper',
                'void',
                $res,
                $request->pnr,
            );
            return response()->json([
                'message' => 'Booking voided successfully',
                'booking' => $booking,
            ]);
        } else if ($request->flight_provider == 'travelport') {
            Log::info('Voiding booking via TravePort API', $request->all());
            $res= null;
            $pnr = $request->pnr;
            if($request->booking_source == 'NDC'){

                $res = $this->travelportApiService->voidReservation($pnr);
            } else if ($request->booking_source == 'GDS') {
                $res = $this->travelportApiService->voidGDSReservation($pnr);
            }

            if (!($res['success'] ?? false)) {
                return response()->json([
                    'message' => $res['error'] ?? 'Failed to void booking on Travelport',
                    'error_response' => $res['data'] ?? null,
                ], 422);
            }

            // Update status and PNR
            
            Log::info($booking->status);
            $booking->status = $request->booking_status;
            $booking->save();
            $this->providerBookingEventService->record(
                $booking,
                'travelport',
                'void',
                $res,
                $request->pnr,
            );
            return response()->json([
                'message' => 'Booking voided successfully',
                'booking' => $booking,
                'travelport_response' => $res['data'] ?? null,
            ]);
        }

    }

    private function isVoidWindowClosed(FlightBookings $booking): bool
    {
        if (!in_array(strtolower((string) $booking->status), ['ticketed', 'issued'], true)) {
            return true;
        }

        if (!$booking->issuance_date) {
            return true;
        }

        return now()->startOfDay()->greaterThan(
            Carbon::parse($booking->issuance_date)->startOfDay()
        );
    }

    public function getPublicBookings(Request $request)
    {
        Log::info($request);

        // Check that both email and refCode are provided
        if (!$request->email || !$request->refCode) {
            return response()->json([
                'message' => 'Both email and PNR must be provided',
                'bookings' => []
            ], 400); // 400 Bad Request
        }

        // Base query
        $query = FlightBookings::with('pessangers', 'user.agentData', 'priceSnapshot')
            ->where('main_email', $request->email)
            ->where('itinerary_ref', $request->refCode);
        Log::info($query->get());
        // // Check user role
        // if ($request->userRole === "customer") {
        //     $query->where('agent_id', $request->userId);
        // }

        // Filter by booking status
        if ($request->bookingFilter && $request->bookingFilter !== "all") {
            $query->where('status', $request->bookingFilter);
        }
        // Get bookings
        $bookings = $query->get();
        Log::info($bookings);

        return response()->json([
            'message' => 'success',
            'bookings' => $bookings
        ]);
    }



    public function sendMail(Request $request)
    {
        Log::info($request);

        $validated = $request->validate([
            'email' => 'required|email',
            'booking_id' => 'required|integer',
            'booking_status' => 'nullable|string',
        ]);
        $booking = FlightBookings::where('id', $validated['booking_id'])->first();
        if (!$booking) {
            return response()->json([
                'message' => 'Booking not found'
            ], 404);
        }

        $status = strtolower(
            (string) ($validated['booking_status'] ?? $booking->status ?? 'booked')
        );

        $this->sendBookingStatusMail($booking, $status, [$validated['email']]);

        return response()->json([
            'message' => 'Email sent successfully'
        ]);
    }


    public function assignTicketNumber(Request $request)
    {
        Log::info($request->all());

        $validated = $request->validate([
            'bookingId' => 'required|integer',
            'pessangers' => 'required|array',
            'pessangers.*.id' => 'required|integer',
            'pessangers.*.ticketNumber' => 'nullable|string',
        ]);

        foreach ($validated['pessangers'] as $p) {
            // Update passenger by ID
            FlightPassenger::where('id', $p['id'])
                ->update([
                    'ticketNumber' => $p['ticketNumber'], // can be null or string
                ]);
        }

        return response()->json([
            'message' => 'Ticket numbers updated successfully',
        ]);
    }


}
