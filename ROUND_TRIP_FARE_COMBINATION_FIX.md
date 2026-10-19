# Round-trip fare combinations: make the result card match the side sheet

## Problem

On round-trip (RSF) results, the price on the result card sometimes leaves out the return fare.

| Where | Shows | Correct? |
|---|---|---|
| Result card (`FZ 355, FZ 392`) | **AED 875.00** | ❌ |
| Airline strip (flydubai) | AED 875.00 | ❌ (same function) |
| Card badge | `Lite + Lite` | ✅ |
| Side sheet → Fare Options → `LITE + LITE` | AED 1,760.00 | ✅ |
| Side sheet → Total price | AED 1,760.00 | ✅ |

The fare options in the side sheet are already right: each option is one combined onward + return
fare. The result card must show **the cheapest of those same combined options**, so that
"card price" equals "price when the side sheet opens".

---

## Root cause

### 1. The card price ignores `fare_combinations` (main bug)

`flightDisplayMoney()` ([FlightSearch.vue:1624](resources/js/pages/FlightSearch.vue#L1624)) and the
fallback `calculateTotalFare()` ([FlightSearch.vue:1353](resources/js/pages/FlightSearch.vue#L1353))
do this:

```
for each leg:
    pick the cheapest fare on that leg on its own
sum them
```

For AT return results, onward and return fares are **not independent**. They can only be sold as the
pairs in `leg.fare_combinations` (matched by `ReturnIdentifier`). Picking a minimum per leg mixes
fares from *different* pairs. AT also sometimes puts the whole round-trip NetFare on one leg and
`0` on the other (that is why `isZeroPriceFare()` exists). For example:

```
Pair A: onward Lite 875  + return Lite 885  = 1,760   ← real cheapest
Pair B: onward X  1,760  + return Y    0    = 1,760

per-leg minimum: onward 875 (from A) + return 0 (from B) = 875   ← shown on card, not sellable
```

So the return fare looks like it "was not added". In fact the card adds the wrong return fare.

The same function feeds:
- `displayFlightPrice()` → price sorting, the price filter, min/max slider limits
- `cheapestFlightsByAirline` → the airline strip at the top

So sorting and filtering are also wrong for these results.

### 2. Four different meanings of "cheapest"

| Function | How it picks |
|---|---|
| `flightDisplayMoney` (card price) | per-leg minimum, ignores pairing ❌ |
| `combinedFareLabel` (card badge) | cheapest valid pair by `fareSortAmount` (selling money) |
| `initializeSelectedFares` (side sheet default) | `fare_combinations[0]`, which the backend sorted by provider `billable_price` |
| `combinationFareCards(leg, fareSortAmount)` | **ignores the second argument** and returns the backend order unsorted |

The badge and the price can describe different fares, and the side sheet can open on a different pair
than the one the card advertised.

### 3. `combined_display_money` is pre-margin money (latent bug)

`AtFlightTransformer::buildReturnFareCombinations()`
([AtFlightTransformer.php:433](app/Transformers/AtFlightTransformer.php#L433)) builds
`combined_display_money` from each fare's `display_money` and sorts by `billable_price`.
After that, `CommercialPricingService::applyToAtFlights()`
([CommercialPricingService.php:16](app/Services/CommercialPricingService.php#L16)) adds
`selling_display_money` (segment margin + promotion) to each leg fare, but **never updates the
combinations**.

Result: when a margin or promotion is active, the price on a Fare Options card (`combined_display_money`)
differs from the side-sheet **Total price** (`grandTotalDisplayMoney()`, which uses
`selling_display_money`). The combination order can also be wrong. The screenshots match only
because no margin applied to this route.

---

## Fix

Rule: **for any leg that has `fare_combinations`, every price, label, sort and default selection comes
from one helper that works on whole pairs, using selling money.** Legs without combinations (one-way,
other providers) keep the current per-leg logic.

### Step 1 — Backend: build combinations after commercial pricing

In `app/Services/CommercialPricingService.php`, at the end of `applyToAtFlights()` (after each fare
has `selling_display_money`), rebuild the combined money for every itinerary that has
`leg.fare_combinations`:

```php
// After the fare loop for each itinerary:
if (!empty($itinerary['leg']['fare_combinations'])) {
    $itinerary['leg']['fare_combinations'] = $this->refreshCombinationMoney(
        $itinerary['leg']['fare_combinations'],
        $itinerary['leg']['flights'],
    );
}
```

```php
/** Recalculate combined selling money from the two priced leg fares and re-sort by it. */
private function refreshCombinationMoney(array $combinations, array $flights): array
{
    $faresByRef = collect($flights)
        ->flatMap(fn (array $flight) => $flight['fares'] ?? [])
        ->keyBy('ref_id');

    return collect($combinations)
        ->map(function (array $combination) use ($faresByRef) {
            [$onwardRef, $returnRef] = $combination['fare_references'];
            $onward = $faresByRef->get($onwardRef);
            $return = $faresByRef->get($returnRef);
            $onwardMoney = $onward['selling_display_money'] ?? $onward['display_money'] ?? null;
            $returnMoney = $return['selling_display_money'] ?? $return['display_money'] ?? null;

            if ($onwardMoney && $returnMoney && $onwardMoney['currency'] === $returnMoney['currency']) {
                $combination['combined_display_money'] = [
                    'currency' => $onwardMoney['currency'],
                    'amount' => bcadd((string) $onwardMoney['amount'], (string) $returnMoney['amount'], 8),
                ];
            }

            return $combination;
        })
        ->sortBy(fn (array $c) => (float) ($c['combined_display_money']['amount'] ?? $c['combined_amount']))
        ->values()
        ->all();
}
```

After this change `fare_combinations[0]` is always the cheapest pair at the price the customer pays,
and `combined_display_money` equals the side-sheet total for that pair.

> If the cached/streamed search results are written before `applyToAtFlights()` runs, apply the same
> refresh wherever `selling_display_money` is assigned, so cache and live responses agree.

### Step 2 — Frontend: one shared "cheapest pair" helper

In [resources/js/lib/atFareCombinations.js](resources/js/lib/atFareCombinations.js):

```js
/** Selling amount of one combination; falls back to its two leg fares. */
export function combinationAmount(combination, leg, fareAmount = defaultFareAmount) {
    const money = Number(combination?.combined_display_money?.amount);
    if (Number.isFinite(money)) return money;

    const fares = (leg?.flights ?? []).flatMap((flight) => flight?.fares ?? []);
    return (combination?.fare_references ?? []).reduce((sum, ref) => {
        const fare = fares.find((candidate) => candidate.ref_id === ref);
        return sum + (fare ? fareAmount(fare) : 0);
    }, 0);
}

/** Sellable round-trip cards, cheapest first. */
export function combinationFareCards(leg, fareAmount = defaultFareAmount) {
    if (!hasFareCombinations(leg)) return [];

    return [...leg.fare_combinations].sort(
        (a, b) => combinationAmount(a, leg, fareAmount) - combinationAmount(b, leg, fareAmount),
    );
}

/** The single pair every card price, label, sort and default selection must use. */
export function cheapestCombination(leg, fareAmount = defaultFareAmount) {
    return combinationFareCards(leg, fareAmount)[0] ?? null;
}
```

This also fixes `combinationFareCards` ignoring its sort argument.

### Step 3 — Frontend: card price uses the cheapest pair

In `FlightSearch.vue`, start `flightDisplayMoney()` with a combination branch:

```js
function flightDisplayMoney(flight) {
    const leg = flight?.leg;

    if (hasFareCombinations(leg)) {
        const pair = cheapestCombination(leg, fareSortAmount);
        if (!pair) return null;

        const money = pair.combined_display_money;
        if (money?.currency && Number.isFinite(Number(money.amount))) {
            return { amount: Number(money.amount), currency: money.currency };
        }

        return null; // fall through to calculateTotalFare, which handles pairs too (below)
    }

    // ...existing per-leg logic unchanged for non-combination results
}
```

And in `calculateTotalFare()`, for combination legs, add **only the two fares in the cheapest pair**
instead of the per-leg minimum:

```js
function calculateTotalFare(item) {
    const leg = item?.leg;
    const pair = hasFareCombinations(leg) ? cheapestCombination(leg, fareSortAmount) : null;

    if (pair) {
        const fares = leg.flights.flatMap((flight) => flight?.fares ?? []);
        return pair.fare_references.reduce((total, ref) => {
            const fare = fares.find((candidate) => candidate.ref_id === ref);
            return total + (fare ? calculateFare(fare) : 0);
        }, 0);
    }

    // ...existing logic unchanged
}
```

Also guard the existing `leg?.fares?.reduce(...)` with an initial value (`null`) so that a leg with an
empty `fares` array does not throw.

`displayFlightPrice()`, `cheapestFlightsByAirline`, sorting, the price filter and the slider limits
all call `flightDisplayMoney` / `calculateTotalFare`, so they become correct without further changes.

### Step 4 — Frontend: badge and default selection use the same pair

```js
function combinedFareLabel(flight) {
    const pair = hasFareCombinations(flight?.leg)
        ? cheapestCombination(flight.leg, fareSortAmount)
        : null;

    return pair?.name_class ?? null; // backend already builds "Lite + Lite"
}
```

`initializeSelectedFares()` already uses `combinationFareCards(...)[0]`; after Step 2 that is the same
pair. Swap it to `cheapestCombination(flight.leg, fareSortAmount)` for clarity.

### Step 5 — Apply the same changes to the agent page

[resources/js/pages/agent/Flights.vue](resources/js/pages/agent/Flights.vue) has copies of the same
functions (`calculateTotalFare` L1352, `initializeSelectedFares` L1493, `flightDisplayMoney` L1549,
`combinedFareLabel` L1583). Apply Steps 3–4 there as well. Better: move `flightDisplayMoney`,
`calculateTotalFare`'s pair branch and `combinedFareLabel` into `atFareCombinations.js` (or a shared
composable) so the two pages cannot drift apart again.

### Step 6 — Side-sheet Fare Options price

`pairedReturnFareMoney()` returns `normalizedFareMoney(fare)` for combination cards, which reads
`combined_display_money`. After Step 1 that value is selling money, so the option price equals the
**Total price** strip. No frontend change is needed beyond Step 1. If you skip Step 1, change
`normalizedFareMoney` to sum the two referenced fares' `selling_display_money` instead.

---

## Expected result

For every round-trip AT result:

```
card price
  == airline-strip price (if it is that airline's cheapest)
  == first (pre-selected) option in Fare Options
  == side-sheet Total price when the sheet opens
  == price sent to checkout (fare_references of that pair)
```

and the card badge (`Lite + Lite`) names that same pair.

## Test checklist

- [ ] DXB ⇄ LYP round trip (screenshot case): card shows AED 1,760.00, not 875.00.
- [ ] A result where AT puts the full price on one leg and `0` on the other: card shows the full pair
      price, never just the non-zero leg.
- [ ] Add a segment margin/promotion for the route: card, Fare Options card and Total price all
      include it and match.
- [ ] Price sort low→high and the price filter place the flight by its pair total.
- [ ] Airline strip shows the pair total.
- [ ] One-way searches and non-AT providers: prices unchanged.
- [ ] Customer page (`FlightSearch.vue`) and agent page (`agent/Flights.vue`) behave the same.
- [ ] Choosing another fare option then Book Now sends the two `fare_references` of that option.
