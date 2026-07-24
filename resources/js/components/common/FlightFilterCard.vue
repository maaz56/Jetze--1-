<script setup>
import Autocomplete from "@/components/common/Autocomplete.vue";
import Button from "@/components/ui/button/Button.vue";
import { Label } from "@/components/ui/label";
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from "@/components/ui/number-field";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { ChevronDownIcon, Search, ArrowLeftRight, ArrowRight, Calendar } from "lucide-vue-next";
import { FETCH_AIRPORTS, FETCH_PNR_DATA } from "@/services/store/actions.type";
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import Calender from "./Calender.vue";
import { CircleArrowDown, Plane } from "lucide-vue-next";
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


const tabs = [
    { id: "flights", name: "Flights", icon: Plane },
    { id: "searchPnr", name: "Search PNR", icon: CircleArrowDown },
    // { id: "hotels", name: "Hotels", icon: Building2 },
    // { id: "cars", name: "Car Rental", icon: Car },
    // { id: "activities", name: "Activities", icon: Compass },
    // { id: "packages", name: "Packages", icon: Ticket },
];
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
});
const emit = defineEmits(["update:modelValue", "search"]);

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
        if (!dateRange.start) {
            errors.value.start = "Start date is required.";
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
        if (!dateRange.start) {
            errors.value.start = "Start date is required.";
            valid = false;
        }
        if (!dateRange.end) {
            errors.value.end = "End date is required.";
            valid = false;
        }
    } else if (flightType === "multi-city") {
        errors.value.multiCityTrips = [];
        multiCityTrips.forEach((trip, idx) => {
            const tripErrors = {};
            if (!trip.origin) tripErrors.origin = "Origin is required.";
            if (!trip.destination)
                tripErrors.destination = "Destination is required.";
            if (!trip.date) tripErrors.date = "Date is required.";
            errors.value.multiCityTrips[idx] = tripErrors;
            if (tripErrors.origin || tripErrors.destination || tripErrors.date)
                valid = false;
        });
    }
    return valid;
}
const activeTab = ref("flights");

const setActiveTab = (id) => {
    activeTab.value = id;
    // Reset PNR field when switching away
    if (id !== "searchPnr") pnr.value = "";
};

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
    } else {
    }
});

function handleSearch() {
    isSubmit.value = true;
    if (validate()) {
        // ✅ update v-model
        emit("update:modelValue", localValue.value);
        emit("search", localValue.value);

        // ✅ emit search with latest data
    }
}
function swapOriginDestination() {
    const previousOrigin = localValue.value.origin;
    localValue.value.origin = localValue.value.destination;
    localValue.value.destination = previousOrigin;
}
// Hide error for date fields when a valid date is selected (one-way/return)
watch(
    () => localValue.value.dateRange?.start,
    (val) => {
        if (val && errors.value.start) {
            errors.value.start = undefined;
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
            if (
                origin &&
                errors.value.multiCityTrips &&
                errors.value.multiCityTrips[idx] &&
                errors.value.multiCityTrips[idx].origin
            ) {
                errors.value.multiCityTrips[idx].origin = undefined;
            }
        });
    },
    { deep: true },
);
watch(
    () => localValue.value.multiCityTrips.map((t) => t.destination),
    (destinations) => {
        destinations.forEach((destination, idx) => {
            if (
                destination &&
                errors.value.multiCityTrips &&
                errors.value.multiCityTrips[idx] &&
                errors.value.multiCityTrips[idx].destination
            ) {
                errors.value.multiCityTrips[idx].destination = undefined;
            }
        });
    },
    { deep: true },
);

