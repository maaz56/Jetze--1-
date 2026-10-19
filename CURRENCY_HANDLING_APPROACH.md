# Currency Handling – Review & Recommended Approach

This document reviews how multi-currency works today in Jetze (Laravel API + Vue SPA),
explains where the UI/functional glitches come from, and proposes one consistent approach
for search → checkout → payment → booking → ledger.

It builds on `currency_conversion_plan.md` (rates as "1 unit = X AED", AED as accounting
base, immutable booking amounts). That plan is right; most of the problems are that the
**frontend does not follow it yet**.

---

## 1. What exists today

### Backend (mostly good)

| Piece | Status |
|---|---|
| `currencies` table, `CurrencyController`, `CurrencyRateHistory` (audited rate changes) | Good |
| `CurrencyConversionService` – bcmath, rate = AED per unit, per-currency `decimal_places`, returns money objects `{amount, currency, decimal_places}` | Good |
| `PriceQuoteService` – stores `provider_currency`, `provider_rate_to_aed`, `display_currency`, `display_rate_to_aed` | Good |
| `BookingPricingService` / `BookingPriceSnapshot` – locks `selling_currency` + `aed_amount` on booking | Good |
| `FlightController::currencyCodeForRequest()` – **forces AED on `*.ae` and PKR on `*.pk`** and ignores the user's choice | Conflicts with the UI switcher |
| Search results cached per user, cache holds one `_currency_code` | Re-pricing on switch requires a new full search |

### Frontend (source of the glitches)

| Piece | Problem |
|---|---|
| `resources/js/lib/utils.js` – `selectedCurrencyCodeState`, `setSelectedCurrencyCode()` fires a `currency-changed` window event | Only **3 pages** listen to it: `CustomerWallet.vue`, `agent/Deposits.vue`, `agent/AgentLedger.vue` |
| `formatAmount(amount, currencyCode = selectedCurrency)` | Defaults to the **currently selected** currency. Used in **~827 places across ~80 files** with a raw number |
| `FlightSearch.vue`, `agent/Flights.vue`, `HotelSearch.vue` | Read `getSelectedCurrencyCode()` once when searching; never re-fetch when the switch changes |
| `lib/useCurrency.js` and `fetchRate()` in `utils.js` | Fetch PKR→AED from a public CDN (`@fawazahmed0/currency-api@latest`) – a second, different rate source from the admin rates. Still used by `convertedAmount()` in `FlightSearch.vue` / `agent/Flights.vue` |
| Stripe calls in ~20 checkout/payment pages | Hard-coded `currency: 'sar'` or `'aed'`, while `amount` comes from a quote in a different currency |
| Ancillaries (seats/meals/baggage) | Fallbacks `'PKR'` (≈49 places) / `'INR'` (≈7), shown with the supplier's currency and added straight into totals |
| Price calculations (`calculateFinalPrice`, `calculateTaxes`, margins, `* 0.03` card fee) | Done on the client from raw fare numbers |
| `Nav.vue` watcher | Comment says "domainCurrency" but `setSelectedCurrencyCode()` does not apply any domain rule, so the UI lets you pick USD on `.ae` while the API silently returns AED |

---

## 2. Why the glitches happen

### Glitch A – "Searched in AED, switched to USD, nothing changes" (or the wrong thing changes)
1. The search ran with `currencyCode=AED`; the server returned AED amounts.
2. The user switches to USD → `selectedCurrencyCodeState` becomes `USD`.
3. Search pages don't listen, so **fare cards don't change**. They still show `AED …` because they
   use `money.currency` from the response. This part is actually correct.
4. Every other place that calls `formatAmount(rawNumber)` now shows **`$1,250.00`** for a number
   that is really **AED 1,250**. That is the "relabel" bug: only the symbol changes, the number stays the same.
   Example: `HotelSearch.vue:919` shows the price-filter label with the new code over old values.

Result: one screen shows a mix of AED and USD labels, and some USD labels sit on AED numbers.

