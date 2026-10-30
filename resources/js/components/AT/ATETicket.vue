<script setup>
import { computed } from "vue";
import moment from "moment";

// Shared AT e-ticket layout used by the public, admin and agent booking pages.
// Pages keep their own fare calculations and pass them through the fare slots.
const props = defineProps({
    booking: { type: Object, required: true },
    agent: { type: Object, default: () => ({}) },
    logo: { type: String, default: "" },
    pnr: { type: String, default: "-" },
    airlinePnr: { type: String, default: "-" },
    pnrDetails: { type: Object, default: null },
    expiry: { type: String, default: "" },
    source: { type: String, default: "" },
    showFare: { type: Boolean, default: true },
    fareColumns: { type: Array, default: () => ["Sector", "Subtotal", "Taxes + Fees", "Grand total"] },
});

const parseJson = (value) => {
    if (value && typeof value === "object") return value;
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
};

const flightData = computed(() => parseJson(props.booking?.flight_data));

const flights = computed(() =>
    flightData.value?.original?.leg?.flights ?? flightData.value?.leg?.flights ?? []
);

const fareReferences = computed(() => {
    const refs = parseJson(props.booking?.fare_reference) ?? props.booking?.fare_reference;
    if (!refs) return [];
    return Array.isArray(refs) ? refs : [refs];
});

// Longer itineraries switch print to a tighter layout so they still fit one A4 page.
const isDense = computed(() => {
    const segments = flights.value.reduce((total, flight) => total + (flight?.segments?.length || 0), 0);
    return segments > 2 || (props.booking?.pessangers?.length || 0) > 2;
});

const companyName = computed(() => props.agent?.company_name || "Jetze Travels");
const logoSrc = computed(() => props.logo || props.agent?.logo || "/public/assets/logo.png");

const status = computed(() => (props.booking?.status || "").toLowerCase());
const statusTone = computed(() => {
    if (["ticketed", "confirmed", "issued", "booked"].includes(status.value)) return "ok";
    if (["pending", "requested", "hold"].includes(status.value)) return "warn";
    if (["cancelled", "canceled", "failed", "voided", "expired"].includes(status.value)) return "bad";
    return "info";
});
const statusLabel = computed(() =>
    status.value ? status.value.charAt(0).toUpperCase() + status.value.slice(1) : "N/A"
);

const bookingDate = computed(() =>
    props.booking?.created_at ? moment(props.booking.created_at).format("DD MMMM YYYY") : "-"
);

const formatMinutes = (minutes) => {
    const value = Number(minutes);
    if (!Number.isFinite(value) || value <= 0) return "";
    return `${Math.floor(value / 60)}h ${value % 60}m`;
};

const flightLabel = (index) => {
    const list = flights.value;
    if (list.length === 1) return "Onward";
    if (list.length === 2 && list[1]?.to?.iata === list[0]?.from?.iata) {
        return index === 0 ? "Onward" : "Return";
    }
    return `Flight ${index + 1}`;
};

const stopsLabel = (flight) => {
    const stops = Math.max((flight?.segments?.length || 1) - 1, 0);
    if (stops === 0) return "Non-stop";
    return stops === 1 ? "1 stop" : `${stops} stops`;
};

// Fall back to the gap between segments when the provider sends no layover_time.
const layoverDuration = (flight, index) => {
    const segment = flight?.segments?.[index];
    const stored = formatMinutes(segment?.layover_time);
    if (stored) return stored;

    const previous = flight?.segments?.[index - 1];
    if (!previous?.arrival_at || !segment?.departure_at) return "";
    return formatMinutes(moment.parseZone(segment.departure_at).diff(moment.parseZone(previous.arrival_at), "minutes"));
};

const flightDuration = (flight) =>
    formatMinutes(flight?.travel_time) || flight?.segments?.[0]?.flight_time || "";

const cabinLabel = (segment) => {
    const cabin = String(segment?.cabin_class || "E").toUpperCase();
    if (["B", "C", "J", "BUSINESS"].includes(cabin)) return "Business";
    if (["F", "FIRST"].includes(cabin)) return "First";
    if (["P", "W", "PREMIUM"].includes(cabin)) return "Premium Economy";
    return "Economy";
};

// Live AT RetrieveBooking response; the PNR endpoint wraps it as { bookingDetails, itineraryStatus }.
const atBooking = computed(() =>
    props.pnrDetails?.bookingDetails ?? (props.pnrDetails?.Trips ? props.pnrDetails : null)
);

const atSegments = computed(() =>
    (atBooking.value?.Trips || [])
        .flatMap((trip) => trip?.Journey || [])
        .flatMap((journey) => journey?.Segments || [])
        .map((segment) => segment?.Flight)
        .filter(Boolean)
);

const crsPnr = computed(() => {
    if (props.pnr && props.pnr !== "-") return props.pnr;
    return atSegments.value.find((flight) => flight?.CRSPNR)?.CRSPNR || "-";
});

const airlinePnrValue = computed(() => {
    if (props.airlinePnr && props.airlinePnr !== "-") return props.airlinePnr;
    const flight = atSegments.value.find((item) => item?.APNR || item?.AirlinePNR);
    return flight?.APNR || flight?.AirlinePNR || "-";
});

const atSegmentFor = (segment) =>
    atSegments.value.find(
        (flight) => flight?.DepartureCode === segment?.from?.iata && flight?.ArrivalCode === segment?.to?.iata
    );