// Hide error for multi-city trip date fields
watch(
    () => localValue.value.multiCityTrips.map((t) => t.date),
    (dates, _, onCleanup) => {
        dates.forEach((date, idx) => {
            if (
                date &&
                errors.value.multiCityTrips &&
                errors.value.multiCityTrips[idx] &&
                errors.value.multiCityTrips[idx].date
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
const todayDate = new Date().toISOString().split("T")[0];
const setFlightType = (type) => {
    localValue.value.flightType = type;
    if (type === "one-way") {
        localValue.value.dateRange.end = null;
    }
    if (type === "multi-city") {
        localValue.value.dateRange.start = null;
        localValue.value.dateRange.end = null;
    }
};

const activateReturnTrip = () => {
    localValue.value.flightType = "return";
    if (!localValue.value.dateRange.end) {
        const startDate = localValue.value.dateRange.start || todayDate;
        const returnDate = new Date(startDate);
        returnDate.setDate(returnDate.getDate() + 1);
        localValue.value.dateRange.end = returnDate.toISOString().split("T")[0];
    }
};

function resetTravelers() {
    localValue.value.adult = 1;
    localValue.value.child = 0;
    localValue.value.infant = 0;
    localValue.value.classType = "y";
}
const isPopoverOpen = ref(false);
const isFlightTypeOpen = ref(false);

const flightTypeOptions = [
    { value: "return", label: "Round Trip" },
    { value: "one-way", label: "One Way" },
    { value: "multi-city", label: "Multi-City" },
];

const selectedFlightType = computed(() => {
    return (
        flightTypeOptions.find(
            (option) => option.value === localValue.value.flightType,
        ) || flightTypeOptions[1]
    );
});

const alternateFlightTypes = computed(() => {
    return flightTypeOptions.filter(
        (option) => option.value !== localValue.value.flightType,
    );
});

const selectFlightType = (type) => {
    setFlightType(type);
    isFlightTypeOpen.value = false;
};

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
    // Ensure at least 1 adult
    const maxAllowed = Math.max(1, maxTotal - localValue.value.child);

    localValue.value.adult = Math.min(val, maxAllowed);

    // Infants cannot exceed adults
    if (localValue.value.infant > localValue.value.adult) {
        localValue.value.infant = localValue.value.adult;
    }

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

const addTrip = () => {
    if (localValue.value.multiCityTrips.length >= maxMultiCityTrips) return;
    localValue.value.multiCityTrips.push({
        origin: null,
        destination: null,
        date: "",
    });
};
const removeTrip = (index) => {
    if (localValue.value.multiCityTrips.length > 2) {
        localValue.value.multiCityTrips.splice(index, 1);
    }
};
onMounted(() => {
    store.dispatch("airport/" + FETCH_AIRPORTS);
     if (window.innerWidth < 640) {
    showRoutePreview.value = true;
  }
});

const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
};

const countdown = ref(props.countdown ?? "0");
watch(
    () => props.countdown,
    (newVal) => {
        countdown.value = newVal ?? "0";
    },
);

const startCountdown = (remainingTime) => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    countdown.value = formatTime(remainingTime);

    timerInterval.value = setInterval(() => {
        remainingTime -= 1000;
        if (remainingTime <= 0) {
            clearInterval(timerInterval.value);
            localStorage.removeItem("previous_search");
            showDialog.value = true;
        } else {
            countdown.value = formatTime(remainingTime);
        }
    }, 1000);
};
</script>

<template>
    <div class="flight-filter-shell">
        <div v-if="activeTab === 'flights'">
            <div v-if="showRoutePreview"
                class="sm:hidden bg-primary backdrop-blur-md border border-white/20  p-3 flex items-center justify-between gap-2 shadow-md"
            >
                <!-- Locations -->
                <div
                    class="flex items-center gap-1 text-sm font-medium text-white truncate"
                >
                    <span>{{ route.query.origin }}</span>
                    <ArrowRight class="w-4 h-4 text-gray-300" />
                    <span>{{ route.query.destination }}</span>
                </div>

                <!-- Date -->
                <div
                    class="flex items-center gap-1 text-sm font-medium text-white"
                >
                    <Calendar class="w-4 h-4 text-gray-300" />
                    <span>{{ route.query.departure_date}}</span>
                </div>

                <!-- Change Button -->
                <button
                    @click="showRoutePreview = false"
                    class="text-xs text-white font-semibold px-3 py-1 bg-white/20 rounded-md hover:bg-white/30 transition"
                >
                    Change
                </button>
            </div>
            <div v-else class="flight-filter-panel py-4 sm:py-5">

                <!-- Flight Search Form -->
                <div
                    class="container"
                    v-if="
                        localValue.flightType === 'one-way' ||
                        localValue.flightType === 'return'
                    "
                >
                    <div class="flight-search-header">
                        <div class="flight-type-cell">
                            <Popover v-model:open="isFlightTypeOpen">
                                <PopoverTrigger as-child>
                                    <button
                                        type="button"
                                        class="flight-type-trigger"
                                    >
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                            Trip
                                        </span>
                                        <span class="flex items-center justify-between gap-3 text-sm font-bold text-slate-950">
                                            {{ selectedFlightType.label }}
                                            <ChevronDownIcon class="h-4 w-4 text-slate-500" />
                                        </span>
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent class="w-44 rounded-md border border-slate-200 bg-white p-1 shadow-xl">
                                    <button
                                        v-for="option in alternateFlightTypes"
                                        :key="option.value"
                                        type="button"
                                        @click="selectFlightType(option.value)"
                                        class="w-full rounded px-3 py-2 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        {{ option.label }}
                                    </button>
                                </PopoverContent>
                            </Popover>
                        </div>
                        <div
                            :class="[
                                'flight-fields-grid grid grid-cols-1 items-stretch',
                                'sm:grid-cols-[1.35fr_1.35fr_1.02fr_1.02fr_1.28fr]',
                            ]"
                        >
                            <div class="filter-booking-cell text-start relative w-full">
                                <label
                                    class="block text-sm font-semibold text-gray-700 sm:mb-1"
                                >
                                    FROM
                                </label>
                                <Autocomplete
                                    v-model="localValue.origin"
                                    placeholder="Origin"
                                    :source="airports"
                                    :icon="'PlaneTakeoff'"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <button
                                    type="button"
                                    @click="swapOriginDestination"
                                    class="absolute -right-5 top-1/2 z-10 hidden w-10 h-10 -translate-y-1/2 bg-white text-black border border-gray-200 rounded-full sm:flex items-center justify-center hover:bg-gray-50 transition-colors shadow-md"
                                    aria-label="Swap origin and destination"
                                >
                                    <ArrowLeftRight class="w-5 h-5 text-black" />
                                </button>
                                <div
                                    v-if="errors.origin"
                                    class="text-destructive mt-1 text-xs"
                                >
                                    {{ errors.origin }}
                                </div>
                            </div>

                            <div class="filter-booking-cell text-start relative w-full">
                                <label
                                    class="block text-sm font-semibold text-gray-700 sm:mb-1"
                                >
                                    TO
                                </label>
                                <Autocomplete
                                    v-model="localValue.destination"
                                    placeholder="Destination"
                                    :source="airports"
                                    :icon="'PlaneLanding'"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 border-none focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <div
                                    v-if="errors.destination"
                                    class="text-destructive mt-1 text-xs"
                                >
                                    {{ errors.destination }}
                                </div>
                            </div>

                            <div class="filter-booking-cell w-full text-start">
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-1"
                                >
                                    Departure
                                </label>
                                <Calender
                                    v-model="localValue.dateRange.start"
                                    :minValue="new Date().toLocaleDateString('en-CA')"
                                    class="w-full h-10 sm:h-auto"
                                />
                                <div
                                    v-if="errors.start"
                                    class="text-destructive mt-1 text-xs"
                                >
                                    {{ errors.start }}
                                </div>
                            </div>

                            <div
                                class="filter-booking-cell w-full text-start cursor-pointer"
                                @click="activateReturnTrip"
                            >
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-1"
                                >
                                    Return
                                </label>
                                <template v-if="localValue.flightType === 'return'">
                                    <Calender
                                        v-model="localValue.dateRange.end"
                                        :minValue="
                                            localValue.dateRange.start ||
                                            new Date().toLocaleDateString('en-CA')
                                        "
                                        class="w-full h-10 sm:h-auto"
                                    />
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="min-h-[60px] w-full pt-4 text-left text-xs font-semibold leading-4 text-gray-500"
                                >
                                    Tap to add a return date for bigger discounts
                                </button>
                                <div
                                    v-if="errors.end"
                                    class="text-destructive mt-1 text-xs"
                                >
                                    {{ errors.end }}
                                </div>
                            </div>

                            <div class="filter-booking-cell w-full text-start">
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-1"
                                >
                                    Travellers & Class
                                </label>
                                <Popover v-model:open="isPopoverOpen">
                                    <PopoverTrigger as-child>
                                        <button
                                            type="button"
                                            class="w-full h-[60px] flex items-center justify-between bg-white text-gray-900 text-sm sm:text-base font-medium focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <div class="text-left">
                                                <p class="font-bold text-lg">
                                                    {{ travelersSummary }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    {{ classLabel }}
                                                </p>
                                            </div>
                                            <ChevronDownIcon class="w-4 h-4 opacity-70" />
                                        </button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        class="w-80 p-6 rounded-lg border-0 shadow-xl"
                                    >
                                        <div class="space-y-6">
                                            <div class="grid grid-cols-2 gap-2">
                                                <button
                                                    @click="localValue.classType = 'Y'"
                                                    :class="[
                                                        'py-2 rounded-md text-sm font-medium transition uppercase',
                                                        localValue.classType === 'Y'
                                                            ? 'bg-secondary text-white'
                                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                    ]"
                                                >
                                                    {{ $t("economy") }}
                                                </button>
                                                <button
                                                    @click="localValue.classType = 'S'"
                                                    :class="[
                                                        'py-2 rounded-md text-sm font-medium transition uppercase',
                                                        localValue.classType === 'S'
                                                            ? 'bg-secondary text-white'
                                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                    ]"
                                                >
                                                    {{ $t("premium_economy") }}
                                                </button>
                                                <button
                                                    @click="localValue.classType = 'C'"
                                                    :class="[
                                                        'py-2 rounded-md text-sm font-medium transition uppercase',
                                                        localValue.classType === 'C'
                                                            ? 'bg-secondary text-white'
                                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                    ]"
                                                >
                                                    {{ $t("business") }}
                                                </button>
                                                <button
                                                    @click="localValue.classType = 'F'"
                                                    :class="[
                                                        'py-2 rounded-md text-sm font-medium transition uppercase',
                                                        localValue.classType === 'F'
                                                            ? 'bg-secondary text-white'
                                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                    ]"
                                                >
                                                    {{ $t("first class") }}
                                                </button>
                                            </div>

                                            <div class="space-y-5">
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Adult</b> <br>(12 Years)</Label>
                                                    <NumberField
                                                        class="w-1/2"
                                                        id="adult-field"
                                                        v-model="localValue.adult"
                                                        :max="maxAdults"
                                                        @update:modelValue="handleAdultChange"
                                                    >
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Child</b> <br>(2-11 Years)</Label>
                                                    <NumberField
                                                        class="w-1/2"
                                                        id="child-field"
                                                        v-model="localValue.child"
                                                        :min="0"
                                                        :max="maxChildren"
                                                        @update:modelValue="handleChildChange"
                                                    >
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Infant</b> <br>(Under 2 Years)</Label>
                                                    <NumberField
                                                        class="w-1/2"
                                                        id="infant-field"
                                                        v-model="localValue.infant"
                                                        :min="0"
                                                        :max="maxInfants"
                                                        @update:modelValue="handleInfantChange"
                                                    >
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                            </div>

                                            <div class="flex justify-between">
                                                <Button
                                                    @click="resetTravelers"
                                                    class="text-sm text-white font-medium hover:text-gray-800"
                                                >
                                                    Reset
                                                </Button>
                                                <Button
                                                    @click="applyChanges"
                                                    class="px-5 py-1 bg-secondary text-white rounded-md font-sm"
                                                >
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>

                        <button
                            @click="handleSearch"
                            class="flight-search-button"
                        >
                            <Search class="w-4 h-4" />
                            <span class="rtl:text-right ltr:text-left">
                                {{ $t("search") }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Multi-City Form -->
                <div v-else class="container">
                    <div class="flight-search-header">
                        <div class="flight-type-cell">
                            <Popover v-model:open="isFlightTypeOpen">
                                <PopoverTrigger as-child>
                                    <button type="button" class="flight-type-trigger">
                                        <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                            Trip
                                        </span>
                                        <span class="flex items-center justify-between gap-3 text-sm font-bold text-slate-950">
                                            {{ selectedFlightType.label }}
                                            <ChevronDownIcon class="h-4 w-4 text-slate-500" />
                                        </span>
                                    </button>
                                </PopoverTrigger>
                                <PopoverContent class="w-44 rounded-md border border-slate-200 bg-white p-1 shadow-xl">
                                    <button
                                        v-for="option in alternateFlightTypes"
                                        :key="option.value"
                                        type="button"
                                        @click="selectFlightType(option.value)"
                                        class="w-full rounded px-3 py-2 text-left text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                    >
                                        {{ option.label }}
                                    </button>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="flight-fields-grid multicity-fields-grid grid grid-cols-1 items-stretch sm:grid-cols-[1.35fr_1.35fr_1.02fr_1.28fr]">
                            <div class="filter-booking-cell text-start relative w-full">
                                <label class="block text-sm font-semibold text-gray-700 sm:mb-1">
                                    TRIP 1 FROM
                                </label>
                                <Autocomplete
                                    v-model="localValue.multiCityTrips[0].origin"
                                    placeholder="Origin"
                                    :source="airports"
                                    :icon="'PlaneTakeoff'"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <div v-if="errors.multiCityTrips?.[0]?.origin" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[0].origin }}
                                </div>
                            </div>

                            <div class="filter-booking-cell text-start relative w-full">
                                <label class="block text-sm font-semibold text-gray-700 sm:mb-1">
                                    TRIP 1 TO
                                </label>
                                <Autocomplete
                                    v-model="localValue.multiCityTrips[0].destination"
                                    placeholder="Destination"
                                    :icon="'PlaneLanding'"
                                    :source="airports"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 border-none focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <div v-if="errors.multiCityTrips?.[0]?.destination" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[0].destination }}
                                </div>
                            </div>

                            <div class="filter-booking-cell w-full text-start">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Trip 1 Date
                                </label>
                                <Calender
                                    v-model="localValue.multiCityTrips[0].date"
                                    :minValue="todayDate"
                                    class="w-full h-10 sm:h-auto"
                                />
                                <div v-if="errors.multiCityTrips?.[0]?.date" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[0].date }}
                                </div>
                            </div>

                            <div class="filter-booking-cell w-full text-start">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Travellers & Class
                                </label>
                                <Popover v-model:open="isPopoverOpen">
                                    <PopoverTrigger as-child>
                                        <button
                                            type="button"
                                            class="w-full h-[60px] flex items-center justify-between bg-white text-gray-900 text-sm sm:text-base font-medium focus:outline-none focus:ring-2 focus:ring-primary"
                                        >
                                            <div class="text-left">
                                                <p class="font-bold text-lg">{{ travelersSummary }}</p>
                                                <p class="text-sm text-gray-500">{{ classLabel }}</p>
                                            </div>
                                            <ChevronDownIcon class="w-4 h-4 opacity-70" />
                                        </button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80 p-6 rounded-lg border-0 shadow-xl">
                                        <div class="space-y-6">
                                            <div class="grid grid-cols-2 gap-2">
                                                <button @click="localValue.classType = 'Y'" :class="['py-2 rounded-md text-sm font-medium transition uppercase', localValue.classType === 'Y' ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ $t("economy") }}</button>
                                                <button @click="localValue.classType = 'S'" :class="['py-2 rounded-md text-sm font-medium transition uppercase', localValue.classType === 'S' ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ $t("premium_economy") }}</button>
                                                <button @click="localValue.classType = 'C'" :class="['py-2 rounded-md text-sm font-medium transition uppercase', localValue.classType === 'C' ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ $t("business") }}</button>
                                                <button @click="localValue.classType = 'F'" :class="['py-2 rounded-md text-sm font-medium transition uppercase', localValue.classType === 'F' ? 'bg-secondary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200']">{{ $t("first class") }}</button>
                                            </div>

                                            <div class="space-y-5">
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Adult</b> <br>(12 Years)</Label>
                                                    <NumberField class="w-1/2" id="adult-field-multicity" v-model="localValue.adult" :max="maxAdults" @update:modelValue="handleAdultChange">
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Child</b> <br>(2-11 Years)</Label>
                                                    <NumberField class="w-1/2" id="child-field-multicity" v-model="localValue.child" :min="0" :max="maxChildren" @update:modelValue="handleChildChange">
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <Label><b>Infant</b> <br>(Under 2 Years)</Label>
                                                    <NumberField class="w-1/2" id="infant-field-multicity" v-model="localValue.infant" :min="0" :max="maxInfants" @update:modelValue="handleInfantChange">
                                                        <NumberFieldContent>
                                                            <NumberFieldDecrement />
                                                            <NumberFieldInput />
                                                            <NumberFieldIncrement />
                                                        </NumberFieldContent>
                                                    </NumberField>
                                                </div>
                                            </div>

                                            <div class="flex justify-between">
                                                <Button @click="resetTravelers" class="text-sm text-white font-medium hover:text-gray-800">
                                                    Reset
                                                </Button>
                                                <Button @click="applyChanges" class="px-5 py-1 bg-secondary text-white rounded-md font-sm">
                                                    Apply
                                                </Button>
                                            </div>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>

                        <button @click="handleSearch" class="flight-search-button">
                            <Search class="w-4 h-4" />
                            <span class="rtl:text-right ltr:text-left">{{ $t("search") }}</span>
                        </button>
                    </div>

                    <div class="multicity-extra-panel">
                        <div
                            v-for="(trip, index) in localValue.multiCityTrips"
                            :key="index"
                            v-show="index > 0"
                            class="multicity-extra-row"
                        >
                            <div class="trip-index-badge">Trip {{ index + 1 }}</div>
                            <div class="filter-booking-cell text-start relative w-full">
                                <label class="block text-sm font-semibold text-gray-700 sm:mb-1">
                                    FROM
                                </label>
                                <Autocomplete
                                    v-model="trip.origin"
                                    placeholder="Origin"
                                    :source="airports"
                                    :icon="'PlaneTakeoff'"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <div v-if="errors.multiCityTrips?.[index]?.origin" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[index].origin }}
                                </div>
                            </div>

                            <div class="filter-booking-cell text-start relative w-full">
                                <label class="block text-sm font-semibold text-gray-700 sm:mb-1">
                                    TO
                                </label>
                                <Autocomplete
                                    v-model="trip.destination"
                                    placeholder="Destination"
                                    :icon="'PlaneLanding'"
                                    :source="airports"
                                    :default-suggestions="headerDefaultAirportCodes"
                                    class="w-full px-0 border-none focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                                />
                                <div v-if="errors.multiCityTrips?.[index]?.destination" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[index].destination }}
                                </div>
                            </div>

                            <div class="filter-booking-cell w-full text-start">
                                <label class="block text-sm font-semibold text-gray-700 mb-1">
                                    Date
                                </label>
                                <Calender
                                    v-model="trip.date"
                                    :minValue="localValue.multiCityTrips[index - 1]?.date || todayDate"
                                    class="w-full h-10 sm:h-auto"
                                />
                                <div v-if="errors.multiCityTrips?.[index]?.date" class="text-destructive mt-1 text-xs">
                                    {{ errors.multiCityTrips[index].date }}
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="removeTrip(index)"
                                :disabled="localValue.multiCityTrips.length <= 2"
                                class="multicity-remove-button"
                            >
                                <span class="text-base leading-none">x</span>
                                <span>Remove</span>
                            </button>
                        </div>
                    </div>

                    <div class="mt-3 flex justify-start">
                        <Button
                            @click="addTrip"
                            :disabled="localValue.multiCityTrips.length >= maxMultiCityTrips"
                            class="w-full sm:w-auto justify-center bg-white text-gray-900 hover:bg-gray-100 text-sm sm:justify-start border border-gray-200 px-4 py-2 rounded-md shadow-sm"
                        >
                            Add Another City
                        </Button>
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
    background: linear-gradient(180deg, #f8fafc 0%, #eef4fb 100%);
}

