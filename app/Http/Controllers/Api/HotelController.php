<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPrebook;
use App\Models\HotelPriceQuote;
use App\Models\HotelSearchSession;
use App\Models\TboHotel;
use App\Models\TboHotelCity;
use App\Models\TboHotelCountry;
use App\Services\TboHotelService;
use App\Services\CurrencyConversionService;
use App\Services\HotelPriceQuoteService;
use App\Services\HotelBookingPricingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class HotelController extends Controller
{
    public function __construct(
        protected TboHotelService $tboHotelService,
        protected CurrencyConversionService $currencyConversionService,
        protected HotelPriceQuoteService $hotelPriceQuoteService,
        protected HotelBookingPricingService $hotelBookingPricingService,
    ) {}

    public function suggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            $cities = TboHotelCity::with('country')
                ->withCount('hotels')
                ->has('hotels')
                ->orderByDesc('hotels_count')
                ->orderBy('name')
                ->limit(8)
                ->get();

            return response()->json([
                'data' => $cities->map(fn (TboHotelCity $city) => $this->citySuggestion($city))->values(),
            ]);
        }

        $likeQuery = '%'.$query.'%';

        $cities = TboHotelCity::with('country')
            ->where(function ($builder) use ($likeQuery, $query) {
                $builder->where('name', 'like', $likeQuery)
                    ->orWhere('city_code', $query);
            })
            ->limit(6)
            ->get()
            ->map(fn (TboHotelCity $city) => $this->citySuggestion($city));

        $hotels = TboHotel::query()
            ->where(function ($builder) use ($likeQuery, $query) {
                $builder->where('hotel_name', 'like', $likeQuery)
                    ->orWhere('city_name', 'like', $likeQuery)
                    ->orWhere('country_name', 'like', $likeQuery)
                    ->orWhere('address', 'like', $likeQuery)
                    ->orWhere('hotel_code', $query)
                    ->orWhere('search_text', 'like', $likeQuery);
            })
            ->orderBy('hotel_name')
            ->limit(7)
            ->get()
            ->map(fn (TboHotel $hotel) => $this->hotelSuggestion($hotel));

        $countries = TboHotelCountry::query()
            ->where(function ($builder) use ($likeQuery, $query) {
                $builder->where('name', 'like', $likeQuery)
                    ->orWhere('code', strtoupper($query));
            })
            ->limit(3)
            ->get()
            ->map(fn (TboHotelCountry $country) => $this->countrySuggestion($country));

        return response()->json([
            'data' => $cities->concat($hotels)->concat($countries)->take(15)->values(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination.type' => 'required|string|in:country,city,hotel',
            'destination.value' => 'required|string',
            'destination.label' => 'nullable|string',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guest_nationality' => 'required|string|size:2',
            'rooms' => 'required|array|min:1|max:8',
            'rooms.*.adults' => 'required|integer|min:1|max:8',
            'rooms.*.children' => 'required|integer|min:0|max:4',
            'rooms.*.children_ages' => 'array',
            'filters.refundable' => 'nullable|boolean',
            'filters.no_of_rooms' => 'nullable|integer|min:0',
            'filters.meal_type' => 'nullable|string|in:All,WithMeal,RoomOnly',
            'currency_code' => 'nullable|string|size:3',
        ]);

        $this->validateChildrenAges($validated['rooms']);
        $displayCurrency = $this->currencyCodeForRequest($request, $validated['currency_code'] ?? null);

        $destination = $validated['destination'];
        $hotelCodes = $this->resolveHotelCodes($destination['type'], $destination['value']);

        if ($hotelCodes->isEmpty() && $destination['type'] === 'city') {
            try {
                $this->syncCityHotels($destination['value']);
                $hotelCodes = $this->resolveHotelCodes($destination['type'], $destination['value']);
            } catch (RuntimeException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'data' => [
                        'hotels' => [],
                        'search_session_id' => null,
                    ],
                ], 422);
            } catch (Throwable $e) {
                Log::error('Unable to sync city hotels on demand', [
                    'city_code' => $destination['value'],
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Unable to fetch hotel codes for this city right now. Please try again.',
                    'data' => [
                        'hotels' => [],
                        'search_session_id' => null,
                    ],
                ], 502);
            }
        }

        if ($hotelCodes->isEmpty()) {
            return response()->json([
                'message' => $destination['type'] === 'country'
                    ? 'Please select a city or hotel. Searching a whole country needs too many TBO hotel codes.'
                    : 'No TBO hotel codes found for the selected destination.',
                'data' => [
                    'hotels' => [],
                    'search_session_id' => null,
                ],
            ], 422);
        }

        $paxRooms = collect($validated['rooms'])->map(function (array $room) {
            return [
                'Adults' => (int) $room['adults'],
                'Children' => (int) $room['children'],
                'ChildrenAges' => array_values($room['children_ages'] ?? []),
            ];
        })->values()->all();

        $tboRequest = [
            'CheckIn' => Carbon::parse($validated['check_in'])->toDateString(),
            'CheckOut' => Carbon::parse($validated['check_out'])->toDateString(),
            'HotelCodes' => $hotelCodes->implode(','),
            'GuestNationality' => strtoupper($validated['guest_nationality']),
            'PaxRooms' => $paxRooms,
            'ResponseTime' => (float) config('tbohotel.timeout_search', 23),
            'IsDetailedResponse' => false,
            'Filters' => [
                'Refundable' => (bool) data_get($validated, 'filters.refundable', false),
                'NoOfRooms' => (int) data_get($validated, 'filters.no_of_rooms', 0),
                'MealType' => data_get($validated, 'filters.meal_type', 'All'),
            ],
        ];

        try {
            $tboResponse = $this->tboHotelService->search($tboRequest);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (Throwable $e) {
            Log::error('Unable to search TBO hotels', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Hotel provider search failed. Please try again.',
            ], 502);
        }

        $session = HotelSearchSession::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => optional($request->user())->id,
            'destination_type' => $destination['type'],
            'destination_code' => $destination['value'],
            'destination_label' => $destination['label'] ?? null,
            'check_in' => $tboRequest['CheckIn'],
            'check_out' => $tboRequest['CheckOut'],
            'guest_nationality' => $tboRequest['GuestNationality'],
            'pax_rooms' => $paxRooms,
            'tbo_request' => $tboRequest,
            'tbo_response' => $tboResponse,
            'expires_at' => now()->addMinutes(30),
        ]);

        $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
        $hotels = $statusCode === 200
            ? $this->normalizeSearchResults($tboResponse, $displayCurrency)
            : collect();

        return response()->json([
            'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => [
                'search_session_id' => $session->uuid,
                'hotels' => $hotels->values(),
            ],
        ], $this->httpStatusForProviderStatus($statusCode));
    }

    public function prebook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_session_id' => 'required|uuid',
            'booking_code' => 'required|string|max:1024',
            'payment_mode' => 'required|string|in:Limit',
            'currency_code' => 'nullable|string|size:3',
        ]);

        $session = HotelSearchSession::where('uuid', $validated['search_session_id'])->first();

        if (! $session) {
            return response()->json(['message' => 'Hotel search session was not found. Please search again.'], 404);
        }

        if ($session->expires_at?->isPast()) {
            return response()->json(['message' => 'Hotel search session has expired. Please search again.'], 422);
        }

        if ($session->user_id && $session->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel search session belongs to another user.'], 403);
        }

        $selection = $this->findSearchSelection($session, $validated['booking_code']);

        if (! $selection) {
            return response()->json(['message' => 'The selected room is not part of this hotel search. Please search again.'], 422);
        }

        try {
            $tboResponse = $this->tboHotelService->preBook([
                'BookingCode' => $validated['booking_code'],
                'PaymentMode' => $validated['payment_mode'],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        } catch (Throwable $e) {
            Log::error('Unable to prebook TBO hotel room', [
                'search_session_id' => $session->uuid,
                'hotel_code' => $selection['hotel_code'],
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Hotel provider could not revalidate this room. Please try again.'], 502);
        }

        $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);

        if ($statusCode !== 200) {
            return response()->json([
                'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
                'provider_status' => data_get($tboResponse, 'Status'),
            ], $this->httpStatusForProviderStatus($statusCode));
        }

        $prebook = DB::transaction(function () use ($session, $selection, $validated, $statusCode, $tboResponse, $request) {
            $prebook = HotelPrebook::create([
                'uuid' => (string) Str::uuid(),
                'hotel_search_session_id' => $session->id,
                'user_id' => $session->user_id,
                'hotel_code' => $selection['hotel_code'],
                'booking_code' => $validated['booking_code'],
                'payment_mode' => $validated['payment_mode'],
                'provider_status_code' => $statusCode,
                'search_room' => $selection['room'],
                'tbo_response' => $tboResponse,
                'expires_at' => $session->expires_at,
            ]);

            $providerRoom = $this->prebookProviderRoom($prebook);
            $providerAmount = $providerRoom['TotalFare'] ?? null;
            $providerCurrency = strtoupper((string) ($providerRoom['Currency'] ?? ''));

            if (! is_numeric($providerAmount) || ! preg_match('/^[A-Z]{3}$/', $providerCurrency)) {
                throw ValidationException::withMessages([
                    'prebook' => 'The provider did not return a valid final fare and currency. Please search again.',
                ]);
            }

            $this->hotelPriceQuoteService->create(
                $prebook,
                $providerAmount,
                $providerCurrency,
                $this->currencyCodeForRequest($request, $validated['currency_code'] ?? null),
            );

            HotelBookingEvent::create([
                'hotel_prebook_id' => $prebook->id,
                'provider' => 'tbo',
                'stage' => 'prebook',
                'provider_status_code' => $statusCode,
                'provider_reference' => Str::limit($prebook->booking_code, 255, ''),
                'request_data' => [
                    'BookingCode' => $validated['booking_code'],
                    'PaymentMode' => $validated['payment_mode'],
                ],
                'response_data' => $tboResponse,
                'occurred_at' => now(),
            ]);

            return $prebook;
        });

        $prebook->load('searchSession', 'priceQuote');

        return response()->json([
            'message' => 'Room price and availability confirmed.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => $this->normalizePrebook($prebook),
        ]);
    }

    public function showPrebook(Request $request, string $prebookUuid): JsonResponse
    {
        $prebook = HotelPrebook::with(['searchSession', 'priceQuote'])
            ->where('uuid', $prebookUuid)
            ->first();

        if (! $prebook) {
            return response()->json(['message' => 'Hotel prebook was not found. Please search again.'], 404);
        }

        if ($prebook->expires_at?->isPast()) {
            return response()->json(['message' => 'Hotel prebook has expired. Please search again.'], 422);
        }

        if ($prebook->user_id && $prebook->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel prebook belongs to another user.'], 403);
        }

        return response()->json([
            'provider_status' => data_get($prebook->tbo_response, 'Status'),
            'data' => $this->normalizePrebook($prebook),
        ]);
    }

    public function book(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prebook_id' => 'required|uuid',
            'email' => 'required|email:rfc,dns|max:255',
            'phone_number' => 'required|string|max:64',
            'customer_details' => 'required|array|min:1|max:8',
            'customer_details.*.customer_names' => 'required|array|min:1|max:12',
            'customer_details.*.customer_names.*.title' => 'required|string|in:Mr,Mrs,Ms',
            'customer_details.*.customer_names.*.first_name' => 'required|string|max:100',
            'customer_details.*.customer_names.*.last_name' => 'required|string|max:100',
            'customer_details.*.customer_names.*.type' => 'required|string|in:Adult,Child',
        ]);

        $prebook = HotelPrebook::with(['searchSession', 'priceQuote'])
            ->where('uuid', $validated['prebook_id'])
            ->first();

        if (! $prebook) {
            return response()->json(['message' => 'Hotel prebook was not found. Please search again.'], 404);
        }

        if ($prebook->expires_at?->isPast()) {
            return response()->json(['message' => 'Hotel prebook has expired. Please search again.'], 422);
        }

        if ($prebook->user_id && $prebook->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel prebook belongs to another user.'], 403);
        }

        $this->validateBookingGuests($prebook, $validated['customer_details']);

        $providerRoom = $this->prebookProviderRoom($prebook);
        $bookingCode = $providerRoom['BookingCode'] ?? $prebook->booking_code;

        if (! $bookingCode) {
            return response()->json(['message' => 'The final booking code is unavailable. Please prebook the room again.'], 422);
        }

        $customerDetails = collect($validated['customer_details'])
            ->map(fn (array $room) => [
                'CustomerNames' => collect($room['customer_names'])
                    ->map(fn (array $guest) => [
                        'Title' => $guest['title'],
                        'FirstName' => $guest['first_name'],
                        'LastName' => $guest['last_name'],
                        'Type' => $guest['type'],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        $reservation = DB::transaction(function () use ($prebook, $validated, $customerDetails, $bookingCode) {
            $lockedPrebook = HotelPrebook::lockForUpdate()->findOrFail($prebook->id);
            $lockedQuote = HotelPriceQuote::where('hotel_prebook_id', $lockedPrebook->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedQuote || $lockedQuote->expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'prebook_id' => 'This final hotel quote has expired. Please search and prebook again.',
                ]);
            }

            $existingBooking = HotelBooking::where('hotel_prebook_id', $lockedPrebook->id)
                ->lockForUpdate()
                ->first();

            if ($existingBooking) {
                return ['booking' => $existingBooking, 'created' => false];
            }

            $bookingUuid = (string) Str::uuid();
            $clientReferenceId = 'HB-' . Str::upper(Str::random(20));
            $bookingReferenceId = 'HBR-' . Str::upper(Str::random(20));
            $tboRequest = [
                'BookingCode' => $bookingCode,
                'CustomerDetails' => $customerDetails,
                'ClientReferenceId' => $clientReferenceId,
                'BookingReferenceId' => $bookingReferenceId,
                // TBO must receive its own locked PreBook amount, never a converted display amount.
                'TotalFare' => (float) $lockedQuote->provider_amount,
                'EmailId' => $validated['email'],
                'PhoneNumber' => $validated['phone_number'],
                'BookingType' => 'Voucher',
                'PaymentMode' => $lockedPrebook->payment_mode,
            ];

            $booking = HotelBooking::create([
                'uuid' => $bookingUuid,
                'hotel_prebook_id' => $lockedPrebook->id,
                'user_id' => $lockedPrebook->user_id,
                'status' => 'processing',
                'client_reference_id' => $clientReferenceId,
                'booking_reference_id' => $bookingReferenceId,
                'currency' => $lockedQuote->provider_currency,
                'total_fare' => $lockedQuote->provider_amount,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'customer_details' => $customerDetails,
                'tbo_request' => $tboRequest,
            ]);

            $booking->guests()->createMany(
                collect($validated['customer_details'])
                    ->flatMap(fn (array $room, int $roomIndex) => collect($room['customer_names'])
                        ->map(fn (array $guest, int $guestIndex) => [
                            'room_index' => $roomIndex,
                            'guest_index' => $guestIndex,
                            'type' => $guest['type'],
                            'title' => $guest['title'],
                            'first_name' => $guest['first_name'],
                            'last_name' => $guest['last_name'],
                            'raw_data' => $guest,
                        ]))
                    ->values()
                    ->all(),
            );

            HotelBookingEvent::create([
                'hotel_prebook_id' => $lockedPrebook->id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'book_request',
                'provider_reference' => $bookingReferenceId,
                // Guest PII is retained only on the booking and guest records, not duplicated in the event log.
                'request_data' => [
                    'BookingCode' => $bookingCode,
                    'ClientReferenceId' => $clientReferenceId,
                    'BookingReferenceId' => $bookingReferenceId,
                    'TotalFare' => $lockedQuote->provider_amount,
                    'PaymentMode' => $lockedPrebook->payment_mode,
                ],
                'occurred_at' => now(),
            ]);

            return [
                'booking' => $booking,
                'quote' => $lockedQuote,
                'created' => true,
            ];
        });

        /** @var HotelBooking $booking */
        $booking = $reservation['booking'];

        if (! $reservation['created']) {
            $httpStatus = $booking->status === 'confirmed' ? 200 : 409;

            return response()->json([
                'message' => $booking->status === 'confirmed'
                    ? 'This prebook has already been booked.'
                    : 'A booking request for this prebook is already being resolved. Do not submit it again.',
                'data' => $this->normalizeBooking($booking),
            ], $httpStatus);
        }

        try {
            $tboResponse = $this->tboHotelService->book($booking->tbo_request);
        } catch (Throwable $e) {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'unknown']);

                HotelBookingEvent::create([
                    'hotel_prebook_id' => $booking->hotel_prebook_id,
                    'hotel_booking_id' => $booking->id,
                    'provider' => 'tbo',
                    'stage' => 'book_transport_error',
                    'provider_reference' => $booking->booking_reference_id,
                    'response_data' => ['message' => 'No response was received from the provider.'],
                    'occurred_at' => now(),
                ]);
            });

            Log::error('TBO hotel booking request did not receive a response', [
                'hotel_booking_id' => $booking->uuid,
                'booking_reference_id' => $booking->booking_reference_id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'The booking result is unknown. Do not submit again; verify it using the booking reference.',
                'booking_reference_id' => $booking->booking_reference_id,
            ], 502);
        }

        $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
        $isConfirmed = $statusCode === 200;
        DB::transaction(function () use ($booking, $reservation, $isConfirmed, $statusCode, $tboResponse) {
            $booking->update([
                'status' => $isConfirmed ? 'confirmed' : 'failed',
                'provider_status_code' => $statusCode,
                'confirmation_number' => data_get($tboResponse, 'ConfirmationNumber'),
                'hotel_confirmation_number' => data_get($tboResponse, 'HotelConfirmationNumber'),
                'tbo_response' => $tboResponse,
            ]);

            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'book',
                'provider_status_code' => $statusCode,
                'provider_reference' => data_get($tboResponse, 'ConfirmationNumber') ?: $booking->booking_reference_id,
                'response_data' => $tboResponse,
                'occurred_at' => now(),
            ]);

            if ($isConfirmed) {
                $this->hotelBookingPricingService->createSnapshot($booking, $reservation['quote']);
            }
        });

        if (! $isConfirmed) {
            return response()->json([
                'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
                'provider_status' => data_get($tboResponse, 'Status'),
                'data' => $this->normalizeBooking($booking->fresh()),
            ], $this->httpStatusForProviderStatus($statusCode));
        }

        return response()->json([
            'message' => 'Hotel booking confirmed successfully.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => $this->normalizeBooking($booking->fresh()),
        ]);
    }

    public function bookings(Request $request): JsonResponse
    {
        $bookings = HotelBooking::with(['prebook.searchSession', 'priceSnapshot'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $bookings->getCollection()
                ->map(fn (HotelBooking $booking) => $this->normalizeBooking($booking))
                ->values(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function adminBookings(Request $request): JsonResponse
    {
        $this->ensureHotelBookingPermission($request, 'view-bookings');

        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'status' => 'nullable|string|max:32',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));

        $bookings = HotelBooking::with(['user', 'prebook.searchSession', 'priceSnapshot'])
            ->when($status !== '' && $status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->when($query !== '', function ($builder) use ($query) {
                $likeQuery = '%'.$query.'%';

                $builder->where(function ($bookingQuery) use ($likeQuery) {
                    $bookingQuery->where('uuid', 'like', $likeQuery)
                        ->orWhere('booking_reference_id', 'like', $likeQuery)
                        ->orWhere('confirmation_number', 'like', $likeQuery)
                        ->orWhere('hotel_confirmation_number', 'like', $likeQuery)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', $likeQuery)
                            ->orWhere('email', 'like', $likeQuery))
                        ->orWhereHas('prebook', fn ($prebookQuery) => $prebookQuery
                            ->where('hotel_code', 'like', $likeQuery));
                });
            })
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => $bookings->getCollection()
                ->map(fn (HotelBooking $booking) => $this->normalizeAdminBooking($booking))
                ->values(),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function adminShowBooking(Request $request, string $bookingUuid): JsonResponse
    {
        $this->ensureHotelBookingPermission($request, 'view-bookings');

        $booking = HotelBooking::with([
            'user',
            'prebook.searchSession',
            'priceSnapshot',
            'guests',
            'events' => fn ($query) => $query->latest('occurred_at'),
        ])->where('uuid', $bookingUuid)->first();

        if (! $booking) {
            return response()->json(['message' => 'Hotel booking was not found.'], 404);
        }

        return response()->json(['data' => $this->normalizeAdminBooking($booking, true)]);
    }

    public function adminRefreshBookingDetails(Request $request, string $bookingUuid): JsonResponse
    {
        $this->ensureHotelBookingPermission($request, 'manage-bookings');

        return $this->refreshBookingDetails($request, $bookingUuid);
    }

    public function adminCancelBooking(Request $request, string $bookingUuid): JsonResponse
    {
        $this->ensureHotelBookingPermission($request, 'manage-bookings');

        return $this->cancelBooking($request, $bookingUuid);
    }

    public function showBooking(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = HotelBooking::with(['prebook.searchSession', 'priceSnapshot', 'guests'])
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Hotel booking was not found.'], 404);
        }

        if ($booking->user_id && $booking->user_id !== optional($request->user())->id && ! $this->canViewHotelBookings($request)) {
            return response()->json(['message' => 'This hotel booking belongs to another user.'], 403);
        }

        return response()->json(['data' => $this->normalizeBooking($booking, true)]);
    }

    public function refreshBookingDetails(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = HotelBooking::with('prebook.searchSession')
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Hotel booking was not found.'], 404);
        }

        if ($booking->user_id && $booking->user_id !== optional($request->user())->id && ! $this->canManageHotelBookings($request)) {
            return response()->json(['message' => 'This hotel booking belongs to another user.'], 403);
        }

        $payload = [
            'PaymentMode' => $booking->prebook->payment_mode,
        ];

        if ($booking->confirmation_number) {
            $payload['ConfirmationNumber'] = $booking->confirmation_number;
        } else {
            $payload['BookingReferenceId'] = $booking->booking_reference_id;
        }

        HotelBookingEvent::create([
            'hotel_prebook_id' => $booking->hotel_prebook_id,
            'hotel_booking_id' => $booking->id,
            'provider' => 'tbo',
            'stage' => 'detail_request',
            'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
            'request_data' => $payload,
            'occurred_at' => now(),
        ]);

        try {
            $tboResponse = $this->tboHotelService->bookingDetail($payload);
        } catch (Throwable $e) {
            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'detail_transport_error',
                'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
                'response_data' => ['message' => 'No response was received from the provider.'],
                'occurred_at' => now(),
            ]);

            Log::error('Unable to retrieve TBO hotel booking details', [
                'hotel_booking_id' => $booking->uuid,
                'booking_reference_id' => $booking->booking_reference_id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to retrieve booking details from the hotel provider.'], 502);
        }

        $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
        DB::transaction(function () use ($booking, $statusCode, $tboResponse) {
            $booking->update([
                'tbo_booking_detail_response' => $tboResponse,
                'booking_details_checked_at' => now(),
            ]);

            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'detail',
                'provider_status_code' => $statusCode,
                'provider_reference' => $booking->confirmation_number ?: $booking->booking_reference_id,
                'response_data' => $tboResponse,
                'occurred_at' => now(),
            ]);
        });

        if ($statusCode !== 200) {
            return response()->json([
                'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
                'provider_status' => data_get($tboResponse, 'Status'),
                'data' => $this->normalizeBooking($booking->fresh(), true),
            ], $this->httpStatusForProviderStatus($statusCode));
        }

        $details = data_get($tboResponse, 'BookingDetail', []);
        $providerBookingStatus = data_get($details, 'BookingStatus');
        $booking->update([
            'status' => $this->localStatusForProviderBooking($providerBookingStatus, $booking->status),
            'confirmation_number' => data_get($details, 'ConfirmationNumber') ?? $booking->confirmation_number,
            'hotel_confirmation_number' => data_get($details, 'HotelConfirmationNumber') ?? $booking->hotel_confirmation_number,
        ]);

        return response()->json([
            'message' => 'Booking details updated successfully.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => $this->normalizeBooking($booking->fresh(), true),
        ]);
    }

    public function cancelBooking(Request $request, string $bookingUuid): JsonResponse
    {
        $booking = HotelBooking::with('prebook.searchSession')
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Hotel booking was not found.'], 404);
        }

        if ($booking->user_id && $booking->user_id !== optional($request->user())->id && ! $this->canManageHotelBookings($request)) {
            return response()->json(['message' => 'This hotel booking belongs to another user.'], 403);
        }

        $cancellation = DB::transaction(function () use ($booking) {
            $lockedBooking = HotelBooking::lockForUpdate()->findOrFail($booking->id);

            if ($lockedBooking->status === 'cancelled') {
                return ['booking' => $lockedBooking, 'state' => 'cancelled'];
            }

            if ($lockedBooking->status === 'cancellation_pending') {
                return ['booking' => $lockedBooking, 'state' => 'pending'];
            }

            if ($lockedBooking->status !== 'confirmed' || ! $lockedBooking->confirmation_number) {
                return ['booking' => $lockedBooking, 'state' => 'not_cancellable'];
            }

            $payload = ['ConfirmationNumber' => $lockedBooking->confirmation_number];

            $lockedBooking->update(['status' => 'cancellation_pending']);
            HotelBookingEvent::create([
                'hotel_prebook_id' => $lockedBooking->hotel_prebook_id,
                'hotel_booking_id' => $lockedBooking->id,
                'provider' => 'tbo',
                'stage' => 'cancel_request',
                'provider_reference' => $lockedBooking->confirmation_number,
                'request_data' => $payload,
                'occurred_at' => now(),
            ]);

            return ['booking' => $lockedBooking, 'payload' => $payload, 'state' => 'started'];
        });

        if ($cancellation['state'] === 'cancelled') {
            return response()->json(['message' => 'This hotel booking has already been cancelled.'], 422);
        }

        if ($cancellation['state'] === 'pending') {
            return response()->json([
                'message' => 'A cancellation request is already awaiting a provider result. Refresh booking details before trying again.',
                'data' => $this->normalizeBooking($cancellation['booking'], true),
            ], 409);
        }

        if ($cancellation['state'] === 'not_cancellable') {
            return response()->json(['message' => 'Only confirmed bookings with a TBO confirmation number can be cancelled.'], 422);
        }

        /** @var HotelBooking $booking */
        $booking = $cancellation['booking'];

        try {
            $tboResponse = $this->tboHotelService->cancel($cancellation['payload']);
        } catch (Throwable $e) {
            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'cancel_transport_error',
                'provider_reference' => $booking->confirmation_number,
                'response_data' => ['message' => 'No response was received from the provider.'],
                'occurred_at' => now(),
            ]);

            Log::error('Unable to cancel TBO hotel booking', [
                'hotel_booking_id' => $booking->uuid,
                'confirmation_number' => $booking->confirmation_number,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'The cancellation result is unknown. Refresh booking details before trying again.',
                'data' => $this->normalizeBooking($booking->fresh(), true),
            ], 502);
        }

        $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
        $isCancelled = $statusCode === 200;
        DB::transaction(function () use ($booking, $statusCode, $isCancelled, $tboResponse) {
            $booking->update([
                'status' => $isCancelled ? 'cancelled' : 'confirmed',
                'provider_status_code' => $isCancelled ? $statusCode : $booking->provider_status_code,
                'tbo_cancel_response' => $tboResponse,
                'cancelled_at' => $isCancelled ? now() : null,
            ]);

            HotelBookingEvent::create([
                'hotel_prebook_id' => $booking->hotel_prebook_id,
                'hotel_booking_id' => $booking->id,
                'provider' => 'tbo',
                'stage' => 'cancel',
                'provider_status_code' => $statusCode,
                'provider_reference' => $booking->confirmation_number,
                'response_data' => $tboResponse,
                'occurred_at' => now(),
            ]);
        });

        if (! $isCancelled) {
            return response()->json([
                'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
                'provider_status' => data_get($tboResponse, 'Status'),
                'data' => $this->normalizeBooking($booking->fresh(), true),
            ], $this->httpStatusForProviderStatus($statusCode));
        }

        return response()->json([
            'message' => 'Hotel booking cancelled successfully.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => $this->normalizeBooking($booking->fresh(), true),
        ]);
    }

    protected function resolveHotelCodes(string $type, string $value): Collection
    {
        return match ($type) {
            'hotel' => collect([(string) $value]),
            'city' => TboHotel::where('city_code', $value)
                ->orderBy('hotel_name')
                ->limit(100)
                ->pluck('hotel_code'),
            'country' => TboHotel::where('country_code', strtoupper($value))
                ->orderBy('city_name')
                ->orderBy('hotel_name')
                ->limit(100)
                ->pluck('hotel_code'),
        };
    }

    protected function syncCityHotels(string $cityCode): void
    {
        $city = TboHotelCity::with('country')->where('city_code', $cityCode)->first();

        if (! $city) {
            throw new RuntimeException('Selected TBO city was not found locally. Please sync countries and cities first.');
        }

        $response = $this->tboHotelService->tboHotelCodeList($city->city_code, true);
        $hotels = $response['Hotels'] ?? [];

        if (! is_array($hotels) || empty($hotels)) {
            throw new RuntimeException('TBO returned no hotels for this city.');
        }

        foreach ($hotels as $hotel) {
            if (is_array($hotel)) {
                $this->upsertHotel($hotel, $city);
            }
        }
    }

    protected function upsertHotel(array $hotel, TboHotelCity $city): void
    {
        $hotelCode = (string) ($hotel['HotelCode'] ?? '');

        if ($hotelCode === '') {
            return;
        }

        [$mapLatitude, $mapLongitude] = $this->parseMap($hotel['Map'] ?? null);
        $latitude = is_numeric($hotel['Latitude'] ?? null) ? (float) $hotel['Latitude'] : $mapLatitude;
        $longitude = is_numeric($hotel['Longitude'] ?? null) ? (float) $hotel['Longitude'] : $mapLongitude;
        $hotelName = $hotel['HotelName'] ?? 'Hotel '.$hotelCode;
        $countryCode = strtoupper((string) ($hotel['CountryCode'] ?? $city->country_code));
        $countryName = $hotel['CountryName'] ?? $city->country?->name;
        $cityName = $hotel['CityName'] ?? $city->name;
        $map = $hotel['Map'] ?? ($latitude && $longitude ? $latitude.'|'.$longitude : null);

        TboHotel::updateOrCreate(
            ['hotel_code' => $hotelCode],
            [
                'hotel_name' => $hotelName,
                'hotel_rating' => isset($hotel['HotelRating']) ? (string) $hotel['HotelRating'] : null,
                'address' => $hotel['Address'] ?? null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'city_code' => (string) ($hotel['CityId'] ?? $city->city_code),
                'city_name' => $cityName,
                'map' => $map,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'images' => $hotel['Images'] ?? null,
                'facilities' => $hotel['HotelFacilities'] ?? null,
                'description' => $hotel['Description'] ?? null,
                'raw_response' => $hotel,
                'search_text' => trim(implode(' ', array_filter([
                    $hotelName,
                    $cityName,
                    $countryName,
                    $hotel['Address'] ?? null,
                    $hotel['HotelRating'] ?? null,
                ]))),
            ]
        );
    }

    protected function parseMap(?string $map): array
    {
        if (! $map || ! str_contains($map, '|')) {
            return [null, null];
        }

        [$latitude, $longitude] = explode('|', $map, 2);

        return [
            is_numeric($latitude) ? (float) $latitude : null,
            is_numeric($longitude) ? (float) $longitude : null,
        ];
    }

    protected function normalizeSearchResults(array $tboResponse, string $displayCurrency): Collection
    {
        $results = collect($tboResponse['HotelResult'] ?? []);
        $hotelCodes = $results->pluck('HotelCode')->map(fn ($code) => (string) $code)->all();
        $staticHotels = TboHotel::whereIn('hotel_code', $hotelCodes)->get()->keyBy('hotel_code');

        return $results->map(function (array $result) use ($staticHotels, $displayCurrency) {
            $hotelCode = (string) ($result['HotelCode'] ?? '');
            $hotel = $staticHotels->get($hotelCode);
            $providerCurrency = strtoupper((string) ($result['Currency'] ?? ''));
            $rooms = collect($result['Rooms'] ?? [])
                ->map(fn (array $room) => $this->normalizeRoom($room, $displayCurrency, $providerCurrency))
                ->values();
            $lowestFare = $rooms->pluck('total_fare')->filter(fn ($fare) => $fare !== null)->min();
            $lowestRoom = $rooms
                ->filter(fn (array $room) => $room['total_fare'] !== null)
                ->sortBy('total_fare')
                ->first();
            $promotions = $rooms
                ->flatMap(fn (array $room) => $room['room_promotion'] ?? [])
                ->filter()
                ->unique()
                ->values();
            $hasAtPropertySupplements = $rooms->contains(function (array $room) {
                return collect($room['supplements'] ?? [])
                    ->flatten(1)
                    ->contains(fn ($supplement) => is_array($supplement) && ($supplement['Type'] ?? null) === 'AtProperty');
            });

            return [
                'hotel_code' => $hotelCode,
                'name' => $hotel?->hotel_name ?: 'Hotel '.$hotelCode,
                'city' => $hotel?->city_name,
                'country' => $hotel?->country_name,
                'rating' => $hotel?->hotel_rating,
                'address' => $hotel?->address,
                'map' => $hotel?->map,
                'latitude' => $hotel?->latitude,
                'longitude' => $hotel?->longitude,
                'currency' => $providerCurrency ?: null,
                'lowest_total_fare' => $lowestFare,
                'lowest_display_money' => $lowestRoom['display_money'] ?? null,
                'lowest_room' => $lowestRoom,
                'room_count' => $rooms->count(),
                'promotions' => $promotions,
                'has_at_property_supplements' => $hasAtPropertySupplements,
                'rooms' => $rooms,
            ];
        });
    }

    protected function normalizeRoom(array $room, string $displayCurrency, ?string $fallbackCurrency = null): array
    {
        $currency = strtoupper((string) ($room['Currency'] ?? $fallbackCurrency ?? ''));
        $totalFare = isset($room['TotalFare']) ? (float) $room['TotalFare'] : null;
        $totalTax = isset($room['TotalTax']) ? (float) $room['TotalTax'] : null;

        return [
            'name' => $room['Name'] ?? [],
            'currency' => $currency ?: null,
            'room_id' => $room['RoomID'] ?? null,
            'booking_code' => $room['BookingCode'] ?? null,
            'inclusion' => $room['Inclusion'] ?? null,
            'total_fare' => $totalFare,
            'total_tax' => $totalTax,
            'display_money' => $this->convertSearchMoney($totalFare, $currency, $displayCurrency),
            'display_tax_money' => $this->convertSearchMoney($totalTax, $currency, $displayCurrency),
            'extra_guest_charges' => isset($room['ExtraGuestCharges']) ? (float) $room['ExtraGuestCharges'] : null,
            'recommended_selling_rate' => $room['RecommendedSellingRate'] ?? null,
            'room_promotion' => $room['RoomPromotion'] ?? [],
            'bedding_group' => $room['BeddingGroup'] ?? null,
            'meal_type' => $room['MealType'] ?? null,
            'is_refundable' => (bool) ($room['IsRefundable'] ?? false),
            'with_transfers' => (bool) ($room['WithTransfers'] ?? false),
            'supplements' => $room['Supplements'] ?? [],
            'cancel_policies' => $room['CancelPolicies'] ?? [],
        ];
    }

    /**
     * Search prices are indicative. They use the currently selected display currency;
     * the authoritative converted amount is still revalidated and frozen at PreBook.
     */
    protected function convertSearchMoney(?float $amount, string $sourceCurrency, string $displayCurrency): ?array
    {
        if ($amount === null || ! preg_match('/^[A-Z]{3}$/', $sourceCurrency)) {
            return null;
        }

        try {
            return $this->currencyConversionService->convertMoney($amount, $sourceCurrency, $displayCurrency);
        } catch (ValidationException) {
            return null;
        }
    }

    protected function findSearchSelection(HotelSearchSession $session, string $bookingCode): ?array
    {
        foreach (($session->tbo_response['HotelResult'] ?? []) as $hotelResult) {
            if (! is_array($hotelResult)) {
                continue;
            }

            foreach (($hotelResult['Rooms'] ?? []) as $room) {
                if (! is_array($room) || ! isset($room['BookingCode'])) {
                    continue;
                }

                if (hash_equals((string) $room['BookingCode'], $bookingCode)) {
                    $room['Currency'] = $hotelResult['Currency'] ?? null;

                    return [
                        'hotel_code' => (string) ($hotelResult['HotelCode'] ?? ''),
                        'room' => $room,
                    ];
                }
            }
        }

        return null;
    }

    protected function normalizePrebook(HotelPrebook $prebook): array
    {
        $room = $this->prebookProviderRoom($prebook);
        $hotel = TboHotel::where('hotel_code', $prebook->hotel_code)->first();
        $normalizedRoom = $this->normalizeRoom(
            $room,
            $prebook->priceQuote?->selling_currency ?? strtoupper((string) ($room['Currency'] ?? 'AED')),
        );
        $normalizedRoom['currency'] = $room['Currency'] ?? null;
        $searchSession = $prebook->searchSession;

        return [
            'prebook_id' => $prebook->uuid,
            'expires_at' => $prebook->expires_at?->toIso8601String(),
            'payment_mode' => $prebook->payment_mode,
            'booking_code' => $room['BookingCode'] ?? $prebook->booking_code,
            'price_quote' => $this->normalizePriceQuote($prebook->priceQuote),
            'stay' => [
                'check_in' => $searchSession?->check_in?->toDateString(),
                'check_out' => $searchSession?->check_out?->toDateString(),
                'pax_rooms' => $searchSession?->pax_rooms ?? [],
            ],
            'hotel' => [
                'hotel_code' => $prebook->hotel_code,
                'name' => $hotel?->hotel_name ?: 'Hotel '.$prebook->hotel_code,
                'city' => $hotel?->city_name,
                'country' => $hotel?->country_name,
                'address' => $hotel?->address,
            ],
            'room' => $normalizedRoom,
        ];
    }

    protected function normalizePriceQuote($quote): ?array
    {
        if (! $quote) {
            return null;
        }

        return [
            'quote_id' => $quote->uuid,
            'provider_money' => [
                'amount' => $quote->provider_amount,
                'currency' => $quote->provider_currency,
            ],
            'base_money' => [
                'amount' => $quote->aed_amount,
                'currency' => 'AED',
            ],
            'selling_money' => [
                'amount' => $quote->selling_amount,
                'currency' => $quote->selling_currency,
            ],
            'expires_at' => $quote->expires_at?->toIso8601String(),
        ];
    }

    protected function currencyCodeForRequest(Request $request, ?string $fallback = null): string
    {
        $originHost = parse_url((string) $request->header('Origin'), PHP_URL_HOST);
        $host = strtolower((string) ($originHost ?: $request->getHost()));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'ae' || str_ends_with($host, '.ae')) {
            return 'AED';
        }

        if ($host === 'pk' || str_ends_with($host, '.pk')) {
            return 'PKR';
        }

        return strtoupper($fallback ?: $request->input('currencyCode', 'AED'));
    }

    protected function prebookProviderRoom(HotelPrebook $prebook): array
    {
        $providerHotel = collect($prebook->tbo_response['HotelResult'] ?? [])
            ->filter(fn ($hotel) => is_array($hotel))
            ->first();
        $providerRoom = collect(is_array($providerHotel) ? ($providerHotel['Rooms'] ?? []) : [])
            ->filter(fn ($room) => is_array($room))
            ->first();
        $room = is_array($providerRoom) ? $providerRoom : $prebook->search_room;
        $room['Currency'] = $providerHotel['Currency'] ?? $room['Currency'] ?? null;

        return $room;
    }

    protected function validateBookingGuests(HotelPrebook $prebook, array $customerDetails): void
    {
        $paxRooms = $prebook->searchSession?->pax_rooms ?? [];

        if (count($customerDetails) !== count($paxRooms)) {
            throw ValidationException::withMessages([
                'customer_details' => ['Guest rooms must match the rooms in the hotel search.'],
            ]);
        }

        foreach ($paxRooms as $index => $paxRoom) {
            $guests = $customerDetails[$index]['customer_names'] ?? [];
            $adultCount = count(array_filter($guests, fn (array $guest) => $guest['type'] === 'Adult'));
            $childCount = count(array_filter($guests, fn (array $guest) => $guest['type'] === 'Child'));

            if ($adultCount !== (int) ($paxRoom['Adults'] ?? 0) || $childCount !== (int) ($paxRoom['Children'] ?? 0)) {
                throw ValidationException::withMessages([
                    "customer_details.$index.customer_names" => ['Guest types must match the adults and children in the hotel search.'],
                ]);
            }
        }
    }

    protected function normalizeBooking(HotelBooking $booking, bool $includeGuests = false): array
    {
        $booking->loadMissing('prebook.searchSession', 'priceSnapshot');
        if ($includeGuests) {
            $booking->loadMissing('guests');
        }
        $providerDetails = data_get($booking->tbo_booking_detail_response, 'BookingDetail', []);
        $prebookData = $booking->prebook ? $this->normalizePrebook($booking->prebook) : null;

        return [
            'booking_id' => $booking->uuid,
            'status' => $booking->status,
            'provider_status_code' => $booking->provider_status_code,
            'confirmation_number' => $booking->confirmation_number,
            'hotel_confirmation_number' => $booking->hotel_confirmation_number,
            'booking_reference_id' => $booking->booking_reference_id,
            'client_reference_id' => $booking->client_reference_id,
            'currency' => $booking->currency,
            'total_fare' => $booking->total_fare,
            'price_snapshot' => $this->normalizeBookingPriceSnapshot($booking->priceSnapshot),
            'guests' => $includeGuests
                ? $booking->guests
                    ->sortBy(fn ($guest) => sprintf('%03d-%03d', $guest->room_index, $guest->guest_index))
                    ->map(fn ($guest) => [
                        'room_index' => $guest->room_index,
                        'guest_index' => $guest->guest_index,
                        'type' => $guest->type,
                        'title' => $guest->title,
                        'first_name' => $guest->first_name,
                        'last_name' => $guest->last_name,
                    ])
                    ->values()
                    ->all()
                : null,
            'provider_booking_status' => data_get($providerDetails, 'BookingStatus'),
            'voucher_status' => data_get($providerDetails, 'VoucherStatus'),
            'invoice_number' => data_get($providerDetails, 'InvoiceNumber'),
            'check_in' => data_get($providerDetails, 'CheckIn') ?? $booking->prebook?->searchSession?->check_in?->toDateString(),
            'check_out' => data_get($providerDetails, 'CheckOut') ?? $booking->prebook?->searchSession?->check_out?->toDateString(),
            'booking_details_checked_at' => $booking->booking_details_checked_at?->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'cancellation_policies' => data_get($providerDetails, 'CancelPolicies') ?? data_get($prebookData, 'room.cancel_policies', []),
            'hotel' => $prebookData['hotel'] ?? null,
        ];
    }

    protected function normalizeBookingPriceSnapshot($snapshot): ?array
    {
        if (! $snapshot) {
            return null;
        }

        return [
            'quote_id' => $snapshot->quote_uuid,
            'provider_money' => [
                'amount' => $snapshot->provider_amount,
                'currency' => $snapshot->provider_currency,
            ],
            'base_money' => [
                'amount' => $snapshot->aed_amount,
                'currency' => 'AED',
            ],
            'selling_money' => [
                'amount' => $snapshot->selling_amount,
                'currency' => $snapshot->selling_currency,
            ],
        ];
    }

    protected function normalizeAdminBooking(HotelBooking $booking, bool $includeDetails = false): array
    {
        $booking->loadMissing('user');
        $data = $this->normalizeBooking($booking, $includeDetails);

        $data['customer'] = $booking->user ? [
            'id' => $booking->user->id,
            'name' => $booking->user->name,
            'email' => $booking->user->email,
        ] : null;
        $data['email'] = $booking->email;
        $data['phone_number'] = $booking->phone_number;
        $data['created_at'] = $booking->created_at?->toIso8601String();
        $data['events'] = $includeDetails
            ? $booking->events
                ->map(fn (HotelBookingEvent $event) => [
                    'stage' => $event->stage,
                    'provider_status_code' => $event->provider_status_code,
                    'provider_reference' => $event->provider_reference,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ])
                ->values()
                ->all()
            : null;

        return $data;
    }

    protected function ensureHotelBookingPermission(Request $request, string $permission): void
    {
        $allowed = $permission === 'manage-bookings'
            ? $this->canManageHotelBookings($request)
            : $this->canViewHotelBookings($request);

        abort_unless($allowed, 403, 'You are not allowed to access hotel bookings.');
    }

    protected function canViewHotelBookings(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && (
            $user->role === 'admin'
            || $user->can('view-bookings')
            || $user->can('manage-bookings')
        );
    }

    protected function canManageHotelBookings(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->role === 'admin' || $user->can('manage-bookings'));
    }

    protected function localStatusForProviderBooking(?string $providerStatus, string $currentStatus): string
    {
        $status = strtolower((string) $providerStatus);

        if (str_contains($status, 'cancel')) {
            return 'cancelled';
        }

        if (str_contains($status, 'confirm') || str_contains($status, 'voucher')) {
            return 'confirmed';
        }

        return $currentStatus;
    }

    protected function validateChildrenAges(array $rooms): void
    {
        foreach ($rooms as $index => $room) {
            $children = (int) ($room['children'] ?? 0);
            $ages = $room['children_ages'] ?? [];

            if ($children !== count($ages)) {
                throw ValidationException::withMessages([
                    "rooms.$index.children_ages" => ['Children ages count must match children count.'],
                ]);
            }

            foreach ($ages as $age) {
                if (! is_numeric($age) || (int) $age < 0 || (int) $age > 18) {
                    throw ValidationException::withMessages([
                        "rooms.$index.children_ages" => ['Child age must be between 0 and 18.'],
                    ]);
                }
            }
        }
    }

    protected function citySuggestion(TboHotelCity $city): array
    {
        $countryName = $city->country?->name;

        return [
            'type' => 'city',
            'label' => trim($city->name.($countryName ? ', '.$countryName : '')),
            'value' => $city->city_code,
            'city_code' => $city->city_code,
            'country_code' => $city->country_code,
        ];
    }

    protected function hotelSuggestion(TboHotel $hotel): array
    {
        return [
            'type' => 'hotel',
            'label' => trim($hotel->hotel_name.($hotel->city_name ? ', '.$hotel->city_name : '')),
            'value' => $hotel->hotel_code,
            'hotel_code' => $hotel->hotel_code,
            'city_code' => $hotel->city_code,
            'country_code' => $hotel->country_code,
        ];
    }

    protected function countrySuggestion(TboHotelCountry $country): array
    {
        return [
            'type' => 'country',
            'label' => $country->name,
            'value' => $country->code,
            'country_code' => $country->code,
        ];
    }

    protected function statusMessage(int $statusCode, ?string $providerMessage): string
    {
        return match ($statusCode) {
            200 => 'Hotels found successfully.',
            201 => 'No hotels are available for this search.',
            207 => 'The selected hotel rate is unavailable.',
            315 => 'The hotel search session expired. Please search again.',
            429 => 'Hotel provider request limit exceeded. Please try again shortly.',
            default => $providerMessage ?: 'Hotel provider returned an error.',
        };
    }

    protected function httpStatusForProviderStatus(int $statusCode): int
    {
        return match ($statusCode) {
            200, 201 => 200,
            400 => 422,
            401 => 502,
            429 => 429,
            default => 502,
        };
    }
}