// AT sometimes sends the flight duration (e.g. "02h 20m") in the aircraft field,
// so fall back to the aircraft returned by RetrieveBooking.
const aircraftName = (segment) => {
    const name = segment?.aircraft?.model ?? (typeof segment?.aircraft === "string" ? segment.aircraft : "");
    if (name && !/^\s*\d+\s*h\s*\d+\s*m\s*$/i.test(name)) return name;

    const atFlight = atSegmentFor(segment);
    return [atFlight?.AirCraft, atFlight?.EquipmentType].filter(Boolean).join(" ");
};

const place = (point) =>
    point?.iata ? `${point?.city?.name || point?.name || point.iata} (${point.iata})` : "-";

const terminal = (value) => value?.Gate ?? value?.gate ?? null;
const departureTerminal = (segment) => terminal(segment?.from_terminal) || atSegmentFor(segment)?.DepartureTerminal || null;
const arrivalTerminal = (segment) => terminal(segment?.to_terminal) || atSegmentFor(segment)?.ArrivalTerminal || null;

const fmtDate = (value) => (value ? moment.parseZone(value).format("ddd, DD MMM YY") : "-");
const fmtTime = (value) => (value ? moment.parseZone(value).format("HH:mm") : "-");
const fmtShortDate = (value) => (value ? moment.parseZone(value).format("DD MMM YYYY") : "");

const isCarry = (policy) => ["carry", "carry-on", "cabin"].includes(policy?.type);
const isChecked = (policy) => ["checked", "checkin", "check-in"].includes(String(policy?.type).toLowerCase());

const typeLabel = (type) => {
    const value = String(type || "").toUpperCase();
    if (["ADT", "ADULT"].includes(value)) return "Adult";
    if (["CHD", "CNN", "CHILD"].includes(value)) return "Child";
    if (["INF", "INFANT"].includes(value)) return "Infant";
    return type || "Passenger";
};

// AT marks a missing checked allowance with allowance_known: false and a placeholder description.
const isUnknownAllowance = (policy) => policy?.allowance_known === false;

const allowance = (policy) => {
    if (!policy) return "N/A";
    if (isUnknownAllowance(policy)) return "Not specified";
    const weight = Number(policy.weight);
    if (Number.isFinite(weight) && weight > 0) return `${weight} kg`;
    if (Number(policy.pieces) > 0) return `${policy.pieces} piece${Number(policy.pieces) > 1 ? "s" : ""}`;
    return policy.description || "N/A";
};

const policyRow = (type, policy) => ({
    type: typeLabel(type),
    allowance: allowance(policy),
    note: isUnknownAllowance(policy) ? "As per airline policy" : policy?.description || "N/A",
});

// AT BAG SSR descriptions read "<checked>,<cabin>", e.g. "15Kg,07 Kg".
const ssrWeight = (value) => {
    const text = String(value || "").trim();
    const kg = text.match(/^0*(\d+(?:\.\d+)?)\s*kg$/i);
    return kg ? `${kg[1]} kg` : text;
};

// Baggage is the same for every segment of a flight, so show one block per flight.
// The booked BAG SSR from RetrieveBooking wins over the search-time fare policy.
const baggage = computed(() =>
    flights.value.map((flight) => {
        const fares = (flight?.fares || []).filter((fare) => fareReferences.value.includes(fare?.ref_id));
        const policies = fares.flatMap((fare) => fare?.baggage_policies || []);

        const fuids = (flight?.segments || [])
            .map((segment) => atSegmentFor(segment)?.FUID)
            .filter((fuid) => fuid !== undefined && fuid !== null)
            .map(String);
        const ssrBags = (atBooking.value?.SSR || []).filter(
            (ssr) => ssr?.Code === "BAG" && fuids.includes(String(ssr?.FUID)) && ssr?.Description
        );

        const types = [...new Set([
            ...ssrBags.map((ssr) => ssr?.PTC),
            ...policies.map((policy) => policy?.traveler_type),
        ])].filter(Boolean);

        const rows = types.map((type) => {
            const ssr = ssrBags.find((item) => item?.PTC === type);
            const [checked, cabin] = String(ssr?.Description || "").split(",");
            const carryPolicy = policies.find((policy) => policy?.traveler_type === type && isCarry(policy));
            const checkedPolicy = policies.find((policy) => policy?.traveler_type === type && isChecked(policy));

            return {
                carry: cabin?.trim()
                    ? { type: typeLabel(type), allowance: ssrWeight(cabin), note: `${ssrWeight(cabin)} cabin baggage` }
                    : policyRow(type, carryPolicy),
                checked: checked?.trim()
                    ? { type: typeLabel(type), allowance: ssrWeight(checked), note: `${ssrWeight(checked)} checked baggage` }
                    : policyRow(type, checkedPolicy),
            };
        });

        return {
            route: `${flight?.from?.iata || "-"} → ${flight?.to?.iata || "-"}`,
            carry: rows.map((row) => row.carry),
            checked: rows.map((row) => row.checked),
        };
    })
);

