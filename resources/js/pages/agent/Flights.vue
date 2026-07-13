<script setup>
import FlightFilterCard from "@/components/common/FlightFilterCard.vue";
import Spinner from "@/components/common/Spinner.vue";
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
    DialogTrigger
} from "@/components/ui/dialog";
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import Input from "@/components/ui/input/Input.vue";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { calculateLayover, calculateTypeMargin, formatAmount, formatDate } from "@/lib/utils";
import { calculateFinalPrice } from "@/lib/utils.js";
import {
    FETCH_AGENT_DATA,
    FETCH_AIRPORTS,
    FETCH_FLIGHT,
    FETCH_PROVIDERS,
    FETCH_AIRPORT_MARGINS,
} from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";

import FlightAnimationLoader from "@/components/common/FlightAnimationLoader.vue";
import {
    ClockIcon,
    LoaderCircle,
    Plane,
    SquareCheckBig,
    SquareX,
    Users,
    Utensils,
    X,
    Zap
} from "lucide-vue-next";
import moment from "moment";
import Skeleton from "primevue/skeleton";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import { fetchRate } from "../../lib/utils";

const activeTab = ref("flights");

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

const store = useStore();
const flightStore = useFlightStore();
const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const flightType = ref("one-way");
const providers = computed(() => store.getters["flight/providers"]);
const flights = computed(() => flightStore.flights);
const allFlights = ref([]);
const sortedSooperFlights = computed(() => flightStore.sortedSooperFlights);

const sooperFlights = computed(() => flightStore.sooperFlights);
// const cheapestFlightsByAirline = computed(
//     () => flightStore.getCheapestFlightsByAirline,
// );

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const isLoading = computed(() => flightStore.isLoading || store.getters["flight/isLoading"]);
// const availableAirlines = computed(() => flightStore.availableAirlines);
const previousSearch = JSON.parse(localStorage.getItem("previous_search"));
// Computed margins from store
 const airportMargins = computed(() => store.getters["airport/airportMargin"] || {});

// Function to fetch margins
 const fetchMargins = async () => {
    await store.dispatch("airport/" + FETCH_AIRPORT_MARGINS);
};

const loading = ref(true);
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
const showDialog = ref(false);
const isSideSheetOpen = ref(false);
const isSooperFlihgtDetailsOpen = ref(false);
const selectedFlightId = ref(null);
const selectedFlight = ref(null);
const loadingDetails = ref(false);
const pnr = ref(null);
const passengerCount = ref();
const selectedFares = reactive([]); // { 0: 'ref_id_1', 1: 'ref_id_2' }



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
        flightType.value ??
        previousSearch.flightType ??
        "one-way";

    // If multi-city → initialize with saved trips OR default
    if (flightType.value === "multi-city") {
        multiCityTrips.value =
            previousSearch.trips ??
            [
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
                return selectedAirline.value.includes(fare.marketing_carrier.iata);
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
        currencyCode: "AED",
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
    })
}

watch(providers, () => {
    fetchFlights();
})

watch(sooperFlights, (newFlights) => {
    if (newFlights && newFlights.length > 0) {
        allFlights.value = [...allFlights.value, ...newFlights];

        // Sort allFlights by price (ascending)
        // Sort allFlights by lowest fare price (ascending)
        allFlights.value.sort((a, b) => {
            // Calculate total price for all fares in all flights (handles return/multi-leg)
            const getTotalPrice = (item) => {
                if (item?.leg?.flights && Array.isArray(item.leg.flights)) {
                    return item.leg.flights.reduce((sum, flight) => {
                        if (flight?.fares && flight.fares.length > 0) {
                            return sum + (flight.fares[0]?.billable_price || 0);
                        }
                        return sum;
                    }, 0);
                }
                return item?.pricing?.totalPrice || 0;
            };
            const priceA = getTotalPrice(a);
            const priceB = getTotalPrice(b);
            return priceA - priceB;
        });

        // Also update filteredFlights
        filteredFlights.value = [...allFlights.value];


        const totalProviders = providers.value.length;
        const completedProviders = allFlights.value.length; // maybe rethink this assumption
        progress.value = Math.round((completedProviders / totalProviders) * 100);
    }
}, { deep: true });

