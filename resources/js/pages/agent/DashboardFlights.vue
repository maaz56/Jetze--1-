<script setup>
import AnnouncementBar from "@/components/common/AnnouncementBar.vue";
import FlightFilterCard from "@/components/common/FlightFilterCard.vue";
import Spinner from "@/components/common/Spinner.vue";

import PromoSlider from "@/components/shared/PromoSlider.vue";
import { Button } from "@/components/ui/button";
import Input from "@/components/ui/input/Input.vue";

import { calculateLayover } from "@/lib/utils";
import GroupTicketsMain from "@/pages/GroupTickets.vue";
import HolidayPackages from "@/pages/Holidays.vue";
import HotelSearch from "@/pages/HotelSearch.vue";
import TravelInsurance from "@/pages/TravelInsurance.vue";
import UmraPackages from "@/pages/UmraPackages.vue";
import Visa from "@/pages/Visas.vue";
import { FETCH_AGENT_DATA, FETCH_AIRPORTS, FETCH_PROMO_IMAGES } from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";
import { FileCheck2, Hotel, Plane, School, ShieldCheck, Sun, Users2, Zap } from "lucide-vue-next";
import moment from "moment";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";

const activeTab = ref("flights");
const tabs = [
    { id: "flights", name: "Flights", icon: Plane },
   
    // { id: "hotels", name: "Hotels", icon: Hotel },
    // { id: "visas", name: "Visas", icon: FileCheck2 },
    // { id: "holidays", name: "Holidays", icon: Sun },
    // { id: "umrah-packages", name: "Umrah Packages", icon: School },
    // { id: "travel-insurance", name: "Travel Insurance", icon: ShieldCheck },
    // { id: "group-tickets", name: "Group Tickets", icon: Users2 },
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
const dateRange = ref({ start: "", end: "" });
const multiCityTrips = ref([
    { origin: null, destination: null, date: "" },
    { origin: null, destination: null, date: "" },
]);
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

const totalTravelers = computed(
    () => adults.value + parseInt(children.value) + parseInt(infants.value),
);

const maxValueAdults = computed(
    () => maxTravelers - (children.value + infants.value),
);
const maxValueChildren = computed(
    () => maxTravelers - (adults.value + infants.value),
);
const maxValueInfants = computed(
    () => Math.min(adults.value, maxTravelers - (adults.value + children.value)),
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
    updateLocalStorage();
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
    const previousSearch = JSON.parse(localStorage.getItem("previous_search")) || {};
    const now = Date.now();

    if (
        previousSearch.timestamp &&
        now - previousSearch.timestamp > 15 * 60 * 1000
    ) {
        localStorage.removeItem("previous_search");
        showDialog.value = true;
        return;
    }

    flightType.value =
        flightType.value ??
        route.query.flightType ??
        previousSearch.flightType ??
        "one-way";

    if (flightType.value === "multi-city") {
        let trips = route.query.trips ?? previousSearch.trips;
        if (typeof trips === "string") {
            if (trips === "[object Object]") {
                trips = undefined; // Ignore invalid data
            } else {
                try {
                    trips = JSON.parse(trips);
                } catch (e) {
                    console.error("Failed to parse trips:", e);
                    trips = undefined;
                }
            }
        }
        if (!Array.isArray(trips) || trips.some(trip => typeof trip !== "object" || trip === null)) {
            trips = [
                { origin: null, destination: null, date: "" },
                { origin: null, destination: null, date: "" },
            ];
        } else {
            trips = trips.map(trip => ({
                origin: typeof trip.origin === "string" ? trip.origin : null,
                destination: typeof trip.destination === "string" ? trip.destination : null,
                date: typeof trip.date === "string" ? trip.date : "",
            }));
        }
        multiCityTrips.value = trips;
    } else {
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
            "";
        dateRange.value.end =
            dateRange.value.end ??
            route.query.return_date ??
            previousSearch.return_date ??
            "";
    }

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

    startCountdown(15 * 60 * 1000 - (now - previousSearch.timestamp));
};

const startCountdown = (remainingTime) => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    countdown.value = formatTime(remainingTime);

    timerInterval.value = setInterval(() => {
        remainingTime -= 1000;
        if (remainingTime <= 0) {
            localStorage.removeItem("previous_search");
            showDialog.value = true;
        } else {
            countdown.value = formatTime(remainingTime);
        }
    }, 1000);
};

const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
};

const confirmReload = () => {
    localStorage.removeItem("previous_search");
    showDialog.value = false;
    window.location.reload();
};

const addTrip = () => {
    multiCityTrips.value = [
        ...multiCityTrips.value,
        { origin: null, destination: null, date: "" },
    ];
};

const removeTrip = (index) => {
    if (multiCityTrips.value.length > 2) {
        multiCityTrips.value = multiCityTrips.value.filter((_, i) => i !== index);
    }
};

