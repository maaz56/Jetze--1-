// AT return results repeat the same fare once per ReturnIdentifier. Each
// identifier links one onward fare to one valid return fare, so fares are kept
// and grouped by fare_key for display, then paired again by identifier.

const defaultFareAmount = (fare) => Number(fare?.billable_price) || 0;

export function hasFareCombinations(leg) {
    const flights = leg?.flights ?? [];

    return (
        leg?.trip_nature === "return" &&
        flights.length === 2 &&
        flights.every((flight) =>
            (flight?.fares ?? []).every(
                (fare) => fare?.fare_key && fare?.return_identifier != null,
            ),
        )
    );
}

/** Show each fare once, preferring the copy that is currently selected. */
function uniqueByFareKey(fares, selectedRefId) {
    const faresByKey = new Map();

    fares.forEach((fare) => {
        if (!faresByKey.has(fare.fare_key) || fare.ref_id === selectedRefId) {
            faresByKey.set(fare.fare_key, fare);
        }
    });

    return [...faresByKey.values()];
}

/** Fare options for one leg; return fares are limited to the selected onward fare. */
export function combinationFareOptions(leg, flightIndex, fares, selectedFares) {
    if (flightIndex === 0) {
        return uniqueByFareKey(fares, selectedFares[0]);
    }

    const onwardFares = leg?.flights?.[0]?.fares ?? [];
    const selectedOnward = onwardFares.find(
        (fare) => fare.ref_id === selectedFares[0],
    );

    if (!selectedOnward) {
        return uniqueByFareKey(fares, selectedFares[flightIndex]);
    }

    const identifiers = new Set(
        onwardFares
            .filter((fare) => fare.fare_key === selectedOnward.fare_key)
            .map((fare) => String(fare.return_identifier)),
    );

    return uniqueByFareKey(
        fares.filter((fare) => identifiers.has(String(fare.return_identifier))),
        selectedFares[flightIndex],
    );
}

/**
 * Select a fare and keep the other leg on a valid pairing. The chosen return
 * fare is kept when it pairs with the onward fare, otherwise the cheapest
 * valid return fare is selected.
 */
export function selectFareCombination(
    leg,
    selectedFares,
    flightIndex,
    refId,
    fareAmount = defaultFareAmount,
) {
    const [onwardFlight, returnFlight] = leg?.flights ?? [];
    const fareKeyOf = (flight, ref) =>
        (flight?.fares ?? []).find((fare) => fare.ref_id === ref)?.fare_key;

    const onwardKey = fareKeyOf(onwardFlight, flightIndex === 0 ? refId : selectedFares[0]);
    const returnKey = fareKeyOf(returnFlight, flightIndex === 1 ? refId : selectedFares[1]);

    const returnFaresById = new Map(
        (returnFlight?.fares ?? []).map((fare) => [String(fare.return_identifier), fare]),
    );
    const pairs = (onwardFlight?.fares ?? [])
        .filter((fare) => fare.fare_key === onwardKey)
        .map((onward) => ({
            onward,
            return: returnFaresById.get(String(onward.return_identifier)),
        }))
        .filter((pair) => pair.return);

    const pair =
        pairs.find((candidate) => candidate.return.fare_key === returnKey) ??
        pairs.sort((first, second) => fareAmount(first.return) - fareAmount(second.return))[0];

    if (!pair) {
        selectedFares[flightIndex] = refId;
        return;
    }

    selectedFares[0] = pair.onward.ref_id;
    selectedFares[1] = pair.return.ref_id;
}
