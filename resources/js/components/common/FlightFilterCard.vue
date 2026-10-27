<script setup>
import Autocomplete from "@/components/common/Autocomplete.vue";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    ArrowLeftRight,
    ArrowRight,
    CalendarDays,
    ChevronDownIcon,
    ChevronUp,
    Minus,
    Plane,
    MoveRight,
    Pencil,
    PlaneLanding,
    PlaneTakeoff,
    Plus,
    Repeat,
    Route,
    Search,
    Users,
    X,
} from "lucide-vue-next";
import { FETCH_AIRPORTS, FETCH_PNR_DATA } from "@/services/store/actions.type";
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import Calender from "./Calender.vue";
import { useRouter } from "vue-router";
import { useRoute } from "vue-router";
import { useAuthStore } from "@/services/stores/auth";
const errors = ref({});
const pnr = ref("");
const router = useRouter();
const route = useRoute();
const store = useStore();
const authStore = useAuthStore();
const user = computed(() => authStore.user);
const pnrLoading = ref(false);
const pnrError = ref("");
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const headerDefaultAirportCodes = ["PEW","LHE","SKT","ISB","KHI","MUX","GWD"];
const ONWARD_DATE_MIN_ERROR = "OnwardDate cannot be less than Today";

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            flightType: "one-way",
            adult: 1,
            child: 0,
            infant: 0,
            classType: "",
            origin: "",
            destination: "",
            dateRange: {
                start: null,
                end: null,
            },
            multiCityTrips: [
                { origin: null, destination: null, date: "" },
                { origin: null, destination: null, date: "" },
            ],
        }),
    },
    countdown: {
        type: String,
        default: "0",
    },
    variant: {
        type: String,
        default: "default",
    },
    compactOnReady: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits(["update:modelValue", "search", "compact-change"]);

const createDefaultModel = () => ({
    flightType: "one-way",
    adult: 1,
    child: 0,
    infant: 0,
    classType: "Y",
    origin: "",
    destination: "",
    dateRange: {
        start: null,
        end: null,
    },
    multiCityTrips: [
        { origin: null, destination: null, date: "" },
        { origin: null, destination: null, date: "" },
    ],
});

const normalizeModelValue = (value = {}) => {
    const defaults = createDefaultModel();
    const dateRange = {
        ...defaults.dateRange,
        ...(value?.dateRange || {}),
    };
    const trips = Array.isArray(value?.multiCityTrips) && value.multiCityTrips.length
        ? value.multiCityTrips.map((trip) => ({
              origin: trip?.origin ?? null,
              destination: trip?.destination ?? null,
              date: trip?.date ?? "",
          }))
        : defaults.multiCityTrips.map((trip) => ({ ...trip }));

    return {
        ...defaults,
        ...(value || {}),
        classType: value?.classType || "Y",
        dateRange,
        multiCityTrips: trips,
    };
};

const localValue = ref(normalizeModelValue(props.modelValue));
const syncingFromParent = ref(false);
const isSubmit = ref(false);
const maxMultiCityTrips = 3;
const getLocalDateString = (date = new Date()) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
};

const normalizeDateValue = (date) => {
    if (!date) return "";
    if (typeof date === "string") return date.slice(0, 10);
    if (date instanceof Date && !Number.isNaN(date.getTime())) {
        return getLocalDateString(date);
    }
    return "";
};

const isDateBeforeToday = (date) => {
    const normalizedDate = normalizeDateValue(date);
    return Boolean(normalizedDate && normalizedDate < todayDate);
};

const addDays = (date, days) => {
    const [year, month, day] = normalizeDateValue(date).split("-").map(Number);
    return getLocalDateString(new Date(year, month - 1, day + days));
};

function validate() {
    errors.value = {};
    const { flightType, origin, destination, dateRange, multiCityTrips } =
        localValue.value;
    let valid = true;
    if (flightType === "one-way") {
        if (!origin) {
            errors.value.origin = "Origin is required.";
            valid = false;
        }
        if (!destination) {
            errors.value.destination = "Destination is required.";
            valid = false;
        }
        if (origin && origin === destination) {
            errors.value.destination = "Origin and destination cannot be the same airport.";
            valid = false;
        }
        if (!dateRange.start) {
            errors.value.start = "Start date is required.";
            valid = false;
        } else if (isDateBeforeToday(dateRange.start)) {
            errors.value.start = ONWARD_DATE_MIN_ERROR;
            valid = false;
        }
    } else if (flightType === "return" || flightType === "two-way") {
        if (!origin) {
            errors.value.origin = "Origin is required.";
            valid = false;
        }
        if (!destination) {
            errors.value.destination = "Destination is required.";
            valid = false;
        }
        if (origin && origin === destination) {
            errors.value.destination = "Origin and destination cannot be the same airport.";
            valid = false;
        }
        if (!dateRange.start) {
            errors.value.start = "Start date is required.";
            valid = false;
        } else if (isDateBeforeToday(dateRange.start)) {
            errors.value.start = ONWARD_DATE_MIN_ERROR;
            valid = false;
        }
        if (!dateRange.end) {
            errors.value.end = "End date is required.";
            valid = false;
        } else if (
            dateRange.start &&
            normalizeDateValue(dateRange.end) < normalizeDateValue(dateRange.start)
        ) {
            errors.value.end = "Return date cannot be before departure.";
            valid = false;
        }
    } else if (flightType === "multi-city") {
        errors.value.multiCityTrips = [];
        multiCityTrips.forEach((trip, idx) => {
            const tripErrors = {};
            if (!trip.origin) tripErrors.origin = "Origin is required.";
            if (!trip.destination)
                tripErrors.destination = "Destination is required.";
            if (trip.origin && trip.origin === trip.destination)
                tripErrors.destination = "Origin and destination cannot be the same airport.";
            if (!trip.date) tripErrors.date = "Date is required.";
            else if (isDateBeforeToday(trip.date))
                tripErrors.date = ONWARD_DATE_MIN_ERROR;
            errors.value.multiCityTrips[idx] = tripErrors;
            if (tripErrors.origin || tripErrors.destination || tripErrors.date)
                valid = false;
        });
    }
    return valid;
}
const activeTab = ref("flights");

const handlePnrSearch = async () => {
    if (!pnr.value.trim()) {
        pnrError.value = "Please enter a valid PNR / Booking Reference";
        return;
    }
    store.dispatch("flight/" + FETCH_PNR_DATA, {
        pnr: pnr.value,
        user_id: user.value ? user.value.id : null,
    });
};

