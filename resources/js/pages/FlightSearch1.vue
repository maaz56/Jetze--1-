<script setup>
import FlightFilterCard from "@/components/common/FlightFilterCard.vue";
import Spinner from "@/components/common/Spinner.vue";
import Header from "../components/shared/Header.vue";
import Autocomplete from "@/components/common/Autocomplete.vue";
import Calender from "@/components/common/Calender.vue";
import DateRangePicker from "@/components/common/DateRangePicker.vue";

import { Button } from "@/components/ui/button";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from "@/components/ui/carousel";
import { Collapsible } from "@/components/ui/collapsible";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb";
import Input from "@/components/ui/input/Input.vue";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
    calculateCustomerPrice,
    calculateCustomerMarginAmount,
    calculateLayover,
    calculateTypeMargin,
    formatAmount,
    formatDate,
    getFlightType,
} from "@/lib/utils";
import { calculateFinalPrice } from "@/lib/utils.js";
import {
    FETCH_AGENT_DATA,
    FETCH_AIRPORT_MARGINS,
    FETCH_AIRPORTS,
    FETCH_CUSTOMER_MARGIN,
    FETCH_CUSTOMER_SETTINGS,
    FETCH_FLIGHT,
    FETCH_PROVIDERS,
} from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";

import FlightAnimationLoader from "@/components/common/FlightAnimationLoader.vue";
import {
    Armchair,
    ArrowDownUp,
    BadgeDollarSign,
    CheckSquare,
    ChevronDown,
    ClockIcon,
    GitCommitHorizontal,
    LoaderCircle,
    Luggage,
    Minus,
    Plane,
    PlaneTakeoff,
    PlaneLanding,
    SquareCheckBig,
    SquareX,
    Timer,
    Users,
    Utensils,
    X,
    Zap,
    Check,
    BriefcaseBusiness,
    Briefcase,
    Clock,
    AlertCircle,
    DollarSign,
    Ticket,
    Calendar,
    TicketCheck,
    ListRestart,
    CircleEllipsis,
    Ellipsis,
    CalendarDays,
    CircleUserRound,
    MapPin,
    Plus,
    Search,
    UsersRound
} from "lucide-vue-next";
import moment from "moment";
import Skeleton from "primevue/skeleton";
import { computed, onMounted, onUnmounted, reactive, ref, watch, watchEffect } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
const activeTab = ref("flights");
import { SlidersHorizontal } from "lucide-vue-next";
import Login from "./Login.vue";
import LoginMini from "./LoginMini.vue";

const isFilterOpen = ref(false);
const isMobileModifyOpen = ref(false);
const MAX_MULTI_CITY_TRIPS = 3;
const mobileTripTypes = [
    { label: "One way", value: "one-way" },
    { label: "Return", value: "return" },
    { label: "Multi-city", value: "multi-city" },
];
const mobileCabinOptions = [
    { label: "Economy", value: "Y" },
    { label: "Premium Economy", value: "S" },
    { label: "Business", value: "C" },
    { label: "First Class", value: "F" },
];
const tabs = [
    { id: "flights", name: "Flights", icon: Plane },
    // { id: "importPnr", name: "Import PNR", icon: UploadCloud }, // Changed to UploadCloud for importing PNRs
    // { id: "hotels", name: "Hotels", icon: Hotel }, // Changed to Hotel (more specific than Building2)
    // { id: "visas", name: "Visas", icon: FileCheck2 }, // Changed to FileCheck2 (visa = approved document)
    // { id: "holidays", name: "Holidays", icon: Sun }, // Changed to Sun (holiday vibe)
    // { id: "umrah-packages", name: "Umrah Packages", icon: School }, // Changed to Mosque (symbolic for Umrah)
    // { id: "travel-insurance", name: "Travel Insurance", icon: ShieldCheck }, // Changed to ShieldCheck (insurance = protection)
    // { id: "group-tickets", name: "Group Tickets", icon: Users2 }, // Changed to Users2 for a modern group icon
];

const setActiveTab = (tabId) => {
    activeTab.value = tabId;
};

const formatFlightNumber = (flightNumber) => {
    if (Array.isArray(flightNumber)) return flightNumber.filter(Boolean).join(" / ");
    if (!flightNumber) return "";
    return String(flightNumber);
};