// AT keeps ticket numbers per segment in Flight.TicketInfo, keyed by Pax[].ID (not Pax[].PaxID).
const atTicketNumber = (traveller, index) => {
    const paxList = atBooking.value?.Pax || [];
    const firstName = String(traveller?.first_name || "").trim().toUpperCase();
    const pax = paxList.find((item) => String(item?.FName || "").trim().toUpperCase() === firstName) ?? paxList[index];
    if (!pax) return "";

    const numbers = atSegments.value
        .flatMap((flight) => flight?.TicketInfo || [])
        .filter((info) => String(info?.PaxID) === String(pax?.ID) && info?.TicketNo)
        .map((info) => String(info.TicketNo).trim());
    return [...new Set(numbers)].join(", ");
};

const ticketNumber = (traveller, index) =>
    props.pnrDetails?.flightTickets?.[index]?.number ||
    props.pnrDetails?.booking_details?.[index]?.ticket_no ||
    atTicketNumber(traveller, index) ||
    "N/A";

const genderLabel = (gender) => {
    const value = String(gender || "").trim().toUpperCase();
    if (["M", "MALE"].includes(value)) return "Male";
    if (["F", "FEMALE"].includes(value)) return "Female";
    return value ? value.charAt(0) + value.slice(1).toLowerCase() : "N/A";
};

// Stored as a country code ("pk") for AT bookings; keep full names as entered.
const nationalityLabel = (nationality) => {
    const value = String(nationality || "").trim();
    if (!value) return "N/A";
    return value.length <= 3 ? value.toUpperCase() : value;
};

const passengerName = (traveller) =>
    [traveller?.title, traveller?.first_name, traveller?.last_name].filter(Boolean).join(" ").toUpperCase();
</script>

