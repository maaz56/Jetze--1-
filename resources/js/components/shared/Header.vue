<script setup>
import { initFlowbite } from "flowbite";
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
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
    ChevronsDownIcon,
} from "lucide-vue-next";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from "@/components/ui/carousel";
import Autocomplete from "@/components/common/Autocomplete.vue";
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
import PromoSlider from "@/components/shared/PromoSlider.vue";
import { PlaneTakeoff, PlaneLanding } from "lucide-vue-next";
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
import { calculateFinalPrice } from "@/lib/utils.js";
import { useStore } from "vuex";
import {
    FETCH_AIRPORTS,
    FETCH_AGENT_DATA,
    FETCH_PROMO_IMAGES,
    FETCH_AIRLINES,
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
    CircleArrowDown,
} from "lucide-vue-next";
import { Teleport } from "vue";
import Calender from "@/components/common/Calender.vue";

const activeTab = ref("flights");

const tabs = [
    { id: "flights", name: "Flights", icon: '/plane.png'  },
    { id: "hotels", name: "Hotels", icon:  '/residential.png' },
    { id: "Umrah Packages", name: "Umrah Packages", icon:  '/package.png' },
];

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};

const activeTabConfig = computed(() =>
    tabs.find((tab) => tab.id === activeTab.value),
);

const isImageIcon = (icon) => typeof icon === "string";

const store = useStore();
const flightStore = useFlightStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const RECENT_SEARCHES_KEY = "recent_search_history";
const MAX_RECENT_SEARCHES = 4;

const flightType = ref("one-way");
const flights = computed(() => flightStore.flights);
const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const promoImages = computed(() => store.getters["promoImage/promoImageData"]);
const isLoading = computed(() => flightStore.isLoading);
const availableAirlines = computed(() => flightStore.availableAirlines);
const airports = computed(() => store.getters["airport/airports"]);
const headerDefaultAirportCodes = ["PEW","LHE","SKT","ISB","KHI","MUX","GWD"];
const airlines = computed(() => store.getters["airline/airlines"]);
const previousSearch = JSON.parse(localStorage.getItem("previous_search"));
const recentSearches = ref([]);
const dropdownPosition = ref();
const loading = ref(true);
const error = ref(null);
const supplierSearchQuery = ref("");
const showAirlineDropdown = ref(false);

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
const multiCityTrips = ref([
    { origin: null, destination: null, date: "" },
    { origin: null, destination: null, date: "" },
]);
const classType = ref("Y");
const adults = ref(1);
const children = ref(0);
const infants = ref(0);
const isPopoverOpen = ref(false);
const maxTravelers = 9;
const countdown = ref(null);
const timerInterval = ref(null);
const selectedStop = ref(null);
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

    initializeSearchParams();
};

const travelersSummary = computed(() => {
    if (!totalTravelers.value) return "";
    return totalTravelers.value === 1
        ? "1 Traveller"
        : `${totalTravelers.value} Travellers`;
});

const classLabel = computed(() => {
    const map = {
        Y: "Economy",
        S: "Premium Economy",
        C: "Business",
        F: "First Class",
    };
    return map[classType.value] || "Economy";
});

const setFlightType = (type) => {
    flightType.value = type;
    if (type === "one-way") {
        dateRange.value.end = null;
    }
    if (type === "multi-city") {
        dateRange.value.start = null;
        dateRange.value.end = null;
    }
    initializeSearchParams();
};

const activateReturnTrip = () => {
    if (flightType.value !== "return") {
        flightType.value = "return";
    }
    if (!dateRange.value.end) {
        const startDate = dateRange.value.start || todayDate.value;
        dateRange.value.end = moment(startDate)
            .add(1, "days")
            .format("YYYY-MM-DD");
    }
    initializeSearchParams();
};

const maxTotal = 9;
const maxAdults = computed(() => {
    return Math.max(1, maxTotal - children.value);
});

const maxChildren = computed(() => {
    return Math.max(0, maxTotal - adults.value);
});

const maxInfants = computed(() => {
    return adults.value;
});

function handleAdultChange(val) {
    // Ensure at least 1 adult
    const maxAllowed = Math.max(1, maxTotal - children.value);

    adults.value = Math.min(val, maxAllowed);

    // Infants cannot exceed adults
    if (infants.value > adults.value) {
        infants.value = adults.value;
    }

    clampTravelers();
}

function handleChildChange(val) {
    const maxAllowed = maxTotal - adults.value;
    children.value = Math.min(val, maxAllowed);

    clampTravelers();
}

function handleInfantChange(val) {
    const maxAllowed = adults.value;
    infants.value = Math.min(val, maxAllowed);

    clampTravelers();
}

function clampTravelers() {
    // Ensure at least 1 adult
    if (adults.value < 1) {
        adults.value = 1;
    }

    // Enforce total travellers rule (adult + child <= maxTotal)
    const totalTravellers = adults.value + children.value;

    if (totalTravellers > maxTotal) {
        children.value = maxTotal - adults.value;
    }

    // Infants are NOT counted but must be <= adults
    if (infants.value > adults.value) {
        infants.value = adults.value;
    }

    // No negatives (safety)
    children.value = Math.max(0, children.value);
    infants.value = Math.max(0, infants.value);
}

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