watch(bookingDetails, () => {
    if (!user.value) {
        router.push({
            name: "BookingsDetails",
            query: {
                pnr: pnr.value.trim(),
                booking_id: bookingDetails?.value?.id,
                booking_source: bookingDetails?.value?.booking_source,
                flight_provider: bookingDetails?.value?.flight_provider,
                flight_mode: "B2C",
            },
        });
    }
});

const isMobileViewport = () => window.innerWidth < 640;

function handleSearch() {
    isSubmit.value = true;
    if (validate()) {
        emit("update:modelValue", localValue.value);
        emit("search", localValue.value);
        // Collapse back to the compact summary on phones once a search runs.
        if (isMobileViewport() && canCollapse.value) showRoutePreview.value = true;
    }
}

const isSwapping = ref(false);
function swapOriginDestination() {
    const previousOrigin = localValue.value.origin;
    localValue.value.origin = localValue.value.destination;
    localValue.value.destination = previousOrigin;
    isSwapping.value = !isSwapping.value;
}
// Hide error for date fields when a valid date is selected (one-way/return)
watch(
    () => localValue.value.dateRange?.start,
    (val) => {
        if (val && !isDateBeforeToday(val) && errors.value.start) {
            errors.value.start = undefined;
        }
        // Keep the return date on or after the new departure date.
        const end = localValue.value.dateRange?.end;
        if (val && end && normalizeDateValue(end) < normalizeDateValue(val)) {
            localValue.value.dateRange.end = addDays(val, 1);
        }
    },
);
watch(
    () => localValue.value.dateRange?.end,
    (val) => {
        if (val && errors.value.end) {
            errors.value.end = undefined;
        }
    },
);

// Hide error for origin and destination fields when a valid value is selected (one-way/return)
watch(
    () => localValue.value.origin,
    (val) => {
        if (val && errors.value.origin) {
            errors.value.origin = undefined;
        }
    },
);
watch(
    () => localValue.value.destination,
    (val) => {
        if (val && errors.value.destination) {
            errors.value.destination = undefined;
        }
    },
);

// Hide error for multi-city trip origin/destination fields
watch(
    () => localValue.value.multiCityTrips.map((t) => t.origin),
    (origins) => {
        origins.forEach((origin, idx) => {
            if (origin && errors.value.multiCityTrips?.[idx]?.origin) {
                errors.value.multiCityTrips[idx].origin = undefined;
            }
        });
    },
    { deep: true },
);
watch(
    () => localValue.value.multiCityTrips.map((t) => t.destination),
    (destinations, previous = []) => {
        destinations.forEach((destination, idx) => {
            if (destination && errors.value.multiCityTrips?.[idx]?.destination) {
                errors.value.multiCityTrips[idx].destination = undefined;
            }
            // Chain legs: a new arrival city becomes the next leg's departure.
            const nextTrip = localValue.value.multiCityTrips[idx + 1];
            if (
                destination &&
                destination !== previous[idx] &&
                nextTrip &&
                !nextTrip.origin
            ) {
                nextTrip.origin = destination;
            }
        });
    },
    { deep: true },
);

// Hide error for multi-city trip date fields
watch(
    () => localValue.value.multiCityTrips.map((t) => t.date),
    (dates) => {
        dates.forEach((date, idx) => {
            if (
                date &&
                !isDateBeforeToday(date) &&
                errors.value.multiCityTrips?.[idx]?.date
            ) {
                errors.value.multiCityTrips[idx].date = undefined;
            }
        });
    },
    { deep: true },
);
watch(
    () => props.modelValue,
    (val) => {
        const normalized = normalizeModelValue(val);
        if (JSON.stringify(normalized) !== JSON.stringify(localValue.value)) {
            syncingFromParent.value = true;
            localValue.value = normalized;
            syncingFromParent.value = false;
        }
    },
    { deep: true, immediate: true },
);

watch(
    localValue,
    (val) => {
        if (syncingFromParent.value) return;
        const normalized = normalizeModelValue(val);
        if (JSON.stringify(normalized) !== JSON.stringify(props.modelValue)) {
            emit("update:modelValue", normalized);
        }
    },
    { deep: true },
);

const airports = computed(() => store.getters["airport/airports"]);

const totalTravelers = computed(
    () =>
        localValue.value.adult +
        localValue.value.child +
        localValue.value.infant,
);
const todayDate = getLocalDateString();

const isMultiCity = computed(() => localValue.value.flightType === "multi-city");

const setFlightType = (type) => {
    const previousType = localValue.value.flightType;
    if (previousType === type) return;
    const value = localValue.value;
    errors.value = {};

    if (type === "multi-city") {
        // Carry the current route into the first leg so nothing is retyped.
        const firstTrip = value.multiCityTrips[0];
        firstTrip.origin = firstTrip.origin || value.origin || null;
        firstTrip.destination = firstTrip.destination || value.destination || null;
        firstTrip.date = firstTrip.date || value.dateRange.start || "";
        value.dateRange.start = null;
        value.dateRange.end = null;
    } else if (previousType === "multi-city") {
        const firstTrip = value.multiCityTrips[0];
        value.origin = value.origin || firstTrip.origin || "";
        value.destination = value.destination || firstTrip.destination || "";
        value.dateRange.start = value.dateRange.start || firstTrip.date || null;
    }

    value.flightType = type;
    if (type === "one-way") {
        value.dateRange.end = null;
    }
    if (type === "return" && !value.dateRange.end && value.dateRange.start) {
        value.dateRange.end = addDays(value.dateRange.start, 1);
    }
};

const activateReturnTrip = () => {
    localValue.value.flightType = "return";
    if (!localValue.value.dateRange.end) {
        const startDate = localValue.value.dateRange.start || todayDate;
        localValue.value.dateRange.end = addDays(startDate, 1);
    }
};

const removeReturnTrip = () => {
    setFlightType("one-way");
};

function resetTravelers() {
    localValue.value.adult = 1;
    localValue.value.child = 0;
    localValue.value.infant = 0;
    localValue.value.classType = "Y";
}
const isPopoverOpen = ref(false);

const flightTypeOptions = [
    { value: "return", label: "Round Trip", icon: Repeat },
    { value: "one-way", label: "One Way", icon: MoveRight },
    { value: "multi-city", label: "Multi-City", icon: Route },
];

const isFlightTypeActive = (type) =>
    localValue.value.flightType === type ||
    (type === "return" && localValue.value.flightType === "two-way");

const cabinClassOptions = [
    { value: "Y", labelKey: "economy" },
    { value: "S", labelKey: "premium_economy" },
    { value: "C", labelKey: "business" },
    { value: "F", labelKey: "first class" },
];