<template>
    <main class="et-ticket" :class="{ 'et-ticket--dense': isDense }">
        <header class="et-head">
            <div class="et-brand">
                <img :src="logoSrc" :alt="companyName" />
                <div>
                    <h1>{{ companyName.toUpperCase() }}</h1>
                    <p>Your Journey, Our Priority</p>
                </div>
            </div>
            <div class="et-contact">
                <template v-if="agent?.address">{{ agent.address }}<br></template>
                <span v-if="agent?.mobile">{{ agent.mobile }}</span>
                <template v-if="agent?.mobile && agent?.company_email">&nbsp;|&nbsp;</template>
                <span v-if="agent?.company_email">{{ agent.company_email }}</span>
            </div>
            <!-- Print-only agency details, laid out as key : value rows. -->
            <dl class="et-contact-print">
                <dt>Name</dt><dd>{{ companyName }}</dd>
                <template v-if="agent?.address"><dt>Address</dt><dd>{{ agent.address }}</dd></template>
                <template v-if="agent?.mobile"><dt>Tel</dt><dd>{{ agent.mobile }}</dd></template>
                <template v-if="agent?.company_email"><dt>Email</dt><dd>{{ agent.company_email }}</dd></template>
            </dl>
        </header>

        <div class="et-title">
            <div>
                <h2>E-Ticket</h2>
                <p>Flight Reservation &amp; Travel Itinerary</p>
            </div>
            <div class="et-title-status">
                <span class="et-badge" :class="`et-badge--${statusTone}`">
                    <template v-if="statusTone === 'ok'">✓ </template>{{ statusLabel }}
                </span>
                <span v-if="expiry" class="et-expiry">Expires in {{ expiry }}</span>
            </div>
        </div>

        <div class="et-meta">
            <div><span class="et-lbl">Reference No.</span><span class="et-val">{{ booking?.id }}</span></div>
            <div><span class="et-lbl">Date of booking</span><span class="et-val">{{ bookingDate }}</span></div>
            <div><span class="et-lbl">PNR</span><span class="et-val">{{ crsPnr }}</span></div>
            <div><span class="et-lbl">Airline PNR</span><span class="et-val">{{ airlinePnrValue }}</span></div>
            <div v-if="source"><span class="et-lbl">Booking source</span><span class="et-val">{{ source }}</span></div>
        </div>

        <slot name="flights">
            <section v-for="(flight, flightIndex) in flights" :key="flightIndex" class="et-sec">
                <div class="et-route">
                    <strong>{{ flightLabel(flightIndex) }} · {{ flight?.from?.city?.name || flight?.from?.iata }} → {{
                        flight?.to?.city?.name || flight?.to?.iata }}</strong>
                    <span>
                        {{ fmtShortDate(flight?.departure_at || flight?.segments?.[0]?.departure_at) }}
                        &nbsp;•&nbsp; {{ stopsLabel(flight) }}
                        <template v-if="flightDuration(flight)">&nbsp;•&nbsp; {{ flightDuration(flight) }}</template>
                        &nbsp;•&nbsp; {{ cabinLabel(flight?.segments?.[0]) }}
                    </span>
                </div>
                <template v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex">
                    <div v-if="segmentIndex > 0 || segment?.layover_time" class="et-layover">
                        Layover at {{ segment?.from?.city?.name || segment?.from?.iata }}
                        <template v-if="layoverDuration(flight, segmentIndex)">· {{ layoverDuration(flight, segmentIndex)
                            }}</template>
                    </div>
                    <div class="et-flight">
                        <div>
                            <span class="et-lbl">Flight</span>
                            <span class="et-val">{{ segment?.operating_carrier?.iata }} {{ segment?.flight_number ?? "N/A"
                                }}</span>
                            <div class="et-sub">{{ segment?.operating_carrier?.name }}</div>
                            <div v-if="aircraftName(segment)" class="et-sub">{{ aircraftName(segment) }}</div>
                        </div>
                        <div>
                            <span class="et-lbl">From</span>
                            <span class="et-val">{{ place(segment?.from) }}</span>
                            <div class="et-sub">
                                {{ segment?.from?.name }}<template v-if="departureTerminal(segment)"> · Terminal {{
                                    departureTerminal(segment) }}</template>
                            </div>
                        </div>
                        <div>
                            <span class="et-lbl">Departure</span>
                            <span class="et-val">{{ fmtDate(segment?.departure_at) }}</span>
                            <div class="et-val et-time">{{ fmtTime(segment?.departure_at) }}</div>
                        </div>
                        <div>
                            <span class="et-lbl">To</span>
                            <span class="et-val">{{ place(segment?.to) }}</span>
                            <div class="et-sub">
                                {{ segment?.to?.name }}<template v-if="arrivalTerminal(segment)"> · Terminal {{
                                    arrivalTerminal(segment) }}</template>
                            </div>
                        </div>
                        <div>
                            <span class="et-lbl">Arrival</span>
                            <span class="et-val">{{ fmtDate(segment?.arrival_at) }}</span>
                            <div class="et-val et-time">{{ fmtTime(segment?.arrival_at) }}</div>
                        </div>
                    </div>
                </template>
            </section>
        </slot>

        <section class="et-sec">
            <div class="et-sec-h">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7z" />
                </svg>
                Passenger information
            </div>
            <div v-for="(traveller, index) in booking?.pessangers" :key="index" class="et-pax">
                <div class="et-wide">
                    <span class="et-lbl">Name</span>
                    <span class="et-val">{{ passengerName(traveller) }}
                        <small v-if="traveller?.type">({{ traveller.type }})</small></span>
                </div>
                <div><span class="et-lbl">Gender</span><span class="et-val">{{ genderLabel(traveller?.gender) }}</span></div>
                <div><span class="et-lbl">Nationality</span><span class="et-val">{{ nationalityLabel(traveller?.nationality)
                        }}</span></div>
                <div><span class="et-lbl">Ticket No</span><span class="et-val">{{ ticketNumber(traveller, index) }}</span></div>
            </div>
        </section>

        <slot name="extras" />

        <section v-if="showFare" class="et-sec">
            <div class="et-sec-h">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 4h16a1 1 0 0 1 1 1v14l-3-2-3 2-3-2-3 2-3-2-2 2V5a1 1 0 0 1 1-1zm3 4v2h10V8zm0 4v2h7v-2z" />
                </svg>
                Fare breakdown
            </div>
            <div class="et-scroll">
                <table class="et-fare">
                    <thead>
                        <tr>
                            <th v-for="column in fareColumns" :key="column">{{ column }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <slot name="fare-rows" />
                    </tbody>
                </table>
            </div>
            <div class="et-fare-sum">
                <slot name="fare-summary" />
            </div>
        </section>

        <section class="et-sec">
            <div class="et-sec-h">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path
                        d="M8 3h8v3h3a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h3zm2 1.5V6h4V4.5z" />
                </svg>
                Baggage allowance
            </div>
            <slot name="baggage">
                <div v-for="(item, index) in baggage" :key="index" class="et-bag-group">
                    <div v-if="baggage.length > 1" class="et-bag-route">{{ item.route }}</div>
                    <div class="et-bag">
                        <div>
                            <h4>Carry-on</h4>
                            <div v-for="row in item.carry" :key="`carry-${row.type}`" class="et-kg">
                                {{ row.type }} – {{ row.allowance }}
                            </div>
                            <ul>
                                <li v-for="row in item.carry" :key="`carry-li-${row.type}`">
                                    <b>{{ row.type }}:</b> {{ row.note }}
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4>Checked baggage</h4>
                            <div v-for="row in item.checked" :key="`checked-${row.type}`" class="et-kg">
                                {{ row.type }} – {{ row.allowance }}
                            </div>
                            <ul>
                                <li v-for="row in item.checked" :key="`checked-li-${row.type}`">
                                    <b>{{ row.type }}:</b> {{ row.note }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div v-if="!baggage.length" class="et-empty">Baggage information is not available.</div>
            </slot>
            <div class="et-foot-note">Refer to the airline baggage policy for further details.</div>
        </section>

        <div class="et-note et-note--info">
            <h5>Important note</h5>
            <ul>
                <li>Make sure you have valid travel documents before your trip (e.g. passport, visa, etc.). For any
                    future reference please refer the above Reference No.</li>
                <li>We recommend you check-in at least 3 hours prior to departure of your domestic flight and 4 hours
                    prior to your international flight.</li>
                <li>Baggage more than specified units is subject to a charge to be paid at the airport during
                    check-in.</li>
                <li>We are not responsible for any delay in cancellation of flight from the airline's end.</li>
                <li>Please refer the Airline PNR Number when communicating with the airline regarding this booking.</li>
                <li><b>Disclaimer:</b> Post-ticketing modifications or cancellations will be processed in accordance
                    with the airline's policy.</li>
            </ul>
        </div>

        <!-- A div, not <footer>: the page print styles hide every footer element. -->
        <div class="et-foot">
            <span>Travel far &nbsp;•&nbsp; Explore more &nbsp;•&nbsp; Create memories</span>
            <span>{{ agent?.company_email || "support@Jetze.pk" }}</span>
        </div>
    </main>
</template>

<style>
/* Not scoped: slot content from the pages and the print clone (#print-container) use these classes too. */
.et-ticket {
    /* Mapped to the app palette in resources/css/app.css. The app has no success/warning
       tokens, so those two status tones keep fixed green/amber values. */
    --et-navy: hsl(var(--primary-dark));
    --et-blue: hsl(var(--primary));
    --et-ink: hsl(var(--foreground));
    --et-muted: hsl(var(--muted-foreground));
    --et-line: hsl(var(--border));
    --et-tint: hsl(var(--muted));
    --et-info: hsl(var(--primary) / .1);
    --et-r: var(--radius);
    --et-ok: #15803d;
    --et-okbg: #dcfce7;
    --et-warn: #b45309;
    --et-warnbg: #fef3c7;
    --et-bad: hsl(var(--destructive));
    --et-badbg: hsl(var(--destructive) / .1);
    max-width: 820px;
    margin: 0 auto 16px;
    padding-bottom: 0;
    background: hsl(var(--card));
    color: var(--et-ink);
    font-family: inherit;
    font-size: 13px;
    line-height: 1.45;
    border-radius: var(--et-r);
    box-shadow: 0 6px 28px hsl(var(--primary-dark) / .12);
    overflow: hidden;
    text-align: left;
}

.et-ticket *,
.et-ticket *::before,
.et-ticket *::after {
    box-sizing: border-box;
}

.et-ticket h1,
.et-ticket h2,
.et-ticket h4,
.et-ticket h5,
.et-ticket p,
.et-ticket ul {
    margin: 0;
    padding: 0;
}

.et-head {
    display: flex;
    justify-content: space-between;
    gap: 24px;
    padding: 24px 32px;
    border-bottom: 1px solid var(--et-line);
}

.et-brand {
    display: flex;
    gap: 12px;
    align-items: center;
}

.et-brand img {
    height: 40px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    flex: none;
}

.et-brand h1 {
    font-size: 16px;
    font-weight: 700;
    color: var(--et-navy);
    letter-spacing: .2px;
    line-height: 1.2;
}

.et-brand p {
    font-size: 12px;
    color: var(--et-muted);
    margin-top: 2px;
}

.et-contact-print {
    display: none;
}

.et-contact {
    font-size: 11.5px;
    color: var(--et-muted);
    text-align: right;
    line-height: 1.6;
}

.et-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 32px 6px;
}

.et-title h2 {
    font-size: 22px;
    font-weight: 700;
    color: var(--et-navy);
    letter-spacing: .3px;
}

.et-title p {
    font-size: 12px;
    color: var(--et-muted);
}

.et-title-status {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.et-badge {
    font-weight: 600;
    font-size: 12px;
    padding: 6px 14px;
    border-radius: 3px;
}

.et-badge--ok { background: var(--et-okbg); color: var(--et-ok); }
.et-badge--warn { background: var(--et-warnbg); color: var(--et-warn); }
.et-badge--bad { background: var(--et-badbg); color: var(--et-bad); }
.et-badge--info { background: var(--et-info); color: var(--et-navy); }

.et-text-ok { color: var(--et-ok); }
.et-text-bad { color: var(--et-bad); }

.et-expiry {
    font-size: 11px;
    color: var(--et-warn);
    font-weight: 500;
}

.et-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    margin: 16px 32px 0;
    background: var(--et-tint);
    border: 1px solid var(--et-line);
    border-radius: var(--et-r);
}

.et-meta > div {
    padding: 12px 16px;
}

.et-meta > div + div {
    border-left: 1px solid var(--et-line);
}

.et-lbl {
    display: block;
    font-size: 11px;
    color: var(--et-muted);
    font-weight: 500;
    margin-bottom: 2px;
}

.et-val {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--et-ink);
}