// Reactive state

// Airline list from Vuex store

// Filtered airlines
const filteredAirlines = computed(() => {
    const query = supplierSearchQuery.value.toLowerCase();
    if (!query) return airlines.value;
    return airlines.value.filter(
        (a) =>
            a.name.toLowerCase().includes(query) ||
            a.iata_code.toLowerCase().includes(query),
    );
});

// Stops options
const stopOptions = [
    { label: "Direct", value: 0 },
    { label: "1 Stop", value: 1 },
    { label: "2+ Stops", value: 2 },
    { label: "All", value: "all" },
];

// Fetch airlines from backend
const fetchAirlines = () => {
    showAirlineDropdown.value = true;
    store.dispatch("airline/" + FETCH_AIRLINES, {
        search: supplierSearchQuery.value,
    });
};

// Toggle airline selection (add/remove)
const toggleAirlineSelection = (airline) => {
    const exists = selectedAirline.value.find((a) => a.id === airline.id);
    if (exists) {
        showAirlineDropdown.value = false;
        selectedAirline.value = selectedAirline.value.filter(
            (a) => a.id !== airline.id,
        );
    } else {
        showAirlineDropdown.value = false;
        selectedAirline.value.push(airline);
    }
};

// Check if airline is selected
const isSelected = (airline) => {
    return selectedAirline.value.some((a) => a.iata_code === airline.iata_code);
};

// Remove airline tag
const removeAirline = (airline) => {
    selectedAirline.value = selectedAirline.value.filter(
        (a) => a.iata_code !== airline.iata_code,
    );
};

// Handle stop selection
const selectStop = (stop) => {
    selectedStops.value = stop;
};

