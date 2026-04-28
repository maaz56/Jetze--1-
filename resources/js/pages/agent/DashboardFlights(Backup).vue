<script setup>
import Autocomplete from "@/components/common/Autocomplete.vue";
import Spinner from "@/components/common/Spinner.vue";
import PromoSlider from "@/components/shared/PromoSlider.vue";
import { Button } from "@/components/ui/button";
import Card from "@/components/ui/card/Card.vue";
import Input from "@/components/ui/input/Input.vue";
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
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    today
} from "@internationalized/date";
import {
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    Zap
} from "lucide-vue-next";
import moment from "moment";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

import {
    calculateLayover
} from "@/lib/utils";
import {
    FETCH_AGENT_DATA,
    FETCH_AIRPORTS,
    FETCH_PROMO_IMAGES
} from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";
import {
    Building2,
    Car,
    CircleArrowDown,
    Compass,
    Plane,
    Ticket
} from 'lucide-vue-next';
import { useStore } from "vuex";

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
const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const promoImages = computed(() => store.getters["promoImage/promoImageData"]);


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
const pnr = ref(null);

function fetchPromoImages() {
    store.dispatch("promoImage/" + FETCH_PROMO_IMAGES);
}
// Computed property to calculate the total travelers
const totalTravelers = computed(
    () => adults.value + parseInt(children.value) + parseInt(infants.value),
);


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
                                                " :placeholder="$t('origin')" :source="airports" :icon="'PlaneTakeoff'" />
                                            <Autocomplete v-model="destination" label="To" :default-value="route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                    ? previousSearch?.destination
                                                    : ''
                                                " :placeholder="$t('destination')" :source="airports" :icon="'PlaneLanding'" />


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
                                                " :placeholder="$t('origin')" :source="airports" :icon="'PlaneTakeoff'" />
                                            <Autocomplete v-model="destination" label="To" :default-value="route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                    ? previousSearch?.destination
                                                    : ''
                                                " :placeholder="$t('destination')" :source="airports" :icon="'PlaneLanding'" />
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
    <div>

        <PromoSlider />
    </div>
    
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