function applyChanges() {
    isPopoverOpen.value = false;
}

const travelersSummary = computed(() => {
    if (!totalTravelers.value) return "";
    return totalTravelers.value === 1
        ? "1 Traveller"
        : `${totalTravelers.value} Travellers`;
});
const showRoutePreview = ref(false);
const classLabel = computed(() => {
    const map = {
        Y: "Economy",
        S: "Premium Economy",
        C: "Business",
        F: "First Class",
    };
    return map[localValue.value.classType] || "Economy";
});
const maxTotal = 9;
const maxAdults = computed(() => {
    return Math.max(1, maxTotal - localValue.value.child);
});

const maxChildren = computed(() => {
    return Math.max(0, maxTotal - localValue.value.adult);
});

const maxInfants = computed(() => {
    return localValue.value.adult;
});

function clampTravelers() {
    // Ensure at least 1 adult
    if (localValue.value.adult < 1) {
        localValue.value.adult = 1;
    }

    // Enforce total travellers rule (adult + child <= maxTotal)
    const totalTravellers = localValue.value.adult + localValue.value.child;

    if (totalTravellers > maxTotal) {
        localValue.value.child = maxTotal - localValue.value.adult;
    }

    // Infants are NOT counted but must be <= adults
    if (localValue.value.infant > localValue.value.adult) {
        localValue.value.infant = localValue.value.adult;
    }

    // No negatives (safety)
    localValue.value.child = Math.max(0, localValue.value.child);
    localValue.value.infant = Math.max(0, localValue.value.infant);
}

function handleAdultChange(val) {
    const maxAllowed = Math.max(1, maxTotal - localValue.value.child);
    localValue.value.adult = Math.min(val, maxAllowed);
    clampTravelers();
}

function handleChildChange(val) {
    const maxAllowed = maxTotal - localValue.value.adult;
    localValue.value.child = Math.min(val, maxAllowed);
    clampTravelers();
}

function handleInfantChange(val) {
    const maxAllowed = localValue.value.adult;
    localValue.value.infant = Math.min(val, maxAllowed);
    clampTravelers();
}

const travellerRows = computed(() => [
    {
        key: "adult",
        label: "Adults",
        hint: "12+ years",
        min: 1,
        max: maxAdults.value,
        onChange: handleAdultChange,
    },
    {
        key: "child",
        label: "Children",
        hint: "2–11 years",
        min: 0,
        max: maxChildren.value,
        onChange: handleChildChange,
    },
    {
        key: "infant",
        label: "Infants",
        hint: "Under 2 years, on lap",
        min: 0,
        max: maxInfants.value,
        onChange: handleInfantChange,
    },
]);

const stepTraveller = (row, delta) => {
    const next = localValue.value[row.key] + delta;
    if (next < row.min || next > row.max) return;
    row.onChange(next);
};

const addTrip = () => {
    if (localValue.value.multiCityTrips.length >= maxMultiCityTrips) return;
    const lastTrip =
        localValue.value.multiCityTrips[localValue.value.multiCityTrips.length - 1];
    localValue.value.multiCityTrips.push({
        origin: lastTrip?.destination || null,
        destination: null,
        date: "",
    });
};
const removeTrip = (index) => {
    if (localValue.value.multiCityTrips.length > 2) {
        localValue.value.multiCityTrips.splice(index, 1);
        errors.value.multiCityTrips?.splice(index, 1);
    }
};

const formatShortDate = (date) => {
    const normalized = normalizeDateValue(date);
    if (!normalized) return "";
    const [year, month, day] = normalized.split("-").map(Number);
    return new Date(year, month - 1, day).toLocaleDateString("en-GB", {
        day: "numeric",
        month: "short",
    });
};

const findCityName = (code) => {
    if (!code || !Array.isArray(airports.value)) return code || "";
    const match = airports.value.find(
        (airport) => String(airport?.iata_code || "").toUpperCase() === String(code).toUpperCase(),
    );
    return match?.city_name || code;
};

const selectedFlightTypeLabel = computed(
    () =>
        flightTypeOptions.find((option) => isFlightTypeActive(option.value))?.label ||
        "One Way",
);

const previewSummary = computed(() => {
    const value = localValue.value;
    let summary;
    if (isMultiCity.value) {
        const trips = value.multiCityTrips;
        summary = {
            origin: trips[0]?.origin || route.query.origin || "",
            destination: trips[trips.length - 1]?.destination || "",
            dates: [trips[0]?.date, trips[trips.length - 1]?.date]
                .filter(Boolean)
                .map(formatShortDate)
                .join(" – "),
        };
    } else {
        const start = value.dateRange.start || route.query.departure_date;
        summary = {
            origin: value.origin || route.query.origin || "",
            destination: value.destination || route.query.destination || "",
            dates: [start, value.flightType === "return" ? value.dateRange.end : null]
                .filter(Boolean)
                .map(formatShortDate)
                .join(" – "),
        };
    }
    return {
        ...summary,
        originCity: findCityName(summary.origin),
        destinationCity: findCityName(summary.destination),
    };
});

// Only offer the compact bar once there is a route worth summarising.
const canCollapse = computed(
    () => Boolean(previewSummary.value.origin && previewSummary.value.destination),
);
const hasAppliedInitialCompactState = ref(false);

watch(
    canCollapse,
    (hasRoute) => {
        if (
            hasRoute &&
            props.compactOnReady &&
            !hasAppliedInitialCompactState.value
        ) {
            showRoutePreview.value = true;
            hasAppliedInitialCompactState.value = true;
        }
    },
    { immediate: true },
);

watch(showRoutePreview, (value) => emit("compact-change", value), {
    immediate: true,
});

onMounted(() => {
    store.dispatch("airport/" + FETCH_AIRPORTS);
    if (isMobileViewport() && canCollapse.value) {
        showRoutePreview.value = true;
    }
});
</script>