### Glitch B – Payment view changes partly, or not at all
`AT/ATCustomerPaymentView.vue` does the right thing for the total: it uses the **locked**
quote money (`selling_currency`, `aed_amount`) with `formatAmountWithCurrency`. But the
taxes and add-on lines nearby use `formatAmount(calculateTaxes(fare))`, so they pick up the
switcher's currency. On switch, the grand total stays in AED, the breakdown lines change symbol,
and the lines no longer add up.

The payment view **should not** re-price (the quote is locked). The bug is that the switcher
is still active and partly applied, and nothing tells the user why the total did not change.

### Glitch C – Domain rule vs. switcher
On `*.ae` / `*.pk` the backend ignores the requested currency, but the switcher still offers
every enabled currency. The UI says one currency and the data comes back in another.

### Glitch D – Real money risk
- Stripe receives an amount in the quote currency (for example PKR 85,000 → `8500000`) with `currency: 'sar'`.
  The customer could be charged the wrong amount in the wrong currency.
- The card fee (`amount * 0.03`) and margins are computed in the browser, so the user can change them.
- Ancillaries in PKR/INR are added to AED/USD totals without conversion.
- The CDN rate (`fetchRate`) differs from the admin rate table, so two prices for the same fare can appear.

---

## 3. Recommended approach

### 3.1 Core rules

1. **Only the server converts.** The frontend never multiplies by a rate and never assumes a currency.
2. **Every amount sent to the frontend is a Money object.** Never a bare number.
   ```json
   { "amount": "1250.00", "currency": "AED", "decimal_places": 2 }
   ```
   `amount` is a string, so there are no float errors. The frontend only formats it.
3. **Three currency roles, always explicit:**
   - `provider` – what the supplier charged (stored, never shown to customers),
   - `base` – AED, for accounting, ledger, wallet and reports,
   - `display` – what the user selected.
4. **Two price states, and the UI must know which one applies:**
   - **Live** (search results, flight/hotel details before a quote): switching currency re-prices.
   - **Locked** (once a `PriceQuote` exists: checkout, payment, booking details, invoices):
     switching currency does **not** change the amount. The switcher is disabled on these pages
     with a note such as *"Price locked in AED 1,250.00"*. You can optionally show
     *"≈ USD 340.36 (indicative)"* as a secondary line.
5. **Ledger and wallet are always AED.** Other currencies appear only as an indicative secondary value.
6. **Domain policy is decided in one place** and the frontend and backend follow the same rule (see 3.5).

### 3.2 Backend changes

**a) Cache search results in base money and convert when responding**

Today the cache stores results priced in the search currency. Instead, cache each fare's
provider money and its AED (base) money, and convert to the display currency when building
the response. Switching currency then needs only a cheap re-price call, with no new supplier
search:

```php
// routes/api.php
Route::get('flights/search/{token}/reprice', [FlightController::class, 'reprice']);

// FlightController
public function reprice(Request $request, string $token)
{
    $currency = $this->currencyResolver->resolve($request);      // see 3.5
    $flights  = Cache::get($this->flightCacheKey($request) . '_flights');
    abort_unless($flights, 410, 'Search expired, please search again.');

    return response()->json([
        'currency' => $currency,
        'results'  => $this->displayPricer->apply($flights, $currency), // base -> display via CurrencyConversionService
    ]);
}
```

Use the same pattern for hotels (`HotelSearch` / `HotelDetails`).

**b) One `CurrencyResolver` service** in place of `currencyCodeForRequest()`:
- reads the `X-Currency` header (sent by the axios interceptor),
- applies the domain policy,
- checks that the currency is enabled in the `currencies` table,
- falls back to AED.

Use it in flights, hotels, visas, packages, ledger and wallet endpoints.

**c) The quote is the only source for checkout and payment amounts**
- Ancillaries (seat/meal/baggage) are added through an API that converts supplier money to the
  quote's `display_currency` and stores it as a `PriceQuoteItem`. The frontend shows the returned Money.
- The server returns the full breakdown (base fare, taxes, margin, discount, add-ons, card fee,
  total) as Money objects. Drop `calculateFinalPrice` / `calculateTaxes` / `* 0.03` on the client.