.flight-filter-panel {
    background:
        radial-gradient(circle at 18% 0%, rgba(0, 142, 255, 0.12), transparent 30%),
        linear-gradient(180deg, #ffffff 0%, #f3f7fb 100%);
    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
}

.flight-search-header {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr) 138px;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.28);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
}

.flight-type-cell {
    border-right: 1px solid #e5e7eb;
    background: #f8fafc;
}

.flight-type-trigger {
    display: flex;
    min-height: 76px;
    width: 100%;
    flex-direction: column;
    justify-content: center;
    gap: 0.35rem;
    padding: 0.85rem 1rem;
    text-align: left;
    transition: background-color 0.2s ease;
}

.flight-type-trigger:hover {
    background: #f1f5f9;
}

.flight-fields-grid {
    min-width: 0;
    background: #ffffff;
}

.flight-search-button {
    display: inline-flex;
    min-height: 76px;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    background: linear-gradient(135deg, #0f7df4 0%, #0b55d9 100%);
    color: #ffffff;
    font-size: 1rem;
    font-weight: 800;
    transition:
        filter 0.2s ease,
        transform 0.2s ease;
}

.flight-search-button:hover {
    filter: brightness(1.06);
}

.flight-search-button:active {
    transform: translateY(1px);
}

.multicity-fields-grid .filter-booking-cell:last-child {
    border-right: 0;
}

.multicity-extra-panel {
    display: grid;
    gap: 0.75rem;
    margin-top: 0.75rem;
}

.multicity-extra-row {
    display: grid;
    grid-template-columns: 88px minmax(0, 1.35fr) minmax(0, 1.35fr) minmax(0, 1.02fr) 112px;
    align-items: stretch;
    overflow: hidden;
    border: 1px solid rgba(148, 163, 184, 0.26);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.05);
}