.et-sec {
    margin: 18px 32px 0;
    border: 1px solid var(--et-line);
    border-radius: var(--et-r);
    overflow: hidden;
}

.et-sec-h {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--et-tint);
    border-bottom: 1px solid var(--et-line);
    font-size: 13px;
    font-weight: 600;
    color: var(--et-navy);
}

.et-sec-h svg {
    width: 16px;
    height: 16px;
    color: var(--et-blue);
}

.et-route {
    background: var(--et-blue);
    color: hsl(var(--primary-foreground));
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
}

.et-route strong {
    font-size: 14px;
    font-weight: 600;
}

.et-route span {
    font-size: 12px;
    color: hsl(var(--primary-foreground) / .78);
}

.et-layover {
    padding: 6px 16px;
    text-align: center;
    background: var(--et-info);
    color: var(--et-navy);
    font-size: 11.5px;
    font-weight: 500;
    border-top: 1px solid var(--et-line);
    border-bottom: 1px solid var(--et-line);
}

.et-flight {
    display: grid;
    grid-template-columns: 1fr 1.2fr 1.1fr 1.2fr 1.1fr;
}

.et-flight > div {
    padding: 16px;
}

.et-flight > div + div {
    border-left: 1px solid var(--et-line);
}

.et-flight .et-lbl {
    margin-bottom: 8px;
}

.et-time {
    margin-top: 2px;
}

.et-sub {
    margin-top: 2px;
    font-size: 11.5px;
    color: var(--et-muted);
}

.et-pax {
    display: grid;
    grid-template-columns: 1.9fr .7fr 1fr 1.4fr;
}