const fetchFlights = () => {
    let searchParams;
    if (flightType.value === "multi-city") {
        searchParams = {
            trips: multiCityTrips.value,
            cabin_class: classType.value,
            adults: adults.value,
            children: children.value,
            infants: infants.value,
            stops: selectedStops.value,
            airline: selectedAirline.value,
            timestamp: Date.now(),
            flightType: flightType.value,
        };
    } else {
        if (origin.value && destination.value && dateRange.value.start) {
            searchParams = {
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
                timestamp: Date.now(),
                flightType: flightType.value,
            };
        }
    }
    if (searchParams) {
        flightStore.fetchFlights(searchParams).then(() => {
            localStorage.setItem("previous_search", JSON.stringify(searchParams));
            localStorage.setItem("last_flight_results", JSON.stringify(flightStore.flights));
        });
    }
};

const getLayoverInfo = (stops) => {
    if (stops.length <= 1) return "";
    let layoverInfo = [];
    for (let i = 0; i < stops.length - 1; i++) {
        const layoverTime = calculateLayover(stops[i], stops[i + 1]);
        layoverInfo.push(`${stops[i].arrival.airport.city_name}: ${layoverTime}`);
    }
    return layoverInfo.join(", ");
};

const updateLocalStorage = () => {
    const searchParams = {
        flightType: flightType.value,
        cabin_class: classType.value,
        adults: adults.value,
        children: children.value,
        infants: infants.value,
        stops: selectedStops.value,
        airline: selectedAirline.value,
        timestamp: Date.now(),
    };

    if (flightType.value === "multi-city") {
        searchParams.trips = multiCityTrips.value;
    } else {
        searchParams.origin = origin.value;
        searchParams.destination = destination.value;
        searchParams.departure_date = dateRange.value.start;
        searchParams.return_date = flightType.value === "return" ? dateRange.value.end : null;
    }

    localStorage.setItem("previous_search", JSON.stringify(searchParams));
};

const searchFlights = () => {
    let errors = [];
    if (flightType.value === "multi-city") {
        multiCityTrips.value.forEach((trip, index) => {
            if (!trip.origin) errors.push(`Please select an origin for trip ${index + 1}`);
            if (!trip.destination) errors.push(`Please select a destination for trip ${index + 1}`);
            if (!trip.date) errors.push(`Please select a date for trip ${index + 1}`);
        });
    } else {
        if (!origin.value) errors.push("Please select an Origin");
        if (!destination.value) errors.push("Please select a Destination");
        if (!dateRange.value.start) errors.push("Please select a Date");
    }

    if (errors.length > 0) {
        inputErrors.value = errors;
        return;
    }

    inputErrors.value = null;

    const searchParams = {
        flightType: flightType.value,
        cabin_class: classType.value,
        adults: adults.value,
        children: children.value,
        infants: infants.value,
        timestamp: Date.now(),
    };

    if (flightType.value === "multi-city") {
        searchParams.trips = multiCityTrips.value;
    } else {
        searchParams.origin = origin.value;
        searchParams.destination = destination.value;
        searchParams.departure_date = dateRange.value.start;
        searchParams.return_date =
            flightType.value === "return" ? dateRange.value.end : null;
    }

    localStorage.setItem("previous_search", JSON.stringify(searchParams));

    router.push({
        name: "Flights",
        query: searchParams,
    });


    fetchFlights();
};

