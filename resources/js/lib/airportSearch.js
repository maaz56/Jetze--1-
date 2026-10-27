import CountryList from "@/components/common/CountryList.json";

const FLAG_URL = (code) => `https://flagpedia.net/data/flags/icon/72x54/${code}.png`;
const MAX_RESULTS = 100;

// Common short names people type instead of the official country name.
const COUNTRY_ALIASES = {
    AE: ["uae", "emirates"],
    GB: ["uk", "britain", "great britain", "england"],
    US: ["usa", "america", "united states of america"],
    SA: ["ksa", "saudia", "saudi"],
    KR: ["korea"],
    RU: ["russian federation"],
};

// The airports table has no traffic data, so busy hubs are listed here to show first
// when a whole country is listed. Order within this list is the display order.
const MAJOR_AIRPORTS = [
    "ISB", "LHE", "KHI", "PEW", "MUX", "SKT", "LYP", "UET", "MDX",
    "DXB", "AUH", "SHJ", "DWC", "RKT",
    "JED", "RUH", "DMM", "MED",
    "DOH", "BAH", "MCT", "KWI",
    "DEL", "BOM", "BLR", "MAA", "HYD", "CCU", "COK",
    "IST", "SAW", "AYT", "ESB",
    "LHR", "LGW", "MAN", "STN", "LTN", "BHX", "EDI", "GLA",
    "JFK", "EWR", "LAX", "ORD", "IAD", "SFO", "ATL", "DFW", "MIA", "IAH",
    "YYZ", "YVR", "YUL", "CDG", "ORY", "FRA", "MUC", "AMS", "MAD", "BCN", "FCO", "MXP",
    "CAI", "BKK", "KUL", "SIN", "CGK", "MNL", "HKG", "PEK", "PVG", "NRT", "HND", "ICN",
    "SYD", "MEL", "DAC", "CMB", "KBL", "THR", "BAK", "TAS", "NBO", "ADD", "JNB",
];
const majorRank = new Map(MAJOR_AIRPORTS.map((code, index) => [code, index]));

const regionNames =
    typeof Intl !== "undefined" && Intl.DisplayNames
        ? new Intl.DisplayNames(["en"], { type: "region" })
        : null;

const countryListByCode = new Map(
    CountryList.map((country) => [String(country.code).toUpperCase(), country]),
);

/** Lowercase, strip accents and punctuation so "Zürich" matches "zurich". */
export const normalize = (value) =>
    String(value ?? "")
        .normalize("NFD")
        .replace(/[̀-ͯ]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, " ")
        .trim();

const countryCache = new Map();

export function getCountry(code) {
    const upper = String(code || "").toUpperCase();
    if (!upper) return null;
    if (countryCache.has(upper)) return countryCache.get(upper);

    const listed = countryListByCode.get(upper);
    let name = listed?.name;
    if (!name) {
        try {
            name = regionNames?.of(upper);
        } catch {
            name = null;
        }
    }

    const country = {
        code: upper,
        name: name || upper,
        flag: listed?.flag || FLAG_URL(upper.toLowerCase()),
        terms: [name, ...(COUNTRY_ALIASES[upper] ?? [])].filter(Boolean).map(normalize),
    };
    countryCache.set(upper, country);
    return country;
}

export const getAirportCountry = (airport) => getCountry(airport?.iata_country_code);

/** Optimal string alignment distance (Levenshtein plus adjacent swaps). */
function editDistance(a, b) {
    const rows = a.length + 1;
    const cols = b.length + 1;
    const d = Array.from({ length: rows }, (_, i) => [i, ...new Array(cols - 1).fill(0)]);
    for (let j = 1; j < cols; j++) d[0][j] = j;

    for (let i = 1; i < rows; i++) {
        for (let j = 1; j < cols; j++) {
            const cost = a[i - 1] === b[j - 1] ? 0 : 1;
            d[i][j] = Math.min(d[i - 1][j] + 1, d[i][j - 1] + 1, d[i - 1][j - 1] + cost);
            if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                d[i][j] = Math.min(d[i][j], d[i - 2][j - 2] + 1);
            }
        }
    }
    return d[a.length][b.length];
}

