<script setup>
import FlightFilterCard from "@/components/common/FlightFilterCard.vue";
import Spinner from "@/components/common/Spinner.vue";
import Header from "../components/shared/Header.vue";

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
    DialogTrigger,
} from "@/components/ui/dialog";
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
    Ellipsis
} from "lucide-vue-next";
import moment from "moment";
import Skeleton from "primevue/skeleton";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
const activeTab = ref("flights");
import { SlidersHorizontal } from "lucide-vue-next";
import Login from "./Login.vue";
import LoginMini from "./LoginMini.vue";

const isFilterOpen = ref(false);
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

// const availableAirlines = computed(() => flightStore.availableAirlines);
const previousSearch = JSON.parse(localStorage.getItem("previous_search"));

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
const maxTravelers = 9;
const countdown = ref(null);
const timerInterval = ref(null);
const showDialog = ref(true);
const isSideSheetOpen = ref(false);
const isSooperFlihgtDetailsOpen = ref(false);
const selectedFlightId = ref(null);
const selectedFlight = ref(null);
const loadingDetails = ref(false);
const pnr = ref(null);
const passengerCount = ref();
const selectedFares = reactive([]); // { 0: 'ref_id_1', 1: 'ref_id_2' }
const savedAmount = ref(0);

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

    // Load flight type only from localStorage
    flightType.value =
        flightType.value ?? previousSearch.flightType ?? "one-way";

    // If multi-city → initialize with saved trips OR default
    if (flightType.value === "multi-city") {
        multiCityTrips.value = previousSearch.trips ?? [
            { origin: null, destination: null, date: null },
            { origin: null, destination: null, date: null },
        ];
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

    const queryParams = { ...searchParams };
    if (searchParams.trips) {
        queryParams.trips = JSON.stringify(searchParams.trips);
    }

    localStorage.setItem("previous_search", JSON.stringify(searchParams));
    startCountdown(15 * 60 * 1000);

    router.push({
        name: "FlightSearch",
        query: queryParams,
    });

    // fetchFlights();
    fetchProviders();
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
const modelValue = ref({
    flightType: "one-way",
    countdownFor: 0,
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
watch(
    () => modelValue.value.flightType,
    (newVal) => {
        if (newVal === "one-way") {
            modelValue.value.dateRange.end = null;
        } else if (newVal === "multi-city") {
            modelValue.value.dateRange.start = null;
            modelValue.value.dateRange.end = null;
        }
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
        multiCityTrips: multiCityTrips.value,
    };
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
         <div class="bg-white shadow-sm overflow-hidden">
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
          <div class="w-full mx-2 sm:-mx-0">
            <div class="px-2 sm:px-0">
              <FlightFilterCard :countdown="countdown" v-model="modelValue" @search="setupFlightsParams" class="w-full" />
            </div>
          </div>

          <!-- Progress Bar -->
          <div v-if="isSearching" class="w-full mt-2 container">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-3 flex-1">
                <div class="relative flex-shrink-0">
                  <div class="w-6 h-6 border-2 border-primary/20 rounded-full"></div>
                  <div :class="[
                    'absolute top-0 left-0 w-6 h-6 border-2 rounded-full border-t-primary border-r-transparent border-b-transparent border-l-transparent',
                    progress > 0 ? 'animate-spin-slow' : 'animate-spin'
                  ]"></div>
                </div>
                <div class="flex-1 min-w-0">
                  <span class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">
                    {{ progress === 100 ? 'Found best flights!' : 'Searching for best flights...' }}
                  </span>
                </div>
              </div>
              <div class="flex-shrink-0">
                <span class="text-sm font-bold text-primary">{{ progress }}%</span>
              </div>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded h-6 relative overflow-hidden">
              <div
                class="absolute inset-0 bg-gradient-to-r from-gray-300/50 to-gray-300/30 dark:from-gray-600/50 dark:to-gray-600/30">
              </div>
              <div :class="[
                'h-full rounded transition-all duration-700 ease-out relative overflow-hidden',
                progress === 0 ? 'bg-primary' : 'bg-primary'
              ]" :style="{ width: progress > 0 ? progress + '%' : '25%' }">
                <div v-if="progress === 0"
                  class="absolute inset-0 bg-white/30 rounded animate-pulse"></div>
                <div :class="[
                  'absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent',
                  progress === 0 ? 'animate-light-sweep-slow' : 'animate-light-sweep'
                ]"></div>
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

  <!-- NEW LAYOUT: Filters Sidebar + Results -->
  <div v-if="filteredFlights && !isLoading && allFlights.length > 0" class="container mx-auto px-4 py-6">
    <div class="flex flex-col lg:flex-row gap-6">
      
      <!-- LEFT SIDEBAR FILTERS - Always visible, not accordion -->
      <div class="lg:w-80 flex-shrink-0 space-y-6">
        <!-- Price Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <BadgeDollarSign class="w-5 h-5 text-primary" />
            Price
          </h3>
          <div class="flex justify-between mb-2 text-sm">
            <span class="text-gray-600">{{ formatAmount(minPriceLimit) }}</span>
            <span class="text-gray-600">{{ formatAmount(maxPrice || maxPriceLimit) }}</span>
          </div>
          <input type="range" :min="minPriceLimit" :max="maxPriceLimit" v-model="maxPrice" @input="filterByPrice"
            class="w-full h-2 bg-gray-200 rounded-full accent-primary cursor-pointer" />
        </div>

        <!-- Stops Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <GitCommitHorizontal class="w-5 h-5 text-primary" />
            Stops
          </h3>
          <div class="space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="all" v-model="selectedStops" @change="filterByStops" class="accent-primary" />
              <span class="text-sm text-gray-700">All</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="0" v-model="selectedStops" @change="filterByStops" class="accent-primary" />
              <span class="text-sm text-gray-700">Non-Stop</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="1" v-model="selectedStops" @change="filterByStops" class="accent-primary" />
              <span class="text-sm text-gray-700">1 Stop</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="2" v-model="selectedStops" @change="filterByStops" class="accent-primary" />
              <span class="text-sm text-gray-700">2+ Stops</span>
            </label>
          </div>
        </div>

        <!-- Airlines Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <Plane class="w-5 h-5 text-primary" />
            Airlines
            <span v-if="selectedAirline.length" class="text-xs bg-gray-100 px-2 py-0.5 rounded-full">{{ selectedAirline.length }}</span>
          </h3>
          <div class="space-y-2 max-h-60 overflow-y-auto">
            <div class="flex justify-between items-center mb-2">
              <button @click="selectedAirline = []; filterByAirline();" class="text-xs text-primary hover:underline">Clear</button>
            </div>
            <label v-for="airline in availableAirlines" :key="airline.id" class="flex items-center gap-2 cursor-pointer py-1">
              <input type="checkbox" v-model="selectedAirline" :value="airline.id" @change="filterByAirline" class="accent-primary" />
              <span class="text-sm text-gray-700 truncate">{{ airline.name }}</span>
            </label>
          </div>
        </div>

        <!-- Duration Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <ClockIcon class="w-5 h-5 text-primary" />
            Duration
          </h3>
          <div class="space-y-3">
            <input type="range" :min="minDuration" :max="maxDuration" v-model="maxDurationFilter" @input="filterByDuration"
              class="w-full h-2 bg-gray-200 rounded-full accent-primary cursor-pointer" />
            <div class="flex justify-between text-sm">
              <span class="text-gray-600">{{ minDuration }}h</span>
              <span class="font-semibold text-primary">{{ maxDurationFilter || maxDuration }}h max</span>
            </div>
          </div>
        </div>

        <!-- Refundable Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
            <SquareCheckBig class="w-5 h-5 text-primary" />
            Refundable
          </h3>
          <div class="space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="all" v-model="refundableFilter" @change="filterByRefundable" class="accent-primary" />
              <span class="text-sm text-gray-700">All Flights</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="refundable" v-model="refundableFilter" @change="filterByRefundable" class="accent-primary" />
              <span class="text-sm text-gray-700">Refundable Only</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" value="non-refundable" v-model="refundableFilter" @change="filterByRefundable" class="accent-primary" />
              <span class="text-sm text-gray-700">Non-Refundable Only</span>
            </label>
          </div>
        </div>

        <!-- Departure Time Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3">Departure Time</h3>
          <div class="space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="departureTimes" value="morning" class="accent-primary" />
              <span class="text-sm text-gray-700">12:00 AM - 06:00 AM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="departureTimes" value="morningLate" class="accent-primary" />
              <span class="text-sm text-gray-700">06:00 AM - 12:00 PM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="departureTimes" value="afternoon" class="accent-primary" />
              <span class="text-sm text-gray-700">12:00 PM - 06:00 PM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="departureTimes" value="night" class="accent-primary" />
              <span class="text-sm text-gray-700">06:00 PM - 12:00 AM</span>
            </label>
          </div>
        </div>

        <!-- Arrival Time Filter -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
          <h3 class="font-semibold text-gray-800 mb-3">Arrival Time</h3>
          <div class="space-y-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="arrivalTimes" value="morning" class="accent-primary" />
              <span class="text-sm text-gray-700">12:00 AM - 06:00 AM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="arrivalTimes" value="morningLate" class="accent-primary" />
              <span class="text-sm text-gray-700">06:00 AM - 12:00 PM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="arrivalTimes" value="afternoon" class="accent-primary" />
              <span class="text-sm text-gray-700">12:00 PM - 06:00 PM</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="arrivalTimes" value="night" class="accent-primary" />
              <span class="text-sm text-gray-700">06:00 PM - 12:00 AM</span>
            </label>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-3">
          <button @click="resetAllFilters" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <ListRestart class="w-4 h-4 inline mr-1" /> Reset
          </button>
          <button @click="isShownMarginInput = !isShownMarginInput" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
            <Zap class="w-4 h-4" />
          </button>
        </div>
        <div v-if="isShownMarginInput">
          <Input v-model="priceMargin" type="number" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Price Margin" />
        </div>
      </div>

      <!-- RIGHT SECTION: Results Header + Flight List -->
      <div class="flex-1 min-w-0">
        <!-- Results Header with Cheapest | Fastest | Best Value -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 mb-4 shadow-sm">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm font-medium text-gray-700">
              Showing <span class="font-bold text-primary">{{ filteredFlights.length }}</span> results of <span class="font-bold">{{ allFlights.length }}</span>
            </div>

            <!-- Cheapest | Fastest | Best Value Tabs -->
            <div class="flex items-center justify-center bg-gray-100 rounded-full p-1">
              <div class="flex gap-1">
                <button @click="filteredFlights = [...allFlights].sort((a, b) => calculateTotalFare(a) - calculateTotalFare(b))"
                  class="flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-all bg-white shadow-sm text-primary">
                  <BadgeDollarSign class="w-4 h-4" />
                  <span>Cheapest</span>
                </button>
                <button @click="filteredFlights = [...allFlights].sort((a, b) => {
                  const getDuration = (flight) => flight.leg.flights.reduce((sum, leg) => sum + (leg.travel_time || 0), 0);
                  return getDuration(a) - getDuration(b);
                })"
                  class="flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-all text-gray-600 hover:bg-white/50">
                  <Zap class="w-4 h-4" />
                  <span>Fastest</span>
                </button>
                <button @click="filteredFlights = [...allFlights].sort((a, b) => calculateTotalFare(a) - calculateTotalFare(b))"
                  class="flex items-center gap-1.5 px-4 py-1.5 rounded-full text-sm font-medium transition-all text-gray-600 hover:bg-white/50">
                  <SquareCheckBig class="w-4 h-4" />
                  <span>Best Value</span>
                </button>
              </div>
            </div>

            <!-- Sort Dropdown -->
            <div class="flex items-center gap-2 text-sm">
              <ArrowDownUp class="w-4 h-4 text-gray-500" />
              <span class="text-gray-600">Sort by:</span>
              <select @change="if ($event.target.value === 'low') {
                filteredFlights = [...allFlights].sort((a, b) => calculateTotalFare(a) - calculateTotalFare(b));
              } else {
                filteredFlights = [...allFlights].sort((a, b) => calculateTotalFare(b) - calculateTotalFare(a));
              }" class="font-medium text-gray-900 bg-transparent border-none outline-none cursor-pointer">
                <option value="low">Price - Low To High</option>
                <option value="high">Price - High To Low</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Flight Results -->
        <div v-if="filteredFlights?.length > 0 && !isLoading" class="space-y-4">
          <div v-for="item in filteredFlights" :key="item?.leg?.ref_id"
            class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden transition-all hover:shadow-md">
            
            <!-- Single Flight (Direct) -->
            <div v-if="item?.leg?.flights?.length === 1" class="p-4 sm:p-5">
              <div class="flex flex-col lg:flex-row lg:items-center gap-5">
                <div class="flex items-center gap-3 lg:w-48 shrink-0">
                  <img class="w-10 h-10 object-contain" :src="item?.leg?.flights[0]?.marketing_carrier?.logo" :alt="item?.leg?.flights[0]?.marketing_carrier?.name" />
                  <div>
                    <span class="text-base font-bold text-gray-900">{{ item?.leg?.flights[0]?.marketing_carrier?.name }}</span>
                    <span class="text-xs text-gray-500 block">{{ item?.leg?.flights[0]?.marketing_carrier?.iata }} {{ formatFlightNumber(item?.leg?.flights[0]?.flight_number) }}</span>
                  </div>
                </div>

                <div class="flex-1 flex items-center justify-between gap-4">
                  <div>
                    <div class="text-2xl font-black text-gray-900">{{ moment.parseZone(item?.leg?.flights[0]?.departure_at).format("HH:mm") }}</div>
                    <div class="font-semibold text-gray-800">{{ item?.leg?.flights[0]?.from?.city?.name }}</div>
                    <div class="text-xs font-bold text-gray-400">{{ item?.leg?.flights[0]?.from?.city?.code }}</div>
                  </div>

                  <div class="flex-1 flex flex-col items-center px-2">
                    <span class="text-xs font-medium text-gray-500">{{ Math.floor(moment.duration(item?.leg?.flights[0]?.travel_time, 'm').asHours()) }}h {{ moment.duration(item?.leg?.flights[0]?.travel_time, 'm').minutes() }}m</span>
                    <div class="relative w-full flex items-center">
                      <div class="h-[2px] w-full bg-emerald-400 rounded-full opacity-60"></div>
                      <div class="absolute left-1/2 -translate-x-1/2 w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-white"></div>
                    </div>
                    <span class="text-[10px] font-medium mt-1 text-gray-500">{{ item?.leg?.flights[0]?.has_layovers ? `${item?.leg?.flights[0]?.layovers_count} Stop` : 'Non stop' }}</span>
                  </div>

                  <div class="text-right">
                    <div class="text-2xl font-black text-gray-900">{{ moment.parseZone(item?.leg?.flights[0]?.arrival_at).format("HH:mm") }}</div>
                    <div class="font-semibold text-gray-800">{{ item?.leg?.flights[0]?.to?.city?.name }}</div>
                    <div class="text-xs font-bold text-gray-400">{{ item?.leg?.flights[0]?.to?.city?.code }}</div>
                  </div>
                </div>

                <div class="lg:w-48 flex lg:flex-col items-center lg:items-end justify-between gap-3 border-t lg:border-t-0 pt-3 lg:pt-0 lg:border-l border-gray-100 lg:pl-5">
                  <div class="text-2xl font-black text-gray-900">{{ formatAmount(calculateTotalFare(item)) }}</div>
                  <button @click="openSooperFlightDetails(item)" class="bg-gradient-to-r from-[#49a7ff] to-[#065af3] hover:brightness-105 text-white px-6 py-2 rounded-lg font-bold text-sm uppercase tracking-wide">
                    Book Now
                  </button>
                </div>
              </div>
            </div>

            <!-- Multi Flight (Round Trip / Connecting) -->
            <div v-else class="p-4 sm:p-5">
              <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                  <img class="w-10 h-10 object-contain" :src="item?.leg?.flights[0]?.marketing_carrier?.logo" :alt="item?.leg?.flights[0]?.marketing_carrier?.name" />
                  <div>
                    <p class="font-bold text-gray-900">{{ item?.leg?.flights[0]?.marketing_carrier?.name }}</p>
                    <p class="text-xs text-gray-500">{{ (item?.leg?.flights || []).map(f => f?.marketing_carrier?.iata + ' ' + formatFlightNumber(f?.flight_number)).join(', ') }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-2xl font-black text-gray-900">{{ formatAmount(calculateTotalFare(item)) }}</p>
                  <button @click="openSooperFlightDetails(item)" class="mt-1 bg-gradient-to-r from-[#49a7ff] to-[#065af3] hover:brightness-105 text-white px-5 py-1.5 rounded-lg font-bold text-xs uppercase tracking-wide">
                    Book Now
                  </button>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="(leg, legIndex) in item?.leg?.flights" :key="legIndex" class="bg-gray-50 rounded-lg p-3 border border-gray-100">
                  <p class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full" :class="legIndex === 0 ? 'bg-blue-500' : 'bg-indigo-500'"></span>
                    {{ legIndex === 0 ? 'Depart' : 'Return' }} • {{ moment(leg?.departure_at).format("ddd, DD MMM") }}
                  </p>
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <p class="text-xl font-black text-gray-900">{{ moment.parseZone(leg?.departure_at).format("HH:mm") }}</p>
                      <p class="font-semibold text-gray-800">{{ leg?.from?.city?.name }}</p>
                      <p class="text-xs font-bold text-gray-400">{{ leg?.from?.city?.code }}</p>
                    </div>
                    <div class="flex-1 flex flex-col items-center px-1">
                      <p class="text-[10px] font-bold text-gray-400">{{ Math.floor(moment.duration(leg?.travel_time, "m").asHours()) }}h {{ moment.duration(leg?.travel_time, "m").minutes() }}m</p>
                      <div class="relative w-full flex items-center">
                        <div class="h-[1px] w-full bg-emerald-400 rounded-full opacity-50"></div>
                        <div class="absolute left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full bg-emerald-400"></div>
                      </div>
                      <p class="text-[9px] font-bold mt-1 text-gray-400 text-center">{{ leg?.has_layovers ? `${leg?.layovers_count} stop` : 'Non stop' }}</p>
                    </div>
                    <div class="text-right">
                      <p class="text-xl font-black text-gray-900">{{ moment.parseZone(leg?.arrival_at).format("HH:mm") }}</p>
                      <p class="font-semibold text-gray-800">{{ leg?.to?.city?.name }}</p>
                      <p class="text-xs font-bold text-gray-400">{{ leg?.to?.city?.code }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="px-5 py-2 bg-gray-50/80 border-t border-gray-100 flex items-center justify-between text-xs">
              <button @click="openSooperFlightDetails(item)" class="text-primary font-semibold hover:underline">View Flight Details</button>
              <div class="flex items-center gap-3">
                <span class="px-2 py-0.5 rounded bg-gray-200 text-gray-600">{{ item?.leg?.flights?.[0]?.cabin_class || "Economy" }}</span>
                <span :class="item?.leg?.flights[0]?.is_refundable ? 'text-emerald-600' : 'text-rose-500'" class="font-semibold">
                  {{ item?.leg?.flights[0]?.is_refundable ? "Refundable" : "Non-Refundable" }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results -->
        <div v-if="showNoFlightsState || showNoFilteredFlightsState"
          class="bg-white border border-gray-200 rounded-xl p-8 text-center">
          <img src="/public/assets/no-data.webp" alt="No flights found" class="w-28 h-auto mx-auto mb-4" />
          <h3 class="text-xl font-bold text-primary">{{ showNoFlightsState ? "No Flights Found" : "No Flights Match Filters" }}</h3>
          <p class="text-sm text-gray-600 mt-2 max-w-md mx-auto">
            {{ showNoFlightsState ? "We could not find flights for your selected route and date. Please try changing dates or nearby airports." : "Your selected filters are too strict. Try clearing some filters to see more flight options." }}
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- MORE FILTERS MODAL (kept for compatibility, but filters are now in sidebar) -->
  <Dialog v-model:open="showMoreFilters">
    <DialogContent class="w-full max-w-md sm:max-w-3xl max-h-[90vh] overflow-y-auto bg-white p-4 sm:p-8 rounded-2xl sm:rounded-3xl">
      <div class="sticky top-0 bg-white border-b border-gray-200 px-4 sm:px-8 py-4 sm:py-6 flex justify-between items-center z-10">
        <div>
          <DialogTitle class="text-xl sm:text-2xl font-bold text-gray-900">More Filters</DialogTitle>
          <DialogDescription class="text-sm sm:text-base text-gray-600 mt-1">
            Refine your flight search with additional options
          </DialogDescription>
        </div>
        <button @click="resetAllFilters(); showMoreFilters = false;" class="px-3 py-1.5 sm:px-5 sm:py-2.5 bg-primary text-white rounded text-xs sm:text-sm font-semibold hover:bg-primary/90 transition">
          Clear All
        </button>
      </div>

      <div class="p-4 sm:p-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-8">
          <!-- Departure Time -->
          <div>
            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Departure Time</h3>
            <div class="space-y-2 sm:space-y-3">
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="departureTimes" value="morning" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">12:00 AM - 06:00 AM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="departureTimes" value="morningLate" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">06:00 AM - 12:00 PM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="departureTimes" value="afternoon" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">12:00 PM - 06:00 PM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="departureTimes" value="night" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">06:00 PM - 12:00 AM</span>
              </label>
            </div>
          </div>

          <!-- Arrival Time -->
          <div>
            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Arrival Time</h3>
            <div class="space-y-2 sm:space-y-3">
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="arrivalTimes" value="morning" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">12:00 AM - 06:00 AM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="arrivalTimes" value="morningLate" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">06:00 AM - 12:00 PM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="arrivalTimes" value="afternoon" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">12:00 PM - 06:00 PM</span>
              </label>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="arrivalTimes" value="night" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700">06:00 PM - 12:00 AM</span>
              </label>
            </div>
          </div>

          <!-- Airlines -->
          <div>
            <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Airlines</h3>
            <div class="space-y-2 sm:space-y-3 max-h-40 sm:max-h-64 overflow-y-auto pr-2">
              <label v-for="airline in availableAirlines" :key="airline.id" class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="selectedAirline" :value="airline.id" @change="filterByAirline" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-xs sm:text-sm text-gray-700 flex-1">{{ airline.name }}</span>
              </label>
            </div>
          </div>

          <!-- Price + Duration + Stops + Refundable -->
          <div class="space-y-6 sm:space-y-8">
            <div>
              <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Price Range</h3>
              <div class="space-y-2 sm:space-y-4">
                <div class="flex justify-between text-xs sm:text-sm text-gray-600">
                  <span>{{ formatAmount(minPriceLimit) }}</span>
                  <span>{{ formatAmount(maxPriceLimit) }}</span>
                </div>
                <input type="range" :min="minPriceLimit" :max="maxPriceLimit" v-model="maxPrice" @input="filterByPrice" class="w-full h-2 sm:h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                <div class="text-center text-sm sm:text-lg font-bold text-primary">{{ formatAmount(maxPrice || maxPriceLimit) }}</div>
              </div>
            </div>

            <div>
              <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Flight Duration</h3>
              <div class="space-y-2 sm:space-y-4">
                <input type="range" min="0" :max="maxDuration" v-model="maxDurationFilter" @input="filterByDuration" class="w-full h-2 sm:h-3 bg-gray-200 rounded-full accent-primary cursor-pointer" />
                <div class="text-center text-xs sm:text-sm text-primary">Up to {{ maxDurationFilter || maxDuration }} hours</div>
              </div>
            </div>

            <div>
              <h3 class="text-sm sm:text-lg font-semibold text-gray-800 mb-2 sm:mb-4">Stops</h3>
              <div class="space-y-2 sm:space-y-3">
                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                  <input type="checkbox" value="0" v-model="selectedStopsArray" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                  <span class="text-xs sm:text-sm text-gray-700">Non-Stop</span>
                </label>
                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                  <input type="checkbox" value="1" v-model="selectedStopsArray" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                  <span class="text-xs sm:text-sm text-gray-700">1 Stop</span>
                </label>
                <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                  <input type="checkbox" value="2" v-model="selectedStopsArray" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                  <span class="text-xs sm:text-sm text-gray-700">2+ Stops</span>
                </label>
              </div>
            </div>

            <div>
              <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                <input type="checkbox" v-model="onlyRefundable" @change="filterByRefundable" class="accent-primary w-4 h-4 sm:w-5 sm:h-5 rounded" />
                <span class="text-sm sm:text-base font-medium text-gray-800">Refundable Only</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter class="sticky bottom-0 bg-white border-t border-gray-200 px-4 sm:px-8 py-4 sm:py-6 flex justify-end gap-3 sm:gap-6">
        <Button @click="showMoreFilters = false" variant="outline" class="px-6 py-2 sm:px-8 sm:py-3 text-sm sm:text-base">Cancel</Button>
        <Button @click="showMoreFilters = false; applyAllFilters();" class="px-8 py-2 sm:px-10 sm:py-3 bg-primary text-white font-semibold text-sm sm:text-base">Apply Filters</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Mobile filter toggle button (visible on small screens) -->
  <div class="lg:hidden fixed bottom-4 right-4 z-40">
    <button @click="showMoreFilters = true" class="bg-primary text-white p-3 rounded-full shadow-lg">
      <SlidersHorizontal class="w-5 h-5" />
    </button>
  </div>

  <!-- Search Expired Dialog -->
  <div v-if="showDialog" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center z-30 justify-center p-8">
    <div class="bg-white p-4 sm:p-6 rounded shadow-lg w-full sm:w-1/4 text-center">
      <img src="/public/assets/clock.svg" alt="Logo" class="mx-auto mb-4 w-16 sm:w-32" />
      <h2 class="text-lg font-bold">Still Around?</h2>
      <p class="mt-2">Your search has been inactive for more than 15 minutes. Please refresh the page to update.</p>
      <div class="mt-4 flex justify-center gap-2">
        <button @click="$router.push({ name: 'Home' })" class="mt-4 text-base px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">Start new search</button>
        <button @click="confirmReload" class="mt-4 text-base px-4 py-2 bg-primary text-white rounded hover:bg-primary/90">Refresh</button>
      </div>
    </div>
  </div>

        <!-- Flight Details Side Panel - RESPONSIVE -->
        <Transition name="fade">
            <div v-if="isSooperFlihgtDetailsOpen" class="fixed inset-0 bg-black bg-opacity-50 z-40"
                @click="isSooperFlihgtDetailsOpen = false"></div>
        </Transition>

        <Transition name="slide-sooper">
            <div v-if="isSooperFlihgtDetailsOpen"
                class="fixed top-0 right-0 h-full w-full sm:w-[95%] md:w-[98%] lg:w-[1200px] xl:w-[1400px] bg-white shadow-2xl z-40 overflow-y-auto">

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
                <div class="p-3 sm:p-4 lg:p-6">
                    <div v-if="loadingDetails" class="flex justify-center items-center h-96">
                        <Spinner />
                    </div>

                    <div v-if="selectedFlight" class="space-y-4 sm:space-y-6">
                        <!-- Main Tabs - Mobile Optimized -->
                        <Tabs default-value="fare-options" class="w-full ">
                            <!-- Tabs Navigation - Mobile Scrollable -->
                            <div class="w-full border-b border-primary/40">
                                <TabsList class="flex justify-start items-end gap-2 bg-transparent p-0 w-full ">

                                    <!-- Fare Options -->
                                    <TabsTrigger value="fare-options" class="group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
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
         group-data-[state=active]:block">
                                        </span>

                                    </TabsTrigger>

                                    <!-- Flight Itinerary -->
                                    <TabsTrigger value="flight-details" class="group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
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
         group-data-[state=active]:block">
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
                                    <TabsTrigger value="fare-breakdown" class="group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
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
         group-data-[state=active]:block">
                                        </span>
                                    </TabsTrigger>
                                    <!-- Baggage -->
                                    <TabsTrigger value="baggage-details" class="group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
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
         group-data-[state=active]:block">
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
                                    <div class="border ">
                                        <div v-for="(
segment, segmentIndex
                                ) in flight?.segments" :key="segmentIndex" class="bg-white border  overflow-hidden">
                                            <!-- Layover Info -->

                                            <div v-if="segment?.layover_time"
                                                class="bg-amber-100 border-b border-amber-100 p-2 sm:p-3 lg:p-4">
                                                <div class="flex items-center justify-center gap-1 sm:gap-2">
                                                    <ClockIcon class="w-3 h-3 sm:w-4 sm:h-5 text-amber-600" />
                                                    <span class="text-xs sm:text-sm font-semibold text-amber-800">
                                                        Layover:
                                                        {{
                                                            moment
                                                                .utc(
                                                                    moment
                                                                        .duration(
                                                                            segment.layover_time,
                                                                            "minutes",
                                                                        )
                                                                        .asMilliseconds(),
                                                                )
                                                                .format("HH:mm")
                                                        }}
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Segment Details - Mobile Stacked -->
                                            <div class="p-2 sm:p-3 lg:p-6">

                                                <div
                                                    class="flex flex-col lg:grid lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
                                                    <!-- Departure - Mobile First -->

                                                    <div
                                                        class="flex items-center justify-between space-y-1 sm:space-y-2 lg:space-y-3">
                                                        <div class="flex items-center gap-2 px-2 py-1">
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
                                                        <div class="space-y-1 sm:space-y-2 lg:space-y-2">
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
                                                        class="flex flex-col items-center justify-center space-y-2 sm:space-y-3 lg:space-y-4 py-2 sm:py-0">
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
                                                    <div class="space-y-1 sm:space-y-2 lg:space-y-2 md:mt-3">
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
                                            'flex flex-col lg:flex-row gap-4 sm:gap-6',
                                            selectedFlight?.leg?.flights.length == 2
                                                ? ''
                                                : '',
                                        ]">
                                            <div v-for="(leg, legIndex) in selectedFlight?.leg?.flights" :key="legIndex"
                                                class="mb-4 sm:mb-8 lg:mb-0">
                                                <!-- Professional Header Strip - Mobile Responsive -->
                                                <div v-if="selectedFlight?.leg?.flights.length !== 1"
                                                    class="flex flex-col sm:flex-row items-stretch bg-white rounded sm:rounded overflow-hidden border border-gray-200 mx-auto">

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

                                        <!-- Flight Tabs Navigation - Mobile Scrollable -->
                                        <Tabs :default-value="selectedFlight?.leg?.flights[0]?.ref_id"
                                            class="w-full mt-4 sm:mt-6">
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
                                            <div class="w-full border-b border-primary/40">

                                                <TabsList class="flex justify-start items-end gap-2
           bg-transparent p-0
           w-full">

                                                    <TabsTrigger
                                                        v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                                        :key="flightIndex" :value="flight.ref_id" class="relative group flex-shrink-0
             text-sm sm:text-base font-medium
             px-5 py-2.5
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
         group-data-[state=active]:block">
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
                                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-6">
                                                    <!-- Fare Card - Mobile Compact -->
                                                    <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex"
                                                        @click="selectFares(flightIndex, fare.ref_id)"
                                                        class="border border-gray-200 rounded overflow-hidden hover:border-primary transition-all duration-200 cursor-pointer"
                                                        :class="selectedFares[flightIndex] === fare.ref_id ? 'border-primary ring-1 sm:ring-2 ring-primary/20' : ''">
                                                        <!-- Fare Header with Price -->
                                                        <div class="bg-gray-50 p-3 sm:p-4 border-b border-gray-200">
                                                            <div class="flex items-center justify-between">
                                                                <h5 class="font-bold text-sm sm:text-lg text-gray-800">
                                                                    {{
                                                                        fare?.name_class || fare?.class || 'Standard' }}
                                                                </h5>
                                                            </div>
                                                            <div class="mt-1 sm:mt-2 flex items-baseline">
                                                                <span
                                                                    class="text-lg sm:text-xl lg:text-2xl font-bold text-primary">{{
                                                                        formatAmount(calculateFare(fare)) }}</span>
                                                            </div>
                                                        </div>

                                                        <!-- Fare Details - Mobile Simplified -->
                                                        <div class="p-3 sm:p-4 space-y-3 sm:space-y-4">
                                                            <!-- Baggage Policies by Segment -->
                                                            <div class="space-y-2 sm:space-y-3">
                                                                <div v-for="(segment, segmentIndex) in flight?.segments"
                                                                    :key="segmentIndex"
                                                                    class="border-b border-gray-100 pb-2 sm:pb-3 last:border-b-0">
                                                                    <div
                                                                        class="text-xs sm:text-sm font-semibold text-gray-900 mb-1 sm:mb-2 flex items-center gap-1 sm:gap-2">
                                                                        <div
                                                                            class="w-1 h-1 sm:w-1.5 sm:h-1.5 bg-primary rounded-full">
                                                                        </div>
                                                                        {{ segment.from.iata }} → {{ segment.to.iata }}
                                                                        <span v-for="(code, codeIndex) in fare?.booking_codes?.filter(
                                                                            (c) => c.segment_ref_id === segment.ref_id
                                                                        )" :key="codeIndex">
                                                                            <span
                                                                                class="text-gray-400 mx-0.5 sm:mx-1">|</span>
                                                                            <span
                                                                                class="text-xs font-medium text-primary">{{
                                                                                    code.booking_code }}</span>
                                                                        </span>
                                                                    </div>
                                                                    <div class="ml-3 sm:ml-4 space-y-1 sm:space-y-2">
                                                                        <template
                                                                            v-for="travelerType in ['adult', 'child', 'infant', 'ADLT', 'CHLD', 'INFT', 'ADT', 'CHD', 'INF']">
                                                                            <div v-if="fare?.baggage_policies.some(
                                                                                (p) => p.segment_ref_id === segment?.ref_id && p.traveler_type === travelerType
                                                                            )" :key="travelerType"
                                                                                class="space-y-0.5 sm:space-y-1">
                                                                                <div v-for="(policy, policyIndex) in fare?.baggage_policies.filter(
                                                                                    (p) => p.segment_ref_id === segment.ref_id && p.traveler_type === travelerType
                                                                                )" :key="policyIndex"
                                                                                    class="flex items-start gap-1 sm:gap-2 rounded transition-colors">
                                                                                    <span
                                                                                        class="inline-flex items-center justify-center w-1.5 h-1.5 sm:w-2 sm:h-2 bg-primary rounded-full border border-primary flex-shrink-0 mt-0.5 sm:mt-1">
                                                                                        <component
                                                                                            :is="policy.type === 'carry' ? 'BriefcaseBusiness' : 'Briefcase'"
                                                                                            class="w-3 h-3 sm:w-4 sm:h-4 text-primary mt-0.5 flex-shrink-0" />
                                                                                    </span>
                                                                                    <span
                                                                                        class="text-xs sm:text-sm text-gray-700 leading-tight">
                                                                                        {{ policy.description || "N/A"
                                                                                        }}
                                                                                        ({{ travelerType }})
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Fare Policies - Mobile Compact -->
                                                            <div v-if="fare?.fare_policies?.[flightIndex]"
                                                                class="mt-2 sm:mt-4">
                                                                <h6
                                                                    class="font-semibold text-gray-700 mb-2 sm:mb-3 px-1 text-xs sm:text-sm">
                                                                    Included
                                                                    in this fare</h6>

                                                                <div class="space-y-1 sm:space-y-2">
                                                                    <div v-for="(service, serviceIndex) in fare?.fare_policies"
                                                                        :key="serviceIndex"
                                                                        class="flex items-center px-2 py-1.5 sm:px-3 sm:py-2.5 rounded hover:bg-primary/5 transition-colors duration-150">

                                                                        <!-- Check Icon with Primary Background -->
                                                                        <div
                                                                            class="flex-shrink-0 w-4 h-4 sm:w-5 sm:h-5 rounded-full bg-primary/10 flex items-center justify-center">
                                                                            <Check
                                                                                class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-primary" />
                                                                        </div>

                                                                        <!-- Service Text -->
                                                                        <span
                                                                            class="text-xs sm:text-sm text-gray-600 leading-tight ml-2 sm:ml-3">
                                                                            {{ service }}
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <!-- Compact Empty State -->
                                                                <div v-if="!fare?.fare_policies"
                                                                    class="text-center py-3 sm:py-4 px-2 sm:px-3 border border-dashed border-gray-200 rounded bg-gray-50/50">
                                                                    <p class="text-xs sm:text-sm text-gray-500">No
                                                                        special policies
                                                                        included</p>
                                                                </div>
                                                            </div>

                                                            <!-- Additional Services - Mobile Compact -->
                                                            <div
                                                                v-if="fare?.additional_services && fare.additional_services.length > 0">
                                                                <h6
                                                                    class="font-semibold text-gray-700 mb-1 sm:mb-2 text-xs sm:text-sm">
                                                                    At Additional
                                                                    Cost</h6>
                                                                <div class="space-y-0.5 sm:space-y-1">
                                                                    <div v-for="(service, serviceIndex) in fare.additional_services"
                                                                        :key="serviceIndex"
                                                                        class="flex items-center text-xs sm:text-sm text-gray-600">
                                                                        <DollarSign
                                                                            class="w-3 h-3 sm:w-4 sm:h-4 mr-1 sm:mr-2" />
                                                                        <span>{{ service.name }}: {{ service.cost
                                                                        }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