<template>
    <div
        :class="[
            'flight-filter-shell',
            { 'flight-filter-shell--edge-overlap': variant === 'edge-overlap' },
        ]"
    >
        <div v-if="activeTab === 'flights'">
            <!-- Compact summary; expands into the full form -->
            <div v-if="showRoutePreview" class="container flight-filter-content">
                <div class="route-preview">
                    <button
                        type="button"
                        class="route-preview__summary"
                        @click="showRoutePreview = false"
                    >
                        <span class="route-preview__icon">
                            <Plane class="h-4 w-4" />
                        </span>
                        <span class="route-preview__segment route-preview__segment--route">
                            <span class="route-preview__route">
                                <span class="truncate">{{ previewSummary.originCity }}</span>
                                <component
                                    :is="localValue.flightType === 'return' ? ArrowLeftRight : ArrowRight"
                                    class="h-4 w-4 shrink-0 text-primary"
                                />
                                <span class="truncate">{{ previewSummary.destinationCity }}</span>
                            </span>
                            <span class="route-preview__sub">
                                {{ previewSummary.origin }} – {{ previewSummary.destination }}
                                · {{ selectedFlightTypeLabel }}
                            </span>
                        </span>
                        <span v-if="previewSummary.dates" class="route-preview__segment">
                            <span class="route-preview__label">
                                <CalendarDays class="h-3.5 w-3.5" />
                                Dates
                            </span>
                            <span class="route-preview__value">{{ previewSummary.dates }}</span>
                        </span>
                        <span class="route-preview__segment">
                            <span class="route-preview__label">
                                <Users class="h-3.5 w-3.5" />
                                Travellers
                            </span>
                            <span class="route-preview__value">
                                {{ travelersSummary }}, {{ classLabel }}
                            </span>
                        </span>
                        <span class="route-preview__meta">
                            <span v-if="previewSummary.dates">{{ previewSummary.dates }}</span>
                            <span v-if="previewSummary.dates" aria-hidden="true">·</span>
                            <span>{{ travelersSummary }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ classLabel }}</span>
                        </span>
                    </button>
                    <button
                        type="button"
                        @click="showRoutePreview = false"
                        class="route-preview__edit"
                    >
                        <Pencil class="h-4 w-4" />
                        <span class="route-preview__edit-text">Modify search</span>
                    </button>
                </div>
            </div>

            <div v-else class="flight-filter-panel py-4 sm:py-5">
                <div class="container flight-filter-content">
                    <!-- Trip type -->
                    <div class="flight-type-bar">
                        <div class="flight-type-tabs" role="tablist" aria-label="Trip type">
                            <button
                                v-for="option in flightTypeOptions"
                                :key="option.value"
                                type="button"
                                role="tab"
                                :aria-selected="isFlightTypeActive(option.value)"
                                @click="setFlightType(option.value)"
                                :class="[
                                    'flight-type-tab',
                                    { 'is-active': isFlightTypeActive(option.value) },
                                ]"
                            >
                                <component :is="option.icon" class="h-3.5 w-3.5" />
                                {{ option.label }}
                            </button>
                        </div>
                        <button
                            v-if="canCollapse"
                            type="button"
                            class="flight-type-collapse"
                            @click="showRoutePreview = true"
                            aria-label="Collapse search form"
                        >
                            <ChevronUp class="h-4 w-4" />
                            <span class="flight-type-collapse__text">Collapse</span>
                        </button>
                    </div>

                    <!-- Main search row -->
                    <div
                        :class="[
                            'flight-search-card',
                            isMultiCity ? 'flight-search-card--multicity' : 'flight-search-card--standard',
                        ]"
                    >
                        <template v-if="!isMultiCity">
                            <div :class="['search-field search-field--from', { 'has-error': errors.origin }]">
                                <label class="search-field__label">
                                    <PlaneTakeoff class="h-3.5 w-3.5" />
                                    From
                                </label>
                                <Autocomplete
                                    v-model="localValue.origin"
                                    clear-on-click
                                    placeholder="Origin"
                                    :source="airports"
                                    :excluded-iata-codes="[localValue.destination]"
                                    :search-debounce="300"
                                    :icon="'MapPin'"
                                    show-icon
                                    compact-icon
                                    :default-suggestions="headerDefaultAirportCodes"
                                    :auto-fill-defaults="true"
                                    auto-fill-role="origin"
                                    :auto-fill-suggestion-index="0"
                                    class="w-full"
                                />
                                <p v-if="errors.origin" class="search-field__error">
                                    {{ errors.origin }}
                                </p>
                                <button
                                    type="button"
                                    @click="swapOriginDestination"
                                    :class="['swap-button', { 'is-swapped': isSwapping }]"
                                    aria-label="Swap origin and destination"
                                >
                                    <ArrowLeftRight class="h-4 w-4" />
                                </button>
                            </div>

                            <div :class="['search-field search-field--to', { 'has-error': errors.destination }]">
                                <label class="search-field__label">
                                    <PlaneLanding class="h-3.5 w-3.5" />
                                    To
                                </label>
                                <Autocomplete
                                    v-model="localValue.destination"
                                    clear-on-click
                                    placeholder="Destination"
                                    :source="airports"
                                    :excluded-iata-codes="[localValue.origin]"
                                    :search-debounce="300"
                                    :icon="'MapPin'"
                                    show-icon
                                    compact-icon
                                    :default-suggestions="headerDefaultAirportCodes"
                                    :auto-fill-defaults="true"
                                    auto-fill-role="destination"
                                    :auto-fill-suggestion-index="1"
                                    class="w-full"
                                />
                                <p v-if="errors.destination" class="search-field__error">
                                    {{ errors.destination }}
                                </p>
                            </div>

                            <div :class="['search-field', { 'has-error': errors.start }]">
                                <label class="search-field__label">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    Departure
                                </label>
                                <Calender
                                    ref="departureCalendar"
                                    v-model="localValue.dateRange.start"
                                    :minValue="todayDate"
                                    large-icon
                                    class="w-full"
                                />
                                <p v-if="errors.start" class="search-field__error">
                                    {{ errors.start }}
                                </p>
                            </div>

                            <div :class="['search-field', { 'has-error': errors.end }]">
                                <label class="search-field__label">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    Return
                                </label>
                                <template v-if="localValue.flightType === 'return' || localValue.flightType === 'two-way'">
                                    <Calender
                                        v-model="localValue.dateRange.end"
                                        :minValue="localValue.dateRange.start || todayDate"
                                        large-icon
                                        class="w-full"
                                    />
                                    <button
                                        type="button"
                                        class="return-clear-button"
                                        @click.stop="removeReturnTrip"
                                        aria-label="Remove return date"
                                    >
                                        <X class="h-3.5 w-3.5" />
                                    </button>
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="return-add-button"
                                    @click="activateReturnTrip"
                                >
                                    <span class="return-add-button__title">
                                        <Plus class="h-4 w-4" />
                                        Add return
                                    </span>
                                    <span class="return-add-button__hint">Save more on round trips</span>
                                </button>
                                <p v-if="errors.end" class="search-field__error">
                                    {{ errors.end }}
                                </p>
                            </div>
                        </template>

                        <template v-else>
                            <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[0]?.origin }]">
                                <label class="search-field__label">
                                    <PlaneTakeoff class="h-3.5 w-3.5" />
                                    Trip 1 · From
                                </label>
                                <Autocomplete
                                    v-model="localValue.multiCityTrips[0].origin"
                                    clear-on-click
                                    placeholder="Origin"
                                    :source="airports"
                                    :excluded-iata-codes="[localValue.multiCityTrips[0].destination]"
                                    :search-debounce="300"
                                    :icon="'MapPin'"
                                    show-icon
                                    compact-icon
                                    :default-suggestions="headerDefaultAirportCodes"
                                    :auto-fill-defaults="true"
                                    auto-fill-role="origin"
                                    :auto-fill-suggestion-index="0"
                                    class="w-full"
                                />
                                <p v-if="errors.multiCityTrips?.[0]?.origin" class="search-field__error">
                                    {{ errors.multiCityTrips[0].origin }}
                                </p>
                            </div>

                            <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[0]?.destination }]">
                                <label class="search-field__label">
                                    <PlaneLanding class="h-3.5 w-3.5" />
                                    Trip 1 · To
                                </label>
                                <Autocomplete
                                    v-model="localValue.multiCityTrips[0].destination"
                                    clear-on-click
                                    placeholder="Destination"
                                    :icon="'MapPin'"
                                    show-icon
                                    compact-icon
                                    :source="airports"
                                    :excluded-iata-codes="[localValue.multiCityTrips[0].origin]"
                                    :search-debounce="300"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    :auto-fill-defaults="true"
                                    auto-fill-role="destination"
                                    :auto-fill-suggestion-index="1"
                                    class="w-full"
                                />
                                <p v-if="errors.multiCityTrips?.[0]?.destination" class="search-field__error">
                                    {{ errors.multiCityTrips[0].destination }}
                                </p>
                            </div>

                            <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[0]?.date }]">
                                <label class="search-field__label">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    Trip 1 · Date
                                </label>
                                <Calender
                                    v-model="localValue.multiCityTrips[0].date"
                                    :minValue="todayDate"
                                    large-icon
                                    class="w-full"
                                />
                                <p v-if="errors.multiCityTrips?.[0]?.date" class="search-field__error">
                                    {{ errors.multiCityTrips[0].date }}
                                </p>
                            </div>
                        </template>

                        <!-- Travellers & cabin (shared by every trip type) -->
                        <div class="search-field search-field--travellers">
                            <label class="search-field__label">
                                <Users class="h-3.5 w-3.5" />
                                Travellers & Class
                            </label>
                            <Popover v-model:open="isPopoverOpen">
                                <PopoverTrigger as-child>
                                    <button type="button" class="travellers-trigger">
                                        <span class="min-w-0 text-left">
                                            <span class="travellers-trigger__value">{{ travelersSummary }}</span>
                                            <span class="travellers-trigger__sub">{{ classLabel }}</span>
                                        </span>
                                        <ChevronDownIcon
                                            :class="[
                                                'h-4 w-4 shrink-0 text-slate-400 transition-transform',
                                                { 'rotate-180 text-primary': isPopoverOpen },
                                            ]"
                                        />
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent
                                    align="end"
                                    class="travellers-popover w-[20rem] rounded-xl border border-slate-200 bg-white p-0 shadow-xl"
                                >
                                    <div class="travellers-popover__section">
                                        <h4 class="travellers-popover__heading">Cabin class</h4>
                                        <div class="cabin-chip-grid">
                                            <button
                                                v-for="cabin in cabinClassOptions"
                                                :key="cabin.value"
                                                type="button"
                                                @click="localValue.classType = cabin.value"
                                                :class="['cabin-chip', { 'is-selected': localValue.classType === cabin.value }]"
                                            >
                                                {{ $t(cabin.labelKey) }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="travellers-popover__section">
                                        <h4 class="travellers-popover__heading">Travellers</h4>
                                        <div class="space-y-3">
                                            <div
                                                v-for="row in travellerRows"
                                                :key="row.key"
                                                class="traveller-row"
                                            >
                                                <div>
                                                    <p class="traveller-row__label">{{ row.label }}</p>
                                                    <p class="traveller-row__hint">{{ row.hint }}</p>
                                                </div>
                                                <div class="traveller-stepper">
                                                    <button
                                                        type="button"
                                                        :disabled="localValue[row.key] <= row.min"
                                                        @click="stepTraveller(row, -1)"
                                                        :aria-label="`Remove one ${row.label.toLowerCase()}`"
                                                    >
                                                        <Minus class="h-3.5 w-3.5" />
                                                    </button>
                                                    <span class="traveller-stepper__count">{{ localValue[row.key] }}</span>
                                                    <button
                                                        type="button"
                                                        :disabled="localValue[row.key] >= row.max"
                                                        @click="stepTraveller(row, 1)"
                                                        :aria-label="`Add one ${row.label.toLowerCase()}`"
                                                    >
                                                        <Plus class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p class="travellers-popover__note">
                                            Up to {{ maxTotal }} adults and children. One infant per adult.
                                        </p>
                                    </div>

                                    <div class="travellers-popover__actions">
                                        <button type="button" class="travellers-reset" @click="resetTravelers">
                                            Reset
                                        </button>
                                        <button type="button" class="travellers-apply" @click="applyChanges">
                                            Done
                                        </button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="search-action">
                            <button type="button" @click="handleSearch" class="flight-search-button">
                                <Search class="h-5 w-5" />
                                <span>{{ $t("search") }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Additional multi-city legs -->
                    <div v-if="isMultiCity" class="multicity-legs">
                        <template v-for="(trip, index) in localValue.multiCityTrips" :key="index">
                            <div v-if="index > 0" class="multicity-leg">
                                <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[index]?.origin }]">
                                    <label class="search-field__label">
                                        <PlaneTakeoff class="h-3.5 w-3.5" />
                                        Trip {{ index + 1 }} · From
                                    </label>
                                    <Autocomplete
                                        v-model="trip.origin"
                                        clear-on-click
                                        placeholder="Origin"
                                        :source="airports"
                                        :excluded-iata-codes="[trip.destination]"
                                        :search-debounce="300"
                                        :icon="'MapPin'"
                                        show-icon
                                        compact-icon
                                        :default-suggestions="headerDefaultAirportCodes"
                                        class="w-full"
                                    />
                                    <p v-if="errors.multiCityTrips?.[index]?.origin" class="search-field__error">
                                        {{ errors.multiCityTrips[index].origin }}
                                    </p>
                                </div>

                                <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[index]?.destination }]">
                                    <label class="search-field__label">
                                        <PlaneLanding class="h-3.5 w-3.5" />
                                        Trip {{ index + 1 }} · To
                                    </label>
                                    <Autocomplete
                                        v-model="trip.destination"
                                        clear-on-click
                                        placeholder="Destination"
                                        :icon="'MapPin'"
                                        show-icon
                                        compact-icon
                                        :source="airports"
                                        :excluded-iata-codes="[trip.origin]"
                                        :search-debounce="300"
                                        :default-suggestions="headerDefaultAirportCodes"
                                        class="w-full"
                                    />
                                    <p v-if="errors.multiCityTrips?.[index]?.destination" class="search-field__error">
                                        {{ errors.multiCityTrips[index].destination }}
                                    </p>
                                </div>

                                <div :class="['search-field', { 'has-error': errors.multiCityTrips?.[index]?.date }]">
                                    <label class="search-field__label">
                                        <CalendarDays class="h-3.5 w-3.5" />
                                        Trip {{ index + 1 }} · Date
                                    </label>
                                    <Calender
                                        v-model="trip.date"
                                        :minValue="localValue.multiCityTrips[index - 1]?.date || todayDate"
                                        large-icon
                                        class="w-full"
                                    />
                                    <p v-if="errors.multiCityTrips?.[index]?.date" class="search-field__error">
                                        {{ errors.multiCityTrips[index].date }}
                                    </p>
                                </div>

                                <div class="multicity-leg__actions">
                                    <button
                                        v-if="index >= 2"
                                        type="button"
                                        @click="removeTrip(index)"
                                        class="multicity-remove-button"
                                    >
                                        <X class="h-4 w-4" />
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </template>

                        <button
                            v-if="localValue.multiCityTrips.length < maxMultiCityTrips"
                            type="button"
                            @click="addTrip"
                            class="multicity-add-button"
                        >
                            <Plus class="h-4 w-4" />
                            Add another flight
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-else-if="activeTab === 'searchPnr'" class="max-w-xl mx-auto p-4">
            <div class="bg-transparent rounded-xl p-4 border-gray-100">
                <label class="text-sm font-medium text-gray-600 mb-2 block">
                    Enter PNR
                </label>

                <div class="flex gap-3">
                    <input
                        id="pnr-input"
                        v-model="pnr"
                        type="text"
                        placeholder="ABC123"
                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-base uppercase tracking-wider"
                    />

                    <button
                        @click="handlePnrSearch"
                        :disabled="pnrLoading || !pnr?.trim()"
                        class="px-5 py-2 bg-primary text-white rounded-lg font-medium disabled:bg-gray-400 transition-colors"
                    >
                        <span v-if="pnrLoading">...</span>
                        <span v-else>Search</span>
                    </button>
                </div>

                <p v-if="pnrError" class="mt-2 text-sm text-red-600">
                    {{ pnrError }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.flight-filter-shell {
    --field-columns-standard: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.15fr) 9.5rem;
    --field-columns-multicity: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1fr) minmax(0, 1.15fr) 9.5rem;
    background: linear-gradient(180deg, #f8fafc 0%, hsl(var(--primary) / 0.08) 100%);
}

