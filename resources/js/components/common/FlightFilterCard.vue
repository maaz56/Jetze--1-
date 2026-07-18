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
    <div class="bg-black">
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
            <div v-else class="p-4 bg-gradient-to-b from-black to-primary backdrop-blur-sm">
               

                <!-- Header Section -->
                <div
                    class="flex flex-col container sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-3"
                >
                    <!-- Flight Type Tabs -->
                    <div class="w-full sm:w-auto">
                        <div
                            class="flex w-full sm:w-auto bg-gray-100 rounded-md p-1 flex-row sm:flex-row gap-1"
                        >
                            <button
                                type="button"
                                @click="setFlightType('return')"
                                :class="[
                                    'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                    localValue.flightType === 'return'
                                        ? 'bg-white text-gray-900 border border-gray-200'
                                        : 'text-gray-600 hover:bg-white',
                                ]"
                            >
                                Round Trip
                            </button>
                            <button
                                type="button"
                                @click="setFlightType('one-way')"
                                :class="[
                                    'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                    localValue.flightType === 'one-way'
                                        ? 'bg-white text-gray-900 border border-gray-200'
                                        : 'text-gray-600 hover:bg-white',
                                ]"
                            >
                                One Way
                            </button>
                            <button
                                type="button"
                                @click="setFlightType('multi-city')"
                                :class="[
                                    'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                    localValue.flightType === 'multi-city'
                                        ? 'bg-white text-gray-900 border border-gray-200'
                                        : 'text-gray-600 hover:bg-white',
                                ]"
                            >
                                Multi-City
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Flight Search Form -->
                <div
                    class="container my-2 sm:my-5"
                    v-if="
                        localValue.flightType === 'one-way' ||
                        localValue.flightType === 'return'
                    "
                >
                    <div class="rounded-xl p-3 sm:p-0">
                        <div
                            :class="[
                                'overflow-hidden rounded-md border border-gray-200 bg-white grid grid-cols-1 items-stretch',
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

                        <div class="flex justify-end mt-3 sm:mt-4">
                            <div class="w-full sm:w-40">
                                <button
                                    @click="handleSearch"
                                    class="w-full bg-gradient-to-r from-[#49a7ff] to-[#065af3] hover:brightness-105 rounded-full px-3 py-3 text-white font-bold flex items-center justify-center gap-2 text-lg sm:text-2xl"
                                >
                                    <Search class="w-4 h-4 sm:w-6 sm:h-6" />
                                    <span class="rtl:text-right ltr:text-left">
                                        {{ $t("search") }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multi-City Form -->
                <div v-else class="flex flex-col gap-3 container mt-3">
                    <div
                        v-for="(trip, index) in localValue.multiCityTrips"
                        :key="index"
                        class="space-y-1"
                    >
                        <p class="text-xl sm:text-3xl font-semibold text-white">
                            Trip {{ index + 1 }}
                        </p>

                        <div
                            :class="[
                                'relative grid grid-cols-1 gap-2 sm:gap-3 items-end',
                                'sm:grid-cols-4',
                            ]"
                        >
                        <!-- From -->
                        <div class="w-full">
                            <Autocomplete
                                v-model="trip.origin"
                                :placeholder="$t('origin')"
                                :source="airports"
                                class="text-white"
                                :default-suggestions="headerDefaultAirportCodes"
                            />
                            <div
                                v-if="errors.multiCityTrips?.[index]?.origin"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.multiCityTrips[index].origin }}
                            </div>
                        </div>

                        <!-- To -->
                        <div class="w-full">
                            <Autocomplete
                                v-model="trip.destination"
                                :placeholder="$t('destination')"
                                :icon="'PlaneLanding'"
                                :source="airports"
                                class="text-white"
                                :default-suggestions="headerDefaultAirportCodes"
                            />
                            <div
                                v-if="
                                    errors.multiCityTrips?.[index]?.destination
                                "
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.multiCityTrips[index].destination }}
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="w-full">
                            <Calender
                                v-model="trip.date"
                                :minValue="
                                    index === 0
                                        ? todayDate
                                        : localValue.multiCityTrips[index - 1]
                                              ?.date || todayDate
                                "
                            />
                            <div
                                v-if="errors.multiCityTrips?.[index]?.date"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.multiCityTrips[index].date }}
                            </div>
                        </div>

                        <div v-if="index === 0" class="w-full mt-1 sm:mt-0 text-start">
                            <label
                                class="hidden sm:block text-sm font-semibold text-white mb-1"
                            >
                                Travellers & Class
                            </label>
                            <Popover v-model:open="isPopoverOpen">
                                <PopoverTrigger as-child>
                                    <button
                                        type="button"
                                        class="w-full h-[110px] px-3 sm:px-4 flex items-center justify-between rounded bg-white border border-gray-200 text-gray-900 text-sm sm:text-base font-medium focus:outline-none focus:ring-2 focus:ring-primary"
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
                                                    id="adult-field-multicity"
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
                                                    id="child-field-multicity"
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
                                                    id="infant-field-multicity"
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
                        <div v-else class="w-full mt-1 sm:mt-0">
                            <button
                                type="button"
                                @click="removeTrip(index)"
                                :disabled="localValue.multiCityTrips.length <= 2"
                                class="w-full h-11 rounded-md border border-red-200 bg-white text-red-600 font-medium text-sm transition-colors hover:bg-red-50 hover:border-red-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-white flex items-center justify-center gap-2"
                            >
                                <span class="text-base leading-none">×</span>
                                <span>Remove Trip</span>
                            </button>
                        </div>
                        </div>
                    </div>

                    <!-- Bottom buttons -->
                    <div
                        class="flex flex-col sm:flex-row justify-between gap-3 mt-2"
                    >
                        <Button
                            @click="addTrip"
                            :disabled="localValue.multiCityTrips.length >= maxMultiCityTrips"
                            class="w-full sm:w-auto justify-center bg-white text-gray-900 hover:bg-gray-100 text-base sm:justify-start border border-gray-200 px-5 py-2 rounded-md shadow-sm"
                        >
                            Add Another City
                        </Button>

                        <Button class="" @click="handleSearch"> Search </Button>
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
.filter-booking-cell {
    @apply relative min-h-[92px] border-b border-gray-200 px-4 py-3 sm:border-b-0 sm:border-r;
}

.filter-booking-cell:last-child {
    @apply sm:border-r-0;
}

.filter-booking-cell :deep(.min-h-\[110px\]) {
    min-height: 58px !important;
    padding: 0 !important;
}

.filter-booking-cell :deep(.h-\[110px\]) {
    height: 58px !important;
    min-height: 58px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.filter-booking-cell :deep(input) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 1.85rem !important;
}

.filter-booking-cell :deep(.dropdown span.mb-1) {
    display: none;
}

.filter-booking-cell :deep(.dropdown .pointer-events-none) {
    padding-top: 0.6rem;
}

.filter-booking-cell :deep(.dropdown h2) {
    font-size: 1.45rem;
    line-height: 1.75rem;
}

.filter-booking-cell :deep(.dropdown p) {
    margin-top: 0.15rem;
}

/* Additional responsive utilities if needed */
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