const formatSegmentDuration = (segment) => {
    const raw = segment?.flight_time ?? segment?.flightTime ?? null;
    if (raw === null || raw === undefined) return "";

    if (typeof raw === "number" && Number.isFinite(raw)) {
        const minutes = Math.max(0, Math.round(raw));
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}h ${String(mins).padStart(2, "0")}m`;
    }

    const text = String(raw || "").trim();
    if (!text) return "";
    // If API already returns a formatted duration string, keep it.
    if (/[a-zA-Z]/.test(text)) return text;

    const asNumber = Number(text);
    if (Number.isFinite(asNumber)) {
        const minutes = Math.max(0, Math.round(asNumber));
        const hours = Math.floor(minutes / 60);
        const mins = minutes % 60;
        return `${hours}h ${String(mins).padStart(2, "0")}m`;
    }

    return "";
};
const formatLayoverTime = (layoverMinutes) => {
    const totalMinutes = Number(layoverMinutes);
    if (!Number.isFinite(totalMinutes) || totalMinutes < 0) return "00:00";

    const normalizedMinutes = Math.round(totalMinutes);
    const hours = Math.floor(normalizedMinutes / 60);
    const minutes = normalizedMinutes % 60;

    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
};
const store = useStore();
const flightStore = useFlightStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const showLogin = ref(false);
const flightType = ref("one-way");
const providers = computed(() => store.getters["flight/providers"]);
const flights = computed(() => flightStore.flights);
const allFlights = ref([]);
const sortedSooperFlights = computed(() => flightStore.sortedSooperFlights);

const sooperFlights = computed(() => flightStore.sooperFlights);
// const cheapestFlightsByAirline = computed(
//     () => flightStore.getCheapestFlightsByAirline,
// );

const isLoggedIn = computed(() => authStore.isLoggedIn);
const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const isFlightLoading = computed(() => flightStore.isFlightLoading);
const isLoading = computed(
    () => store.getters["flight/isLoading"],
);
const customerSettings = computed(
    () => store.getters["customer/customerSettings"],
);
const airportMargin = computed(
    () => store.getters["airport/airportMargin"] || {},
);

const CustomerMargin = computed(
    () => store.getters["customerMargin/customerMargin"],
);
const airports = computed(() => store.getters["airport/airports"] || []);

// const availableAirlines = computed(() => flightStore.availableAirlines);
const previousSearch = JSON.parse(localStorage.getItem("previous_search"));
const RECENT_SEARCHES_KEY = "recent_search_history";
const MAX_RECENT_SEARCHES = 4;

const loading = ref(true);
const toCheckoutClicked = ref(false);
const error = ref(null);
const selectedStops = ref();
const selectedAirline = ref([]);
const selectedTimes = ref([]);
const maxPrice = ref();
const priceMargin = ref("");
const airlineMargin = ref(null);
const quotationTo = ref("");
const isShownMarginInput = ref(false);
const inputErrors = ref(null);
const origin = ref(null);
const destination = ref(null);

const routeLabel = (value) => {
    if (!value) return "";
    if (typeof value === "string") return value.trim().toUpperCase();
    return String(value.iata || value.iata_code || value.code || value.value || value.name || "")
        .trim()
        .toUpperCase();
};

const searchedRouteTitle = computed(() => {
    const routes = flightType.value === "multi-city"
        ? multiCityTrips.value
            .map((trip) => [routeLabel(trip?.origin), routeLabel(trip?.destination)])
            .filter(([from, to]) => from && to)
            .map(([from, to]) => `${from} to ${to}`)
        : [[routeLabel(origin.value), routeLabel(destination.value)]]
            .filter(([from, to]) => from && to)
            .map(([from, to]) => `${from} to ${to}`);

    return routes.length ? `${routes.join(" | ")} Flights | ApnaTicket` : "Flight Search | ApnaTicket";
});

const tripTypeLabel = computed(() => {
    const labels = {
        "one-way": "One way",
        return: "Return",
        "multi-city": "Multi-city",
    };

    return labels[flightType.value] || "Flight";
});

const cabinClassLabel = computed(() => {
    const labels = {
        Y: "Economy",
        S: "Premium Economy",
        C: "Business",
        F: "First Class",
    };

    return labels[classType.value] || classType.value || "Economy";
});

const travelerSummary = computed(() => {
    const total = Number(adults.value || 0) + Number(children.value || 0) + Number(infants.value || 0);
    return `${total || 1} ${total === 1 ? "Traveller" : "Travellers"}`;
});

const mobileEditTravelerLabel = computed(() => {
    const total =
        Number(modelValue.value.adult || 0) +
        Number(modelValue.value.child || 0) +
        Number(modelValue.value.infant || 0);

    return `${total || 1} ${total === 1 ? "Traveller" : "Travellers"}`;
});

const mobileRouteSummary = computed(() => {
    if (flightType.value === "multi-city") {
        const routes = multiCityTrips.value
            .map((trip) => [routeLabel(trip?.origin), routeLabel(trip?.destination)])
            .filter(([from, to]) => from && to)
            .map(([from, to]) => `${from}-${to}`);

        return routes.length ? routes.join(" | ") : "Multi-city route";
    }

    const from = routeLabel(origin.value) || "Origin";
    const to = routeLabel(destination.value) || "Destination";
    return `${from} to ${to}`;
});

const formatSearchDate = (value) => {
    if (!value) return "";
    const parsed = moment(value);
    return parsed.isValid() ? parsed.format("DD MMM") : value;
};

const mobileDateSummary = computed(() => {
    if (flightType.value === "multi-city") {
        const dates = multiCityTrips.value
            .map((trip) => formatSearchDate(trip?.date))
            .filter(Boolean);

        return dates.length ? dates.join(", ") : "Select dates";
    }

    const departure = formatSearchDate(dateRange.value?.start);
    const returning = flightType.value === "return" ? formatSearchDate(dateRange.value?.end) : "";

    if (departure && returning) return `${departure} - ${returning}`;
    return departure || "Select date";
});

watchEffect(() => {
    document.title = searchedRouteTitle.value;
});

onUnmounted(() => {
    document.title = "ApnaTicket";
});
const dateRange = ref({
    start: null,
    end: null,
});
const multiCityTrips = ref([
    { origin: null, destination: null, date: null },
    { origin: null, destination: null, date: null },
]);

const completedProviders = ref(0); // Track completed providers
const progress = ref(0);
const isSearching = ref(false);
const filteredFlights = ref([]);
const classType = ref("Y");
const adults = ref(1);
const children = ref(0);
const infants = ref(0);
const flexiblePlusMinus3 = ref(false);
const maxTravelers = 9;
const countdown = ref(null);
const timerInterval = ref(null);
const showDialog = ref(true);
const isSideSheetOpen = ref(false);
const isSooperFlihgtDetailsOpen = ref(false);
const selectedFlightId = ref(null);
const selectedFlight = ref(null);
const showFareDetailsDialog = ref(false);
const selectedFareDetails = ref(null);
const selectedFareDetailsFlight = ref(null);
const loadingDetails = ref(false);
const pnr = ref(null);
const passengerCount = ref();
const selectedFares = reactive([]); // { 0: 'ref_id_1', 1: 'ref_id_2' }
const savedAmount = ref(0);
const modelValue = ref({
    flightType: "one-way",
    countdownFor: 0,
    adult: 1,
    child: 0,
    infant: 0,
    classType: "",
    flexible_plus_minus_3: false,
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

function resetFlightParams() {
    modelValue.value = {
        flightType: flightType.value,
        adult: adults.value,
        child: children.value,
        infant: infants.value,
        classType: classType.value,
        origin: origin.value,
        destination: destination.value,
        dateRange: dateRange.value,
        flexible_plus_minus_3: flexiblePlusMinus3.value,
        multiCityTrips: multiCityTrips.value,
    };
}

function toggleMobileModify() {
    if (!isMobileModifyOpen.value) {
        resetFlightParams();
    }

    isMobileModifyOpen.value = !isMobileModifyOpen.value;
}

function setMobileTripType(value) {
    modelValue.value.flightType = value;
    inputErrors.value = null;

    if (value === "one-way") {
        modelValue.value.dateRange = {
            ...(modelValue.value.dateRange || {}),
            end: null,
        };
    }

    if (value === "multi-city") {
        const existingTrips = Array.isArray(modelValue.value.multiCityTrips)
            ? modelValue.value.multiCityTrips
            : [];

        modelValue.value.multiCityTrips = existingTrips.length >= 2
            ? existingTrips
            : [
                {
                    origin: modelValue.value.origin || null,
                    destination: modelValue.value.destination || null,
                    date: modelValue.value.dateRange?.start || "",
                },
                {
                    origin: modelValue.value.destination || null,
                    destination: null,
                    date: modelValue.value.dateRange?.end || "",
                },
            ];
    }
}

function swapMobileRoute() {
    const previousOrigin = modelValue.value.origin;
    modelValue.value.origin = modelValue.value.destination;
    modelValue.value.destination = previousOrigin;
}

function updateMobileTraveler(type, direction) {
    const delta = direction === "increment" ? 1 : -1;
    const keyMap = {
        adults: "adult",
        children: "child",
        infants: "infant",
    };
    const key = keyMap[type];
    if (!key) return;

    const current = Number(modelValue.value[key] || 0);
    const next = current + delta;
    const totalTravelers =
        Number(modelValue.value.adult || 0) +
        Number(modelValue.value.child || 0) +
        Number(modelValue.value.infant || 0);

    if (key === "adult") {
        if (next < 1 || (delta > 0 && totalTravelers >= maxTravelers)) return;
        modelValue.value.adult = next;
        if (Number(modelValue.value.infant || 0) > next) {
            modelValue.value.infant = next;
        }
        return;
    }

    if (key === "child") {
        if (next < 0 || (delta > 0 && totalTravelers >= maxTravelers)) return;
        modelValue.value.child = next;
        return;
    }

    if (next < 0 || next > Number(modelValue.value.adult || 1) || (delta > 0 && totalTravelers >= maxTravelers)) {
        return;
    }

    modelValue.value.infant = next;
}

function addMobileTrip() {
    const trips = Array.isArray(modelValue.value.multiCityTrips)
        ? modelValue.value.multiCityTrips
        : [];

    if (trips.length >= MAX_MULTI_CITY_TRIPS) return;

    const lastTrip = trips[trips.length - 1];
    modelValue.value.multiCityTrips = [
        ...trips,
        {
            origin: lastTrip?.destination || null,
            destination: null,
            date: "",
        },
    ];
}

function removeMobileTrip(index) {
    const trips = Array.isArray(modelValue.value.multiCityTrips)
        ? modelValue.value.multiCityTrips
        : [];

    if (trips.length <= 2) return;
    modelValue.value.multiCityTrips = trips.filter((_, tripIndex) => tripIndex !== index);
}

const readRecentSearches = () => {
    try {
        const parsed = JSON.parse(localStorage.getItem(RECENT_SEARCHES_KEY));
        if (!Array.isArray(parsed)) return [];
        const sanitized = parsed
            .filter((item) => item && typeof item === "object")
            .slice(0, MAX_RECENT_SEARCHES);
        if (sanitized.length !== parsed.length) {
            localStorage.setItem(
                RECENT_SEARCHES_KEY,
                JSON.stringify(sanitized),
            );
        }
        return sanitized;
    } catch {
        return [];
    }
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
        flexible_plus_minus_3: !!searchParams.flexible_plus_minus_3,
    });

const saveRecentSearch = (searchParams) => {
    const entry = {
        ...searchParams,
        savedAt: Date.now(),
        signature: createSearchSignature(searchParams),
    };

    const current = readRecentSearches();
    const deduped = current.filter((item) => item.signature !== entry.signature);
    const next = [entry, ...deduped].slice(0, MAX_RECENT_SEARCHES);
    localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
};

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS);
}
const openFlightDetails = (flightId) => {
    loadingDetails.value = true;
    store.dispatch("flight/" + FETCH_FLIGHT, { flight_id: flightId });
    isSideSheetOpen.value = true;
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

const activeFilter = ref(null);

const toggleFilter = (filter) => {
    activeFilter.value = activeFilter.value === filter ? null : filter;
};

const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
};
async function openSooperFlightDetails(flight) {
    selectedFlight.value = flight;
    // store.dispatch("flight/" + FETCH_FLIGHT, {
    //     flight_id: flightId,
    //     isSooperFlight: true
    // });
    isSooperFlihgtDetailsOpen.value = true;
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

    // Prefer route query first (recent search click), then fallback to localStorage.
    flightType.value =
        route.query.flightType ??
        previousSearch.flightType ??
        flightType.value ??
        "one-way";

    // If multi-city -> initialize from query/localStorage trips or default.
    if (flightType.value === "multi-city") {
        let trips = route.query.trips ?? previousSearch.trips;
        if (typeof trips === "string") {
            if (trips === "[object Object]") {
                trips = null;
            } else {
                try {
                    trips = JSON.parse(trips);
                } catch {
                    trips = null;
                }
            }
        }

        if (
            !Array.isArray(trips) ||
            trips.some((trip) => typeof trip !== "object" || trip === null)
        ) {
            trips = [
                { origin: null, destination: null, date: null },
                { origin: null, destination: null, date: null },
            ];
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
            null;
        dateRange.value.end =
            dateRange.value.end ??
            route.query.return_date ??
            previousSearch.return_date ??
            null;
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
    
    flexiblePlusMinus3.value = 
        route.query.flexible_plus_minus_3 === 'true' || 
        route.query.flexible_plus_minus_3 === true ||
        !!previousSearch.flexible_plus_minus_3;

    startCountdown(15 * 60 * 1000 - (now - previousSearch.timestamp));
};

const confirmReload = () => {
    localStorage.removeItem("previous_search");
    showDialog.value = false;
    window.location.reload();
};
const getFlightsByAirline = computed(() => {
    return sooperFlights.value.filter((flight) => {
        return (
            flight?.leg?.flights?.length > 0 &&
            flight?.leg?.flights.some((fare) => {
                if (selectedAirline.value.length === 0) return true;
                return selectedAirline.value.includes(
                    fare.marketing_carrier.iata,
                );
            })
        );
    });
});

function fetchProviders() {
    isSearching.value = true;
    // let searchParams;
    // if (origin.value && destination.value && dateRange.value.start) {
    //     searchParams = {
    //         origin: origin.value,
    //         destination: destination.value,
    //         departure_date: dateRange.value.start,
    //         return_date: dateRange.value.end,
    //         cabin_class: classType.value,
    //         adults: adults.value,
    //         children: children.value,
    //         infants: infants.value,
    //         stops: selectedStops.value,
    //         airline: selectedAirline.value,
    //         timestamp: Date.now(),
    //         flightType: flightType.value,
    //         currencyCode: "AED",

    //     };
    // }
    let searchParams;
    const baseParams = {
        cabin_class: classType.value,
        adults: adults.value,
        children: children.value,
        infants: infants.value,
        stops: selectedStops.value,
        timestamp: Date.now(),
        flightType: flightType.value,
        // currencyCode: localStorage.getItem("currencyCode") || "SAR",
        currencyCode: "PKR",
        flexible_plus_minus_3: flexiblePlusMinus3.value,
    };

    if (flightType.value === "multi-city") {
        searchParams = { ...baseParams, trips: multiCityTrips.value };
    } else if (origin.value && destination.value && dateRange.value.start) {
        searchParams = {
            ...baseParams,
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date: dateRange.value.end,
        };
    }
    store.dispatch("flight/" + FETCH_PROVIDERS, {
        searchParams,
    });
}

watch(providers, () => {
    fetchFlights();
});

watch(
    sooperFlights,
    (newFlights) => {
        if (newFlights && newFlights.length > 0) {
            allFlights.value = [...allFlights.value, ...newFlights];
            filteredFlights.value = [...allFlights.value];
           
            filteredFlights.value = [...filteredFlights.value].sort((a, b) => {
            const priceA = calculateTotalFare(a);
            const priceB = calculateTotalFare(b);

    // console.log(priceA, priceB);

    return priceA - priceB;
});
            // Also update filteredFlights
           

            const totalProviders = providers.value.length;
            const completedProviders = allFlights.value.length; // maybe rethink this assumption
            progress.value = Math.round(
                (completedProviders / totalProviders) * 100,
            );
        }
    },
    { deep: true },
);

const cheapestFlightsByAirline = computed(() => {
    if (!allFlights.value || allFlights.value.length === 0) return [];
    const map = new Map();
    allFlights.value.forEach((flight) => {
        // Try to get airline code/id from the first leg/segment
        const airlineId =
            flight?.leg?.flights?.[0]?.marketing_carrier?.id ||
            flight?.leg?.flights?.[0]?.marketing_carrier?.iata ||
            flight?.leg?.flights?.[0]?.marketing_carrier?.code ||
            flight?.leg?.flights?.[0]?.marketing_carrier?.name ||
            "unknown";
        // Calculate total price for comparison
        const getTotalPrice = (item) => {
            if (item?.leg?.flights && Array.isArray(item.leg.flights)) {
                return item.leg.flights.reduce((sum, fl) => {
                    if (fl?.fares && fl.fares.length > 0) {
                        return sum + (fl.fares[0]?.billable_price || 0);
                    }
                    return sum;
                }, 0);
            }
            return item?.pricing?.totalPrice || 0;
        };
        const price = getTotalPrice(flight);
        if (!map.has(airlineId) || getTotalPrice(map.get(airlineId)) > price) {
            map.set(airlineId, flight);
        }
    });
    return Array.from(map.values());
});
const availableAirlines = computed(() => {
    // Extract unique airlines from allFlights
    const airlinesMap = new Map();
    allFlights.value.forEach((flight) => {
        // Try to get airline info from the first leg/segment
        const carrier = flight?.leg?.flights?.[0]?.marketing_carrier;
        if (carrier && (carrier.id || carrier.iata || carrier.code)) {
            const id = carrier.id || carrier.iata || carrier.code;
            if (!airlinesMap.has(id)) {
                airlinesMap.set(id, {
                    id,
                    name: carrier.name,
                    logo_url: carrier.logo || carrier.logo_url,
                });
            }
        }
    });
    return Array.from(airlinesMap.values());
});

function sortFlights() {
    flightStore.sortFlights({
        flights: allFlights.value,
        airline: selectedAirline.value,
        stops: selectedStops.value,
    });
}

function filterByAirline() {
    if (!selectedAirline.value || selectedAirline.value.length === 0) {
        filteredFlights.value = [...allFlights.value];
        return;
    }
    filteredFlights.value = (filteredFlights.value?.length
        ? filteredFlights.value
        : allFlights.value
    ).filter((flight) => {
        const carrierIds =
            flight?.leg?.flights?.map((leg) => {
                const carrier = leg?.marketing_carrier;
                return (
                    carrier?.id ||
                    carrier?.iata ||
                    carrier?.code ||
                    carrier?.name ||
                    null
                );
            }) || [];
        return carrierIds.some((id) => selectedAirline.value.includes(id));
    });
}
function filterByStops() {
    // if (!selectedStops.value || selectedStops.value === "all") {
    //   filteredFlights.value = allFlights.value;
    //   return;
    // }
    // filteredFlights.value = allFlights.value.filter(flight => {
    //   // Count total stops across all legs
    //   const stopsCount = flight?.leg?.flights?.reduce((sum, leg) => {
    //     return sum + (leg?.layovers_count || 0);
    //   }, 0);
    //   return String(stopsCount) === String(selectedStops.value);
    // });
    sortFlights();
}

watch(sortedSooperFlights, () => {
    filteredFlights.value = [...sortedSooperFlights.value];
});

const fetchFlights = () => {
    resetAllFilters();
    allFlights.value = [];
    sooperFlights.value = null;
    sortedSooperFlights.value = null;
    filteredFlights.value = null;
    completedProviders.value = 0;
    progress.value = 0;
    isSearching.value = true; // Start showing the progress bar
    localStorage.removeItem("selectedFlight");

    let searchParams;
    const baseParams = {
        cabin_class: classType.value,
        adults: adults.value,
        children: children.value,
        infants: infants.value,
        stops: selectedStops.value,
        timestamp: Date.now(),
        flightType: flightType.value,
        // currencyCode: localStorage.getItem("currencyCode") || "SAR",
        currencyCode: "PKR",
        flexible_plus_minus_3: flexiblePlusMinus3.value,
    };

    if (flightType.value === "multi-city") {
        searchParams = { ...baseParams, trips: multiCityTrips.value };
    } else if (origin.value && destination.value && dateRange.value.start) {
        searchParams = {
            ...baseParams,
            origin: origin.value,
            destination: destination.value,
            departure_date: dateRange.value.start,
            return_date: dateRange.value.end,
        };
    }

    if (searchParams) {
        localStorage.setItem("previous_search", JSON.stringify(searchParams));
        // const prioritizedProviders = ["SABRE", "EMIRATES", "TURKISH", "NUFLIGHTS"];
        // const reorderedProviders = [
        //     ...providers.value.filter(p => !prioritizedProviders.includes(p.identifier)),
        //     ...providers.value.filter(p => prioritizedProviders.includes(p.identifier))
        // ];

        providers.value.forEach((provider) => {
            const paramsWithProvider = {
                ...searchParams,
                airline: provider.identifier,
            };
            flightStore
                .fetchFlights(paramsWithProvider)

                .catch((error) => {
                    console.error(
                        `Error fetching flights for ${provider.identifier}:`,
                        error,
                    );
                })
                .finally(() => {
                    completedProviders.value++;

                    progress.value = Math.round(
                        (completedProviders.value / providers?.value?.length) *
                        100,
                    );

                    if (completedProviders.value === providers.value.length) {
                        setTimeout(() => {
                            isSearching.value = false;
                        }, 1000);
                    }
                });
        });
    }
};

const showMoreFilters = ref(false);
const maxDurationFilter = ref(null);
const refundableFilter = ref("all"); // 'all', 'refundable', 'non-refundable'
const calculateDuration = (departure, arrival) => {
    const dep = moment(departure)
    const arr = moment(arrival)
    const duration = moment.duration(arr.diff(dep))
    const hours = Math.floor(duration.asHours())
    const minutes = duration.minutes()
    return `${hours}h ${minutes}m`
}
// Compute max duration from flights
const maxDuration = computed(() => {
    if (!allFlights.value.length) return 24;
    return Math.max(
        ...allFlights.value.map((flight) => {
            return (
                flight.leg.flights.reduce(
                    (sum, leg) => sum + leg.travel_time,
                    0,
                ) / 60
            );
        }),
    );
});

function filterByDuration() {
    // Implement duration filtering logic
    if (!maxDurationFilter.value) {
        filteredFlights.value = [...allFlights.value];
        return;
    }
    filteredFlights.value = allFlights.value.filter((flight) => {
        const totalMinutes = flight.leg.flights.reduce(
            (sum, leg) => sum + leg.travel_time,
            0,
        );
        return totalMinutes / 60 <= maxDurationFilter.value;
    });
}

function filterByRefundable() {
    if (refundableFilter.value === "all") {
        filteredFlights.value = [...allFlights.value];
        return;
    }
    filteredFlights.value = allFlights.value.filter((flight) => {
        const isRefundable = flight.leg.flights.some((f) => f.is_refundable);
        return refundableFilter.value === "refundable"
            ? isRefundable
            : !isRefundable;
    });
}

function applyMoreFilters() {
    filterByAirline();
    filterByDuration();
    filterByRefundable();
}

function resetAllFilters() {
    selectedStops.value = null;
    selectedAirline.value = [];
    maxPrice.value = null;
    maxDurationFilter.value = null;
    refundableFilter.value = "all";
    activeFilter.value = null;
    filteredFlights.value = [...allFlights.value];
    departureTimes.value = [];
    arrivalTimes.value = [];
    selectedStopsArray.value = [];
    onlyRefundable.value = false;
}

function sortFlightsByPrice(order) {
    const direction = order === "high" ? -1 : 1;
    filteredFlights.value = [...allFlights.value].sort(
        (a, b) =>
            direction * (calculateTotalFare(a) - calculateTotalFare(b)),
    );
}

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
        flexible_plus_minus_3: flexiblePlusMinus3.value,
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
watch(
    () => route.query,
    (newQuery) => {
        flightType.value = newQuery.flightType || flightType.value;

        if (flightType.value === "multi-city") {
            // ✅ Do not get from query anymore, keep existing localStorage or current statetype
            multiCityTrips.value = multiCityTrips.value;
        } else {
            // ✅ For other types, allow query updates
            origin.value = newQuery.origin || origin.value;
            destination.value = newQuery.destination || destination.value;
            dateRange.value.start =
                newQuery.departure_date || dateRange.value.start;
            dateRange.value.end = newQuery.return_date || dateRange.value.end;
        }

        classType.value = newQuery.cabin_class || classType.value;
        adults.value = parseInt(newQuery.adults) || adults.value;
        children.value = parseInt(newQuery.children) || children.value;
        infants.value = parseInt(newQuery.infants) || infants.value;
        flexiblePlusMinus3.value = 
            newQuery.flexible_plus_minus_3 === 'true' || 
            newQuery.flexible_plus_minus_3 === true || 
            flexiblePlusMinus3.value;

        // Keep FlightFilterCard in sync when route query changes (e.g. recent history click).
        resetFlightParams();
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

function importPnr(pnr) {
    router.push({
        name: "PnrDetails",
        query: { pnr: pnr },
    });
}
const departureTimes = ref([]); // ['morning', 'morningLate', 'afternoon', 'night']
const arrivalTimes = ref([]);
const selectedStopsArray = ref([]); // ['0', '1', '2']
const onlyRefundable = ref(false);

const showNoFlightsState = computed(() => {
    return (
        !isLoading.value &&
        !isFlightLoading.value &&
        !isSearching.value &&
        completedProviders.value > 0 &&
        allFlights.value.length === 0
    );
});

const showNoFilteredFlightsState = computed(() => {
    return (
        !isLoading.value &&
        !isFlightLoading.value &&
        !isSearching.value &&
        allFlights.value.length > 0 &&
        (!filteredFlights.value || filteredFlights.value.length === 0)
    );
});

const isPopularRoutePage = computed(() => route.name === "PopularRouteDetails");

const popularRouteFareRows = computed(() => {
    const sourceFlights =
        (filteredFlights.value && filteredFlights.value.length > 0
            ? filteredFlights.value
            : allFlights.value) || [];

    return sourceFlights.map((item) => {
        const legs = item?.leg?.flights || [];
        const firstLeg = legs[0] || {};
        const lastLeg = legs[legs.length - 1] || firstLeg;
        const airlineCode = firstLeg?.marketing_carrier?.iata || "";

        const fromCity =
            firstLeg?.from?.city?.name ||
            firstLeg?.from?.city_name ||
            firstLeg?.from?.iata ||
            origin.value ||
            "-";
        const toCity =
            lastLeg?.to?.city?.name ||
            lastLeg?.to?.city_name ||
            lastLeg?.to?.iata ||
            destination.value ||
            "-";

        return {
            route: `${fromCity} to ${toCity}`,
            airline:
                firstLeg?.marketing_carrier?.name ||
                airlineCode ||
                "-",
            flightNumber: firstLeg?.flight_number
                ? `${airlineCode ? `${airlineCode}-` : ""}${firstLeg.flight_number}`
                : "-",
            totalFare: `PKR ${formatAmount(calculateTotalFare(item))}`,
        };
    });
});

const popularFareTableTitle = computed(() => {
    const firstRowRoute = popularRouteFareRows.value?.[0]?.route || "";
    const [from, to] = firstRowRoute.split(" to ");
    return `Cheapest fare from ${from || "Origin"} to ${to || "Destination"}`;
});

function scrollToSearchTop() {
    window.scrollTo({ top: 0, behavior: "smooth" });
}

function applyAllFilters() {
    filterByPrice();
    filterByStopsModal(); // new function for checkbox stops
    filterByAirline();
    filterByDuration();
    filterByRefundable();
    filterByDepartureTime();
    filterByArrivalTime();
}

// New filtering functions
function filterByStopsModal() {
    if (selectedStopsArray.value.length === 0) {
        filteredFlights.value = [...allFlights.value];
        return;
    }
    filteredFlights.value = allFlights.value.filter((flight) => {
        const stops = flight.leg.flights.reduce(
            (sum, leg) => sum + (leg.layovers_count || 0),
            0,
        );
        return (
            selectedStopsArray.value.includes(String(stops)) ||
            (selectedStopsArray.value.includes("2") && stops >= 2)
        );
    });
}

function filterByDepartureTime() {
    if (departureTimes.value.length === 0) return;
    filteredFlights.value = filteredFlights.value.filter((flight) => {
        const hour = moment
            .parseZone(flight.leg.flights[0]?.departure_at)
            .hour();
        return departureTimes.value.some((slot) => {
            if (slot === "morning") return hour < 6;
            if (slot === "morningLate") return hour >= 6 && hour < 12;
            if (slot === "afternoon") return hour >= 12 && hour < 18;
            if (slot === "night") return hour >= 18;
            return false;
        });
    });
}

function filterByArrivalTime() {
    if (arrivalTimes.value.length === 0) return;
    filteredFlights.value = filteredFlights.value.filter((flight) => {
        const lastLeg = flight.leg.flights[flight.leg.flights.length - 1];
        const hour = moment.parseZone(lastLeg?.arrival_at).hour();
        return arrivalTimes.value.some((slot) => {
            if (slot === "morning") return hour < 6;
            if (slot === "morningLate") return hour >= 6 && hour < 12;
            if (slot === "afternoon") return hour >= 12 && hour < 18;
            if (slot === "night") return hour >= 18;
            return false;
        });
    });
}
function searchFlights() {
    const now = Date.now();
    console.log("Timer: " + countdown.value);
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
        return false;
    }

    inputErrors.value = null;

    const searchParams = {
        flightType: flightType.value,
        cabin_class: classType.value,
        adults: adults.value,
        children: children.value,
        infants: infants.value,
        flexible_plus_minus_3: flexiblePlusMinus3.value,
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

    const queryParams = { ...searchParams };
    if (searchParams.trips) {
        queryParams.trips = JSON.stringify(searchParams.trips);
    }

    localStorage.setItem("previous_search", JSON.stringify(searchParams));
    saveRecentSearch(searchParams);
    startCountdown(15 * 60 * 1000);

    router.push({
        name: "FlightSearch",
        query: queryParams,
    });

    // fetchFlights();
    fetchProviders();
    return true;
}

watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
    }
});

watch(sooperFlights, (newUserId) => {
    if (newUserId) {
        const totalCount =
            parseFloat(route.query.adults) +
            parseFloat(route.query.children) +
            parseFloat(route.query.infants);
        passengerCount.value = totalCount;
    }
});

function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN).catch((error) => {
        console.error("Error fetching customer margin:", error);
    });
}

function calculateCustomerMargin(basePrice) {
    return calculateCustomerMarginAmount(basePrice, CustomerMargin?.value);
}

function calculateFareMargin(basePrice, marginAmount, marginType, amountType) {
    const amount = parseFloat(marginAmount) || 0;
    const price = parseFloat(basePrice) || 0;

    if (!marginType) return 0;

    if (marginType === "discount") {
        return amountType === "percent" ? -(price * amount) / 100 : -amount;
    }

    if (marginType === "markup") {
        return amountType === "percent" ? (price * amount) / 100 : amount;
    }

    return 0;
}

// 🧾 Correct Total Fare Calculation
function calculateTotalFare(item) {
    let total = 0;
    let totalWithoutTypeMarginsLocal = 0;

    item?.leg?.flights?.forEach((leg) => {
        const fare = leg?.fares?.reduce((minFare, current) => {
            const minPrice = parseFloat( minFare?.total_price || Infinity);
            const currentPrice = parseFloat( current?.total_price || Infinity);
            return currentPrice < minPrice ? current : minFare;
        });
        if (!fare) return;

        const basePrice = parseFloat(fare.base_price || 0);
        const paxCount = parseInt(passengerCount.value || 1);

        // Margins
        const typeMargin = calculateTypeMargin(user.value, airportMargin.value);
        const customerMargin = calculateCustomerMargin(basePrice);
        const fareMargin = calculateFareMargin(
            basePrice,
            fare.margin_amount,
            fare.margin_type,
            fare.amount_type,
        );

        const baseTotal = basePrice;
        const otherCharges =
            parseFloat(fare.surchage || 0) +
            parseFloat(fare.taxes || 0) +
            parseFloat(fare.service_charges || 0) +
            parseFloat(fare.ancillaries_charges || 0) +
            parseFloat(fare.fees || 0)+
            parseFloat(priceMargin.value || 0);

        const totalWithoutMargin = baseTotal + otherCharges;
        const billableDiff = fare.billable_price - totalWithoutMargin;

        const customerOtherCharges = parseFloat(
            CustomerMargin?.value?.other_charges || 0,
        );

        totalWithoutTypeMarginsLocal =
            totalWithoutMargin +
            // billableDiff +
            (customerMargin + fareMargin) * paxCount +
            customerOtherCharges;
        total += totalWithoutTypeMarginsLocal + typeMargin * paxCount;
        savedAmount.value = total - totalWithoutTypeMarginsLocal;
    });

    return total;
}

const fetchMargins = async () => {
    store.dispatch("airport/" + FETCH_AIRPORT_MARGINS);
};
// 💰 Single Fare Calculation (for individual display)
function calculateFare(fare) {
    const basePrice = parseFloat(fare.base_price || 0);
    const paxCount = parseInt(passengerCount.value || 1);
    const typeMargin = calculateTypeMargin(user.value, airportMargin.value);
    const customerMargin = calculateCustomerMargin(basePrice);
    const fareMargin = calculateFareMargin(
        basePrice,
        fare.margin_amount,
        fare.margin_type,
        fare.amount_type,
    );

    const baseTotal = basePrice;

    const otherCharges =
        parseFloat(fare.surchage || 0) +
        parseFloat(fare.taxes || 0) +
        parseFloat(fare.service_charges || 0) +
        parseFloat(fare.ancillaries_charges || 0) +
        parseFloat(fare.fees || 0);

    const totalWithoutMargin = baseTotal + otherCharges;
    const billableDiff = fare.billable_price - totalWithoutMargin;

    const customerOtherCharges = parseFloat(
        CustomerMargin?.value?.other_charges || 0,
    );

    const total =
        totalWithoutMargin +
        // billableDiff +
        (customerMargin + fareMargin + typeMargin) * paxCount +
        customerOtherCharges;

    return total;
}

function calculateGrandTotal() {
    let total = 0;
    // console.log("Selected Flight:", selectedFlight?.value);
    selectedFlight?.value?.leg?.flights?.forEach((flight) => {
        flight?.fares?.forEach((fare) => {
            if (selectedFares.includes(fare.ref_id)) {
                total += calculateFare(fare);
            }
        });
    });

    return total;
}

const minPriceLimit = computed(() => {
    if (!allFlights.value.length) return 0;
    return Math.min(...allFlights.value.map((f) => calculateTotalFare(f)));
});

const maxPriceLimit = computed(() => {
    if (!allFlights.value.length) return 10000;
    return Math.max(
        ...allFlights.value.map((f) => calculateTotalFare(f)),
        10000,
    );
});

function filterByPrice() {
    if (!maxPrice.value) {
        filteredFlights.value = [...allFlights.value];
        return;
    }
    filteredFlights.value = allFlights.value.filter(
        (flight) =>
            calculateTotalFare(flight) <=
            (maxPrice.value || maxPriceLimit.value),
    );
}

const isButtonDisabled = computed(() => {
    const isReturn = route.query.flightType === "return";
    const hasSelected = isReturn
        ? selectedFares?.[0] && selectedFares?.[1]
        : selectedFares?.[0];

    // Disable if no valid selection or booking not allowed
    return !hasSelected || customerSettings.value?.is_booking_allowed !== 1;
});
watch(user, () => {
    console.log("User changed:", user.value);
    console.log("To Checkout Clicked:", toCheckoutClicked.value);
    if (toCheckoutClicked.value) {
        localStorage.setItem(
            "selectedFlight",
            JSON.stringify(selectedFlight.value),
        );
        router.push({
            name: "Checkout",
            query: {
                flight_id: selectedFlight.value?.leg?.ref_id,
                fares: JSON.stringify(selectedFares), // 👈 stringify array
                flight_provider: selectedFlight.value?.provider?.name || "N/A",
                flight_mode: "B2C",
                flight_source: 1,
                passenger_count: passengerCount.value,
                adults: parseInt(route.query.adults) || 1,
                children: parseInt(route.query.children) || 0,
                infants: parseInt(route.query.infants) || 0,
                price_margin: priceMargin.value || 0,
            },
        });
    }
})

function goToCheckout() {
    toCheckoutClicked.value = true;
    if (user && user.value?.id) {
        localStorage.setItem(
            "selectedFlight",
            JSON.stringify(selectedFlight.value),
        );
        router.push({
            name: "Checkout",
            query: {
                flight_id: selectedFlight.value?.leg?.ref_id,
                fares: JSON.stringify(selectedFares), // 👈 stringify array
                flight_provider: selectedFlight.value?.provider?.name || "N/A",
                flight_mode: "B2C",
                flight_source: 1,
                passenger_count: passengerCount.value,
                adults: parseInt(route.query.adults) || 1,
                children: parseInt(route.query.children) || 0,
                infants: parseInt(route.query.infants) || 0,
                price_margin: priceMargin.value || 0,
            },
        });
    } else {
        showLogin.value = true;
    }
}
function findSegmentName(segmentRefId, segments) {
    const segment = segments.find((seg) => seg.ref_id === segmentRefId);
    return segment ? `${segment.from.iata} → ${segment.to.iata}` : "N/A";
}
function selectFares(flightIdx, ref_id) {
    if (selectedFlight?.value?.provider?.name === 'travelport') {
        selectedFlight?.value?.leg?.flights.forEach((flight, index) => {

            selectedFares[index] = ref_id;

        });

    } else {
        selectedFares[flightIdx] = ref_id;
    }
    // console.log("Selected Fares:", selectedFares);
}

function openFareDetails(flight, fare) {
    selectedFareDetailsFlight.value = flight
    selectedFareDetails.value = fare
    showFareDetailsDialog.value = true
}

const getSelectedFare = (flightIndex) => {
    if (!selectedFares?.[flightIndex] || !selectedFlight?.value?.leg?.flights?.[flightIndex]?.fares) {
        return null
    }
    const selectedFareRefId = selectedFares?.[flightIndex]
    const flight = selectedFlight.value.leg.flights?.[flightIndex]

    // Find the fare with matching ref_id
    return flight.fares.find(fare => fare.ref_id === selectedFareRefId)
}

// Helper function to get segment-specific baggage policies
const getSegmentBaggagePolicies = (baggagePolicies, segmentRefId, travelerType) => {
    if (!baggagePolicies || !Array.isArray(baggagePolicies)) return []
    return baggagePolicies.filter(policy =>
        policy.segment_ref_id === segmentRefId &&
        policy.traveler_type === travelerType
    )
}

// Helper function to check if segment has baggage policies
const hasSegmentBaggagePolicies = (baggagePolicies, segmentRefId, travelerType) => {
    return getSegmentBaggagePolicies(baggagePolicies, segmentRefId, travelerType).length > 0
}

// Helper function to get general baggage policies (no specific segment)
const getGeneralBaggagePolicies = (baggagePolicies, travelerType) => {
    if (!baggagePolicies || !Array.isArray(baggagePolicies)) return []
    return baggagePolicies.filter(policy =>
        (!policy.segment_ref_id || policy.segment_ref_id === null) &&
        policy.traveler_type === travelerType
    )
}

// Helper function to check if has general baggage policies
const hasGeneralBaggagePolicies = (baggagePolicies, travelerType) => {
    return getGeneralBaggagePolicies(baggagePolicies, travelerType).length > 0
}



const convertedValues = ref({}); // Cache map: amount -> converted string

const convertedAmount = (amount) => {
    if (convertedValues.value[amount]) {
        // Already converted
        return convertedValues.value[amount];
    }

    // Start async conversion (don't block UI)
    fetchRate(amount)
        .then((result) => {
            convertedValues.value[amount] = result;
        })
        .catch(() => {
            convertedValues.value[amount] = "Error";
        });

    return "Loading...";
};
//------
const getUniqueBaggageTypes = (baggagePolicies, travelerType) => {
    if (!baggagePolicies || !Array.isArray(baggagePolicies)) return []
    const policiesForTraveler = baggagePolicies.filter(policy => policy.traveler_type === travelerType)
    const types = policiesForTraveler.map(policy => policy.type)
    return [...new Set(types)]
}

// Helper function to get first policy by traveler type and baggage type
const getFirstPolicyByType = (baggagePolicies, travelerType, baggageType) => {
    if (!baggagePolicies || !Array.isArray(baggagePolicies)) return null
    return baggagePolicies.find(policy =>
        policy.traveler_type === travelerType &&
        policy.type === baggageType

    )
}

// Get unique traveler types (keep existing function)
const getUniqueTravelerTypes = (baggagePolicies) => {
    if (!baggagePolicies || !Array.isArray(baggagePolicies)) return []
    const types = baggagePolicies.map(policy => policy.traveler_type)
    return [...new Set(types)]
}

// Get traveler type label (keep existing function)
const getTravelerTypeLabel = (travelerType) => {
    const labels = {
        'ADT': 'Adult',
        'ADLT': 'Adult',
        'ADT': 'Adult',
        'CHLD': 'Child',
        'CHD': 'Child',
        'CH': 'Child',
        'INFT': 'Infant',
        'INF': 'Infant',
        'child': 'Child',
        'adult': 'Adult',
        'infant': 'Infant'
    }
    return labels[travelerType] || travelerType
}

// Get default baggage description (keep existing function)
const getDefaultBaggageDescription = (policy) => {
    if (!policy) return 'Baggage allowance'
    if (policy.type === 'carry') {
        return `${policy.pieces || 1} piece(s) cabin baggage${policy.weight ? ` up to ${policy.weight}` : ''}`
    } else if (policy.type === 'checked') {
        return `${policy.pieces || 1} piece(s) checked baggage${policy.weight ? ` up to ${policy.weight}` : ''}`
    }
    return 'Baggage allowance'
}

const getNormalizedFarePolicies = (fare) => {
    if (!Array.isArray(fare?.fare_policies)) return []

    return fare.fare_policies
        .filter(Boolean)
        .map((policy, index) => {
            if (typeof policy === "string") {
                return {
                    id: `policy-${index}`,
                    title: policy,
                    description: "",
                    type: "included",
                    price: null,
                    price_type: null,
                    traveler_type: null,
                }
            }

            return {
                id: policy.id || `policy-${index}`,
                title: policy.title || policy.name || policy.label || policy.type || "Policy",
                description: policy.description || policy.text || "",
                type: policy.type || "included",
                price: policy.price ?? null,
                price_type: policy.price_type ?? null,
                traveler_type: policy.traveler_type ?? null,
            }
        })
}

const getFarePolicyCount = (fare) => getNormalizedFarePolicies(fare).length

const getFarePolicyPreview = (fare, limit = 2) =>
    getNormalizedFarePolicies(fare)
        .map((policy) => policy.title)
        .filter(Boolean)
        .slice(0, limit)

const getFareBaggageSummary = (fare, baggageType) => {
    const policies = (fare?.baggage_policies || []).filter(
        (policy) => policy?.type === baggageType,
    )

    if (!policies.length) return "Not Included"

    const descriptions = [
        ...new Set(
            policies
                .map((policy) => policy.description || getDefaultBaggageDescription(policy))
                .filter(Boolean),
        ),
    ]

    if (!descriptions.length) return "Included"

    return descriptions.length === 1
        ? descriptions[0]
        : `${descriptions[0]} +${descriptions.length - 1} more`
}

watch(
    selectedFlight,
    () => {
        //console.log("Selected flight changed:", selectedFlight.value);
        //console.log("Selected fares:", selectedFares.value);
        selectedFlight.value?.leg?.flights?.forEach((flight, index) => {
            if (flight?.fares?.length > 0) {
                selectedFares[index] = flight.fares[0].ref_id;
            }
        });
        loadingDetails.value = false;
    },
    { immediate: true, deep: true },
);
watch(
    () => modelValue.value.flightType,
    (newVal) => {
        if (newVal == "single") {
            modelValue.value.dateRange.end = null;
        } else if (newVal == "multi-city") {
            modelValue.value.dateRange.start = null;
            modelValue.value.dateRange.end = null;
        }
        initializeSearchParams();
    },
);
const setupFlightsParams = () => {
    flightType.value = modelValue.value.flightType;
    adults.value = modelValue.value.adult;
    children.value = modelValue.value.child;
    infants.value = modelValue.value.infant;
    classType.value = modelValue.value.classType;
    origin.value = modelValue.value.origin;
    destination.value = modelValue.value.destination;
    dateRange.value = modelValue.value.dateRange;
    flexiblePlusMinus3.value = modelValue.value.flexible_plus_minus_3;
    multiCityTrips.value = modelValue.value.multiCityTrips;
    const didSearch = searchFlights();
    if (didSearch) {
        isMobileModifyOpen.value = false;
    }
    // You can add additional logic here to handle the search action
};
onMounted(() => {
    initializeSearchParams();
    resetFlightParams();
    fetchCustomerSettings();
    fetchCustomerMarginValues();
    fetchMargins();
    showDialog.value = false;
    startCountdown(15 * 60 * 1000);
    if (user.value?.id) {
        fetchAgent();
    }
    store.dispatch("airport/" + FETCH_AIRPORTS);
    if (
        (flightType.value === "multi-city" &&
            multiCityTrips.value.some(
                (trip) => trip.origin && trip.destination && trip.date,
            )) ||
        (origin.value && destination.value && dateRange.value.start)
    ) {
        // fetchFlights();
        fetchProviders();
    }
});
watch(isLoggedIn, (newVal) => {
    if (newVal == true) {
        showLogin.value = false;
    }
});
</script>

<template>
    <!-- Container -->
    <div class="min-h-screen bg-gray-50">
        <!-- Main Content -->
        <!-- BACKDROP + MODAL -->
        <LoginMini v-if="showLogin" @close="showLogin = false" />
        <div class="bg-white shadow-sm overflow-visible">

            <!-- Tab Navigation - RESPONSIVE -->
            <div class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">
                <!-- Tabs would go here -->
            </div>

            <!-- Tab Content -->
            <div class="">
                <div v-if="activeTab === 'flights'" class="animate-fadeIn">
                    <div v-if="isLoading"
                        class="flex items-center gap-2 justify-center bg-white p-8 sm:p-24 rounded mt-4 sm:mt-8">
                        <Spinner />
                    </div>
                    <div v-else>

                        <div class="w-full sm:-mx-0">
                            <div class="fixed inset-x-0 top-0 z-[40] sm:hidden border-b border-slate-200 bg-white px-3 py-3 shadow-sm backdrop-blur">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center gap-2">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                                <PlaneTakeoff class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0">
                                                <p class="truncate text-[15px] font-semibold leading-tight text-slate-950">
                                                    {{ mobileRouteSummary }}
                                                </p>
                                                <p class="mt-0.5 truncate text-xs font-semibold text-slate-500">
                                                    {{ tripTypeLabel }} - {{ mobileDateSummary }} - {{ travelerSummary }} - {{ cabinClassLabel }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full bg-primary px-3 text-xs font-extrabold text-white shadow-sm"
                                        @click="toggleMobileModify"
                                    >
                                        <SlidersHorizontal class="h-4 w-4" />
                                        <span>{{ isMobileModifyOpen ? "Close" : "Edit" }}</span>
                                    </button>
                                </div>

                                <div v-if="isMobileModifyOpen" class="mt-3 max-h-[calc(100vh-5.25rem)] overflow-y-auto pb-4 scrollbar-hide">
                                    <div class="mobile-result-search-panel rounded-[1.5rem] border border-white/80 bg-gradient-to-b from-secondary/20 via-white to-secondary/5 p-2.5 ">
                                        <div class="grid grid-cols-3 rounded-full  p-1 shadow-inner shadow-slate-900/10">
                                            <button
                                                v-for="type in mobileTripTypes"
                                                :key="type.value"
                                                type="button"
                                                @click="setMobileTripType(type.value)"
                                                class="relative flex min-h-10 items-center justify-center rounded-full text-[13px] font-extrabold transition"
                                                :class="modelValue.flightType === type.value ? 'bg-secondary text-white shadow-lg shadow-secondary/25 ring-1 ring-white/80' : 'text-slate-700 hover:bg-white/80'"
                                            >
                                                <span>{{ type.label }}</span>
                                            </button>
                                        </div>

                                        <div class="pt-4">
                                            <template v-if="modelValue.flightType !== 'multi-city'">
                                                <div class="relative space-y-2">
                                                    <div class="mobile-result-pill flex min-h-[4.35rem] items-center gap-3 rounded-full bg-white px-3 py-2 shadow-lg shadow-slate-900/10">
                                                        <span class="mobile-result-icon-bubble">
                                                            <MapPin class="h-5 w-5" />
                                                        </span>
                                                        <Autocomplete
                                                            v-model="modelValue.origin"
                                                            :source="airports"
                                                            placeholder="Origin"
                                                            variant="admin-form"
                                                            dropdown-variant="mobile"
                                                            :search-results-limit="12"
                                                            hide-icon
                                                            class="mobile-result-route-input min-w-0 flex-1"
                                                        />
                                                    </div>

                                                    <div class="mobile-result-pill flex min-h-[4.35rem] items-center gap-3 rounded-full bg-white px-3 py-2 shadow-lg shadow-slate-900/10">
                                                        <span class="mobile-result-icon-bubble">
                                                            <MapPin class="h-5 w-5" />
                                                        </span>
                                                        <Autocomplete
                                                            v-model="modelValue.destination"
                                                            :source="airports"
                                                            placeholder="Destination"
                                                            variant="admin-form"
                                                            dropdown-variant="mobile"
                                                            :search-results-limit="12"
                                                            hide-icon
                                                            class="mobile-result-route-input min-w-0 flex-1"
                                                        />
                                                    </div>

                                                    <button
                                                        type="button"
                                                        @click="swapMobileRoute"
                                                        class="absolute right-6 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full border border-white bg-white text-secondary shadow-xl shadow-slate-900/20"
                                                        aria-label="Swap origin and destination"
                                                    >
                                                        <ArrowDownUp class="h-5 w-5" />
                                                    </button>
                                                </div>

                                                <div class="my-3 h-px bg-secondary/15"></div>

                                                <div class="mobile-result-pill flex min-h-[4.35rem] min-w-0 items-center gap-3 rounded-full bg-white px-3 py-2 shadow-lg shadow-slate-900/10">
                                                    <span class="mobile-result-icon-bubble">
                                                        <CalendarDays class="h-5 w-5" />
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <Calender
                                                            v-if="modelValue.flightType === 'one-way'"
                                                            v-model="modelValue.dateRange.start"
                                                            :minValue="todayDate"
                                                            variant="mobile"
                                                            :months-to-show="1"
                                                            :show-price-note="false"
                                                            hide-icon
                                                            class="mobile-result-date-input min-w-0 flex-1"
                                                            placeholder="Departure date"
                                                        />
                                                        <DateRangePicker
                                                            v-else
                                                            v-model="modelValue.dateRange"
                                                            :minValue="todayDate"
                                                            variant="mobile"
                                                            hide-icon
                                                            class="mobile-result-return-date mobile-result-date-input min-w-0 flex-1"
                                                        />
                                                    </div>
                                                </div>
                                            </template>

                                            <div v-else class="space-y-2">
                                                <div
                                                    v-for="(trip, index) in modelValue.multiCityTrips"
                                                    :key="index"
                                                    class="space-y-2 rounded-[1.25rem] bg-white/85 p-2.5 shadow-lg shadow-slate-900/10"
                                                >
                                                    <div class="flex items-center justify-between gap-3">
                                                        <p class="text-xs font-extrabold uppercase text-secondary">
                                                            Trip {{ index + 1 }}
                                                        </p>
                                                        <button
                                                            v-if="modelValue.multiCityTrips.length > 2"
                                                            type="button"
                                                            class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-50 hover:text-red-500"
                                                            :aria-label="`Remove trip ${index + 1}`"
                                                            @click="removeMobileTrip(index)"
                                                        >
                                                            <X class="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                    <div class="mobile-result-pill flex items-center gap-3 rounded-full bg-white px-3 py-2 shadow-md shadow-slate-900/10">
                                                        <span class="mobile-result-icon-bubble">
                                                            <MapPin class="h-5 w-5" />
                                                        </span>
                                                        <Autocomplete
                                                            v-model="trip.origin"
                                                            :source="airports"
                                                            placeholder="Origin"
                                                            variant="admin-form"
                                                            dropdown-variant="mobile"
                                                            :search-results-limit="12"
                                                            hide-icon
                                                            class="mobile-result-route-input min-w-0 flex-1"
                                                        />
                                                    </div>
                                                    <div class="mobile-result-pill flex items-center gap-3 rounded-full bg-white px-3 py-2 shadow-md shadow-slate-900/10">
                                                        <span class="mobile-result-icon-bubble">
                                                            <MapPin class="h-5 w-5" />
                                                        </span>
                                                        <Autocomplete
                                                            v-model="trip.destination"
                                                            :source="airports"
                                                            placeholder="Destination"
                                                            variant="admin-form"
                                                            dropdown-variant="mobile"
                                                            :search-results-limit="12"
                                                            hide-icon
                                                            class="mobile-result-route-input min-w-0 flex-1"
                                                        />
                                                    </div>
                                                    <div class="mobile-result-pill flex items-center gap-3 rounded-full bg-white px-3 py-2 shadow-md shadow-slate-900/10">
                                                        <span class="mobile-result-icon-bubble">
                                                            <CalendarDays class="h-5 w-5" />
                                                        </span>
                                                        <Calender
                                                            v-model="trip.date"
                                                            :minValue="index === 0 ? todayDate : modelValue.multiCityTrips[index - 1]?.date || todayDate"
                                                            variant="mobile"
                                                            :months-to-show="1"
                                                            :show-price-note="false"
                                                            hide-icon
                                                            class="mobile-result-date-input min-w-0 flex-1"
                                                            placeholder="Departure date"
                                                        />
                                                    </div>
                                                </div>

                                                <button
                                                    v-if="modelValue.multiCityTrips.length < MAX_MULTI_CITY_TRIPS"
                                                    type="button"
                                                    class="flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-dashed border-secondary/40 bg-white text-sm font-bold text-secondary shadow-sm"
                                                    @click="addMobileTrip"
                                                >
                                                    <Plus class="h-4 w-4" />
                                                    <span>Add another city</span>
                                                </button>
                                            </div>

                                            <div class="mt-3 grid grid-cols-2 gap-2.5">
                                                <Popover>
                                                    <PopoverTrigger as-child>
                                                        <button
                                                            type="button"
                                                            class="mobile-result-pill flex min-h-14 items-center gap-2.5 rounded-[1.15rem] bg-white px-3 text-left shadow-lg shadow-slate-900/10"
                                                        >
                                                            <UsersRound class="h-6 w-6 shrink-0 text-secondary" />
                                                            <span class="min-w-0 truncate text-sm font-semibold text-slate-800">
                                                                {{ mobileEditTravelerLabel }}
                                                            </span>
                                                        </button>
                                                    </PopoverTrigger>
                                                    <PopoverContent align="start" class="z-[90] w-72 rounded-lg border-slate-200 bg-white p-4 shadow-xl">
                                                        <div class="space-y-4">
                                                            <div
                                                                v-for="item in [
                                                                    { key: 'adults', label: 'Adults', value: modelValue.adult },
                                                                    { key: 'children', label: 'Children', value: modelValue.child },
                                                                    { key: 'infants', label: 'Infants', value: modelValue.infant },
                                                                ]"
                                                                :key="item.key"
                                                                class="flex items-center justify-between gap-4"
                                                            >
                                                                <span class="text-sm font-semibold text-slate-700">{{ item.label }}</span>
                                                                <span class="flex items-center gap-3">
                                                                    <button
                                                                        type="button"
                                                                        class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-lg font-bold text-slate-700"
                                                                        @click="updateMobileTraveler(item.key, 'decrement')"
                                                                    >
                                                                        -
                                                                    </button>
                                                                    <span class="w-5 text-center text-sm font-bold">{{ item.value }}</span>
                                                                    <button
                                                                        type="button"
                                                                        class="flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 text-lg font-bold text-slate-700"
                                                                        @click="updateMobileTraveler(item.key, 'increment')"
                                                                    >
                                                                        +
                                                                    </button>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </PopoverContent>
                                                </Popover>

                                                <label class="mobile-result-pill flex min-h-14 items-center gap-2.5 rounded-[1.15rem] bg-white px-3 shadow-lg shadow-slate-900/10">
                                                    <CircleUserRound class="h-6 w-6 shrink-0 text-secondary" />
                                                    <select
                                                        v-model="modelValue.classType"
                                                        class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm font-semibold text-slate-800 outline-none focus:ring-0"
                                                    >
                                                        <option
                                                            v-for="option in mobileCabinOptions"
                                                            :key="option.value"
                                                            :value="option.value"
                                                        >
                                                            {{ option.label }}
                                                        </option>
                                                    </select>
                                                </label>
                                            </div>

                                            <label class="mt-3 flex items-center gap-3 text-sm font-medium text-slate-700">
                                                <span
                                                    class="relative flex h-7 w-14 items-center rounded-full p-1 transition"
                                                    :class="modelValue.flexible_plus_minus_3 ? 'bg-secondary' : 'bg-slate-300'"
                                                >
                                                    <span
                                                        class="h-5 w-5 rounded-full bg-white shadow transition"
                                                        :class="modelValue.flexible_plus_minus_3 ? 'translate-x-7' : 'translate-x-0'"
                                                    ></span>
                                                </span>
                                                <input v-model="modelValue.flexible_plus_minus_3" type="checkbox" class="sr-only" />
                                                <span>Flexible dates</span>
                                            </label>

                                            <div v-if="inputErrors && inputErrors.length" class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                                                {{ inputErrors[0] }}
                                            </div>

                                            <button
                                                type="button"
                                                @click="setupFlightsParams"
                                                class="mt-4 flex min-h-14 w-full items-center justify-center gap-3 rounded-full bg-secondary px-4 text-base font-extrabold text-white shadow-xl shadow-secondary/30"
                                            >
                                                <Search class="h-5 w-5" />
                                                <span>Search flights</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="md:h-[66px] sm:hidden"></div>

                            <div class="hidden sm:block sm:px-0">
                                <FlightFilterCard :countdown="countdown" v-model="modelValue"
                                    @search="setupFlightsParams" class="w-full"
                                      />
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div v-if="isSearching" class="w-full mt-2 container ">
                            <!-- Message with loader and progress -->
                            <div class="flex items-center justify-between mb-3">
                                <!-- Left side: Loader + Message -->
                                <div class="flex items-center gap-3 flex-1">
                                    <!-- Loader circle -->
                                    <div class="relative flex-shrink-0">
                                        <!-- Outer circle -->
                                        <div class="w-6 h-6 border-2 border-primary/20 rounded-full"></div>
                                        <!-- Spinning arc -->
                                        <div :class="[
                                            'absolute top-0 left-0 w-6 h-6 border-2 rounded-full border-t-primary border-r-transparent border-b-transparent border-l-transparent',
                                            progress > 0 ? 'animate-spin-slow' : 'animate-spin'
                                        ]"></div>
                                    </div>

                                    <!-- Message text -->
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                                            {{ progress === 100 ? 'Found best flights!' : 'Searching for best flights...' }}
                                        </span>

                                    </div>
                                </div>

                                <!-- Right side: Progress percentage -->
                                <div class="flex-shrink-0">
                                    <span class="text-sm font-bold text-primary">{{ progress }}%</span>
                                </div>
                            </div>

                            <!-- Progress bar -->
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded h-6 relative overflow-hidden">
                                <!-- Background track -->
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-gray-300/50 to-gray-300/30 dark:from-gray-600/50 dark:to-gray-600/30">
                                </div>

                                <!-- Progress fill -->
                                <div :class="[
                                    'h-full rounded transition-all duration-700 ease-out relative overflow-hidden',
                                    progress === 0 ? 'bg-primary' : 'bg-primary'
                                ]" :style="{ width: progress > 0 ? progress + '%' : '25%' }">
                                    <!-- Pulse effect -->
                                    <div v-if="progress === 0"
                                        class="absolute inset-0 bg-white/30 rounded animate-pulse"></div>

                                    <!-- Shimmer effect -->
                                    <div :class="[
                                        'absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent',
                                        progress === 0 ? 'animate-light-sweep-slow' : 'animate-light-sweep'
                                    ]"></div>
                                </div>

                                <!-- Text inside progress bar -->
                                <div class="absolute inset-0 flex items-center justify-center pointer-events-none">

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other Tab Contents -->
                <div v-else-if="activeTab === 'importPnr'" class="animate-fadeIn">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Enter PNR to import.
                    </h3>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4 p-2 sm:p-4">
                        <Input v-model="pnr" type="text" class="w-full sm:w-[200px]" placeholder="PNR" />
                        <Button @click="importPnr(pnr)" class="w-full sm:w-auto">Import PNR</Button>
                    </div>
                </div>

                <div v-else-if="activeTab === 'hotels'" class="animate-fadeIn">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Find the perfect hotel
                    </h3>
                    <p class="text-gray-600 mb-4">Coming soon</p>
                </div>

                <div v-else-if="activeTab === 'cars'" class="animate-fadeIn">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Rent a car
                    </h3>
                    <p class="text-gray-600 mb-4">Coming soon</p>
                </div>

                <div v-else-if="activeTab === 'activities'" class="animate-fadeIn">
                    <p class="text-gray-600 mb-4">Coming soon</p>
                </div>

                <div v-else-if="activeTab === 'packages'" class="animate-fadeIn">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Find travel packages
                    </h3>
                    <p class="text-gray-600 mb-4">Coming Soon.</p>
                </div>
            </div>
        </div>
        <!-- FILTER STRIP -->
        <!-- FILTER STRIP - PROFESSIONAL & FULLY WORKING -->
        <div v-if="filteredFlights && !isLoading && allFlights.length > 0"
            class="bg-white border hidden sm:block border-gray-200 px-3 py-2 mb-8 shadow-md">
            <div class="flex flex-wrap  container justify-center items-center">
                <!-- Main Visible Filters -->
                <div
                    class="flex w-full flex-wrap  justify-between items-center  rounded py-2 divide-x divide-gray-300">
                    <!-- Price -->
                    <div class="relative px-2">
                        <button @click="
                            activeFilter =
                            activeFilter === 'price' ? null : 'price'
                            "
                            class="flex items-center gap-2.5 px-5 py-3 text-sm font-medium text-gray-700 hover:text-primary transition">
                            <BadgeDollarSign class="w-5 h-5" />
                            Price
                            <ChevronDown class="w-4 h-4 transition-transform" :class="{
                                'rotate-180': activeFilter === 'price',
                            }" />
                        </button>

                        <div v-if="activeFilter === 'price'"
                            class="absolute left-1/2 -translate-x-1/2 top-full mt-4 w-80 bg-white border border-gray-200 rounded-2xl shadow-2xl p-6 z-50"
                            @click.stop>
                            <div class="flex justify-between mb-4">
                                <span class="text-sm font-medium text-gray-600">Max Price</span>
                                <span class="text-lg font-bold text-primary">{{
                                    formatAmount(maxPrice || maxPriceLimit)
                                    }}</span>
                            </div>
                            <input type="range" :min="minPriceLimit" :max="maxPriceLimit" v-model="maxPrice"
                                @input="filterByPrice"
                                class="w-full h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                            <div class="flex justify-between mt-3 text-xs text-gray-500">
                                <span>{{ formatAmount(minPriceLimit) }}</span>
                                <span>{{ formatAmount(maxPriceLimit) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Stops -->
                    <div class="relative px-2">
                        <button @click="
                            activeFilter =
                            activeFilter === 'stops' ? null : 'stops'
                            "
                            class="flex items-center gap-2.5 px-5 py-3 text-sm font-medium text-gray-700 hover:text-primary transition">
                            <GitCommitHorizontal class="w-5 h-5" />
                            Stops
                            <ChevronDown class="w-4 h-4 transition-transform" :class="{
                                'rotate-180': activeFilter === 'stops',
                            }" />
                        </button>

                        <div v-if="activeFilter === 'stops'"
                            class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-64 bg-white border border-gray-200 rounded shadow-xl p-4 z-50"
                            @click.stop>
                            <p class="text-xs font-semibold text-gray-700 mb-3 text-center uppercase tracking-wide">
                                Number of Stops
                            </p>

                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 border rounded cursor-pointer
             text-xs font-semibold text-gray-700
             hover:bg-primary/5 hover:border-primary/40 transition">
                                    <input type="radio" value="all" v-model="selectedStops" @change="filterByStops"
                                        class="accent-primary scale-90" />
                                    <span>All</span>
                                </label>

                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 border rounded cursor-pointer
             text-xs font-medium text-gray-700
             hover:bg-primary/5 hover:border-primary/40 transition">
                                    <input type="radio" value="0" v-model="selectedStops" @change="filterByStops"
                                        class="accent-primary scale-90" />
                                    <span>Non-Stop</span>
                                </label>

                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 border rounded cursor-pointer
             text-xs font-medium text-gray-700
             hover:bg-primary/5 hover:border-primary/40 transition">
                                    <input type="radio" value="1" v-model="selectedStops" @change="filterByStops"
                                        class="accent-primary scale-90" />
                                    <span>1 Stop</span>
                                </label>

                                <label class="flex items-center justify-center gap-1.5 py-2 px-2 border rounded cursor-pointer
             text-xs font-medium text-gray-700
             hover:bg-primary/5 hover:border-primary/40 transition">
                                    <input type="radio" value="2" v-model="selectedStops" @change="filterByStops"
                                        class="accent-primary scale-90" />
                                    <span>2+ Stops</span>
                                </label>
                            </div>
                        </div>

                    </div>

                    <!-- Airlines -->
                    <div class="relative px-2">
                        <button @click="
                            activeFilter =
                            activeFilter === 'airline'
                                ? null
                                : 'airline'
                            "
                            class="flex items-center gap-2.5 px-5 py-3 text-sm font-medium text-gray-700 hover:text-primary transition">
                            <Plane class="w-5 h-5" />
                            Airlines
                            {{
                                selectedAirline.length
                                    ? `(${selectedAirline.length})`
                                    : ""
                            }}
                            <ChevronDown class="w-4 h-4 transition-transform" :class="{
                                'rotate-180': activeFilter === 'airline',
                            }" />
                        </button>

                        <!-- Airlines Dropdown -->
                        <div v-if="activeFilter === 'airline'"
                            class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-80 bg-white border border-gray-200 rounded shadow-xl p-4 z-50 max-h-80 overflow-y-auto"
                            @click.stop>
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-800 uppercase tracking-wide">
                                    Airlines
                                </h4>

                                <button @click="
                                    selectedAirline = [];
                                filterByAirline();
                                activeFilter = null;
                                " class="text-xs text-primary font-medium hover:underline">
                                    Clear
                                </button>
                            </div>

                            <!-- Airline List -->
                            <div class="space-y-2">
                                <label v-for="airline in availableAirlines" :key="airline.id" class="flex items-center gap-3 px-3 py-2 bg-gray-50 border border-transparent rounded
             cursor-pointer text-xs
             hover:bg-primary/5 hover:border-primary/30 transition">
                                    <input type="checkbox" v-model="selectedAirline" :value="airline.id"
                                        @change="filterByAirline" class="accent-primary w-4 h-4" />

                                    <span class="font-medium text-gray-800 flex-1 truncate">
                                        {{ airline.name }}
                                    </span>
                                </label>
                            </div>
                        </div>

                    </div>

                    <!-- Duration -->
                    <div class="relative px-2">
                        <button @click="
                            activeFilter =
                            activeFilter === 'duration'
                                ? null
                                : 'duration'
                            "
                            class="flex items-center gap-2.5 px-5 py-3 text-sm font-medium text-gray-700 hover:text-primary transition">
                            <ClockIcon class="w-5 h-5" />
                            Duration
                            {{
                                maxDurationFilter
                                    ? `
                            < ${maxDurationFilter}h` : "" }} <ChevronDown class="w-4 h-4 transition-transform" :class="{
                                'rotate-180': activeFilter === 'duration',
                            }" />
                        </button>

                        <!-- Duration Dropdown -->
                        <div v-if="activeFilter === 'duration'"
                            class="absolute left-1/2 -translate-x-1/2 top-full mt-4 w-80 bg-white border border-gray-200 rounded-2xl shadow-2xl p-6 z-50"
                            @click.stop>
                            <h4 class="text-lg font-semibold text-gray-800 mb-5">
                                Maximum Flight Duration
                            </h4>
                            <div class="space-y-4">
                                <input type="range" :min="minDuration" :max="maxDuration" v-model="maxDurationFilter"
                                    @input="filterByDuration"
                                    class="w-full h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ minDuration }} hours</span>
                                    <span class="font-bold text-primary">{{
                                        maxDurationFilter|| maxDuration
                                    }}
                                        hours</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Refundable -->
                    <div class="relative px-2">
                        <button @click="
                            activeFilter =
                            activeFilter === 'refundable'
                                ? null
                                : 'refundable'
                            "
                            class="flex items-center gap-2.5 px-5 py-3 text-sm font-medium text-gray-700 hover:text-primary transition">
                            <SquareCheckBig class="w-5 h-5" />
                            Refundable
                            {{
                                refundableFilter !== "all"
                                    ? `(${refundableFilter})`
                                    : ""
                            }}
                            <ChevronDown class="w-4 h-4 transition-transform" :class="{
                                'rotate-180': activeFilter === 'refundable',
                            }" />
                        </button>

                        <!-- Refundable Dropdown -->
                        <div v-if="activeFilter === 'refundable'"
                            class="absolute left-1/2 -translate-x-1/2 top-full mt-3 w-64 bg-white border border-gray-200 rounded shadow-xl p-4 z-50"
                            @click.stop>
                            <!-- Header -->
                            <h4 class="text-xs font-semibold text-gray-800 mb-3 text-center uppercase tracking-wide">
                                Refund Policy
                            </h4>

                            <!-- Options -->
                            <div class="space-y-2">
                                <label class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 border border-transparent rounded
             cursor-pointer text-xs font-medium text-gray-800
             hover:bg-primary/5 hover:border-primary/30 transition">
                                    <input type="radio" value="all" v-model="refundableFilter"
                                        @change="filterByRefundable" class="accent-primary w-4 h-4" />
                                    <span>All Flights</span>
                                </label>

                                <label class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 border border-transparent rounded
             cursor-pointer text-xs font-medium text-gray-800
             hover:bg-primary/5 hover:border-primary/30 transition">
                                    <input type="radio" value="refundable" v-model="refundableFilter"
                                        @change="filterByRefundable" class="accent-primary w-4 h-4" />
                                    <span>Refundable Only</span>
                                </label>

                                <label class="flex items-center gap-2.5 px-3 py-2 bg-gray-50 border border-transparent rounded
             cursor-pointer text-xs font-medium text-gray-800
             hover:bg-primary/5 hover:border-primary/30 transition">
                                    <input type="radio" value="non-refundable" v-model="refundableFilter"
                                        @change="filterByRefundable" class="accent-primary w-4 h-4" />
                                    <span>Non-Refundable Only</span>
                                </label>
                            </div>
                        </div>

                        

                    </div>
                    <div class="flex items-end gap-3 pl-2">
                        <Button variant="outline" @click="showMoreFilters = true"
                            >
                            <Ellipsis class="w-5 h-5" />
                        </Button>

                        <!-- Reset Button -->
                        <Button variant="outline" @click="resetAllFilters"
                            >
                             <ListRestart class="w-5 h-5" />
                        </Button>

                         <Button variant="outline" @click="isShownMarginInput = !isShownMarginInput">
                                <Zap class="w-5 h-5" />
                            </Button>
                    </div>
                    
                </div>
                
                <!-- More Filters Button -->
                 

            </div>
            <div class="flex container justify-end items-end">
                <Input v-if="isShownMarginInput" v-model="priceMargin" type="number" class=" w-full sm:w-[200px] border border-gray-300 rounded-md px-2 py-1 focus:outline-none focus:border-none"
                                placeholder="Price Margin" />
        </div>
            </div>

        <div v-if="filteredFlights && !isLoading && allFlights.length > 0"
            class="border-b border-gray-200 bg-white px-2 py-3 shadow-sm sm:px-4 sm:py-4">
            <div class="container mx-auto flex items-center justify-between gap-2 sm:gap-4">
                <!-- Left: Showing results -->
                <div class=" text-lg font-bold text-gray-700 sm:block">
                    Showing
                    <span class="font-semibold text-primary">{{
                        filteredFlights.length
                        }}</span>
                    results
                </div>

                <!-- Center: Cheapest | Fastest | Best Value -->
                <div class="hidden sm:flex items-center justify-center bg-gray-100 rounded-full p-1">
                    <Tabs default-value="cheapest" class="w-full">
                        <TabsList class="grid grid-cols-3 w-full bg-transparent p-1 gap-1">
                            <!-- Cheapest Tab -->
                            <TabsTrigger value="cheapest"
                                class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-full text-sm font-medium transition-all data-[state=active]:bg-white data-[state=active]:shadow-sm data-[state=active]:text-primary"
                                @click="filteredFlights = [...allFlights].sort(
                                    (a, b) =>
                                        calculateTotalFare(a) -
                                        calculateTotalFare(b),
                                )">
                                <BadgeDollarSign class="w-5 h-5 text-red-500" />
                                <span>Cheapest</span>
                                <span class="ml-1 font-bold">
                                    PKR
                                    {{
                                        formatAmount(
                                            Math.min(
                                                ...allFlights.map((f) =>
                                                    calculateTotalFare(f),
                                                ),
                                            ),
                                        )
                                    }}
                                </span>
                            </TabsTrigger>

                            <!-- Fastest Tab -->
                            <TabsTrigger value="fastest"
                                class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-full text-sm font-medium transition-all data-[state=active]:bg-white data-[state=active]:shadow-sm data-[state=active]:text-primary"
                                @click="filteredFlights = [...allFlights].sort((a, b) => {
                                    const getDuration = (flight) =>
                                        flight.leg.flights.reduce(
                                            (sum, leg) =>
                                                sum + (leg.travel_time || 0),
                                            0,
                                        );
                                    return getDuration(a) - getDuration(b);
                                })">
                                <Zap class="w-5 h-5 text-red-500" />
                                <span>Fastest</span>
                                <span class="ml-1 font-bold">
                                    PKR
                                    {{
                                        formatAmount(
                                            calculateTotalFare(
                                                [...allFlights].sort((a, b) => {
                                                    const getDuration = (f) =>
                                                        f.leg.flights.reduce(
                                                            (s, l) =>
                                                                s +
                                                                (l.travel_time || 0),
                                                            0,
                                                        );
                                                    return (
                                                        getDuration(a) - getDuration(b)
                                                    );
                                                })[0],
                                            ),
                                        )
                                    }}
                                </span>
                            </TabsTrigger>

                            <!-- Best Value Tab -->
                            <TabsTrigger value="bestvalue"
                                class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-full text-sm font-medium transition-all data-[state=active]:bg-white data-[state=active]:shadow-sm data-[state=active]:text-primary"
                                @click="filteredFlights = [...allFlights].sort(
                                    (a, b) =>
                                        calculateTotalFare(a) -
                                        calculateTotalFare(b),
                                )">
                                <SquareCheckBig class="w-5 h-5 text-red-500" />
                                <span>Best Value</span>
                                <span class="ml-1 font-bold">
                                    PKR
                                    {{
                                        formatAmount(
                                            Math.min(
                                                ...allFlights.map((f) =>
                                                    calculateTotalFare(f),
                                                ),
                                            ),
                                        )
                                    }}
                                </span>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>

                <!-- Mobile: Sort and filter controls -->
                <div class="flex min-w-0 items-center justify-end gap-1.5 sm:hidden">
                    <label class="relative flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-md border border-primary px-2 py-1.5 text-xs font-semibold text-primary">
                        <ArrowDownUp class="h-4 w-4 shrink-0" />
                        <span>Sort By</span>
                        <select @change="sortFlightsByPrice($event.target.value)"
                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                            aria-label="Sort flights by price">
                            <option value="low">Price - Low To High</option>
                            <option value="high">Price - High To Low</option>
                        </select>
                    </label>

                    <button type="button" @click="showMoreFilters = true"
                        class="flex min-h-9 items-center gap-1.5 whitespace-nowrap rounded-md border border-primary px-2 py-1.5 text-xs font-semibold text-primary">
                        <SlidersHorizontal class="h-4 w-4 shrink-0" />
                        <span>Filters</span>
                    </button>
                </div>

                <!-- Right: Desktop sort dropdown -->
                <div class="hidden items-center gap-2 text-sm sm:flex">
                    <ArrowDownUp class="w-4 h-4 text-gray-500" />
                    <span class="text-gray-600">Sort by:</span>
                    <select @change="sortFlightsByPrice($event.target.value)"
                        class="font-medium text-gray-900 bg-transparent border-none outline-none cursor-pointer">
                        <option value="low">Price - Low To High</option>
                        <option value="high">Price - High To Low</option>
                    </select>
                </div>
            </div>
        </div>
        <!-- <button
  @click="showMoreFilters = true"
  class="fixed bottom-4 left-1/2 transform -translate-x-1/2 sm:hidden bg-primary text-white font-semibold px-6 py-3 rounded-full shadow-lg hover:bg-primary/90 transition z-50"
>
  Filters
</button>             -->
        <!-- MORE FILTERS MODAL -->
        <Dialog v-model:open="showMoreFilters">
            <DialogContent
                class="w-full max-w-md sm:max-w-3xl max-h-[90vh] overflow-y-auto bg-white p-4 sm:p-8 rounded-2xl sm:rounded-3xl">

                <!-- Header -->
                <div
                    class="sticky top-0 bg-white border-b border-gray-200 px-4 sm:px-8 py-4 sm:py-6 flex justify-between items-center z-10">
                    <div>
                        <DialogTitle class="text-xl sm:text-2xl font-bold text-gray-900">More Filters</DialogTitle>
                        <DialogDescription class="text-sm sm:text-base text-gray-600 mt-1">
                            Refine your flight search with additional options
                        </DialogDescription>
                    </div>
                    <button @click="resetAllFilters(); showMoreFilters = false;"
                        class="px-3 py-1.5 sm:px-5 sm:py-2.5 bg-primary text-white rounded text-xs sm:text-sm font-semibold hover:bg-primary/90 transition">
                        Clear All
                    </button>
                </div>

                <!-- Filters Grid -->
                <div class="p-4 sm:p-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-8">

                        <!-- Departure Time -->
                        <div>
                            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Departure Time</h3>
                            <div class="space-y-2 sm:space-y-3">
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="departureTimes" value="morning"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">12:00 AM - 06:00 AM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="departureTimes" value="morningLate"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">06:00 AM - 12:00 PM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="departureTimes" value="afternoon"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">12:00 PM - 06:00 PM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="departureTimes" value="night"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">06:00 PM - 12:00 AM</span>
                                </label>
                            </div>
                        </div>

                        <!-- Arrival Time -->
                        <div>
                            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Arrival Time</h3>
                            <div class="space-y-2 sm:space-y-3">
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="arrivalTimes" value="morning"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">12:00 AM - 06:00 AM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="arrivalTimes" value="morningLate"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">06:00 AM - 12:00 PM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="arrivalTimes" value="afternoon"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">12:00 PM - 06:00 PM</span>
                                </label>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="arrivalTimes" value="night"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700">06:00 PM - 12:00 AM</span>
                                </label>
                            </div>
                        </div>

                        <!-- Airlines -->
                        <div>
                            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Airlines</h3>
                            <div class="space-y-2 sm:space-y-3 max-h-40 sm:max-h-64 overflow-y-auto pr-2">
                                <label v-for="airline in availableAirlines" :key="airline.id"
                                    class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="selectedAirline" :value="airline.id"
                                        @change="filterByAirline"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-xs sm:text-sm text-gray-700 flex-1">{{ airline.name }}</span>
                                </label>
                            </div>
                        </div>

                        <!-- Price + Duration + Stops + Refundable -->
                        <div class="space-y-6 sm:space-y-8">
                            <!-- Price Range -->
                            <div>
                                <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Price Range</h3>
                                <div class="space-y-2 sm:space-y-4">
                                    <div class="flex justify-between text-xs sm:text-sm text-gray-600">
                                        <span>{{ formatAmount(minPriceLimit) }}</span>
                                        <span>{{ formatAmount(maxPriceLimit) }}</span>
                                    </div>
                                    <input type="range" :min="minPriceLimit" :max="maxPriceLimit" v-model="maxPrice"
                                        @input="filterByPrice"
                                        class="w-full h-2 sm:h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                                    <div class="text-center text-sm sm:text-lg font-bold text-primary">{{
                                        formatAmount(maxPrice || maxPriceLimit) }}</div>
                                </div>
                            </div>

                            <!-- Flight Duration -->
                            <div>
                                <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Flight Duration
                                </h3>
                                <div class="space-y-2 sm:space-y-4">
                                    <input type="range" min="0" :max="maxDuration" v-model="maxDurationFilter"
                                        @input="filterByDuration"
                                        class="w-full h-2 sm:h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                                    <div class="text-center text-xs sm:text-sm text-primary">Up to {{ maxDurationFilter
                                        || maxDuration }} hours</div>
                                </div>
                            </div>

                            <!-- Stops -->
                            <div>
                                <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Stops</h3>
                                <div class="space-y-2 sm:space-y-3">
                                    <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                        <input type="checkbox" value="0" v-model="selectedStopsArray"
                                            class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                        <span class="text-xs sm:text-sm text-gray-700">Non-Stop</span>
                                    </label>
                                    <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                        <input type="checkbox" value="1" v-model="selectedStopsArray"
                                            class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                        <span class="text-xs sm:text-sm text-gray-700">1 Stop</span>
                                    </label>
                                    <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                        <input type="checkbox" value="2" v-model="selectedStopsArray"
                                            class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                        <span class="text-xs sm:text-sm text-gray-700">2+ Stops</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Refundable -->
                            <div>
                                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="onlyRefundable" @change="filterByRefundable"
                                        class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                                    <span class="text-sm sm:text-base font-medium text-gray-800">Refundable Only</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <DialogFooter
                    class="sticky bottom-0 bg-white border-t border-gray-200 px-4 sm:px-8 py-4 sm:py-6 flex justify-end gap-3 sm:gap-6">
                    <Button @click="showMoreFilters = false" variant="outline"
                        class="px-6 py-2 sm:px-8 sm:py-3 text-sm sm:text-base">Cancel</Button>
                    <Button @click="showMoreFilters = false; applyAllFilters();"
                        class="px-8 py-2 sm:px-10 sm:py-3 bg-primary text-white font-semibold text-sm sm:text-base">Apply
                        Filters</Button>
                </DialogFooter>

            </DialogContent>
        </Dialog>


        <!-- Carousel Section -->
        <!-- <div
            v-if="!isLoading && allFlights"
            class="mt-4 sm:mt-6 px-2 sm:px-0 container"
        >
            <Carousel
                class="relative z-10 w-full overflow-hidden"
                :opts="{ align: 'start', containScroll: 'trimSnaps' }"
            >
                <div class="absolute left-0 top-1/2 -translate-y-1/2 z-10">
                    <CarouselPrevious
                        class="bg-white/90 shadow-md rounded-full p-1.5 sm:p-2 hover:bg-white"
                    />
                </div>

                <CarouselContent class="-ml-2 flex">
                    <CarouselItem
                        v-for="flight in cheapestFlightsByAirline"
                        :key="flight.id"
                        class="pl-2 flex-shrink-0 w-[88%] /* 1 card on xs */ sm:w-[48%] /* 2 cards on sm */ md:w-[31%] /* 3 cards on md */ lg:w-[15%] /* ~6 cards on lg */ max-w-[220px] /* upper bound */"
                    >
                        <div class="h-full">
                            <div
                                @click="openSooperFlightDetails(flight)"
                                class="bg-white border border-gray-200 p-3 sm:p-4 rounded hover:shadow-md transition-all duration-200 cursor-pointer select-none h-full flex flex-col"
                            >
                                
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center"
                                    >
                                        <img
                                            :src="
                                                flight?.id
                                                    ? flight?.legs[0]?.stops[0]
                                                          ?.airline?.logo_url
                                                    : flight?.leg?.flights[0]
                                                          ?.marketing_carrier
                                                          ?.logo
                                            "
                                            alt=""
                                            class="w-5 h-5 sm:w-6 sm:h-6 lg:w-7 lg:h-7 object-contain"
                                        />
                                    </div>
                                    <span
                                        class="text-xs font-light text-gray-800 line-clamp-1"
                                    >
                                        {{
                                            flight?.id
                                                ? flight?.legs[0]?.stops[0]
                                                      ?.airline?.name
                                                : flight?.leg?.flights[0]
                                                      ?.marketing_carrier?.name
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-auto pt-2 border-t border-gray-100"
                                >
                                    <p class="text-base font-bold text-primary">
                                        {{
                                            formatAmount(
                                                calculateTotalFare(flight),
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Best available price
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CarouselItem>
                </CarouselContent>

                <div class="absolute right-0 top-1/2 -translate-y-1/2 z-10">
                    <CarouselNext
                        class="bg-white/90 shadow-md rounded-full p-1.5 sm:p-2 hover:bg-white"
                    />
                </div>
            </Carousel>
        </div> -->

        <!-- RESPONSIVE MAIN LAYOUT -->
        <div class="px-2 sm:px-0 mt-4 sm:mt-6 container">
            <!-- Added mobile filter toggle button -->
            <div class="mb-4 hidden sm:block lg:hidden">
                <Button @click="showMoreFilters = true" variant="outline" class="flex items-center gap-2 w-full">
                    <SlidersHorizontal class="w-5 h-5" />
                    <span>{{
                        isFilterOpen ? "Hide Filters" : "Show Filters"
                        }}</span>
                </Button>
            </div>

            <div class="flex flex-col lg:flex-row gap-4 lg:gap-x-10">

                <!-- Main Flights List Section -->
                <div class="w-full">
                    <!-- Loading State -->
                    <div v-if="isLoading" class="space-y-3 sm:space-y-0">
                        <div v-for="(item, index) in 5" :key="index"
                            class="flex min-h-[200px] w-full items-start gap-3 overflow-hidden rounded-lg border bg-white p-3 sm:h-[200px] sm:justify-between sm:gap-0 sm:overflow-visible sm:rounded sm:p-4">
                            <div class="flight-skeleton-column min-w-0 flex-1 sm:flex-none">
                                <div class="flex items-center gap-x-3">
                                    <Skeleton width="150px" height="30px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100}`" />
                                </div>
                                <Skeleton width="60px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 50}`" />
                                <Skeleton width="90px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 100}`" />
                                <Skeleton width="200px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 150}`" />
                                <Skeleton width="150px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 200}`" />
                            </div>
                            <div class="flight-skeleton-column flex min-w-0 flex-1 flex-col items-end sm:flex-none">
                                <Skeleton width="150px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 250}`" />
                                <Skeleton width="60px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 300}`" />
                                <Skeleton width="90px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 350}`" />
                                <Skeleton width="200px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 400}`" />
                                <Skeleton width="150px" height="15px"
                                    :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 450}`" />
                            </div>
                        </div>
                    </div>

                   
                    <!-- Flight Results - RESPONSIVE GRID -->
                    <div class="w-full">
                        
                        <div v-if="filteredFlights?.length > 0 && !isLoading" class="mt-4 space-y-4">
                            <div v-for="item in filteredFlights" :key="item?.leg?.ref_id"
                                class="overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm transition-all duration-300 sm:rounded sm:shadow-none">
                                <!-- Compact mobile flight card -->
                                <div class="sm:hidden">
                                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                                        <div class="flex min-w-0 items-center gap-2.5">
                                            <img
                                                class="h-8 w-8 shrink-0 rounded bg-white object-contain"
                                                :src="item?.leg?.flights?.[0]?.marketing_carrier?.logo"
                                                :alt="item?.leg?.flights?.[0]?.marketing_carrier?.name"
                                            />
                                            <div class="min-w-0">
                                                <div class="truncate text-sm font-semibold text-slate-800">
                                                    {{ item?.leg?.flights?.[0]?.marketing_carrier?.name }}
                                                </div>
                                                <div class="truncate text-[11px] text-slate-500">
                                                    {{ item?.leg?.flights?.[0]?.marketing_carrier?.iata }}-{{
                                                        formatFlightNumber(item?.leg?.flights?.[0]?.flight_number)
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase text-slate-600">
                                            {{ item?.provider?.contentSource || "LCC" }}
                                        </span>
                                    </div>

                                    <div
                                        v-for="(leg, legIndex) in item?.leg?.flights"
                                        :key="`mobile-${legIndex}`"
                                        class="px-4 py-3"
                                        :class="{ 'border-t border-slate-100': legIndex > 0 }"
                                    >
                                        <div v-if="item?.leg?.flights?.length > 1"
                                            class="mb-2 flex items-center justify-between text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                            <span>{{ legIndex === 0 ? "Departure" : "Return" }}</span>
                                            <span>{{ moment.parseZone(leg?.departure_at).format("DD MMM") }}</span>
                                        </div>

                                        <div class="grid grid-cols-[1fr_110px_1fr] items-center gap-2">
                                            <div class="min-w-0 text-left">
                                                <div class="whitespace-nowrap text-lg font-bold text-slate-900">
                                                    {{ moment.parseZone(leg?.departure_at).format("hh:mm A") }}
                                                </div>
                                                <div class="mt-1 truncate text-xs font-medium text-slate-700">
                                                    {{ leg?.from?.city?.name }} ({{ leg?.from?.city?.code }})
                                                </div>
                                            </div>

                                            <div class="flex min-w-0 flex-col items-center px-1 text-center">
                                                <span class="text-[11px] font-medium text-slate-600">
                                                    {{ Math.floor(moment.duration(leg?.travel_time, "m").asHours()) }}h
                                                    {{ moment.duration(leg?.travel_time, "m").minutes() }}m
                                                </span>
                                                <div class="my-1.5 flex w-full items-center">
                                                    <span class="h-px flex-1 bg-slate-300"></span>
                                                    <Plane class="mx-1 h-3.5 w-3.5 rotate-45 text-primary" />
                                                    <span class="h-px flex-1 bg-slate-300"></span>
                                                </div>
                                                <span class="text-[10px] font-medium text-slate-500">
                                                    {{ leg?.has_layovers
                                                        ? `${leg?.layovers_count} ${leg?.layovers_count === 1 ? "Stop" : "Stops"}`
                                                        : "Nonstop" }}
                                                </span>
                                            </div>

                                            <div class="min-w-0 text-right">
                                                <div class="whitespace-nowrap text-lg font-bold text-slate-900">
                                                    {{ moment.parseZone(leg?.arrival_at).format("hh:mm A") }}
                                                </div>
                                                <div class="mt-1 truncate text-xs font-medium text-slate-700">
                                                    {{ leg?.to?.city?.name }} ({{ leg?.to?.city?.code }})
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-2 text-[10px] text-slate-500">
                                            {{ moment.parseZone(leg?.departure_at).format("ddd, DD MMM YYYY") }}
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-[11px] text-slate-500">
                                                {{ item?.leg?.flights?.[0]?.cabin_class || "Economy" }} ·
                                                {{ item?.leg?.flights?.[0]?.is_refundable ? "Refundable" : "Non-refundable" }}
                                            </div>
                                            <div class="mt-0.5 text-[10px] font-medium uppercase text-slate-400">Total fare</div>
                                        </div>
                                        <button @click="openSooperFlightDetails(item)"
                                            class="shrink-0 rounded-md bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm">
                                            {{ formatAmount(calculateTotalFare(item)) }}
                                        </button>
                                    </div>
                                </div>

                                <div class="hidden sm:block">
                                <!-- Top Header: Route, Dates, Price, Book Button - Mobile Responsive -->
                                
                                <div
                                    class="px-3 sm:px-4 pt-3 pb-3 sm:pt-3 sm:pb-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 border-b">
                                    <!-- Airline Logo and Name - Mobile Stacked -->
                                    <div v-if="item?.leg?.flights.length > 1"
                                        class="flex items-center gap-3 sm:gap-5 min-w-0 sm:w-[320px] md:w-[360px] lg:w-[420px] flex-shrink-0">
                                        <img class="w-10 h-10 sm:w-16 sm:h-16 md:w-16 md:h-16 object-contain p-1 bg-white rounded"
                                            :src="item?.leg?.flights?.[0]?.marketing_carrier?.logo"
                                            :alt="item?.leg?.flights?.[0]?.marketing_carrier?.name" />
                                        <div class="text-left min-w-0">
                                            <div class="text-sm sm:text-base md:text-lg font-semibold text-gray-900 truncate">
                                                {{ item?.leg?.flights?.[0]?.marketing_carrier?.name }}
                                            </div>
                                            <!-- Flight Numbers - Mobile Hidden on small screens -->
                                           <div
  class="hidden sm:block text-xs sm:text-sm text-gray-600 mt-0.5 truncate overflow-hidden whitespace-nowrap"
  v-html="
    (item?.leg?.flights || [])
      .map((flight) => {
        return (flight?.segments || [])
          .map((seg) => {
            const code =
              seg?.operating_carrier?.iata ||
              flight?.marketing_carrier?.iata ||
              '';
            const num = formatFlightNumber(seg?.flight_number);
            return code && num ? `${code}-${num}` : '';
          })
          .filter(Boolean)
          .join(' / ');
      })
      .filter(Boolean)
      .join(' • ')
  "
></div>
                                        </div>
                                    </div>

                                    <div v-else class="flex flex-col sm:items-start gap-1 text-sm text-gray-700">
                                        <div class="font-semibold text-base">
                                            {{
                                                item?.leg?.flights[0]?.from
                                                    ?.city?.name
                                            }}
                                            to
                                            {{
                                                item?.leg?.flights[0]?.to?.city
                                                    ?.name
                                            }}
                                          
                                        </div>
                                        <div class="text-gray-500">
                                            {{
                                                moment(
                                                    item?.leg?.flights[0]
                                                        ?.departure_at,
                                                ).format("ddd, DD MMM YYYY")
                                            }}
                                            <span v-if="
                                                item?.leg?.flights?.length >
                                                1
                                            ">
                                                —
                                                {{
                                                    moment(
                                                        item?.leg?.flights[1]
                                                            ?.departure_at,
                                                    ).format("ddd, DD MMM YYYY")
                                                }}
                                            </span>
                                        </div>
                                    </div>


                                    <!-- Price and Button - Mobile Stacked Right -->
                                    <div
                                        class="flex flex-col sm:flex-row items-start sm:items-center justify-between sm:justify-end gap-3 sm:gap-4 mt-2 sm:mt-0 flex-shrink-0">
                                        <div class="text-left sm:text-right">
                                            <div class="text-lg sm:text-xl md:text-2xl font-bold text-gray-900">
                                                {{
                                                    formatAmount(
                                                        calculateTotalFare(item),
                                                    )
                                                }}
                                            </div>
                                            <!-- Per Person Price - Mobile Only -->
                                            <div class="text-xs text-gray-500 sm:hidden mt-0.5">
                                                Total
                                            </div>
                                        </div>
                                        <button @click="openSooperFlightDetails(item)"
                                            class="bg-primary text-white text-sm sm:text-md px-4 sm:px-6 md:px-8 py-2 sm:py-3 rounded-md font-semibold transition w-full sm:w-auto">
                                            Select Flight
                                        </button>
                                    </div>
                                </div>

                                <!-- All Flight Legs (Outbound + Return) - Mobile Responsive -->
                                <div class="px-2 sm:px-3 pt-4 pb-3">
                                    <div class="flex flex-col gap-4 sm:gap-6" :class="{ 'lg:flex-row': route.query.flightType !== 'multi-city' }">
                                        <div v-for="(leg, legIndex) in item?.leg?.flights" :key="legIndex"
                                            class="mb-6 sm:mb-8 last:mb-0">

                                            <!-- Professional Header Strip - Mobile Stacked -->
                                            <div v-if="item?.leg?.flights.length !== 1"
                                                class="flex flex-col sm:flex-row items-stretch bg-white rounded sm:rounded-r-xl overflow-hidden border border-gray-200 mx-auto">
                                                <!-- Colored Label (Header) -->
                                                <div class="flex items-center justify-center gap-1 sm:gap-2 px-3 sm:px-4 py-2 text-white font-semibold bg-primary text-xs sm:text-sm relative overflow-hidden w-24 sm:w-28 md:w-32"
                                                    :style="{
                                                        clipPath: 'polygon(0% 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%)'
                                                    }">
                                                    <!-- Icon -->
                                                    <PlaneTakeoff
                                                        v-if="route.query.flightType === 'multi-city' || legIndex === 0"
                                                        class="w-3 h-3 sm:w-4 sm:h-4" />
                                                    <PlaneLanding v-else class="w-3 h-3 sm:w-4 sm:h-4" />

                                                    <!-- Label -->
                                                    <span class="truncate text-sm">
                                                        {{
                                                            route.query.flightType === 'multi-city'
                                                                ? `Trip ${legIndex + 1}`
                                                        : legIndex === 0
                                                        ? 'Departure'
                                                        : 'Arrival'
                                                        }}
                                                    </span>
                                                </div>

                                                <!-- Route & Info Section -->
                                                <div class="flex-1 px-3 sm:px-4 lg:px-5 py-2 sm:py-3 bg-gray-50">
                                                    <div
                                                        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1 sm:gap-2 lg:gap-4">
                                                        <!-- Route -->
                                                        <div class="flex-1 min-w-0">
                                                            <div
                                                                class="text-sm sm:text-base font-semibold text-gray-900 leading-tight truncate">
                                                                {{ leg?.from?.city?.name || leg?.from?.city?.code }}
                                                                ({{ leg?.from?.city?.code }})
                                                                →
                                                                {{ leg?.to?.city?.name || leg?.to?.city?.code }}
                                                                ({{ leg?.to?.city?.code }})
                                                            </div>

                                                            <div class="text-xs text-gray-600 mt-0.5">
                                                                {{ formatDate(leg?.departure_at) }}
                                                            </div>
                                                        </div>

                                                        <!-- Right Meta - Mobile Stacked -->
                                                        <div class="flex flex-wrap gap-1 sm:gap-1.5 mt-1 sm:mt-0">
                                                            <!-- Stops -->
                                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-xs font-semibold border"
                                                                :class="leg?.has_layovers
                                                                    ? 'bg-orange-50 text-orange-700 border-orange-200'
                                                                    : 'bg-green-50 text-green-700 border-green-200'">
                                                                <component :is="leg?.has_layovers ? ArrowDownUp : Minus"
                                                                    class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                                                                <span>
                                                                    {{ leg?.has_layovers
                                                                        ? leg?.layovers_count === 1
                                                                            ? '1 Stop'
                                                                            : leg?.layovers_count + ' Stops'
                                                                        : 'Non-stop'
                                                                    }}
                                                                </span>
                                                            </div>

                                                            <!-- Duration -->
                                                            <div
                                                                class="inline-flex items-center gap-1 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-xs font-semibold border bg-gray-50 text-gray-700 border-gray-200">
                                                                <Timer
                                                                    class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-gray-500" />
                                                                <span>
                                                                    {{ Math.floor(moment.duration(leg?.travel_time,
                                                                        'm').asHours()) }}h
                                                                    {{ moment.duration(leg?.travel_time, 'm').minutes()
                                                                    }}m
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Flight Details Grid - Mobile Stacked -->
                                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 sm:gap-6 items-center mt-3 sm:mt-4"
                                                :class="{
                                                    'mt-4': item?.leg?.flights.length !== 1,
                                                }">
                                                <!-- Airline Info - Mobile Center -->
                                                <div class="flex flex-col justify-center md:col-span-1" :class="{
                                                    'ps-0 sm:ps-3': item?.leg?.flights.length === 1,
                                                    'ps-0 sm:ps-2': item?.leg?.flights.length !== 1,
                                                    }">
                                                    <div
                                                        class="flex items-center gap-3 sm:gap-5 justify-center sm:justify-start">
                                                        <div v-if="item?.leg?.flights.length === 1"
                                                            class="mt-2 flex-shrink-0 hidden md:block">
                                                            <div
                                                                class="w-3 h-3 sm:w-4 sm:h-4 bg-red-600 rounded-full ring-2 sm:ring-4 ring-red-100">
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-3 sm:gap-5">
                                                            <div class="flex flex-col flex-shrink-0 justify-center items-center">
                                                                <img class="w-10 h-10 sm:w-14 sm:h-14 md:w-20 md:h-20 object-contain p-1 sm:p-1.5 bg-white rounded"
                                                                :src="leg?.marketing_carrier?.logo"
                                                                :alt="leg?.marketing_carrier?.name" />
                                                                <div class="inline-flex items-center gap-1 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-xs font-semibold border"
                                                                
                                                                >
                                                            
                                                                <span>
                                                                   {{ item?.provider?.contentSource || 'LCC' }}
                                                                </span>
                                                            </div>
                                                            </div>
                                                               
                                                            <div class="text-center sm:text-left">
                                                                <div
                                                                    class="text-sm sm:text-base md:text-lg tex-left font-semibold text-gray-900"
                                                                    :class="{
                                                                        'sm:w-48 ': item?.leg?.flights.length === 1,
                                                                    }">
                                                                    {{ leg?.marketing_carrier?.name }}
                                                                </div>
                                                                <div class="text-xs sm:text-sm text-gray-600"
                                                                    v-html="
                                                                        (leg?.segments || [])
                                                                            .map((seg) => {
                                                                                const code =
                                                                                    seg?.operating_carrier?.iata ||
                                                                                    leg?.marketing_carrier?.iata ||
                                                                                    '';
                                                                                const num = formatFlightNumber(seg?.flight_number);
                                                                                return code && num ? `${code}-${num}` : '';
                                                                            })
                                                                            .filter(Boolean)
                                                                            .join('<br>')
                                                                    ">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Timeline Section - Mobile Stacked -->
                                                <div
                                                    class="col-span-1 md:col-span-3 grid grid-cols-3 gap-3 sm:gap-6 items-center mt-3 sm:mt-0">
                                                    <!-- Departure -->
                                                    <div class="text-center">
                                                        <div
                                                            class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900">
                                                            {{ moment.parseZone(leg?.departure_at).format("HH:mm") }}
                                                        </div>
                                                        <div
                                                            class="text-sm sm:text-base md:text-lg font-semibold text-gray-700 mt-0.5 sm:mt-1">
                                                            {{ leg?.from?.city?.code }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{
                                                                moment(
                                                                    leg.departure_at,
                                                                ).format("ddd, DD MMM YYYY")
                                                            }}

                                                        </div>
                                                    </div>

                                                    <!-- Duration & Stops - Mobile Compact -->
                                                    <div class="flex flex-col items-center">
                                                        <div v-if="item?.leg?.flights.length == 1"
                                                            class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm font-medium text-gray-600 mb-2 sm:mb-3">
                                                            <Timer class="w-3 h-3 sm:w-4 sm:h-4 text-gray-500" />
                                                            <span>
                                                                {{ Math.floor(moment.duration(leg?.travel_time,
                                                                    "m").asHours()) }}h
                                                                {{ moment.duration(leg?.travel_time, "m").minutes() }}m
                                                            </span>
                                                        </div>

                                                        <div class="relative w-full flex items-center justify-center">
                                                            <div class="flex-1 border-t border-dashed border-gray-300">
                                                            </div>
                                                            <div
                                                                class="mx-2 sm:mx-4 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-red-50 flex items-center justify-center shadow-sm">
                                                                <Plane class="w-4 h-4 sm:w-5 sm:h-5 text-red-600"
                                                                    strokeWidth="1.8" />
                                                            </div>
                                                            <div class="flex-1 border-t border-dashed border-gray-300">
                                                            </div>
                                                        </div>

                                                        <div v-if="item?.leg?.flights.length == 1" class="mt-2 sm:mt-3">
                                                            <div class="inline-flex items-center gap-1 px-2 py-0.5 sm:px-3 sm:py-1.5 rounded-full text-xs font-semibold border"
                                                                :class="leg?.has_layovers ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-green-50 text-green-700 border-green-200'">
                                                                <component :is="leg?.has_layovers ? ArrowDownUp : Minus"
                                                                    class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                                                <span>
                                                                    {{ leg?.has_layovers
                                                                        ? leg?.layovers_count === 1 ? "1 Stop" :
                                                                            leg?.layovers_count + " Stops"
                                                                        : "Non-stop" }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Arrival -->
                                                    <div class="text-center">
                                                        <div
                                                            class="text-xl sm:text-2xl md:text-3xl font-bold text-gray-900">
                                                            {{ moment.parseZone(leg?.arrival_at).format("HH:mm") }}
                                                        </div>
                                                        <div
                                                            class="text-sm sm:text-base md:text-lg font-semibold text-gray-700 mt-0.5 sm:mt-1">
                                                            {{ leg?.to?.city?.code }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            {{
                                                                moment(
                                                                    leg.arrival_at,
                                                                ).format("ddd, DD MMM YYYY")
                                                            }}

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mobile Actions - Bottom Section -->
                                    <div class="border-t border-gray-200 px-1 pt-2" :class="{
                                        'mt-4': item?.leg?.flights?.length === 1,
                                        'mt-2': item?.leg?.flights?.length !== 1
                                    }">
                                        <div
                                            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-0">
                                            <!-- Cabin Class Info - Mobile Stacked -->
                                            <div class="flex flex-wrap gap-3 text-xs sm:text-sm text-gray-600">
                                                <div class="flex items-center gap-1.5">
                                                    <Armchair class="w-4 h-4 text-gray-500" />
                                                    <span class="font-medium text-gray-700">
                                                        {{ item?.leg?.flights?.[0]?.cabin_class || "Economy" }}
                                                    </span>
                                                </div>
                                                <!-- Flight Numbers - Mobile Only -->
                                                <div class="sm:hidden flex items-center gap-1.5">
                                                    <Ticket class="w-4 h-4 text-gray-500" />
                                                    <span class="font-medium text-gray-700">
                                                        <span v-for="(flight, index) in item?.leg?.flights"
                                                            :key="flight?.flight_number">
                                                            {{ flight?.marketing_carrier?.iata }}-{{
                                                                formatFlightNumber(flight?.flight_number) }}
                                                            <span v-if="index < item?.leg?.flights.length - 1"> •
                                                            </span>
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Right: Refundable Badge + Details Button -->
                                            <div
                                                class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 mt-3 sm:mt-0">
                                                <!-- Refundable Badge -->
                                                <span
                                                    class="inline-flex items-center px-2 py-1 sm:px-3 sm:py-1.5 rounded-full text-xs font-semibold border"
                                                    :class="item?.leg?.flights[0]?.is_refundable
                                                        ? 'bg-green-50 border-green-200 text-green-700'
                                                        : 'bg-red-50 border-red-200 text-red-700'
                                                        ">
                                                    {{
                                                        item?.leg?.flights[0]?.is_refundable
                                                            ? "Refundable"
                                                            : "Non-Refundable"
                                                    }}
                                                </span>

                                                <!-- Details Button -->
                                                <button @click="openSooperFlightDetails(item)"
                                                    class="bg-primary text-white px-4 py-2 sm:px-6 sm:py-2.5 rounded-md font-semibold transition shadow-md w-full sm:w-auto">
                                                    Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>



                        <div v-if="isFlightLoading" class="mt-4 space-y-3 sm:mt-8 sm:space-y-4">
                            <div v-for="(item, index) in 5" :key="index"
                                class="flex min-h-[200px] w-full items-start gap-3 overflow-hidden rounded-lg border bg-white p-3 sm:h-[200px] sm:justify-between sm:gap-0 sm:overflow-visible sm:rounded sm:p-4">
                                <div class="flight-skeleton-column min-w-0 flex-1 sm:flex-none">
                                    <div class="flex items-center gap-x-3">
                                        <Skeleton width="150px" height="30px"
                                            :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100}`" />
                                    </div>
                                    <Skeleton width="60px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 50}`" />
                                    <Skeleton width="90px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 100}`" />
                                    <Skeleton width="200px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 150}`" />
                                    <Skeleton width="150px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 200}`" />
                                </div>
                                <div class="flight-skeleton-column flex min-w-0 flex-1 flex-col items-end sm:flex-none">
                                    <Skeleton width="150px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 250}`" />
                                    <Skeleton width="60px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 300}`" />
                                    <Skeleton width="90px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 350}`" />
                                    <Skeleton width="200px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 400}`" />
                                    <Skeleton width="150px" height="15px"
                                        :class="`rounded-none bg-gray-300 mb-4 animate-pulse animation-delay-${index * 100 + 450}`" />
                                </div>
                            </div>


                        </div>
                    </div>

                    <!-- No Results -->
                    <div v-if="showNoFlightsState || showNoFilteredFlightsState"
                        class="mt-4 sm:mt-8 bg-white border border-gray-200 rounded-2xl p-6 sm:p-10 text-center">
                        <img src="/public/assets/no-data.webp" alt="No flights found"
                            class="w-28 sm:w-36 h-auto mx-auto mb-4 sm:mb-6" />
                        <h3 class="text-xl sm:text-2xl font-bold text-primary">
                            {{ showNoFlightsState ? "No Flights Found" : "No Flights Match Filters" }}
                        </h3>
                        <p class="text-sm sm:text-base text-gray-600 mt-2 max-w-2xl mx-auto">
                            {{
                                showNoFlightsState
                                    ? "We could not find flights for your selected route and date. Please try changing dates or nearby airports."
                                    : "Your selected filters are too strict. Try clearing some filters to see more flight options."
                            }}
                        </p>
                        <!-- <div class="mt-5 sm:mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                            <button @click="resetAllFilters"
                                class="px-5 py-2.5 rounded-md border border-primary text-primary font-semibold hover:bg-primary/5">
                                Reset Filters
                            </button>
                            <button @click="scrollToSearchTop"
                                class="px-5 py-2.5 rounded-md bg-primary text-white font-semibold hover:bg-primary/90">
                                Modify Search
                            </button>
                        </div> -->
                    </div>

                    <div
                        v-if="isPopularRoutePage && popularRouteFareRows.length > 0"
                        class="mt-8 mb-2 border border-gray-300 bg-white overflow-x-auto"
                    >
                    <div class="bg-gray-50/80 px-6 py-4 border-b border-gray-200">
              <h2 class="text-xl font-semibold font-medium text-primary">{{ popularFareTableTitle }}</h2>
            </div>
                        <table class="min-w-full">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left">Routes</th>
                                    <th class="px-4 py-3 text-left">Airline</th>
                                    <th class="px-4 py-3 text-left">Flight Number</th>
                                    <th class="px-4 py-3 text-left">Ticket price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, idx) in popularRouteFareRows"
                                    :key="`popular-fare-${idx}`"
                                    class="border-t border-gray-200"
                                >
                                    <td class="px-4 py-2">{{ row.route }}</td>
                                    <td class="px-4 py-2">{{ row.airline }}</td>
                                    <td class="px-4 py-2">{{ row.flightNumber }}</td>
                                    <td class="px-4 py-2">{{ row.totalFare }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>


                </div>
            </div>
        </div>

        <!-- Search Expired Dialog -->
        <div v-if="showDialog"
            class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center z-30 justify-center p-8">
            <div class="bg-white p-4 sm:p-6 rounded shadow-lg w-full sm:w-1/4 text-center">
                <img src="/public/assets/clock.svg" alt="Logo" class="mx-auto mb-4 w-16 sm:w-32" />
                <h2 class="text-lg font-bold">Still Arround?</h2>
                <p class="mt-2">
                    Your search has been inactive for more than 15 minutes.Please refresh the page to update.
                </p>

                <div class="mt-4 flex justify-center gap-2">
                    <button @click="$router.push({ name: 'Home' })" class="mt-4 text-base px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">
                    Start new search
                </button>
                 <button @click="confirmReload" class="mt-4 text-base px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">
                    Refresh
                </button>
                </div>
            </div>
        </div>

        <!-- Flight Details Side Panel - RESPONSIVE -->
        <Transition name="fade">
            <div v-if="isSooperFlihgtDetailsOpen" class="fixed inset-0 bg-black bg-opacity-50 z-70"
                @click="isSooperFlihgtDetailsOpen = false"></div>
        </Transition>

        <Transition name="slide-sooper">
            <div v-if="isSooperFlihgtDetailsOpen"
                class="fixed top-0 right-0 h-full w-full sm:w-[90%] md:w-[85%] lg:w-[980px] xl:w-[980px] bg-white shadow-2xl z-[70] overflow-y-auto">

                <!-- Header - Improved for Mobile -->
                <div class="sticky top-0 bg-white border-b border-gray-100 px-3 sm:px-6 py-3 sm:py-4 z-10">
                    <div class="flex items-center gap-2 sm:gap-4">
                        <button @click="isSooperFlihgtDetailsOpen = false"
                            class="text-primary bg-primary/30 h-7 w-7 sm:h-8 sm:w-8 rounded-full hover:text-gray-700 flex items-center justify-center flex-shrink-0">
                            <X class="w-3 h-3 sm:w-4 sm:h-4" />
                        </button>
                        <h2 class="text-base sm:text-xl lg:text-2xl font-bold text-primary truncate">
                            Flight Details
                        </h2>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="min-w-0 overflow-x-hidden p-3 sm:p-4 lg:p-6">
                    <div v-if="loadingDetails" class="flex justify-center items-center h-96">
                        <Spinner />
                    </div>

                    <div v-if="selectedFlight" class="space-y-4 sm:space-y-6">
                        <!-- Main Tabs - Mobile Optimized -->
                        <Tabs default-value="fare-options" class="min-w-0 w-full">
                            <!-- Tabs Navigation - Mobile Scrollable -->
                            <div class="scrollbar-hide w-full overflow-x-auto border-b border-primary/40 overscroll-x-contain">
                                <TabsList class="flex w-max min-w-full items-end justify-start gap-1 bg-transparent p-0 sm:gap-2">

                                    <!-- Fare Options -->
                                    <TabsTrigger value="fare-options" class="group relative flex-shrink-0 text-xs sm:text-base font-medium
             px-3 sm:px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">

                                        Fare Options

                                        <!-- Bottom Arrow -->
                                        <span class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         sm:group-data-[state=active]:block">
                                        </span>

                                    </TabsTrigger>

                                    <!-- Flight Itinerary -->
                                    <TabsTrigger value="flight-details" class="group relative flex-shrink-0 text-xs sm:text-base font-medium
             px-3 sm:px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
            data-[state=active]:bg-white

             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">

                                        Flight Itinerary

                                        <span class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         sm:group-data-[state=active]:block">
                                        </span>
                                    </TabsTrigger>

                                    <!-- Fare Rules -->
                                    <!-- <TabsTrigger
      value="fare-rules"
      class="group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">

      Fare Rules

      <span
  class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         group-data-[state=active]:block">
</span>
    </TabsTrigger> -->
                                    <TabsTrigger value="fare-breakdown" class="group relative flex-shrink-0 text-xs sm:text-base font-medium
             px-3 sm:px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">
                                        Fare Breakdown
                                        <span class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         sm:group-data-[state=active]:block">
                                        </span>
                                    </TabsTrigger>
                                    <!-- Baggage -->
                                    <TabsTrigger value="baggage-details" class="group relative flex-shrink-0 text-xs sm:text-base font-medium
             px-3 sm:px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">

                                        Baggage

                                        <span class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         sm:group-data-[state=active]:block">
                                        </span>
                                    </TabsTrigger>

                                </TabsList>
                            </div>


                            <!-- Flight Details Tab - Mobile Optimized -->
                            <TabsContent value="flight-details" class="mt-4 sm:mt-6">
                                <!-- Itinerary Display - Responsive Layout -->
                                <div class="" v-for="(flight, flightIndex) in selectedFlight?.leg?.flights">
                                    <div class="bg-primary rounded p-2 sm:p-3 lg:p-4 my-2">
                                        <div
                                            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1 sm:gap-2 lg:gap-4">
                                            <h3 class="text-sm sm:text-base lg:text-xl font-bold text-white truncate ">
                                                Flight Details:
                                                {{ flight?.from?.city?.name }} →
                                                {{ flight?.to?.city?.name }}
                                            </h3>
                                            <div class="inline-flex items-center rounded px-2 py-1 sm:px-3 sm:py-1 text-xs font-medium gap-1 bg-white flex-shrink-0 mt-1 sm:mt-0"
                                                :class="flight?.is_refundable ? 'text-green-500' : 'text-red-500'">
                                                <SquareCheckBig class="w-3 h-3 sm:w-4 sm:h-4"
                                                    v-if="flight?.is_refundable" />
                                                <SquareX v-else class="w-3 h-3 sm:w-4 sm:h-4" />
                                                <span class="font-semibold text-xs sm:text-sm">
                                                    {{
                                                        flight?.is_refundable
                                                            ? "Refundable"
                                                            : "Non-Refundable"
                                                    }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Segments - Mobile Optimized -->
                                    <div class="space-y-3 sm:space-y-0 sm:border">
                                        <div v-for="(
segment, segmentIndex
                                ) in flight?.segments" :key="segmentIndex" class="overflow-hidden rounded border bg-white sm:rounded-none">
                                            <!-- Layover Info -->
                                            <div v-if="segment?.layover_time"
                                                class="bg-amber-100 border-b border-amber-100 p-2 sm:p-3 lg:p-4">
                                                <div class="flex items-center justify-center gap-1 sm:gap-2">
                                                    <ClockIcon class="w-3 h-3 sm:w-4 sm:h-5 text-amber-600" />
                                                    <span class="text-xs sm:text-sm font-semibold text-amber-800">
                                                        Layover:
                                                        {{
                                                            formatLayoverTime(segment.layover_time)
                                                        }}
                                                                       
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Segment Details - Mobile Stacked -->
                                            <div class="p-3 sm:p-3 lg:p-6">

                                                <div
                                                    class="flex flex-col lg:grid lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                                                    <!-- Departure - Mobile First -->

                                                    <div
                                                        class="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 sm:space-y-2 lg:space-y-3">
                                                        <div class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 sm:rounded-none sm:bg-transparent sm:px-2 sm:py-1">
                                                            <img class="w-6 h-6 sm:w-8 sm:h-8 lg:w-16 lg:h-16 rounded-full border border-gray-100"
                                                                :src="segment
                                                                    ?.operating_carrier
                                                                    ?.logo
                                                                    " alt="" />
                                                            <div>
                                                                <div
                                                                    class="font-semibold text-xs sm:text-sm text-gray-900">
                                                                    {{
                                                                        segment
                                                                            ?.operating_carrier
                                                                            ?.name
                                                                    }}
                                                                </div>
                                                                <div class="text-xs text-gray-500">
                                                                    {{ segment?.operating_carrier?.iata }}-{{
                                                                        formatFlightNumber(segment?.flight_number) ||
                                                                        "N/A"
                                                                    }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="rounded-lg bg-slate-50 p-3 sm:space-y-2 sm:rounded-none sm:bg-transparent sm:p-0 lg:space-y-2">
                                                            <div
                                                                class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                                Departure
                                                            </div>
                                                            <div
                                                                class="text-sm sm:text-base lg:text-lg font-bold text-gray-900">
                                                                {{
                                                                    formatDate(
                                                                        segment?.departure_at,
                                                                    )
                                                                }}
                                                            </div>

                                                            <div class="space-y-1">
                                                                <div
                                                                    class="font-semibold text-xs sm:text-sm text-gray-900">
                                                                    {{
                                                                        segment?.from
                                                                            ?.name
                                                                    }}
                                                                    <span class="text-gray-500 font-normal text-xs">({{
                                                                        segment
                                                                            ?.from
                                                                            ?.iata
                                                                        }})</span>
                                                                </div>
                                                                <div class="text-xs text-gray-500">
                                                                    Terminal:
                                                                    {{
                                                                        segment
                                                                            ?.from_terminal
                                                                            ?.Gate ??
                                                                        "N/A"
                                                                    }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Flight Path - Mobile Compact -->
                                                    <div
                                                        class="flex flex-col items-center justify-center space-y-2 rounded-lg border border-slate-100 px-3 py-3 sm:space-y-3 sm:rounded-none sm:border-0 sm:px-0 sm:py-0 lg:space-y-4">
                                                        <div class="flex items-center gap-1 sm:gap-2 lg:gap-4 w-full">
                                                            <span
                                                                class="text-base sm:text-lg lg:text-xl font-bold text-gray-900">
                                                                {{
                                                                    moment
                                                                        .parseZone(
                                                                            segment?.departure_at,
                                                                        )
                                                                        .format(
                                                                            "HH:mm",
                                                                        )
                                                                }}
                                                            </span>
                                                            <div class="flex-1 relative">
                                                                <div
                                                                    class="absolute left-0 top-1/2 transform -translate-y-1/2 w-1.5 h-1.5 sm:w-2 sm:h-2 lg:w-3 lg:h-3 bg-primary rounded-full">
                                                                </div>
                                                                <div class="border-t border-dashed border-primary">
                                                                </div>
                                                                <div
                                                                    class="absolute right-0 top-1/2 transform -translate-y-1/2 w-1.5 h-1.5 sm:w-2 sm:h-2 lg:w-3 lg:h-3 bg-primary rounded-full">
                                                                </div>
                                                            </div>
                                                            <span
                                                                class="text-base sm:text-lg lg:text-xl font-bold text-gray-900">
                                                                {{
                                                                    moment
                                                                        .parseZone(
                                                                            segment?.arrival_at,
                                                                        )
                                                                        .format(
                                                                            "HH:mm",
                                                                        )
                                                                }}
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="flex justify-between w-full text-xs sm:text-sm text-gray-500 font-medium">
                                                            <span>{{
                                                                segment?.from?.iata
                                                            }}</span>
                                                            <span>{{
                                                                segment?.to?.iata
                                                            }}</span>
                                                        </div>
                                                        <div v-if="formatSegmentDuration(segment)"
                                                            class="text-[11px] sm:text-xs text-gray-500 font-medium">
                                                            Duration: {{ formatSegmentDuration(segment) }}
                                                        </div>
                                                    </div>

                                                    <!-- Arrival - Mobile First -->
                                                    <div class="space-y-1 rounded-lg bg-slate-50 p-3 sm:space-y-2 sm:rounded-none sm:bg-transparent sm:p-0 lg:space-y-2 md:mt-3">
                                                        <div
                                                            class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                                                            Arrival
                                                        </div>
                                                        <div
                                                            class="text-sm sm:text-base lg:text-lg font-bold text-gray-900">
                                                            {{
                                                                formatDate(
                                                                    segment?.arrival_at,
                                                                )
                                                            }}
                                                        </div>
                                                        <div class="space-y-1">
                                                            <div class="font-semibold text-xs sm:text-sm text-gray-900">
                                                                {{
                                                                    segment?.to
                                                                        ?.name
                                                                }}
                                                                <span class="text-gray-500 font-normal text-xs">({{
                                                                    segment?.to
                                                                        ?.iata
                                                                }})</span>
                                                            </div>
                                                            <div class="text-xs text-gray-500">
                                                                Terminal:
                                                                {{
                                                                    segment
                                                                        ?.to_terminal
                                                                        ?.Gate ??
                                                                    "N/A"
                                                                }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <!-- Fare Options Tab - Mobile Optimized -->
                            <TabsContent value="fare-options" class="mt-4 sm:mt-6">
                                <!-- Fare Options Display -->
                                <div class="space-y-4 sm:space-y-6">
                                    <div class="">
                                        <div :class="[
                                            'flex flex-col gap-4 sm:gap-6',
                                            route.query.flightType === 'multi-city' ? 'lg:flex-col' : 'lg:flex-row'
                                        ]">
                                            <div v-for="(leg, legIndex) in selectedFlight?.leg?.flights" :key="legIndex"
                                                class="mb-4 sm:mb-8 lg:mb-0">
                                                <!-- Compact fare-option leg summary for mobile -->
                                                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:hidden">
                                                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2">
                                                        <span class="text-[11px] font-bold uppercase tracking-wide text-primary">
                                                            {{ route.query.flightType === 'multi-city'
                                                                ? `Trip ${legIndex + 1}`
                                                                : legIndex === 0 ? 'Departure' : 'Return' }}
                                                        </span>
                                                        <span class="text-[11px] font-medium text-slate-500">
                                                            {{ moment.parseZone(leg?.departure_at).format('ddd, DD MMM') }}
                                                        </span>
                                                    </div>

                                                    <div class="flex items-center gap-2.5 px-3 py-2.5">
                                                        <img class="h-8 w-8 shrink-0 object-contain"
                                                            :src="leg?.marketing_carrier?.logo"
                                                            :alt="leg?.marketing_carrier?.name" />
                                                        <div class="min-w-0">
                                                            <div class="truncate text-xs font-semibold text-slate-800">
                                                                {{ leg?.marketing_carrier?.name }}
                                                            </div>
                                                            <div class="text-[10px] text-slate-500">
                                                                {{ leg?.marketing_carrier?.iata }}-{{ formatFlightNumber(leg?.flight_number) }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-[1fr_92px_1fr] items-center gap-2 px-3 pb-3">
                                                        <div class="min-w-0 text-left">
                                                            <div class="text-lg font-bold text-slate-900">
                                                                {{ moment.parseZone(leg?.departure_at).format('hh:mm A') }}
                                                            </div>
                                                            <div class="mt-0.5 truncate text-xs font-semibold text-slate-600">
                                                                {{ leg?.from?.city?.code }}
                                                            </div>
                                                        </div>

                                                        <div class="flex flex-col items-center text-center">
                                                            <span class="text-[10px] font-medium text-slate-500">
                                                                {{ Math.floor(moment.duration(leg?.travel_time, 'm').asHours()) }}h
                                                                {{ moment.duration(leg?.travel_time, 'm').minutes() }}m
                                                            </span>
                                                            <div class="my-1 flex w-full items-center">
                                                                <span class="h-px flex-1 bg-slate-300"></span>
                                                                <Plane class="mx-1 h-3 w-3 rotate-45 text-primary" />
                                                                <span class="h-px flex-1 bg-slate-300"></span>
                                                            </div>
                                                            <span class="text-[10px] font-medium text-slate-500">
                                                                {{ leg?.has_layovers
                                                                    ? `${leg?.layovers_count} ${leg?.layovers_count === 1 ? 'Stop' : 'Stops'}`
                                                                    : 'Nonstop' }}
                                                            </span>
                                                        </div>

                                                        <div class="min-w-0 text-right">
                                                            <div class="text-lg font-bold text-slate-900">
                                                                {{ moment.parseZone(leg?.arrival_at).format('hh:mm A') }}
                                                            </div>
                                                            <div class="mt-0.5 truncate text-xs font-semibold text-slate-600">
                                                                {{ leg?.to?.city?.code }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="hidden sm:block">
                                                <!-- Professional Header Strip - Mobile Responsive -->
                                                <div v-if="selectedFlight?.leg?.flights.length !== 1"
                                                    class="flex flex-col sm:flex-row items-stretch bg-white rounded sm:rounded overflow-hidden border border-gray-200 mx-auto">

                                                    <!-- Colored Label (Header) -->
                                                    <div class="mobile-leg-label relative flex w-24 items-center justify-center gap-1 overflow-hidden bg-primary px-3 py-2 text-xs font-semibold text-white sm:w-28 sm:gap-2 sm:px-4 sm:text-sm md:w-32"
                                                        :style="{
                                                            clipPath: 'polygon(0% 0%, 100% 0%, calc(100% - 10px) 100%, 0% 100%)'
                                                        }">
                                                        <!-- Icon -->
                                                        <PlaneTakeoff
                                                            v-if="route.query.flightType === 'multi-city' || legIndex === 0"
                                                            class="w-3 h-3 sm:w-4 sm:h-4" />
                                                        <PlaneLanding v-else class="w-3 h-3 sm:w-4 sm:h-4" />

                                                        <!-- Label -->
                                                        <span class="truncate text-sm">
                                                            {{
                                                                route.query.flightType === 'multi-city'
                                                                    ? `Trip ${legIndex + 1}`
                                                            : legIndex === 0
                                                            ? 'Departure'
                                                            : 'Arrival'
                                                            }}
                                                        </span>
                                                    </div>

                                                    <!-- Route & Info Section -->
                                                    <div class="flex-1 px-3 sm:px-4 lg:px-5 py-2 sm:py-3 bg-gray-50">
                                                        <div
                                                            class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1 sm:gap-2 lg:gap-4">
                                                            <!-- Route -->
                                                            <div class="flex-1 min-w-0">
                                                                <div
                                                                    class="text-sm sm:text-base font-semibold text-gray-900 leading-tight truncate">
                                                                    {{ leg?.from?.city?.name || leg?.from?.city?.code }}
                                                                    ({{ leg?.from?.city?.code }})
                                                                    →
                                                                    {{ leg?.to?.city?.name || leg?.to?.city?.code }}
                                                                    ({{ leg?.to?.city?.code }})
                                                                </div>

                                                                <div class="text-xs text-gray-600 mt-0.5">
                                                                    {{ formatDate(leg?.departure_at)
                                                                    }}
                                                                </div>
                                                            </div>

                                                            <!-- Right Meta - Mobile Stacked -->
                                                            <div class="flex flex-wrap gap-1 sm:gap-1.5 mt-1 sm:mt-0">
                                                                <!-- Stops -->
                                                                <div class="mt-2" data-tooltip-target="tooltip-default">
                                                <div v-if="leg?.has_layovers"
                                                    class=" ">
                                                

                                                    <TooltipProvider >
                                                        <Tooltip>
                                                            <TooltipTrigger as-child>
                                                                <Button size="md" class="hover:bg-orange-100 px-2 py-1 hover:text-orange-700 rounded-full text-xs border bg-orange-100 border-orange-200 text-orange-700" variant="ghost">
                                                                     {{
                                                        leg?.layovers_count
                                                    }}
                                                    Stop
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent v-if="leg?.has_layovers" >
                                                                 {{ leg?.segments?.[0]?.to?.name }}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>


                                                </div>
                                                <Button size="md" v-else
                                                    class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium border border-green-200
                                                    hover:bg-green-100 hover:text-green-700"
                                                    variant="ghost">
                                                    Non-Stop
                                                </Button>
                                            </div>

                                                                <!-- Duration -->
                                                                <div
                                                                    class="inline-flex items-center gap-1 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-xs font-semibold border bg-gray-50 text-gray-700 border-gray-200">
                                                                    <Timer
                                                                        class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-gray-500" />
                                                                    <span>
                                                                        {{ Math.floor(moment.duration(leg?.travel_time,
                                                                            'm').asHours()) }}h
                                                                        {{ moment.duration(leg?.travel_time,
                                                                            'm').minutes()
                                                                        }}m
                                                                    </span>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Flight Details Grid - Mobile Stacked -->
                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 sm:gap-6 items-center mt-2 sm:mt-4"
                                                    :class="{
                                                        'mt-4': selectedFlight?.leg?.flights.length !== 1,
                                                    }">
                                                    <!-- Airline Info - Mobile Center -->
                                                    <div class="flex flex-col justify-center md:col-span-1" :class="{
                                                        'ps-0 sm:ps-3': selectedFlight?.leg?.flights.length === 1,
                                                        'ps-0 sm:ps-2': selectedFlight?.leg?.flights.length !== 1,
                                                    }">
                                                        <div
                                                            class="flex items-center gap-3 sm:gap-6 justify-center sm:justify-start">
                                                            <div v-if="selectedFlight?.leg?.flights.length === 1"
                                                                class="mt-2 flex-shrink-0 hidden md:block">
                                                                <div
                                                                    class="w-3 h-3 sm:w-4 sm:h-4 bg-red-600 rounded-full ring-2 sm:ring-4 ring-red-100">
                                                                </div>
                                                            </div>
                                                            <div class="flex items-center gap-3 sm:gap-5">
                                                                <img class="w-10 h-10 sm:w-14 sm:h-14 lg:w-16 lg:h-16 object-contain p-1 sm:p-1.5 bg-white rounded"
                                                                    :src="leg?.marketing_carrier?.logo"
                                                                    :alt="leg?.marketing_carrier?.name" />
                                                                <div class="text-center sm:text-left">
                                                                    <div
                                                                        class="text-sm sm:text-lg font-semibold text-gray-900">
                                                                        {{ leg?.marketing_carrier?.name }}
                                                                    </div>
                                                                    <div class="text-xs sm:text-sm text-gray-600">
                                                                        {{ leg?.marketing_carrier?.iata }}-{{
                                                                            formatFlightNumber(leg?.flight_number) }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Timeline Section - Mobile Stacked -->
                                                    <div
                                                        class="col-span-1 md:col-span-3 grid grid-cols-3 gap-3 sm:gap-6 items-center mt-3 sm:mt-0">
                                                        <!-- Departure -->
                                                        <div class="text-center">
                                                            <div
                                                                class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">
                                                                {{ moment.parseZone(leg?.departure_at).format("HH:mm")
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-sm sm:text-lg font-semibold text-gray-700 mt-0.5 sm:mt-1">
                                                                {{ leg?.from?.city?.code }}
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                            {{
                                                                moment(
                                                                    leg.departure_at,
                                                                ).format("ddd, DD MMM YYYY")
                                                            }}

                                                        </div>
                                                        </div>

                                                        <!-- Duration & Stops -->
                                                        <div class="flex flex-col items-center">
                                                            <div v-if="selectedFlight?.leg?.flights.length == 1"
                                                                class="flex items-center gap-1 sm:gap-2 text-xs sm:text-sm font-medium text-gray-600 mb-2 sm:mb-3">
                                                                <Timer class="w-3 h-3 sm:w-4 sm:h-4 text-gray-500" />
                                                                <span>
                                                                    {{ Math.floor(moment.duration(leg?.travel_time,
                                                                        "m").asHours()) }}h
                                                                    {{ moment.duration(leg?.travel_time, "m").minutes()
                                                                    }}m
                                                                </span>
                                                            </div>

                                                            <div
                                                                class="relative w-full flex items-center justify-center">
                                                                <div
                                                                    class="flex-1 border-t border-dashed border-gray-300">
                                                                </div>
                                                                <div
                                                                    class="mx-2 sm:mx-4 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-red-50 flex items-center justify-center shadow-sm">
                                                                    <Plane class="w-4 h-4 sm:w-5 sm:h-5 text-red-600"
                                                                        strokeWidth="1.8" />
                                                                </div>
                                                                <div
                                                                    class="flex-1 border-t border-dashed border-gray-300">
                                                                </div>
                                                            </div>

                                                            <div v-if="selectedFlight?.leg?.flights.length == 1"
                                                                class="mt-2 sm:mt-3">
                                                                <div class="mt-2" data-tooltip-target="tooltip-default">
                                                <div v-if="leg?.has_layovers"
                                                    class=" ">
                                                

                                                    <TooltipProvider >
                                                        <Tooltip>
                                                            <TooltipTrigger as-child>
                                                                <Button size="md" class="hover:bg-orange-100 px-2 py-1 hover:text-orange-700 rounded-full text-xs border bg-orange-100 border-orange-200 text-orange-700" variant="ghost">
                                                                     {{
                                                        leg?.layovers_count
                                                    }}
                                                    Stop
                                                                </Button>
                                                            </TooltipTrigger>
                                                            <TooltipContent v-if="leg?.has_layovers" >
                                                                 {{ leg?.segments?.[0]?.to?.name }}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </TooltipProvider>


                                                </div>
                                                <Button size="md" v-else
                                                    class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium border border-green-200
                                                    hover:bg-green-100 hover:text-green-700"
                                                    variant="ghost">
                                                    Non-Stop
                                                </Button>
                                            </div>
                                                            </div>
                                                        </div>

                                                        <!-- Arrival -->
                                                        <div class="text-center">
                                                            <div
                                                                class="text-xl sm:text-2xl lg:text-3xl font-bold text-gray-900">
                                                                {{ moment.parseZone(leg?.arrival_at).format("HH:mm") }}
                                                            </div>
                                                            <div
                                                                class="text-sm sm:text-lg font-semibold text-gray-700 mt-0.5 sm:mt-1">
                                                                {{ leg?.to?.city?.code }}
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                            {{
                                                                moment(
                                                                    leg.arrival_at,
                                                                ).format("ddd, DD MMM YYYY")
                                                            }}

                                                        </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Flight Tabs Navigation - Mobile Scrollable -->
                                        <Tabs :default-value="selectedFlight?.leg?.flights[0]?.ref_id"
                                            class="mt-4 min-w-0 w-full sm:mt-6">
                                            <div class="bg-primary rounded p-2 sm:p-3 lg:p-4 mb-2">
                                                <div
                                                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1 sm:gap-2 lg:gap-4">
                                                    <h3
                                                        class="flex gap-2 text-sm sm:text-base lg:text-xl font-bold text-white truncate">
                                                        <TicketCheck />
                                                        Fare Options

                                                    </h3>

                                                </div>
                                            </div>
                                            <!-- Flight Tabs -->
                                            <div class="scrollbar-hide w-full overflow-x-auto border-b border-primary/40 overscroll-x-contain">

                                                <TabsList class="flex w-max min-w-full justify-start items-end gap-1 sm:gap-2
           bg-transparent p-0
           ">

                                                    <TabsTrigger
                                                        v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                                        :key="flightIndex" :value="flight.ref_id" class="relative group flex-shrink-0
             text-xs sm:text-base font-medium
             px-3 sm:px-5 py-2.5
             rounded-none
             bg-transparent
             border-b-2 border-transparent
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=active]:border-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary
             transition">

                                                        {{ flight?.from?.city?.name }} → {{ flight?.to?.city?.name }}

                                                        <!-- Bottom Notch -->
                                                        <span class="absolute left-1/2 -bottom-[7px] -translate-x-1/2
         w-3 h-3 bg-primary rotate-45
         hidden
         sm:group-data-[state=active]:block">
                                                        </span>

                                                    </TabsTrigger>

                                                </TabsList>

                                            </div>


                                            <!-- Flight Tab Content -->
                                            <TabsContent v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                                :key="flightIndex" :value="flight.ref_id"
                                                class="space-y-4 sm:space-y-6 mt-3 sm:mt-6">

                                                <!-- Flight Header - Mobile Compact -->
                                                <div class="bg-primary/5 rounded p-3 sm:p-4 border border-primary/20">
                                                    <div
                                                        class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-3">
                                                        <div class="flex-1 min-w-0">
                                                            <h4
                                                                class="font-bold text-sm sm:text-lg text-gray-800 truncate">
                                                                {{
                                                                    flight?.from?.city?.name }} to {{ flight?.to?.city?.name
                                                                }}
                                                            </h4>
                                                            <div
                                                                class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-1 sm:gap-3 mt-1 sm:mt-2 text-xs sm:text-sm text-gray-600">
                                                                <div class="flex items-center gap-1">
                                                                    <Calendar class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                    <span>{{ moment(flight?.departure_at).format('ddd,DD MMM, YYYY') }}</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <Plane class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                    <span>{{ flight?.operating_carrier?.name }}</span>
                                                                </div>
                                                                <div class="flex items-center gap-1">
                                                                    <Clock class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                    <span>{{
                                                                        moment.parseZone(flight?.departure_at).format('HH:mm')
                                                                    }} - {{
                                                                            moment.parseZone(flight?.arrival_at).format('HH:mm')
                                                                        }}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="inline-flex items-center rounded px-2 py-0.5 sm:px-3 sm:py-1 text-xs sm:text-sm font-medium gap-1 bg-white border border-gray-200 flex-shrink-0 mt-2 sm:mt-0"
                                                            :class="flight?.is_refundable ? 'text-green-500' : 'text-red-500'">
                                                            <SquareCheckBig class="w-3 h-3 sm:w-4 sm:h-4"
                                                                v-if="flight?.is_refundable" />
                                                            <SquareX v-else class="w-3 h-3 sm:w-4 sm:h-4" />
                                                            <span class="font-semibold text-xs sm:text-sm">
                                                                {{ flight?.is_refundable ? "Refundable" :
                                                                    "Non-Refundable"
                                                                }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Fare Options Grid for Current Flight - Mobile Stacked -->
                                                <div class="grid grid-cols-1 gap-3 sm:gap-5 lg:grid-cols-3">
                                                    <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex"
                                                        @click="selectFares(flightIndex, fare.ref_id)"
                                                        class="flex h-full cursor-pointer flex-col rounded border p-3 transition-all duration-200 sm:rounded-md sm:p-5"
                                                        :class="selectedFares[flightIndex] === fare.ref_id
                                                            ? 'border-primary ring-1 ring-primary/20 bg-primary/[0.02] shadow-sm'
                                                            : 'border-gray-200 bg-white hover:border-gray-300'">

                                                        <div class="mb-3 flex items-center justify-between gap-3 border-b border-gray-100 pb-3 sm:mb-4">
                                                            <h5 class="font-bold text-base sm:text-lg text-gray-800 uppercase tracking-tight">
                                                                {{ fare?.name_class || fare?.class || 'Standard' }}
                                                            </h5>
                                                            <div class="flex h-6 w-6 items-center justify-center rounded-full border transition-colors"
                                                                :class="selectedFares[flightIndex] === fare.ref_id ? 'border-primary bg-primary text-white' : 'border-gray-300 bg-white text-transparent'">
                                                                <Check class="w-3.5 h-3.5 stroke-[3px]" />
                                                            </div>
                                                        </div>

                                                        <div class="space-y-3">
                                                            <h6 class="font-bold text-sm text-gray-900">Baggage</h6>
                                                            <div class="space-y-2">
                                                                <div class="flex items-center gap-2 text-gray-600">
                                                                    <BriefcaseBusiness class="w-4 h-4 text-primary/70 flex-shrink-0" />
                                                                    <p class="text-xs sm:text-sm leading-snug">
                                                                        <span class="font-medium text-gray-500">Hand Carry:</span>
                                                                        {{ getFareBaggageSummary(fare, 'carry') }}
                                                                    </p>
                                                                </div>
                                                                <div class="flex items-center gap-2 text-gray-600">
                                                                    <Briefcase class="w-4 h-4 text-primary/70 flex-shrink-0" />
                                                                    <p class="text-xs sm:text-sm leading-snug">
                                                                        <span class="font-medium text-gray-500">Checked:</span>
                                                                        {{ getFareBaggageSummary(fare, 'checked') }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div v-if="getFarePolicyPreview(fare).length" class="mt-4 flex flex-wrap gap-1.5">
                                                            <span v-for="(policyTitle, policyIndex) in getFarePolicyPreview(fare)" :key="policyIndex"
                                                                class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600 border border-gray-200/50 uppercase tracking-wider">
                                                                {{ policyTitle }}
                                                            </span>
                                                        </div>

                                                        <div class="mt-auto flex items-end justify-between gap-3 pt-4 sm:block sm:pt-6">
                                                            <button type="button" @click.stop="openFareDetails(flight, fare)"
                                                                class="block text-xs font-semibold text-primary underline underline-offset-4 transition-colors hover:text-primary/80 sm:mb-3 sm:text-sm">
                                                                View Details
                                                            </button>

                                                            <div class="flex shrink-0 flex-col text-right sm:text-left">
                                                                <p class="text-lg font-bold text-gray-900 sm:text-2xl">
                                                                    {{ formatAmount(calculateFare(fare)) }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <Dialog v-model:open="showFareDetailsDialog">
                                                    <DialogContent class="max-w-2xl overflow-hidden bg-white p-0">
                                                        <div class="border-b border-gray-100 p-4 sm:p-5">
                                                            <DialogHeader class="space-y-2">
                                                                <DialogTitle class="text-base sm:text-lg text-gray-900">
                                                                    {{ selectedFareDetails?.name_class || selectedFareDetails?.class || 'Standard' }}
                                                                </DialogTitle>
                                                                <DialogDescription class="text-xs sm:text-sm text-gray-500">
                                                                    {{ selectedFareDetailsFlight?.from?.city?.name }} to {{ selectedFareDetailsFlight?.to?.city?.name }}
                                                                    <span v-if="selectedFareDetailsFlight?.departure_at">
                                                                        • {{ moment(selectedFareDetailsFlight?.departure_at).format('ddd, DD MMM YYYY') }}
                                                                    </span>
                                                                </DialogDescription>
                                                            </DialogHeader>
                                                            <div class="mt-3 flex items-center justify-between gap-3">
                                                                <span class="inline-flex items-center rounded-full border border-gray-200 px-2.5 py-1 text-xs font-medium"
                                                                    :class="selectedFareDetailsFlight?.is_refundable ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'">
                                                                    {{ selectedFareDetailsFlight?.is_refundable ? 'Refundable' : 'Non-Refundable' }}
                                                                </span>
                                                                <span class="text-base sm:text-lg font-bold text-primary">
                                                                    {{ selectedFareDetails ? formatAmount(calculateFare(selectedFareDetails)) : '' }}
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="max-h-[75vh] space-y-5 overflow-y-auto p-4 sm:p-5">
                                                           

                                                            <section class="space-y-3">
                                                                <div class="flex items-center gap-2">
                                                                    <TicketCheck class="w-4 h-4 text-primary" />
                                                                    <h6 class="text-sm font-semibold text-gray-900">
                                                                        Fare Policies
                                                                    </h6>
                                                                </div>

                                                                <div v-if="getNormalizedFarePolicies(selectedFareDetails).length" class="space-y-2">
                                                                    <div v-for="policy in getNormalizedFarePolicies(selectedFareDetails)"
                                                                        :key="policy.id"
                                                                        class="rounded-lg border border-gray-200 p-3">
                                                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                                                            <div class="min-w-0">
                                                                                <p class="text-sm font-semibold text-gray-900">
                                                                                    {{ policy.title }}
                                                                                </p>
                                                                                <p v-if="policy.description"
                                                                                    class="mt-1 text-xs sm:text-sm text-gray-600">
                                                                                    {{ policy.description }}
                                                                                </p>
                                                                            </div>
                                                                            <div class="flex flex-wrap gap-1.5">
                                                                                <span v-if="policy.type"
                                                                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium capitalize text-gray-600">
                                                                                    {{ policy.type }}
                                                                                </span>
                                                                                <span v-if="policy.traveler_type"
                                                                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary">
                                                                                    {{ getTravelerTypeLabel(policy.traveler_type) }}
                                                                                </span>
                                                                                <span v-if="policy.price !== null && policy.price !== undefined"
                                                                                    class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700">
                                                                                    {{ policy.price }}{{ policy.price_type === 'percentage' ? '%' : '' }}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div v-else class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-center text-xs sm:text-sm text-gray-500">
                                                                    No fare policies available for this fare.
                                                                </div>
                                                            </section>

                                                            <section v-if="selectedFareDetails?.additional_services?.length" class="space-y-3">
                                                                <div class="flex items-center gap-2">
                                                                    <DollarSign class="w-4 h-4 text-primary" />
                                                                    <h6 class="text-sm font-semibold text-gray-900">
                                                                        Additional Services
                                                                    </h6>
                                                                </div>

                                                                <div class="space-y-2">
                                                                    <div v-for="(service, serviceIndex) in selectedFareDetails.additional_services"
                                                                        :key="serviceIndex"
                                                                        class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3 text-xs sm:text-sm">
                                                                        <span class="font-medium text-gray-800">
                                                                            {{ service.name }}
                                                                        </span>
                                                                        <span class="text-gray-600">
                                                                            {{ service.cost }}
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </section>
                                                        </div>
                                                    </DialogContent>
                                                </Dialog>
                                            </TabsContent>
                                        </Tabs>
                                    </div>
                                </div>
                            </TabsContent>

                            <!-- Fare Breakdown Tab - Mobile Optimized -->
                            <TabsContent value="fare-breakdown" class="text-xs sm:text-base">
                                <div class="space-y-3 sm:space-y-4">
                                    <div v-if="selectedFlight?.leg?.flights?.length > 0">
                                        <!-- Loop through each flight -->
                                        <div v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                            :key="flightIndex"
                                            class="border border-gray-200 rounded overflow-hidden mb-4 sm:mb-6 last:mb-0">

                                            <!-- Flight Header - Mobile Compact -->
                                            <div class="bg-primary/5 p-3 sm:p-4 border-b border-primary/20">
                                                <div
                                                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="font-bold text-sm sm:text-lg text-gray-800">
                                                            {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name
                                                            }}
                                                        </h4>
                                                        <div
                                                            class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-1 sm:gap-3 mt-1 sm:mt-2 text-xs sm:text-sm text-gray-600">
                                                            <div class="flex items-center gap-1">
                                                                <Calendar class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{ moment(flight?.departure_at).format('ddd, DD MMM, YYYY') }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <Plane class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{ flight?.operating_carrier?.name }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <Clock class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{
                                                                    moment.parseZone(flight?.departure_at).format('HH:mm')
                                                                }} - {{
                                                                        moment.parseZone(flight?.arrival_at).format('HH:mm')
                                                                    }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="inline-flex items-center rounded px-2 py-0.5 sm:px-3 sm:py-1 text-xs sm:text-sm font-medium gap-1 bg-white border border-gray-200 flex-shrink-0 mt-2 sm:mt-0"
                                                        :class="flight?.is_refundable ? 'text-green-500' : 'text-red-500'">
                                                        <SquareCheckBig class="w-3 h-3 sm:w-4 sm:h-4"
                                                            v-if="flight?.is_refundable" />
                                                        <SquareX v-else class="w-3 h-3 sm:w-4 sm:h-4" />
                                                        <span class="font-semibold text-xs sm:text-sm">
                                                            {{ flight?.is_refundable ? "Refundable" : "Non-Refundable"
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Fare Information - Mobile Compact -->
                                            <div class="p-3 sm:p-4 border-b border-gray-200 bg-gray-50">
                                                <div
                                                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1 sm:gap-2">
                                                    <div class="flex-1 min-w-0">
                                                        <h5 class="font-semibold text-gray-800 text-sm sm:text-base">
                                                            Selected Fare:
                                                            <span v-if="getSelectedFare(flightIndex)"
                                                                class="text-primary">
                                                                {{ getSelectedFare(flightIndex)?.name_class ||
                                                                    getSelectedFare(flightIndex)?.class
                                                                    || 'Standard' }}
                                                            </span>
                                                            <span v-else class="text-amber-600">No fare selected</span>
                                                        </h5>
                                                    </div>
                                                    <div v-if="getSelectedFare(flightIndex)"
                                                        class="text-lg sm:text-xl font-bold text-primary mt-1 sm:mt-0">
                                                        {{ formatAmount(calculateFare(getSelectedFare(flightIndex))) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Passenger Fare Table - Mobile Scrollable -->
                                            <div v-if="getSelectedFare(flightIndex)" class="overflow-x-auto">
                                                <div class="min-w-[600px] sm:min-w-0">
                                                    <table class="w-full border-collapse text-xs sm:text-sm">
                                                        <thead>
                                                            <tr class="bg-gray-50">
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Passenger Type
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Base Price
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Taxes
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Fees
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Service Charges
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Surcharge
                                                                </th>
                                                                <th
                                                                    class="border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left text-xs font-semibold text-gray-900 whitespace-nowrap">
                                                                    Total
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template
                                                                v-if="getSelectedFare(flightIndex)?.passenger_fares?.length > 0">
                                                                <tr v-for="(passengerFare, index) in getSelectedFare(flightIndex)?.passenger_fares"
                                                                    :key="index" class="hover:bg-gray-50">
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{ passengerFare.traveler_type }}
                                                                        X
                                                                        {{ passengerFare.total_passenger }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{
                                                                            formatAmount(
                                                                                ((calculateFareMargin(
                                                                                    parseFloat(
                                                                                        passengerFare?.base_price,
                                                                                    ) || 0,
                                                                                    getSelectedFare(flightIndex)?.margin_amount,
                                                                                    getSelectedFare(flightIndex)?.margin_type,
                                                                                    getSelectedFare(flightIndex)?.amount_type,
                                                                                ) +
                                                                                    parseFloat(
                                                                                        CustomerMargin?.other_charges || 0,
                                                                                    ) +
                                                                                    parseFloat(
                                                                                        calculateCustomerMargin(
                                                                                            passengerFare?.base_price,
                                                                                            CustomerMargin?.value?.discount || 0,
                                                                                            CustomerMargin?.value?.margin_amount || 0,
                                                                                        ),
                                                                                    )) *
                                                                                    passengerCount) +
                                                                                parseFloat(passengerFare?.base_price || 0)
                                                                            )
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{ formatAmount(passengerFare.taxes) }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{ formatAmount(passengerFare.fees) }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{ formatAmount(passengerFare.service_charges)
                                                                        }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b border-gray-100 px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-xs font-medium text-left whitespace-nowrap">
                                                                        {{ formatAmount(passengerFare.surchage) }}
                                                                    </td>
                                                                    <td
                                                                        class="border-b px-2 sm:px-3 lg:px-4 py-1.5 sm:py-2 lg:py-3 text-left whitespace-nowrap">
                                                                        <span
                                                                            class="text-xs sm:text-sm lg:text-lg font-bold text-primary">
                                                                            {{
                                                                                getSelectedFare(flightIndex)?.currency?.symbol
                                                                            }}{{
                                                                                formatAmount(
                                                                                    parseFloat(passengerFare.base_price || 0) +
                                                                                    parseFloat(passengerFare.surchage || 0) +
                                                                                    parseFloat(passengerFare.taxes || 0) +
                                                                                    parseFloat(passengerFare.fees || 0) +
                                                                                    parseFloat(passengerFare.service_charges ||
                                                                                        0) +
                                                                                    parseFloat(passengerFare.ancillaries_charges
                                                                                        || 0) +
                                                                                    ((calculateFareMargin(
                                                                                        parseFloat(passengerFare.base_price) || 0,
                                                                                        getSelectedFare(flightIndex).margin_amount,
                                                                                        getSelectedFare(flightIndex).margin_type,
                                                                                        getSelectedFare(flightIndex).amount_type,
                                                                                    ) +
                                                                                        calculateCustomerMargin(
                                                                                            parseFloat(passengerFare.base_price) || 0,
                                                                                        )) * passengerFare?.total_passenger))
                                                                            }}
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                            <template v-else>
                                                                <tr>
                                                                    <td colspan="7"
                                                                        class="px-2 sm:px-3 lg:px-4 py-2 sm:py-3 lg:py-4 text-center">
                                                                        <div
                                                                            class="text-xs sm:text-sm text-gray-500 italic">
                                                                            No passenger fare data available for this
                                                                            fare
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- No Fare Selected Message - Mobile Compact -->
                                            <div v-else class="p-4 sm:p-6 text-center">
                                                <div
                                                    class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 mx-auto mb-2 sm:mb-3 rounded-full bg-gray-200/60 flex items-center justify-center">
                                                    <AlertCircle
                                                        class="w-4 h-4 sm:w-5 sm:h-5 lg:w-6 lg:h-6 text-gray-400" />
                                                </div>
                                                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-0.5 sm:mb-1">
                                                    No fare selected for this
                                                    flight</p>
                                                <p class="text-xs text-gray-500">Please select a fare from the fare
                                                    options tab</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State for No Flights - Mobile Compact -->
                                    <div v-else class="text-center py-6 sm:py-8">
                                        <div
                                            class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 mx-auto mb-2 sm:mb-3 rounded-full bg-gray-200/60 flex items-center justify-center">
                                            <AlertCircle class="w-4 h-4 sm:w-5 sm:h-5 lg:w-6 lg:h-6 text-gray-400" />
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-600 font-medium mb-0.5 sm:mb-1">No flight
                                            data available</p>
                                        <p class="text-xs text-gray-500">Please check the flight details</p>
                                    </div>
                                </div>
                            </TabsContent>

                            <!-- Baggage Details Tab - Mobile Optimized -->
                            <TabsContent value="baggage-details" class="text-xs sm:text-base">
                                <div class="space-y-3 sm:space-y-4">
                                    <div v-if="selectedFlight?.leg?.flights?.length > 0">
                                        <!-- Loop through each flight -->
                                        <div v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                            :key="flightIndex"
                                            class="border border-gray-200 rounded overflow-hidden mb-4 sm:mb-6 last:mb-0">

                                            <!-- Flight Header -->
                                            <div class="bg-primary/5 p-3 sm:p-4 border-b border-primary/20">
                                                <div
                                                    class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <h4 class="font-bold text-sm sm:text-lg text-gray-800">
                                                            {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name
                                                            }}
                                                        </h4>
                                                        <div
                                                            class="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center gap-1 sm:gap-3 mt-1 sm:mt-2 text-xs sm:text-sm text-gray-600">
                                                            <div class="flex items-center gap-1">
                                                                <Calendar class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{ moment(flight?.departure_at).format('ddd, DD MMM, YYYY') }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <Plane class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{ flight?.operating_carrier?.name }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1">
                                                                <Clock class="w-3 h-3 sm:w-4 sm:h-4" />
                                                                <span>{{
                                                                    moment.parseZone(flight?.departure_at).format('HH:mm')
                                                                    }} - {{
                                                                        moment.parseZone(flight?.arrival_at).format('HH:mm')
                                                                    }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="inline-flex items-center rounded px-2 py-0.5 sm:px-3 sm:py-1 text-xs sm:text-sm font-medium gap-1 bg-white border border-gray-200 flex-shrink-0 mt-2 sm:mt-0"
                                                        :class="flight?.is_refundable ? 'text-green-500' : 'text-red-500'">
                                                        <SquareCheckBig class="w-3 h-3 sm:w-4 sm:h-4"
                                                            v-if="flight?.is_refundable" />
                                                        <SquareX v-else class="w-3 h-3 sm:w-4 sm:h-4" />
                                                        <span class="font-semibold text-xs sm:text-sm">
                                                            {{ flight?.is_refundable ? "Refundable" : "Non-Refundable"
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Selected Fare Information -->
                                            <div class="p-3 sm:p-4 border-b border-gray-200 bg-gray-50">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <h5 class="font-semibold text-gray-800 text-sm sm:text-base">
                                                            Selected Fare:
                                                            <span v-if="getSelectedFare(flightIndex)"
                                                                class="text-primary">
                                                                {{ getSelectedFare(flightIndex)?.name_class ||
                                                                    getSelectedFare(flightIndex)?.class
                                                                || 'Standard' }}
                                                            </span>
                                                            <span v-else class="text-amber-600">No fare selected</span>
                                                        </h5>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Baggage Policies Display -->
                                            <div v-if="getSelectedFare(flightIndex)?.baggage_policies?.length > 0"
                                                class="p-3 sm:p-4">
                                                <h5
                                                    class="font-semibold text-gray-800 mb-3 sm:mb-4 flex items-center gap-1 sm:gap-2 text-sm sm:text-base">
                                                    <Briefcase class="w-4 h-4 sm:w-5 sm:h-5 text-primary" />
                                                    Baggage Allowance
                                                </h5>

                                                <!-- Loop through segments for separate tables -->
                                                <div v-for="(segment, segmentIndex) in flight?.segments"
                                                    :key="segmentIndex" class="mb-4 last:mb-0">
                                                    <!-- Segment Header -->
                                                    <div class="mb-2 pb-2 border-b border-gray-200">
                                                        <div class="flex items-center gap-2">
                                                            <div
                                                                class="w-1.5 h-1.5 bg-primary rounded-full flex-shrink-0">
                                                            </div>
                                                            <span class="text-sm font-semibold text-gray-900">
                                                                {{ segment.from.iata }} → {{ segment.to.iata }}
                                                            </span>
                                                            <!-- Booking codes -->
                                                            <span v-for="(code, codeIndex) in getSelectedFare(flightIndex)?.booking_codes?.filter(
                                                                (c) => c.segment_ref_id === segment.ref_id
                                                            )" :key="codeIndex">
                                                                <span class="text-gray-400 mx-1">|</span>
                                                                <span class="text-xs font-medium text-primary">
                                                                    {{ code.booking_code }}
                                                                </span>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <!-- Baggage Table for this segment -->
                                                    <div class="overflow-x-auto">
                                                        <table
                                                            class="w-full border-collapse border border-gray-200 rounded text-xs sm:text-sm">
                                                            <thead>
                                                                <tr class="bg-gray-50">
                                                                    <th
                                                                        class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-900">
                                                                        Traveler</th>
                                                                    <th
                                                                        class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-900">
                                                                        Check-in</th>
                                                                    <th
                                                                        class="border border-gray-200 px-3 py-2 text-left font-semibold text-gray-900">
                                                                        Cabin</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <!-- Loop through traveler types -->
                                                                <template v-for="travelerType in ['ADT', 'CHD', 'INF']"
                                                                    :key="travelerType">
                                                                    <tr v-if="getSelectedFare(flightIndex)?.baggage_policies?.some(
                                                                        (p) => p.segment_ref_id === segment.ref_id && p.traveler_type === travelerType
                                                                    )" class="border-b border-gray-100 hover:bg-gray-50">
                                                                        <!-- Traveler Type -->
                                                                        <td class="border border-gray-200 px-3 py-2">
                                                                            <span class="font-medium text-gray-700">
                                                                                {{ travelerType === 'ADT' ? 'Adult' :
                                                                                travelerType === 'CHD' ? 'Child' :
                                                                                'Infant' }}
                                                                            </span>
                                                                        </td>

                                                                        <!-- Check-in Baggage -->
                                                                        <td class="border border-gray-200 px-3 py-2">
                                                                            <span class="text-gray-600">
                                                                                {{
                                                                                    getSelectedFare(flightIndex)?.baggage_policies?.find(
                                                                                        (p) => p.segment_ref_id ===
                                                                                            segment.ref_id &&
                                                                                            p.traveler_type === travelerType &&
                                                                                            p.type === 'checked'
                                                                                )?.description || 'Not Included'
                                                                                }}
                                                                            </span>
                                                                        </td>

                                                                        <!-- Cabin Baggage -->
                                                                        <td class="border border-gray-200 px-3 py-2">
                                                                            <span class="text-gray-600">
                                                                                {{
                                                                                    getSelectedFare(flightIndex)?.baggage_policies?.find(
                                                                                        (p) => p.segment_ref_id ===
                                                                                            segment.ref_id &&
                                                                                            p.traveler_type === travelerType &&
                                                                                            p.type === 'carry'
                                                                                )?.description || 'Not Included'
                                                                                }}
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                </template>

                                                                <!-- No baggage for this segment -->
                                                                <tr v-if="!getSelectedFare(flightIndex)?.baggage_policies?.some(
                                                                    (p) => p.segment_ref_id === segment.ref_id
                                                                )">
                                                                    <td colspan="3"
                                                                        class="border border-gray-200 px-3 py-2 text-center text-gray-500">
                                                                        No baggage allowance for this segment
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- No Baggage Policies Message -->
                                            <div v-else-if="getSelectedFare(flightIndex)"
                                                class="p-4 sm:p-6 text-center">
                                                <div
                                                    class="w-10 h-10 sm:w-12 sm:h-12 lg:w-16 lg:h-16 mx-auto mb-2 sm:mb-3 lg:mb-4 rounded-full bg-gray-200/60 flex items-center justify-center">
                                                    <Briefcase
                                                        class="w-5 h-5 sm:w-6 sm:h-6 lg:w-8 lg:h-8 text-gray-400" />
                                                </div>
                                                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-0.5 sm:mb-1">
                                                    No baggage policies available
                                                </p>
                                                <p class="text-xs text-gray-500">This fare doesn't include any baggage
                                                    allowance</p>
                                            </div>

                                            <!-- No Fare Selected Message -->
                                            <div v-else class="p-4 sm:p-6 text-center">
                                                <div
                                                    class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 mx-auto mb-2 sm:mb-3 rounded-full bg-gray-200/60 flex items-center justify-center">
                                                    <AlertCircle
                                                        class="w-4 h-4 sm:w-5 sm:h-5 lg:w-6 lg:h-6 text-gray-400" />
                                                </div>
                                                <p class="text-xs sm:text-sm text-gray-600 font-medium mb-0.5 sm:mb-1">
                                                    No fare selected for this flight
                                                </p>
                                                <p class="text-xs text-gray-500">Please select a fare from the fare
                                                    options tab</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State for No Flights -->
                                    <div v-else class="text-center py-6 sm:py-8">
                                        <div
                                            class="w-8 h-8 sm:w-10 sm:h-10 lg:w-12 lg:h-12 mx-auto mb-2 sm:mb-3 rounded-full bg-gray-200/60 flex items-center justify-center">
                                            <AlertCircle class="w-4 h-4 sm:w-5 sm:h-5 lg:w-6 lg:h-6 text-gray-400" />
                                        </div>
                                        <p class="text-xs sm:text-sm text-gray-600 font-medium mb-0.5 sm:mb-1">No flight
                                            data available</p>
                                        <p class="text-xs text-gray-500">Please check the flight details</p>
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>

                        <!-- Book Now Strip (Common For All Tabs) -->
                        <div
                            class="sticky bottom-0 bg-white border-t border-gray-100 p-3 sm:p-4 lg:p-6 mt-4 sm:mt-8 shadow-lg sm:shadow-none">
                            <div
                                class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-4">
                                <div
                                    class="flex flex-col sm:flex-row items-start sm:items-center gap-1 sm:gap-2">
                                    <div class="text-sm sm:text-base lg:text-xl font-bold text-gray-700">
                                        Total Price:
                                    </div>
                                    <div class="text-lg sm:text-xl lg:text-2xl font-bold text-primary">
                                        {{ formatAmount(calculateGrandTotal()) }}
                                    </div>
                                </div>
                                <button
                                    @click="goToCheckout"
                                    class="w-full sm:w-auto text-white py-2 sm:py-3 px-4 sm:px-8 rounded text-sm sm:text-base font-semibold transform transition-all duration-200 shadow-lg bg-primary hover:bg-primary/90 hover:scale-105"
                                >
                                    <span>{{ $t("book_now") }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
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

.mobile-result-search-panel {
    
}

.mobile-result-pill {
    box-shadow:
        0 10px 20px rgb(15 23 42 / 0.09),
        inset 0 1px 0 rgb(255 255 255 / 0.9);
}

.mobile-result-icon-bubble {
    display: inline-flex;
    height: 2.75rem;
    width: 2.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background: hsl(var(--secondary) / 0.12);
    color: hsl(var(--secondary));
}

.mobile-result-route-input :deep(input) {
    height: 2.5rem;
    border: 0;
    box-shadow: none;
    font-size: 1rem;
    font-weight: 700;
}

.mobile-result-route-input :deep(.relative.mt-1) {
    margin-top: 0;
}

.mobile-result-route-input :deep(input:focus) {
    border: 0;
    box-shadow: none;
    --tw-ring-shadow: 0 0 #0000;
}

.mobile-result-route-input :deep(input::placeholder) {
    font-size: 1rem;
    font-weight: 700;
}

.mobile-result-route-input :deep(.pointer-events-none.absolute.inset-0) {
    font-size: 1rem;
}

.mobile-result-route-input :deep(.pointer-events-none.absolute.inset-0 span) {
    font-size: 0.95rem;
}

.mobile-result-date-input :deep(button) {
    min-height: 2.5rem;
    border: 0;
    box-shadow: none;
    font-size: 1rem;
}

.mobile-result-date-input :deep(button:hover),
.mobile-result-date-input :deep(button:focus) {
    border: 0;
    box-shadow: none;
}

.mobile-result-date-input :deep(span) {
    font-size: 1rem;
    font-weight: 700;
}

.mobile-result-return-date :deep(button) {
    min-height: 2.5rem;
    width: 100%;
    justify-content: flex-start;
    border: 0;
    background: white;
    color: rgb(15 23 42);
    box-shadow: none;
}

.mobile-result-return-date :deep(svg) {
    color: hsl(var(--secondary));
}

.mobile-result-return-date :deep(span) {
    color: rgb(15 23 42);
    font-size: 1rem;
    font-weight: 700;
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
    font-family: "Arial", sans-serif;
    color: #333;
    background-color: #f9f9f9;
    border: 2px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

/* Add these animations to your styles */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

.animate-spin-slow {
    animation: spin-slow 2s linear infinite;
}

@keyframes light-sweep {
    0% {
        transform: translateX(-100%) skewX(-15deg);
    }

    100% {
        transform: translateX(200%) skewX(-15deg);
    }
}

@keyframes light-sweep-slow {
    0% {
        transform: translateX(-100%) skewX(-15deg);
    }

    100% {
        transform: translateX(200%) skewX(-15deg);
    }
}

.animate-light-sweep {
    animation: light-sweep 2s ease-in-out infinite;
}

.animate-light-sweep-slow {
    animation: light-sweep-slow 3s ease-in-out infinite;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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

/* Transition for backdrop */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Transition for side panel */
.slide-enter-active,
.slide-leave-active {
    transition: transform 0.3s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.5;
    }
}

/* Animation delays */
.animation-delay-50 {
    animation-delay: 50ms;
}

.animation-delay-100 {
    animation-delay: 100ms;
}

.animation-delay-150 {
    animation-delay: 150ms;
}

.animation-delay-200 {
    animation-delay: 200ms;
}

.animation-delay-250 {
    animation-delay: 250ms;
}

.animation-delay-300 {
    animation-delay: 300ms;
}

.animation-delay-350 {
    animation-delay: 350ms;
}

.animation-delay-400 {
    animation-delay: 400ms;
}

.animation-delay-450 {
    animation-delay: 450ms;
}

@media (max-width: 639px) {
    .flight-skeleton-column :deep(.p-skeleton) {
        max-width: 100%;
    }

    .mobile-leg-label {
        width: 100%;
        clip-path: none !important;
    }
}

@keyframes light-sweep {
    0% {
        transform: translateX(-100%);
    }

    100% {
        transform: translateX(100%);
    }
}

.animate-light-sweep {
    animation: light-sweep 1.5s ease-in-out infinite;
}
</style>