/** Distance from the query to the start of a term, so half-typed words still match. */
function prefixDistance(query, term) {
    let best = Infinity;
    for (let length = query.length - 1; length <= query.length + 1; length++) {
        if (length < 1 || length > term.length) continue;
        best = Math.min(best, editDistance(query, term.slice(0, length)));
    }
    return best;
}

const allowedTypos = (length) => (length < 4 ? 0 : length <= 5 ? 1 : length <= 8 ? 2 : 3);

const startsWithWord = (text, query) => text.startsWith(query) || text.includes(` ${query}`);

/** Major hubs first, then "International" airports, then alphabetical by city. */
const byImportance = (a, b) =>
    (majorRank.get(a.iata_code) ?? Infinity) - (majorRank.get(b.iata_code) ?? Infinity) ||
    Number(/international/i.test(b.name || "")) - Number(/international/i.test(a.name || "")) ||
    String(a.city_name || "").localeCompare(String(b.city_name || ""));

/**
 * Search airports by IATA code, city, airport name or country.
 * A country name (or a misspelling of one) returns every airport in that country.
 *
 * @returns {{ results: object[], country: object|null, correctedTo: string|null }}
 */
export function searchAirports(source, rawQuery) {
    const empty = { results: [], country: null, correctedTo: null };
    const query = normalize(rawQuery);
    if (!query || !Array.isArray(source)) return empty;

    const countryCodes = new Set(
        source.map((airport) => String(airport.iata_country_code || "").toUpperCase()).filter(Boolean),
    );
    const countries = [...countryCodes].map(getCountry);
    const exactCountry = countries.find((c) => c.terms.includes(query));

    const exactIata = source.find((airport) => normalize(airport.iata_code) === query);
    if (exactIata && !exactCountry) return { results: [exactIata], country: null, correctedTo: null };

    const ranked = [];

    for (const airport of source) {
        const city = normalize(airport.city_name);
        const name = normalize(airport.name);
        let rank = null;

        if (normalize(airport.iata_city_code) === query) rank = 0;
        else if (city.startsWith(query)) rank = 1;
        else if (startsWithWord(city, query) || startsWithWord(name, query)) rank = 2;
        else if (query.length >= 3 && (city.includes(query) || name.includes(query))) rank = 3;

        if (rank !== null) ranked.push({ airport, rank });
    }

    let country =
        exactCountry ??
        (query.length >= 3 ? countries.find((c) => c.terms.some((term) => startsWithWord(term, query))) : null);
    let correctedTo = null;

    // Nothing typed matches directly: forgive spelling mistakes in country and city names.
    const typos = allowedTypos(query.length);
    if (!country && !ranked.length && typos) {
        let bestDistance = Infinity;
        for (const candidate of countries) {
            for (const term of candidate.terms) {
                const distance = prefixDistance(query, term);
                if (distance <= typos && distance < bestDistance) {
                    bestDistance = distance;
                    country = candidate;
                }
            }
        }
        if (country) correctedTo = country.name;

        if (!country) {
            const close = source
                .map((airport) => ({ airport, distance: prefixDistance(query, normalize(airport.city_name)) }))
                .filter((entry) => entry.distance <= typos);
            const bestDistance = Math.min(...close.map((entry) => entry.distance));
            const closest = close.filter((entry) => entry.distance === bestDistance);

            ranked.push(...closest.map((entry) => ({ airport: entry.airport, rank: 4 })));
            correctedTo = closest[0]?.airport.city_name ?? null;
        }
    }

    ranked.sort((a, b) => a.rank - b.rank || byImportance(a.airport, b.airport));
    let results = ranked.map((entry) => entry.airport);

    if (country) {
        const countryAirports = source
            .filter((airport) => String(airport.iata_country_code).toUpperCase() === country.code)
            .sort(byImportance);
        const inCountry = new Set(countryAirports);
        const others = results.filter((airport) => !inCountry.has(airport));
        // A complete country name lists that country first; a partial one keeps city matches on top.
        results = exactCountry ? [...countryAirports, ...others] : [...others, ...countryAirports];
    }

    return { results: results.slice(0, MAX_RESULTS), country: country ?? null, correctedTo };
}
