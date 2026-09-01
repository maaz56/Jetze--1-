# TBO Hotel API Implementation Plan

This document separates the TBO Hotel API document instructions from the requested project implementation.

## User Request

Implement hotel search in this project so the Hotels tab has a destination/property search bar like the screenshot. The user should be able to search by:

- Country, for example `United Arab Emirates`
- City, for example `Dubai`
- Hotel/property name, for example `Sofitel`

The search bar should lead into real hotel availability and booking flow using the attached TBO Hotel API specification and Postman collection.

## TBO API Instructions From The Documents

TBO uses Basic Auth with the username and password provided by TBO. These credentials must stay on the Laravel backend and must never be exposed in Vue.

Base URLs found in the documents:

- PDF staging/current format: `https://api.tbotechnology.in/HotelAPI`
- Postman collection older/sample format: `http://api.tbotechnology.in/TBOHolidays_HotelAPI`
- Live format from PDF: `{Live-URL}/HotelAPI`

Before implementation, confirm with TBO which base URL is active for your account. The PDF dated April 2026 says the staging endpoint changed, so use the PDF endpoint unless TBO credentials specifically require the Postman one.

Main endpoints:

- `GET /CountryList`
- `POST /CityList`
- `POST /TBOHotelCodeList`
- `POST /HotelDetails`
- `POST /Search`
- `POST /PreBook`
- `POST /Book`
- `POST /BookingDetail`
- `POST /BookingDetailsBasedOnDate`
- `POST /Cancel`

Important API behavior:

- `Search` does not accept free text destination names. It requires `HotelCodes`, a comma-separated list of TBO hotel codes.
- TBO recommends sending around 100 hotel codes per search request.
- `CheckIn` and `CheckOut` format is `YYYY-MM-DD`.
- `GuestNationality` must be an ISO 3166-1 alpha-2 country code, for example `PK`, `AE`, `US`.
- Do not hardcode guest nationality.
- Recommended `Search` timeout is 5-23 seconds.
- Recommended `PreBook` timeout is 23 seconds.
- Recommended `Book` timeout is 120 seconds.
- Booking should happen within 30 minutes from search.
- Use `IsDetailedResponse: false` in search for better performance.
- Cancellation policy and norms from `PreBook` are final and must be shown before booking.
- Mandatory `AtProperty` supplements must be shown before or during booking.

## How Free Text Search Is Possible

The frontend cannot call TBO `Search` directly with `Dubai` or `Sofitel`.

We need our own local hotel destination index:

1. Sync countries from `CountryList`.
2. Sync cities per country from `CityList`.
3. Sync hotels per city from `TBOHotelCodeList` with `IsDetailedResponse: true`.
4. Store countries, cities, and hotels in local database tables.
5. The search bar queries our local tables by country name, city name, and hotel name.
6. When the user selects a suggestion, Laravel converts that selection into TBO hotel codes.
7. Laravel sends those hotel codes to TBO `Search`.

This is the key bridge between the requested UI and the TBO API.

## Current Project State

The project is a Laravel backend with Vue frontend.

Existing hotel UI files:

- `resources/js/pages/HotelSearch.vue`
- `resources/js/pages/HotelDetails.vue`
- `resources/js/pages/HotelCheckout.vue`
- `resources/js/pages/agent/DashboardFlights.vue`
- `resources/js/pages/Home.vue`
- `resources/js/services/routes/client.routes.js`

Current state:

- `HotelSearch.vue` contains static sample hotel data.
- `DashboardFlights.vue` already mounts `<HotelSearch />` in the Hotels tab.
- Client routes already include:
  - `/hotel/search`
  - `/hotel/details`
  - `/hotel/checkout`
- Backend does not yet have TBO hotel service/controllers/models.

## Recommended Backend Structure

Add config:

- `config/tbohotel.php`

Add environment variables:

```env
TBO_HOTEL_BASE_URL=https://api.tbotechnology.in/HotelAPI
TBO_HOTEL_USERNAME=
TBO_HOTEL_PASSWORD=
TBO_HOTEL_TIMEOUT_SEARCH=23
TBO_HOTEL_TIMEOUT_PREBOOK=23
TBO_HOTEL_TIMEOUT_BOOK=120
```

