# Currency Conversion — Simple Implementation Plan

## Core Rules

- Only one admin will add currencies and define their rates.
- AED is the fixed base currency.
- One provider has one currency for its complete booking lifecycle.
  - Example: if AT search returns PKR, its fare details, booking, PNR fetch,
    cancel and refund will use PKR.
  - If provider changes, currency can be different. Example: TravelPort may
    return AED.
- Agent/customer can choose an enabled currency from the switcher.
- Admin rate update affects only new searches/new quotes.
- A booked booking, payment, invoice and ledger record will never change when
  admin updates a rate.


## Phase 1 — Currency Setup

### Update existing files

- `database migration: currencies table`
  - Add: `is_enabled`, `decimal_places`, `is_base`.
  - Keep AED as the only base currency.
  - Reason: admin can disable a currency without deleting old records.

- `app/Http/Controllers/Api/CurrencyController.php`
  - Allow only the admin to add a currency, enable/disable it and enter its
    rate.
  - Do not allow currency delete.
  - Reason: old bookings may depend on that currency.

- `resources/js/pages/admin/Currencies.vue`
  - Keep a simple admin screen: currency code, symbol, rate, enabled/disabled.
  - Clearly show rate format: `1 PKR = X AED`.
  - Reason: admin should always know what amount is being entered.


## Phase 2 — Rate History

### New files

- `database migration: currency_rate_history table`
  - Fields: `currency_code`, `rate_to_aed`, `old_rate`, `changed_by`, `reason`,
    `created_at`.
  - Logic: when admin changes a rate, current rate updates for new searches and
    a history row is saved.
  - Reason: later we can see who changed a rate, when, and from which value.

- `app/Models/CurrencyRateHistory.php`
  - Represents one admin rate-change record.

- `app/Services/CurrencyRateService.php`
  - Gets the latest rate for a currency and saves rate history when admin
    updates it.
  - Reason: rate logic stays in one place.


## Phase 3 — One Central Conversion Service

### New files

- `app/Services/CurrencyConversionService.php`
  - Input: amount, source currency, target currency.
  - Logic: converts through AED.
  - Formula: `target = source x source AED rate / target AED rate`.
  - Reason: all APIs and all pages use one conversion formula.

### Update existing files

- `app/Transformers/AtFlightTransformer.php`
- Other provider transformers: PIA, TravelPort, Flydubai, AirSial, OneApi.
  - Logic: each transformer returns original provider amount and provider
    currency. It does not convert.
  - Reason: each provider controls its own booking currency.

- `app/Services/FlightAggregationService.php`
  - Logic: after a transformer returns provider money, call
    `CurrencyConversionService` for the selected switcher currency.
  - Reason: all search results get the same conversion behaviour.


## Phase 4 — Search Quote

### New files

- `database migration: price_quotes table`
  - Fields: `uuid`, `provider`, `provider_amount`, `provider_currency`,
    `display_amount`, `display_currency`, `aed_amount`, `expires_at`,
    `flight_data`, timestamps.
  - Logic: every selected flight price gets a quote record.
  - Reason: browser amount is not trusted at booking time.

- `app/Models/PriceQuote.php`
  - Represents one search/checkout price quote.

- `app/Services/PriceQuoteService.php`
  - Creates and reads quotes using provider amount/currency and converted
    display amount/currency.
  - Reason: search and booking use the same saved price information.

### Update existing files

- `app/Http/Controllers/Api/FlightController.php`
  - Accept selected `currencyCode` only as display preference.
  - Return `quote_id`, provider money and display money.

- Search result and checkout Vue pages
  - Send `quote_id` when user continues.
  - Display backend-returned converted amount; do not calculate in browser.

- `resources/js/lib/utils.js`
  - Replace financial `formatAmount(amount)` usage with formatter that accepts
    `{ amount, currency }`.
  - Reason: symbol and amount always remain together.


## Phase 5 — Booking Lock

### New files

- `database migration: booking_price_snapshots table`
  - Fields: `booking_id`, `quote_id`, `provider`, `provider_amount`,
    `provider_currency`, `selling_amount`, `selling_currency`, `aed_amount`,
    `rate_used`, timestamps.
  - Logic: create this record once booking succeeds.
  - Reason: this is the permanent booked-price record.

- `app/Models/BookingPriceSnapshot.php`
  - Represents the locked price for one booking.

- `app/Services/BookingPricingService.php`
  - Loads quote, checks quote expiry, locks its money values and creates the
    booking snapshot.
  - Reason: one service owns the booking amount logic.

### Update existing files

