<script setup>
import { initFlowbite } from "flowbite";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import Skeleton from "primevue/skeleton";
import { Slider } from "@/components/ui/slider";
import { Badge } from "@/components/ui/badge";
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    LoaderCircle,
    Percent,
    Share,
    Zap,
    UserIcon,
    DollarSignIcon,
    ReceiptIcon,
    CreditCardIcon,
    CoinsIcon,
    LuggageIcon,
    PackageIcon,
    ChevronDownIcon,
    HandCoins,
    BriefcaseBusiness,
    Briefcase,
    BriefcaseIcon,
    SquareCheckBig,
    SquareX,
    Utensils,
    Users
} from "lucide-vue-next";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from "@/components/ui/carousel";
import Autocomplete from "@/components/common/Autocomplete.vue";
import { Calendar } from "@/components/ui/calendar";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from "@/components/ui/number-field";
import {
    CalendarDate,
    DateFormatter,
    getLocalTimeZone,
    today,
} from "@internationalized/date";
import { Calendar as CalendarIcon, Search } from "lucide-vue-next";
import { RangeCalendar } from "@/components/ui/range-calendar";
import { Label } from "@/components/ui/label";
import Card from "@/components/ui/card/Card.vue";
import { PlaneTakeoff } from "lucide-vue-next";
import { PlaneLanding } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import Spinner from "@/components/common/Spinner.vue";
import { useRouter } from "vue-router";
import Input from "@/components/ui/input/Input.vue";
import moment from "moment";
import { ClockIcon } from "lucide-vue-next";

import {
    cn,
    formatAmount,
    formatDuration,
    calculateLayover,
} from "@/lib/utils";
import { useFlightStore } from "@/services/stores/flight";
import { useAuthStore } from "@/services/stores/auth";
import Accordion from "@/components/ui/accordion/Accordion.vue";
import { calculateFinalPrice } from "@/lib/utils.js"
import { useStore } from "vuex";
import {
    FETCH_AIRPORTS,
    FETCH_AGENT_DATA,
    FETCH_FLIGHT
} from "@/services/store/actions.type";
import {
    Plane,
    Building2,
    Car,
    Ship,
    Train,
    Bus,
    Compass,
    Ticket,
    CircleArrowDown
} from 'lucide-vue-next';

const activeTab = ref('flights');

const tabs = [
    { id: 'flights', name: 'Flights', icon: Plane },
    { id: 'importPnr', name: 'Import PNR', icon: CircleArrowDown },
    { id: 'hotels', name: 'Hotels', icon: Building2 },
    { id: 'cars', name: 'Car Rental', icon: Car },
    { id: 'activities', name: 'Activities', icon: Compass },
    { id: 'packages', name: 'Packages', icon: Ticket },
];

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};

const store = useStore();
const flightStore = useFlightStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const flightType = ref("one-way");
const flights = computed(() => flightStore.flights);
const cheapestFlightsByAirline = computed(() => flightStore.getCheapestFlightsByAirline);


const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);

const isLoading = computed(() => flightStore.isLoading);
const availableAirlines = computed(() => flightStore.availableAirlines);
const airports = computed(() => store.getters["airport/airports"]);
const previousSearch = JSON.parse(localStorage.getItem("previous_search"));

const loading = ref(true);
const error = ref(null);

const selectedStops = ref();
const selectedAirline = ref([]);
const selectedTimes = ref([]);
const maxPrice = ref();
const priceMargin = ref("");
const quotationTo = ref("");
const isShownMarginInput = ref(false);
const inputErrors = ref(null);

const origin = ref(null);
const destination = ref(null);
const dateRange = ref({
    start: null,
    end: null,
});
const classType = ref("Y");
const adults = ref(1);
const children = ref(0);
const infants = ref(0);
const maxTravelers = 9;
const countdown = ref(null);
const timerInterval = ref(null);
const showDialog = ref(false);
const isSideSheetOpen = ref(false);
const selectedFlightId = ref(null) // Default flight ID
const selectedFlight = computed(() => store.getters["flight/flight"]);
const pnr = ref(null);
const passengerCount = ref();




// Computed property to calculate the total travelers
const totalTravelers = computed(
    () => adults.value + parseInt(children.value) + parseInt(infants.value),
);

// Dynamically calculate max values for adults and children
// const maxValueAdults = computed(() => maxTravelers - children.value);
// const maxValueChildren = computed(() => maxTravelers - adults.value);
// const maxValueInfants = computed(() => adults.value); // 1 INF per ADT

const maxValueAdults = computed(
    () => maxTravelers - (children.value + infants.value),
);
const maxValueChildren = computed(
    () => maxTravelers - (adults.value + infants.value),
);
const maxValueInfants = computed(() =>
    Math.min(adults.value, maxTravelers - (adults.value + children.value)),
);

const isFareOpen = ref(false);
const isBaggageOpen = ref(false);



const toggleFare = () => {
    isFareOpen.value = !isFareOpen.value;
};

const toggleBaggage = () => {
    isBaggageOpen.value = !isBaggageOpen.value;
};

// Open flight details
const openFlightDetails = (flightId) => {
    //console.log(flightId);
    store.dispatch("flight/" + FETCH_FLIGHT, {
        flight_id: flightId,
    });


    isSideSheetOpen.value = true;

};

const getCabinClass = (code) => {
    const cabinClasses = {
        F: "First Class",
        J: "Business Class",
        W: "Premium Economy",
        Y: "Economy Class"
    };

    return cabinClasses[code] || "Unknown Cabin";
};

const validateTravelers = (type, value) => {
    let newValue = parseInt(value);

    if (type === "adults") {
        if (newValue + children.value + infants.value <= maxTravelers) {
            adults.value = newValue;
            if (infants.value > adults.value) {
                infants.value = adults.value;
            }
        }
    } else if (type === "children") {
        if (adults.value + newValue + infants.value <= maxTravelers) {
            children.value = newValue;
        }
    } else if (type === "infants") {
        if (
            newValue <= adults.value &&
            adults.value + children.value + newValue <= maxTravelers
        ) {
            infants.value = newValue;
        }
    }

    initializeSearchParams(); // Update localStorage after changes
};
function fetchAgent() {
    if (user_id.value) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: user_id.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}

const initializeSearchParams = () => {
    const previousSearch =
        JSON.parse(localStorage.getItem("previous_search")) || {};
    const now = Date.now();

    if (
        previousSearch.timestamp &&
        now - previousSearch.timestamp > 15 * 60 * 1000 // FIXED: 15 minutes
    ) {
        localStorage.removeItem("previous_search");

        showDialog.value = true; // Show dialog before refresh
        return;
    }

    // Restore parameters if not already set

    flightType.value =
        flightType.value ??
        route.query.flightType ??
        previousSearch.flightType ??
        "one-way";

    origin.value =
        origin.value ?? route.query.origin ?? previousSearch.origin ?? null;
    destination.value =
        destination.value ??
        route.query.destination ??
        previousSearch.destination ??
        null;
    dateRange.value.start =
        dateRange.value.start ??
        route.query.departure_date ??
        previousSearch.departure_date ??
        null;
    dateRange.value.end =
        dateRange.value.end ??
        route.query.return_date ??
        previousSearch.return_date ??
        null;
    classType.value =
        classType.value ??
        route.query.cabin_class ??
        previousSearch.cabin_class ??
        "Y";
    adults.value =
        adults.value ??
        parseInt(route.query.adults) ??
        previousSearch.adults ??
        1;
    children.value =
        children.value ??
        parseInt(route.query.children) ??
        previousSearch.children ??
        0;
    infants.value =
        infants.value ??
        parseInt(route.query.infants) ??
        previousSearch.infants ??
        0;

    // Start countdown timer
    startCountdown(15 * 60 * 1000 - (now - previousSearch.timestamp));
};

