<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotelSearchSession;
use App\Models\TboHotel;
use App\Models\TboHotelCity;
use App\Models\TboHotelCountry;
use App\Services\TboHotelService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class HotelController extends Controller
{
    public function __construct(protected TboHotelService $tboHotelService)
    {
    }

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

        $likeQuery = '%' . $query . '%';

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
        ]);

        $this->validateChildrenAges($validated['rooms']);

        $destination = $validated['destination'];
        $hotelCodes = $this->resolveHotelCodes($destination['type'], $destination['value']);

        if ($hotelCodes->isEmpty()) {
            return response()->json([
                'message' => 'No TBO hotel codes found for the selected destination. Please sync hotel data for this country or city first.',
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
        $hotels = $statusCode === 200 ? $this->normalizeSearchResults($tboResponse) : collect();

        return response()->json([
            'message' => $this->statusMessage($statusCode, data_get($tboResponse, 'Status.Description')),
            'provider_status' => data_get($tboResponse, 'Status'),
            'data' => [
                'search_session_id' => $session->uuid,
                'hotels' => $hotels->values(),
            ],
        ], $this->httpStatusForProviderStatus($statusCode));
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

    protected function normalizeSearchResults(array $tboResponse): Collection
    {
        $results = collect($tboResponse['HotelResult'] ?? []);
        $hotelCodes = $results->pluck('HotelCode')->map(fn ($code) => (string) $code)->all();
        $staticHotels = TboHotel::whereIn('hotel_code', $hotelCodes)->get()->keyBy('hotel_code');

        return $results->map(function (array $result) use ($staticHotels) {
            $hotelCode = (string) ($result['HotelCode'] ?? '');
            $hotel = $staticHotels->get($hotelCode);
            $rooms = collect($result['Rooms'] ?? [])->map(fn (array $room) => $this->normalizeRoom($room))->values();
            $lowestFare = $rooms->pluck('total_fare')->filter(fn ($fare) => $fare !== null)->min();
            $images = $hotel?->images ?: [];

            return [
                'hotel_code' => $hotelCode,
                'name' => $hotel?->hotel_name ?: 'Hotel ' . $hotelCode,
                'city' => $hotel?->city_name,
                'country' => $hotel?->country_name,
                'rating' => $hotel?->hotel_rating,
                'address' => $hotel?->address,
                'map' => $hotel?->map,
                'image' => $images[0] ?? null,
                'currency' => $result['Currency'] ?? null,
                'lowest_total_fare' => $lowestFare,
                'rooms' => $rooms,
            ];
        });
    }

    protected function normalizeRoom(array $room): array
    {
        return [
            'name' => $room['Name'] ?? [],
            'room_id' => $room['RoomID'] ?? null,
            'booking_code' => $room['BookingCode'] ?? null,
            'inclusion' => $room['Inclusion'] ?? null,
            'total_fare' => isset($room['TotalFare']) ? (float) $room['TotalFare'] : null,
            'total_tax' => isset($room['TotalTax']) ? (float) $room['TotalTax'] : null,
            'extra_guest_charges' => isset($room['ExtraGuestCharges']) ? (float) $room['ExtraGuestCharges'] : null,
            'recommended_selling_rate' => $room['RecommendedSellingRate'] ?? null,
            'room_promotion' => $room['RoomPromotion'] ?? [],
            'meal_type' => $room['MealType'] ?? null,
            'is_refundable' => (bool) ($room['IsRefundable'] ?? false),
            'with_transfers' => (bool) ($room['WithTransfers'] ?? false),
            'supplements' => $room['Supplements'] ?? [],
            'cancel_policies' => $room['CancelPolicies'] ?? [],
        ];
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
                if (!is_numeric($age) || (int) $age < 0 || (int) $age > 18) {
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
            'label' => trim($city->name . ($countryName ? ', ' . $countryName : '')),
            'value' => $city->city_code,
            'city_code' => $city->city_code,
            'country_code' => $city->country_code,
        ];
    }

    protected function hotelSuggestion(TboHotel $hotel): array
    {
        return [
            'type' => 'hotel',
            'label' => trim($hotel->hotel_name . ($hotel->city_name ? ', ' . $hotel->city_name : '')),
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