- `app/Http/Controllers/Api/BookingController.php`
  - Receive `quote_id` instead of trusting frontend `amount` and `currency`.
  - Book provider using the quote's original provider amount/currency.
  - Save final `BookingPriceSnapshot` after successful booking.

- `database migration: flight_bookings table`
  - Add: `price_quote_id`, `price_snapshot_id`, `selling_currency`,
    `selling_amount`, `aed_amount`.
  - Reason: quick booking screen/report values plus link to detailed snapshot.


## Phase 6 — Payment and Invoice

### Update existing files

- `app/Http/Controllers/Api/PaymentController.php`
  - Load locked `BookingPriceSnapshot` and use its selling amount/currency for
    payment.
  - Do not use amount sent from frontend.
  - Reason: customer cannot change payment amount in request.

- `app/Http/Controllers/Api/ZohoController.php`
  - Create invoice from locked booking snapshot.
  - Reason: invoice must stay equal to booked selling amount.


## Phase 7 — Post-Booking API Calls

### New files

- `database migration: provider_booking_events table`
  - Fields: `booking_id`, `provider`, `stage`, `provider_reference`,
    `provider_amount`, `provider_currency`, `response_data`, `created_at`.
  - Stages: `book`, `ticket`, `pnr_fetch`, `cancel`, `void`, `refund`.
  - Reason: all later provider API responses are stored against the booking.

- `app/Models/ProviderBookingEvent.php`
  - Represents one provider response during booking lifecycle.

- `app/Services/ProviderBookingSyncService.php`
  - Saves provider response and updates operational details: PNR, ticket
    number, booking status, schedule.
  - It must not update the locked booking snapshot.
  - Reason: PNR fetch can happen many times but booked price stays stable.

### Update existing files

- `app/Http/Controllers/Api/BookingController.php`
  - For PNR fetch, cancel, void and refund: save `ProviderBookingEvent` with
    provider currency/amount.
  - Use the booking's original provider currency for the call.


## Phase 8 — Adjustment and Audit

### New files

- `database migration: booking_adjustments table`
  - Fields: `booking_id`, `type`, `amount`, `currency`, `reason`,
    `created_by`, timestamps.
  - Logic: supplier penalty, refund, or admin manual difference creates a new
    adjustment record.
  - Reason: original booked amount is never overwritten.

- `app/Models/BookingAdjustment.php`
  - Represents a separate financial change after booking.

- `app/Services/BookingAdjustmentService.php`
  - Creates adjustments and converts their AED value for accounting/reporting.
  - Reason: every after-booking financial change is traceable.


## Final Expected Flow

1. Provider sends a price in its own currency.
2. Transformer preserves that provider amount/currency.
3. Central service converts it only for the selected switcher currency.
4. Quote saves provider money, display money and AED amount.
5. Booking uses quote's provider money for provider API call.
6. Booking snapshot locks the sale amount permanently.
7. Later PNR/details fetch updates operational data only.
8. Cancel/refund/penalty becomes a new event or adjustment; original booking
   amount remains unchanged.


## Current ancillary flow

Abhi aapka assumption correct hai: AT ancillaries backend se fetch hoti hain, lekin **raw provider format/amount mein frontend ko forward** ho rahi hain. Conversion aur final total frontend par ho raha hai.

```text
Checkout
  → POST /ancillaries
  → BookingController::getAncillaries()
  → AtApiService::fetchAncillaries()
  → AT SSR + Seat Map raw response
  → Vuex flight store
  → ATCustomerFlightCheckout.vue
```

Main files:

- `ATCustomerFlightCheckout.vue`
  - Quote ki fresh TUI se ancillary request banata hai.
  - `selectedExtras` mein baggage/meal/seat ka raw provider item save karta hai.
  - `getExtrasTotal()` raw `Charge / Fare / SSRNetAmount` add karta hai.
  - `formatAmount()` selected currency sign ke saath raw amount show kar sakta hai—wrong conversion risk.
  - Final booking mein `selectedExtras` browser se bhejta hai.

- `BookingController::getAncillaries()`
  - AT response ko sirf wrap karke frontend bhej raha hai; conversion/mapping nahi.

- `AtApiService::fetchAncillaries()`
  - AT SSR and seat-map APIs call karta hai.
  - Raw response return karta hai.

- `BookingController::patchAncillaries()`
  - AT ke liye currently koi handling nahi. Isliye selection backend par validate/save nahi hoti.

- `AtApiService::atBooking()`
  - Browser ke `selectedExtras` se SSR references aur raw SSR amount bana kar AT booking API ko bhej raha hai. Ye secure nahi hai.