.flight-filter-shell--edge-overlap {
    background: transparent;
}

.flight-filter-shell--edge-overlap .flight-filter-panel {
    padding: 0;
    background: transparent;
    border-bottom: 0;
}

.flight-filter-panel {
    background:
        radial-gradient(circle at 18% 0%, hsl(var(--primary) / 0.12), transparent 30%),
        linear-gradient(180deg, #ffffff 0%, hsl(var(--primary) / 0.05) 100%);
    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
}

/* Keep the complete flight form 20% narrower than its surrounding header on
   desktop, matching the results column below it. */
.flight-filter-content {
    width: 80%;
}

/* ---------- Trip type tabs ---------- */
.flight-type-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.6rem;
}

.flight-type-tabs {
    display: inline-flex;
    gap: 0.25rem;
    padding: 0.2rem;
    border-radius: 999px;
    background: #e2e8f0;
}

.flight-type-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    min-height: 1.85rem;
    padding: 0 0.85rem;
    border-radius: 999px;
    color: #475569;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
    transition: background-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
}

.flight-type-tab:hover {
    color: hsl(var(--primary));
}

.flight-type-tab.is-active {
    background: #ffffff;
    color: hsl(var(--primary));
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.12);
}

.flight-type-collapse {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0 0.75rem;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
    font-size: 0.75rem;
    font-weight: 700;
    transition: background-color 0.2s ease;
}