const cheapestFlightsByAirline = computed(() => {
    if (!allFlights.value || allFlights.value.length === 0) return [];
    const map = new Map();
    allFlights.value.forEach(flight => {
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
    allFlights.value.forEach(flight => {
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
    // if (!selectedAirline.value || selectedAirline.value.length === 0) {
    //   filteredFlights.value = allFlights.value; // Show all flights if no airline selected
    //   return;
    // }
    // filteredFlights.value = allFlights.value.filter(flight => {
    //   const carrier = flight?.leg?.flights?.[0]?.marketing_carrier;
    //   if (!carrier) return false;
    //   const carrierId =
    //     carrier.id || carrier.iata || carrier.code || carrier.name;
    //   return selectedAirline.value.includes(carrierId);
    // });
    sortFlights();

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
})
const fetchFlights = () => {
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
        currencyCode: "AED",
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
        localStorage.setItem(
            "previous_search",
            JSON.stringify(searchParams),
        );
        // const prioritizedProviders = ["SABRE", "EMIRATES", "TURKISH", "NUFLIGHTS"];
        // const reorderedProviders = [
        //     ...providers.value.filter(p => !prioritizedProviders.includes(p.identifier)),
        //     ...providers.value.filter(p => prioritizedProviders.includes(p.identifier))
        // ];


        providers.value.forEach(provider => {
            const paramsWithProvider = { ...searchParams, airline: provider.identifier };
            flightStore.fetchFlights(paramsWithProvider)

                .catch(error => {
                    console.error(`Error fetching flights for ${provider.identifier}:`, error);
                })
                .finally(() => {
                    completedProviders.value++;

                    progress.value = Math.round((completedProviders.value / providers.value.length) * 100);

                    if (completedProviders.value === providers.length) {
                        setTimeout(() => {
                            isSearching.value = false;
                        }, 1000);
                    }

                });
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
            dateRange.value.end =
                newQuery.return_date || dateRange.value.end;
        }

        classType.value = newQuery.cabin_class || classType.value;
        adults.value = parseInt(newQuery.adults) || adults.value;
        children.value = parseInt(newQuery.children) || children.value;
        infants.value = parseInt(newQuery.infants) || infants.value;
    },
    { immediate: true }
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
        name: "Flights",
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
const calculateFareMargin = (basePrice, marginAmount, marginType, amountType) => {
  const price = parseFloat(basePrice) || 0;
  let margin = 0;

  if (marginType === "discount") {
    if (amountType === "percent") {
      margin = -((price * (parseFloat(marginAmount) || 0)) / 100);
    } else {
      margin = -(parseFloat(marginAmount) || 0);
    }
  } else if (marginType === "markup") {
    // Margin can be percent or amount
    if (amountType === "percent") {
      margin = (price * (parseFloat(marginAmount) || 0)) / 100;
    } else {
      margin = parseFloat(marginAmount) || 0;
    }
  }
  return margin;
};

function calculateTotalFare(item) {
    let total = 0;

    item?.leg?.flights?.forEach((leg) => {
        if (!leg?.fares?.length) return;

        // 🔹 Find the lowest fare by total_price or billable_price
        const cheapestFare = leg.fares.reduce((minFare, current) => {
            const minPrice = parseFloat(minFare?.billable_price || minFare?.total_price || Infinity);
            const currentPrice = parseFloat(current?.billable_price || current?.total_price || Infinity);
            return currentPrice < minPrice ? current : minFare;
        });

        if (cheapestFare) {
            const billable = parseFloat(cheapestFare.base_price || 0)
                + parseFloat(cheapestFare.surchage || 0)
                + parseFloat(cheapestFare.taxes || 0)
                + parseFloat(cheapestFare.fees || 0)
                + parseFloat(cheapestFare.service_charges || 0)
                + parseFloat(cheapestFare.ancillaries_charges || 0);

            const airlineMargin = calculateFareMargin(
                cheapestFare.base_price,
                cheapestFare.margin_amount || 0,
                cheapestFare.margin_type,
                cheapestFare.amount_type
            );
            const typeMargin = parseFloat(calculateTypeMargin(
        user.value,
        airportMargins.value,
    )) * passengerCount.value;
            const agentMargin =
                parseFloat(agentData.value?.agent_data?.margin_amount || 0) *
                passengerCount.value;

            const agentDiscount =
                parseFloat(agentData.value?.agent_data?.agent_discount || 0) *
                passengerCount.value;

            // 🔹 Add the lowest fare’s total
            total +=
                parseFloat(cheapestFare.billable_price || 0) +
                agentMargin -
                agentDiscount + typeMargin +
                parseFloat(airlineMargin || 0);
        }
    });

    // 🔹 Apply global price margin (if applicable)
    total += Number(priceMargin.value || 0);

    return total;
}





function calculateFare(fare) {
    let total = 0;
    const billablePrice = fare?.billable_price || 0;
        const airlineMargin = calculateFareMargin(
            fare.base_price,
            fare.margin_amount || 0,
            fare.margin_type,
            fare.amount_type
        );
        const typeMargin = parseFloat(calculateTypeMargin(
        user.value,
        airportMargins.value,
    )) * passengerCount.value;
        if (fare) {
              const billable =parseFloat(fare.base_price) + parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0);
        
            const agentMargin =
                parseFloat(agentData.value?.agent_data?.margin_amount || 0) *
                passengerCount.value;
            const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0) *
                passengerCount.value;
            total += billablePrice + agentMargin - agentDiscount + typeMargin + (parseFloat(airlineMargin || 0)) + Number(priceMargin.value || 0);
            const surcharge = billablePrice - billable;
           
    return total;
}
}

function calculateGrandTotal() {
    let total = 0;

    selectedFlight?.value?.leg?.flights?.forEach((flight) => {
        flight?.fares?.forEach(fare => {
            if (selectedFares.includes(fare.ref_id)) {

                total += calculateFare(fare);
            }
        });
    });

    return total;
}


function goToCheckout() {
    localStorage.setItem("selectedFlight", JSON.stringify(selectedFlight.value));
    router.push({
        name: "AgentCheckout",
        query: {
            flight_id: selectedFlight.value?.leg?.ref_id,
            fares: JSON.stringify(selectedFares), // 👈 stringify array
            flight_source: 1,
            flight_provider: selectedFlight.value?.provider?.name || 'N/A',
            flight_type: route.query.flightType || 'one-way',
            flight_mode: "B2B",
            passenger_count: passengerCount.value,
            adults: parseInt(route.query.adults) || 1,
            children: parseInt(route.query.children) || 0,
            infants: parseInt(route.query.infants) || 0,
            price_margin: priceMargin.value || 0,
        },
    });
}
function findSegmentName(segmentRefId, segments) {
    const segment = segments.find(seg => seg.ref_id === segmentRefId);
    return segment ? `${segment.from.iata} → ${segment.to.iata}` : 'N/A';
};




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
watch(() => modelValue.value.flightType, (newVal) => {
    if (newVal == 'single') {
        modelValue.value.dateRange.end = null;
    } else if (newVal == 'multi-city') {
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

onMounted(() => {
    initializeSearchParams();
    fetchMargins();
    resetFlightParams();
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
</script>

<template>
    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
        <FlightAnimationLoader v-if="isLoading" :type="modelValue.flightType" :origin="modelValue.origin"
            :destination="modelValue.destination" :routes="modelValue.multiCityTrips" />
        <div class="flex overflow-x-auto scrollbar-hide border-b border-gray-200">

        </div>

        <div class="p-4">
            <div v-if="activeTab === 'flights'" class="animate-fadeIn">
                <div v-if="isLoading" class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                    <Spinner />
                </div>
                <div v-else>
                    <div class="mb-2">
                        <Breadcrumb>
                            <BreadcrumbList>
                                <!-- Home -->
                                <BreadcrumbItem>
                                    <BreadcrumbLink class="hover:cursor-pointer"
                                        @click.prevent="router.push({ name: 'DashboardFlights' })">
                                        Flights
                                    </BreadcrumbLink>
                                </BreadcrumbItem>

                                <BreadcrumbSeparator />

                                <!-- Flights (Go Back instead of fixed href) -->
                                <BreadcrumbItem>
                                    <BreadcrumbPage>Flight Search</BreadcrumbPage>
                                </BreadcrumbItem>



                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                    <FlightFilterCard :countdown="countdown" v-model="modelValue" @search="setupFlightsParams">
                    </FlightFilterCard>
                   <div v-if="isSearching"  class="w-full bg-gray-200 mt-2 rounded dark:bg-gray-700 relative overflow-hidden">
        <div
          class="bg-primary text-xs font-medium text-blue-100 text-center p-1 leading-none rounded transition-all duration-500 ease-out relative overflow-hidden"
          :style="{ width: progress + '%' }">
          <!-- Pulsing blur overlay -->
          <div class="absolute inset-0 bg-white/20 rounded animate-pulse"></div>

          <!-- Moving light streak -->
          <div
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-white/40 rounded-full animate-light-sweep">
          </div>

          <!-- Progress text -->
          <span class="relative z-10">{{ progress }}%</span>
        </div>
      </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center justify-end w-full gap-2 mt-2">
                            <Input v-if="isShownMarginInput" v-model="priceMargin" type="number" class="w-[200px]"
                                placeholder="Price Margin" />
                            <Button @click="
                                isShownMarginInput = !isShownMarginInput
                                ">
                                <Zap class="w-5 h-5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else-if="activeTab === 'importPnr'" class="animate-fadeIn">
                <h3 class="text-lg font-medium text-gray-900 mb-4">
                    Enter PNR to import.
                </h3>
                <div class="flex gap-4 p-4">
                    <Input v-model="pnr" type="text" class="w-[200px]" placeholder="PNR" />
                    <Button @click="importPnr(pnr)">Import PNR</Button>
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
    <div v-if="!isLoading && allFlights" class="mt-6">
        <Carousel class="flex items-center gap-x-3 relative w-full" :opts="{ align: 'start' }">
            <div>
                <CarouselPrevious />
            </div>
            <CarouselContent>

                <CarouselItem v-for="flight in cheapestFlightsByAirline" :key="flight.id"
                    class="md:basis-1/2 lg:basis-1/6 min-w-[200px] flex-shrink-0 px-2 mx-2">
                    <div class="h-full">
                        <div @click="openSooperFlightDetails(flight)"
                            class="bg-white border border-gray-200 p-4 rounded hover:shadow-md transition-all duration-200 cursor-pointer select-none h-full flex flex-col">
                            <div class="flex items-center">
                                <div class="w-10 h-10 border-gray-100 flex items-center justify-center mr-3">
                                    <img :src="flight?.id
                                        ? flight?.legs[0]?.stops[0]
                                            ?.airline?.logo_url
                                        : flight?.leg?.flights[0]
                                            ?.marketing_carrier?.logo
                                        " alt="" class="w-6 h-6 sm:w-7 sm:h-7 object-contain" />
                                </div>
                                <span class="text-xs font-light text-gray-800 line-clamp-1">
                                    {{
                                        flight?.id
                                            ? flight?.legs[0]?.stops[0]?.airline
                                                ?.name
                                            : flight?.leg?.flights[0]
                                                ?.marketing_carrier?.name
                                    }}
                                </span>
                            </div>
                            <!-- <div class="text-xs text-gray-500 mb-2">
                                {{ flight.legs[0].stops.length > 1 ?
                                    flight.legs[0].stops.length - 1 + ' Stop' + (flight.legs[0].stops.length > 2 ? 's' : '')
                                    : 'Direct Flight' }}
                            </div> -->
                            <div class="mt-auto pt-2 border-t border-gray-100">
                                <p class="text-normal font-base text-primary">
                                    {{
                                        formatAmount(
                                            calculateTotalFare(flight)
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
            <div>
                <CarouselNext />
            </div>
        </Carousel>
    </div>

    <div class="flex flex-col md:flex-row gap-x-10">
        <div v-if="isLoading"
            class="bg-white rounded-lg h-[400px] w-full md:w-[450px] p-4 flex items-center justify-center border mt-8">
            <div role="status">
                <LoaderCircle class="w-5 h-5 animate-spin text-primary" />
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <div v-if="!isLoading && filteredFlights" class="relative w-full md:w-[450px]">
            <div class="top-1 mt-4 p-4 bg-white rounded-lg h-[150vh] overflow-y-auto">
                <div class=" border rounded-lg p-4 flex flex-col">
                    <span class="text-sm text-muted-foreground font-medium">{{
                        $t("search_queries")
                        }}</span>
                    <div class="flex-grow">
                        <div v-if="flightType === 'multi-city'">
                            <div v-for="(trip, index) in multiCityTrips" :key="index">
                                <div class="flex items-center">
                                    <span class="text-lg font-semibold">{{
                                        trip.origin
                                        }}</span>
                                    <span class="mx-2">To</span>
                                    <span class="text-lg font-semibold">{{
                                        trip.destination
                                        }}</span>
                                </div>
                                <div>
                                    <span class="text-sm text-muted-foreground mr-2">{{ $t("departure_date") }}:</span>
                                    <span>{{ trip.date }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else>
                            <div class="flex items-center">
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
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center gap-x-2 bg-gray-50 rounded-lg px-3 py-2 cursor-pointer border border-gray-200 hover:border-primary transition"
                                :class="{ 'border-primary bg-primary/10': selectedStops === 'all' }">
                                <input v-model="selectedStops" @change="filterByStops" type="radio" value="all"
                                    name="flight-stops" class="hidden" />
                                <span class="text-gray-500 text-base">{{ $t("all") }}</span>
                            </label>
                            <label
                                class="flex items-center gap-x-2 bg-gray-50 rounded-lg px-3 py-2 cursor-pointer border border-gray-200 hover:border-primary transition"
                                :class="{ 'border-primary bg-primary/10': selectedStops === '0' }">
                                <input v-model="selectedStops" @change="filterByStops" type="radio" value="0"
                                    name="flight-stops" class="hidden" />
                                <span class="text-gray-500 text-base">Non-{{ $t("stop") }}</span>
                            </label>
                            <label
                                class="flex items-center gap-x-2 bg-gray-50 rounded-lg px-3 py-2 cursor-pointer border border-gray-200 hover:border-primary transition"
                                :class="{ 'border-primary bg-primary/10': selectedStops === '1' }">
                                <input v-model="selectedStops" @change="filterByStops" type="radio" value="1"
                                    name="flight-stops" class="hidden" />
                                <span class="text-gray-500 text-base">1 {{ $t("stop") }}</span>
                            </label>
                            <label
                                class="flex items-center gap-x-2 bg-gray-50 rounded-lg px-3 py-2 cursor-pointer border border-gray-200 hover:border-primary transition"
                                :class="{ 'border-primary bg-primary/10': selectedStops === '2' }">
                                <input v-model="selectedStops" @change="filterByStops" type="radio" value="2"
                                    name="flight-stops" class="hidden" />
                                <span class="text-gray-500 text-base">2 {{ $t("stops") }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("price") }}
                        </p>
                        <div class="flex items-center gap-3">
                            <input type="range" min="0"
                                :max="Math.max(...allFlights.map(f => calculateTotalFare(f)), 10000)" step="1"
                                v-model="maxPrice" @input="filterByPrice" class="w-full accent-primary" />
                            <span class="text-sm text-gray-700 font-semibold">
                                {{formatAmount(maxPrice || Math.max(...allFlights.map(f => calculateTotalFare(f)), 0))
                                }}
                            </span>
                        </div>
                    </div>
                    <div class="mt-3 ">
                        <div class="flex items-center justify-between ">
                            <p class="my-3 text-gray-500 font-medium text-sm">
                                {{ $t("airline") }}
                            </p>
                            <Button class="text-xs bg-white" size="sm" variant="outline" @click="
                                selectedAirline = [];
                            filterByAirline();
                            ">
                                Reset
                            </Button>
                        </div>

                        <ul>
                            <li v-for="item in availableAirlines" :key="item.id"
                                class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input v-model="selectedAirline" :id="item.name" type="checkbox" :value="item.id"
                                        @change="filterByAirline(item.id)"
                                        class="accent-primary w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" />
                                    <label :for="item.name"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base">
                                        <div class="w-6 h-6 rounded-full overflow-hidden">
                                            <img class="w-full h-full object-cover" :src="item?.logo_url
                                                ? item?.logo_url
                                                : item?.logo
                                                " alt="" />
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
        <div class="grid w-full mt-8">
            <!-- Sooper Item -->
            <div class="w-full">
                <!-- <pre>{{ sooperFlights }}</pre> -->
                <div v-if="filteredFlights?.length > 0 && !isLoading" class="mt-4 space-y-4">
                    <Collapsible v-model:open="item.isOpen" v-for="(item) in filteredFlights" :key="item?.leg?.ref_id"
                        class="bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-lg hover:border-gray-300 transition-all duration-300 overflow-hidden group">
                        <!-- BADGE TYPE DATA DEMO -->
                        <div class="mb-4 flex gap-2">
                                <span
                                class="inline-flex items-center px-2 py-1  bg-blue-100 text-blue-700 text-xs font-semibold">
                                {{ item?.provider?.source || 'N/A' }}
                            </span>

                        </div>
                        <!-- HARDCODED FLIGHT DEMO -->

                        <div class="grid grid-cols-5 gap-4 p-6 items-center relative">
                            <!-- Subtle gradient overlay -->
                            <div
                                class="absolute inset-0 bg-gradient-to-r from-blue-50/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            </div>
                            <!-- Left Section (Airline Info) -->
                            <div class="col-span-1 flex flex-col items-start relative z-10">
                                <div v-for="(leg, legIndex) in item?.leg
                                    ?.flights" :key="leg.ref_id" class="mb-3 relative">
                                    <div class="relative group/tooltip inline-block">
                                        <img class="w-12 h-12 object-contain rounded-lg border border-gray-100 p-1 bg-white shadow-sm cursor-pointer hover:shadow-md transition-shadow duration-200"
                                            :src="leg?.marketing_carrier?.logo" alt="" />
                                        <!-- Tooltip for airline name -->
                                        <div
                                            class="absolute z-20 bg-gray-200 text-black text-xs rounded-lg px-3 py-2 shadow-lg whitespace-nowrap opacity-0 group-hover/tooltip:opacity-100 transition-opacity duration-200 top-full mt-2 left-0 min-w-max">
                                            <span class="block">{{
                                                leg?.marketing_carrier?.name
                                                }}</span>
                                            <div class="absolute -top-1 left-4 w-2 h-2 bg-gray-200 rotate-45"></div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-600 font-medium mt-1">
                                        {{ leg?.marketing_carrier?.iata }}
                                        {{ leg?.flight_number }}

                                    </p>
                                    <div class="flex gap-1 mt-2">
                                        <div class="inline-flex items-center rounded-full px-2 py-1 text-[10px] font-medium gap-1 border"
                                            :class="leg?.is_refundable
                                                ? 'bg-green-50 border-green-200 text-green-700'
                                                : 'bg-red-50 border-red-200 text-red-700'
                                                ">
                                            <SquareCheckBig class="w-3 h-3" v-if="leg?.is_refundable" />
                                            <SquareX v-else class="w-3 h-3" />
                                            <span class="font-medium">
                                                {{
                                                    leg?.is_refundable
                                                        ? "Refundable"
                                                        : "Non-Refundable"
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Middle Section (Flight Route) -->
                            <div class="col-span-3 flex flex-col items-center relative z-10">
                                <div v-for="(leg, legIndex) in item?.leg
                                    ?.flights" :key="legIndex" class="w-full">
                                    <div class="flex justify-between items-center mb-3 relative">
                                        <!-- Departure -->
                                        <div class="text-center">
                                            <span class="text-xl font-bold text-gray-900">{{
                                                moment
                                                    .parseZone(
                                                        leg?.departure_at,
                                                    )
                                                    .format("HH:mm")
                                            }}</span>
                                            <p class="text-sm font-semibold text-gray-700">
                                                {{ leg?.from?.city?.code ?? leg?.from?.city }}
                                            </p>
                                            <!-- <p class="text-xs text-gray-500">{{ moment(leg?.departure_at).format("ddd DD MMM") }}</p> -->
                                            <p class="text-xs text-gray-500">
                                                {{ moment(leg?.departure_at).format("ddd DD MMM") }}
                                            </p>
                                        </div>

                                        <!-- Flight Path -->
                                        <div class="flex flex-col items-center flex-1 mx-6 relative">
                                            <span class="text-xs text-gray-500 mb-1 font-medium">
                                                {{
                                                    Math.floor(moment.duration(leg.travel_time, "m").asHours())
                                                }}h
                                                {{
                                                    moment
                                                        .duration(
                                                            leg.travel_time,
                                                            "m",
                                                        )
                                                        .minutes()
                                                }}m
                                            </span>

                                            <!-- Flight line with plane icon -->
                                            <div class="relative w-full flex items-center">
                                                <div class="flex-1 h-0.5 bg-gradient-to-r from-primary/30 to-primary">
                                                </div>
                                                <div
                                                    class="mx-2 w-6 h-6 bg-primary rounded-full flex items-center justify-center">
                                                    <div class="w-3 h-3 bg-white rounded-full"></div>
                                                </div>
                                                <div class="flex-1 h-0.5 bg-gradient-to-r from-primary to-primary/30">
                                                </div>
                                            </div>

                                            <div class="mt-2">
                                                <div v-if="leg?.has_layovers"
                                                    class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded-full font-medium border border-orange-200">
                                                    {{
                                                        leg?.layovers_count
                                                    }}
                                                    Stop
                                                </div>
                                                <div v-else
                                                    class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full font-medium border border-green-200">
                                                    Non-Stop
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Arrival -->
                                        <div class="text-center">
                                            <span class="text-xl font-bold text-gray-900">{{
                                                moment
                                                    .parseZone(
                                                        leg?.arrival_at,
                                                    )
                                                    .format("HH:mm")
                                            }}</span>
                                            <p class="text-sm font-semibold text-gray-700">
                                                {{ leg?.to?.city?.code }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ moment(leg?.arrival_at).format("ddd DDMMM") }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- <div class="text-center">
                                        <span class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded-full">Terminal:
                                            {{ leg?.terminal }}</span>
                                    </div> -->
                                </div>
                            </div>
                            <!-- Right Section (Price and Book) -->
                            <div class="col-span-1 flex flex-col items-end relative z-10">
                                <div class="mb-4 text-right">
                                    <p class="text-2xl font-bold text-gray-900">
                                        {{
                                            formatAmount(
                                                calculateTotalFare(item),
                                            )
                                        }}
                                    </p>
                                    <p class="text-xs text-gray-500 font-medium">
                                        Total Price
                                    </p>
                                </div>

                                <!-- <button 
                                @click="$router.push({
                                    name: 'AgentFlightCheckout',
                                    query: {
                                        flight_id: item?.leg?.ref_id,
                                        fare_id: item?.leg?.flights[0]?.fares[0]?.ref_id,
                                        fare_id2: item?.leg?.flights[1]?.fares[0]?.ref_id ? item?.leg?.flights[1]?.fares[0]?.ref_id : null,
                                        provider_ref: item?.ref_id,
                                        flight_source: 1,
                                        passenger_count: passengerCount,
                                        price_margin: priceMargin || 0
                                    }
                                })" 
                                class="inline-flex items-center justify-center rounded-lg bg-primary text-white py-2.5 px-6 text-sm font-semibold hover:bg-primary/90 transform hover:scale-105 transition-all duration-200 shadow-md hover:shadow-lg">
                                    Book Now
                                </button> -->
                                <button @click="openSooperFlightDetails(item)"
                                    class="inline-flex items-center justify-center rounded-lg bg-primary text-white py-2.5 px-6 text-sm font-semibold hover:bg-primary/90 transform hover:scale-105 transition-all duration-200 shadow-md hover:shadow-lg">
                                    Book Now
                                </button>

                                <p class="text-xs text-primary mt-3 cursor-pointer hover:text-primary/80 font-medium underline decoration-dotted"
                                    @click="openSooperFlightDetails(item)">
                                    Flight Details
                                </p>
                            </div>
                        </div>
                    </Collapsible>
                </div>
                <div v-if="!isLoading && !sooperFlights"
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
            <!-- Sabre Items -->
            <div class="w-full">
                <div v-if="flights && !isLoading" class="mt-4">
                    <Collapsible v-model:open="item.isOpen" v-for="(item, index) in flights?.itineraries" :key="index"
                        class="bg-white border-[.5px] hover:shadow-sm hover:scale-105 transition-all duration-150 h-fit cursor-pointer">

                        <div class="grid grid-cols-4 px-4 py-2 border">
                            <div class="grid grid-cols-1 content-center">
                                <div class="flex flex-col mb-2" v-for="(leg, legIndex) in item.legs" :key="legIndex">
                                    <img class="w-16 h-16 object-contain" :src="item.legs[legIndex].stops[0].airline
                                        ?.logo_url
                                        " alt="" />
                                    <p class="text-sm p-0">
                                        {{
                                            item.legs[legIndex].stops[0].airline
                                                ?.name +
                                            " (" +
                                            item.legs[legIndex].stops[0].airline
                                                ?.iata_code +
                                            ")"
                                        }}
                                    </p>
                                    <div class="font-light text-xs">
                                        {{
                                            item.legs[legIndex].stops[0]
                                                .aircraft?.name
                                        }}
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <div
                                        class="inline-flex items-start rounded px-3 py-1 text-[10px] bg-green-100 text-green-700 gap-1">
                                        <Users class="w-3 h-3 text-primary" />
                                        <span class="font-light">{{
                                            item?.passengerInfo[0]
                                                .fareComponents[0]
                                                .segments[0].segment
                                                .seatsAvailable
                                        }}
                                            {{
                                                item?.passengerInfo[0]
                                                    .fareComponents[0]
                                                    .segments[0].segment
                                                    .seatsAvailable === 1
                                                    ? "Seat"
                                                    : "Seats"
                                            }}
                                            Available</span>
                                    </div>
                                    <div class="inline-flex items-center rounded px-2 py-1 text-[10px] font-light text-white gap-1"
                                        :class="!item?.passengerInfo[0]
                                            .nonRefundable
                                            ? 'bg-green-100'
                                            : 'bg-red-100'
                                            ">
                                        <SquareCheckBig class="w-3 h-3 text-primary" v-if="
                                            !item?.passengerInfo[0]
                                                .nonRefundable
                                        " />
                                        <SquareX v-else class="w-3 h-3 text-red-500" />
                                        <span :class="!item?.passengerInfo[0]
                                            .nonRefundable
                                            ? 'text-primary'
                                            : 'text-red-500'
                                            " class="text-primary font-light text-[10px]">
                                            {{
                                                item?.passengerInfo[0]
                                                    .nonRefundable
                                                    ? "Non-Refundable"
                                                    : "Refundable"
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-span-2 items-center justify-self-center justify-items-center w-full">
                                <div v-for="(leg, legIndex) in item.legs" :key="legIndex" class="w-[400px] p-2">
                                    <div class="flex items-center justify-center">
                                        <span
                                            class="flex gap-2 text-xs text-muted-foreground font-light bg-gray-50 px-3 py-1 rounded">
                                            <span>
                                                {{
                                                    (moment
                                                        .duration(
                                                            leg.duration,
                                                            "m",
                                                        )
                                                        .asHours() |
                                                        Math.floor) +
                                                    " hr" +
                                                    ((moment
                                                        .duration(
                                                            leg.duration,
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
                                                            leg.duration,
                                                            "m",
                                                        )
                                                        .minutes() +
                                                    " m" +
                                                    (moment
                                                        .duration(
                                                            leg.duration,
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
                                        <!-- v-for="(stop, stopIndex) in leg.stops" :key="stopIndex" -->
                                        <div class="flex flex-col items-center">
                                            <span class="text-xl whitespace-nowrap text-gray-800 font-bold">
                                                {{
                                                    moment(
                                                        leg.stops[0].departure
                                                            .time,
                                                        "hh:mm",
                                                    ).format("HH:mm")
                                                }}
                                            </span>
                                            <span class="text-xl whitespace-nowrap text-gray-800 font-bold">
                                                {{
                                                    leg?.stops[0]?.departure
                                                        ?.airport?.iata_code
                                                }}
                                            </span>
                                        </div>

                                        <div class="w-full relative">
                                            <div
                                                class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-3 h-3 bg-primary border-2 border-white ring-2 ring-primary/20">
                                            </div>
                                            <hr class="border-primary/30 border-dashed border-t-2" />
                                            <div
                                                class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-3 h-3 bg-primary border-2 border-white ring-2 ring-primary/20">
                                            </div>
                                        </div>
                                        <div class="flex flex-col items-center">
                                            <span class="text-xl whitespace-nowrap text-gray-700 font-bold">
                                                {{
                                                    moment(
                                                        leg.stops[
                                                            leg.stops.length - 1
                                                        ].arrival.time,
                                                        "hh:mm",
                                                    ).format("HH:mm")
                                                }}
                                            </span>
                                            <span class="text-xl whitespace-nowrap text-gray-800 font-bold">
                                                {{
                                                    leg?.stops[
                                                        leg.stops.length - 1
                                                    ]?.arrival.airport
                                                        ?.iata_code
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <div class="flex gap-2 text-base font-bold text-gray-800">
                                            {{
                                                leg.stops[0].departure
                                                    ?.iata_code
                                            }}
                                        </div>
                                        <div v-if="leg.stops.length > 1"
                                            class="text-xs bg-gray-50 px-2 py-1 rounded text-gray-500 font-light relative group cursor-help">
                                            <span>{{ leg.stops.length - 1 }}
                                                {{
                                                    $t(
                                                        leg.stops.length === 2
                                                            ? "stop"
                                                            : "stops",
                                                    )
                                                }}</span>
                                            <div
                                                class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-800 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 whitespace-nowrap z-10 shadow-lg">
                                                {{ getLayoverInfo(leg.stops) }}
                                            </div>
                                        </div>
                                        <div v-else
                                            class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded font-light">
                                            {{ $t("non_stop") }}
                                        </div>
                                        <div class="flex gap-2 text-xs font-light text-gray-800">
                                            {{
                                                leg.stops[leg.stops.length - 1]
                                                    .arrival?.iata_code
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 justify-items-end content-center gap-2">
                                <div class="flex items-center gap-2">
                                    <div class="hidden">
                                        {{
                                            passengerCount =
                                            item.passengerInfo.reduce(
                                                (total, p) =>
                                                    total +
                                                    p.passengerNumber,
                                                0,
                                            )
                                        }}
                                    </div>
                                    <p class="text-sm font-normal">
                                        {{
                                            formatAmount(
                                                calculateFinalPrice(
                                                    item.pricing.totalPrice -
                                                    item.pricing
                                                        .totalTaxAmount,
                                                    item.legs[0].stops[0]
                                                        .airline
                                                        ?.margin_amount *
                                                    passengerCount,
                                                    item.legs[0].stops[0]
                                                        .airline?.margin_type,
                                                    item.legs[0].stops[0]
                                                        .airline?.amount_type,
                                                ) +
                                                item.pricing
                                                    .totalTaxAmount +
                                                parseFloat(
                                                    agentData?.agent_data
                                                        ?.margin_amount,
                                                ) *
                                                passengerCount +
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
                                                price_margin:
                                                    priceMargin || 0,
                                                flight_source: 0,
                                            },
                                        })
                                        "
                                        class="inline-flex items-center justify-center rounded text-white py-1 px-2 text-xs font-light bg-primary hover:bg-green-700 hover:text-white">
                                        <span>{{ $t("book_now") }}</span>
                                    </button>
                                </div>
                                <p class="bg-green-100 rounded font-light text-xs px-2 py-1 text-primary text-center"
                                    @click="openFlightDetails(item.id)">
                                    Flight Details
                                </p>
                            </div>
                        </div>
                    </Collapsible>
                </div>
                <!-- <div v-if="!isLoading && !flights?.itineraries"
                    class="flex items-center justify-center p-12 bg-white rounded-lg border mt-8">
                    <span>{{ $t("nothing_found") }}.</span>
                </div> -->
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
        </div>

        <div v-if="showDialog" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center z-30 justify-center">
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
            <div v-if="selectedFlight.legs == undefined"
                class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                <Spinner />
            </div>
            <div>
                <div>
                    <div class="p-2 max-w-sm bg-primary mt-4">
                        <span class="ml-8 text-xl text-white">Flight Details </span><span class="text-white">(
                            {{
                                selectedFlight.legs[0]?.stops[0].departure
                                    .airport?.city_name
                            }}
                            to
                            {{
                                selectedFlight.legs[0]?.stops[
                                    selectedFlight.legs[0]?.stops.length - 1
                                ].arrival.airport?.city_name
                            }}
                            )</span>
                    </div>
                    <div class="flex gap-4 items-center p-6">
                        <p class="text-gray-500 font-medium">
                            Departure:
                            {{ selectedFlight?.dates[0].departureDate }}
                        </p>

                        <div
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-indigo-100 text-indigo-700 gap-1">
                            <Users class="w-3 h-3 text-indigo-700" />
                            <span>{{
                                selectedFlight?.passengerInfo[0]
                                    .fareComponents[0].segments[0].segment
                                    .seatsAvailable
                            }}
                                {{
                                    seatsAvailable === 1 ? "Seat" : "Seats"
                                }}
                                Available</span>
                        </div>
                        <div
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-amber-100 text-amber-700 gap-1">
                            <Utensils class="w-3 h-3 text-amber" />
                            <span>{{
                                selectedFlight?.passengerInfo[0]
                                    .fareComponents[0].segments[0].segment
                                    .mealCode == "M"
                                    ? "MEAL"
                                    : "NO-SNACK"
                            }}</span>
                        </div>

                        <div class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold text-white gap-1"
                            :class="!selectedFlight?.passengerInfo[0].nonRefundable
                                ? 'bg-green-100 '
                                : 'bg-red-100 '
                                ">
                            <SquareCheckBig class="w-4 h-4 text-primary" v-if="
                                !selectedFlight?.passengerInfo[0]
                                    .nonRefundable
                            " />
                            <SquareX v-else class="w-4 h-4 text-red-500" />

                            <span :class="!selectedFlight?.passengerInfo[0]
                                .nonRefundable
                                ? 'text-primary '
                                : 'text-red-500'
                                " class="text-primary font-light text-xs">{{
                                    selectedFlight?.passengerInfo[0]
                                        .nonRefundable
                                        ? "Non-Refundable"
                                        : "Refundable"
                                }}</span>
                        </div>
                        <div
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs bg-orange-100 text-orange-700 gap-1">
                            <Users class="w-3 h-3 text-orange-700" />
                            <span>{{
                                selectedFlight?.passengerInfo[0]
                                    .fareComponents[0].segments[0].segment
                                    .cabinCode === "Y"
                                    ? "Economy"
                                    : selectedFlight?.passengerInfo[0]
                                        .fareComponents[0].segments[0]
                                        .segment.cabinCode === "S"
                                        ? "Premium Economy"
                                        : selectedFlight?.passengerInfo[0]
                                            .fareComponents[0].segments[0]
                                            .segment.cabinCode === "C"
                                            ? "Business Class"
                                            : selectedFlight?.passengerInfo[0]
                                                .fareComponents[0].segments[0]
                                                .segment.cabinCode === "J"
                                                ? "Premium Business"
                                                : selectedFlight?.passengerInfo[0]
                                                    .fareComponents[0].segments[0]
                                                    .segment.cabinCode === "F"
                                                    ? "First Class"
                                                    : selectedFlight?.passengerInfo[0]
                                                        .fareComponents[0]
                                                        .segments[0].segment
                                                        .cabinCode === "P"
                                                        ? "Premium First"
                                                        : "Others"
                            }}</span>
                        </div>
                    </div>
                    <div v-for="(leg, legIndex) in selectedFlight.legs" :key="legIndex">
                        <div v-for="(stop, stopIndex) in selectedFlight.legs[
                            legIndex
                        ].stops" :key="stop.id" class="bg-gradient-to-r from-rose-100/50 to-teal-100/50">
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
                                                        ?.city_name
                                                }}
                                                <span class="font-medium text-muted-foreground">({{
                                                    stop.departure.airport
                                                        ?.iata_code
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
                                                        moment
                                                            .parseZone(
                                                                stop.departure
                                                                    .time,
                                                            )
                                                            .format("HH:mm")
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
                                                    }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <div class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium">
                                                    {{
                                                        stop.departure.airport
                                                            ?.iata_code
                                                    }}
                                                </div>
                                                <div class="flex gap-2 text-sm text-muted-foreground font-medium">
                                                    {{
                                                        stop.arrival.airport
                                                            ?.iata_code
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
                                                        ?.city_name
                                                }}
                                                <span class="font-medium text-muted-foreground">({{
                                                    stop.arrival.airport
                                                        ?.iata_code
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
                </div>

                <!-- return flight -->

                <!-- Convert fare information and baggage details to tabs -->
                <div class="p-2 max-w-sm bg-primary mt-4">
                    <span class="ml-8 text-xl text-white">Baggages Allowance
                    </span>
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
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-primary mb-1">
                                    Check-in Baggage
                                </span>
                                <div class="text-sm font-semibold">
                                    {{
                                        selectedFlight.passengerInfo[0]
                                            .baggage[0].pieces || 1
                                    }}
                                    Piece(s), Total
                                    {{
                                        selectedFlight.passengerInfo[0]
                                            .baggage[0].weight
                                    }}
                                    {{
                                        selectedFlight.passengerInfo[0]
                                            .baggage[0].unit
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-2 max-w-sm bg-primary mt-4">
                    <span class="ml-8 text-xl text-white">Fare Information
                    </span>
                </div>
                <div>
                    <div v-for="(
passenger, index
                        ) in selectedFlight.passengerInfo" :key="index"
                        class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow duration-200 overflow-hidden">
                        <!-- Header -->
                        <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-700">
                                        Passenger
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded-full">
                                        {{ passenger.passengerNumber }}
                                        {{ passenger.passengerType }}
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
                                                    passenger.passengerTotalFare
                                                        .equivalentAmount *
                                                    passenger.passengerNumber,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.margin_amount *
                                                    passenger.passengerNumber,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.margin_type,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.amount_type,
                                                ) +
                                                parseFloat(
                                                    agentData?.agent_data
                                                        ?.margin_amount *
                                                    passenger.passengerNumber,
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
                                                passenger.passengerTotalFare
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
                                                    passenger.passengerTotalFare
                                                        .equivalentAmount *
                                                    passenger.passengerNumber,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.margin_amount *
                                                    passenger.passengerNumber,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.margin_type,
                                                    selectedFlight.legs[0]
                                                        .stops[0].airline
                                                        ?.amount_type,
                                                ) +
                                                passenger.passengerTotalFare
                                                    .totalTaxAmount *
                                                passenger.passengerNumber +
                                                parseFloat(
                                                    agentData?.agent_data
                                                        ?.margin_amount *
                                                    passenger.passengerNumber,
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

    <Transition name="fade">
        <div v-if="isSooperFlihgtDetailsOpen" class="fixed inset-0 bg-black bg-opacity-50 z-40"
            @click="isSooperFlihgtDetailsOpen = false"></div>
    </Transition>
    <Transition name="slide-sooper">
        <div v-if="isSooperFlihgtDetailsOpen"
            class="fixed top-0 right-0 h-full w-full md:w-[900px] bg-white shadow-2xl z-50 overflow-y-auto">
            <!-- Header with close button area -->
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 z-10">
                <div class="flex items-center gap-4">
                    <button @click="isSooperFlihgtDetailsOpen = false"
                        class="text-primary bg-primary/30 h-8 w-8 rounded-full hover:text-gray-700 flex items-center justify-center">
                        <X class="w-4 h-4" />
                    </button>
                    <h2 class="text-2xl font-bold text-primary">Flight Details</h2>

                </div>
            </div>

            <div class="p-6">
                <div v-if="loadingDetails" class="flex justify-center items-center h-96">
                    <Spinner />
                </div>

                <div v-if="selectedFlight" class="space-y-6">
                    <Tabs :default-value="selectedFlight?.leg?.flights[0]?.ref_id" class="w-full">
                        <!-- Updated tabs styling for cleaner look -->
                        <TabsList class="grid item-center w-full  rounded"
                            :style="{ gridTemplateColumns: `repeat(${selectedFlight?.leg?.flights?.length}, minmax(0, 1fr))` }">
                            <TabsTrigger v-for="(flight, flightIndex) in selectedFlight?.leg?.flights"
                                :key="flightIndex" :value="flight.ref_id"
                                class="text-sm font-medium  rounded data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name }}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent v-for="(flight, flightIndex) in selectedFlight?.leg?.flights" :key="flightIndex"
                            :value="flight.ref_id" class="mt-2">
                            <!-- Updated flight header section -->
                            <div class="bg-primary rounded p-4 mb-2">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xl font-bold text-white">
                                        Flight Details: {{ flight?.from?.city?.name }} → {{ flight?.to?.city?.name }}
                                    </h3>
                                    <div class="inline-flex items-center rounded px-3 py-1 text-xs font-medium gap-2 bg-white backdrop-blur-sm"
                                        :class="flight?.is_refundable ? 'text-green-500' : 'text-red-500'">
                                        <SquareCheckBig class="w-4 h-4" v-if="flight?.is_refundable" />
                                        <SquareX v-else class="w-4 h-4" />
                                        <span class="font-semibold">
                                            {{ flight?.is_refundable ? "Refundable" : "Non-Refundable" }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <!-- Updated segments layout with better spacing and white cards -->
                            <div class="">
                                <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex"
                                    class="bg-white border border-primary rounded overflow-hidden">
                                    <!-- Layover information -->

                                    <div v-if="segment?.layover_time" class="bg-amber-50 border-b border-amber-100 p-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <ClockIcon class="w-5 h-5 text-amber-600" />
                                            <span class="text-sm font-semibold text-amber-800">
                                                Layover: {{ moment.utc(moment.duration(segment.layover_time,
                                                    "minutes").asMilliseconds()).format("HH:mm") }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Redesigned segment details with better grid layout -->
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                            <!-- Departure Information -->
                                            <div class="space-y-3">
                                                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                                    Departure</div>
                                                <div class="text-lg font-bold text-gray-900">
                                                    {{ formatDate(segment?.departure_at) }}
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <img class="w-10 h-10 rounded-full border-2 border-gray-100"
                                                        :src="segment?.operating_carrier?.logo" alt="" />
                                                    <div>
                                                        <div class="font-semibold text-gray-900">{{
                                                            segment?.operating_carrier?.name }}</div>
                                                        <div class="text-sm text-gray-500">{{ segment?.flight_number
                                                            ?? "N/A" }}</div>
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="font-semibold text-gray-900">
                                                        {{ segment?.from?.name }}
                                                        <span class="text-gray-500 font-normal">({{ segment?.from?.iata
                                                            }})</span>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        Terminal: {{ segment?.from_terminal?.Gate ?? "N/A" }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Flight Path -->
                                            <div class="flex flex-col items-center justify-center space-y-4">
                                                <div class="flex items-center gap-4 w-full max-w-xs">
                                                    <span class="text-lg font-bold text-gray-900">
                                                        {{ moment.parseZone(segment?.departure_at).format("HH:mm") }}
                                                    </span>
                                                    <div class="flex-1 relative">
                                                        <div
                                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 w-3 h-3 bg-primary rounded-full">
                                                        </div>
                                                        <div class="border-t-2 border-dashed border-primary"></div>
                                                        <div
                                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 w-3 h-3 bg-primary rounded-full">
                                                        </div>
                                                    </div>
                                                    <span class="text-lg font-bold text-gray-900">
                                                        {{ moment.parseZone(segment?.arrival_at).format("HH:mm") }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex justify-between w-full max-w-xs text-sm text-gray-500 font-medium">
                                                    <span>{{ segment?.from?.iata }}</span>
                                                    <span>{{ segment?.to?.iata }}</span>
                                                </div>
                                            </div>

                                            <!-- Arrival Information -->
                                            <div class="space-y-3 text-right lg:text-left">
                                                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">
                                                    Arrival</div>
                                                <div class="text-lg font-bold text-gray-900">
                                                    {{ formatDate(segment?.arrival_at) }}
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="font-semibold text-gray-900">
                                                        {{ segment?.to?.name }}
                                                        <span class="text-gray-500 font-normal">({{ segment?.to?.iata
                                                            }})</span>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        Terminal: {{ segment?.to_terminal?.Gate ?? "N/A" }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Updated fare section with cleaner design -->
                            <div class="mt-2">
                                <div class="bg-primary rounded p-4 mb-2">
                                    <h4 class="text-xl font-bold text-white">Fare & Baggage Information</h4>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex"
                                        @click="selectedFares[flightIndex] = fare.ref_id"
                                        class="bg-white border-2 rounded-md p-6 cursor-pointer transition-all duration-200 hover:border-primary hover:shadow-md"
                                        :class="selectedFares[flightIndex] === fare.ref_id ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                        <div class="flex flex-col gap-4">
                                            <!-- <pre>{{ fare }}</pre> -->
                                            <!-- Radio Button and Fare Details -->
                                            <div class="flex items-center justify-between gap-6">
                                                <input type="radio" :name="'fare-' + flightIndex" class="hidden"
                                                    :value="fare.ref_id" v-model="selectedFares[flightIndex]" />
                                                <div class="flex-1">
                                                    <div class="font-bold text-gray-900 text-sm">{{ fare?.name_class }}
                                                    </div>

                                                </div>

                                                <div class="text-right">
                                                    <div class="text-lg font-bold text-primary">
                                                        {{ formatAmount(calculateFare(fare)) }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="space-y-3">
                                                <div v-for="(segment, segmentIndex) in flight?.segments"
                                                    :key="segmentIndex"
                                                    class="border-b border-gray-100 pb-3 last:border-b-0">
                                                    <!-- Segment Header with inline booking code filtering -->
                                                    <div
                                                        class="text-sm font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                                        <div class="w-1.5 h-1.5 bg-primary rounded-full"></div>
                                                        {{ segment.from.iata }} → {{ segment.to.iata }}
                                                        <!-- Inline booking_code mapping -->
                                                        <span v-for="(code, codeIndex) in fare?.booking_codes?.filter(
                                                            (c) => c.segment_ref_id === segment.ref_id
                                                        )" :key="codeIndex">
                                                            <span class="text-gray-400 mx-1">|</span>
                                                            <span class="text-xs font-medium text-primary">
                                                                {{ code.booking_code }}
                                                            </span>
                                                        </span>
                                                    </div>
                                                   
                                                    <!-- Traveller types and baggage policies -->
                                                    <div class="ml-4 space-y-2">
                                                        <template v-for="travelerType in ['adult', 'child', 'infant','ADLT','CHLD','INFT' , 'ADT','CHD','INF']">
                                                            <div v-if="fare?.baggage_policies.some(
                                                                (p) =>  p.traveler_type === travelerType
                                                            )" :key="travelerType" class="space-y-1">
                                                                <div class="ml-3 space-y-2">
                                                                    <div v-for="(policy, policyIndex) in fare?.baggage_policies.filter(
                                                                        (p) =>  p.traveler_type === travelerType
                                                                    )" :key="policyIndex" class="flex items-start gap-2 rounded transition-colors">
                                                                        <span
                                                                            class="inline-flex items-center justify-center w-2 h-2 bg-primary rounded-full border-2 border-primary">
                                                                            <component
                                                                                :is="policy.type === 'carry' ? 'BriefcaseBusiness' : 'Briefcase'"
                                                                                class="w-4 h-4 text-primary mt-0.5 flex-shrink-0" />
                                                                        </span>
                                                                        <span
                                                                            class="text-xs text-gray-700 leading-tight">
                                                                            {{ policy.description || 'N/A' }} ({{
                                                                            travelerType }})
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>


                                        </div>
                                        <Dialog >
                                            <DialogTrigger asChild>
                                                <div
                                                    class="mt-4 text-sm float-right text-primary underline cursor-pointer">
                                                    View Details
                                                </div>
                                            </DialogTrigger>
                                            <DialogContent class="bg-white max-w-4xl">
                                                <DialogHeader>
                                                    <DialogTitle>{{ fare.name }} ({{ fare.class }}) Details
                                                    </DialogTitle>
                                                </DialogHeader>
                                                <Tabs default-value="policies" class="w-full">
                                                    <TabsList
                                                        class="grid item-center w-full bg-gray-100 p-1 rounded-lg grid-cols-2">
                                                        <TabsTrigger
                                                            class="text-sm font-medium px-4 py-2 rounded-md data-[state=active]:bg-white data-[state=active]:shadow-sm"
                                                            value="policies">Fare Policies</TabsTrigger>
                                                        <TabsTrigger
                                                            class="text-sm font-medium px-4 py-2 rounded-md data-[state=active]:bg-white data-[state=active]:shadow-sm"
                                                            value="price">Price Breakdown</TabsTrigger>
                                                    </TabsList>
                                                    <TabsContent value="policies">
                                                        <!-- Convert fare policies to table format -->
                                                        <div v-if="fare?.fare_policies?.length > 0"
                                                            class="overflow-x-auto">
                                                            <table
                                                                class="w-full border-collapse border border-gray-200 rounded-lg">
                                                                <thead>
                                                                    <tr class="bg-gray-50">
                                                                        <th
                                                                            class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                            Policy Title</th>
                                                                        <th
                                                                            class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                            Description</th>
                                                                        <th
                                                                            class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                            Type</th>
                                                                        <th
                                                                            class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                            Price</th>
                                                                        <th
                                                                            class="border border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                            Traveler Type</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <tr v-for="(policy, policyIndex) in fare.fare_policies"
                                                                        :key="policyIndex" class="hover:bg-gray-50">
                                                                        <td
                                                                            class="border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900">
                                                                            {{ policy.title }}
                                                                        </td>
                                                                        <td
                                                                            class="border border-gray-200 px-4 py-3 text-sm text-gray-600">
                                                                            {{ policy.description || 'Not available' }}
                                                                        </td>
                                                                        <td
                                                                            class="border border-gray-200 px-4 py-3 text-sm text-gray-600 capitalize">
                                                                            {{ policy.type }}
                                                                        </td>
                                                                        <td
                                                                            class="border border-gray-200 px-4 py-3 text-sm font-medium text-gray-900">
                                                                            {{ policy.price }}&nbsp;{{ policy.price_type
                                                                                === 'percentage' ? '%' : 'AED' }}

                                                                        </td>
                                                                        <td
                                                                            class="border border-gray-200 px-4 py-3 text-sm text-gray-600 capitalize">
                                                                            {{ policy.traveler_type }}
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <DialogDescription v-else
                                                            class="text-sm text-gray-500 p-4 text-center bg-gray-50 rounded-lg">
                                                            No fare policies available for this fare.
                                                        </DialogDescription>
                                                    </TabsContent>
                                                    <TabsContent value="price">
                                                        <!-- Convert passenger fares to table format -->
                                                        <div class="space-y-4">
                                                            <div
                                                                class="border border-gray-200 rounded-lg overflow-hidden">

                                                                <div class="overflow-x-auto">
                                                                    <table class="w-full border-collapse">
                                                                        <thead>
                                                                            <tr class="bg-gray-50">
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Passenger Type</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Base Price</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Taxes</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Fees</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Service Charges</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Surcharge</th>
                                                                                <th
                                                                                    class="border-b border-gray-200 px-4 py-3 text-left text-sm font-semibold text-gray-900">
                                                                                    Total</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <tr v-for="(passengerFare, index) in fare.passenger_fares"
                                                                                :key="index" class="hover:bg-gray-50">
                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{
                                                                                    passengerFare?.traveler_type?.toUpperCase()
                                                                                    }}
                                                                                </td>
                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{ fare?.currency?.symbol }}{{
                                                                                        (
                                                                                            calculateFinalPrice(
                                                                                                parseFloat(passengerFare.base_price)
                                                                                                || 0,
                                                                                                fare.margin_amount,
                                                                                                fare.margin_type,
                                                                                                fare.amount_type
                                                                                            )
                                                                                        ) +
                                                                                        (
                                                                                            (parseFloat(agentData?.agent_data?.margin_amount
                                                                                                || 0) *
                                                                                                (passengerFare.total_passenger ||
                                                                                                    0)) +
                                                                                            (agentMargin || 0)
                                                                                        ) -
                                                                                        (
                                                                                            parseFloat(agentData?.agent_data?.agent_discount
                                                                                    || 0) *
                                                                                    (passengerFare.total_passenger || 0)
                                                                                    )
                                                                                    }}
                                                                                </td>
                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{ fare?.currency?.symbol }}&nbsp;{{
                                                                                        passengerFare.taxes }}
                                                                                </td>
                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{ fare?.currency?.symbol }}&nbsp;{{
                                                                                        passengerFare.fees }}
                                                                                </td>
                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{ fare?.currency?.symbol }}&nbsp;{{
                                                                                        passengerFare.service_charges||0 }}
                                                                                </td>

                                                                                <td
                                                                                    class="border-b border-gray-100 px-4 py-3 text-sm font-medium text-left">
                                                                                    {{ fare?.currency?.symbol }}&nbsp;{{
                                                                                        passengerFare.surchage  || 0 }}
                                                                                </td>
                                                                                <td
                                                                                    class=" border-b px-4 py-4 text-left">
                                                                                    <span
                                                                                        class="text-lg font-bold text-primary">
                                                                                        {{ fare?.currency?.symbol }}{{
                                                                                            parseFloat(passengerFare.surchage
                                                                                                || 0) +
                                                                                            parseFloat(passengerFare.taxes
                                                                                                || 0) +
                                                                                            parseFloat(passengerFare.fees ||
                                                                                                0) +
                                                                                            parseFloat(passengerFare.service_charges
                                                                                                || 0) +
                                                                                            parseFloat(passengerFare.ancillaries_charges
                                                                                                || 0) +
                                                                                            (calculateFinalPrice(
                                                                                                parseFloat(passengerFare.base_price)
                                                                                                || 0,
                                                                                                fare.margin_amount,
                                                                                                fare.margin_type,
                                                                                                fare.amount_type
                                                                                            )) +
                                                                                            (parseFloat(agentData?.agent_data?.margin_amount
                                                                                        || 0) *
                                                                                        passengerFare.total_passenger) +
                                                                                        (agentMargin || 0)
                                                                                        -
                                                                                        (parseFloat(agentData?.agent_data?.agent_discount
                                                                                        || 0) *
                                                                                        passengerFare.total_passenger)
                                                                                        }}
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                            <div v-if="fare.passenger_fares.length === 0"
                                                                class="text-sm text-gray-500 p-4 text-center bg-gray-50 rounded-lg">
                                                                No passenger fare data available.
                                                            </div>
                                                        </div>
                                                    </TabsContent>
                                                </Tabs>

                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                </div>
                            </div>
                        </TabsContent>
                    </Tabs>
                    <!-- Updated booking button with better positioning -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-100 p-6 mt-8">
                        <div class="flex justify-between items-center">
                            <div class="flex gap-2">
                                <div class="text-xl font-bold">Total Price: </div>

                                <div class="text-xl font-bold text-primary">{{ formatAmount(calculateGrandTotal()) }}
                                </div>
                            </div>
                            <button
                                :disabled="(route.query.flightType === 'return' ? !(selectedFares[0] && selectedFares[1]) : !selectedFares[0])"
                                :class="{ 'bg-gray-300 cursor-not-allowed': !selectedFares[0] || !selectedFlight?.leg?.ref_id }"
                                @click="goToCheckout"
                                class="bg-primary text-white py-3 px-8 rounded text-base font-semibold hover:bg-primary/90 disabled:hover:bg-gray-300 transform hover:scale-105 transition-all duration-200 shadow-lg hover:shadow-xl">
                                <span>{{ $t("book_now") }}</span>
                            </button>
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
    font-family: "Arial", sans-serif;
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