.trip-index-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    border-right: 1px solid #e5e7eb;
    background: #f8fafc;
    color: #0f172a;
    font-size: 0.8rem;
    font-weight: 800;
}

.multicity-remove-button {
    display: inline-flex;
    min-height: 76px;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-left: 1px solid #fee2e2;
    background: #fff7f7;
    color: #dc2626;
    font-size: 0.82rem;
    font-weight: 700;
    transition:
        background-color 0.2s ease,
        color 0.2s ease;
}

.multicity-remove-button:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.multicity-remove-button:disabled {
    cursor: not-allowed;
    opacity: 0.55;
}

.filter-booking-cell {
    @apply relative min-h-[76px] border-b border-gray-200 px-3 py-2 sm:border-b-0 sm:border-r;
}

.filter-booking-cell:last-child {
    @apply sm:border-r-0;
}

.filter-booking-cell :deep(.min-h-\[110px\]) {
    min-height: 44px !important;
    padding: 0 !important;
}

.filter-booking-cell :deep(.h-\[110px\]) {
    height: 44px !important;
    min-height: 44px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.filter-booking-cell :deep(input) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 1.35rem !important;
    font-size: 1.05rem !important;
    line-height: 1.35rem !important;
}

.filter-booking-cell :deep(.dropdown span.mb-1) {
    display: none;
}