.flight-type-collapse:hover {
    background: #cbd5e1;
}

/* On the blue results hero the tabs sit on the gradient itself. */
.flight-filter-shell--edge-overlap .flight-type-tabs,
.flight-filter-shell--edge-overlap .flight-type-collapse {
    background: rgba(255, 255, 255, 0.14);
    color: #ffffff;
    backdrop-filter: blur(6px);
}

.flight-filter-shell--edge-overlap .flight-type-collapse:hover {
    background: rgba(255, 255, 255, 0.24);
}

.flight-filter-shell--edge-overlap .flight-type-tab {
    color: rgba(255, 255, 255, 0.85);
}

.flight-filter-shell--edge-overlap .flight-type-tab:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.1);
}

.flight-filter-shell--edge-overlap .flight-type-tab.is-active {
    background: #ffffff;
    color: hsl(var(--primary));
}

/* ---------- Search card ---------- */
.flight-search-card,
.multicity-leg {
    display: grid;
    align-items: stretch;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #ffffff;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
}

.flight-search-card--standard {
    grid-template-columns: var(--field-columns-standard);
}

.flight-search-card--multicity,
.multicity-leg {
    grid-template-columns: var(--field-columns-multicity);
}

.search-field {
    position: relative;
    display: flex;
    min-width: 0;
    min-height: 4.75rem;
    flex-direction: column;
    justify-content: center;
    gap: 0.15rem;
    padding: 0.55rem 0.9rem;
    border-right: 1px solid #e2e8f0;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
}

.search-field:first-child {
    border-top-left-radius: 0.75rem;
    border-bottom-left-radius: 0.75rem;
}

.search-field:hover {
    background: hsl(var(--primary) / 0.035);
}

.search-field:focus-within {
    background: hsl(var(--primary) / 0.05);
    box-shadow: inset 0 -2px 0 hsl(var(--primary));
}

.search-field.has-error {
    background: #fef2f2;
    box-shadow: inset 0 -2px 0 #ef4444;
}

.search-field__label {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: #64748b;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1rem;
    text-transform: uppercase;
    white-space: nowrap;
}

.search-field:focus-within .search-field__label {
    color: hsl(var(--primary));
}