watch(
    () => route.query,
    (newQuery) => {
        flightType.value = newQuery.flightType || flightType.value;
        if (flightType.value === "multi-city") {
            let trips = newQuery.trips;
            if (typeof trips === "string") {
                if (trips === "[object Object]") {
                    trips = undefined;
                } else {
                    try {
                        trips = JSON.parse(trips);
                    } catch (e) {
                        console.error("Failed to parse query.trips:", e);
                        trips = undefined;
                    }
                }
            }
            if (!Array.isArray(trips) || trips.some(trip => typeof trip !== "object" || trip === null)) {
                trips = [
                    { origin: null, destination: null, date: "" },
                    { origin: null, destination: null, date: "" },
                ];
            } else {
                trips = trips.map(trip => ({
                    origin: typeof trip.origin === "string" ? trip.origin : null,
                    destination: typeof trip.destination === "string" ? trip.destination : null,
                    date: typeof trip.date === "string" ? trip.date : "",
                }));
            }
            multiCityTrips.value = trips;
        } else {
            origin.value = newQuery.origin || origin.value;
            destination.value = newQuery.destination || destination.value;
            dateRange.value.start = newQuery.departure_date || dateRange.value.start;
            dateRange.value.end = newQuery.return_date || dateRange.value.end;
        }
        classType.value = newQuery.cabin_class || classType.value;
        adults.value = parseInt(newQuery.adults) || adults.value;
        children.value = parseInt(newQuery.children) || children.value;
        infants.value = parseInt(newQuery.infants) || infants.value;
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
    endDateMin.value = dateRange.value.start || todayDate.value;
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

function changeMultiCityDate(index, direction) {
    const currentDate = moment(multiCityTrips.value[index].date);
    multiCityTrips.value[index].date =
        direction === "next"
            ? currentDate.add(1, "days").format("YYYY-MM-DD")
            : currentDate.subtract(1, "days").format("YYYY-MM-DD");
}

function importPnr(pnr) {
    router.push({
        name: "PnrDetails",
        query: { pnr: pnr },
    });
}

onMounted(() => {
    if (user.value?.id) {
        fetchAgent();
    }
    store.dispatch("airport/" + FETCH_AIRPORTS);
    initializeSearchParams();
    resetFlightParams();
    if (
        (flightType.value === "multi-city" &&
            multiCityTrips.value.some(trip => trip.origin && trip.destination && trip.date)) ||
        (origin.value && destination.value && dateRange.value.start)
    ) {
        fetchFlights();
    }
});
const modelValue = ref({
      flightType: 'one-way',
      countdownFor: 0,
      adult: 1,
      child: 0,
      infant: 0,
      classType: '',
      origin: '',
      destination: '',
      dateRange: {
        start: null,
        end: null
      },
      multiCityTrips: [{ origin: null, destination: null, date: "" },
    { origin: null, destination: null, date: "" }]
    })
    watch(()=> modelValue.value.flightType, (newVal)=>{
        if(newVal == 'single'){
            modelValue.value.dateRange.end = null;
        } else if(newVal == 'multi-city'){
            modelValue.value.dateRange.start = null;
            modelValue.value.dateRange.end = null;
        }
        initializeSearchParams();
    })
    const setupFlightsParams = () => {
       flightType.value = modelValue.value.flightType;
       adults.value = modelValue.value.adult;
       children.value = modelValue.value.child;
       infants.value = modelValue.value.infant;
       classType.value = modelValue.value.classType;
       origin.value = modelValue.value.origin;
       destination.value = modelValue.value.destination;
       dateRange.value = modelValue.value.dateRange;
       multiCityTrips.value = modelValue.value.multiCityTrips;
         searchFlights();
        // You can add additional logic here to handle the search action
    };
    const resetFlightParams = () => {
        modelValue.value = {
            flightType: flightType.value,
            adult: adults.value,
            child: children.value,
            infant: infants.value,
            classType: classType.value,
            origin: origin.value,
            destination: destination.value,
            dateRange: dateRange.value,
            multiCityTrips: multiCityTrips.value
        };
    };
</script>

<template>


    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
       
        <!-- Tabs Navigation -->
        <!-- <div class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
            <button v-for="tab in tabs" :key="tab.id" @click="setActiveTab(tab.id)"
                class="flex flex-col items-center justify-center min-w-[100px] py-4 px-6 transition-colors duration-200 whitespace-nowrap"
                :class="[
                    activeTab === tab.id
                        ? 'text-gray-600 border-b-2 border-gray-600 font-medium'
                        : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50',
                ]">
                <component :is="tab.icon" :class="[
                    'w-5 h-5 mb-1',
                    activeTab === tab.id ? 'text-gray-600' : 'text-gray-500'
                ]" />
                <span class="text-sm">{{ tab.name }}</span>
            </button>
        </div> -->
        <!-- Tab Content -->
        <div class="p-4">
            <!-- Flights Tab Content -->
            <div v-if="activeTab === 'flights'" class="animate-fadeIn">
                <div>
                    <div v-if="isLoading" class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                        <Spinner />
                    </div>
                    <div v-else>
                        <FlightFilterCard :countdown="countdown" v-model="modelValue" @search="setupFlightsParams"> 
        </FlightFilterCard>
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
                    <Button @click="importPnr(pnr)">Import PNR</Button>

                </div>
            </div>


            <!-- Hotels Tab Content -->
            <div v-else-if="activeTab === 'hotels'" class="animate-fadeIn">
                <HotelSearch />
            </div>
            <!-- visas Tab Content -->
            <div v-else-if="activeTab === 'visas'" class="animate-fadeIn">
                <Visa />
            </div>
            <!-- holidays Tab Content -->
            <div v-else-if="activeTab === 'holidays'" class="animate-fadeIn">
                <HolidayPackages />
            </div>
            <!-- umrah-packages Tab Content -->
            <div v-else-if="activeTab === 'umrah-packages'" class="animate-fadeIn">
                <UmraPackages />
            </div>
            <!-- travel-insurance Tab Content -->
            <div v-else-if="activeTab === 'travel-insurance'" class="animate-fadeIn">
                <TravelInsurance />
            </div>
            <!-- group-tickets Tab Content -->
            <div v-else-if="activeTab === 'group-tickets'" class="animate-fadeIn">
                <GroupTicketsMain />
            </div>







        </div>
    </div>
    <div>
        <AnnouncementBar />
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
    border-color: #a89666;
    box-shadow: #a89666;
    background-color: #fff;
}

.date-input:hover {
    border-color: #a89666;
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