.et-pax + .et-pax {
    border-top: 1px solid var(--et-line);
}

.et-pax > div {
    padding: 12px 14px;
}

.et-pax .et-val {
    font-size: 12.5px;
    font-weight: 500;
}

.et-pax .et-val small {
    font-size: 11px;
    color: var(--et-muted);
}

.et-pax .et-lbl {
    font-size: 10.5px;
}

.et-pax > div + div {
    border-left: 1px solid var(--et-line);
}

.et-bag-group + .et-bag-group {
    border-top: 1px solid var(--et-line);
}

.et-bag-route {
    padding: 8px 16px 0;
    font-size: 12px;
    font-weight: 600;
    color: var(--et-navy);
}

.et-bag {
    display: grid;
    grid-template-columns: 1fr 1fr;
}

.et-bag > div {
    padding: 14px 16px;
}

.et-bag > div + div {
    border-left: 1px solid var(--et-line);
}

.et-bag h4 {
    font-size: 12px;
    font-weight: 500;
    color: var(--et-muted);
}

.et-kg {
    font-size: 16px;
    font-weight: 600;
    color: var(--et-navy);
    margin: 2px 0;
}

.et-bag ul {
    list-style: none;
    font-size: 12px;
    color: var(--et-muted);
    margin-top: 6px;
}

.et-bag li {
    padding: 3px 0;
    border-top: 1px dashed var(--et-line);
}

.et-bag li b {
    color: var(--et-ink);
    font-weight: 500;
}

.et-empty {
    padding: 14px 16px;
    font-size: 12px;
    color: var(--et-muted);
}

.et-foot-note {
    padding: 8px 16px;
    font-size: 11.5px;
    color: var(--et-muted);
    border-top: 1px solid var(--et-line);
}

.et-scroll {
    overflow-x: auto;
}

.et-fare {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
    min-width: 480px;
}

.et-fare th {
    text-align: left;
    font-size: 10.5px;
    font-weight: 500;
    color: var(--et-muted);
    padding: 10px 16px;
    border-bottom: 1px solid var(--et-line);
    background: transparent;
}

.et-fare td {
    padding: 12px 16px;
    font-weight: 500;
}

.et-fare tbody tr + tr td {
    border-top: 1px solid var(--et-line);
}

.et-fare th:last-child,
.et-fare td:last-child {
    text-align: right;
}

.et-fare-sum {
    background: var(--et-tint);
    border-top: 1px solid var(--et-line);
    padding: 6px 16px;
}

.et-fare-sum > div {
    display: flex;
    justify-content: flex-end;
    align-items: baseline;
    gap: 32px;
    padding: 6px 0;
    font-size: 12.5px;
}

.et-fare-sum span {
    color: var(--et-muted);
    text-align: right;
}

.et-fare-sum span small {
    display: block;
    font-size: 10.5px;
}

.et-fare-sum b {
    min-width: 90px;
    text-align: right;
    font-weight: 500;
}

.et-fare-sum .et-tot {
    border-top: 1px solid var(--et-line);
    margin-top: 4px;
    padding-top: 10px;
}

.et-fare-sum .et-tot b {
    color: var(--et-navy);
    font-weight: 600;
    font-size: 14px;
}

/* Extra tables passed through the extras slot (special services, fare rules). */
.et-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.et-table th {
    text-align: left;
    font-size: 10.5px;
    font-weight: 500;
    color: var(--et-muted);
    padding: 8px 16px;
    border-bottom: 1px solid var(--et-line);
    background: transparent;
}

.et-table td {
    padding: 9px 16px;
    font-weight: 500;
}

.et-table tbody tr + tr td {
    border-top: 1px solid var(--et-line);
}

.et-sub-h {
    padding: 10px 16px 0;
    font-size: 12px;
    font-weight: 600;
    color: var(--et-navy);
}

.et-note {
    margin: 14px 32px 0;
    padding: 12px 16px;
    border-radius: var(--et-r);
    font-size: 12px;
}

.et-note h5 {
    font-size: 12.5px;
    font-weight: 600;
    margin-bottom: 4px;
}

.et-note ul {
    padding-left: 18px;
    list-style: disc;
}

.et-note--info {
    background: var(--et-info);
    color: var(--et-navy);
}

.et-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 22px;
    padding: 14px 32px;
    border-top: 1px solid var(--et-line);
    font-size: 11.5px;
    color: var(--et-muted);
}

@media screen and (max-width: 700px) {
    .et-head,
    .et-title,
    .et-foot {
        flex-direction: column;
        align-items: flex-start;
    }

    .et-head,
    .et-title,
    .et-foot {
        padding-left: 16px;
        padding-right: 16px;
    }

    .et-meta,
    .et-sec,
    .et-note {
        margin-left: 12px;
        margin-right: 12px;
    }

    .et-title-status {
        align-items: flex-start;
    }

    .et-contact {
        text-align: left;
    }

    .et-flight,
    .et-bag {
        grid-template-columns: 1fr;
    }

    .et-meta {
        grid-template-columns: 1fr 1fr;
    }

    .et-meta > div + div {
        border-left: 0;
    }

    .et-meta > div:nth-child(n + 3) {
        border-top: 1px solid var(--et-line);
    }

    .et-meta > div:nth-child(even) {
        border-left: 1px solid var(--et-line);
    }

    .et-flight > div + div,
    .et-bag > div + div {
        border-left: 0;
        border-top: 1px solid var(--et-line);
    }

    .et-pax {
        grid-template-columns: 1fr 1fr;
    }

    .et-pax > div + div {
        border-left: 0;
    }

    .et-pax .et-wide {
        grid-column: 1 / -1;
        border-bottom: 1px solid var(--et-line);
    }

    .et-pax > div:nth-child(3) {
        border-left: 1px solid var(--et-line);
    }

    .et-pax > div:nth-child(4) {
        grid-column: 1 / -1;
        border-top: 1px solid var(--et-line);
    }
}