.search-field__error {
    position: absolute;
    top: calc(100% + 0.4rem);
    left: 0.5rem;
    z-index: 20;
    max-width: calc(100% + 4rem);
    padding: 0.3rem 0.55rem;
    border-radius: 0.4rem;
    background: #dc2626;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1rem;
    box-shadow: 0 6px 16px rgba(220, 38, 38, 0.25);
    pointer-events: none;
}

.search-field__error::before {
    position: absolute;
    top: -0.25rem;
    left: 0.75rem;
    width: 0.5rem;
    height: 0.5rem;
    content: "";
    background: #dc2626;
    transform: rotate(45deg);
}

/* Swap control straddles the From / To divider. */
.swap-button {
    position: absolute;
    top: 50%;
    right: -1.05rem;
    z-index: 15;
    display: inline-flex;
    height: 2.1rem;
    width: 2.1rem;
    align-items: center;
    justify-content: center;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #ffffff;
    color: hsl(var(--primary));
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
    transform: translateY(-50%);
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.swap-button svg {
    transition: transform 0.3s ease;
}

.swap-button.is-swapped svg {
    transform: rotate(180deg);
}

.swap-button:hover {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary));
    color: #ffffff;
}

.search-field--to {
    padding-left: 1.4rem;
}

/* Return date: add / clear */
.return-add-button {
    display: flex;
    min-height: 2.75rem;
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    text-align: left;
}

.return-add-button__title {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: hsl(var(--primary));
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.25rem;
}

.return-add-button__hint {
    color: #94a3b8;
    font-size: 0.7rem;
    line-height: 1rem;
}

.return-clear-button {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    z-index: 2;
    display: inline-flex;
    height: 1.4rem;
    width: 1.4rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    color: #94a3b8;
    transition: background-color 0.2s ease, color 0.2s ease;
}

.return-clear-button:hover {
    background: #f1f5f9;
    color: #0f172a;
}

/* Travellers */
.travellers-trigger {
    display: flex;
    min-height: 2.75rem;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    outline: none;
}

.travellers-trigger__value {
    display: block;
    overflow: hidden;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.travellers-trigger__sub {
    display: block;
    overflow: hidden;
    color: #64748b;
    font-size: 0.75rem;
    line-height: 1rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.travellers-popover__section {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.travellers-popover__heading {
    margin-bottom: 0.65rem;
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 800;
}

.cabin-chip-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.cabin-chip {
    min-height: 2.25rem;
    padding: 0.45rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.4rem;
    background: #ffffff;
    color: #475569;
    font-size: 0.72rem;
    font-weight: 600;
    line-height: 1rem;
    transition: border-color 0.2s ease, background-color 0.2s ease, color 0.2s ease;
}

.cabin-chip:hover,
.cabin-chip.is-selected {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary) / 0.07);
    color: hsl(var(--primary));
}

.traveller-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.traveller-row__label {
    color: #0f172a;
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.2rem;
}

.traveller-row__hint {
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1rem;
}

.traveller-stepper {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.traveller-stepper button {
    display: inline-flex;
    height: 1.9rem;
    width: 1.9rem;
    align-items: center;
    justify-content: center;
    border: 1px solid hsl(var(--primary) / 0.4);
    border-radius: 999px;
    color: hsl(var(--primary));
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.traveller-stepper button:hover:not(:disabled) {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary));
    color: #ffffff;
}

.traveller-stepper button:disabled {
    border-color: #e2e8f0;
    color: #cbd5e1;
    cursor: not-allowed;
}

.traveller-stepper__count {
    min-width: 1.25rem;
    color: #0f172a;
    font-size: 0.95rem;
    font-weight: 700;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

.travellers-popover__note {
    margin-top: 0.85rem;
    color: #94a3b8;
    font-size: 0.7rem;
    line-height: 1rem;
}

.travellers-popover__actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
}

.travellers-reset {
    color: hsl(var(--primary));
    font-size: 0.78rem;
    font-weight: 700;
}

.travellers-reset:hover {
    text-decoration: underline;
}

.travellers-apply {
    min-height: 2.1rem;
    padding: 0 1.25rem;
    border-radius: 0.5rem;
    background: hsl(var(--primary));
    color: hsl(var(--primary-foreground));
    font-size: 0.8rem;
    font-weight: 700;
    transition: filter 0.2s ease;
}

.travellers-apply:hover {
    filter: brightness(1.08);
}

/* Search button sits inset inside the card. */
.search-action {
    display: flex;
    padding: 0.45rem;
}

.flight-search-button {
    display: inline-flex;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.55rem;
    background: linear-gradient(135deg, hsl(var(--primary-button-start)) 0%, hsl(var(--primary-button-end)) 100%);
    color: #ffffff;
    font-size: 1.05rem;
    font-weight: 800;
    box-shadow: 0 8px 18px hsl(var(--primary) / 0.3);
    transition: filter 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
}

.flight-search-button:hover {
    filter: brightness(1.06);
    box-shadow: 0 10px 22px hsl(var(--primary) / 0.38);
}

.flight-search-button:active {
    transform: translateY(1px);
}

/* ---------- Multi-city legs ---------- */
.multicity-legs {
    display: grid;
    gap: 0.6rem;
    margin-top: 0.6rem;
}

.multicity-leg {
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.multicity-leg__actions {
    display: flex;
    grid-column: span 2;
    align-items: center;
    justify-content: flex-end;
    padding: 0.45rem 0.75rem;
}

.multicity-remove-button {
    display: inline-flex;
    min-height: 2.25rem;
    align-items: center;
    gap: 0.35rem;
    padding: 0 0.85rem;
    border: 1px solid #fecaca;
    border-radius: 0.5rem;
    color: #dc2626;
    font-size: 0.78rem;
    font-weight: 700;
    transition: background-color 0.2s ease;
}

.multicity-remove-button:hover {
    background: #fef2f2;
}

.multicity-add-button {
    display: inline-flex;
    min-height: 2.6rem;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border: 1.5px dashed hsl(var(--primary) / 0.45);
    border-radius: 0.75rem;
    background: hsl(var(--primary) / 0.04);
    color: hsl(var(--primary));
    font-size: 0.82rem;
    font-weight: 700;
    transition: background-color 0.2s ease, border-color 0.2s ease;
}

.multicity-add-button:hover {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary) / 0.08);
}

