<script setup>
import Autocomplete from "@/components/common/Autocomplete.vue";
import DateRangePicker from "@/components/common/DateRangePicker.vue";
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
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

const localValue = ref({ ...props.modelValue });
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
    // Create a new object with swapped values
    emit("update:modelValue", {
        ...localValue.value,
        origin: localValue.value.destination,
        destination: localValue.value.origin,
    });
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
        if (JSON.stringify(val) !== JSON.stringify(localValue.value)) {
            localValue.value = { ...val };
        }
    },
);

watch(
    localValue,
    (val) => {
        if (JSON.stringify(val) !== JSON.stringify(props.modelValue)) {
            emit("update:modelValue", { ...val });
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
    <div class="bg-gray-50">
        <div v-if="activeTab === 'flights'">
            <div v-if="showRoutePreview"
                class="sm:hidden bg-secondary backdrop-blur-md border border-white/20  p-3 flex items-center justify-between gap-2 shadow-md"
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
            <div v-else class="p-4 bg-secondary ">
                <div class="py-6 hidden sm:block">
                    <div class="container">
                        <h2 class="text-4xl font-light text-white">
                            Search for Flights
                        </h2>
                        <p class="text-lg text-white/80 mt-1">
                            Find the best deals for your air travel
                        </p>
                    </div>
                </div>

                <!-- Header Section -->
                <div
                    class="flex flex-col container sm:flex-row justify-between gap-3 sm:gap-0"
                >
                    <!-- Flight Type Tabs -->
                    <div class="w-full sm:w-auto">
                        <Tabs v-model="localValue.flightType" class="w-full">
                            <TabsList
                                class="grid grid-cols-3 sm:mb-4 bg-white/10 text-white w-full"
                            >
                                <TabsTrigger
                                    value="return"
                                    class="text-xs sm:text-sm px-2 py-1.5 sm:px-4 sm:py-2"
                                    >Round Trip</TabsTrigger
                                >
                                <TabsTrigger
                                    value="one-way"
                                    class="text-xs sm:text-sm px-2 py-1.5 sm:px-4 sm:py-2"
                                    >One Way
                                </TabsTrigger>
                                <TabsTrigger
                                    value="multi-city"
                                    class="text-xs sm:text-sm px-2 py-1.5 sm:px-4 sm:py-2"
                                    >Multi-City
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>

                    <!-- Travel Options -->
                    <div
                        class="flex flex-row gap-3 items-stretch xs:items-center"
                    >
                        <!-- Countdown -->
                        <!-- <div v-if="countdown !== null || countdown == '0'"
             class="border bg-white py-1.5 px-2 rounded-md text-primary text-xs sm:text-sm flex items-center justify-center h-10">
          {{ countdown }}
        </div> -->

                        <!-- Class Selection -->
                        <Popover v-model:open="isPopoverOpen">
                            <PopoverTrigger as-child>
                                <button
                                    type="button"
                                    class="w-full h-11 px-3 sm:px-4 flex items-center justify-between rounded-md bg-white/10 hover:bg-white/15 text-white text-sm sm:text-base font-medium focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <!-- Left content -->
                                    <div
                                        class="flex items-center gap-2 truncate"
                                    >
                                        <!-- <ChevronDownIcon
                                                class="w-4 h-4 opacity-80"
                                            /> -->

                                        <span
                                            v-if="totalTravelers"
                                            class="truncate"
                                        >
                                            <span class="font-semibold">
                                                {{ travelersSummary }}
                                            </span>
                                            <span class="mx-1 opacity-60"
                                                >•</span
                                            >
                                            <span class="opacity-90">
                                                {{ classLabel }}
                                            </span>
                                        </span>

                                        <span v-else class="text-gray-400">
                                            Travellers & Class
                                        </span>
                                    </div>

                                    <!-- Right arrow -->
                                    <ChevronDownIcon
                                        class="w-4 h-4 opacity-70"
                                    />
                                </button>
                            </PopoverTrigger>
                            <PopoverContent
                                class="w-80 p-6 rounded-lg border-0 shadow-xl"
                            >
                                <div class="space-y-6">
                                    <!-- Cabin Class -->
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

                                    <!-- Counters -->
                                    <div class="space-y-5">
                                        <div class="flex justify-between items-center">
                                            <Label><b>Adult</b> <br>(12 Years) </Label>
                                            <NumberField
                                            class="w-1/2"
                                                id="adult-field"
                                                v-model="localValue.adult"
                                                :max="maxAdults"
                                                @update:modelValue="
                                                    handleAdultChange
                                                "
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
                                                @update:modelValue="
                                                    handleChildChange
                                                "
                                            >
                                                <NumberFieldContent>
                                                    <NumberFieldDecrement />
                                                    <NumberFieldInput />
                                                    <NumberFieldIncrement />
                                                </NumberFieldContent>
                                            </NumberField>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <Label
                                                ><b>Infant</b> <br>(Under 2 Years)</Label
                                            >
                                            <NumberField
                                            class="w-1/2"
                                                id="infant-field"
                                                v-model="localValue.infant"
                                                :min="0"
                                                :max="maxInfants"
                                                @update:modelValue="
                                                    handleInfantChange
                                                "
                                            >
                                                <NumberFieldContent>
                                                    <NumberFieldDecrement />
                                                    <NumberFieldInput />
                                                    <NumberFieldIncrement />
                                                </NumberFieldContent>
                                            </NumberField>
                                        </div>
                                    </div>

                                    <!-- Footer -->
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

                <!-- Flight Search Form -->
                <div
                    class="gap-2 flex-col container my-2 sm:my-5"
                    v-if="
                        localValue.flightType === 'one-way' ||
                        localValue.flightType === 'return'
                    "
                >
                    <div class="flex flex-col sm:flex-row gap-2">
                        <div class="w-full">
                            <Autocomplete
                                v-model="localValue.origin"
                                placeholder="Origin"
                                :source="airports"
                                :icon="'PlaneTakeoff'"
                                class="text-white"
                                  :default-suggestions="headerDefaultAirportCodes"
                            />
                            <div
                                v-if="errors.origin"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.origin }}
                            </div>
                        </div>

                        <!-- Swap Button -->
                        <div class=" hidden sm:flex items-center justify-center">
                            <button
                                type="button"
                                @click="swapOriginDestination"
                                class="p-2 rounded-md hover:bg-white/20 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50"
                                aria-label="Swap origin and destination"
                            >
                                <ArrowLeftRight class="w-8 h-6 text-white" />
                            </button>
                        </div>

                        <div class="w-full">
                            <Autocomplete
                                v-model="localValue.destination"
                                placeholder="Destination"
                                :source="airports"
                                :icon="'PlaneLanding'"
                                class="text-white"
                                :default-suggestions="headerDefaultAirportCodes"
                            />
                            <div
                                v-if="errors.destination"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.destination }}
                            </div>
                        </div>
                        <div
                            class="w-full"
                            v-show="localValue.flightType === 'one-way'"
                        >
                            <!-- <label
                                class="hidden sm:block text-sm font-semibold text-white mb-1"
                                >Dates</label
                            > -->
                            <Calender
                                class="mt-2"
                                v-model="localValue.dateRange.start"
                                :minValue="
                                    new Date().toLocaleDateString('en-CA')
                                "
                                :prices="{
                                    '2026-01-10': '120,200',
                                    '2026-01-11': '100,200',
                                    '2026-01-12': '200,489',
                                }"
                            />
                            <div
                                v-if="errors.start"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.start }}
                            </div>
                        </div>
                        <div
                            class="w-full"
                            v-show="localValue.flightType === 'return'"
                        >
                            <!-- <label
                                class="hidden sm:block text-sm font-semibold text-white mb-1"
                                >Dates</label
                            > -->
                            <DateRangePicker
                                class="mt-2"
                                :minValue="
                                    new Date().toLocaleDateString('en-CA')
                                "
                                v-model="localValue.dateRange"
                            />
                            <div
                                v-if="errors.start"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.start }}
                            </div>
                            <div
                                v-if="errors.end"
                                class="text-destructive mt-1 text-xs"
                            >
                                {{ errors.end }}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end mt-5">
                        <Button
                            class="text-white bg-primary h-10 gap-2 px-6"
                            @click="handleSearch"
                        >
                            <Search class="w-5" /> Modify Search
                        </Button>
                    </div>
                </div>

                <!-- Multi-City Form -->
                <div v-else class="flex flex-col gap-3 container mt-3">
                    <div
                        v-for="(trip, index) in localValue.multiCityTrips"
                        :key="index"
                        class="relative grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 items-end"
                    >
                        <!-- ❌ Remove button (overlay, NOT a column) -->
                        <button
                            v-if="index >= 2"
                            @click="removeTrip(index)"
                            type="button"
                            class="absolute -top-2 -right-2 flex items-center justify-center w-7 h-7 rounded-full bg-white/60 hover:bg-white text-gray-600 hover:text-red-500"
                        >
                            ✕
                        </button>

                        <!-- From -->
                        <div class="w-full">
                            <Autocomplete
                                v-model="trip.origin"
                                :label="`From (Trip ${index + 1})`"
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
                                :label="`To (Trip ${index + 1})`"
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
                    </div>

                    <!-- Bottom buttons -->
                    <div
                        class="flex flex-col sm:flex-row justify-between gap-3 mt-2"
                    >
                        <Button
                            variant="link"
                            @click="addTrip"
                            :disabled="localValue.multiCityTrips.length >= maxMultiCityTrips"
                            class="w-full sm:w-auto justify-center text-white hover:text-gray-300 text-lg hover:underline hover:underline-offset-4 sm:justify-start"
                        >
                            Add Trip
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