/* Print fills one A4 sheet (10px page margin): sizes sit between the screen layout and a
   compact one, and the ticket stretches to the page height so the footer sits at the bottom. */
@media print {
    /* !important because the customer bookings page also loads every other provider's
       print CSS (e.g. Hakimi sets a 0.4in page margin), and the last one loaded would win.
       A zero margin leaves Chrome no room for its date/title/URL header and footer. */
    @page {
        size: A4 !important;
        margin: 0 !important;
    }

    /* Other providers' print CSS sets a fixed font size on every element (body * { font-size: 9pt })
       and borders/grey backgrounds on all th/td. Undo that inside the ticket; the rules below
       then set the ticket's own sizes and borders. */
    .et-ticket * {
        font-size: inherit;
    }

    .et-ticket th,
    .et-ticket td {
        border: 0;
        background: #fff !important;
    }

    .et-ticket .et-fare th,
    .et-ticket .et-table th {
        border-bottom: 1px solid #000;
    }

    .et-ticket .et-fare tbody tr + tr td,
    .et-ticket .et-table tbody tr + tr td {
        border-top: 1px solid #000;
    }

    /* The browser's default 8px body margin would push the footer onto a second page. */
    html:has(.et-ticket),
    body:has(.et-ticket) {
        margin: 0 !important;
        padding: 0 !important;
    }

    .et-ticket {
        display: flex;
        flex-direction: column;
        max-width: none;
        /* One A4 sheet minus the 10px edge on each side, with a little slack for rounding. */
        min-height: calc(297mm - 20px - 3mm);
        margin: 10px;
        break-after: avoid;
        box-shadow: none;
        border-radius: 0;
        font-size: 11.5px;
        line-height: 1.35;
        background: #fff;
        color: #000;

        /* Black and white, high contrast: every palette colour collapses to black on white. */
        --et-navy: #000;
        --et-blue: #000;
        --et-ink: #000;
        --et-muted: #222;
        --et-line: #000;
        --et-tint: #fff;
        --et-info: #fff;
        --et-ok: #000;
        --et-okbg: #fff;
        --et-warn: #000;
        --et-warnbg: #fff;
        --et-bad: #000;
        --et-badbg: #fff;
    }

    .et-ticket .et-brand img {
        filter: grayscale(1) contrast(1.4);
    }

    /* Filled bars become outlined so nothing relies on a coloured background. */
    .et-ticket .et-route {
        background: #fff;
        color: #000;
        border-bottom: 1.5px solid #000;
    }

    .et-ticket .et-route span {
        color: #000;
    }

    .et-ticket .et-head,
    .et-ticket .et-sec-h {
        border-bottom: 1.5px solid #000;
    }

    .et-ticket .et-sec-h,
    .et-ticket .et-lbl,
    .et-ticket .et-fare th,
    .et-ticket .et-table th {
        font-weight: 700;
    }

    .et-ticket .et-badge {
        border: 1.5px solid #000;
        font-weight: 700;
    }

    .et-ticket .et-layover {
        border-top: 1px dashed #000;
        border-bottom: 1px dashed #000;
    }

    .et-ticket .et-note {
        border: 1px solid #000;
    }

    .et-ticket .et-bag li {
        border-top-color: #000;
    }

    .et-sec,
    .et-note,
    .et-head {
        page-break-inside: avoid;
    }

    /* Header: company on the left, agency details as key : value on the right. */
    .et-ticket .et-head {
        align-items: center;
        gap: 20px;
        padding: 12px 16px;
        background: var(--et-tint);
    }

    .et-ticket .et-brand img {
        max-height: 38px !important;
    }

    .et-ticket .et-brand h1 {
        font-size: 15px;
    }

    .et-ticket .et-brand p,
    .et-ticket .et-contact {
        display: none;
    }

    .et-ticket .et-contact-print {
        display: grid;
        grid-template-columns: auto 1fr;
        column-gap: 8px;
        row-gap: 2px;
        max-width: 58%;
        margin: 0;
        font-size: 10.5px;
        color: var(--et-muted);
    }

    /* The colon sits at the right edge of the key column so all colons line up. */
    .et-contact-print dt {
        display: flex;
        justify-content: space-between;
        gap: 12px;
    }

    .et-contact-print dt::after {
        content: ":";
    }

    .et-contact-print dd {
        margin: 0;
        color: var(--et-ink);
    }

    .et-ticket .et-title {
        padding: 12px 16px 0;
    }

    .et-ticket .et-title h2 {
        font-size: 19px;
    }

    .et-ticket .et-title p,
    .et-ticket .et-expiry {
        font-size: 10.5px;
    }

    .et-ticket .et-badge {
        font-size: 11px;
        padding: 4px 10px;
    }

    .et-ticket .et-meta,
    .et-ticket .et-sec,
    .et-ticket .et-note {
        margin: 12px 16px 0;
    }

    .et-ticket .et-meta > div {
        padding: 8px 12px;
    }

    .et-ticket .et-lbl {
        font-size: 9.5px;
        margin-bottom: 1px;
    }

    .et-ticket .et-val {
        font-size: 12.5px;
    }

    .et-ticket .et-sec-h {
        padding: 7px 12px;
        font-size: 12px;
    }

    .et-ticket .et-sec-h svg {
        width: 14px;
        height: 14px;
    }

    .et-ticket .et-route {
        padding: 8px 12px;
    }

    .et-ticket .et-route strong {
        font-size: 12.5px;
    }

    .et-ticket .et-route span {
        font-size: 10.5px;
    }

    .et-ticket .et-layover {
        padding: 4px 12px;
        font-size: 10px;
    }

    .et-ticket .et-flight > div {
        padding: 10px 12px;
    }

    .et-ticket .et-flight .et-lbl {
        margin-bottom: 3px;
    }

    .et-ticket .et-sub {
        font-size: 10px;
        margin-top: 1px;
    }

    .et-ticket .et-pax > div {
        padding: 8px 12px;
    }

    .et-ticket .et-pax .et-val {
        font-size: 12px;
    }

    .et-ticket .et-bag > div {
        padding: 10px 12px;
    }

    .et-ticket .et-bag h4 {
        font-size: 10.5px;
    }

    .et-ticket .et-kg {
        font-size: 14px;
        margin: 1px 0;
    }

    .et-ticket .et-bag ul {
        font-size: 10.5px;
        margin-top: 4px;
    }

    .et-ticket .et-bag li {
        padding: 2px 0;
    }

    .et-ticket .et-bag-route,
    .et-ticket .et-sub-h {
        padding: 6px 12px 0;
        font-size: 10.5px;
    }

    .et-ticket .et-foot-note,
    .et-ticket .et-empty {
        padding: 5px 12px;
        font-size: 10px;
    }

    .et-ticket .et-fare,
    .et-ticket .et-table {
        font-size: 11.5px;
    }

    .et-ticket .et-fare th,
    .et-ticket .et-fare td,
    .et-ticket .et-table th,
    .et-ticket .et-table td {
        padding: 6px 12px !important;
    }

    .et-ticket .et-fare th,
    .et-ticket .et-table th {
        font-size: 9.5px;
    }

    .et-ticket .et-fare-sum {
        padding: 4px 12px;
    }

    .et-ticket .et-fare-sum > div {
        padding: 3px 0;
        font-size: 11.5px;
    }

    .et-ticket .et-fare-sum .et-tot {
        margin-top: 3px;
        padding-top: 6px;
    }

    .et-ticket .et-fare-sum .et-tot b {
        font-size: 13px;
    }

    .et-ticket .et-note {
        margin-bottom: 12px;
        padding: 9px 12px;
        font-size: 10.5px;
    }

    .et-ticket .et-note h5 {
        font-size: 11.5px;
        margin-bottom: 3px;
    }

    /* margin-top: auto pushes the footer to the bottom of the sheet. */
    .et-ticket .et-foot {
        margin-top: auto;
        padding: 8px 16px;
        font-size: 10.5px;
    }

    .et-ticket--dense .et-head {
        padding: 8px 14px;
    }

    .et-ticket--dense .et-title {
        padding: 6px 14px 0;
    }

    .et-ticket--dense .et-meta,
    .et-ticket--dense .et-sec,
    .et-ticket--dense .et-note {
        margin: 7px 14px 0;
    }

    .et-ticket--dense .et-meta > div,
    .et-ticket--dense .et-pax > div {
        padding: 5px 12px;
    }

    .et-ticket--dense .et-route,
    .et-ticket--dense .et-sec-h {
        padding: 5px 12px;
    }

    .et-ticket--dense .et-flight > div,
    .et-ticket--dense .et-bag > div {
        padding: 6px 12px;
    }

    .et-ticket--dense .et-flight .et-lbl {
        margin-bottom: 1px;
    }

    .et-ticket--dense .et-layover {
        padding: 2px 12px;
    }

    .et-ticket--dense .et-fare th,
    .et-ticket--dense .et-fare td,
    .et-ticket--dense .et-table th,
    .et-ticket--dense .et-table td {
        padding: 4px 12px !important;
    }

    .et-ticket--dense .et-note {
        margin-bottom: 8px;
        padding: 6px 12px;
        font-size: 9px;
        line-height: 1.25;
    }

    .et-ticket--dense {
        line-height: 1.25;
    }

    .et-ticket--dense .et-title h2 {
        font-size: 16px;
    }

    .et-ticket--dense .et-val {
        font-size: 11.5px;
    }

    .et-ticket--dense .et-sub {
        font-size: 9px;
        margin-top: 0;
    }

    .et-ticket--dense .et-kg {
        font-size: 12px;
    }

    /* The kg line already states the allowance; drop the repeated detail lines. */
    .et-ticket--dense .et-bag ul,
    .et-ticket--dense .et-foot-note {
        display: none;
    }

    .et-ticket--dense .et-bag-route {
        padding: 4px 12px 0;
    }

    .et-ticket--dense .et-foot {
        padding: 5px 14px;
    }
}
</style>