**d) Payment intents are built only on the server**
```php
// The frontend sends only booking_id (and the payment method).
$snapshot = $this->bookingPricing->snapshotFor($booking);
$intent = $stripe->paymentIntents->create([
    'amount'   => $this->toMinorUnits($snapshot->selling_amount, $snapshot->selling_currency),
    'currency' => strtolower($snapshot->selling_currency),
    'metadata' => ['booking_id' => $booking->id, 'aed_amount' => $snapshot->aed_amount],
]);
```
Remove `amount` and `currency` from all `SEND_PAYMENT_REQUEST` payloads. If the gateway
cannot settle in the display currency (for example Nomod/Stripe account limits), charge in
the settlement currency and show it clearly: *"You will be charged AED 1,250.00 (≈ USD 340.36)"*.

**e) Rate freshness**
Quotes already store `*_rate_to_aed`. Add a quote expiry (for example 20–30 minutes). If an admin
changes a rate, only new quotes use the new rate, as `currency_conversion_plan.md` requires.

### 3.3 Frontend: one currency module

Replace `lib/useCurrency.js` (the CDN one) and the currency parts of `lib/utils.js` with one
composable, `resources/js/composables/useCurrency.js`:

```js
import { ref, computed, watch, onBeforeUnmount } from "vue";
import { useRoute } from "vue-router";
import store from "@/config/store";

const selected = ref(readInitialCurrency());   // localStorage → domain default → "AED"

export function useCurrency() {
    const route = useRoute();
    const currencies = computed(() => store.getters["currency/currencies"] || []);

    /** "live" pages re-price on switch, "locked" pages never do, "base" pages are AED-only. */
    const mode = computed(() => route?.meta?.currencyMode ?? "live");
    const canSwitch = computed(() => mode.value === "live" && !domainLockedCurrency());

    function setCurrency(code) {
        if (!canSwitch.value) return;
        selected.value = code;
        localStorage.setItem("currencyCode", code);
    }

    /** Run `cb` whenever the user switches currency (auto-removed on unmount). */
    function onCurrencyChange(cb) {
        const stop = watch(selected, (code, old) => code !== old && cb(code));
        onBeforeUnmount(stop);
    }

    return { selected, currencies, mode, canSwitch, setCurrency, onCurrencyChange };
}

/** The ONLY formatter: always takes a Money object, never a bare number. */
export function formatMoney(money, { compact = false } = {}) {
    if (!money?.currency || money.amount == null) return "—";
    const dp = money.decimal_places ?? 2;
    return new Intl.NumberFormat(undefined, {
        style: "currency",
        currency: money.currency,
        minimumFractionDigits: dp,
        maximumFractionDigits: dp,
        notation: compact ? "compact" : "standard",
    }).format(Number(money.amount));
}
```

**Axios:** send the currency once, globally, in `resources/js/config/axios.js`:
```js
apiService.interceptors.request.use((config) => {
    config.headers["X-Currency"] = getSelectedCurrency();
    return config;
});
```
Then drop the per-call `currencyCode` / `currency_code` params.

**Router:** declare the mode for each route in `resources/js/config/router.js` / `services/routes`:
```js
{ path: "/flights",            meta: { currencyMode: "live"   } },
{ path: "/hotel/:id",          meta: { currencyMode: "live"   } },
{ path: "/checkout/:quote",    meta: { currencyMode: "locked" } },
{ path: "/payment/:booking",   meta: { currencyMode: "locked" } },
{ path: "/booking/:id",        meta: { currencyMode: "locked" } },
{ path: "/agent/ledger",       meta: { currencyMode: "base"   } },
```

**Nav switcher (`components/shared/Nav.vue`, `components/agent/Nav.vue`):**
- `live` → normal dropdown.
- `locked` → dropdown disabled, with a chip such as `🔒 AED` and the tooltip *"Prices are locked for this booking"*.
- `base` → show `AED`. Optionally show an indicative value in the selected currency.
- Remove the ad-hoc "admin = AED" and "pick list[0]" logic. Let the resolver decide.

**Search pages:**
```js
const { onCurrencyChange } = useCurrency();
onCurrencyChange(async () => {
    repricing.value = true;                                 // dim the cards or show a skeleton, keep filters and scroll
    const { data } = await api.get(`flights/search/${searchToken}/reprice`);
    flights.value = data.results;
    priceFilter.value = rescale(priceFilter.value, data);   // price-range filter must be re-based too
    repricing.value = false;
});
```
Keep selected filters, sort and scroll position. Only the amounts change. If the reprice call
returns `410`, show *"Search expired"* with a **Search again** button.

