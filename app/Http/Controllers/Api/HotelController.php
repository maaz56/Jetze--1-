<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelBooking;
use App\Models\HotelBookingEvent;
use App\Models\HotelPrebook;
use App\Models\HotelPriceQuote;
use App\Models\HotelPaymentAttempt;
use App\Models\HotelSearchSession;
use App\Models\TboHotel;
use App\Models\TboHotelCity;
use App\Models\TboHotelCountry;
use App\Services\TboHotelService;
use App\Services\CurrencyConversionService;
use App\Services\HotelPriceQuoteService;
use App\Services\HotelBookingPricingService;
use App\Services\AgentWalletBalanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
        protected AgentWalletBalanceService $agentWalletBalanceService,
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
            'rooms' => 'required|array|min:1|max:6',
            'rooms.*.adults' => 'required|integer|min:1|max:6',
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
        $hotelCodes = $this->resolveHotelCodes($destination['type'], $destination['value'])
            ->map(static fn ($hotelCode): string => trim((string) $hotelCode))
            ->filter()
            ->unique()
            ->values();

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
                'NoOfRooms' => max(1, (int) data_get($validated, 'filters.no_of_rooms', 1)),
                'MealType' => data_get($validated, 'filters.meal_type', 'All'),
            ],
        ];

        Log::info('TBO Hotel Search Outgoing Request', [
            'destination_type' => $destination['type'],
            'destination_code' => $destination['value'],
            'hotel_code_count' => $hotelCodes->count(),
            'request' => $tboRequest,
        ]);

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

    /** Refresh one selected hotel's detailed availability in its active search session. */
    public function details(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_session_id' => ['required', 'uuid'],
            'hotel_code' => ['required', 'string', 'max:100'],
            'booking_code' => ['nullable', 'string', 'max:1024'],
            'language' => ['nullable', 'string', 'size:2'],
            'currency_code' => ['nullable', 'string', 'size:3'],
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

        $hotelCode = (string) $validated['hotel_code'];
        $requestedBookingCode = isset($validated['booking_code'])
            ? (string) $validated['booking_code']
            : null;
        $currentProviderHotel = $this->findSearchHotel($session, $hotelCode);

        if (! $currentProviderHotel) {
            return response()->json([
                'message' => 'This hotel is not part of the current search. Please search again.',
            ], 422);
        }

        $displayCurrency = $this->currencyCodeForRequest($request, $validated['currency_code'] ?? null);
        $requestedSearchRoom = $requestedBookingCode
            ? $this->findProviderRoomByBookingCode($currentProviderHotel, $requestedBookingCode)
            : null;
        $canReuseDetailedSearch = $requestedBookingCode
            && ($currentProviderHotel['_DetailedResponse'] ?? false) === true
            && $requestedSearchRoom;
        $selectedBookingCode = $canReuseDetailedSearch ? $requestedBookingCode : null;

        if ($canReuseDetailedSearch) {
            $tboResponse = [
                'Status' => ['Code' => 200, 'Description' => 'Successful'],
                'HotelResult' => [$currentProviderHotel],
            ];
            $detailedHotel = $currentProviderHotel;
        } else {
            $detailedSearchRequest = $this->detailedHotelSearchRequest($session, $hotelCode);

            Log::info('TBO Hotel Detailed Search Outgoing Request', [
                'search_session_id' => $session->uuid,
                'hotel_code' => $hotelCode,
                'request' => $detailedSearchRequest,
            ]);

            try {
                $tboResponse = $this->tboHotelService->search($detailedSearchRequest);
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            } catch (Throwable $e) {
                Log::error('Unable to run detailed TBO hotel search', [
                    'search_session_id' => $session->uuid,
                    'hotel_code' => $hotelCode,
                    'message' => $e->getMessage(),
                ]);

                return response()->json(['message' => 'Current room options could not be loaded. Please try again.'], 502);
            }

            $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
            if ($statusCode !== 200) {
                return response()->json([
                    'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
                    'provider_status' => data_get($tboResponse, 'Status'),
                ], $this->httpStatusForProviderStatus($statusCode));
            }

            $detailedHotel = collect($tboResponse['HotelResult'] ?? [])
                ->first(fn ($hotel) => is_array($hotel) && (string) ($hotel['HotelCode'] ?? '') === $hotelCode);

            if (! is_array($detailedHotel)) {
                return response()->json([
                    'message' => 'No detailed room options were returned for the selected property.',
                ], 502);
            }

            if ($requestedSearchRoom) {
                $selectedDetailedRoom = $this->matchDetailedSearchRoom($requestedSearchRoom, $detailedHotel);
                $selectedBookingCode = isset($selectedDetailedRoom['BookingCode'])
                    ? (string) $selectedDetailedRoom['BookingCode']
                    : null;

                if (! $selectedBookingCode) {
                    return response()->json([
                        'message' => 'The selected room is no longer available. Please choose another room.',
                    ], 422);
                }
            }

            // Detailed Search returns fresh booking codes. Save them in the trusted
            // session so the following PreBook can validate the selected room.
            $this->storeDetailedSearchHotel($session, $detailedHotel);
        }

        $searchHotel = $this->normalizeSearchResults($tboResponse, $displayCurrency)
            ->first(fn (array $hotel) => $hotel['hotel_code'] === $hotelCode);
        $hotel = TboHotel::where('hotel_code', $hotelCode)->first();

        if (! $searchHotel || ! $hotel) {
            return response()->json([
                'message' => 'Current hotel details could not be mapped. Please search again.',
            ], 422);
        }

        $providerHotel = is_array($hotel->raw_response) ? $hotel->raw_response : [];

        // Static HotelDetails only enriches the selected room with images, size
        // and description. It must not block a valid availability result because
        // TBO can return "No Hotels Found" here even when Search has rooms.
        if ($requestedBookingCode) {
            try {
                $hotelDetailsResponse = $this->tboHotelService->hotelDetails(
                    $hotelCode,
                    strtoupper((string) ($validated['language'] ?? 'EN')),
                    true,
                );
                $hotelDetailsStatusCode = (int) data_get($hotelDetailsResponse, 'Status.Code', 500);

                if ($hotelDetailsStatusCode === 200) {
                    $freshProviderHotel = collect($hotelDetailsResponse['HotelDetails'] ?? [])
                        ->first(fn ($providerResult) => is_array($providerResult)
                            && (string) ($providerResult['HotelCode'] ?? '') === $hotelCode);

                    if (is_array($freshProviderHotel)) {
                        $providerHotel = $freshProviderHotel;
                        $hotel = $this->storeHotelDetails($freshProviderHotel);
                    } else {
                        Log::warning('TBO HotelDetails did not contain the selected hotel; using cached hotel content.', [
                            'search_session_id' => $session->uuid,
                            'hotel_code' => $hotelCode,
                        ]);
                    }
                } else {
                    Log::warning('TBO HotelDetails enrichment unavailable; continuing with Search availability.', [
                        'search_session_id' => $session->uuid,
                        'hotel_code' => $hotelCode,
                        'provider_status' => data_get($hotelDetailsResponse, 'Status'),
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Unable to enrich selected room from TBO HotelDetails; using cached hotel content.', [
                    'search_session_id' => $session->uuid,
                    'hotel_code' => $hotelCode,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $roomDetails = $this->normalizeHotelRoomDetails($providerHotel);
        $roomDetailsById = collect($roomDetails)->keyBy('room_id');
        $searchRooms = collect($searchHotel['rooms'])
            ->map(function (array $room) use ($roomDetailsById): array {
                $room['room_details'] = $this->matchHotelRoomDetailsById($room, $roomDetailsById);

                return $room;
            })
            ->values()
            ->all();

        return response()->json([
            'message' => 'Detailed hotel availability fetched successfully.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => array_merge($this->normalizeHotelDetails($providerHotel, $hotel), $searchHotel, [
                'stay' => [
                    'check_in' => $session->check_in?->toDateString(),
                    'check_out' => $session->check_out?->toDateString(),
                    'pax_rooms' => $session->pax_rooms ?? [],
                ],
                'rooms' => $searchRooms,
                'room_count' => $searchHotel['room_count'],
                'promotions' => $searchHotel['promotions'],
                'selected_booking_code' => $selectedBookingCode,
                'room_details' => $roomDetails,
            ]),
        ]);
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

        $prebookExpiresAt = now()->addMinutes(30);

        $prebook = DB::transaction(function () use ($session, $selection, $validated, $statusCode, $tboResponse, $request, $prebookExpiresAt) {
            $prebook = HotelPrebook::create([
                'uuid' => (string) Str::uuid(),
                'hotel_search_session_id' => $session->id,
                'user_id' => $request->user()->id,
                'hotel_code' => $selection['hotel_code'],
                'booking_code' => $validated['booking_code'],
                'payment_mode' => $validated['payment_mode'],
                'provider_status_code' => $statusCode,
                'search_room' => $selection['room'],
                'tbo_response' => $tboResponse,
                // This is the application's fixed reservation window, measured from
                // the successful PreBook response and persisted with the prebook.
                'expires_at' => $prebookExpiresAt,
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

        if ($prebook->user_id && $prebook->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel prebook belongs to another user.'], 403);
        }

        return response()->json([
            'provider_status' => data_get($prebook->tbo_response, 'Status'),
            'data' => $this->normalizePrebook($prebook),
        ]);
    }

    /** Save validated hotel guests as a pending reservation; payment and TBO Book happen later. */
    public function reserve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prebook_id' => 'required|uuid',
            'email' => 'required|email:rfc,dns|max:255',
            'phone_number' => 'required|string|max:64',
            'customer_details' => 'required|array|min:1|max:8',
            'customer_details.*.customer_names' => 'required|array|min:1|max:12',
            'customer_details.*.customer_names.*.title' => 'required|string|in:Mr,Mrs,Ms',
            'customer_details.*.customer_names.*.first_name' => ['required', 'string', 'min:2', 'max:25', 'regex:/^[A-Za-z]+$/'],
            'customer_details.*.customer_names.*.last_name' => ['required', 'string', 'min:2', 'max:25', 'regex:/^[A-Za-z]+$/'],
            'customer_details.*.customer_names.*.type' => 'required|string|in:Adult,Child',
        ]);

        $prebook = HotelPrebook::with(['searchSession', 'priceQuote'])
            ->where('uuid', $validated['prebook_id'])
            ->first();

        if (! $prebook) {
            return response()->json(['message' => 'Hotel prebook was not found. Please search again.'], 404);
        }
        if ($prebook->expires_at?->isPast()) {
            return response()->json(['message' => 'This reservation window has expired. Please search again.'], 422);
        }
        if ($prebook->user_id && $prebook->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel prebook belongs to another user.'], 403);
        }

        $this->validateBookingGuests($prebook, $validated['customer_details']);
        $customerDetails = collect($validated['customer_details'])->map(fn (array $room) => [
            'CustomerNames' => collect($room['customer_names'])->map(fn (array $guest) => [
                'Title' => $guest['title'], 'FirstName' => $guest['first_name'], 'LastName' => $guest['last_name'], 'Type' => $guest['type'],
            ])->values()->all(),
        ])->values()->all();

        $userId = $request->user()->id;
        $booking = DB::transaction(function () use ($prebook, $validated, $customerDetails, $userId) {
            $lockedPrebook = HotelPrebook::lockForUpdate()->findOrFail($prebook->id);
            $quote = HotelPriceQuote::where('hotel_prebook_id', $lockedPrebook->id)->lockForUpdate()->first();
            if (! $quote || $lockedPrebook->expires_at?->isPast()) {
                throw ValidationException::withMessages(['prebook_id' => 'This reservation window has expired. Please search again.']);
            }

            $existing = HotelBooking::where('hotel_prebook_id', $lockedPrebook->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $providerRoom = $this->prebookProviderRoom($lockedPrebook);
            $bookingCode = $providerRoom['BookingCode'] ?? $lockedPrebook->booking_code;
            $booking = HotelBooking::create([
                'uuid' => (string) Str::uuid(),
                'hotel_prebook_id' => $lockedPrebook->id,
                'user_id' => $userId,
                'status' => 'payment_pending',
                'client_reference_id' => 'HB-'.Str::upper(Str::random(20)),
                'booking_reference_id' => 'HBR-'.Str::upper(Str::random(20)),
                'currency' => $quote->provider_currency,
                'total_fare' => $quote->provider_amount,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'customer_details' => $customerDetails,
                'tbo_request' => [
                    'BookingCode' => $bookingCode,
                    'CustomerDetails' => $customerDetails,
                    'ClientReferenceId' => 'pending',
                    'BookingReferenceId' => 'pending',
                    'TotalFare' => (float) $quote->provider_amount,
                    'EmailId' => $validated['email'],
                    'PhoneNumber' => $validated['phone_number'],
                    'BookingType' => 'Voucher',
                    'PaymentMode' => $lockedPrebook->payment_mode,
                ],
            ]);
            $request = $booking->tbo_request;
            $request['ClientReferenceId'] = $booking->client_reference_id;
            $request['BookingReferenceId'] = $booking->booking_reference_id;
            $booking->update(['tbo_request' => $request]);
            $booking->guests()->createMany(collect($validated['customer_details'])->flatMap(fn (array $room, int $roomIndex) => collect($room['customer_names'])->map(fn (array $guest, int $guestIndex) => [
                'room_index' => $roomIndex, 'guest_index' => $guestIndex, 'type' => $guest['type'], 'title' => $guest['title'], 'first_name' => $guest['first_name'], 'last_name' => $guest['last_name'], 'raw_data' => $guest,
            ]))->values()->all());
            // Wallet eligibility must use an immutable server-side AED value.
            $this->hotelBookingPricingService->createSnapshot($booking, $quote);
            HotelBookingEvent::create(['hotel_prebook_id' => $lockedPrebook->id, 'hotel_booking_id' => $booking->id, 'provider' => 'tbo', 'stage' => 'reservation_saved', 'provider_reference' => $booking->booking_reference_id, 'occurred_at' => now()]);

            return $booking;
        });

        return response()->json(['message' => 'Guest details saved. Continue to payment to confirm your booking.', 'data' => $this->normalizeBooking($booking, true)]);
    }

    public function book(Request $request): JsonResponse
    {
        Log::info('TBO Hotel Book Endpoint Received', [
            'prebook_id' => $request->input('prebook_id'),
            'has_email' => $request->filled('email'),
            'has_phone_number' => $request->filled('phone_number'),
            'customer_room_count' => is_array($request->input('customer_details'))
                ? count($request->input('customer_details'))
                : null,
        ]);

        try {
            $validated = $request->validate([
                'prebook_id' => 'required|uuid',
                'email' => 'required|email:rfc,dns|max:255',
                'phone_number' => 'required|string|max:64',
                'customer_details' => 'required|array|min:1|max:8',
                'customer_details.*.customer_names' => 'required|array|min:1|max:12',
                'customer_details.*.customer_names.*.title' => 'required|string|in:Mr,Mrs,Ms',
                'customer_details.*.customer_names.*.first_name' => ['required', 'string', 'min:2', 'max:25', 'regex:/^[A-Za-z]+$/'],
                'customer_details.*.customer_names.*.last_name' => ['required', 'string', 'min:2', 'max:25', 'regex:/^[A-Za-z]+$/'],
                'customer_details.*.customer_names.*.type' => 'required|string|in:Adult,Child',
            ]);
        } catch (ValidationException $e) {
            Log::warning('TBO Hotel Book Validation Failed', [
                'prebook_id' => $request->input('prebook_id'),
                'invalid_fields' => array_keys($e->errors()),
            ]);

            throw $e;
        }

        Log::info('TBO Hotel Book Validation Passed', [
            'prebook_id' => $validated['prebook_id'],
            'customer_room_count' => count($validated['customer_details']),
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

            // TBO can accept a booking even when the Book response is lost. Reconcile by the
            // booking reference before asking the user to retry or treating it as failed.
            $this->fetchAndStoreBookingDetailsAfterBooking($booking->fresh(['prebook']));
            $booking = $booking->fresh();

            if ($booking->status === 'confirmed') {
                return response()->json([
                    'message' => 'The booking was confirmed after its provider status was checked.',
                    'data' => $this->normalizeBooking($booking),
                ]);
            }

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
                'booking_reference_id' => data_get($tboResponse, 'BookingReferenceId')
                    ?? $booking->booking_reference_id,
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

        // A confirmed booking must be immediately reconciled with TBO's BookingDetail response.
        // A lookup failure is recorded but must not turn an already confirmed booking into a failed one.
        $this->fetchAndStoreBookingDetailsAfterBooking($booking->fresh(['prebook']));

        return response()->json([
            'message' => 'Hotel booking confirmed successfully.',
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => $this->normalizeBooking($booking->fresh()),
        ]);
    }

    /** Verify wallet funds and then finalize the pending reservation with TBO. */
    public function confirmReservation(Request $request, string $bookingUuid): JsonResponse
    {
        $request->validate([
            'payment_method' => ['required', 'string', 'in:wallet'],
        ]);

        $booking = HotelBooking::with(['prebook.searchSession', 'prebook.priceQuote', 'priceSnapshot'])
            ->where('uuid', $bookingUuid)
            ->first();
        if (! $booking) {
            return response()->json(['message' => 'Hotel reservation was not found.'], 404);
        }
        if (! $booking->user_id || $booking->user_id !== optional($request->user())->id) {
            return response()->json(['message' => 'This hotel reservation belongs to another user.'], 403);
        }
        if ($booking->status === 'confirmed') {
            return response()->json(['message' => 'This hotel booking is already confirmed.', 'data' => $this->normalizeBooking($booking, true)]);
        }
        if ($booking->status !== 'payment_pending') {
            return response()->json(['message' => 'This reservation cannot be confirmed in its current state.'], 422);
        }
        if ($booking->prebook?->expires_at?->isPast()) {
            $booking->update(['status' => 'expired']);
            return response()->json(['message' => 'This reservation window has expired. Please search again.'], 422);
        }

        // Support pending reservations created before snapshots were generated at reserve time.
        if (! $booking->priceSnapshot && $booking->prebook?->priceQuote) {
            $this->hotelBookingPricingService->createSnapshot($booking, $booking->prebook->priceQuote);
            $booking->load('priceSnapshot');
        }
        if ($booking->priceSnapshot?->aed_amount === null
            || bccomp((string) $booking->priceSnapshot->aed_amount, '0', 8) <= 0) {
            return response()->json([
                'code' => 'HOTEL_PRICE_SNAPSHOT_UNAVAILABLE',
                'message' => 'The locked hotel price is unavailable. Please create a new reservation.',
            ], 422);
        }

        $paymentLock = Cache::lock('hotel-payment:booking:'.$booking->uuid, 120);
        if (! $paymentLock->get()) {
            return response()->json([
                'message' => 'A payment is already being processed for this hotel reservation.',
            ], 409);
        }

        $walletLock = Cache::lock('wallet-confirm:agent:'.$booking->user_id, 120);
        if (! $walletLock->get()) {
            $paymentLock->release();
            return response()->json([
                'message' => 'A wallet payment is already being processed for this account.',
            ], 409);
        }

        try {
            // Re-read state and balance after acquiring the shared wallet lock.
            $booking->refresh()->load(['prebook.priceQuote', 'priceSnapshot']);
            if ($booking->status !== 'payment_pending') {
                return response()->json(['message' => 'This reservation cannot be confirmed in its current state.'], 422);
            }
            if ($booking->prebook?->expires_at?->isPast()) {
                $booking->update(['status' => 'expired']);
                return response()->json(['message' => 'This reservation window has expired. Please search again.'], 422);
            }
            if (HotelPaymentAttempt::query()
                ->where('hotel_booking_id', $booking->id)
                ->where('provider', HotelPaymentAttempt::PROVIDER_NOMOD)
                ->whereIn('status', [
                    HotelPaymentAttempt::STATUS_INITIATING,
                    HotelPaymentAttempt::STATUS_CREATED,
                    HotelPaymentAttempt::STATUS_CREATION_UNKNOWN,
                    HotelPaymentAttempt::STATUS_PAID,
                ])
                ->exists()) {
                return response()->json([
                    'message' => 'A card payment is already active for this hotel reservation. Do not pay again with wallet.',
                ], 409);
            }
            if (! $this->agentWalletBalanceService->canPayForHotelBooking($booking)) {
                return response()->json([
                    'code' => 'INSUFFICIENT_WALLET_BALANCE',
                    'message' => 'Your wallet balance is insufficient to confirm this hotel booking.',
                    'required_aed' => $booking->priceSnapshot?->aed_amount,
                    'available_aed' => $this->agentWalletBalanceService->balanceInAed((int) $booking->user_id),
                ], 422);
            }

            $booking->update(['status' => 'processing', 'payment_method' => 'wallet']);
            HotelBookingEvent::create(['hotel_prebook_id' => $booking->hotel_prebook_id, 'hotel_booking_id' => $booking->id, 'provider' => 'tbo', 'stage' => 'book_request', 'provider_reference' => $booking->booking_reference_id, 'occurred_at' => now()]);

            try {
                $tboResponse = $this->tboHotelService->book($booking->tbo_request);
            } catch (Throwable $e) {
                $booking->update(['status' => 'unknown']);
                Log::error('TBO hotel reservation confirmation failed', ['hotel_booking_id' => $booking->uuid, 'message' => $e->getMessage()]);
                return response()->json(['message' => 'The booking result is unknown. Do not submit again; verify it using the booking reference.'], 502);
            }

            $statusCode = (int) data_get($tboResponse, 'Status.Code', 500);
            $isConfirmed = $statusCode === 200;
            $booking->update([
                'status' => $isConfirmed ? 'confirmed' : 'failed',
                'paid_at' => $isConfirmed ? now() : null,
                'provider_status_code' => $statusCode,
                'booking_reference_id' => data_get($tboResponse, 'BookingReferenceId')
                    ?? $booking->booking_reference_id,
                'confirmation_number' => data_get($tboResponse, 'ConfirmationNumber'),
                'hotel_confirmation_number' => data_get($tboResponse, 'HotelConfirmationNumber'),
                'tbo_response' => $tboResponse,
            ]);
            HotelBookingEvent::create(['hotel_prebook_id' => $booking->hotel_prebook_id, 'hotel_booking_id' => $booking->id, 'provider' => 'tbo', 'stage' => 'book', 'provider_status_code' => $statusCode, 'provider_reference' => data_get($tboResponse, 'ConfirmationNumber') ?: $booking->booking_reference_id, 'response_data' => $tboResponse, 'occurred_at' => now()]);

            if (! $isConfirmed) {
                return response()->json(['message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')), 'data' => $this->normalizeBooking($booking->fresh(), true)], $this->httpStatusForProviderStatus($statusCode));
            }

            $this->fetchAndStoreBookingDetailsAfterBooking($booking->fresh(['prebook']));

            return response()->json(['message' => 'Hotel booking confirmed successfully.', 'data' => $this->normalizeBooking($booking->fresh(), true)]);
        } finally {
            $walletLock->release();
            $paymentLock->release();
        }
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
        Log::info('Hotel booking detail requested', [
            'hotel_booking_id' => $bookingUuid,
            'user_id' => $request->user()?->id,
        ]);

        $booking = HotelBooking::with(['prebook.searchSession', 'priceSnapshot', 'guests'])
            ->where('uuid', $bookingUuid)
            ->first();

        if (! $booking) {
            Log::warning('Hotel booking detail not found', [
                'hotel_booking_id' => $bookingUuid,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['message' => 'Hotel booking was not found.'], 404);
        }

        if ($booking->user_id && $booking->user_id !== optional($request->user())->id && ! $this->canViewHotelBookings($request)) {
            Log::warning('Hotel booking detail access denied', [
                'hotel_booking_id' => $bookingUuid,
                'booking_user_id' => $booking->user_id,
                'user_id' => $request->user()?->id,
            ]);

            return response()->json(['message' => 'This hotel booking belongs to another user.'], 403);
        }

        Log::info('Hotel booking detail retrieved', [
            'hotel_booking_id' => $booking->uuid,
            'booking_user_id' => $booking->user_id,
            'user_id' => $request->user()?->id,
            'status' => $booking->status,
            'payment_method' => $booking->payment_method,
            'paid_at' => $booking->paid_at?->toIso8601String(),
        ]);

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

        $payload = $this->bookingDetailRequest($booking);

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
            'booking_reference_id' => data_get($details, 'BookingReferenceId')
                ?? data_get($tboResponse, 'BookingReferenceId')
                ?? $booking->booking_reference_id,
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

    /** Fetch and persist the authoritative TBO details immediately after a confirmed booking. */
    protected function fetchAndStoreBookingDetailsAfterBooking(HotelBooking $booking): void
    {
        $booking->loadMissing('prebook');

        if (! $booking->prebook) {
            Log::error('TBO Hotel BookingDetails After Book Skipped', [
                'hotel_booking_id' => $booking->uuid,
                'reason' => 'The booking prebook record is unavailable.',
            ]);

            return;
        }

        $payload = $this->bookingDetailRequest($booking);

        HotelBookingEvent::create([
            'hotel_prebook_id' => $booking->hotel_prebook_id,
            'hotel_booking_id' => $booking->id,
            'provider' => 'tbo',
            'stage' => 'detail_request',
            'provider_reference' => $booking->confirmation_number,
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
                'provider_reference' => $booking->confirmation_number,
                'response_data' => [
                    'trigger' => 'after_book',
                    'message' => 'No response was received from the provider.',
                ],
                'occurred_at' => now(),
            ]);

            Log::error('TBO Hotel BookingDetails After Book Failed', [
                'hotel_booking_id' => $booking->uuid,
                'confirmation_number' => $booking->confirmation_number,
                'message' => $e->getMessage(),
            ]);

            return;
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
                'provider_reference' => $booking->confirmation_number,
                'response_data' => $tboResponse,
                'occurred_at' => now(),
            ]);
        });

        if ($statusCode !== 200) {
            Log::warning('TBO Hotel BookingDetails After Book Returned Error', [
                'hotel_booking_id' => $booking->uuid,
                'confirmation_number' => $booking->confirmation_number,
                'provider_status' => data_get($tboResponse, 'Status'),
            ]);

            return;
        }

        $details = data_get($tboResponse, 'BookingDetail', []);
        $booking->update([
            'status' => $this->localStatusForProviderBooking(data_get($details, 'BookingStatus'), $booking->status),
            'booking_reference_id' => data_get($details, 'BookingReferenceId')
                ?? data_get($tboResponse, 'BookingReferenceId')
                ?? $booking->booking_reference_id,
            'confirmation_number' => data_get($details, 'ConfirmationNumber') ?? $booking->confirmation_number,
            'hotel_confirmation_number' => data_get($details, 'HotelConfirmationNumber') ?? $booking->hotel_confirmation_number,
        ]);

        Log::info('TBO Hotel BookingDetails After Book Saved', [
            'hotel_booking_id' => $booking->uuid,
            'confirmation_number' => $booking->confirmation_number,
            'provider_status' => data_get($tboResponse, 'Status'),
        ]);
    }

    /** Build TBO's BookingDetail lookup with both provider identifiers. */
    protected function bookingDetailRequest(HotelBooking $booking): array
    {
        $payload = [];

        if ($booking->booking_reference_id) {
            $payload['BookingReferenceId'] = $booking->booking_reference_id;
        }

        if ($booking->confirmation_number) {
            $payload['ConfirmationNumber'] = $booking->confirmation_number;
        }

        $payload['PaymentMode'] = $booking->prebook->payment_mode;

        return $payload;
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
            $hotelImages = collect($hotel?->images ?? [])
                ->filter(fn ($image) => is_string($image) && $image !== '')
                ->unique()
                ->values();
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
                'primary_image' => $hotelImages->first(),
                // Keep the complete provider gallery; the UI only previews a few
                // images and exposes the rest on demand.
                'images' => $hotelImages->all(),
                'image_count' => $hotelImages->count(),
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

    /** Return a trusted hotel result only when it belongs to the saved provider search. */
    protected function findSearchHotel(HotelSearchSession $session, string $hotelCode): ?array
    {
        foreach ($session->tbo_response['HotelResult'] ?? [] as $hotel) {
            if (is_array($hotel) && (string) ($hotel['HotelCode'] ?? '') === $hotelCode) {
                return $hotel;
            }
        }

        return null;
    }

    /** Build a fresh detailed Search request for only the selected property. */
    protected function detailedHotelSearchRequest(HotelSearchSession $session, string $hotelCode): array
    {
        $request = is_array($session->tbo_request) ? $session->tbo_request : [];
        $filters = is_array($request['Filters'] ?? null) ? $request['Filters'] : [];

        $request['HotelCodes'] = $hotelCode;
        $request['IsDetailedResponse'] = true;
        $filters['NoOfRooms'] = 0;
        $request['Filters'] = $filters;

        return $request;
    }

    /** Find one trusted provider room by the opaque booking code shown to the user. */
    protected function findProviderRoomByBookingCode(array $providerHotel, string $bookingCode): ?array
    {
        foreach ($providerHotel['Rooms'] ?? [] as $room) {
            if (is_array($room)
                && isset($room['BookingCode'])
                && hash_equals((string) $room['BookingCode'], $bookingCode)) {
                return $room;
            }
        }

        return null;
    }

    /** Match an earlier selected offer to its refreshed detailed-search offer. */
    protected function matchDetailedSearchRoom(array $selectedRoom, array $detailedHotel): ?array
    {
        $rooms = collect($detailedHotel['Rooms'] ?? [])->filter(fn ($room) => is_array($room));
        $selectedRoomId = $this->normalizeRoomId(
            $selectedRoom['TBORoomID'] ?? null,
            $selectedRoom['RoomID'] ?? null,
        );
        $selectedMeal = (string) ($selectedRoom['MealType'] ?? '');
        $selectedInclusion = (string) ($selectedRoom['Inclusion'] ?? '');

        if ($selectedRoomId !== null) {
            $sameRoomId = $rooms->filter(fn (array $room): bool => $this->normalizeRoomId(
                $room['TBORoomID'] ?? null,
                $room['RoomID'] ?? null,
            ) === $selectedRoomId);

            $sameOffer = $sameRoomId->first(fn (array $room): bool =>
                (string) ($room['MealType'] ?? '') === $selectedMeal
                && (string) ($room['Inclusion'] ?? '') === $selectedInclusion
            );

            if (is_array($sameOffer)) {
                return $sameOffer;
            }

            $roomIdMatch = $sameRoomId->first();
            if (is_array($roomIdMatch)) {
                return $roomIdMatch;
            }
        }

        $selectedName = json_encode($selectedRoom['Name'] ?? [], JSON_UNESCAPED_UNICODE);

        return $rooms->first(fn (array $room): bool =>
            json_encode($room['Name'] ?? [], JSON_UNESCAPED_UNICODE) === $selectedName
            && (string) ($room['MealType'] ?? '') === $selectedMeal
        );
    }

    /**
     * Replace the selected property's fast result with its detailed result.
     * This keeps the room and booking-code allow-list used by PreBook current.
     */
    protected function storeDetailedSearchHotel(HotelSearchSession $session, array $detailedHotel): void
    {
        $detailedHotel['_DetailedResponse'] = true;
        $hotelCode = (string) ($detailedHotel['HotelCode'] ?? '');

        DB::transaction(function () use ($session, $detailedHotel, $hotelCode): void {
            $lockedSession = HotelSearchSession::query()->lockForUpdate()->findOrFail($session->id);
            $response = is_array($lockedSession->tbo_response) ? $lockedSession->tbo_response : [];
            $hotelResults = is_array($response['HotelResult'] ?? null) ? $response['HotelResult'] : [];
            $wasReplaced = false;

            foreach ($hotelResults as $index => $hotelResult) {
                if (is_array($hotelResult) && (string) ($hotelResult['HotelCode'] ?? '') === $hotelCode) {
                    $hotelResults[$index] = $detailedHotel;
                    $wasReplaced = true;
                    break;
                }
            }

            if (! $wasReplaced) {
                $hotelResults[] = $detailedHotel;
            }

            $response['HotelResult'] = array_values($hotelResults);
            $lockedSession->update(['tbo_response' => $response]);
        });

        $session->refresh();
    }

    /** Persist static provider property information for later searches and detail views. */
    protected function storeHotelDetails(array $providerHotel): TboHotel
    {
        $hotelCode = (string) ($providerHotel['HotelCode'] ?? '');
        [$latitude, $longitude] = $this->parseMap($providerHotel['Map'] ?? null);
        $existingHotel = TboHotel::where('hotel_code', $hotelCode)->first();
        $hotelName = $providerHotel['HotelName'] ?? $existingHotel?->hotel_name ?? 'Hotel '.$hotelCode;
        $cityName = $providerHotel['CityName'] ?? $existingHotel?->city_name;
        $countryName = $providerHotel['CountryName'] ?? $existingHotel?->country_name;
        $address = $providerHotel['Address'] ?? $existingHotel?->address;

        return TboHotel::updateOrCreate(
            ['hotel_code' => $hotelCode],
            [
                'hotel_name' => $hotelName,
                'hotel_rating' => isset($providerHotel['HotelRating'])
                    ? (string) $providerHotel['HotelRating']
                    : $existingHotel?->hotel_rating,
                'address' => $address,
                'country_code' => strtoupper((string) ($providerHotel['CountryCode'] ?? $existingHotel?->country_code)),
                'country_name' => $countryName,
                'city_code' => (string) ($providerHotel['CityId'] ?? $existingHotel?->city_code),
                'city_name' => $cityName,
                'map' => $providerHotel['Map'] ?? $existingHotel?->map,
                'latitude' => $latitude ?? $existingHotel?->latitude,
                'longitude' => $longitude ?? $existingHotel?->longitude,
                'images' => $providerHotel['Images'] ?? $existingHotel?->images,
                'facilities' => $providerHotel['HotelFacilities'] ?? $existingHotel?->facilities,
                'description' => $providerHotel['Description'] ?? $existingHotel?->description,
                'raw_response' => $providerHotel,
                'search_text' => trim(implode(' ', array_filter([
                    $hotelName,
                    $cityName,
                    $countryName,
                    $address,
                    $providerHotel['HotelRating'] ?? null,
                ]))),
            ],
        );
    }

    /** Map provider hotel content into the property-details response contract. */
    protected function normalizeHotelDetails(array $providerHotel, TboHotel $hotel): array
    {
        [$latitude, $longitude] = $this->parseMap($providerHotel['Map'] ?? $hotel->map);
        $images = collect($providerHotel['Images'] ?? $hotel->images ?? [])
            ->filter(fn ($image) => is_string($image) && $image !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'hotel_code' => $hotel->hotel_code,
            'name' => $providerHotel['HotelName'] ?? $hotel->hotel_name,
            'description_html' => $providerHotel['Description'] ?? $hotel->description,
            'primary_image' => $providerHotel['Image'] ?? $images[0] ?? null,
            'images' => $images,
            'facilities' => array_values($providerHotel['HotelFacilities'] ?? $hotel->facilities ?? []),
            'attractions' => array_values($providerHotel['Attractions'] ?? []),
            'rating' => $providerHotel['HotelRating'] ?? $hotel->hotel_rating,
            'address' => $providerHotel['Address'] ?? $hotel->address,
            'pin_code' => $providerHotel['PinCode'] ?? null,
            'city' => $providerHotel['CityName'] ?? $hotel->city_name,
            'country' => $providerHotel['CountryName'] ?? $hotel->country_name,
            'country_code' => $providerHotel['CountryCode'] ?? $hotel->country_code,
            'location' => [
                'map' => $providerHotel['Map'] ?? $hotel->map,
                'latitude' => $latitude ?? $hotel->latitude,
                'longitude' => $longitude ?? $hotel->longitude,
            ],
            'contact' => [
                'phone_number' => $providerHotel['PhoneNumber'] ?? null,
                'email' => $providerHotel['Email'] ?? null,
                'website_url' => $providerHotel['HotelWebsiteUrl'] ?? null,
                'fax_number' => $providerHotel['FaxNumber'] ?? null,
            ],
            'check_in_time' => $providerHotel['CheckInTime'] ?? null,
            'check_out_time' => $providerHotel['CheckOutTime'] ?? null,
            'fees' => [
                'optional' => data_get($providerHotel, 'HotelFees.Optional', []),
                'mandatory' => data_get($providerHotel, 'HotelFees.Mandatory', []),
            ],
        ];
    }

    /** Map static TBO room metadata; it is never used as a booking or pricing source. */
    protected function normalizeHotelRoomDetails(array $providerHotel): array
    {
        return collect($providerHotel['RoomDetails'] ?? [])
            ->filter(fn ($room) => is_array($room)
                && $this->normalizeRoomId($room['RoomId'] ?? null) !== null
                && ! empty($room['RoomName']))
            ->map(function (array $room): array {
                $images = collect($room['imageURL'] ?? [])
                    ->filter(fn ($image) => is_string($image) && $image !== '')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'room_id' => $this->normalizeRoomId($room['RoomId'] ?? null),
                    'name' => $room['RoomName'] ?? 'Room details',
                    'size' => $room['RoomSize'] ?? null,
                    'description' => $room['RoomDescription'] ?? null,
                    'primary_image' => $images[0] ?? null,
                    'images' => $images,
                ];
            })
            ->values()
            ->all();
    }

    /** Attach static room content by ID, then by a conservative normalized-name match. */
    protected function matchHotelRoomDetailsById(array $searchRoom, Collection $roomDetailsById): ?array
    {
        $roomId = $this->normalizeRoomId($searchRoom['room_id'] ?? null);
        if ($roomId !== null && $roomDetailsById->has($roomId)) {
            return $roomDetailsById->get($roomId);
        }

        $searchName = is_array($searchRoom['name'] ?? null)
            ? implode(' ', $searchRoom['name'])
            : (string) ($searchRoom['name'] ?? '');
        $searchTokens = $this->roomNameTokens($searchName);

        if (count($searchTokens) < 3) {
            return null;
        }

        $bestMatch = $roomDetailsById->values()
            ->map(function (array $roomDetails) use ($searchTokens): array {
                $overlap = array_values(array_intersect(
                    $searchTokens,
                    $this->roomNameTokens((string) ($roomDetails['name'] ?? '')),
                ));
                $distinctiveTerms = ['executive', 'superior', 'deluxe', 'standard', 'king', 'queen', 'twin'];

                return [
                    'room_details' => $roomDetails,
                    'score' => count($overlap) + count(array_intersect($overlap, $distinctiveTerms)),
                    'matches' => count($overlap),
                ];
            })
            ->sortByDesc('score')
            ->first();

        return ($bestMatch['matches'] ?? 0) >= 3 ? $bestMatch['room_details'] : null;
    }

    /** Normalize TBO's differently worded live and static room names for matching. */
    protected function roomNameTokens(string $name): array
    {
        $name = preg_replace_callback('/\b(one|two|three|four|five)\b/i', fn (array $match): string => [
            'one' => '1',
            'two' => '2',
            'three' => '3',
            'four' => '4',
            'five' => '5',
        ][strtolower($match[1])], $name);
        $name = preg_replace('/\bbedrooms?\b/i', 'bedroom', $name);
        $name = preg_replace('/\bbeds?\b/i', 'bed', $name);
        $name = preg_replace('/[^a-z0-9]+/i', ' ', $name);

        return array_values(array_unique(array_filter(
            explode(' ', strtolower(trim($name))),
            fn (string $token): bool => ! in_array($token, ['a', 'an', 'and', 'with', 'or', 'no', 'non', 'smoking', 'city', 'view'], true),
        )));
    }

    /**
     * TBO returns room identifiers inconsistently: a scalar TBORoomID for some
     * rooms and an array-shaped RoomID for others. Convert either form to one
     * usable identifier, treating the provider's placeholder "0" as absent.
     */
    protected function normalizeRoomId(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            foreach (is_array($value) ? $value : [$value] as $candidate) {
                if (! is_scalar($candidate)) {
                    continue;
                }

                $roomId = trim((string) $candidate);
                if ($roomId !== '' && $roomId !== '0') {
                    return $roomId;
                }
            }
        }

        return null;
    }

    protected function normalizeRoom(array $room, string $displayCurrency, ?string $fallbackCurrency = null): array
    {
        $currency = strtoupper((string) ($room['Currency'] ?? $fallbackCurrency ?? ''));
        $totalFare = isset($room['TotalFare']) ? (float) $room['TotalFare'] : null;
        $totalTax = isset($room['TotalTax']) ? (float) $room['TotalTax'] : null;

        return [
            'name' => $room['Name'] ?? [],
            'currency' => $currency ?: null,
            'room_id' => $this->normalizeRoomId(
                $room['TBORoomID'] ?? null,
                $room['RoomID'] ?? null,
            ),
            'booking_code' => $room['BookingCode'] ?? null,
            'inclusion' => $room['Inclusion'] ?? null,
            'day_rates' => $room['DayRates'] ?? [],
            'total_fare' => $totalFare,
            'total_tax' => $totalTax,
            'display_money' => $this->convertSearchMoney($totalFare, $currency, $displayCurrency),
            'display_tax_money' => $this->convertSearchMoney($totalTax, $currency, $displayCurrency),
            'extra_guest_charges' => isset($room['ExtraGuestCharges']) ? (float) $room['ExtraGuestCharges'] : null,
            'recommended_selling_rate' => $room['RecommendedSellingRate'] ?? null,
            'room_promotion' => $room['RoomPromotion'] ?? [],
            'rate_conditions' => $room['RateConditions'] ?? [],
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
            'remaining_seconds' => max(0, now()->diffInSeconds($prebook->expires_at, false)),
            'is_expired' => $prebook->expires_at?->isPast() ?? true,
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
        $room['RateConditions'] = $providerHotel['RateConditions'] ?? $room['RateConditions'] ?? [];

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
        $reservationExpiresAt = $booking->prebook?->expires_at;

        return [
            'booking_id' => $booking->uuid,
            'status' => $booking->status,
            'payment_method' => $booking->payment_method,
            'paid_at' => $booking->paid_at?->toIso8601String(),
            'provider_status_code' => $booking->provider_status_code,
            'confirmation_number' => $booking->confirmation_number,
            'hotel_confirmation_number' => $booking->hotel_confirmation_number,
            'booking_reference_id' => $booking->booking_reference_id,
            'client_reference_id' => $booking->client_reference_id,
            'currency' => $booking->currency,
            'total_fare' => $booking->total_fare,
            'reservation_expires_at' => $reservationExpiresAt?->toIso8601String(),
            'remaining_seconds' => $reservationExpiresAt ? max(0, now()->diffInSeconds($reservationExpiresAt, false)) : 0,
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
