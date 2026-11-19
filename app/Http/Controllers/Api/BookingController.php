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
use App\Models\ModifyRequest;
use App\Models\Passenger;
use App\Models\Segment;
use App\Models\Slice;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\AtApiService;
use App\Services\HakimiApiService;
use App\Services\AtPreBookingSsrLimitService;
use App\Services\AgentWalletBalanceService;
use App\Services\BookingPricingService;
use App\Services\Booking\BookingRequestService;
use App\Services\BookingVoidSettlementService;
use App\Services\PriceQuoteService;
use App\Services\ProviderBookingEventService;
use App\Transformers\AtAncillaryTransformer;

// use App\Services\SafepayService;
use Carbon\Carbon;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Mail;

class BookingController extends Controller
{
    /** AT may round its returned fare by up to one AED after CreateItinerary. */
    private const AT_AMOUNT_TOLERANCE = '1.00';

    protected $safepayService;

    protected $priceQuoteService;
    protected $agentWalletBalanceService;
    protected $bookingPricingService;
    protected $bookingVoidSettlementService;
    protected $bookingRequestService;
    protected $providerBookingEventService;
    protected $atPreBookingSsrLimitService;
    protected $hakimiApiService;

    public function __construct(
        PriceQuoteService $priceQuoteService,
        AgentWalletBalanceService $agentWalletBalanceService,
        BookingPricingService $bookingPricingService,
        BookingRequestService $bookingRequestService,
        BookingVoidSettlementService $bookingVoidSettlementService,
        ProviderBookingEventService $providerBookingEventService,
        AtPreBookingSsrLimitService $atPreBookingSsrLimitService,
        HakimiApiService $hakimiApiService,
    ) {
        // $this->safepayService = $safepayService;
        $this->priceQuoteService = $priceQuoteService;
        $this->agentWalletBalanceService = $agentWalletBalanceService;
        $this->bookingPricingService = $bookingPricingService;
        $this->bookingRequestService = $bookingRequestService;
        $this->bookingVoidSettlementService = $bookingVoidSettlementService;
        $this->providerBookingEventService = $providerBookingEventService;
        $this->atPreBookingSsrLimitService = $atPreBookingSsrLimitService;
        $this->hakimiApiService = $hakimiApiService;
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
        $query = FlightBookings::with(['pessangers', 'user.agentData', 'user.customer'])->orderBy('id', 'desc');
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
            $booking->itinerary_reference = $booking->itinerary_ref
                ?? data_get($pnrResponse, 'CreatePassengerNameRecordRS.ItineraryRef.ID');
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

    /** Return a Hakimi booking only to its owner or an administrator. */
    public function hakimiBookingDetails(Request $request)
    {
        $validated = $request->validate(['booking_id' => ['required', 'integer']]);
        $booking = FlightBookings::with(['pessangers', 'priceSnapshot', 'providerEvents'])
            ->findOrFail($validated['booking_id']);
        $user = $request->user();

        if (($user->role ?? null) !== 'admin' && (int) $booking->agent_id !== (int) $user->id) {
            abort(403, 'You are not allowed to view this booking.');
        }
        if (strtolower((string) $booking->flight_provider) !== 'hakimi') {
            abort(404, 'Hakimi booking not found.');
        }

        return response()->json([
            'booking' => $booking,
            'flight' => json_decode((string) $booking->flight_data, true) ?: [],
            'hakimi' => json_decode((string) $booking->pnr_response, true) ?: [],
        ]);
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

    private function ensureMainContactCountry(Request $request): void
    {
        $mainContact = $request->input('main_contact', []);

        if (!is_array($mainContact) || !empty($mainContact['country'])) {
            return;
        }

        $firstTraveller = $request->input('travellers.0', []);
        $fallbackCountry = is_array($firstTraveller)
            ? ($firstTraveller['nationality'] ?? $firstTraveller['issueCountry'] ?? null)
            : null;

        $mainContact['country'] = trim((string) ($fallbackCountry ?: 'PK'));

        $request->merge([
            'main_contact' => $mainContact,
        ]);

        Log::warning('Booking main_contact.country was missing; applied fallback before validation.', [
            'fallback_country' => $mainContact['country'],
            'flight_provider' => $request->input('flight_provider'),
            'agent_id' => $request->input('agent_id'),
        ]);
    }

    private function atPaymentFailureMessage(array $response): ?string
    {
        $messages = array_filter(array_map('strval', (array) ($response['Msg'] ?? [])));
        $message = $messages[0] ?? ($response['message'] ?? $response['error'] ?? null);
        $code = (string) ($response['Code'] ?? '');

        if (($response['status'] ?? true) === false) {
            return (string) ($message ?: 'Payment Failed !');
        }

        if ($code !== '' && $code !== '200' && $code !== '2000') {
            return (string) ($message ?: 'AT payment failed with code ' . $code);
        }

        if ($message && in_array(strtolower((string) $message), [
            'payment failed !',
            'payment failed',
            'insufficient subuserbalance',
        ], true)) {
            return (string) $message;
        }

        return null;
    }

    /**
     * AT confirms StartPay completion after CreateItinerary with this message.
     * Do not update the local ticket status for any other successful-looking
     * response, because AT may still be processing the reservation.
     */
    private function atStartPayCompleted(array $response): bool
    {
        // Payment callbacks may wrap the supplier payload in a `response` key.
        $response = is_array($response['response'] ?? null)
            ? $response['response']
            : $response;

        // AT uses 6033 to acknowledge that StartPay has accepted the booking
        // and it is being processed. This is a successful acknowledgement,
        // not a payment failure.
        if ((string) ($response['Code'] ?? '') === '6033') {
            return true;
        }

        $messages = $response['Msg'] ?? $response['message'] ?? [];

        foreach ((array) $messages as $message) {
            if (strcasecmp(trim((string) $message), 'BOOKING INPROGRESS !') === 0) {
                return true;
            }
        }

        return false;
    }

    public function store(Request $request)
    {
        $this->ensureMainContactCountry($request);

        $priceQuote = $this->loadBookingQuote($request);

        if (strtolower((string) $request->input('flight_provider')) === 'hakimi') {
            $this->validateHakimiBookingRequest($request);
        }

        $pnrResponse = null;
        $airlinePnr = null;
        $transactionId = null;
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
        if ($request->booking_status_setting == 1 && strtolower((string) $request->flight_provider) !== 'hakimi') {
            $pnr = json_encode(['local_pnr' => strtoupper(Str::random(6))]);
            $itineraryRef = json_decode($pnr, true)['local_pnr'];
            $pnrArray = json_decode($pnr, true);
            $localPnr = $pnrArray['local_pnr'];
            $flightData = json_encode($flightData);

        } else {
            switch ($request->flight_provider) {
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
                    // For AT, use the CRS PNR as the itinerary reference. The
                    // transaction ID remains a fallback if AT omits its CRSPNR.
                    $crsPnr = data_get($response, 'Trips.0.Journey.0.Segments.0.Flight.CRSPNR');
                    $crsPnr = is_string($crsPnr) ? trim($crsPnr) : null;

                    // RetrieveBooking only accepts this ID, not the PNR.
                    $transactionId = (string) $response['TransactionID'];

                    $itineraryRef = $crsPnr ?: (!empty($response['TransactionIDList'])
                        ? implode(',', $response['TransactionIDList'])
                        : $response['TransactionID']);

                    // Holdable fares were created with BookingType HB; place the
                    // hold now. Non-holdable fares are only saved, and both are
                    // ticketed later by StartPay (HP) on confirm.
                    if ($service->isHoldBooking($flightData, (array) $fareRefernce, $request->input('fareType'))) {
                        // StartPay must use AT's final CreateItinerary amount. This
                        // includes any locked SSRs and can legitimately be decimal.
                        $holdAmount = $response['NetAmount'] ?? $priceQuote?->provider_amount;
                        $expectedAmount = $this->atExpectedReservationAmount(
                            $priceQuote?->provider_amount,
                            $request->input('quote_ancillary_items', []),
                        );

                        if (
                            $expectedAmount !== null
                            && is_numeric($holdAmount)
                            && ! $this->atAmountsWithinTolerance($expectedAmount, (string) $holdAmount)
                        ) {
                            Log::warning('AT CreateItinerary amount differs from the locked quote by more than the allowed tolerance.', [
                                'transaction_id' => $response['TransactionID'],
                                'locked_amount' => $expectedAmount,
                                'supplier_amount' => $holdAmount,
                                'tolerance_aed' => self::AT_AMOUNT_TOLERANCE,
                            ]);
                        }

                        if (is_numeric($holdAmount) && (float) $holdAmount > 0) {
                            $atHoldResult = $service->holdReservation($response, (float) $holdAmount);

                            // CreateItinerary has no CRSPNR yet; RetrieveBooking
                            // after the hold returns it, so prefer that PNR.
                            $heldCrsPnr = $this->atCrsPnr($atHoldResult['booking_details'] ?? null);
                            if ($heldCrsPnr) {
                                $itineraryRef = $heldCrsPnr;
                            }
                            $airlinePnr = $this->atAirlinePnr($atHoldResult['booking_details'] ?? null);
                        } else {
                            Log::warning('AT hold skipped: the locked amount is invalid for StartPay.', [
                                'transaction_id' => $response['TransactionID'],
                                'provider_amount' => $holdAmount,
                            ]);
                        }
                    } else {
                        // A non-hold booking must not call StartPay until its
                        // payment is confirmed. Keep CreateItinerary's
                        // transaction ID as the supplier-payment fallback.
                        $localPnr = (string) $response['TransactionID'];
                    }

                    $pnrResponse = json_encode($response);
                    $flightData = json_encode($flightData);
                    break;
                case 'hakimi':
                    $localPnr = 'null';
                    $pnr = null;
                    $fare = $this->hakimiBookingFare($flightData, (array) $fareRefernce);
                    $passengerCounts = $fare['passenger_counts'] ?? [];
                    $finalReprice = $this->hakimiApiService->reprice(
                        (string) $fare['inventory_id'],
                        $passengerCounts,
                    );

                    if (!$priceQuote || bccomp((string) $priceQuote->provider_amount, (string) $finalReprice['totalFare'], 2) !== 0) {
                        Log::warning('Hakimi booking blocked because Reprice changed after checkout.', [
                            'inventory_id' => $fare['inventory_id'],
                            'quoted_total' => $priceQuote?->provider_amount,
                            'reprice_total' => $finalReprice['totalFare'] ?? null,
                        ]);
                        return response()->json([
                            'message' => 'The Hakimi fare changed. Please return to the flight results and continue with the latest price.',
                        ], 422);
                    }

                    $passengers = $this->hakimiPassengers($request->input('travellers', []));
                    if (!$this->hakimiPassengerCountsMatch($passengers, $passengerCounts)) {
                        return response()->json([
                            'message' => 'Passenger details no longer match the Hakimi repriced fare. Please return to checkout and try again.',
                        ], 422);
                    }

                    $response = $this->hakimiApiService->book(
                        (string) $fare['inventory_id'],
                        $passengers,
                        $this->hakimiContact($request->input('main_contact', []), $request->input('travellers', [])),
                    );
                    $itineraryRef = (string) $response['hakimiBookingNo'];
                    $airlinePnr = $response['airlinePnr'] ?? null;
                    $pnrResponse = json_encode($response);
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

        $lockedAdjustmentTotals = $this->lockedQuoteAdjustmentTotals($priceQuote);

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

        $isAtBooking = strtolower((string) $request->flight_provider) === 'at';
        $paymentExpiryTime = now()->addMinutes($isAtBooking ? 40 : 20);
        if ($isAtBooking) {
            $expiryTime = $paymentExpiryTime;
        }

        $flightBooking = FlightBookings::create([
            'main_email' => $validated['main_contact']['email'] ?? 'unknown@example.com',
            'main_phone' => $validated['main_contact']['phone'] ?? 'N/A',
            'main_country' => $validated['main_contact']['country'] ?? 'Unknown',
            'flight_data' => $flightData ?? '{}', // must never be null
            'pnr_response' => $pnrResponse, // must never be null
            'pnr' => $localPnr ?? null,
            'transaction_id' => $transactionId,
            'airline_pnr' => $airlinePnr ?? null,
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
            'segment_margin' => $lockedAdjustmentTotals['segment_margin']
                ?? ($validated['segment_margin'] ?? 0),
            'promotion_margin' => $lockedAdjustmentTotals['promotion']
                ?? ($validated['promotion_margin'] ?? 0),
            'fare_reference' => $request->fare_reference
                ? json_encode($request->fare_reference)
                : null,
            'itinerary_ref' => $itineraryRef ?? null,
            'expiry_time' => $expiryTime ?? null,
            'payment_expires_at' => $paymentExpiryTime,
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

        if (isset($atHoldResult)) {
            $this->providerBookingEventService->record(
                $flightBooking,
                'at',
                $atHoldResult['held'] ? 'hold' : 'hold_failed',
                $atHoldResult,
                $itineraryRef ?? null,
            );
        }


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
        if ($request->paymentMethod == 'hold' && config('app.booking_email_notifications', true)) {

            $admin = User::where('role', 'admin')->first();

            $flightDataForMail = json_decode($flightBooking->flight_data, true) ?? [];
            $recipients = array_values(array_unique(array_filter(array_map(
                static fn ($email) => strtolower(trim((string) $email)),
                [$flightBooking->agency_email, $admin?->email],
            ))));

            foreach ($recipients as $email) {
                if (!empty($email)) {
                    Mail::to($email)->queue(
                        (new BookingCreatedMail($email, $flightBooking, $flightDataForMail))->afterCommit()
                    );
                }
            }
        }
        $flightBooking = FlightBookings::with(['pessangers', 'user.agentData'])->where('id', $flightBooking->id)->first();
        $pnr = $flightBooking->itinerary_ref ?? $flightBooking->pnr ?? 'N/A';
        $notification = [
            'type' => 'booking_created',
            'heading' => 'New Flight Booking',
            'description' => "A flight booking was created for PNR {$pnr}.",
            'booking_id' => $flightBooking->id,
            'pnr' => $pnr,
            'url' => \App\Support\AdminNotificationLinks::booking($flightBooking),
        ];
        User::query()->where('role', 'admin')->each(
            fn (User $admin) => $admin->notify(new SystemNotification($notification))
        );
        // The booking owner must not receive an /admin/... link they can't open.
        $flightBooking->user?->notify(new SystemNotification(['url' => null] + $notification));
        Log::info('Expiry Time: ' . $expiryTime);
        CancelBookingJob::dispatch($flightBooking)->delay($expiryTime);

        // AT omits the CRS PNR right after booking/hold; fetch it in the
        // background and map it onto the booking once it is available.
        if ($isAtBooking && filled($flightBooking->transaction_id)) {
            \App\Jobs\SyncAtBookingPnrJob::dispatch($flightBooking->id)->delay(now()->addSeconds(10));
        }


        return response()->json([
            'message' => 'Booking confirmed successfully',
            'booking' => $flightBooking,
        ], 200);
    }

    // Helper functions

    /** Return signed AED legacy totals from trusted quote adjustments, never browser pricing. */
    private function lockedQuoteAdjustmentTotals($quote): array
    {
        if (!$quote) {
            return [];
        }

        return [
            'promotion' => (string) $quote->adjustments()
                ->where('type', 'promotion')
                ->sum('aed_amount'),
            'segment_margin' => (string) $quote->adjustments()
                ->where('type', 'segment_margin')
                ->sum('aed_amount'),
        ];
    }

    /**
     * Replace AT or Hakimi checkout price data with active server-side quote values.
     */
    private function loadBookingQuote(Request $request)
    {
        $requestedProvider = strtolower((string) $request->input('flight_provider'));

        if (!$request->filled('quote_id')) {
            if (in_array($requestedProvider, ['at', 'hakimi'], true)) {
                abort(422, 'A valid price quote is required before booking this flight.');
            }

            return null;
        }

        $quote = $this->priceQuoteService->findActive($request->string('quote_id')->toString(), $request->user());

        if ($requestedProvider !== '' && $requestedProvider !== strtolower((string) $quote->provider)) {
            abort(422, 'The selected flight provider does not match this price quote. Please start a new booking.');
        }

        $flight = $quote->flight_data;
        $providerPricing = $quote->provider_pricing_data ?? [];
        // AT provides this explicitly; non-AT providers (including Hakimi)
        // do not use a NetAmount field in their booking payload.
        $fareNetAmount = null;
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

    /** Return the locked AT fare plus selected SSR cost in provider currency. */
    private function atExpectedReservationAmount(mixed $fareAmount, mixed $ancillaryItems): ?string
    {
        if (!is_numeric($fareAmount)) {
            return null;
        }

        $total = bcadd((string) $fareAmount, '0', 8);

        foreach ((array) $ancillaryItems as $item) {
            $amount = is_array($item) ? ($item['provider_amount'] ?? null) : null;

            if (is_numeric($amount)) {
                $total = bcadd($total, (string) $amount, 8);
            }
        }

        return $total;
    }

    /** AT's post-booking total may vary by up to AED 1.00 due to supplier rounding. */
    private function atAmountsWithinTolerance(string $expected, string $actual): bool
    {
        if (!is_numeric($expected) || !is_numeric($actual)) {
            return false;
        }

        $difference = bcsub($expected, $actual, 8);
        $absoluteDifference = str_starts_with($difference, '-') ? substr($difference, 1) : $difference;

        return bccomp($absoluteDifference, self::AT_AMOUNT_TOLERANCE, 8) <= 0;
    }

    private function hakimiBookingFare(array $flight, array $fareReferences): array
    {
        $fare = collect(data_get($flight, 'leg.flights', []))
            ->flatMap(fn (array $flightLeg) => $flightLeg['fares'] ?? [])
            ->first(fn (array $candidate) => in_array($candidate['ref_id'] ?? null, $fareReferences, true));

        if (!is_array($fare) || blank($fare['inventory_id'] ?? null)) {
            abort(422, 'The Hakimi inventory is no longer available. Please search again.');
        }

        return $fare;
    }

    private function hakimiPassengers(array $travellers): array
    {
        return array_map(function (array $traveller): array {
            $type = match (strtoupper((string) ($traveller['type'] ?? 'ADT'))) {
                'ADT', 'ADULT' => 'adult',
                'CNN', 'CHD', 'CHILD', 'CHILDREN' => 'child',
                'INF', 'INFANT' => 'infant',
                default => abort(422, 'A Hakimi passenger has an unsupported traveller type.'),
            };
            $passenger = [
                'title' => $traveller['title'] ?? null,
                'firstName' => $traveller['firstName'] ?? null,
                'lastName' => $traveller['lastName'] ?? null,
                'type' => $type,
                'gender' => strtoupper((string) ($traveller['gender'] ?? '')),
                'dob' => $traveller['dob'] ?? null,
                'passportNo' => $traveller['documentNo'] ?? null,
                'passportExpiry' => $traveller['expiryDate'] ?? null,
                'nationality' => strtoupper((string) ($traveller['nationality'] ?? '')),
            ];

            return array_filter($passenger, static fn ($value) => $value !== null && $value !== '');
        }, $travellers);
    }

    private function hakimiPassengerCountsMatch(array $passengers, array $counts): bool
    {
        $actual = ['adult' => 0, 'child' => 0, 'infant' => 0];
        foreach ($passengers as $passenger) {
            if (isset($actual[$passenger['type'] ?? ''])) {
                $actual[$passenger['type']]++;
            }
        }

        return $actual['adult'] === (int) ($counts['ADT'] ?? 0)
            && $actual['child'] === (int) ($counts['CHD'] ?? 0)
            && $actual['infant'] === (int) ($counts['INF'] ?? 0);
    }

    private function validateHakimiBookingRequest(Request $request): void
    {
        Validator::make($request->all(), [
            'main_contact.email' => ['required', 'email'],
            'main_contact.phone' => ['required', 'string'],
            'travellers' => ['required', 'array', 'min:1'],
            'travellers.*.title' => ['required', 'string'],
            'travellers.*.firstName' => ['required', 'string'],
            'travellers.*.lastName' => ['required', 'string'],
            'travellers.*.type' => ['required', 'string'],
            'travellers.*.gender' => ['required', 'in:M,F'],
            'travellers.*.dob' => ['required', 'date'],
            'travellers.*.nationality' => ['required', 'string'],
            'travellers.*.documentNo' => ['nullable', 'string'],
            'travellers.*.expiryDate' => ['nullable', 'date'],
        ])->validate();
    }

    private function hakimiContact(array $contact, array $travellers): array
    {
        $lead = $travellers[0] ?? [];
        return [
            'name' => trim((string) (($lead['firstName'] ?? '') . ' ' . ($lead['lastName'] ?? ''))),
            'email' => $contact['email'] ?? null,
            'mobile' => $contact['phone'] ?? null,
        ];
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
                ->orWhere('transaction_id', $request->pnr)
                ->first();
        }

        return null;
    }

    /** AT's RetrieveBooking endpoint expects TransactionID rather than CRSPNR. */
    private function atTransactionId(?FlightBookings $booking): ?string
    {
        // `pnr` is overwritten with the airline PNR on confirm, so it cannot
        // be used here; read the stored transaction ID instead.
        $storedTransactionId = trim((string) ($booking?->transaction_id ?? ''));
        if ($storedTransactionId !== '') {
            return $storedTransactionId;
        }

        $response = json_decode((string) ($booking?->pnr_response ?? ''), true);
        $transactionId = is_array($response) ? ($response['TransactionID'] ?? null) : null;

        return is_scalar($transactionId) && (string) $transactionId !== ''
            ? (string) $transactionId
            : null;
    }

    /** Pull the customer-facing CRS PNR from AT's RetrieveBooking response. */
    private function atCrsPnr(mixed $pnrData): ?string
    {
        $crsPnr = data_get($pnrData, 'bookingDetails.Trips.0.Journey.0.Segments.0.Flight.CRSPNR')
            ?? data_get($pnrData, 'bookingDetails.Trips.0.Journey.0.Segments.0.Flight.CSRPNR');

        return is_string($crsPnr) && trim($crsPnr) !== '' ? trim($crsPnr) : null;
    }

    /** Pull AT's airline PNR from RetrieveBooking for the booking record. */
    private function atAirlinePnr(mixed $pnrData): ?string
    {
        $airlinePnr = data_get($pnrData, 'bookingDetails.Trips.0.Journey.0.Segments.0.Flight.AirlinePNR');

        return is_string($airlinePnr) && trim($airlinePnr) !== '' ? trim($airlinePnr) : null;
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
        $booking = $this->findBookingForProviderEvent($request);
        if ($request->flight_provider == 'at') {
            $atApiService = new AtApiService();
            // AT RetrieveBooking requires the supplier transaction ID, while
            // the booking list displays the CRS PNR in itinerary_ref.
            $atRequest = clone $request;
            $atRequest->merge([
                'pnr' => $this->atTransactionId($booking) ?? $request->pnr,
            ]);
            $pnrData = $atApiService->getBookingDetails($atRequest);

            $crsPnr = $this->atCrsPnr($pnrData);
            $airlinePnr = $this->atAirlinePnr($pnrData);
            if ($booking && ($crsPnr || $airlinePnr)) {
                // A manually maintained PNR is stored in both `pnr` and
                // `itinerary_ref`. Do not overwrite it each time AT's
                // RetrieveBooking endpoint is called; only seed an empty
                // legacy record from the supplier response.
                // The booking is created with AT's transaction ID as a
                // placeholder when CreateItinerary returns no CRSPNR, so
                // treat that placeholder like an empty value and replace it.
                $currentRef = trim((string) $booking->itinerary_ref);
                $placeholderRefs = array_filter(array_merge(
                    [trim((string) $this->atTransactionId($booking))],
                    array_map('trim', explode(',', (string) $booking->transaction_id)),
                ));
                if ($crsPnr && ($currentRef === '' || in_array($currentRef, $placeholderRefs, true))) {
                    $booking->itinerary_ref = $crsPnr;
                    $booking->pnr = $crsPnr;
                }
                if ($airlinePnr) {
                    $booking->airline_pnr = $airlinePnr;
                }
                $booking->save();
            }
        } else if (strtolower((string) $request->flight_provider) === 'hakimi') {
            $booking = $this->findBookingForProviderEvent($request);
            if (! $booking || strtolower((string) $booking->flight_provider) !== 'hakimi') {
                return response()->json(['message' => 'Hakimi booking not found.'], 404);
            }

            // The server always uses the stored provider reference, never a
            // booking number supplied by the browser.
            $pnrData = $this->hakimiApiService->bookingDetails((string) $booking->itinerary_ref);
        }

        if ($booking) {
            $this->providerBookingEventService->record(
                $booking,
                (string) ($request->flight_provider ?? $booking->flight_provider),
                'pnr_fetch',
                $pnrData,
                $booking->itinerary_ref ?? $request->pnr,
            );
        }

        return response()->json([
            'message' => 'PNR Details fetched successfully',
            'pnr' => $booking?->itinerary_ref,
            'data' => $pnrData
        ]);
    }


    public function cancelPnrDetails(Request $request)
    {
        Log::info($request);
        if (strtolower((string) ($request->booking_source ?? $request->flight_provider)) === "at") {
            $validated = $request->validate(["bookingId" => ["required", "integer"]]);
            $booking = FlightBookings::find($validated["bookingId"]);

            if (! $booking) {
                return response()->json(["message" => "Booking not found."], 404);
            }
            $this->ensureBookingOwnerOrManager($booking, $request->user());

            if (strtolower((string) $booking->flight_provider) !== "at") {
                return response()->json(["message" => "This is not an AT booking."], 422);
            }

            if (strtolower((string) $booking->status) === 'ticketed') {
                return response()->json([
                    "message" => "A ticketed booking must be voided before a cancellation email can be sent.",
                ], 422);
            }

            if (in_array(strtolower((string) $booking->status), ["canceled", "cancelled", "voided"], true)) {
                return response()->json(["message" => "This booking has already been cancelled."], 409);
            }

            $atBookingData = json_decode((string) $booking->pnr_response, true);
            $atBookingData = data_get($atBookingData, "bookingDetails") ?: $atBookingData;
            $atApiService = new AtApiService();
            $cancelResult = $atApiService->cancelBooking(
                is_array($atBookingData) ? $atBookingData : [],
                (string) $request->input("remarks", "Cancelled via Jetze"),
                (string) $request->ip(),
            );

            if (! ($cancelResult["success"] ?? false)) {
                return response()->json([
                    "message" => $cancelResult["message"] ?? "AT cancellation failed.",
                ], 422);
            }

            $providerName = "at";
            DB::transaction(function () use ($booking) {
                $booking->update(["status" => "canceled"]);
                $this->cancelOpenApplicationsForBooking(
                    $booking,
                    'The booking was cancelled directly before this request was reviewed.'
                );
            });
            $this->providerBookingEventService->record(
                $booking,
                $providerName,
                "cancel",
                $cancelResult["response"] ?? null,
                $request->pnr ?: $booking->itinerary_ref,
            );
            $this->sendBookingCanceledMail($booking);

            return response()->json([
                "message" => "Booking cancelled and notification queued successfully.",
                "booking" => $booking->fresh(),
            ]);
        } else if (strtolower((string) ($request->booking_source ?? $request->flight_provider)) === "hakimi") {
            $validated = $request->validate(["bookingId" => ["required", "integer"]]);
            $booking = FlightBookings::find($validated["bookingId"]);

            if (! $booking) {
                return response()->json(["message" => "Booking not found."], 404);
            }
            $this->ensureBookingOwnerOrManager($booking, $request->user());

            if (strtolower((string) $booking->flight_provider) !== "hakimi") {
                return response()->json(["message" => "This is not a Hakimi booking."], 422);
            }

            if (strtolower((string) $booking->status) === 'ticketed') {
                return response()->json([
                    "message" => "A ticketed booking must be voided before a cancellation email can be sent.",
                ], 422);
            }

            if (in_array(strtolower((string) $booking->status), ["canceled", "cancelled", "voided"], true)) {
                return response()->json(["message" => "This booking has already been cancelled."], 409);
            }

            DB::transaction(function () use ($booking) {
                $booking->update(["status" => "canceled"]);
                $this->cancelOpenApplicationsForBooking(
                    $booking,
                    'The booking was cancelled directly before this request was reviewed.'
                );
            });
            $this->providerBookingEventService->record(
                $booking,
                "hakimi",
                "cancel",
                ["source" => "local_hold_release"],
                $request->pnr ?: $booking->itinerary_ref,
            );
            $this->sendBookingCanceledMail($booking);

            return response()->json([
                "message" => "Booking cancelled and notification queued successfully.",
                "booking" => $booking->fresh(),
            ]);
        }

        return;

    }

    /** Normalize and de-duplicate email recipients. */
    private function normalizeEmailRecipients(array $emails): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            $emails,
        ))));
    }

    private function sendBookingCanceledMail($booking): void
    {
        if (!$booking || ! config('app.booking_email_notifications', true)) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $flightDataForMail = json_decode($booking->flight_data, true) ?? [];

        $recipients = $this->normalizeEmailRecipients([
            $booking->main_email,
            $booking->agency_email,
            $admin->email ?? null,
        ]);

        foreach ($recipients as $email) {
            Mail::to($email)->queue(
                (new BookingCanceledMail($email, $booking, $flightDataForMail))->afterCommit()
            );
        }
    }

    private function sendBookingStatusMail($booking, ?string $status = null, ?array $recipientEmails = null): void
    {
        if (!$booking || ! config('app.booking_email_notifications', true)) {
            return;
        }

        $status = strtolower((string) ($status ?? $booking->status ?? ''));
        if (!in_array($status, ['booked', 'canceled', 'cancelled', 'ticketed'], true)) {
            return;
        }

        $admin = User::where('role', 'admin')->first();
        $flightDataForMail = json_decode($booking->flight_data, true) ?? [];

        $recipients = $recipientEmails ?? [
            $booking->main_email,
            $booking->agency_email,
            $admin->email ?? null,
        ];

        $recipients = $this->normalizeEmailRecipients($recipients);

        foreach ($recipients as $email) {
            $mail = match ($status) {
                'canceled', 'cancelled' => new BookingCanceledMail($email, $booking, $flightDataForMail),
                'ticketed' => new BookingConfirmedMail($email, $booking, $flightDataForMail),
                default => new BookingCreatedMail($email, $booking, $flightDataForMail),
            };

            Mail::to($email)->queue($mail->afterCommit());
        }
    }

    public function confirmPnr(Request $request)
    {
        Log::info($request);
        $walletPaymentLock = null;
        $flightProvider = strtolower((string) ($request->input('flight_provider') ?? $request->input('booking_source')));

        if (in_array($flightProvider, ['at', 'hakimi'], true)) {
            $booking = FlightBookings::with('priceSnapshot')->find($request->bookingId);

            if (!$booking) {
                return response()->json(['message' => 'Booking not found.'], 404);
            }

            if ($request->user()?->role !== 'admin' && (int) $booking->agent_id !== (int) $request->user()?->id) {
                return response()->json(['message' => 'You cannot confirm this booking.'], 403);
            }
            $request->validate([
                'bookingId' => ['required', 'integer'],
                'booking_status' => ['required', 'string', 'in:ticketed'],
                'pnr' => ['nullable', 'string', 'max:255'],
            ]);


            if ($booking->payment_expires_at && ! $booking->payment_expires_at->isFuture()) {
                return response()->json([
                    'message' => 'This booking payment window has expired. Please create a new booking.',
                ], 422);
            }

            // Admins may perform operational confirmations without an agent wallet.
            // Every non-admin customer/agent must pass the server-side balance check.
            $isAdmin = $request->user()?->role === 'admin';
            if (!$isAdmin && !$this->agentWalletBalanceService->canPayForBooking($booking)) {
                return response()->json([
                    'message' => 'Your wallet balance is insufficient to confirm this booking.',
                ], 422);
            }

            if (strtolower((string) $booking->status) === 'ticketed') {
                return response()->json([
                    'message' => 'This booking has already been ticketed.',
                ], 409);
            }

            $walletPaymentLock = Cache::lock('wallet-confirm:agent:'.$booking->agent_id, 120);
            if (! $walletPaymentLock->get()) {
                return response()->json([
                    'message' => 'A wallet payment is already being processed for this agent.',
                ], 409);
            }
        }

        $res = null;
        if ($flightProvider === 'at') {

            // Never trust the amount (or reservation payload) posted by the
            // browser. The server-side price snapshot is the source of truth.
            $lockedProviderAmount = $booking->priceSnapshot?->provider_amount;
            $storedReservation = json_decode((string) $booking->pnr_response, true);
            if (!is_array($storedReservation) || empty($storedReservation)) {
                if ($walletPaymentLock) { $walletPaymentLock->release(); }
                return response()->json([
                    'message' => 'The stored AT reservation is unavailable.',
                ], 422);
            }

            $supplierPaymentAmount = data_get($storedReservation, 'NetAmount') ?? $lockedProviderAmount;
            if (!is_numeric($supplierPaymentAmount) || (float) $supplierPaymentAmount <= 0) {
                if ($walletPaymentLock) { $walletPaymentLock->release(); }
                return response()->json([
                    'message' => 'The locked booking amount is invalid for AT payment.',
                ], 422);
            }

            // Override all payment inputs with trusted booking values.
            $request->merge([
                // Use AT's stored final total so decimal fares and SSRs are paid
                // exactly as they were accepted by CreateItinerary.
                'net_amount' => (float) $supplierPaymentAmount,
                'pnrData' => $storedReservation,
                'FareType' => $storedReservation['FareType'] ?? $request->input('FareType'),
                // Held (HB) and non-held bookings are both ticketed with HP.
                'bookingType' => 'HP',
            ]);

            Log::info('AT wallet payment using locked booking values', [
                'booking_id' => $booking->id,
                'price_snapshot_id' => $booking->price_snapshot_id,
                'provider_amount' => $supplierPaymentAmount,
            ]);

            $atApiService = new AtApiService();
            Log::info('Booking held, skipping confirmation step.');
            $res = $atApiService->atPaymentRequest($request);

            Log::info($res);
            $res = json_decode($res, true);
            if ($res == null) {
                if ($walletPaymentLock) { $walletPaymentLock->release(); }
                return response()->json([
                    'message ' => 'Booking confirmation failed',
                    'error ' => 'No response from NDC WT API'
                ], 400);

            }

            // A repeated StartPay can return BOOKING INPROGRESS even with a
            // non-success code. It is still AT's completion acknowledgement,
            // so check it before treating the response as a payment failure.
            if (! $this->atStartPayCompleted($res)) {
                $atPaymentFailureMessage = $this->atPaymentFailureMessage($res);
                if ($atPaymentFailureMessage !== null) {
                    if ($walletPaymentLock) { $walletPaymentLock->release(); }
                    Log::warning('AT confirmation failed; booking status was not updated.', [
                        'booking_id' => $booking->id,
                        'code' => $res['Code'] ?? null,
                        'message' => $atPaymentFailureMessage,
                        'response' => $res,
                    ]);

                    return response()->json([
                        'message' => 'Booking confirmation failed',
                        'error' => $atPaymentFailureMessage,
                        'provider_response' => $res,
                    ], 400);
                }

                if ($walletPaymentLock) { $walletPaymentLock->release(); }

                Log::warning('AT StartPay did not confirm booking completion; booking status was not updated.', [
                    'booking_id' => $booking->id,
                    'code' => $res['Code'] ?? null,
                    'messages' => $res['Msg'] ?? null,
                    'response' => $res,
                ]);

                return response()->json([
                    'message' => 'AT booking is not yet confirmed.',
                    'provider_response' => $res,
                ], 409);
            }

            // Final step of AT's flow: GetItineraryStatus after StartPay (HP).
            $res['itinerary_status'] = $atApiService->itineraryStatusForReservation($storedReservation, $res);
        } elseif ($flightProvider === 'hakimi') {
            // Hakimi has already accepted the supplier booking before this
            // payment screen is reached. Wallet payment only confirms the
            // locally held booking; it must never call the AT payment API.
            $booking = FlightBookings::with('priceSnapshot')->find($request->bookingId);
            if (! $booking || ! $booking->priceSnapshot) {
                if ($walletPaymentLock) { $walletPaymentLock->release(); }

                return response()->json([
                    'message' => 'The locked Hakimi booking is unavailable for payment.',
                ], 422);
            }

            $request->merge([
                'pnr' => $booking->itinerary_ref,
            ]);
            $res = [
                'success' => true,
                'payment' => 'wallet',
                'hakimiBookingNo' => $booking->itinerary_ref,
            ];

            Log::info('Hakimi wallet payment confirmed using locked booking values.', [
                'booking_id' => $booking->id,
                'price_snapshot_id' => $booking->price_snapshot_id,
                'provider_amount' => $booking->priceSnapshot->provider_amount,
            ]);
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
        if ($walletPaymentLock) { $walletPaymentLock->release(); }
        }

        return response()->json([
            'message' => 'Booking confirmed successfully',
            // 'booking' => $booking,
        ]);
    }

    public function approveBooking(Request $request)
    {
        Log::info($request);
        $this->ensureBookingManager($request->user());
        $request->validate([
            'booking_id' => 'required|integer|exists:flight_bookings,id',
            'status' => 'required|string|in:pending,approved,rejected,confirmed,canceled,ticketed,booked,requested,voided',
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
            if (in_array(strtolower((string) $booking->status), ['canceled', 'cancelled', 'voided', 'refunded'], true)) {
                $this->cancelOpenApplicationsForBooking(
                    $booking,
                    'The booking status was changed directly before this request was reviewed.'
                );
            }

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

        $query = FlightBookings::with(['pessangers', 'user.customer'])
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
            $booking->itinerary_reference = $booking->itinerary_ref
                ?? data_get($pnrResponse, 'CreatePassengerNameRecordRS.ItineraryRef.ID');
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
        if ($request->flight_provider == 'AT') {
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
        if (strtolower($request->flight_provider) === 'at') {
            $atApiService = new AtApiService();
            $quote = null;

            if ($request->quote_id && $request->user()) {
                $quote = $this->priceQuoteService->findActive($request->quote_id, $request->user());
            }

            if ($quote) {
                $capability = $this->atPreBookingSsrLimitService->capabilityForQuote($quote);
                $requestFlags = $this->atPreBookingSsrLimitService->requestFlags($capability);
                $displayCurrency = $quote->display_currency;
                $providerCurrency = $quote->provider_currency;

                if (!$requestFlags['includeSSR'] && !$requestFlags['includeSeatLayout']) {
                    $response = $this->atPreBookingSsrLimitService->emptyAncillaries(
                        $capability,
                        $displayCurrency,
                        $providerCurrency,
                    );
                } else {
                    $rawResponse = $atApiService->fetchAncillaries([
                        ...$request->input('body', []),
                        ...$requestFlags,
                    ]);
                    $response = app(AtAncillaryTransformer::class)->transform(
                        $rawResponse,
                        $displayCurrency,
                        $providerCurrency,
                    );
                    $response = $this->atPreBookingSsrLimitService->apply($response, $capability);
                }
            } else {
                $rawResponse = $atApiService->fetchAncillaries($request->body);
                $response = app(AtAncillaryTransformer::class)->transform(
                    $rawResponse,
                    data_get($request->body, 'currency_code', 'AED'),
                    'AED',
                );
            }
        }

        return response()->json([
            'message' => 'Ancillaries fetched successfully',
            'ancillaries' => $response,
            'seatMap' => $promise1,
        ], 200);

    }

    public function voidRequest(Request $request)
    {
        $validated = $request->validate([
            'bookingId' => 'required|integer|exists:flight_bookings,id',
            'remarks' => 'nullable|string|max:1000',
        ]);
        $booking = FlightBookings::findOrFail($validated['bookingId']);
        $this->ensureBookingOwnerOrManager($booking, $request->user());

        $passengerIds = $booking->pessangers()
            ->whereNotIn('status', FlightBookings::SETTLED_PASSENGER_STATUSES)
            ->pluck('id')
            ->all();

        if ($passengerIds === []) {
            return response()->json(['message' => 'There are no passengers eligible for a void request.'], 422);
        }

        $bookingRequest = $this->bookingRequestService->submit(
            booking: $booking,
            user: $request->user(),
            type: 'void',
            passengerIds: $passengerIds,
            remarks: isset($validated['remarks']) ? trim($validated['remarks']) : null,
        );

        return response()->json([
            'message' => 'Void request submitted successfully.',
            'data' => $bookingRequest,
        ], 201);
    }

    public function voidBooking(Request $request)
    {
        $this->ensureBookingManager($request->user());
        Log::info($request);
        $bookingInput = $request->validate(['bookingId' => 'required|integer|exists:flight_bookings,id']);
        $booking = FlightBookings::findOrFail($bookingInput['bookingId']);

        // AT & Hakimi currently confirm a void locally until a supplier endpoint is available.
        // Financial settlement is created only after this local confirmation succeeds.
        if (in_array(strtolower((string) $booking->flight_provider), ['at', 'hakimi'], true)) {
            $validated = $request->validate([
                'void_charge_aed' => 'required|numeric|min:0',
                'void_date' => 'required|date',
                'void_description' => 'required|string|max:2000',
            ]);

            $voidSnapshot = $this->bookingVoidSettlementService->settleAtWalletVoid(
                $booking,
                (string) $validated['void_charge_aed'],
                $validated['void_date'],
                $validated['void_description'],
                $request->user()?->id,
            );

            // AT voiding is the completed cancellation path. Notify the same
            // recipients as other cancelled bookings only after settlement
            // has successfully changed the booking status to "voided".
            $booking = $booking->fresh();
            $this->cancelOpenApplicationsForBooking(
                $booking,
                'The booking was voided directly before this request was reviewed.'
            );
            $this->sendBookingCanceledMail($booking);

            return response()->json([
                'message' => 'Booking voided and wallet settlement completed successfully.',
                'booking' => $booking->fresh('priceSnapshot'),
                'void_snapshot' => $voidSnapshot,
            ]);
        }

        return response()->json([
            'message' => 'Voiding is not supported for this flight provider.',
        ], 422);
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

    /** Update the manually maintained ticket PNR or one passenger's e-ticket number. */
    public function updateTicketDetails(Request $request, FlightBookings $booking)
    {
        $validated = $request->validate([
            'pnr' => ['sometimes', 'nullable', 'string', 'max:255'],
            'itinerary_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'passenger_id' => ['required_without_all:pnr,itinerary_ref', 'nullable', 'integer'],
            'ticket_number' => ['required_with:passenger_id', 'nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        if (! $user || ($user->role !== 'admin' && (int) $booking->agent_id !== (int) $user->id)) {
            abort(403, 'You are not allowed to update this ticket.');
        }

        if (array_key_exists('pnr', $validated) || array_key_exists('itinerary_ref', $validated)) {
            $pnr = $validated['pnr'] ?? $validated['itinerary_ref'];
            $pnr = filled($pnr) ? trim($pnr) : null;

            // Keep both legacy PNR columns aligned. Airline PNR is a separate
            // supplier value and must never be changed by this editor.
            $booking->pnr = $pnr;
            $booking->itinerary_ref = $pnr;
            $booking->save();
        }

        if (array_key_exists('passenger_id', $validated)) {
            $passenger = $booking->pessangers()
                ->whereKey($validated['passenger_id'])
                ->firstOrFail();

            $passenger->ticketNumber = filled($validated['ticket_number'])
                ? trim($validated['ticket_number'])
                : null;
            $passenger->save();
        }

        return response()->json([
            'message' => 'Ticket details updated successfully.',
            'booking' => $booking->fresh('pessangers'),
        ]);
    }

    private function canManageBookings(?User $user): bool
    {
        return (bool) $user && ($user->role === 'admin' || $user->can('manage-bookings'));
    }

    private function ensureBookingManager(?User $user): void
    {
        abort_unless($this->canManageBookings($user), 403, 'Only administrators can perform this booking action.');
    }

    private function ensureBookingOwnerOrManager(FlightBookings $booking, ?User $user): void
    {
        abort_unless(
            $this->canManageBookings($user) || ($user && (int) $booking->agent_id === (int) $user->id),
            403,
            'You are not allowed to manage this booking.'
        );
    }

    /** Close pending applications when a booking is changed outside their review flow. */
    private function cancelOpenApplicationsForBooking(FlightBookings $booking, string $reason): void
    {
        $this->bookingRequestService->cancelOpenRequestsForBooking($booking, $reason);

        ModifyRequest::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }


}