.filter-booking-cell :deep(.dropdown .pointer-events-none) {
    padding-top: 0.2rem;
}

.filter-booking-cell :deep(.dropdown h2) {
    font-size: 1.08rem;
    line-height: 1.35rem;
}

.filter-booking-cell :deep(.dropdown p) {
    margin-top: 0.15rem;
    font-size: 0.75rem;
    line-height: 1rem;
}

.filter-booking-cell :deep(button.h-\[110px\]) {
    height: 44px !important;
}

.filter-booking-cell :deep(button.h-\[110px\] p:first-child) {
    font-size: 1rem !important;
    line-height: 1.25rem !important;
}

.filter-booking-cell label {
    font-size: 0.7rem;
    letter-spacing: 0;
    line-height: 1rem;
}

.filter-booking-cell button.min-h-\[60px\] {
    min-height: 44px;
    padding-top: 0.3rem;
    font-size: 0.68rem;
    line-height: 0.95rem;
}

.filter-booking-cell button.h-\[60px\] {
    height: 44px;
}

.filter-booking-cell button.h-\[60px\] p:first-child {
    font-size: 1rem;
    line-height: 1.25rem;
}

.filter-booking-cell button.h-\[60px\] p:last-child {
    font-size: 0.78rem;
    line-height: 1rem;
}

/* Additional responsive utilities if needed */
@media (max-width: 1024px) {
    .flight-search-header {
        grid-template-columns: 1fr;
    }

    .multicity-extra-row {
        grid-template-columns: 1fr;
    }

    .flight-type-cell {
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .flight-type-trigger,
    .flight-search-button,
    .multicity-remove-button {
        min-height: 56px;
    }

    .trip-index-badge {
        min-height: 42px;
        border-right: 0;
        border-bottom: 1px solid #e5e7eb;
        justify-content: flex-start;
        padding: 0 0.85rem;
    }
}

@media (max-width: 480px) {
}

@media (max-width: 640px) {
    .xs\:flex-row {
        flex-direction: row;
    }

    .xs\:items-center {
        align-items: center;
    }

    .xs\:min-w-\[140px\] {
        min-width: 140px;
    }

    .xs\:w-auto {
        width: auto;
    }
}
</style>