Current quote sirf fare snapshot/totals save karta hai. **Selected baggage, meal, seat quote mein save nahi hotay.**

## Required new flow

```text
Checkout loads active quote
  → Backend fetches AT ancillaries using locked fresh TUI
  → Backend maps + converts option prices
  → Frontend displays display_money only
  → User selects an item reference
  → Backend validates + saves selection in quote
  → Backend recalculates quote total
  → Frontend renders returned quote breakdown
  → Booking uses quote_id only for fare/add-on price and AT SSR references
```

## Implementation plan

### 1. Convert available ancillary options on backend

New files:

- `app/Transformers/AtAncillaryTransformer.php`
  - Raw AT SSR/seat response ko one common structure mein map karega.
  - Every option: title, type, segment, traveller, provider item references, availability, `source_money`, `base_money`, `display_money`.

- `app/Services/AncillaryPricingService.php`
  - Quote ki locked currency rates use karke ancillary amount convert karega.
  - Current admin rate dobara use nahi karega; quote wali rate use hogi, so same checkout mein amounts change nahi honge.
  - Provider currency quote currency se match validate karega.

Update:

- `AtApiService.php`
  - AT se SSR/seat response fetch karega only.

- `BookingController.php`
  - Raw response direct return karne ke bajaye transformer/service se pass karega.

- New endpoint:
  - `GET /flight-quotes/{quote_id}/ancillaries`
  - Active quote, locked fresh TUI, selected fare refs se options fetch karega.
  - User ownership and 15-minute expiry check hogi.

### 2. Save selected ancillary lines in quote

New table/model:

- `price_quote_items`
  - `price_quote_id`
  - `type`: `baggage`, `meal`, `seat`
  - `status`: `active` / `removed`
  - provider references: `FUID`, `PaxID`, `SSID`
  - segment/traveller references
  - title and quantity
  - source amount/currency
  - AED amount
  - display amount/currency
  - locked rates
  - selected timestamp / removed timestamp
  - raw provider item JSON for audit

Quote parent will keep latest aggregate totals:

```text
fare total + active ancillary total = quote total
```

Quote ki original fare pricing bhi `provider_pricing_data` mein remain karegi. AT booking ke liye:

```text
NetAmount = locked fare NetAmount
SSRAmount = active quote ancillary provider total
```

New endpoint:

- `PUT /flight-quotes/{quote_id}/ancillaries`
  - Frontend only provider item references bhejega, price nahi.
  - Backend current AT options se selection validate karega.
  - Active/removed quote item rows update karega.
  - Quote totals AED/display/provider currency mein recalculate karega.
  - Updated breakdown frontend ko return karega.

Selection quote ki 15-minute expiry extend nahi karegi.

### 3. Checkout frontend change

Update `ATCustomerFlightCheckout.vue`:

- Raw `selectedExtras`, `extraCharges`, `getExtrasTotal()` price calculation remove/retire.
- Ancillary options backend endpoint se load kare.
- User select/remove kare to quote ancillary update endpoint call ho.
- UI backend `display_money` show kare.
- Total Amount hamesha latest quote `display_money` show kare:
  - Fare
  - Baggage
  - Meal
  - Seat
  - Grand total
- Update ke dauran “Updating price…” state.
- Backend confirmation ke baghair Next Step/Pay disabled.

Frontend sirf selection IDs aur display rendering karega; price addition/conversion nahi.

### 4. Secure AT booking

Update `BookingController.php` and `AtApiService.php`:

- Browser se `selectedExtras`, amount, TUI, NetAmount trust nahi honge.
- `quote_id` se active quote + active ancillary lines load hongi.
- Backend stored `FUID/PaxID/SSID` se AT SSR payload banayega.
- Backend stored source ancillary total se `SSRAmount` bhejega.
- AT provider response final authority rahega; unavailable/changed ancillary par booking reject aur refresh required hoga.

### 5. Permanent booking audit

New table/model:

- `booking_price_snapshot_items`
  - Booking snapshot ki permanent fare/add-on line history.
  - Type, title, traveller/segment, provider/AED/display amounts, rates, provider refs.

Successful booking par:

```text
price quote (30-day history)
  → active fare + ancillary lines
  → permanent booking snapshot + snapshot items
```

Quote 30 days retained rahega. Booking snapshot aur items permanently rahenge.

In short: **yes, selected ancillary quote mein save hogi—but browser price nahi bhejega. Backend provider item ID verify karega, amount convert/lock karega, quote total update karega, aur booking same saved selection use karegi.**