Add service:

- `app/Services/TboHotelService.php`

Responsibilities:

- Create authenticated HTTP requests with Basic Auth.
- Wrap all TBO endpoints.
- Log request/response failures without exposing credentials.
- Normalize inconsistent endpoint casing between docs and Postman if needed.

Suggested methods:

```php
countryList(): array
cityList(string $countryCode): array
tboHotelCodeList(string $cityCode, bool $detailed = true): array
hotelDetails(string|array $hotelCodes, string $language = 'EN', bool $roomDetails = false): array
search(array $payload): array
preBook(array $payload): array
book(array $payload): array
bookingDetail(array $payload): array
bookingDetailsBasedOnDate(string $fromDate, string $toDate): array
cancel(array $payload): array
```

Add controller:

- `app/Http/Controllers/Api/HotelController.php`

Suggested API routes:

```php
Route::prefix('hotels')->group(function () {
    Route::get('suggestions', [HotelController::class, 'suggestions']);
    Route::post('search', [HotelController::class, 'search']);
    Route::post('prebook', [HotelController::class, 'prebook'])->middleware('auth:sanctum');
    Route::post('book', [HotelController::class, 'book'])->middleware('auth:sanctum');
    Route::get('{hotelCode}', [HotelController::class, 'show']);
});
```

Admin/sync routes should be protected:

```php
Route::middleware(['auth:sanctum', 'log.route'])->prefix('admin/hotels')->group(function () {
    Route::post('sync-countries', [HotelSyncController::class, 'syncCountries']);
    Route::post('sync-cities', [HotelSyncController::class, 'syncCities']);
    Route::post('sync-hotels', [HotelSyncController::class, 'syncHotels']);
});
```

## Recommended Database Tables

Create `tbo_hotel_countries`:

```text
id
code
name
raw_response nullable json
timestamps
```

Create `tbo_hotel_cities`:

```text
id
country_code
city_code
name
raw_response nullable json
timestamps
```

Create `tbo_hotels`:

```text
id
hotel_code
hotel_name
hotel_rating nullable
address nullable
country_code
country_name nullable
city_code
city_name nullable
map nullable
latitude nullable
longitude nullable
images nullable json
facilities nullable json
description nullable longText
raw_response nullable json
search_text indexed/fulltext
timestamps
```

Create `hotel_search_sessions`:

```text
id
uuid
user_id nullable
destination_type country|city|hotel
destination_code
check_in
check_out
guest_nationality
pax_rooms json
tbo_request json
tbo_response json
expires_at
timestamps
```

Create `hotel_bookings`:

```text
id
uuid
user_id
hotel_code
booking_code
booking_reference_id nullable
confirmation_number nullable
client_reference_number
status
currency
total_fare
total_tax
prebook_response json
book_request json
book_response json
check_in
check_out
guest_nationality
pax_rooms json
timestamps
```

## Data Sync Flow

Country sync:

1. Call TBO `GET /CountryList`.
2. Upsert records by `Code`.

City sync:

1. For each country, call `POST /CityList` with:

```json
{
  "CountryCode": "AE"
}
```

2. Upsert by `country_code + city_code`.

Hotel sync:

1. For each city, call `POST /TBOHotelCodeList` with:

```json
{
  "CityCode": "130452",
  "IsDetailedResponse": true
}
```

2. Upsert hotels by `HotelCode`.
3. Build `search_text` from hotel name, city, country, address, and rating.

This sync should run as queued jobs because the hotel dataset may be large.

Suggested commands:

```bash
php artisan make:command SyncTboHotelCountries
php artisan make:command SyncTboHotelCities
php artisan make:command SyncTboHotels
```

## Search Bar Flow

Frontend input placeholder:

```text
Enter City/Hotel/Area/building
```

Expected behavior:

1. User types at least 2 characters.
2. Vue calls `GET /api/hotels/suggestions?q=dub`.
3. Backend searches local tables.
4. Suggestions return mixed result types:

```json
[
  {
    "type": "city",
    "label": "Dubai, United Arab Emirates",
    "value": "115936",
    "country_code": "AE"
  },
  {
    "type": "hotel",
    "label": "Sofitel Dubai The Palm, Dubai",
    "value": "1234567",
    "city_code": "115936",
    "country_code": "AE"
  },
  {
    "type": "country",
    "label": "United Arab Emirates",
    "value": "AE"
  }
]
```

