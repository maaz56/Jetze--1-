<script setup>
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
import HotelSearchHeader from "@/components/common/HotelSearchHeader.vue";
import moment from "moment";
import { ClockIcon } from "lucide-vue-next";
import {
    cn,
    formatAmount,
    formatDuration,
    calculateLayover,
    getSelectedCurrencyCode,
} from "@/lib/utils";
import { useFlightStore } from "@/services/stores/flight";
import { useAuthStore } from "@/services/stores/auth";
import Accordion from "@/components/ui/accordion/Accordion.vue";
import { calculateFinalPrice } from "@/lib/utils.js";
import { useStore } from "vuex";
import {
    FETCH_AGENT_DATA,
    FETCH_PROMO_IMAGES,
    FETCH_AIRLINES,
    FETCH_AIRPORTS,
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
    ArrowLeftRight,
    ArrowRight,
    Route,
    Plus,
    Sparkles,
} from "lucide-vue-next";
import { Teleport } from "vue";
import Calender from "@/components/common/Calender.vue";

const activeTab = ref("flights");

const tabs = [
    { id: "flights", name: "Flights", iconComponent: Plane, icon: '/plane.png' },
    { id: "hotels", name: "Hotels", iconComponent: Building2, icon: '/residential.png' },
    { id: "holidays", name: "Holidays", iconComponent: Compass, icon: '/holidays.png' },
];

const setActiveTab = (tabId) => {
    if (tabId === "hotels") {
        router.push({ name: "HotelSearch" });
        return;
    }

    if (tabId === "holidays") {
        router.push({ name: "HolidayPackages" });
        return;
    }

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
const ONWARD_DATE_MIN_ERROR = "OnwardDate cannot be less than Today";

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

const normalizeDateValue = (date) => {
    if (!date) return "";
    if (typeof date === "string") return date.slice(0, 10);
    if (date instanceof Date && !Number.isNaN(date.getTime())) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");
        return `${year}-${month}-${day}`;
    }
    return "";
};