const openDropdown = async (event) => {
    showAirlineDropdown.value = true;
    await nextTick();
    const rect = event.target.getBoundingClientRect();
    dropdownPosition.value = {
        position: "absolute",
        top: `${rect.bottom + window.scrollY}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${rect.width}px`,
    };
};
const initializeSearchParams = () => {
    const previousSearch =
        JSON.parse(localStorage.getItem("previous_search")) || {};
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
        if (
            !Array.isArray(trips) ||
            trips.some((trip) => typeof trip !== "object" || trip === null)
        ) {
            trips = [
                { origin: null, destination: null, date: todayDate.value },
                {
                    origin: null,
                    destination: null,
                    date: moment(todayDate.value)
                        .add(1, "days")
                        .format("YYYY-MM-DD"),
                },
            ];
        } else {
            trips = trips.map((trip, index) => ({
                origin: typeof trip.origin === "string" ? trip.origin : null,
                destination:
                    typeof trip.destination === "string"
                        ? trip.destination
                        : null,
                date:
                    typeof trip.date === "string" && trip.date
                        ? trip.date
                        : moment(todayDate.value)
                              .add(index, "days")
                              .format("YYYY-MM-DD"),
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
            todayDate.value;
        dateRange.value.end =
            dateRange.value.end ??
            route.query.return_date ??
            previousSearch.return_date ??
            (flightType.value === "return"
                ? moment(todayDate.value).add(1, "days").format("YYYY-MM-DD")
                : null);
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
};

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

const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(
        2,
        "0",
    )}`;
};

const confirmReload = () => {
    localStorage.removeItem("previous_search");
    showDialog.value = false;
    window.location.reload();
};

const addTrip = () => {
    const lastTripDate =
        multiCityTrips.value[multiCityTrips.value.length - 1]?.date ||
        todayDate.value;
    multiCityTrips.value = [
        ...multiCityTrips.value,
        {
            origin: null,
            destination: null,
            date: moment(lastTripDate).add(1, "days").format("YYYY-MM-DD"),
        },
    ];
};

const removeTrip = (index) => {
    if (multiCityTrips.value.length > 2) {
        multiCityTrips.value = multiCityTrips.value.filter(
            (_, i) => i !== index,
        );
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
            currencyCode: localStorage.getItem("currencyCode") || "PKR",
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
                selectedStops: selectedStops.value,
                selectedAirline: selectedAirline.value,
                timestamp: Date.now(),
                flightType: flightType.value,
                currencyCode: localStorage.getItem("currencyCode") || "PKR",
            };
        }
    }

    if (searchParams) {
        flightStore.fetchFlights(searchParams).then(() => {
            localStorage.setItem(
                "previous_search",
                JSON.stringify(searchParams),
            );
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
        searchParams.return_date =
            flightType.value === "return" ? dateRange.value.end : null;
    }

    localStorage.setItem("previous_search", JSON.stringify(searchParams));
    searchFlights();
};

const readRecentSearches = () => {
    try {
        const parsed = JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY));
        if (!Array.isArray(parsed)) return [];
        return parsed
            .filter((item) => item && typeof item === "object")
            .slice(0, MAX_RECENT_SEARCHES);
    } catch {
        return [];
    }
};

const writeRecentSearches = (searches) => {
    localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(searches));
    recentSearches.value = searches;
};

const createSearchSignature = (searchParams) =>
    JSON.stringify({
        flightType: searchParams.flightType,
        origin: searchParams.origin ?? null,
        destination: searchParams.destination ?? null,
        departure_date: searchParams.departure_date ?? null,
        return_date: searchParams.return_date ?? null,
        trips: searchParams.trips ?? null,
        cabin_class: searchParams.cabin_class,
        adults: searchParams.adults,
        children: searchParams.children,
        infants: searchParams.infants,
    });

const saveRecentSearch = (searchParams) => {
    const entry = {
        ...searchParams,
        savedAt: Date.now(),
        signature: createSearchSignature(searchParams),
    };
    const deduped = readRecentSearches().filter(
        (item) => item.signature !== entry.signature,
    );
    writeRecentSearches([entry, ...deduped].slice(0, MAX_RECENT_SEARCHES));
};

const getRecentSearchCities = (search) => {
    if (search.flightType === "multi-city" && Array.isArray(search.trips)) {
        return {
            from: search.trips[0]?.origin || "-",
            to: search.trips[search.trips.length - 1]?.destination || "-",
        };
    }
    return {
        from: search.origin || "-",
        to: search.destination || "-",
    };
};

const formatRecentSearchDate = (date) =>
    date ? moment(date).format("MMM D") : "";

const getRecentSearchDateLabel = (search) => {
    if (search.flightType === "multi-city" && Array.isArray(search.trips)) {
        const dates = search.trips
            .map((trip) => trip?.date)
            .filter((date) => typeof date === "string" && date);
        if (!dates.length) return "";
        const first = formatRecentSearchDate(dates[0]);
        const last = formatRecentSearchDate(dates[dates.length - 1]);
        return first === last ? first : `${first} - ${last}`;
    }

    const departure = formatRecentSearchDate(search.departure_date);
    const returnDate = formatRecentSearchDate(search.return_date);
    if (!departure) return "";
    if (!returnDate || search.flightType === "one-way") return departure;
    return `${departure} - ${returnDate}`;
};

const applyRecentSearch = (search) => {
    flightType.value = search.flightType ?? "one-way";
    classType.value = search.cabin_class ?? "Y";
    adults.value = Number(search.adults ?? 1);
    children.value = Number(search.children ?? 0);
    infants.value = Number(search.infants ?? 0);

    if (flightType.value === "multi-city") {
        multiCityTrips.value = Array.isArray(search.trips) ? search.trips : [];
    } else {
        origin.value = search.origin ?? null;
        destination.value = search.destination ?? null;
        dateRange.value.start = search.departure_date ?? todayDate.value;
        dateRange.value.end =
            flightType.value === "return" ? (search.return_date ?? null) : null;
    }

    localStorage.setItem(
        "previous_search",
        JSON.stringify({ ...search, timestamp: Date.now() }),
    );
    searchFlights();
};

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
    console.log(pnr);
    router.push({
        name: "PnrDetails",
        query: { pnr: pnr },
    });
}

function swapAirports() {
    const temp = origin.value;
    origin.value = destination.value;
    destination.value = temp;
}

function searchFlights() {
    let errors = [];

    if (flightType.value === "multi-city") {
        multiCityTrips.value.forEach((trip, index) => {
            if (!trip.origin)
                errors.push(`Please select an origin for trip ${index + 1}`);
            if (!trip.destination)
                errors.push(
                    `Please select a destination for trip ${index + 1}`,
                );
            if (!trip.date)
                errors.push(`Please select a date for trip ${index + 1}`);
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
        selectedStops: selectedStops.value,
        selectedAirline: selectedAirline.value,
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
    saveRecentSearch(searchParams);

    router.push({
        name: "FlightSearch",
        query: searchParams,
    });

    //fetchFlights();
}

const getDefaultValue = computed(() => (field) => {
    return route.query?.[field] ?? previousSearch?.[field] ?? "";
});

function resetTravelers() {
    adults.value = 1;
    children.value = 0;
    infants.value = 0;
    classType.value = "Y";
}

function applyChanges() {
    isPopoverOpen.value = false;
}

function applyDefaultAirportSelection() {
    if (!Array.isArray(airports.value) || airports.value.length === 0) return;
    const firstAirport = airports.value[0]?.iata_code || null;
    const secondAirport = airports.value[1]?.iata_code || firstAirport;

    if (!origin.value) {
        origin.value = firstAirport;
    }
    if (!destination.value) {
        destination.value = secondAirport;
    }
}

watch(
    airports,
    () => {
        applyDefaultAirportSelection();
    },
    { immediate: true },
);

watch(
    flightType,
    (newType) => {
        if (newType === "return" && !dateRange.value.end) {
            const startDate = dateRange.value.start || todayDate.value;
            dateRange.value.end = moment(startDate)
                .add(1, "days")
                .format("YYYY-MM-DD");
        }
        if (newType === "one-way") {
            dateRange.value.end = null;
        }
    },
    { immediate: true },
);

watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
    }
});

onMounted(() => {
    if (user.value?.id) {
        fetchAgent();
    }
    fetchAirlines();
    store.dispatch("airport/" + FETCH_AIRPORTS);
    if (
        (flightType.value === "multi-city" &&
            multiCityTrips.value.some(
                (trip) => trip.origin && trip.destination && trip.date,
            )) ||
        (origin.value && destination.value && dateRange.value.start)
    ) {
        fetchFlights();
    }
    initializeSearchParams();
    recentSearches.value = readRecentSearches();
    applyDefaultAirportSelection();
    if (!dateRange.value.start) {
        dateRange.value.start = todayDate.value;
    }
    if (flightType.value === "return" && !dateRange.value.end) {
        dateRange.value.end = moment(todayDate.value)
            .add(1, "days")
            .format("YYYY-MM-DD");
    }
});
</script>

<template>
    <div class="w-full flex justify-center items-center px-2 sm:px-0 sm:mt-12">
        <div class="w-full sm:max-w-fit border-b border-gray-200 p-1 sm:p-2 bg-white rounded overflow-x-auto scrollbar-hide">
            <div class="flex flex-row gap-1 sm:gap-4 justify-start min-w-max">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="setActiveTab(tab.id)"
                    :class="[
                        'relative flex flex-col items-center justify-center gap-2 min-w-[80px] sm:min-w-[110px] px-2 py-3 transition-all duration-300',
                        /* Remove background colors, just manage text color */
                        activeTab === tab.id ? 'text-primary' : 'text-gray-500 hover:text-gray-700'
                    ]"
                >
                    <img 
                        :src="tab.icon" 
                        :alt="tab.name"
                        :class="[
                            'w-8 h-8 object-contain transition-all duration-500',
                            /* Icon is colored when active, grayscale when inactive */
                            activeTab === tab.id ? 'scale-110' : 'grayscale opacity-60'
                        ]"
                    />
                    
                    <span class="whitespace-nowrap text-[10px] sm:text-md font-bold uppercase tracking-wider">
                        {{ $t(tab.name) }}
                    </span>

                    <div 
                        v-if="activeTab === tab.id" 
                        class="absolute -bottom-[2px] left-0 w-full h-1 bg-primary rounded-t-full transition-all duration-300"
                    ></div>
                </button>
            </div>
        </div>
    </div>
    <div
        :class="flightType === 'multi-city' ? 'h-auto min-h-[24rem]' : 'min-h-[24rem]'"
        class="bg-white shadow-2xl mt-4 rounded-2xl border border-gray-200 overflow-visible max-w-7xl mx-auto"
    >
        <!-- Top Navigation Tabs -->

        <!-- Content Area -->
        <div class="py-6 px-8">
            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center bg-gradient-to-r from-blue-50 to-purple-50 p-12 sm:p-24 rounded-2xl"
            >
                <div class="text-center">
                    <Spinner class="mx-auto mb-4 w-6 h-6 sm:w-8 sm:h-8" />
                    <p class="text-gray-900 font-medium text-sm sm:text-base">
                        Searching for the best flights...
                    </p>
                </div>
            </div>

            <!-- Flights Tab -->
            <div
                v-else-if="activeTab === 'flights'"
                class="animate-fadeIn w-full flex flex-col gap-2"
            >
                <!-- Trip Type Selection -->
                <div>
                    <h1
                        class="hidden sm:block sm:text-4xl text-gray-900 text-left font-semibold tracking-tight"
                    >
                        Discover Your Ideal Flight Adventure
                    </h1>
                    <p
                        class="hidden sm:block text-left sm:text-sm lg:text-lg text-gray-600 mb-2 font-light"
                    >
                        Effortlessly search, compare, and book from thousands of
                        global airlines for your next journey.
                    </p>
                </div>
                <div
                    class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 sm:gap-3"
                >
                    <!-- Trip Type Buttons -->
                    <div
                        class="flex w-full sm:w-auto bg-gray-100 rounded-md p-1
       flex-row sm:flex-row gap-1"

                    >
                        <!-- Round Trip -->
                        <button
                            @click="setFlightType('return')"
                            :class="[
                                'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                flightType === 'return'
                                    ? 'bg-white text-gray-900 border border-gray-200'
                                    : 'text-gray-600 hover:bg-white',
                            ]"
                        >
                            {{ $t("Round Trip") }}
                        </button>

                        <!-- One Way -->
                        <button
                            @click="setFlightType('one-way')"
                            :class="[
                                'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                flightType === 'one-way'
                                    ? 'bg-white text-gray-900 border border-gray-200'
                                    : 'text-gray-600 hover:bg-white',
                            ]"
                        >
                            {{ $t("One Way") }}
                        </button>

                        <!-- Multi-City -->
                        <button
                            @click="setFlightType('multi-city')"
                            :class="[
                                'w-full sm:w-auto h-11 sm:h-auto px-3 sm:px-4 py-2 rounded-md sm:rounded-[6px] text-sm sm:text-base transition flex items-center justify-center',
                                flightType === 'multi-city'
                                    ? 'bg-white text-gray-900 border border-gray-200'
                                    : 'text-gray-600 hover:bg-white',
                            ]"
                        >
                            {{ $t("Multi-City") }}
                        </button>
                    </div>
                </div>

                <!-- Main Search Form -->
                <div class="bg-white rounded-xl sm:pr-0 rtl:pr-0">
                    <div v-if="flightType !== 'multi-city'">
                        <div
                            class="overflow-hidden rounded-md border border-gray-200 bg-white"
                        >
                            <div
                                class="grid grid-cols-1 sm:grid-cols-[1.35fr_1.35fr_1.02fr_1.02fr_1.28fr] items-stretch"
                            >
                        <!-- From -->
                        <div class="booking-cell text-start relative w-full">
                            <label
                                class="block text-sm font-medium text-gray-700 sm:mb-1"
                                >{{ $t("FROM") }}</label
                            >
                            <Autocomplete
                                v-model="origin"
                                :default-value="
                                    route.query?.origin
                                        ? route.query?.origin
                                        : previousSearch?.origin
                                          ? previousSearch?.origin
                                          : ''
                                "
                                :placeholder="$t('origin')"
                                :source="airports"
                                :default-suggestions="headerDefaultAirportCodes"
                                class="w-full px-0 focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                            />
                            <button
                                @click="swapAirports"
                                type="button"
                                class="absolute -right-5 top-1/2 z-10 hidden w-10 h-10 -translate-y-1/2 bg-white border border-gray-200 text-gray-700 rounded-full sm:flex items-center justify-center hover:bg-gray-50 transition-colors shadow-md"
                            >
                                <svg
                                    class="w-5 h-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                                    ></path>
                                </svg>
                            </button>
                        </div>

                        <!-- To -->
                        <div class="booking-cell text-start relative w-full">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >{{ $t("TO") }}</label
                            >
                            <Autocomplete
                                v-model="destination"
                                :icon="'PlaneLanding'"
                                :default-value="
                                    route.query?.destination
                                        ? route.query?.destination
                                        : previousSearch?.destination
                                          ? previousSearch?.destination
                                          : ''
                                "
                                :placeholder="$t('destination')"
                                :source="airports"
                                :default-suggestions="headerDefaultAirportCodes"
                                class="w-full px-0 border-none focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                            />
                        </div>

                        <div class="booking-cell w-full text-start">
                            <div
                                class="flex sm:mt-0.5 justify-start items-center"
                            >
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Departure</label
                                >
                            </div>
                            <Calender
                                v-model="dateRange.start"
                                :minValue="new Date().toLocaleDateString('en-CA')"
                                class="w-full h-10 sm:h-auto"
                            />
                        </div>
                        <div class="booking-cell w-full text-start cursor-pointer" @click="activateReturnTrip">
                            <div
                                class="flex sm:mt-0.5 justify-start items-center"
                            >
                                <label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Return</label
                                >
                            </div>
                            <template v-if="flightType === 'return'">
                                <Calender
                                    v-model="dateRange.end"
                                    :minValue="
                                        dateRange.start || new Date().toLocaleDateString('en-CA')
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
                        </div>

                        <div class="booking-cell w-full text-start">
                            <label
                                class="block text-sm font-medium text-gray-700 mb-1"
                                >Travellers & Class</label
                            >
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
                                <PopoverContent class="w-80 p-6 rounded-lg border-0 shadow-xl">
                                    <div class="space-y-6">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                @click="classType = 'Y'"
                                                :class="[
                                                    'py-2 rounded-md text-sm font-medium transition uppercase',
                                                    classType === 'Y'
                                                        ? 'bg-primary text-white'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                ]"
                                            >
                                                {{ $t("economy") }}
                                            </button>
                                            <button
                                                @click="classType = 'S'"
                                                :class="[
                                                    'py-2 rounded-md text-sm font-medium transition uppercase',
                                                    classType === 'S'
                                                        ? 'bg-primary text-white'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                ]"
                                            >
                                                {{ $t("premium_economy") }}
                                            </button>
                                            <button
                                                @click="classType = 'C'"
                                                :class="[
                                                    'py-2 rounded-md text-sm font-medium transition uppercase',
                                                    classType === 'C'
                                                        ? 'bg-primary text-white'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                ]"
                                            >
                                                {{ $t("business") }}
                                            </button>
                                            <button
                                                @click="classType = 'F'"
                                                :class="[
                                                    'py-2 rounded-md text-sm font-medium transition uppercase',
                                                    classType === 'F'
                                                        ? 'bg-primary text-white'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                ]"
                                            >
                                                {{ $t("first class") }}
                                            </button>
                                        </div>
                                        <div class="space-y-5">
                                            <div class="flex justify-between items-center">
                                                <Label><b>Adult</b> <br>(12 Years) </Label>
                                                <NumberField class="w-1/2" id="adult-field-inline-return" v-model="adults" :max="maxAdults" @update:modelValue="handleAdultChange">
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <Label><b>Child</b> <br> (2-11 Years)</Label>
                                                <NumberField class="w-1/2" id="child-field-inline-return" v-model="children" :min="0" :max="maxChildren" @update:modelValue="handleChildChange">
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <Label><b>Infant</b> <br>(Under 2 Years)</Label>
                                                <NumberField class="w-1/2" id="infant-field-inline-return" v-model="infants" :min="0" :max="maxInfants" @update:modelValue="handleInfantChange">
                                                    <NumberFieldContent>
                                                        <NumberFieldDecrement />
                                                        <NumberFieldInput />
                                                        <NumberFieldIncrement />
                                                    </NumberFieldContent>
                                                </NumberField>
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <Button @click="resetTravelers" class="text-sm text-gray-700 font-medium hover:text-gray-900">
                                                Reset
                                            </Button>
                                            <Button @click="applyChanges" class="px-5 py-1 bg-primary text-white rounded-md font-sm">
                                                Apply
                                            </Button>
                                        </div>
                                    </div>
                                </PopoverContent>
                            </Popover>
                            </div>
                        </div>
                        </div>
                        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:mt-4">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <button
                                v-if="recentSearches.length"
                                    v-for="(search, index) in recentSearches"
                                    :key="search.signature || `${search.timestamp}-${index}`"
                                    type="button"
                                    @click="applyRecentSearch(search)"
                                    class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-white hover:text-primary"
                                >
                                    <ClockIcon class="h-3.5 w-3.5 text-primary" />
                                    <span>{{ getRecentSearchCities(search).from }}</span>
                                    <span>-&gt;</span>
                                    <span>{{ getRecentSearchCities(search).to }}</span>
                                    <span class="text-gray-500">
                                        {{ getRecentSearchDateLabel(search) }}
                                    </span>
                                </button>
                            </div>
                            <div class="w-full sm:w-48">
                                <button
                                    @click="searchFlights"
                                    class="w-full bg-gradient-to-r from-[#49a7ff] to-[#065af3] hover:brightness-105 rounded-full p-3 sm:p-4 text-white font-bold flex items-center justify-center gap-2 text-lg sm:text-2xl"
                                >
                                    <Search class="w-5 h-5 sm:w-6 sm:h-6" />
                                    <span
                                        v-if="!isLoading"
                                        class="rtl:text-right ltr:text-left"
                                    >
                                        {{ $t("search") }}
                                    </span>
                                    <span v-else class="flex items-center gap-2">
                                        <LoaderCircle
                                            class="w-4 h-4 sm:w-5 sm:h-5 animate-spin"
                                        />
                                        Loading...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Search Button -->

                    <!-- Multi-city Form -->
                    <div
                        v-else-if="flightType === 'multi-city'"
                        class="w-full flex flex-col sm:flex-row items-stretch"
                    >
                        <div class="w-full">
                            <div
                                v-for="(trip, index) in multiCityTrips"
                                :key="index"
                            >
                                <!-- Origin -->
                                <div class="font-normal text-start text-gray-700">
                                    Trip {{ index + 1 }}
                                </div>
                                <div
                                    class="grid grid-cols-1 sm:grid-cols-4 gap-2 border-gray-200 items-center"
                                >
                               
                                    <Autocomplete
                                        v-model="trip.origin"
                                        :default-value="
                                            route.query?.origin
                                                ? route.query?.origin
                                                : previousSearch?.origin
                                                  ? previousSearch?.origin
                                                  : ''
                                        "
                                        :placeholder="$t('origin')"
                                        :source="airports"
                                        :default-suggestions="
                                            headerDefaultAirportCodes
                                        "
                                        class="w-full px-0 border-none focus:outline-none focus:ring-0 text-base sm:text-lg font-semibold text-gray-900"
                                    />

                                    <!-- Destination -->
                                    <Autocomplete
                                        v-model="trip.destination"
                                        :icon="'PlaneLanding'"
                                        :default-value="
                                            route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                  ? previousSearch?.destination
                                                  : ''
                                        "
                                        :placeholder="$t('destination')"
                                        :source="airports"
                                        :default-suggestions="
                                            headerDefaultAirportCodes
                                        "
                                        class="w-full px-0 border-none focus:outline-none focus:ring-0 text-base sm:text-lg font-semibold text-gray-900"
                                    />

                                    <!-- Date -->
                                    <!-- <input
                                    type="date"
                                    v-model="trip.date"
                                    :min="index === 0 ? todayDate : multiCityTrips[index - 1]?.date || todayDate"
                                    class="w-full h-10 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-0 text-lg"
                                /> -->
                                    <div class="w-full mx-1 border-gray-300 rounded-md text-lg">
                                        <Calender
                                            v-model="trip.date"
                                            :minValue="
                                                index === 0
                                                    ? todayDate
                                                    : multiCityTrips[index - 1]
                                                          ?.date || todayDate
                                            "
                                            class="w-full border-none"
                                        />
                                    </div>
                                    <div v-if="index === 0" class="w-full text-start">
                                        
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
                                            <PopoverContent class="w-80 p-6 rounded-lg border-0 shadow-xl">
                                                <div class="space-y-6">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <button
                                                            @click="classType = 'Y'"
                                                            :class="[
                                                                'py-2 rounded-md text-sm font-medium transition uppercase',
                                                                classType === 'Y'
                                                                    ? 'bg-primary text-white'
                                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                            ]"
                                                        >
                                                            {{ $t("economy") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'S'"
                                                            :class="[
                                                                'py-2 rounded-md text-sm font-medium transition uppercase',
                                                                classType === 'S'
                                                                    ? 'bg-primary text-white'
                                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                            ]"
                                                        >
                                                            {{ $t("premium_economy") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'C'"
                                                            :class="[
                                                                'py-2 rounded-md text-sm font-medium transition uppercase',
                                                                classType === 'C'
                                                                    ? 'bg-primary text-white'
                                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                            ]"
                                                        >
                                                            {{ $t("business") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'F'"
                                                            :class="[
                                                                'py-2 rounded-md text-sm font-medium transition uppercase',
                                                                classType === 'F'
                                                                    ? 'bg-primary text-white'
                                                                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200',
                                                            ]"
                                                        >
                                                            {{ $t("first class") }}
                                                        </button>
                                                    </div>
                                                    <div class="space-y-5">
                                                        <div class="flex justify-between items-center">
                                                            <Label><b>Adult</b> <br>(12 Years) </Label>
                                                            <NumberField class="w-1/2" id="adult-field-inline-multicity" v-model="adults" :max="maxAdults" @update:modelValue="handleAdultChange">
                                                                <NumberFieldContent>
                                                                    <NumberFieldDecrement />
                                                                    <NumberFieldInput />
                                                                    <NumberFieldIncrement />
                                                                </NumberFieldContent>
                                                            </NumberField>
                                                        </div>
                                                        <div class="flex justify-between items-center">
                                                            <Label><b>Child</b> <br> (2-11 Years)</Label>
                                                            <NumberField class="w-1/2" id="child-field-inline-multicity" v-model="children" :min="0" :max="maxChildren" @update:modelValue="handleChildChange">
                                                                <NumberFieldContent>
                                                                    <NumberFieldDecrement />
                                                                    <NumberFieldInput />
                                                                    <NumberFieldIncrement />
                                                                </NumberFieldContent>
                                                            </NumberField>
                                                        </div>
                                                        <div class="flex justify-between items-center">
                                                            <Label><b>Infant</b> <br>(Under 2 Years)</Label>
                                                            <NumberField class="w-1/2" id="infant-field-inline-multicity" v-model="infants" :min="0" :max="maxInfants" @update:modelValue="handleInfantChange">
                                                                <NumberFieldContent>
                                                                    <NumberFieldDecrement />
                                                                    <NumberFieldInput />
                                                                    <NumberFieldIncrement />
                                                                </NumberFieldContent>
                                                            </NumberField>
                                                        </div>
                                                    </div>
                                                    <div class="flex justify-between">
                                                        <Button @click="resetTravelers" class="text-sm text-gray-700 font-medium hover:text-gray-900">
                                                            Reset
                                                        </Button>
                                                        <Button @click="applyChanges" class="px-5 py-1 bg-primary text-white rounded-md font-sm">
                                                            Apply
                                                        </Button>
                                                    </div>
                                                </div>
                                            </PopoverContent>
                                        </Popover>
                                    </div>
                                    <!-- Remove Button -->
                                    <Button
                                        v-if="index >= 2"
                                        @click="removeTrip(index)"
                                        class="text-background h-11 bg-primary hover:bg-primary w-full"
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </div>
                            <div
                                class="flex flex-col sm:flex-row justify-between items-center gap-2 p-4"
                            >
                                <Button
                                    @click="addTrip"
                                    class="text-sm bg-white border border-gray-200 text-gray-800 hover:bg-gray-100 w-full sm:w-auto"
                                >
                                    Add Another City
                                </Button>
                            </div>
                            <div class="flex justify-end pb-4 px-4">
                                <button
                                    @click="searchFlights"
                                    class="w-full sm:w-48 bg-gradient-to-r from-[#49a7ff] to-[#065af3] hover:brightness-105 rounded-full p-4 text-white font-bold flex items-center justify-center gap-2 text-lg sm:text-2xl"
                                >
                                    <Search class="w-5 h-5" />
                                    <span
                                        v-if="!isLoading"
                                        class="rtl:text-right ltr:text-left"
                                    >
                                        {{ $t("search") }}
                                    </span>
                                    <span
                                        v-else
                                        class="flex items-center gap-2"
                                    >
                                        <LoaderCircle
                                            class="w-5 h-5 animate-spin"
                                        />
                                        Loading...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Messages -->
                <div
                    v-if="inputErrors"
                    class="mt-6 bg-red-50 border border-red-200 rounded-xl p-4"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-5 h-5 text-red-600 mt-0.5">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div>
                            <h4
                                class="font-semibold text-red-800 mb-2 text-sm sm:text-base"
                            >
                                {{
                                    $t(
                                        "please_ensure_that_these_fields_are_filled_properly",
                                    )
                                }}
                            </h4>
                            <ul class="space-y-1 text-sm text-red-700">
                                <li
                                    v-for="error in inputErrors"
                                    :key="error"
                                    class="flex items-center gap-2"
                                >
                                    <div
                                        class="w-1.5 h-1.5 bg-red-500 rounded-full"
                                    ></div>
                                    {{ error }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import PNR Tab -->
            <div v-else-if="activeTab === 'importPnr'" class="animate-fadeIn">
                <div
                    class="bg-gradient-to-r from-gray-50 to-gray-100 rounded-2xl p-6 sm:p-8 text-center"
                >
                    <div class="max-w-md mx-auto">
                        <CircleArrowDown
                            class="w-12 h-10 sm:w-16 sm:h-16 text-blue-600 mx-auto mb-6"
                        />
                        <h3
                            class="text-xl sm:text-2xl font-bold text-gray-800 mb-4"
                        >
                            Import PNR
                        </h3>
                        <p class="text-white mb-6 sm:mb-8 text-sm sm:text-base">
                            Enter your PNR code to import booking details
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <Input
                                v-model="pnr"
                                type="text"
                                placeholder="Enter PNR Code"
                                class="flex-1 bg-white rounded-xl border-gray-300 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm sm:text-base"
                            />
                            <Button
                                @click="importPnr(pnr)"
                                class="bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-bold rounded-xl px-6 sm:px-8 py-3 shadow-lg text-sm sm:text-base"
                            >
                                Import
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Other Tabs (Coming Soon) -->
            <div v-else class="animate-fadeIn">
                <div
                    class="flex items-center justify-center h-52 sm:h-72 bg-gradient-to-br from-gray-100 via-gray-50 to-white rounded-3xl shadow-md border border-gray-200"
                >
                    <div class="text-center space-y-4">
                        <img
                            v-if="isImageIcon(activeTabConfig?.icon)"
                            :src="activeTabConfig?.icon"
                            :alt="activeTabConfig?.name || 'tab icon'"
                            class="w-14 h-14 sm:w-20 sm:h-20 object-contain mx-auto transition-transform duration-300 hover:scale-110"
                        />
                        <component
                            v-else
                            :is="activeTabConfig?.icon"
                            class="w-14 h-14 sm:w-20 sm:h-20 text-gray-400 mx-auto transition-transform duration-300 hover:scale-110"
                        />
                        <h3
                            class="text-lg sm:text-2xl font-semibold text-gray-700 tracking-wide"
                        >
                            {{ activeTabConfig?.name }}
                        </h3>
                        <a
                           
                            target="_blank"
                        >
                            <Button
                                class=""
                            >
                               Comming Soon
                            </Button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-fadeIn {
    animation: fadeIn 0.5s ease-in-out;
}

.booking-cell {
    @apply relative min-h-[92px] border-b border-gray-200 px-4 py-3 sm:border-b-0 sm:border-r;
}

.booking-cell:last-child {
    @apply sm:border-r-0;
}

.booking-cell :deep(.min-h-\[110px\]) {
    min-height: 58px !important;
    padding: 0 !important;
}

.booking-cell :deep(.h-\[110px\]) {
    height: 58px !important;
    min-height: 58px !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

.booking-cell :deep(input) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 1.85rem !important;
}

.booking-cell :deep(.dropdown span.mb-1) {
    display: none;
}

.booking-cell :deep(.dropdown .pointer-events-none) {
    padding-top: 0.6rem;
}

.booking-cell :deep(.dropdown h2) {
    font-size: 1.45rem;
    line-height: 1.75rem;
}

.booking-cell :deep(.dropdown p) {
    margin-top: 0.15rem;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Custom date input styling */
input[type="date"] {
    position: relative;
}

input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    filter: invert(0.6);
    transition: filter 0.2s;
}

input[type="date"]::-webkit-calendar-picker-indicator:hover {
    filter: invert(0.4);
}

/* Smooth transitions for all interactive elements */
button,
input,
select {
    transition: all 0.3s ease;
}

/* Hide scrollbar but allow scrolling */
.scrollbar-hide {
    -ms-overflow-style: none;
    /* IE & Edge */
    scrollbar-width: none;
    /* Firefox */
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
    /* Chrome, Safari */
}

/* Custom scrollbar for popover content */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Radio button styling */
input[type="radio"] {
    accent-color: #3b82f6;
}

/* Checkbox styling */
input[type="checkbox"] {
    accent-color: #3b82f6;
}
</style>