5. User selects one suggestion.
6. User selects check-in, check-out, rooms, adults, children, and guest nationality.
7. User clicks Search.
8. Vue calls `POST /api/hotels/search`.

## Search Request Mapping

Frontend request to our backend:

```json
{
  "destination": {
    "type": "city",
    "value": "115936",
    "label": "Dubai, United Arab Emirates"
  },
  "check_in": "2026-09-23",
  "check_out": "2026-09-25",
  "guest_nationality": "PK",
  "rooms": [
    {
      "adults": 1,
      "children": 0,
      "children_ages": []
    }
  ]
}
```

Backend converts this to TBO:

```json
{
  "CheckIn": "2026-09-23",
  "CheckOut": "2026-09-25",
  "HotelCodes": "1000001,1000002,1000003",
  "GuestNationality": "PK",
  "PaxRooms": [
    {
      "Adults": 1,
      "Children": 0,
      "ChildrenAges": []
    }
  ],
  "ResponseTime": 23,
  "IsDetailedResponse": false,
  "Filters": {
    "Refundable": false,
    "NoOfRooms": 0,
    "MealType": "All"
  }
}
```

Hotel-code rules:

- If destination type is `hotel`, send only that hotel code.
- If destination type is `city`, send the first batch of matching hotel codes for that city, ideally 100 codes per TBO recommendation.
- If destination type is `country`, do not immediately search every hotel in the country. Ask the user to choose a city or show top cities first. Searching an entire country may exceed limits and produce poor performance.
- If the user types a country name, suggestions can show the country, but after selecting it the UI should show city suggestions inside that country.

## Results Flow

After TBO `Search` returns:

1. Match each `HotelResult[].HotelCode` with local `tbo_hotels`.
2. Merge static hotel info with live room/rate info.
3. Return normalized cards to Vue.

Suggested frontend shape:

```json
{
  "search_session_id": "uuid",
  "hotels": [
    {
      "hotel_code": "1120548",
      "name": "Luxury Room Hotel",
      "city": "Dubai",
      "country": "United Arab Emirates",
      "rating": 5,
      "address": "Palm Jumeirah",
      "image": "https://...",
      "lowest_total_fare": 152.88,
      "currency": "USD",
      "rooms": [
        {
          "name": ["Luxury Room, 1 King Bed"],
          "booking_code": "1120548!TB!...",
          "inclusion": "Free WiFi",
          "total_fare": 152.88,
          "total_tax": 28.12,
          "meal_type": "Room_Only",
          "is_refundable": false,
          "with_transfers": false,
          "supplements": []
        }
      ]
    }
  ]
}
```

## Details, PreBook, And Booking Flow

Hotel details page:

1. User clicks a hotel card.
2. Vue navigates to `/hotel/details?hotelCode=1120548&sessionId=...`.
3. Backend returns local hotel static details plus rooms from the stored search session.
4. Optionally call TBO `HotelDetails` with `IsRoomDetailRequired: true` if room images/details are needed.

PreBook:

1. User selects room/rate.
2. Vue calls `POST /api/hotels/prebook` with `BookingCode`.
3. Backend calls TBO `PreBook`.
4. Show final cancellation policies, fare, supplements, and mandatory at-property charges.

Book:

1. User enters lead guest and passenger details.
2. Vue calls `POST /api/hotels/book`.
3. Backend creates a local pending booking.
4. Backend calls TBO `Book`.
5. Store TBO booking response.
6. Show confirmation number/voucher status.

Booking detail:

Use TBO `BookingDetail` after booking to refresh final status by `ConfirmationNumber` or `BookingReferenceId`.

Cancel:

Use TBO `Cancel`, then update local `hotel_bookings.status`.

## Frontend Changes

Replace static `HotelSearch.vue` data with real state:

- `destinationQuery`
- `destinationSuggestions`
- `selectedDestination`
- `checkIn`
- `checkOut`
- `rooms`
- `guestNationality`
- `isSearching`
- `hotelResults`
- `searchSessionId`

Add API calls through existing Axios service:

- `GET /api/hotels/suggestions?q=...`
- `POST /api/hotels/search`
- `GET /api/hotels/{hotelCode}?session_id=...`
- `POST /api/hotels/prebook`
- `POST /api/hotels/book`

The Hotels tab UI should contain:

- Destination/property combobox
- Check-in date picker
- Check-out date picker
- Nights count
- Rooms and guests selector
- Nationality selector
- Search button
- Loading state
- Empty/no availability state
- Hotel results grid/list

For the screenshot-like search box:

- On focus, show a white dropdown under the destination field.
- Show recent/popular destinations before typing.
- After typing, show country/city/hotel suggestions with icons.
- Require a selected suggestion before final search.

## Validation Rules

Backend validation for `/api/hotels/search`:

- `destination.type` required, one of `country`, `city`, `hotel`
- `destination.value` required
- `check_in` required, date, today or future
- `check_out` required, date after `check_in`
- `guest_nationality` required, 2-letter country code
- `rooms` required array, minimum 1
- `rooms.*.adults` required integer between 1 and 8
- `rooms.*.children` required integer between 0 and 4
- `rooms.*.children_ages` required if children > 0
- each child age integer between 0 and 18

## Error Handling

Map TBO statuses to frontend messages:

- `200 SUCCESS`: show results or confirmed booking.
- `201 NO_AVAILABILITY`: show no hotels available.
- `207 RATE_UNAVAILABLE`: ask user to select another room/rate.
- `315 BOOKINGCODE_EXPIRED`: ask user to run search again.
- `300 INSUFFICIENT_BALANCE`: show admin/agent balance issue.
- `401 UNAUTHORIZED`: log credential issue server-side.
- `429 LIMIT_EXCEEDED`: retry later or throttle searches.
- `400 INVALID_REQUEST`: show validation issue.
- `405 BOOKING_FAIL`: show booking failed and log full provider response.
- `479 CANCEL_FAIL`: show cancellation failed and log response.
- `500 UNEXPECTED_ERROR`: generic provider error message.

## Security

- Keep TBO username/password only in `.env`.
- Never call TBO directly from Vue.
- Do not log Basic Auth headers.
- Store full provider payloads only where useful for support/debugging.
- Mask guest payment/card data if card booking mode is used.
- Protect booking, prebook, sync, and admin endpoints with `auth:sanctum`.

## Performance Notes

- Use local DB for suggestions; do not call TBO on every keypress.
- Debounce frontend suggestions by 250-400ms.
- Cache common suggestions.
- Limit suggestions to 10-15 rows.
- For city searches, send hotel codes in batches of about 100.
- Search can be slow, so show skeleton/loading UI.
- Store search results in `hotel_search_sessions` with `expires_at` because TBO booking codes expire.

## Phased Implementation

Phase 1: Provider foundation

- Add config and env variables.
- Add `TboHotelService`.
- Add basic tests for payload building and status mapping.

Phase 2: Static data sync

- Add migrations/models for countries, cities, and hotels.
- Add sync commands/jobs.
- Import countries, cities, and hotels from TBO.

Phase 3: Search bar

- Add `/api/hotels/suggestions`.
- Replace static hotel search UI with destination combobox and search controls.
- Support country, city, and hotel-name suggestions.

Phase 4: Availability search

- Add `/api/hotels/search`.
- Convert selected destination to hotel codes.
- Call TBO `Search`.
- Merge live availability with local hotel static data.
- Render real results.

Phase 5: Details and checkout

- Update `HotelDetails.vue` to use selected hotel/session data.
- Add `PreBook` before checkout confirmation.
- Show final cancellation policies and mandatory supplements.
- Add `HotelCheckout.vue` booking submission.

Phase 6: Booking management

- Persist bookings.
- Add booking detail refresh.
- Add cancellation support.
- Add admin visibility/reporting as needed.

## Practical First Milestone

The first usable milestone should be:

1. Sync countries, cities, and hotels for one country, for example UAE.
2. Make the Hotels tab search bar return suggestions for `Dubai` and hotel names inside Dubai.
3. Search one selected city/hotel using TBO `Search`.
4. Display hotel result cards with live minimum price.

Once that works, expand sync coverage to all countries and complete prebook/book.