### 3.4 What each screen should show

| Screen | Mode | Amount source | On currency switch |
|---|---|---|---|
| Home trending routes, flight/hotel search, details | live | server Money in display currency | call `reprice`, show skeleton |
| Checkout (before pay) | locked | `PriceQuote` breakdown | switch disabled. Optional "≈" line |
| Payment view | locked | `BookingPriceSnapshot` | switch disabled. Gateway amount comes from the server |
| Booking details / invoice / PDF | locked | snapshot (`selling_*`, `aed_amount`) | no change |
| Agent ledger, wallet, deposits | base (AED) | ledger AED | primary stays AED. Secondary "≈ selected" line |
| Admin screens | base (AED) | AED | switch hidden |

### 3.5 Domain policy (pick one and apply it on both sides)

- **Option 1 (recommended): the domain sets the default only.** `.ae` defaults to AED, `.pk` to PKR,
  and the user can switch. Remove the forced override from the backend resolver.
- **Option 2: the domain locks the currency.** Then `canSwitch` must return false on that domain
  and the switcher is hidden.

The current mix (backend forces it, frontend allows switching) causes Glitch C.

---

## 4. Cleanup list (concrete)

1. Delete `resources/js/lib/useCurrency.js`, `fetchRate()` in `utils.js`, and `convertedAmount()` in
   `FlightSearch.vue` / `agent/Flights.vue`. This removes all external rate APIs from the browser.
2. Make `formatAmount(amount)` without a currency **log a warning in dev**, then migrate its ~827 call
   sites to `formatMoney(money)` page by page. Start with checkout and payment pages.
3. Remove `currency: 'sar' | 'aed'` and `amount` from every Stripe `SEND_PAYMENT_REQUEST` (~20 files).
4. Replace `'PKR'` / `'INR'` fallbacks with server-provided Money for ancillaries.
5. Move margin, discount, tax and card-fee math to the server (`CommercialPricingService` already exists).
6. Delete the `* copy.vue` / `* copy.php` duplicates so fixes are not applied to dead files.
7. The ~20 provider-specific checkout pages (AT, PIA, Sabre, Travelport, …) repeat the same
   price/breakdown blocks. Extract `<PriceBreakdown :breakdown="quote.breakdown" />` and
   `<MoneyText :money="..." />` components so currency display is written once.

---

## 5. Rollout order

1. **Money safety first:** server-side payment intents, remove client amount/currency from payments.
2. **Locked mode:** route `meta.currencyMode`, disabled switcher on checkout/payment/booking pages,
   breakdown lines from the quote only. This fixes Glitch B.
3. **CurrencyResolver + `X-Currency` header + domain policy.** This fixes Glitch C.
4. **`reprice` endpoints + `onCurrencyChange` on search pages.** This fixes Glitch A.
5. **Formatter migration** (`formatMoney`) and component extraction, page by page.
6. Remove legacy rate code and duplicate files.

---

## 6. Test checklist

- [ ] Search in AED → switch to USD → every fare card, filter range and "from" price shows USD with converted numbers. No AED number appears with a `$` label.
- [ ] Switch back to AED → the original amounts come back exactly (no rounding drift, because the server converts from the base each time).
- [ ] Open checkout → switcher disabled. The breakdown lines add up to the total in the quote currency.
- [ ] Add a PKR-priced seat on a USD quote → it appears converted to USD, and the total updates from the server.
- [ ] Payment → the gateway charge equals the snapshot amount and currency (check the Stripe dashboard).
- [ ] Admin changes the USD rate → existing quotes, bookings and invoices are unchanged. A new search uses the new rate.
- [ ] `.ae` / `.pk` domains follow the chosen domain policy on both the UI and the API.
- [ ] Agent ledger/wallet always settle in AED. The indicative line updates on switch.
- [ ] A currency with `decimal_places = 0` (for example PKR, if configured that way) renders without decimals everywhere.
- [ ] Reprice after cache expiry → "Search expired" message, not stale or broken prices.