const isDateBeforeToday = (date) => {
    const normalizedDate = normalizeDateValue(date);
    return Boolean(normalizedDate && normalizedDate < todayDate.value);
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
            currencyCode: getSelectedCurrencyCode(),
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
                currencyCode: getSelectedCurrencyCode(),
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
            if (trip.origin && trip.origin === trip.destination)
                errors.push("Origin and destination cannot be the same airport");
            if (!trip.date)
                errors.push(`Please select a date for trip ${index + 1}`);
            else if (isDateBeforeToday(trip.date))
                errors.push(`Trip ${index + 1}: ${ONWARD_DATE_MIN_ERROR}`);
        });
    } else {
        if (!origin.value) errors.push("Please select an Origin");
        if (!destination.value) errors.push("Please select a Destination");
        if (origin.value && origin.value === destination.value)
            errors.push("Origin and destination cannot be the same airport");
        if (!dateRange.value.start) errors.push("Please select a Date");
        else if (isDateBeforeToday(dateRange.value.start))
            errors.push(ONWARD_DATE_MIN_ERROR);
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

watch([origin, destination], ([newOrigin, newDestination], [previousOrigin, previousDestination]) => {
    if (!newOrigin || newOrigin !== newDestination) return;

    if (newOrigin !== previousOrigin && newDestination === previousDestination) {
        origin.value = null;
        return;
    }

    destination.value = null;
});

watch(multiCityTrips, (trips, previousTrips = []) => {
    trips.forEach((trip, index) => {
        if (!trip.origin || trip.origin !== trip.destination) return;

        const previousTrip = previousTrips[index] || {};
        if (trip.origin !== previousTrip.origin && trip.destination === previousTrip.destination) {
            trip.origin = null;
            return;
        }

        trip.destination = null;
    });
}, { deep: true });

onMounted(() => {
    store.dispatch("airport/" + FETCH_AIRPORTS);
    if (user.value?.id) {
        fetchAgent();
    }
    fetchAirlines();
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
    <div class="relative mx-auto max-w-7xl">
        <!-- Main booking services: tab strip merged into the top edge of the search card -->
        <div class="merged-tabs relative z-10 -mb-px flex w-fit max-w-full items-center gap-1 rounded-t-2xl bg-white px-1.5 pb-1 pt-1.5 sm:gap-1.5 sm:rounded-t-3xl sm:px-2.5 sm:pt-2.5">
            <template v-for="(tab, index) in tabs" :key="tab.id">
                <span
                    v-if="index > 0"
                    aria-hidden="true"
                    class="h-5 w-px shrink-0 bg-slate-200"
                ></span>
                <button
                    type="button"
                    @click="setActiveTab(tab.id)"
                    :class="[
                        'relative inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-3.5 py-2 text-xs font-semibold transition-all duration-200 sm:gap-2 sm:px-6 sm:py-2.5 sm:text-sm',
                        activeTab === tab.id
                            ? 'bg-primary text-white shadow-md shadow-primary/30'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-primary',
                    ]"
                >
                    <component :is="tab.iconComponent" class="h-4 w-4 sm:h-[18px] sm:w-[18px]" />
                    <span>{{ tab.name }}</span>
                </button>
            </template>
        </div>

        <div
            class="h-auto overflow-visible rounded-2xl border border-slate-200/80 bg-white shadow-[0_24px_60px_-16px_rgba(15,23,42,0.18)] rounded-tl-none sm:rounded-3xl sm:rounded-tl-none"
        >
        <!-- Content Area -->
        <div class="px-4 pb-5 pt-4 sm:px-7 sm:pb-6 sm:pt-5">
            <!-- Loading State -->
            <div
                v-if="isLoading"
                class="flex items-center justify-center rounded-2xl bg-blue-50/50 p-12 sm:p-20"
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
                <!-- Trip Type Selection & Title Row -->
                <div
                    class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-3.5"
                >
                    <!-- Trip Type Segmented Boxed Tabs -->
                    <div
                        class="grid grid-cols-3 w-full items-center gap-1 rounded-xl bg-slate-100/90 p-1 border border-slate-200/70 shadow-inner sm:inline-flex sm:w-auto"
                        role="tablist"
                        aria-label="Trip Type"
                    >
                        <!-- Round Trip -->
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="flightType === 'return'"
                            @click="setFlightType('return')"
                            :class="[
                                'trip-type-btn group',
                                flightType === 'return'
                                    ? 'trip-type-btn-active'
                                    : 'trip-type-btn-idle',
                            ]"
                        >
                            <ArrowLeftRight class="w-3.5 h-3.5 shrink-0 transition-transform duration-300 group-hover:rotate-180" />
                            <span>{{ $t("Round Trip") }}</span>
                        </button>

                        <!-- One Way -->
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="flightType === 'one-way'"
                            @click="setFlightType('one-way')"
                            :class="[
                                'trip-type-btn group',
                                flightType === 'one-way'
                                    ? 'trip-type-btn-active'
                                    : 'trip-type-btn-idle',
                            ]"
                        >
                            <ArrowRight class="w-3.5 h-3.5 shrink-0 transition-transform duration-200 group-hover:translate-x-0.5" />
                            <span>{{ $t("One Way") }}</span>
                        </button>

                        <!-- Multi-City -->
                        <button
                            type="button"
                            role="tab"
                            :aria-selected="flightType === 'multi-city'"
                            @click="setFlightType('multi-city')"
                            :class="[
                                'trip-type-btn group',
                                flightType === 'multi-city'
                                    ? 'trip-type-btn-active'
                                    : 'trip-type-btn-idle',
                            ]"
                        >
                            <Route class="w-3.5 h-3.5 shrink-0 transition-transform duration-200 group-hover:scale-110" />
                            <span>{{ $t("Multi-City") }}</span>
                        </button>
                    </div>

                    <!-- Right Indicator / Assurance Badge -->
                    <div class="hidden items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3.5 py-1.5 text-xs font-semibold text-primary sm:flex shadow-xs">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
                        </span>
                        <Sparkles class="h-3.5 w-3.5 text-primary" />
                        <span>Best Flight Deals Guaranteed</span>
                    </div>
                </div>
                <!-- Main Search Form -->
                <div class="bg-white rounded-xl sm:pr-0 rtl:pr-0">
                    <div v-if="flightType !== 'multi-city'">
                        <div
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                        >
                            <div
                                class="grid grid-cols-1 sm:grid-cols-[minmax(0,1.35fr)_minmax(0,1.35fr)_minmax(0,1.02fr)_minmax(0,1.02fr)_minmax(0,1.28fr)] items-stretch"
                            >
                        <!-- From -->
                        <div class="booking-cell text-start relative w-full">
                            <label
                                class="booking-label sm:mb-1"
                                >{{ $t("FROM") }}</label
                            >
                            <Autocomplete
                                v-model="origin"
                                clear-on-click
                                :icon="'MapPin'"
                                show-icon
                                :default-value="
                                    route.query?.origin
                                        ? route.query?.origin
                                        : previousSearch?.origin
                                          ? previousSearch?.origin
                                          : ''
                                "
                                :placeholder="$t('origin')"
                                :source="airports"
                                :excluded-iata-codes="[destination]"
                                :search-debounce="300"
                                :default-suggestions="headerDefaultAirportCodes"
                                :auto-fill-defaults="true"
                                auto-fill-role="origin"
                                :auto-fill-suggestion-index="0"
                                class="w-full px-0 focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                            />
                            <button
                                @click="swapAirports"
                                type="button"
                                title="Swap origin and destination"
                                class="absolute -right-5 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200/90 bg-white text-primary shadow-[0_4px_14px_rgba(15,23,42,0.12)] transition-all duration-300 hover:rotate-180 hover:scale-105 hover:border-primary/50 hover:bg-blue-50/80 active:scale-95 sm:flex"
                            >
                                <ArrowLeftRight class="w-4 h-4 text-primary" />
                            </button>
                        </div>

                        <!-- To -->
                        <div class="booking-cell text-start relative w-full">
                            <label
                                class="booking-label mb-1"
                                >{{ $t("TO") }}</label
                            >
                            <Autocomplete
                                v-model="destination"
                                clear-on-click
                                :icon="'MapPin'"
                                show-icon
                                :default-value="
                                    route.query?.destination
                                        ? route.query?.destination
                                        : previousSearch?.destination
                                          ? previousSearch?.destination
                                          : ''
                                "
                                :placeholder="$t('destination')"
                                :source="airports"
                                :excluded-iata-codes="[origin]"
                                :search-debounce="300"
                                :default-suggestions="headerDefaultAirportCodes"
                                :auto-fill-defaults="true"
                                auto-fill-role="destination"
                                :auto-fill-suggestion-index="1"
                                class="w-full px-0 border-none focus:outline-none focus:ring-0 text-sm sm:text-lg font-semibold text-gray-900"
                            />
                        </div>

                        <div class="booking-cell w-full text-start">
                            <div
                                class="flex sm:mt-0.5 justify-start items-center"
                            >
                                <label
                                    class="booking-label mb-1"
                                    >Departure</label
                                >
                            </div>
                            <Calender
                                ref="departureCalendar"
                                v-model="dateRange.start"
                                :minValue="new Date().toLocaleDateString('en-CA')"
                                large-icon
                                class="w-full h-10 sm:h-auto"
                            />
                        </div>
                        <div class="booking-cell w-full text-start cursor-pointer" @click="activateReturnTrip">
                            <div
                                class="flex sm:mt-0.5 justify-start items-center"
                            >
                                <label
                                    class="booking-label mb-1"
                                    >Return</label
                                >
                            </div>
                            <template v-if="flightType === 'return'">
                                <Calender
                                    v-model="dateRange.end"
                                    :minValue="
                                        dateRange.start || new Date().toLocaleDateString('en-CA')
                                    "
                                    large-icon
                                    class="w-full h-10 sm:h-auto"
                                />
                            </template>
                            <button
                                v-else
                                type="button"
                                class="group/add flex min-h-[50px] w-full flex-col justify-center text-left transition-colors"
                            >
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-primary transition-transform duration-200 group-hover/add:translate-x-0.5">
                                    <Plus class="h-3.5 w-3.5" />
                                    <span>Add Return Date</span>
                                </span>
                                <span class="text-[11px] text-slate-400">Save more on round trips</span>
                            </button>
                        </div>

                        <div class="booking-cell w-full text-start">
                            <label
                                class="booking-label mb-1"
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
                                <PopoverContent class="w-80 rounded-2xl border border-slate-100 p-5 shadow-2xl">
                                    <div class="space-y-6">
                                        <div class="grid grid-cols-2 gap-2">
                                            <button
                                                @click="classType = 'Y'"
                                                :class="[
                                                    'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                    classType === 'Y'
                                                        ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                ]"
                                            >
                                                {{ $t("economy") }}
                                            </button>
                                            <button
                                                @click="classType = 'S'"
                                                :class="[
                                                    'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                    classType === 'S'
                                                        ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                ]"
                                            >
                                                {{ $t("premium_economy") }}
                                            </button>
                                            <button
                                                @click="classType = 'C'"
                                                :class="[
                                                    'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                    classType === 'C'
                                                        ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                ]"
                                            >
                                                {{ $t("business") }}
                                            </button>
                                            <button
                                                @click="classType = 'F'"
                                                :class="[
                                                    'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                    classType === 'F'
                                                        ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
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
                                            <Button @click="resetTravelers" variant="outline" class="text-sm font-medium text-gray-700 hover:text-white">
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
                        <div class="mt-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:mt-4">
                            <div
                                class="flex flex-wrap items-center gap-2"
                            >
                                <span
                                    v-if="recentSearches.length"
                                    class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1"
                                >
                                    Recent:
                                </span>
                                <button
                                    v-if="recentSearches.length"
                                    v-for="(search, index) in recentSearches"
                                    :key="search.signature || `${search.timestamp}-${index}`"
                                    type="button"
                                    @click="applyRecentSearch(search)"
                                    class="group inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-slate-50/80 px-3.5 py-1.5 text-xs font-medium text-slate-600 shadow-xs transition-all duration-200 hover:border-primary/50 hover:bg-white hover:text-primary hover:shadow-xs"
                                >
                                    <ClockIcon class="h-3.5 w-3.5 text-slate-400 group-hover:text-primary transition-colors" />
                                    <span class="font-semibold text-slate-800 group-hover:text-primary">{{ getRecentSearchCities(search).from }}</span>
                                    <ArrowRight class="h-4 w-4 shrink-0 text-primary" :stroke-width="2.5" aria-hidden="true" />
                                    <span class="font-semibold text-slate-800 group-hover:text-primary">{{ getRecentSearchCities(search).to }}</span>
                                    <span class="text-slate-400 text-[11px]">
                                        {{ getRecentSearchDateLabel(search) }}
                                    </span>
                                </button>
                            </div>
                            <div class="w-full sm:w-48 sm:shrink-0">
                                <button
                                    @click="searchFlights"
                                    class="flight-search-button group"
                                >
                                    <Search class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
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
                                class="multicity-trip-row mb-3 last:mb-0"
                            >
                                <div class="mb-1.5 flex items-center gap-2 text-start">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary/10 text-[11px] font-bold leading-none text-primary">{{ index + 1 }}</span>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600">Flight {{ index + 1 }}</span>
                                </div>
                                <div
                                    class="grid grid-cols-1 items-stretch overflow-hidden rounded-2xl border border-slate-200 bg-white sm:grid-cols-[minmax(0,1.35fr)_minmax(0,1.35fr)_minmax(0,1.02fr)_minmax(0,1.28fr)]"
                                >
                                    <div class="booking-cell multicity-booking-cell text-start relative w-full">
                                        <label class="booking-label">{{ $t("FROM") }}</label>
                                    <Autocomplete
                                        v-model="trip.origin"
                                        clear-on-click
                                        :default-value="
                                            route.query?.origin
                                                ? route.query?.origin
                                                : previousSearch?.origin
                                                  ? previousSearch?.origin
                                                  : ''
                                        "
                                        :placeholder="$t('origin')"
                                        :source="airports"
                                        :excluded-iata-codes="[trip.destination]"
                                        :search-debounce="300"
                                        :icon="'MapPin'"
                                        show-icon
                                        :default-suggestions="
                                            headerDefaultAirportCodes
                                        "
                                        :auto-fill-defaults="index === 0"
                                        auto-fill-role="origin"
                                        :auto-fill-suggestion-index="0"
                                        class="w-full px-0 border-none focus:outline-none focus:ring-0 text-base sm:text-lg font-semibold text-gray-900"
                                    />
                                    </div>

                                    <div class="booking-cell multicity-booking-cell text-start relative w-full">
                                        <label class="booking-label">{{ $t("TO") }}</label>
                                    <Autocomplete
                                        v-model="trip.destination"
                                        clear-on-click
                                        :icon="'MapPin'"
                                        show-icon
                                        :default-value="
                                            route.query?.destination
                                                ? route.query?.destination
                                                : previousSearch?.destination
                                                  ? previousSearch?.destination
                                                  : ''
                                        "
                                        :placeholder="$t('destination')"
                                        :source="airports"
                                        :excluded-iata-codes="[trip.origin]"
                                        :search-debounce="300"
                                        :default-suggestions="
                                            headerDefaultAirportCodes
                                        "
                                        :auto-fill-defaults="index === 0"
                                        auto-fill-role="destination"
                                        :auto-fill-suggestion-index="1"
                                        class="w-full px-0 border-none focus:outline-none focus:ring-0 text-base sm:text-lg font-semibold text-gray-900"
                                    />
                                    </div>

                                    <div class="booking-cell multicity-booking-cell w-full text-start">
                                        <label class="booking-label">Departure</label>
                                        <Calender
                                            v-model="trip.date"
                                            :minValue="
                                                index === 0
                                                    ? todayDate
                                                    : multiCityTrips[index - 1]
                                                          ?.date || todayDate
                                            "
                                            large-icon
                                            class="w-full border-none"
                                        />
                                    </div>
                                    <div v-if="index === 0" class="w-full text-start">
                                        <div class="booking-cell multicity-booking-cell w-full text-start">
                                        <label class="booking-label">Travellers &amp; Class</label>
                                        <Popover v-model:open="isPopoverOpen">
                                            <PopoverTrigger as-child>
                                                <button
                                                    type="button"
                                                    class="multicity-travelers-trigger w-full h-[110px] px-3 sm:px-4 flex items-center justify-between rounded border-0 bg-white text-gray-900 text-sm sm:text-base font-medium focus:outline-none focus:ring-2 focus:ring-primary"
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
                                            <PopoverContent class="w-80 rounded-2xl border border-slate-100 p-5 shadow-2xl">
                                                <div class="space-y-6">
                                                    <div class="grid grid-cols-2 gap-2">
                                                        <button
                                                            @click="classType = 'Y'"
                                                            :class="[
                                                                'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                                classType === 'Y'
                                                                    ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                            ]"
                                                        >
                                                            {{ $t("economy") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'S'"
                                                            :class="[
                                                                'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                                classType === 'S'
                                                                    ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                            ]"
                                                        >
                                                            {{ $t("premium_economy") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'C'"
                                                            :class="[
                                                                'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                                classType === 'C'
                                                                    ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
                                                            ]"
                                                        >
                                                            {{ $t("business") }}
                                                        </button>
                                                        <button
                                                            @click="classType = 'F'"
                                                            :class="[
                                                                'rounded-xl py-2 px-3 text-xs font-bold uppercase tracking-wide transition-all duration-200',
                                                                classType === 'F'
                                                                    ? 'bg-primary text-white shadow-sm ring-2 ring-primary/20'
                                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80 hover:text-slate-900',
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
                                                        <Button @click="resetTravelers" variant="outline" class="text-sm font-medium text-gray-700 hover:text-white">
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
                                    <div v-else-if="index === 1" @click="addTrip" class="booking-cell multicity-booking-cell flex w-full cursor-pointer items-center justify-center bg-blue-50/50 text-start transition-all hover:bg-blue-50 hover:border-primary/30 active:bg-blue-100">
                                        <Button
                                            @click.stop="addTrip"
                                            class="h-auto w-full border-0 bg-transparent text-sm font-bold text-primary shadow-none hover:bg-transparent flex items-center justify-center gap-1.5"
                                        >
                                            <Plus class="h-4 w-4" />
                                            Add Another City
                                        </Button>
                                    </div>
                                    <!-- Remove Button -->
                                    <div
                                        v-if="index >= 2"
                                        @click="removeTrip(index)"
                                        class="booking-cell multicity-booking-cell flex w-full cursor-pointer items-center justify-center bg-red-50/60 transition-colors hover:bg-red-100/80 active:bg-red-200"
                                    >
                                        <Button
                                            @click.stop="removeTrip(index)"
                                            class="h-auto w-full border-0 bg-transparent text-sm font-bold text-red-600 shadow-none hover:bg-transparent"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <div class="multicity-actions mt-3 flex justify-end">
                                <button
                                    @click="searchFlights"
                                    class="flight-search-button group w-full sm:w-48"
                                >
                                    <Search class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
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
                    class="mt-5 rounded-2xl border border-red-200 bg-red-50 p-4"
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
                        <p class="text-gray-600 mb-6 sm:mb-8 text-sm sm:text-base">
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
                                class="rounded-xl bg-primary px-6 py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-110 sm:px-8 sm:text-base"
                            >
                                Import
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'hotels'" class="animate-fadeIn w-full">
                <div class="mb-5">
                    <h1 class="hidden text-left text-3xl font-semibold tracking-tight text-gray-900 sm:block lg:text-4xl">
                        Discover Your Ideal Stay
                    </h1>
                    <p class="hidden text-left text-sm font-light text-gray-600 sm:mt-1 sm:block lg:text-lg">
                        Search, compare, and book hotels for your next trip.
                    </p>
                </div>

                <!-- <HotelSearchHeader /> -->
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
                               Coming Soon
                            </Button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</template>

<style scoped>
/* Inverted corner so the tab strip flows into the card below it. */
.merged-tabs {
    overflow: visible;
}

.merged-tabs::after {
    content: "";
    position: absolute;
    bottom: 0;
    width: 18px;
    height: 18px;
    pointer-events: none;
    right: -18px;
    background: radial-gradient(circle at top right, transparent 17.5px, #fff 18px);
}

.animate-fadeIn {
    animation: fadeIn 0.5s ease-in-out;
}

/* Boxed service tabs (Flights / Hotels / Holidays) */
.service-tab {
    @apply relative flex h-12 sm:h-14 min-w-[110px] sm:min-w-[136px] items-center justify-center gap-2.5 rounded-xl px-4 text-xs font-bold transition-all duration-200 select-none cursor-pointer sm:px-5 sm:text-sm tracking-tight border;
}

.service-tab-active {
    @apply text-white shadow-[0_8px_24px_-4px_hsl(var(--primary)/0.55)] border-primary/30 scale-[1.02];
    background: linear-gradient(135deg, hsl(var(--primary-button-start)) 0%, hsl(var(--primary-button-end)) 100%);
}

.service-tab-idle {
    @apply text-slate-700 bg-white/60 hover:bg-slate-100/90 hover:text-slate-900 border-transparent active:scale-[0.98];
}

/* Boxed segmented trip-type control */
.trip-type-btn {
    @apply flex h-9 w-full cursor-pointer items-center justify-center gap-1.5 whitespace-nowrap rounded-lg px-3 text-xs font-semibold transition-all duration-200 select-none sm:h-8 sm:w-auto sm:px-4 sm:text-xs md:text-sm border;
}

.trip-type-btn-active {
    @apply bg-white text-primary shadow-[0_2px_8px_rgba(15,23,42,0.08)] border-slate-200/80 font-bold;
}

.trip-type-btn-idle {
    @apply text-slate-500 hover:text-slate-900 hover:bg-white/60 border-transparent;
}

/* Field labels inside the search grid */
.booking-label {
    @apply block text-[11px] font-bold uppercase tracking-wider text-slate-500;
}

.flight-search-button {
    display: inline-flex;
    border-radius: 9999px;
    min-height: 3rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    gap: 0.55rem;
    background: linear-gradient(135deg, hsl(var(--primary-button-start)) 0%, hsl(var(--primary-button-end)) 100%);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.01em;
    box-shadow: 0 12px 24px -10px hsl(var(--primary) / 0.55);
    transition:
        filter 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.flight-search-button:hover {
    filter: brightness(1.07);
    transform: translateY(-1px);
    box-shadow: 0 16px 30px -12px hsl(var(--primary) / 0.6);
}

.flight-search-button:active {
    transform: translateY(1px);
    box-shadow: 0 8px 18px -10px hsl(var(--primary) / 0.5);
}

.booking-cell {
    @apply relative min-w-0 min-h-[76px] border-b border-slate-200 px-3.5 py-2 transition-colors duration-200 hover:bg-slate-50/70 focus-within:bg-blue-50/40 sm:border-b-0 sm:border-r;
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

.booking-cell :deep(button.h-\[110px\]) {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

.booking-cell :deep(input) {
    padding-left: 0 !important;
    padding-right: 0 !important;
    padding-top: 1.85rem !important;
}

.booking-cell :deep(.dropdown input) {
    padding-left: 3rem !important;
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

/* Multi-city uses the same compact density as the one-way form. */
.multicity-trip-row > .mb-1\.5 {
    margin-bottom: 0.35rem;
}

.multicity-booking-cell {
    min-height: 66px;
    padding: 0.45rem 0.9rem;
}

.multicity-booking-cell > label {
    margin-bottom: 0.2rem;
    font-size: 0.75rem;
    line-height: 1rem;
}

.multicity-booking-cell :deep(.h-\[110px\]),
.multicity-booking-cell :deep(.min-h-\[110px\]),
.multicity-booking-cell :deep(button.h-\[110px\]) {
    height: 50px !important;
    min-height: 50px !important;
}

.multicity-booking-cell :deep(.dropdown .pointer-events-none) {
    padding-top: 0 !important;
}

.multicity-booking-cell :deep(.dropdown input) {
    padding-top: 0 !important;
}

.multicity-booking-cell :deep(.multicity-travelers-trigger) {
    height: 50px !important;
    min-height: 50px !important;
}

.multicity-actions {
    padding: 0 0.9rem 0.25rem;
}

.multicity-actions .flight-search-button {
    width: 12rem;
}

@media (max-width: 639px) {
    .multicity-actions .flight-search-button {
        width: 100%;
    }
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