.flight-filter-shell--edge-overlap .multicity-add-button {
    border-color: rgba(255, 255, 255, 0.55);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

.flight-filter-shell--edge-overlap .multicity-add-button:hover {
    border-color: #ffffff;
    background: rgba(255, 255, 255, 0.18);
}

/* ---------- Compact summary bar ---------- */
.route-preview {
    display: flex;
    align-items: stretch;
    gap: 0.5rem;
    padding: 0.4rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #ffffff;
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.14);
}

.route-preview__summary {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    gap: 0.25rem;
    border-radius: 0.55rem;
    text-align: left;
    transition: background-color 0.2s ease;
}

.route-preview__summary:hover {
    background: hsl(var(--primary) / 0.035);
}

.route-preview__icon {
    display: inline-flex;
    height: 2.4rem;
    width: 2.4rem;
    flex: none;
    align-items: center;
    justify-content: center;
    margin: 0 0.35rem 0 0.25rem;
    border-radius: 999px;
    background: hsl(var(--primary) / 0.1);
    color: hsl(var(--primary));
}

.route-preview__segment {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.2rem 1.1rem;
    border-left: 1px solid #e2e8f0;
}

.route-preview__segment--route {
    flex: 1 1 auto;
    padding-left: 0.25rem;
    border-left: 0;
}

.route-preview__route {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 0.45rem;
    color: #0f172a;
    font-size: 1rem;
    font-weight: 800;
    line-height: 1.3rem;
}

.route-preview__sub {
    overflow: hidden;
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1rem;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.route-preview__label {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    color: #64748b;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    line-height: 1rem;
    text-transform: uppercase;
}

.route-preview__value {
    color: #0f172a;
    font-size: 0.85rem;
    font-weight: 700;
    line-height: 1.2rem;
    white-space: nowrap;
}

/* Single-line meta replaces the segments on narrow screens. */
.route-preview__meta {
    display: none;
    flex-wrap: wrap;
    gap: 0.3rem;
    color: #64748b;
    font-size: 0.72rem;
    line-height: 1rem;
}

.route-preview__edit {
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    min-height: 2.9rem;
    padding: 0 1.25rem;
    border-radius: 0.55rem;
    background: linear-gradient(135deg, hsl(var(--primary-button-start)) 0%, hsl(var(--primary-button-end)) 100%);
    color: #ffffff;
    font-size: 0.85rem;
    font-weight: 800;
    box-shadow: 0 8px 18px hsl(var(--primary) / 0.3);
    transition: filter 0.2s ease;
}

.route-preview__edit:hover {
    filter: brightness(1.06);
}

/* ---------- Child component overrides (Autocomplete / Calender) ---------- */
.search-field :deep(.min-h-\[110px\]) {
    min-height: 2.75rem !important;
    padding: 0 !important;
}

.search-field :deep(.h-\[110px\]) {
    height: 2.75rem !important;
    min-height: 2.75rem !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.search-field :deep(.dropdown input) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    font-size: 1rem !important;
    line-height: 1.25rem !important;
}

.search-field :deep(.dropdown span.mb-1) {
    display: none;
}

/* Field labels carry the icons, so drop the large inline ones. */
.search-field :deep(.dropdown .pointer-events-none) {
    padding-left: 0;
    padding-right: 0;
}

.search-field :deep(.dropdown .pointer-events-none > svg),
.search-field :deep(button.h-\[110px\] > svg) {
    display: none;
}

.search-field :deep(.dropdown .pointer-events-none p:first-child) {
    margin-bottom: 0 !important;
    color: #0f172a;
    font-size: 1rem !important;
    font-weight: 700;
    line-height: 1.25rem !important;
}

.search-field :deep(.dropdown .pointer-events-none p + p) {
    margin-top: 0 !important;
    margin-bottom: 0 !important;
    color: #64748b;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
}

.search-field :deep(button.h-\[110px\]) {
    background: transparent !important;
}

.search-field :deep(button.h-\[110px\] p:first-child) {
    color: #0f172a;
    font-size: 1rem !important;
    line-height: 1.25rem !important;
}

.search-field :deep(button.h-\[110px\] p + p) {
    margin-top: 0 !important;
    color: #64748b;
    font-size: 0.75rem !important;
    line-height: 1rem !important;
}

/* ---------- Responsive ---------- */
@media (max-width: 1400px) {
    .flight-filter-content {
        width: 100%;
    }
}

@media (max-width: 1024px) {
    .flight-search-card--standard {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .flight-search-card--multicity,
    .multicity-leg {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .search-field {
        border-bottom: 1px solid #e2e8f0;
    }

    .search-field:first-child {
        border-bottom-left-radius: 0;
        border-top-right-radius: 0;
    }

    .search-field--travellers,
    .search-action {
        grid-column: 1 / -1;
    }

    .flight-search-button {
        min-height: 3rem;
    }

    .multicity-leg__actions {
        grid-column: 1 / -1;
    }

    .multicity-leg__actions:empty {
        display: none;
    }

    .search-field__error {
        top: auto;
        bottom: 0.3rem;
        left: auto;
        right: 0.5rem;
        max-width: calc(100% - 1rem);
        padding: 0.15rem 0.4rem;
        font-size: 0.65rem;
    }

    .search-field__error::before {
        display: none;
    }
}

@media (max-width: 640px) {
    .flight-search-card--standard,
    .flight-search-card--multicity,
    .multicity-leg {
        grid-template-columns: minmax(0, 1fr);
    }

    .search-field {
        min-height: 4.25rem;
        border-right: 0;
    }

    .search-field:first-child {
        border-top-right-radius: 0.75rem;
    }

    .search-field--to {
        padding-left: 0.9rem;
    }

    /* Swap sits on the From / To divider, at the right edge. */
    .swap-button {
        top: auto;
        right: 1rem;
        bottom: -1.05rem;
        transform: rotate(90deg);
    }

    .flight-type-tab {
        padding: 0 0.65rem;
        font-size: 0.72rem;
    }

    .flight-type-collapse {
        width: 2rem;
        padding: 0;
    }

    .flight-type-collapse__text {
        display: none;
    }
}

@media (max-width: 768px) {
    .route-preview__summary {
        flex-wrap: wrap;
        align-content: center;
        padding: 0.15rem 0;
    }

    .route-preview__icon {
        display: none;
    }

    .route-preview__segment {
        display: none;
    }

    .route-preview__segment--route {
        display: flex;
        flex-basis: 100%;
        padding: 0 0.5rem;
    }

    .route-preview__sub {
        display: none;
    }

    .route-preview__meta {
        display: flex;
        padding: 0 0.5rem;
    }

    .route-preview__edit {
        min-height: 2.6rem;
        width: 2.6rem;
        padding: 0;
    }

    .route-preview__edit-text {
        display: none;
    }
}
</style>