// Function to start countdown
const startCountdown = (remainingTime) => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    countdown.value = formatTime(remainingTime);

    timerInterval.value = setInterval(() => {
        remainingTime -= 1000;
        if (remainingTime <= 0) {
            clearInterval(timerInterval.value);
            localStorage.removeItem("previous_search"); // Clear search data
            showDialog.value = true; // Show dialog before refresh
        } else {
            countdown.value = formatTime(remainingTime);
        }
    }, 1000);
};
// Function to format time in MM:SS
const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(
        2,
        "0",
    )}`;
};
// Function to confirm and refresh

const confirmReload = () => {
    localStorage.removeItem("previous_search");
    // Ensure it's cleared before reload
    showDialog.value = false;
    window.location.reload();
};

const fetchFlights = () => {
    if (origin.value && destination.value && dateRange.value.start) {
        const searchParams = {
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date: dateRange.value.end,
            cabin_class: classType.value,
            adults: adults.value,
            children: children.value,
            infants: infants.value,
            stops: selectedStops.value,
            airline: selectedAirline.value,
            timestamp: Date.now(), // Add timestamp when saving search
            flightType: flightType.value, // Store selected flight type
        };

        flightStore.fetchFlights(searchParams).then(() => {
            // Cache search parameters
            localStorage.setItem(
                "previous_search",
                JSON.stringify(searchParams),
            );

            // Cache flight results
            localStorage.setItem(
                "last_flight_results",
                JSON.stringify(flightStore.flights),
            );
        });
    }
};

const getLayoverInfo = (stops) => {
    if (stops.length <= 1) return "";

    let layoverInfo = [];
    for (let i = 0; i < stops.length - 1; i++) {
        const layoverTime = calculateLayover(stops[i], stops[i + 1]);
        layoverInfo.push(
            `${stops[i].arrival.airport.city_name}: ${layoverTime}`,
        );
    }

    return layoverInfo.join(", ");
};
watch(flightType, (newType) => {
    if (newType === "one-way") {
        dateRange.value.end = null;
        //console.log(dateRange.value.end);
        // Update localStorage with current search params
        updateLocalStorage();
    }
});

const updateLocalStorage = () => {
    localStorage.setItem(
        "previous_search",
        JSON.stringify({
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date:
                flightType.value === "return" ? dateRange.value.end : null,
            cabin_class: classType.value,
            adults: adults.value,
            children: children.value,
            infants: infants.value,
            stops: selectedStops.value,
            airline: selectedAirline.value,
            timestamp: Date.now(),
            flightType: flightType.value,
        }),
    );

    searchFlights();
};
watch(
    () => route.query,
    (newQuery) => {
        origin.value = newQuery.origin || origin.value;
        destination.value = newQuery.destination || destination.value;
        dateRange.value.start =
            newQuery.departure_date || dateRange.value.start;
        dateRange.value.end = newQuery.return_date || dateRange.value.end;
        classType.value = newQuery.cabin_class || classType.value;
        adults.value = parseInt(newQuery.adults) || adults.value;
        children.value = parseInt(newQuery.children) || children.value;
        infants.value = parseInt(newQuery.infants) || infants.value;
        flightType.value = newQuery.flightType || flightType.value;
    },
    { immediate: true },
);
const todayDate = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
});
const endDateMin = ref(todayDate.value);
const updateEndDateMin = () => {
    endDateMin.value = dateRange.value.start || today.value;

    // If end date is before new start date, update it
    if (dateRange.value.end && dateRange.value.end < endDateMin.value) {
        dateRange.value.end = endDateMin.value;
    }
};

function changeDateRange(direction, isReturn = false) {
    const dateKey = isReturn ? "end" : "start";
    const currentDate = moment(dateRange.value[dateKey]);
    dateRange.value[dateKey] =
        direction === "next"
            ? currentDate.add(1, "days").format("YYYY-MM-DD")

            : currentDate.subtract(1, "days").format("YYYY-MM-DD");
}

function importPnr(pnr){
    //console.log(pnr);
    router.push({
                name: "PnrDetails", 
                query: {pnr:pnr}
            });

}

function searchFlights() {
    let errors = [];

    if (!origin.value) errors.push("Please select an Origin");
    if (!destination.value) errors.push("Please select a Destination");
    if (!dateRange.value.start) errors.push("Please select a Date");

    if (errors.length > 0) {
        inputErrors.value = errors;
        return;
    }

    inputErrors.value = null;
    // Store search parameters in localStorage
    localStorage.setItem(
        "previous_search",
        JSON.stringify({
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date:
                flightType.value === "return" ? dateRange.value.end : null,
            cabin_class: classType.value,
            adults: adults.value,
            children: children.value,
            infants: infants.value,
            timestamp: Date.now(),
            flightType: flightType.value,
        }),
    );

    // Update the URL query parameters
    router.push({
        name: "Flights",
        query: {
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date:
                flightType.value === "return" ? dateRange.value.end : null,
            cabin_class: classType.value,
            adults: adults.value,
            children: children.value,
            infants: infants.value,
            flightType: flightType.value,
        },
    });

    // Fetch the flights based on the new parameters
    fetchFlights();
}
// const calculateLayover = (currentStop, nextStop) => {
//     ////console.log(currentStop.arrival.time, nextStop.departure.time);

//     const arrivalTime = moment(currentStop.arrival.time, "hh:mm:ssA");
//     const departureTime = moment(nextStop.departure.time, "hh:mm:ssA");
//     if (departureTime.isBefore(arrivalTime)) {
//     // Add 24 hours to departure time
//     departureTime.add(1, 'day');
//   }
//     const duration = moment.duration(departureTime.diff(arrivalTime));

//     const hours = duration.hours();
//     const minutes = duration.minutes();

//     if (hours > 0) {
//         return `${hours}h ${minutes}m`;
//     } else {
//         return `${minutes}m`;
//     }
// };

const getDefaultValue = computed(() => (field) => {
    return route.query?.[field] ?? previousSearch.value?.[field] ?? "";
});
watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
    }
});

onMounted(() => {
    if (user.value?.id) {
        fetchAgent();
    }
    store.dispatch("airport/" + FETCH_AIRPORTS);
    if (origin.value && destination.value && dateRange.value.start) {
        fetchFlights();
    }
    initializeSearchParams();

    // const cachedResults = JSON.parse(
    //     localStorage.getItem("last_flight_results"),
    // );
    // if (cachedResults) {
    //     flightStore.flights = cachedResults; // Display cached results temporarily
    // }
});

</script>

<template>
    <div class=" bg-white shadow-sm rounded-lg overflow-hidden">

        <!-- Tabs Navigation -->
        <div class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
            <button v-for="tab in tabs" :key="tab.id" @click="setActiveTab(tab.id)"
                class="flex flex-col items-center justify-center min-w-[100px] py-4 px-6 transition-colors duration-200 whitespace-nowrap"
                :class="[
                    activeTab === tab.id
                        ? 'text-green-600 border-b-2 border-green-600 font-medium'
                        : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'
                ]">
                <component :is="tab.icon" :class="[
                    'w-5 h-5 mb-1',
                    activeTab === tab.id ? 'text-green-600' : 'text-gray-500'
                ]" />
                <span class="text-sm">{{ tab.name }}</span>
            </button>
        </div>

        <!-- Tab Content -->
        <div class="p-4">
            <!-- Flights Tab Content -->
            <div v-if="activeTab === 'flights'" class="animate-fadeIn">
                <div class="">
                    <div v-if="isLoading" class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                        <Spinner />
                    </div>
                    <div v-else>
                        <Card class="p-4 bg-white border-b-4 border-b-primary rounded-none shadow-none">
                            <Tabs v-model="flightType" class="">
                                <div class="grid md:grid-cols-2">
                                    <TabsList class="justify-self-start w-full md:w-fit">
                                        <TabsTrigger value="one-way" @click="
                                            flightType = 'one-way';
                                        dateRange.end = null;
                                        initializeSearchParams();
                                        ">One Way</TabsTrigger>
                                        <TabsTrigger value="return" @click="
                                            flightType = 'return';
                                        initializeSearchParams();
                                        ">Return</TabsTrigger>
                                    </TabsList>
                                    <div class="w-full flex items-center gap-3 justify-self-end mt-4 md:mt-0 md:w-fit">
                                        <div v-if="countdown !== null"
                                            class="bg-green-200 py-2 px-2 rounded-md text-primary font-semibold">
                                            {{ countdown }}
                                        </div>
                                        <Select v-model="classType" defaultValue="Y">
                                            <SelectTrigger id="class-type" class="gap-2">
                                                <SelectValue placeholder="Select class" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem value="Y">{{
                                                        $t("economy")
                                                        }}</SelectItem>
                                                    <SelectItem value="S">{{
                                                        $t("premium_economy")
                                                        }}</SelectItem>
                                                    <SelectItem value="C">{{
                                                        $t("business")
                                                        }}</SelectItem>
                                                    <SelectItem value="J">{{
                                                        $t("Premium Business")
                                                        }}</SelectItem>
                                                    <SelectItem value="F">{{
                                                        $t("first")
                                                        }}</SelectItem>
                                                    <SelectItem value="P">{{
                                                        $t("Premium First")
                                                        }}</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>

                                        <Popover>
                                            <PopoverTrigger as-child>
                                                <Button variant="outline">
                                                    <h6 v-if="totalTravelers" class="text-base font-semibold">
                                                        {{ totalTravelers }}
                                                        {{
                                                            totalTravelers === 1
                                                                ? $t("traveller")
                                                                : $t("travellers")
                                                        }}
                                                    </h6>
                                                    <h1 v-else class="md:text-2xl font-semibold">
                                                        Travellers
                                                    </h1>
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                class="border bg-white rounded-lg overflow-hidden w-full p-4">
                                                <!-- Adults Field -->
                                                <NumberField class="mb-3" id="adults" v-model="adults" :min="1"
                                                    :max="maxValueAdults" @update:model-value="
                                                        (value) =>
                                                            validateTravelers(
                                                                'adults',
                                                                value,
                                                            )
                                                    ">
                                                    <Label for="adults">{{
                                                        $t("adults")
                                                        }}</Label>
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>

                                                <!-- Children Field -->
                                                <NumberField class="mb-3" id="children" v-model="children" :min="0"
                                                    :max="maxValueChildren" @update:model-value="
                                                        (value) =>
                                                            validateTravelers(
                                                                'children',
                                                                value,
                                                            )
                                                    ">
                                                    <Label for="children">{{
                                                        $t("children")
                                                        }}</Label>
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>
                                                <NumberField class="mb-3" id="infants" v-model="infants"
                                                    :max="maxValueInfants" :min="0" @update:model-value="
                                                        (value) =>
                                                            validateTravelers(
                                                                'infants',
                                                                value,
                                                            )
                                                    ">
                                                    <Label for="infants">{{
                                                        $t("infants")
                                                        }}</Label>
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                </div>
                                <!-- one way flight search -->
                                <TabsContent value="one-way">
                                    <div class="grid md:grid-cols-2 gap-2">
                                        <div class="grid md:grid-cols-2 gap-2">
                                            <Autocomplete v-model="origin" label="From" :default-value="route.query?.origin
                                                ? route.query?.origin
                                                : previousSearch?.origin
                                                    ? previousSearch?.origin
                                                    : ''
                                                " :placeholder="$t('origin')" :source="airports" />
                                            <Autocomplete v-model="destination" label="To" :default-value="route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                    ? previousSearch?.destination
                                                    : ''
                                                " :placeholder="$t('destination')" :source="airports" />


                                        </div>

                                        <div class="grid md:grid-cols-3 gap-2">
                                            <div class="col-span-2 flex items-center gap-2">
                                                <div class="h-full relative w-full">
                                                    <div class="absolute flex items-center justify-between w-full">
                                                        <span class="text-base ml-3 mt-2">Departure date</span>
                                                        <div class="flex items-center">
                                                            <Button class="rounded-full" variant="ghost" size="icon"
                                                                @click="
                                                                    changeDateRange(
                                                                        'previous',
                                                                    )
                                                                    ">
                                                                <ChevronLeft class="w-5 h-5 text-gray-600" />
                                                            </Button>
                                                            <Button class="rounded-full" variant="ghost" size="icon"
                                                                @click="
                                                                    changeDateRange('next')
                                                                    ">
                                                                <ChevronRight class="w-5 h-5 text-gray-600" />
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    <input id="departure-date" type="date" v-model="dateRange.start"
                                                        :min="todayDate"
                                                        class="w-full h-full pt-7 px-3 py-2 bg-background border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-input" />

                                                </div>
                                            </div>

                                            <Button @click="searchFlights" class="text-lg h-full">
                                                <span v-if="!isLoading">{{
                                                    $t("search")
                                                    }}</span>
                                                <span v-else class="flex items-center gap-2">
                                                    <LoaderCircle class="w-5 h-5 animate-spin" />Loading...
                                                </span>
                                            </Button>
                                        </div>
                                    </div>
                                </TabsContent>

                                <!--return flight search -->
                                <TabsContent value="return">
                                    <div class="grid md:grid-cols-2 gap-2">
                                        <div class="grid md:grid-cols-2 gap-2">
                                            <Autocomplete v-model="origin" label="From" :default-value="route.query?.origin
                                                ? route.query?.origin
                                                : previousSearch?.origin
                                                    ? previousSearch?.origin
                                                    : ''
                                                " :placeholder="$t('origin')" :source="airports" />
                                            <Autocomplete v-model="destination" label="To" :default-value="route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                    ? previousSearch?.destination
                                                    : ''
                                                " :placeholder="$t('destination')" :source="airports" />
                                            <!-- <Autocomplete v-model="origin" label="From" :default-value="route.query?.origin
                                                    ? route.query?.origin
                                                    : previousSearch?.origin
                                                        ? previousSearch?.origin
                                                        : ''
                                                " :placeholder="$t('origin')" :source="airports" />
                                            <Autocomplete v-model="destination" label="To" :default-value="route.query?.destination
                                                    ? route.query?.destination
                                                    : previousSearch?.destination
                                                        ? previousSearch?.destination
                                                        : ''
                                                " :placeholder="$t('destination')" :source="airports" /> -->
                                        </div>

                                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                            <div class="space-y-2 md:col-span-2 lg:col-span-3">
                                                <div class="grid gap-2 sm:grid-cols-2 h-full">
                                                    <div class="relative">
                                                        <div class="absolute flex items-center justify-between w-full">
                                                            <span class="text-base ml-3 mt-2">Departure date</span>
                                                            <div class="flex items-center">
                                                                <Button class="rounded-full" variant="ghost" size="icon"
                                                                    @click="
                                                                        changeDateRange(
                                                                            'previous',
                                                                            false,
                                                                        )
                                                                        ">
                                                                    <ChevronLeft class="w-5 h-5 text-gray-600" />
                                                                </Button>
                                                                <Button class="rounded-full" variant="ghost" size="icon"
                                                                    @click="
                                                                        changeDateRange(
                                                                            'next',
                                                                            false,
                                                                        )
                                                                        ">
                                                                    <ChevronRight class="w-5 h-5 text-gray-600" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                        <input id="departure-date" type="date"
                                                            @change="updateEndDateMin" v-model="dateRange.start"
                                                            :min="todayDate"
                                                            class="w-full h-full pt-7 px-3 py-2 bg-background border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-input" />
                                                    </div>
                                                    <div class="relative">
                                                        <div class="absolute flex items-center justify-between w-full">
                                                            <span class="text-base ml-3 mt-2">Return date</span>
                                                            <div class="flex items-center">
                                                                <Button class="rounded-full" variant="ghost" size="icon"
                                                                    @click="
                                                                        changeDateRange(
                                                                            'previous',
                                                                            true,
                                                                        )
                                                                        ">
                                                                    <ChevronLeft class="w-5 h-5 text-gray-600" />
                                                                </Button>
                                                                <Button class="rounded-full" variant="ghost" size="icon"
                                                                    @click="
                                                                        changeDateRange(
                                                                            'next',
                                                                            true,
                                                                        )
                                                                        ">
                                                                    <ChevronRight class="w-5 h-5 text-gray-600" />
                                                                </Button>
                                                            </div>
                                                        </div>
                                                        <input id="return-date" :min="endDateMin" type="date"
                                                            v-model="dateRange.end"
                                                            class="w-full h-full pt-7 px-3 py-2 bg-background border border-input rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-input" />
                                                    </div>
                                                </div>
                                            </div>

                                            <button @click="searchFlights"
                                                class="h-full px-4 py-2 bg-primary text-primary-foreground rounded-md hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
                                                <template v-if="isLoading">
                                                    <LoaderCircle class="inline-block mr-2 h-4 w-4 animate-spin" />
                                                    Loading...
                                                </template>
                                                <template v-else>
                                                    <!-- <Search
                                            class="inline-block mr-2 h-4 w-4"
                                        /> -->
                                                    Search
                                                </template>
                                            </button>
                                        </div>
                                    </div>
                                </TabsContent>
                            </Tabs>
                            <div v-if="inputErrors"
                                class="flex p-4 mb-4 mt-4 text-sm text-red-800 rounded-lg bg-red-100 dark:bg-gray-800 dark:text-red-400"
                                role="alert">
                                <svg class="flex-shrink-0 inline w-4 h-4 me-3 mt-[2px]" aria-hidden="true"
                                    xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z" />
                                </svg>
                                <div>
                                    <span class="font-medium">{{
                                        $t(
                                            "please_ensure_that_these_fields_are_filled_properly",
                                        )
                                    }}</span>
                                    <ul class="mt-1.5 list-disc list-inside">
                                        <li v-for="error in inputErrors" :key="error">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </Card>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-end w-full gap-2 mt-2">
                            <Input v-if="isShownMarginInput" v-model="priceMargin" type="number" class="w-[200px]"
                                placeholder="Price Margin" />
                            <Button @click="isShownMarginInput = !isShownMarginInput">
                                <Zap class="w-5 h-5" />
                            </Button>
                        </div>
                    </div>

                </div>

                <!-- Flight search form would go here -->

            </div>
            <div v-else-if="activeTab === 'importPnr'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Enter PNR to import.</h3>

                <!-- Packages search form would go here -->
                <div class="flex gap-4 p-4">
                    <Input v-model="pnr" type="text" class="w-[200px]" placeholder="PNR" />
                    <Button
                    @click="importPnr(pnr)"
                    > Import PNR</Button>

                </div>



            </div>

            <!-- Hotels Tab Content -->
            <div v-else-if="activeTab === 'hotels'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Find the perfect hotel</h3>
                <p class="text-gray-600 mb-4">Coming soon</p>

                <!-- Hotel search form would go here -->

            </div>

            <!-- Car Rental Tab Content -->
            <div v-else-if="activeTab === 'cars'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Rent a car</h3>
                <p class="text-gray-600 mb-4">Coming soon</p>



            </div>

            <!-- Cruises Tab Content -->
            <!-- <div v-else-if="activeTab === 'cruises'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Book a cruise</h3>
                <p class="text-gray-600 mb-4">Explore cruise options for your next vacation.</p>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm text-gray-500">Cruise search form placeholder</p>
                </div>
            </div> -->

            <!-- Trains Tab Content -->
            <!-- <div v-else-if="activeTab === 'trains'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Find train tickets</h3>
                <p class="text-gray-600 mb-4">Search for train connections to your destination.</p>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm text-gray-500">Train search form placeholder</p>
                </div>
            </div> -->

            <!-- Buses Tab Content -->
            <!-- <div v-else-if="activeTab === 'buses'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Find bus tickets</h3>
                <p class="text-gray-600 mb-4">Search for bus connections to your destination.</p>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm text-gray-500">Bus search form placeholder</p>
                </div>
            </div> -->

            <!-- Activities Tab Content -->
            <div v-else-if="activeTab === 'activities'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Discover activities</h3>
                <p class="text-gray-600 mb-4">Coming soon</p>


            </div>

            <!-- Packages Tab Content -->
            <div v-else-if="activeTab === 'packages'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Find travel packages</h3>
                <p class="text-gray-600 mb-4">Coming Soon.</p>

                <!-- Packages search form would go here -->

            </div>


        </div>
    </div>
    <!--  -->

    <div v-if="!isLoading && flights?.itineraries" class="mt-6">
        <Carousel class="flex items-center gap-x-3 relative w-full" :opts="{
            align: 'start',
        }">
            <div>
                <CarouselPrevious />
            </div>
            <CarouselContent>
                <!-- Fixed carousel items to prevent shrinking with min-width -->
                <CarouselItem v-for="flight in cheapestFlightsByAirline" :key="flight.id"
                    class="md:basis-1/2 lg:basis-1/6 min-w-[200px] flex-shrink-0 px-2 mx-2">
                    <div class="h-full">
                        <div @click="$router.push({
                            name: 'AgentFlightCheckout',
                            query: {
                                flight_id: flight.id,
                                price_margin: priceMargin || 0,
                            },
                        })" class="bg-white border border-gray-200 p-4 rounded hover:shadow-md transition-all duration-200 cursor-pointer select-none h-full flex flex-col">
                            <div class="flex items-center">
                                <div
                                    class="w-10 h-10 border-gray-100 flex items-center justify-center mr-3">
                                    <img :src="flight.legs[0].stops[0].airline.logo_url" alt=""
                                        class="w-7 h-7 object-contain" />
                                </div>
                                <span class="text-xs font-light text-gray-800 line-clamp-1">
                                    {{ flight.legs[0].stops[0].airline.name }}
                                </span>
                            </div>

                            <!-- Flight type indicator -->
                            <div class="text-xs text-gray-500 mb-2">
                                {{ flight.legs[0].stops.length > 1 ?
                                    flight.legs[0].stops.length - 1 + ' Stop' + (flight.legs[0].stops.length > 2 ? 's' : '')
                                :
                                'Direct Flight' }}
                            </div>

                            <!-- Price section -->
                            <div class="mt-auto pt-2 border-t border-gray-100">
                                <p class="text-normal font-base text-primary">
                                    
                                    {{ formatAmount(flight.pricing.totalPrice) }}
                                </p>
                                <p class="text-xs text-gray-500">Best available price</p>
                            </div>
                        </div>
                    </div>
                </CarouselItem>
            </CarouselContent>
            <div>
                <CarouselNext />
            </div>
        </Carousel>
    </div>

    <div class=" flex flex-col md:flex-row gap-x-10">
        <div v-if="isLoading"
            class="bg-white rounded-lg h-[400px] w-full md:w-[450px] p-4 flex items-center justify-center border mt-8">
            <div role="status">
                <LoaderCircle class="w-5 h-5 animate-spin text-primary" />
                <span class="sr-only">Loading...</span>
            </div>
        </div>

        <div v-if="!isLoading && flights?.itineraries" class="relative w-full md:w-[450px]">
            <div class="top-1 mt-4 p-4 bg-white rounded-lg h-[150vh] overflow-y-auto">
                <div class="bg-gray-100 border rounded-lg p-4 flex flex-col">
                    <span class="text-sm text-muted-foreground font-medium">{{
                        $t("search_queries")
                        }}</span>
                    <div class="flex-grow">
                        <div>
                            <span class="text-lg font-semibold">{{
                                $route.query.origin
                                }}</span>
                            <span class="mx-2">To</span>
                            <span class="text-lg font-semibold">{{
                                $route.query.destination
                                }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground mr-2">{{ $t("departure_date") }}:</span>
                            <span>{{ $route.query.departure_date }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground mr-2">{{ $t("return_date") }}:</span>
                            <span>{{ $route.query.return_date }}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div v-if="$route.query.adults">
                                <span class="text-sm text-muted-foreground mr-2">{{ $t("adults") }}:</span>
                                <span>{{ $route.query.adults }}</span>
                            </div>
                            <div v-if="$route.query.children">
                                <span class="text-sm text-muted-foreground mr-2">{{ $t("children") }}:</span>
                                <span>{{ $route.query.children }}</span>
                            </div>
                            <div v-if="$route.query.infants">
                                <span class="text-sm text-muted-foreground mr-2">{{ $t("infants") }}:</span>
                                <span>{{ $route.query.infants }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <h1 class="text-xl font-medium">{{ $t("filter") }}</h1>

                    <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("stops") }}
                        </p>
                        <ul>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input v-model="selectedStops" @change="fetchFlights" id="all" type="radio"
                                        value="all" name="flight-stops"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                    <label for="all"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base">
                                        {{ $t("all") }}
                                    </label>
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input v-model="selectedStops" @change="fetchFlights" id="1" type="radio" value="1"
                                        name="flight-stops"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                    <label for="1"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base">
                                        1 {{ $t("stop") }}
                                    </label>
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input v-model="selectedStops" @change="fetchFlights" id="2" type="radio" value="2"
                                        name="flight-stops"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                    <label for="2"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base">
                                        2 {{ $t("stops") }}
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("airline") }}
                        </p>
                        <ul>
                            <li v-for="item in availableAirlines" :key="item.id"
                                class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input v-model="selectedAirline" @change="fetchFlights" :id="item.name"
                                        type="checkbox" :value="item.id"
                                        class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" />
                                    <label :for="item.name"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base">
                                        <div class="w-6 h-6 rounded-full overflow-hidden">
                                            <img class="w-full h-full object-cover" :src="item.logo_url" alt="" />
                                        </div>
                                        {{ item.name }}
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-full">

            <div v-if="flights && !isLoading" class="mt-4">
                <Collapsible v-model:open="item.isOpen" v-for="(item, index) in flights?.itineraries" :key="index"
                    class="bg-white border-[.5px] hover:shadow-sm hover:scale-105 transition-all duration-150 h-fit cursor-pointer">

                    <div class="grid grid-cols-3 px-4 py-2">
                        <div class="grid grid-cols-1 content-center">
                            <div class="flex flex-col">
                                <img class="w-6 h-6 object-contain" :src="item.legs[0].stops[0].airline?.logo_url
                                    " alt="" />
                                <p class="text-sm p-0">
                                    {{
                                        item.legs[0].stops[0].airline?.name +
                                        " (" +
                                        item.legs[0].stops[0].airline
                                            ?.iata_code +
                                        ")"
                                    }}
                                </p>
                                <div class="font-light text-xs">
                                    {{ item.legs[0].stops[0].aircraft?.name }}
                                </div>
                                <div class="flex gap-2">
                                    <div
                                        class="inline-flex items-start rounded px-3 py-1 text-[10px]  bg-green-100 text-green-700 gap-1">
                                        <Users class="w-3 h-3 text-green-700" />
                                        <span class="font-light">{{ item?.passengerInfo[0]
                                            .fareComponents[0]
                                            .segments[0]
                                            .segment.seatsAvailable }} {{ seatsAvailable === 1 ? 'Seat' : 'Seats' }}
                                            Available</span>
                                    </div>

                                    <div class="inline-flex items-center rounded px-2 py-1 text-[10px] font-light text-white gap-1"
                                        :class="!item?.passengerInfo[0].nonRefundable ? 'bg-green-100 ' : 'bg-red-100 '">
                                        <SquareCheckBig class="w-3 h-3 text-primary"
                                            v-if="!item?.passengerInfo[0].nonRefundable" />
                                        <SquareX v-else class="w-3 h-3 text-red-500" />

                                        <span
                                            :class="!item?.passengerInfo[0].nonRefundable ? 'text-primary ' : 'text-red-500'"
                                            class="text-primary font-light text-[10px]">{{
                                                item?.passengerInfo[0].nonRefundable ?
                                                    'Non-Refundable' : 'Refundable' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="grid items-center justify-self-center ">
                            <div class="w-[250px] p-2 ">
                                <!-- Flight Duration -->
                                <div class="flex items-center justify-center">
                                    <span
                                        class="flex gap-2 text-xs text-muted-foreground font-light bg-gray-50 px-3 py-1 rounded">
                                        <span>
                                            {{
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .asHours() |
                                                    Math.floor) +
                                                " hr" +
                                                ((moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .asHours() |
                                                    Math.floor) !==
                                                    1
                                                    ? "s"
                                                    : "") +
                                                " " +
                                                moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .minutes() +
                                                " m" +
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .minutes() !== 1
                                                    ? ""
                                                    : "")
                                            }}
                                        </span>
                                    </span>
                                </div>

                                <!-- Flight Timeline -->
                                <div class="flex items-center gap-3">
                                    <span class="text-sm whitespace-nowrap text-gray-800 font-light">
                                        {{
                                            moment(
                                                item.legs[0].stops[0].departure
                                                    .time,
                                                "hh:mm",
                                            ).format("HH:mm")
                                        }}
                                    </span>
                                    <div class="w-full relative">
                                        <div
                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-3 h-3 bg-primary border-2 border-white ring-2 ring-primary/20">
                                        </div>
                                        <hr class="border-primary/30 border-dashed border-t-2" />
                                        <div
                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-3 h-3 bg-primary border-2 border-white ring-2 ring-primary/20">
                                        </div>
                                    </div>
                                    <span class="text-sm whitespace-nowrap text-gray-700 font-light">
                                        {{
                                            moment(
                                                item.legs[0].stops[
                                                    item.legs[0].stops.length -
                                                    1
                                                ].arrival.time,
                                                "hh:mm",
                                            ).format("HH:mm")
                                        }}
                                    </span>
                                </div>

                                <!-- Airport Codes and Stops -->
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-2 text-base font-bold text-gray-800">
                                        {{
                                            item.legs[0].stops[0].departure
                                                .iata_code
                                        }}
                                    </div>
                                    <div v-if="item.legs[0].stops.length > 1"
                                        class="text-xs bg-gray-50 px-2 py-1 rounded text-gray-500 font-light relative group cursor-help">
                                        <span>
                                            {{ item.legs[0].stops.length - 1 }}
                                            {{
                                                $t(
                                                    item.legs[0].stops
                                                        .length === 2
                                                        ? "stop"
                                                        : "stops",
                                                )
                                            }}
                                        </span>
                                        <div
                                            class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap z-10 shadow-lg">
                                            {{
                                                getLayoverInfo(
                                                    item.legs[0].stops,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div v-else
                                        class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded font-light">
                                        {{ $t("non_stop") }}
                                    </div>
                                    <div class="flex gap-2 text-xs font-light text-gray-800">
                                        {{
                                            item.legs[0].stops[
                                                item.legs[0].stops.length - 1
                                            ].arrival.iata_code
                                        }}
                                    </div>
                                </div>
                            </div>
                            <!-- <div class="w-[300px]">
                                <div class="flex items-center justify-center">
                                    <span class="flex gap-2 text-xs mb-2 text-muted-foreground font-medium">
                                        <span>
                                            {{
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .asHours() |
                                                    Math.floor) +
                                                " hr" +
                                                ((moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .asHours() |
                                                    Math.floor) !==
                                                    1
                                                    ? "s"
                                                    : "") +
                                                " " +
                                                moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .minutes() +
                                                " m" +
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "m",
                                                    )
                                                    .minutes() !== 1
                                                    ? ""
                                                    : "")
                                            }}
                                        </span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs whitespace-nowrap text-muted-foreground font-medium">
                                        {{
                                            moment(
                                                item.legs[0].stops[0].departure
                                                    .time,
                                                "hh:mm",
                                            ).format("HH:mm")
                                        }}
                                    </span>
                                    <div class="w-full relative">

                                        <div
                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                        </div>
                                        <hr class="border-black border-dashed" />
                                        <div
                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                        </div>
                                    </div>
                                    <span class="text-xs whitespace-nowrap text-muted-foreground font-medium">
                                        {{
                                            moment(
                                                item.legs[0].stops[
                                                    item.legs[0].stops.length -
                                                    1
                                                ].arrival.time,
                                                "hh:mm",
                                            ).format("HH:mm")
                                        }}
                                    </span>
                                </div>
                            
                                <div class="flex items-center justify-between">
                                    <div class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                        {{
                                            item.legs[0].stops[0].departure
                                                .iata_code
                                        }}
                                    </div>
                                    <div v-if="item.legs[0].stops.length > 1"
                                        class="text-xs text-muted-foreground font-medium relative group">
                                        <span>
                                            {{ item.legs[0].stops.length - 1 }}
                                            {{
                                                $t(
                                                    item.legs[0].stops
                                                        .length === 2
                                                        ? "stop"
                                                        : "stops",
                                                )
                                            }}
                                        </span>
                                        <div
                                            class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap">
                                            {{
                                                getLayoverInfo(
                                                    item.legs[0].stops,
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-muted-foreground font-medium">
                                        {{ $t("non_stop") }}
                                    </div>
                                    <div class="flex gap-2 text-xs text-muted-foreground font-medium">
                                        {{
                                            item.legs[0].stops[0].arrival
                                                .iata_code
                                        }}
                                    </div>
                                </div>
                            </div> -->
                        </div>

                        <div class="grid grid-cols-1 justify-items-end content-center gap-2">
                            <div class="flex items-center gap-2">
                                <div class="hidden">{{ passengerCount = (passengerCount = item.passengerInfo.reduce((total, p) => total + p.passengerNumber, 0)) }}</div> 
                               
                                <p class="text-sm font-normal">
                                    
                                    {{
                                        
                                        formatAmount(
                                            calculateFinalPrice(
                                                item.pricing.totalPrice -
                                                item.pricing.totalTaxAmount,
                                                (item.legs[0].stops[0].airline
                                                    ?.margin_amount * passengerCount),
                                                item.legs[0].stops[0].airline
                                                    ?.margin_type,
                                                item.legs[0].stops[0].airline
                                                    ?.amount_type,
                                            ) +
                                            item.pricing.totalTaxAmount +
                                            (parseFloat(
                                                agentData?.agent_data
                                                    ?.margin_amount,
                                            ) * passengerCount) +
                                            priceMargin,
                                        )
                                    }}
                                </p>
                            </div>

                            <div class="flex">

                                <button @click="
                                    $router.push({
                                        name: 'AgentFlightCheckout',
                                        query: {
                                            flight_id: item.id,
                                            price_margin: priceMargin || 0,
                                        },
                                    })
                                    "
                                    class="inline-flex items-center justify-center rounded text-white py-1 px-2 text-xs font-light  bg-primary hover:bg-green-700 hover:text-white">
                                    <span>{{ $t("book_now") }}</span>
                                </button>

                                <!-- <CollapsibleTrigger as-child>
                                    <Button variant="ghost">
                                        <ChevronDown class="w-5 h-5" />
                                    </Button>
                                </CollapsibleTrigger> -->
                            </div>
                            <p class="bg-green-100 rounded font-light text-xs px-2 py-1 text-primary text-center"
                                @click="openFlightDetails(item.id)">
                                Flight Details</p>
                        </div>
                    </div>

                    <CollapsibleContent>
                        <div>
                            <div v-for="(stop, stopIndex) in item.legs[0].stops" :key="stop.id"
                                class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50">
                                <div class="p-6 border-b-2 border-dashed">
                                    <div class="grid grid-cols-3 gap-x-3">
                                        <div class="text-start">
                                            <div class="flex items-center gap-x-3">
                                                <img class="w-8 h-8 rounded-full" :src="stop.airline?.logo_url
                                                    " alt="" />
                                                <span class="text-lg font-semibold">{{
                                                    stop.airline?.name
                                                }}</span>
                                            </div>

                                            <div>
                                                <span class="text-lg font-semibold">
                                                    {{
                                                        stop.departure.airport
                                                            .city_name
                                                    }}
                                                    <span class="font-medium text-muted-foreground">({{
                                                        stop.departure
                                                            .airport
                                                            .iata_code
                                                    }})</span>
                                                </span>
                                            </div>

                                            <div class="text-sm font-medium text-muted-foreground mb-2">
                                                <span>
                                                    {{ $t("aircraft") }}:
                                                    <span>{{
                                                        stop.aircraft?.name
                                                        }}</span>
                                                </span>
                                            </div>
                                            <div class="text-sm font-medium text-muted-foreground mb-2">
                                                <span>
                                                    {{ $t("terminal") }}:
                                                    <span>{{
                                                        stop.departure
                                                            .terminal ?? "N / A"
                                                    }}</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 items-center">
                                            <div class="w-[300px]">
                                                <div class="flex items-center gap-3">
                                                    <span
                                                        class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                        {{
                                                            moment(
                                                                stop.departure
                                                                    .time,
                                                                "hh:mm",
                                                            ).format("HH:mm")
                                                        }}
                                                    </span>

                                                    <div class="w-full relative">
                                                        <div
                                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                        </div>

                                                        <hr class="border-black border-dashed" />
                                                        <div
                                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                        </div>
                                                    </div>
                                                    <span
                                                        class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                        {{
                                                            moment(
                                                                stop.arrival
                                                                    .time,
                                                                "hh:mm",
                                                            ).format("HH:mm")
                                                        }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <div
                                                        class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                                        {{
                                                            stop.departure
                                                                .airport
                                                                .iata_code
                                                        }}
                                                    </div>
                                                    <div class="flex gap-2 text-sm text-muted-foreground font-medium">
                                                        {{
                                                            stop.arrival.airport
                                                                .iata_code
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end flex flex-col justify-center">
                                            <div>
                                                <span class="text-lg font-semibold">
                                                    {{
                                                        stop.arrival.airport
                                                            .city_name
                                                    }}
                                                    <span class="font-medium text-muted-foreground">({{
                                                        stop.arrival.airport
                                                            .iata_code
                                                    }})</span>
                                                </span>
                                            </div>
                                            <div class="text-sm font-medium text-muted-foreground mb-2">
                                                <span>
                                                    {{ $t("aircraft") }}:
                                                    <span>{{
                                                        stop.aircraft?.name
                                                        }}</span>
                                                </span>
                                            </div>
                                            <div class="text-sm font-medium text-muted-foreground mb-2">
                                                <span>
                                                    {{ $t("terminal") }}:
                                                    <span>{{
                                                        stop.arrival.terminal ??
                                                        "N / A"
                                                        }}</span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Add layover information -->
                                <div v-if="
                                    stopIndex <
                                    item.legs[0].stops.length - 1
                                "
                                    class="bg-gradient-to-r from-rose-100/50 to-teal-100/50 p-4 border-b-2 border-dashed">
                                    <div class="flex items-center justify-center">
                                        <ClockIcon class="w-5 h-5 text-primary mr-2" />
                                        <span class="text-sm font-bold text-primary">
                                            Layover:
                                            {{
                                                calculateLayover(
                                                    stop,
                                                    item.legs[0].stops[
                                                    stopIndex + 1
                                                    ],
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- return flight -->
                        <div v-for="(stop, stopIndex) in item.legs[1]?.stops" :key="stop.id"
                            class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50">
                            <div class="p-6 border-b-2 border-dashed">
                                <div class="grid grid-cols-3 gap-x-3">
                                    <div class="text-start">
                                        <div class="flex items-center gap-x-3">
                                            <img class="w-8 h-8 rounded-full" :src="stop.airline?.logo_url" alt="" />
                                            <span class="text-lg font-semibold">{{ stop.airline?.name }}</span>
                                        </div>
                                        <div>
                                            <span class="text-lg font-semibold">
                                                {{
                                                    stop.departure.airport
                                                        .city_name
                                                }}
                                                <span class="font-medium text-muted-foreground">({{
                                                    stop.departure.airport
                                                        .iata_code
                                                }})</span>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground mb-2">
                                            <span>
                                                {{ $t("aircraft") }}:
                                                <span>{{
                                                    stop.aircraft?.name
                                                    }}</span>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground mb-2">
                                            <span>
                                                {{ $t("terminal") }}:
                                                <span>{{
                                                    stop.departure.terminal ??
                                                    "N / A"
                                                    }}</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 items-center">
                                        <div class="w-[300px]">
                                            <div class="flex items-center gap-3">
                                                <span
                                                    class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                    {{
                                                        moment(
                                                            stop.departure.time,
                                                            "hh:mm",
                                                        ).format("HH:mm")
                                                    }}
                                                </span>
                                                <div class="w-full relative">
                                                    <div
                                                        class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                    </div>
                                                    <hr class="border-black border-dashed" />
                                                    <div
                                                        class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                    </div>
                                                </div>
                                                <span
                                                    class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                    {{
                                                        moment(
                                                            stop.arrival.time,
                                                            "hh:mm",
                                                        ).format("HH:mm")
                                                    }}</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                                    {{
                                                        stop.departure.airport
                                                            .iata_code
                                                    }}
                                                </div>
                                                <div class="flex gap-2 text-sm text-muted-foreground font-medium">
                                                    {{
                                                        stop.arrival.airport
                                                            .iata_code
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end flex flex-col justify-center">
                                        <div>
                                            <span class="text-lg font-semibold">
                                                {{
                                                    stop.arrival.airport
                                                        .city_name
                                                }}
                                                <span class="font-medium text-muted-foreground">({{
                                                    stop.arrival.airport
                                                        .iata_code
                                                }})</span>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground mb-2">
                                            <span>
                                                {{ $t("aircraft") }}:
                                                <span>{{
                                                    stop.aircraft?.name
                                                    }}</span>
                                            </span>
                                        </div>
                                        <div class="text-sm font-medium text-muted-foreground mb-2">
                                            <span>
                                                {{ $t("terminal") }}:
                                                <span>{{
                                                    stop.arrival.terminal ??
                                                    "N / A"
                                                    }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Add layover information for return flight -->
                            <div v-if="stopIndex < item.legs[1].stops.length - 1"
                                class="bg-gradient-to-r from-rose-100/50 to-teal-100/50 p-4 border-b-2 border-dashed">
                                <div class="flex items-center justify-center">
                                    <ClockIcon class="w-5 h-5 text-primary mr-2" />
                                    <span class="text-sm font-bold text-primary">
                                        Layover:
                                        {{
                                            calculateLayover(
                                                stop,
                                                item.legs[1].stops[
                                                stopIndex + 1
                                                ],
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Convert fare information and baggage details to tabs -->

                        <div class="bg-white overflow-hidden mx-auto p-2">
                            <div class="grid grid-cols-2 gap-2">
                                <!-- Fare Information Collapsible -->
                                <div class="border rounded-lg">
                                    <button @click="toggleFare"
                                        class="flex justify-between items-center w-full p-4 text-left hover:bg-gray-200 transition-colors duration-200">
                                        <HandCoins />
                                        <span class="text-md font-medium">Fare Information</span>
                                        <ChevronDownIcon :class="{
                                            'transform rotate-180':
                                                isFareOpen,
                                        }" class="w-5 h-5 transition-transform duration-200" />
                                    </button>
                                    <div v-show="isFareOpen" class="">
                                        <div v-for="(passenger, index
                                            ) in item.passengerInfo" :key="index"
                                            class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 overflow-hidden">
                                            <!-- Header -->
                                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-700">
                                                            Passenger
                                                        </span>
                                                        <span
                                                            class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">
                                                            {{
                                                                passengerCount = (passenger.passengerNumber)
                                                            }}
                                                            {{
                                                                passenger.passengerType
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Fare Details -->
                                            <div class="p-4 space-y-3">
                                                <!-- Base Fare -->
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm text-gray-600">Base Fare</span>
                                                    <div class="flex items-center">
                                                        <span class="text-sm font-medium">
                                                            
                                                            {{
                                                                formatAmount(
                                                                    calculateFinalPrice(
                                                                        passenger
                                                                            .passengerTotalFare
                                                                            .equivalentAmount *
                                                                        passenger.passengerNumber,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.margin_amount,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.margin_type,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.amount_type,
                                                                    ) +
                                                                    parseFloat(
                                                                        agentData
                                                                            ?.agent_data
                                                                            ?.margin_amount,
                                                                    ) +
                                                                    priceMargin,
                                                                )
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Tax Amount -->
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm text-gray-600">Taxes & Fees</span>
                                                    <div class="flex items-center">
                                                        <span class="text-sm font-medium">
                                                            {{
                                                                formatAmount(
                                                                    passenger
                                                                        .passengerTotalFare
                                                                        .totalTaxAmount *
                                                                    passenger.passengerNumber,
                                                                )
                                                            }}
                                                        </span>

                                                    </div>
                                                </div>

                                                <!-- Divider -->
                                                <div class="border-t border-dashed my-2"></div>

                                                <!-- Total Fare -->
                                                <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                                    <span class="text-sm font-medium text-gray-700">Total Amount</span>
                                                    <div class="flex items-center">
                                                        <span class="text-base font-bold text-primary">
                                                            {{
                                                                formatAmount(
                                                                    calculateFinalPrice(
                                                                        passenger
                                                                            .passengerTotalFare
                                                                            .equivalentAmount *
                                                                        passenger.passengerNumber,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.margin_amount,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.margin_type,
                                                                        item
                                                                            .legs[0]
                                                                            .stops[0]
                                                                            .airline
                                                                            ?.amount_type,
                                                                    ) +
                                                                    passenger
                                                                        .passengerTotalFare
                                                                        .totalTaxAmount *
                                                                    passenger.passengerNumber +
                                                                    parseFloat(
                                                                        agentData
                                                                            ?.agent_data
                                                                            ?.margin_amount,
                                                                    ) +
                                                                    priceMargin,
                                                                )
                                                            }}
                                                        </span>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Baggage Allowance Collapsible -->
                                <div class="border rounded-lg overflow-hidden">
                                    <button @click="toggleBaggage"
                                        class="flex justify-between items-center w-full p-4 text-left hover:bg-gray-200 transition-colors duration-200">
                                        <BriefcaseBusiness />
                                        <span class="text-md font-medium">Baggage Allowance</span>

                                        <ChevronDownIcon :class="{
                                            'transform rotate-180':
                                                isBaggageOpen,
                                        }" class="w-5 h-5 transition-transform duration-200" />
                                    </button>
                                    <div v-show="isBaggageOpen" class="">
                                        <div class="grid grid-cols-1 gap-2 p-2">
                                            <!-- Cabin Baggage -->
                                            <div class=" ">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-medium text-primary">
                                                        Cabin Baggage
                                                    </span>
                                                    <div class="text-sm font-semibold">
                                                        1 Piece(s), Total 7 Kg
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Check-in Baggage -->
                                            <div class="">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-sm font-medium text-primary mb-1">
                                                        Check-in Baggage
                                                    </span>
                                                    <div class="text-sm font-semibold">
                                                        {{
                                                            item
                                                                .passengerInfo[0]
                                                                .baggage[0]
                                                                .pieces || 1
                                                        }}
                                                        Piece(s), Total
                                                        {{
                                                            item
                                                                .passengerInfo[0]
                                                                .baggage[0]
                                                                .weight
                                                        }}
                                                        {{
                                                            item
                                                                .passengerInfo[0]
                                                                .baggage[0].unit
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>

            <div v-if="!isLoading && !flights?.itineraries"
                class="flex items-center justify-center p-12 bg-white rounded-lg border mt-8">
                <span>{{ $t("nothing_found") }}.</span>
            </div>
            <div v-if="isLoading" class="space-y-4 mt-8">
                <div v-for="i in 5" :key="i.id"
                    class="bg-white border w-full rounded-lg h-[200px] p-4 flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-x-3">
                            <Skeleton width="150px" height="30px" class="rounded-none bg-gray-300 mb-4" />
                        </div>
                        <Skeleton width="60px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="90px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="200px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="150px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                    </div>

                    <div class="flex flex-col items-end">
                        <Skeleton width="150px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="60px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="90px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="200px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                        <Skeleton width="150px" height="15px" class="rounded-none bg-gray-300 mb-4" />
                    </div>
                </div>
            </div>
        </div>
        <!-- Dialog Modal -->
        <div v-if="showDialog" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center justify-center">
            <div class="bg-white p-4 rounded-lg shadow-lg w-96 text-center">
                <h2 class="text-lg font-bold">Search Expired</h2>
                <p class="mt-2">
                    Your search data has expired. Click "OK" to refresh the
                    page.
                </p>
                <button @click="confirmReload" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded">
                    OK
                </button>
            </div>
        </div>
    </div>

    <Transition name="fade">
        <div v-if="isSideSheetOpen" class="fixed inset-0 bg-black bg-opacity-50 z-40" @click="isSideSheetOpen = false">
        </div>
    </Transition>


    <Transition name="slide">
        <div v-if="isSideSheetOpen"
            class="fixed top-0 right-0 h-full w-full md:w-[900px] bg-white shadow-lg z-50 overflow-y-auto">
            <div>
                <div>
                    <div class="p-2 max-w-sm bg-primary mt-4">
                        <span class="ml-8 text-xl text-white">Flight Details </span><span class="text-white">(
                            {{
                                selectedFlight.legs[0]?.stops[0].departure.airport
                                    .city_name
                            }}
                            to
                            {{
                                selectedFlight.legs[0]?.stops[
                                    selectedFlight.legs[0]?.stops.length - 1
                                ].arrival.airport.city_name
                            }}
                            )</span>
                    </div>
                    <div class="flex gap-4 items-center p-6">
                        <p class=" text-gray-500 font-medium">Departure: {{ selectedFlight?.dates[0].departureDate
                        }}
                        </p>


                        <div
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs  bg-indigo-100 text-indigo-700 gap-1">
                            <Users class="w-3 h-3 text-indigo-700" />
                            <span>{{ selectedFlight?.passengerInfo[0]
                                .fareComponents[0]
                                .segments[0]
                                .segment.seatsAvailable }} {{ seatsAvailable === 1 ? 'Seat' : 'Seats' }}
                                Available</span>
                        </div>
                        <div
                            class="inline-flex items-center rounded-full  px-3 py-1 text-xs bg-amber-100 text-amber-700 gap-1">
                            <Utensils class="w-3 h-3 text-amber" />
                            <span>{{ selectedFlight?.passengerInfo[0]
                                .fareComponents[0]
                                .segments[0]
                                .segment.mealCode == "M" ? "MEAL" : "NO-SNACK" }}</span>
                        </div>


                        <div class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold text-white gap-1"
                            :class="!selectedFlight?.passengerInfo[0].nonRefundable ? 'bg-green-100 ' : 'bg-red-100 '">
                            <SquareCheckBig class="w-4 h-4 text-primary"
                                v-if="!selectedFlight?.passengerInfo[0].nonRefundable" />
                            <SquareX v-else class="w-4 h-4 text-red-500" />

                            <span
                                :class="!selectedFlight?.passengerInfo[0].nonRefundable ? 'text-primary ' : 'text-red-500'"
                                class="text-primary font-light text-xs">{{
                                    selectedFlight?.passengerInfo[0].nonRefundable ?
                                        'Non-Refundable' : 'Refundable' }}</span>
                        </div>
                        <div
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs  bg-orange-100 text-orange-700 gap-1">
                            <Users class="w-3 h-3 text-orange-700" />
                            <span>{{ selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode
                                ===
                                "Y"
                                ? "Economy"
                                : selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode ===
                                    "S"
                                    ? "Premium Economy"
                                    : selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode ===
                                        "C"
                                        ? "Business Class"
                                        : selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode ===
                                            "J"
                                            ? "Premium Business"
                                            : selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode ===
                                                "F"
                                                ? "First Class"
                                                : selectedFlight?.passengerInfo[0].fareComponents[0].segments[0].segment.cabinCode ===
                                                    "P"
                                                    ? "Premium First"
                                                    : "Others" }}</span>

                        </div>


                    </div>
                    <div v-for="(stop, stopIndex) in selectedFlight.legs[0].stops" :key="stop.id"
                        class="bg-gradient-to-r from-rose-100/50 to-teal-100/50">


                        <div class="p-6 border-b-2 border-dashed">
                            <div class="grid grid-cols-3 gap-x-3">
                                <div class="text-start">
                                    <div class="flex items-center gap-x-3">
                                        <img class="w-8 h-8 rounded-full" :src="stop.airline?.logo_url
                                            " alt="" />
                                        <span class="text-lg font-semibold">{{
                                            stop.airline?.name
                                        }}</span>
                                    </div>

                                    <div>
                                        <span class="text-lg font-semibold">
                                            {{
                                                stop.departure.airport
                                                    .city_name
                                            }}
                                            <span class="font-medium text-muted-foreground">({{
                                                stop.departure
                                                    .airport
                                                    .iata_code
                                            }})</span>
                                        </span>
                                    </div>

                                    <div class="text-sm font-medium text-muted-foreground mb-2">
                                        <span>
                                            {{ $t("aircraft") }}:
                                            <span>{{
                                                stop.aircraft?.name
                                            }}</span>
                                        </span>
                                    </div>
                                    <div class="text-sm font-medium text-muted-foreground mb-2">
                                        <span>
                                            {{ $t("terminal") }}:
                                            <span>{{
                                                stop.departure
                                                    .terminal ?? "N / A"
                                            }}</span>
                                        </span>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 items-center">
                                    <div class="w-[300px]">
                                        <div class="flex items-center gap-3">
                                            <span class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                {{
                                                    moment(
                                                        stop.departure
                                                            .time,
                                                        "hh:mm",
                                                    ).format("HH:mm")
                                                }}
                                            </span>

                                            <div class="w-full relative">
                                                <div
                                                    class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                </div>

                                                <hr class="border-black border-dashed" />
                                                <div
                                                    class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                                </div>
                                            </div>
                                            <span class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                                {{
                                                    moment(
                                                        stop.arrival
                                                            .time,
                                                        "hh:mm",
                                                    ).format("HH:mm")
                                                }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                                {{
                                                    stop.departure
                                                        .airport
                                                        .iata_code
                                                }}
                                            </div>
                                            <div class="flex gap-2 text-sm text-muted-foreground font-medium">
                                                {{
                                                    stop.arrival.airport
                                                        .iata_code
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end flex flex-col justify-center">
                                    <div>
                                        <span class="text-lg font-semibold">
                                            {{
                                                stop.arrival.airport
                                                    .city_name
                                            }}
                                            <span class="font-medium text-muted-foreground">({{
                                                stop.arrival.airport
                                                    .iata_code
                                            }})</span>
                                        </span>
                                    </div>
                                    <div class="text-sm font-medium text-muted-foreground mb-2">
                                        <span>
                                            {{ $t("aircraft") }}:
                                            <span>{{
                                                stop.aircraft?.name
                                            }}</span>
                                        </span>
                                    </div>
                                    <div class="text-sm font-medium text-muted-foreground mb-2">
                                        <span>
                                            {{ $t("terminal") }}:
                                            <span>{{
                                                stop.arrival.terminal ??
                                                "N / A"
                                            }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Add layover information -->
                        <div v-if="
                            stopIndex <
                            selectedFlight.legs[0].stops.length - 1
                        " class="bg-gradient-to-r from-rose-100/50 to-teal-100/50 p-4 border-b-2 border-dashed">
                            <div class="flex items-center justify-center">
                                <ClockIcon class="w-5 h-5 text-primary mr-2" />
                                <span class="text-sm font-bold text-primary">
                                    Layover:
                                    {{
                                        calculateLayover(
                                            stop,
                                            selectedFlight.legs[0].stops[
                                            stopIndex + 1
                                            ],
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- return flight -->
                <div v-for="(stop, stopIndex) in selectedFlight.legs[1]?.stops" :key="stop.id"
                    class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50">
                    <div class="p-6 border-b-2 border-dashed">
                        <div class="grid grid-cols-3 gap-x-3">
                            <div class="text-start">
                                <div class="flex items-center gap-x-3">
                                    <img class="w-8 h-8 rounded-full" :src="stop.airline?.logo_url" alt="" />
                                    <span class="text-lg font-semibold">{{ stop.airline?.name }}</span>
                                </div>
                                <div>
                                    <span class="text-lg font-semibold">
                                        {{
                                            stop.departure.airport
                                                .city_name
                                        }}
                                        <span class="font-medium text-muted-foreground">({{
                                            stop.departure.airport
                                                .iata_code
                                        }})</span>
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-muted-foreground mb-2">
                                    <span>
                                        {{ $t("aircraft") }}:
                                        <span>{{
                                            stop.aircraft?.name
                                        }}</span>
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-muted-foreground mb-2">
                                    <span>
                                        {{ $t("terminal") }}:
                                        <span>{{
                                            stop.departure.terminal ??
                                            "N / A"
                                        }}</span>
                                    </span>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 items-center">
                                <div class="w-[300px]">
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                            {{
                                                moment(
                                                    stop.departure.time,
                                                    "hh:mm",
                                                ).format("HH:mm")
                                            }}
                                        </span>
                                        <div class="w-full relative">
                                            <div
                                                class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                            </div>
                                            <hr class="border-black border-dashed" />
                                            <div
                                                class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black">
                                            </div>
                                        </div>
                                        <span class="text-sm whitespace-nowrap text-muted-foreground font-medium">
                                            {{
                                                moment(
                                                    stop.arrival.time,
                                                    "hh:mm",
                                                ).format("HH:mm")
                                            }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                            {{
                                                stop.departure.airport
                                                    .iata_code
                                            }}
                                        </div>
                                        <div class="flex gap-2 text-sm text-muted-foreground font-medium">
                                            {{
                                                stop.arrival.airport
                                                    .iata_code
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex flex-col justify-center">
                                <div>
                                    <span class="text-lg font-semibold">
                                        {{
                                            stop.arrival.airport
                                                .city_name
                                        }}
                                        <span class="font-medium text-muted-foreground">({{
                                            stop.arrival.airport
                                                .iata_code
                                        }})</span>
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-muted-foreground mb-2">
                                    <span>
                                        {{ $t("aircraft") }}:
                                        <span>{{
                                            stop.aircraft?.name
                                        }}</span>
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-muted-foreground mb-2">
                                    <span>
                                        {{ $t("terminal") }}:
                                        <span>{{
                                            stop.arrival.terminal ??
                                            "N / A"
                                        }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Add layover information for return flight -->
                    <div v-if="stopIndex < selectedFlight.legs[1].stops.length - 1"
                        class="bg-gradient-to-r from-rose-100/50 to-teal-100/50 p-4 border-b-2 border-dashed">
                        <div class="flex items-center justify-center">
                            <ClockIcon class="w-5 h-5 text-primary mr-2" />
                            <span class="text-sm font-bold text-primary">
                                Layover:
                                {{
                                    calculateLayover(
                                        stop,
                                        selectedFlight.legs[1].stops[
                                        stopIndex + 1
                                        ],
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
                <!-- Convert fare information and baggage details to tabs -->
                <div class="p-2 max-w-sm bg-primary mt-4">
                    <span class="ml-8 text-xl text-white">Baggages Allowance </span>

                </div>
                <div>
                    <div class="grid grid-cols-1 gap-2 p-2 mx-8">
                        <!-- Cabin Baggage -->
                        <div class=" ">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-primary">
                                    Cabin Baggage
                                </span>
                                <div class="text-sm font-semibold">
                                    1 Piece(s), Total 7 Kg
                                </div>
                            </div>
                        </div>

                        <!-- Check-in Baggage -->
                        <div class="">
                            <div class="flex items-center justify-between ">
                                <span class="text-sm font-medium text-primary mb-1">
                                    Check-in Baggage
                                </span>
                                <div class="text-sm font-semibold">
                                    {{
                                        selectedFlight
                                            .passengerInfo[0]
                                            .baggage[0]
                                            .pieces || 1
                                    }}
                                    Piece(s), Total
                                    {{
                                        selectedFlight
                                            .passengerInfo[0]
                                            .baggage[0]
                                            .weight
                                    }}
                                    {{
                                        selectedFlight
                                            .passengerInfo[0]
                                            .baggage[0].unit
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 max-w-sm bg-primary mt-4">
                    <span class="ml-8 text-xl text-white">Fare Information </span>

                </div>
                <div>
                    <div v-for="(passenger, index) in selectedFlight.passengerInfo" :key="index"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 overflow-hidden">
                        <!-- Header -->
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-700">
                                        Passenger
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">
                                        {{
                                            passenger.passengerNumber
                                        }}
                                        {{
                                            passenger.passengerType
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Fare Details -->
                        <div class="p-4 space-y-3">
                            <!-- Base Fare -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Base Fare</span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium">
                                        {{
                                            formatAmount(
                                                calculateFinalPrice(
                                                    passenger
                                                        .passengerTotalFare
                                                        .equivalentAmount *
                                                    passenger.passengerNumber,
                                                    (selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.margin_amount* passenger.passengerNumber),
                                                    selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.margin_type,
                                                    selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.amount_type,
                                                ) +
                                                parseFloat(
                                                    (agentData
                                                        ?.agent_data
                                                        ?.margin_amount* passenger.passengerNumber),
                                                ) +
                                                priceMargin,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tax Amount -->
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Taxes & Fees</span>
                                <div class="flex items-center">
                                    <span class="text-sm font-medium">
                                        {{
                                            formatAmount(
                                                passenger
                                                    .passengerTotalFare
                                                    .totalTaxAmount *
                                                passenger.passengerNumber,
                                            )
                                        }}
                                    </span>

                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="border-t border-dashed my-2"></div>

                            <!-- Total Fare -->
                            <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                <span class="text-sm font-medium text-gray-700">Total Amount</span>
                                <div class="flex items-center">
                                    <span class="text-base font-bold text-primary">
                                        {{
                                            formatAmount(
                                                calculateFinalPrice(
                                                    (passenger
                                                        .passengerTotalFare
                                                        .equivalentAmount *
                                                    passenger.passengerNumber),
                                                    (selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.margin_amount * passenger.passengerNumber),
                                                    selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.margin_type,
                                                    selectedFlight
                                                        .legs[0]
                                                        .stops[0]
                                                        .airline
                                                        ?.amount_type,
                                                ) +
                                                passenger
                                                    .passengerTotalFare
                                                    .totalTaxAmount *
                                                passenger.passengerNumber +
                                                parseFloat(
                                                    (agentData
                                                        ?.agent_data
                                                        ?.margin_amount *
                                                        passenger.passengerNumber)
                                                        ,
                                                ) +
                                                priceMargin,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>



            </div>
        </div>
    </Transition>
</template>
<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.animate-fadeIn {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(5px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.date-input {
    width: 100%;
    height: 100%;
    padding: 12px 16px;
    font-size: 16px;
    font-family: 'Arial', sans-serif;
    color: #333;
    background-color: #f9f9f9;
    border: 2px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.date-input:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 8px rgba(0, 123, 255, 0.5);
    background-color: #fff;
}

.date-input:hover {
    border-color: #007bff;
}

.date-input::placeholder {
    color: #999;
}

.date-input::-webkit-calendar-picker-indicator {
    filter: invert(0.5);
    cursor: pointer;
}

.date-input::-webkit-calendar-picker-indicator:hover {
    filter: invert(0.3);
}
</style>

<style scoped>
/* Transition for the backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Transition for the side sheet */
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
