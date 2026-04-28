<script setup>
import Header from "../components/shared/Header.vue";

import Autoplay from "embla-carousel-autoplay";
import Button from "@/components/ui/button/Button.vue";
import Nav from "../components/shared/Nav.vue";
import TopBar from "@/components/shared/TopBar.vue";
import { ArrowRight, Map } from "lucide-vue-next";
import {
  Carousel,
  CarouselContent,
  CarouselItem,
} from "@/components/ui/carousel";
import { useStore } from "vuex";
import { debounce } from "lodash";

import { computed, nextTick, onUnmounted, ref, watch } from "vue";
import Card from "@/components/ui/card/Card.vue";
import {
  ClockIcon,
  GlobeIcon,
  HeadphonesIcon,
  HeartIcon,
  MapPinIcon,
  RefreshCcwIcon,
  SearchIcon,
  ShieldIcon,
  Star,
  UserIcon,
  Search,
  Calendar,
  MapPin,
  Users,
  Plane,
  Building2,
  CheckCircle,
  Globe,
  Shield,
  Clock,
  Award,
  Menu,
  X,
  ChevronDown,
  User,
  Heart,
  ChevronLeft,
  ChevronRight,
  Zap,
} from "lucide-vue-next";
import { onMounted } from "vue";
import { useRoute } from "vue-router";
import { toast } from "vue3-toastify";
import { useRouter } from "vue-router";

const store = useStore();
const route = useRoute();
const router = useRouter();
import AnnouncementBar from "@/components/common/AnnouncementBar.vue";
import FlightFilterCard from "@/components/common/FlightFilterCard.vue";
import Spinner from "@/components/common/Spinner.vue";

import PromoSlider from "@/components/shared/PromoSlider.vue";

import { calculateLayover, formatAmount } from "@/lib/utils";
import GroupTicketsMain from "@/pages/GroupTickets.vue";
import HolidayPackages from "@/pages/Holidays.vue";
import HotelSearch from "@/pages/HotelSearch.vue";
import TravelInsurance from "@/pages/TravelInsurance.vue";
import UmraPackages from "@/pages/UmraPackages.vue";
import Visa from "@/pages/Visas.vue";
import { FETCH_AGENT_DATA, FETCH_AIRPORTS, FETCH_POPULAR_ROUTES, FETCH_PROMO_IMAGES } from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";
import { FileCheck2, Hotel, School, ShieldCheck, Sun, Users2 } from "lucide-vue-next";
import moment from "moment";
import Input from "@/components/ui/input/Input.vue";
import OffersSection from "./Home/OffersSection.vue";
import InfoCards from "./Home/InfoCards.vue";
import Collections from "./Home/Collections.vue";
import TrendingRoutes from "./Home/TrendingRoutes.vue";

const activeTab = ref("flights");
const tabs = [
  { id: "flights", name: "Flights", icon: Plane },

  { id: "hotels", name: "Hotels", icon: Hotel },
  { id: "visas", name: "Visas", icon: FileCheck2 },
  { id: "holidays", name: "Holidays", icon: Sun },
  { id: "umrah-packages", name: "Umrah Packages", icon: School },
  { id: "travel-insurance", name: "Travel Insurance", icon: ShieldCheck },
  { id: "group-tickets", name: "Group Tickets", icon: Users2 },
];

const setActiveTab = (tabId) => {
  activeTab.value = tabId;
};

const flightStore = useFlightStore();
const authStore = useAuthStore();

const carouselRef = ref(null)
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
// Store carousel instance
const carouselApi = ref(null);
const routesData = computed(() => store.getters['cms/popularRoutes']);
const popularRoutes = computed(() => routesData.value.data || []);
const fetchPopularRoutes = debounce(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

onMounted(() => {
  fetchPopularRoutes();
  // Shadcn provides .$el to access API
  if (carouselRef.value) {
    carouselApi.value = carouselRef.value.api;
  }
});


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
// Function to get airport name by IATA code
const getAirportName = (iataCode) => {
    if (!iataCode || !airports.value) return '';
    
    const airport = airports.value.find(a => a.iata_code === iataCode);
    return airport ? airport.name : '';
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
    name: "FlightSearch",
    query: searchParams,
  });


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

const date = new Date();
const formattedDate = new Intl.DateTimeFormat('en-CA', { 
    year: 'numeric', 
    month: '2-digit', 
    day: '2-digit' 
}).format(date);
// Displaying a message after email verification
onMounted(() => {
  if (route.query.verified) {
    toast("Your email has been verified successfully.", {
      type: "success",
    });
    const query = { ...route.query };
    delete query.verified;
    router.replace({ query });
  }
});
const featuredHotels = [
  {
    id: 1,
    name: "Movenpick Grand Al Bustan",
    location: "Dubai, United Arab Emirates",
    image: "https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&h=250&fit=crop",
    rating: 4,
    price: 100.0,
    currency: "USD",
  },
  {
    id: 2,
    name: "Four Points by Sheraton Bar Dubai",
    location: "Dubai, United Arab Emirates",
    image: "https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=400&h=250&fit=crop",
    rating: 4,
    price: 260.0,
    currency: "USD",
  },
  {
    id: 3,
    name: "Voco Dubai an IHG Hotel",
    location: "Dubai, United Arab Emirates",
    image: "https://images.unsplash.com/photo-1578774204375-826dc5d996ed?w=400&h=250&fit=crop",
    rating: 5,
    price: 200.0,
    currency: "USD",
  },
  {
    id: 4,
    name: "Luxury Resort & Spa",
    location: "Dubai, United Arab Emirates",
    image: "https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=400&h=250&fit=crop",
    rating: 5,
    price: 350.0,
    currency: "USD",
  },
];
const destinations = [
  {
    city: 'Dubai',
    country: 'Dubai, United Arab Emirates',
    price: 64734,
    image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600&h=500&fit=crop'
  },
  {
    city: 'London',
    country: 'London, United Kingdom',
    price: 101084,
    image: 'https://www.visitlondon.com/-/media/images/london/visit/things-to-do/nightlife/tower-bridge-at-night1920x1080.png?mw=640&rev=743f319d95bf47638fe287a5322c115c&hash=023E58432D989F191B9A9E52DC097D39'
  },
  {
    city: 'Beijing',
    country: 'Beijing, China',
    price: 172888,
    image: 'https://media.istockphoto.com/id/482334184/photo/night-on-beijing-central-business-district-buildings-skyline-china-cityscape.jpg?s=612x612&w=0&k=20&c=gd1nunX5dLfHTAyyqTE2frn4Iw-dzyr60YqJGaK2M4U='
  },
  {
    city: 'New York City',
    country: 'New York, United States Of America',
    price: 205417,
    image: 'https://www.airport-ostrava.cz/img/nahrane-obrazky/new-york_f57688469ca42ed2b970f3915b9c7d56.jpg'
  },
  {
    city: 'Istanbul',
    country: 'Istanbul, Turkey',
    price: 78990,
    image: 'https://www.hotelgift.com/media/wp/HG/2022/08/blue-mosque-Turkey-where-to-stay-in-istanbul.webp'
  },
  {
    city: 'Islamabad',
    country: 'Islamabad, Pakistan',
    price: 18500,
    image: 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTjltsBw_k-TGxaNSrjoMItTxc-cnLf7SYmHA&s'
  },
  {
    city: 'Karachi',
    country: 'Karachi, Pakistan',
    price: 15900,
    image: 'https://edge.amadeusonlinesuite.com/prod/OrganizationData/2402050612236425504/CMS/PopularRoutes/2403190509564034265/destination_image_2.jpeg?t=202409Th224306fffffff?height=200'
  },
  {
    city: 'Bangkok',
    country: 'Bangkok, Thailand',
    price: 52300,
    image: 'https://edge.amadeusonlinesuite.com/prod/OrganizationData/2402050612236425504/CMS/PopularRoutes/2403190509564034265/destination_image_11.jpeg?t=202412We190037fffffff?height=200'
  },
  // Add more destinations easily here!
];

const hotDeals = ref([
  {
    title: "Dubai Luxury Escape",
    route: "Karachi → Dubai",
    tag: "Top Seller",
    originalPrice: 95000,
    discountedPrice: 74999,
    image: "https://images.unsplash.com/photo-1518684079-3c830dcef090?w=800&h=600&fit=crop"
  },
  {
    title: "London Calling",
    route: "Lahore → London",
    tag: "Flash Sale",
    originalPrice: 185000,
    discountedPrice: 149999,
    image: "https://www.visitlondon.com/-/media/images/london/visit/things-to-do/nightlife/tower-bridge-at-night1920x1080.png?mw=640&rev=743f319d95bf47638fe287a5322c115c&hash=023E58432D989F191B9A9E52DC097D39"
  },
  {
    title: "Thailand Paradise",
    route: "Islamabad → Bangkok",
    tag: "50% Off",
    originalPrice: 120000,
    discountedPrice: 59999,
    image: "https://internationalliving.com/_next/image/?url=https%3A%2F%2Fimages.ctfassets.net%2Fwv75stsetqy3%2FDaKdXY2tkQGWeVQiCbSx7%2Fac01166282697e4e0cafb99180d35cd1%2FThailand_Featured.jpg%3Fq%3D60%26fit%3Dfill%26fm%3Dwebp&w=3840&q=60"
  },
  {
    title: "Istanbul Adventure",
    route: "Karachi → Istanbul",
    tag: "Hot Deal",
    originalPrice: 98000,
    discountedPrice: 77999,
    image: "https://www.hotelgift.com/media/wp/HG/2022/08/blue-mosque-Turkey-where-to-stay-in-istanbul.webp"
  },
  {
    title: "Malaysia Truly Asia",
    route: "Lahore → Kuala Lumpur",
    tag: "Early Bird",
    originalPrice: 115000,
    discountedPrice: 89999,
    image: "https://i.natgeofe.com/k/8dc7401d-fac9-43c5-a6d4-d056401f7779/kuala-lumpur.jpg?wp=1&w=1084.125&h=721.875"
  },
  {
    title: "Baku Weekend Getaway",
    route: "Karachi → Baku",
    tag: "Last Minute",
    originalPrice: 85000,
    discountedPrice: 67999,
    image: "https://livepositively.com/images/gallery/article/586691_pexelsmiguelcuenca6788247320014467.webp"
  },
]);
// Featured Flights Data
const featuredFlights = [
  {
    id: 1,
    airline: "Pakistan International Airlines",
    route: "Multan → Jeddah",
    price: 345.0,
    currency: "USD",
    logo: "🇵🇰",
    color: "bg-green-500",
  },
  {
    id: 2,
    airline: "Pakistan International Airlines",
    route: "Lahore → Dubai",
    price: 195.0,
    currency: "USD",
    logo: "🇵🇰",
    color: "bg-green-500",
  },
  {
    id: 3,
    airline: "Air Philippines",
    route: "Manila → Dubai",
    price: 445.0,
    currency: "USD",
    logo: "🇵🇭",
    color: "bg-red-500",
  },
  {
    id: 4,
    airline: "Malaysia Airlines",
    route: "Kuala Lumpur → Dubai",
    price: 620.0,
    currency: "USD",
    logo: "🇲🇾",
    color: "bg-blue-500",
  },
  {
    id: 5,
    airline: "Emirates",
    route: "Dubai → Sharjah",
    price: 680.0,
    currency: "USD",
    logo: "🇦🇪",
    color: "bg-primary",
  },
  {
    id: 6,
    airline: "Turkish Airlines",
    route: "Berlin → Istanbul",
    price: 560.0,
    currency: "USD",
    logo: "🇹🇷",
    color: "bg-red-700",
  },
];

// Popular Tours Data
const popularTours = [
  {
    id: 1,
    title: "Dubai",
    subtitle: "Combo Packages",
    image: "https://unsplash.com/photos/Fr6zexbmjmc/download?ixid=M3wxMjA3fDB8MXxhbGx8fHx8fHx8fHwxNzUwMTcxMDkwfA&force=true&w=640",
    rating: 4,
    size: "large",
  },
  {
    id: 2,
    title: "Stunning Dubai",
    image: "https://images.unsplash.com/photo-1580674684081-7617fbf3d745?w=400&h=300&fit=crop",
    rating: 5,
    size: "medium",
  },
  {
    id: 3,
    title: "Sydney and Bondi Beach",
    subtitle: "Australia",
    image: "https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop",
    rating: 4,
    size: "large",
  },
  {
    id: 4,
    title: "6 Days Around Thailand",
    image: "https://images.unsplash.com/photo-1552465011-b4e21bf6e79a?w=400&h=300&fit=crop",
    rating: 5,
    size: "large",
  },
  {
    id: 5,
    title: "Spectacular Of The Nile",
    subtitle: "5 Nights",
    image: "https://images.unsplash.com/photo-1539650116574-75c0c6d73f6e?w=400&h=300&fit=crop",
    rating: 4,
    size: "medium",
  },
];
const reviews = ref([
  {
    name: "Qurat ul Ain",
    text: "Booking with Jetze was incredibly easy and hassle-free. Their user-friendly website made finding and confirming my travel options quick and seamless, providing peace of mind throughout the process."
  },
  {
    name: "Nauman Shahzad",
    text: "Jetze provides unparalleled service with a user-friendly platform, exceptional customer support, and unwavering reliability. It’s my top choice for hassle-free travel arrangements."
  },
  {
    name: "Abdul Rehman",
    text: "As a frequent traveler, I confidently declare Jetze as the best tour operator. Their seamless booking process, responsive customer service, and unwavering reliability make them my go-to choice for all my travel needs."
  },
  {
    name: "Ayesha Khan",
    text: "Excellent service! Got my tickets instantly and the support team was available 24/7. Highly recommended for anyone traveling from Pakistan."
  },
  {
    name: "Mohammad Ali",
    text: "Best prices and super fast booking. I always book my international flights through Jetze now. Trusted and reliable!"
  }
])

const testimonialIndex = ref(0)
const testimonialWidth = ref(100) // will be updated based on screen size
let testimonialInterval = null

const startTestimonialScroll = () => {
  testimonialInterval = setInterval(() => {
    nextTestimonial()
  }, autoScrollDelay)
}

const pauseTestimonialScroll = () => {
  if (testimonialInterval) {
    clearInterval(testimonialInterval)
    testimonialInterval = null
  }
}

const nextTestimonial = async () => {
  testimonialIndex.value++

  if (testimonialIndex.value >= reviews.value.length) {
    testimonialIndex.value = 0
    const track = document.getElementById('testimonial-track')
    track.style.transition = 'none'
    await nextTick()
    track.style.transition = 'transform 500ms ease-in-out'
  }
}

const prevTestimonial = () => {
  if (testimonialIndex.value <= 0) {
    testimonialIndex.value = reviews.value.length
    const track = document.getElementById('testimonial-track')
    track.style.transition = 'none'
    nextTick(() => {
      testimonialIndex.value--
      nextTick(() => {
        track.style.transition = 'transform 500ms ease-in-out'
      })
    })
  } else {
    testimonialIndex.value--
  }
}

const updateTestimonialWidth = () => {
  const width = window.innerWidth
  if (width >= 1024) {
    testimonialWidth.value = 33.333 // 3 cards
  } else if (width >= 768) {
    testimonialWidth.value = 50     // 2 cards
  } else {
    testimonialWidth.value = 100    // 1 card
  }
}

onMounted(() => {
  updateTestimonialWidth()
  window.addEventListener('resize', updateTestimonialWidth)
  startTestimonialScroll()
})

onUnmounted(() => {
  pauseTestimonialScroll()
  window.removeEventListener('resize', updateTestimonialWidth)
})

const currentHotelIndex = ref(0);

const nextHotel = () => {
  if (currentHotelIndex.value < featuredHotels.length - 3) {
    currentHotelIndex.value++;
  }
};

const prevHotel = () => {
  if (currentHotelIndex.value > 0) {
    currentHotelIndex.value--;
  }
};

const renderStars = (rating) => {
  return Array.from({ length: 5 }, (_, i) => i < rating);
};

// Features

// Reactive data
const screenWidth = ref(0);

// Sample data

// Computed properties
const isMobile = computed(() => screenWidth.value < 640);
const isTablet = computed(
  () => screenWidth.value >= 640 && screenWidth.value < 1024,
);
const isDesktop = computed(() => screenWidth.value >= 1024);

// Methods
const getVisibleHotels = () => {
  if (isMobile.value) return 1;
  if (isTablet.value) return 2;
  return 3;
};

const updateScreenWidth = () => {
  screenWidth.value = window.innerWidth;
};

// Lifecycle hooks
const currentIndex = ref(0)
const itemWidth = ref(100) // percentage width per item (will adjust dynamically)
let autoScrollInterval = null
const autoScrollDelay = 4000 // 4 seconds

// Start auto-scroll
const startAutoScroll = () => {
  autoScrollInterval = setInterval(() => {
    nextSlide()
  }, autoScrollDelay)
}

// Pause auto-scroll
const pauseAutoScroll = () => {
  if (autoScrollInterval) {
    clearInterval(autoScrollInterval)
    autoScrollInterval = null
  }
}

// Go to next slide
const nextSlide = async () => {
  currentIndex.value++

  // Seamless loop: when reaching cloned items, jump back instantly
  if (currentIndex.value >= hotDeals.value.length) {
    currentIndex.value = 0
    // Disable transition for instant jump
    const track = document.getElementById('carousel-track')
    track.style.transition = 'none'
    await nextTick()
    track.style.transition = 'transform 500ms ease-in-out'
  }
}

// Go to previous slide
const prevSlide = () => {
  if (currentIndex.value <= 0) {
    // Jump to the cloned part at the end
    currentIndex.value = hotDeals.value.length
    const track = document.getElementById('carousel-track')
    track.style.transition = 'none'
    nextTick(() => {
      currentIndex.value--
      nextTick(() => {
        track.style.transition = 'transform 500ms ease-in-out'
      })
    })
  } else {
    currentIndex.value--
  }
}

// Calculate item width based on screen size
const updateItemWidth = () => {
  const width = window.innerWidth
  if (width >= 1024) {
    itemWidth.value = 33.333 // lg:basis-1/3
  } else if (width >= 768) {
    itemWidth.value = 50     // md:basis-1/2
  } else {
    itemWidth.value = 100    // mobile: full width
  }
}

// Lifecycle
onMounted(() => {
  updateItemWidth()
  window.addEventListener('resize', updateItemWidth)
  startAutoScroll()
})

onUnmounted(() => {
  pauseAutoScroll()
  window.removeEventListener('resize', updateItemWidth)
})
</script>

<template>
  <div class="flex flex-col  bg-gray-100">

    <div
      class="relative w-full min-h-[72vh] h-auto py-12 bg-[url('/public/864.webp')] bg-cover bg-center bg-no-repeat flex items-center justify-center overflow-visible z-30">
      <!-- Enhanced Overlay with Gradient for better readability and modern feel -->
      <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/50 to-black/40"></div>

      <!-- Content Container with improved responsiveness and animations -->
      <div
  class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8
         text-center animate-fade-in-up
         overflow-hidden">
    
    <!-- <div class="text-white mb-8 sm:mb-10 lg:mb-12">
      <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight drop-shadow-lg">
        Discover Your Ideal Flight Adventure
      </h1>
      <p class="mt-4 text-lg sm:text-xl lg:text-2xl text-gray-100 font-light max-w-3xl mx-auto">
        Effortlessly search, compare, and book from thousands of global airlines for your next journey.
      </p>
    </div> -->

    <!-- Filter Card with enhanced styling: softer shadows, subtle border, and hover effects -->
    
    <!-- <FlightFilterCard :countdown="countdown" v-model="modelValue" @search="setupFlightsParams" /> -->
    <Header />

</div>

    </div>
    <OffersSection/>
    <InfoCards/>
    <Collections/>
    <TrendingRoutes/>
    <section class="py-16 bg-white">
      <div class="max-w-7xl mx-auto text-center">

        <!-- Heading -->
        <h2 class="text-2xl md:text-3xl font-extrabold text-[#1e2d61]">
          Are You Ready To Enjoy Epic Journeys With Us?
        </h2>
        <h3 class="text-xl md:text-2xl font-extrabold text-[#1e2d61] mt-1">
          Let’s Together Explore The Beauty Of World!
        </h3>

        <!-- Subtext -->
        <p class="text-gray-700 mt-2">Join Our Newsletter</p>

        <!-- Input + Button -->
        <div class="mt-6 flex justify-center">
          <div class="flex w-full max-w-2xl">
            <input type="email" placeholder="Enter your email"
              class="w-full px-4 bg-[#fae9ff] placeholder-gray-400 border-0 focus:ring-0" />
            <button class="px-8 py-4 bg-primary text-white font-semibold hover:bg-red-700 transition">
              Join Our Newsletter
            </button>
          </div>
        </div>

      </div>
    </section>

  </div>
</template>


<style scoped>
/* Custom CSS variables for consistent theming */


/* Smooth transitions for all interactive elements */
* {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Container utility for consistent max-width and centering */
.container {
  max-width: 1280px;
}

.carousel-item {
  scroll-snap-align: start;
}

/* Line clamp utilities for text truncation */
.line-clamp-1 {
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Enhanced hover effects */
.group:hover .group-hover\:scale-105 {
  transform: scale(1.05);
}

.group:hover .group-hover\:scale-110 {
  transform: scale(1.1);
}

/* Custom scrollbar styling */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: var(--primary-color);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: var(--primary-dark);
}

/* Focus styles for accessibility */
button:focus,
input:focus {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .border-gray-100 {
    border-color: #6b7280;
  }

  .text-gray-600 {
    color: #374151;
  }

  .shadow-sm {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }

  .group-hover\:scale-105,
  .group-hover\:scale-110 {
    transform: none !important;
  }
}

/* Mobile-first responsive adjustments */
@media (max-width: 480px) {
  .container {
    padding-left: 1rem;
    padding-right: 1rem;
  }

  /* Ensure cards don't get too small on very small screens */
  .grid>div {
    min-width: 280px;
  }
}

/* Tablet landscape optimizations */
@media (min-width: 768px) and (max-width: 1023px) and (orientation: landscape) {
  .h-56 {
    height: 12rem;
  }

  .lg\:h-72 {
    height: 16rem;
  }
}

/* Large screen optimizations */
@media (min-width: 1280px) {
  .container {
    max-width: 1400px;
  }
}

/* Print styles */
@media print {

  .bg-gradient-to-br,
  .bg-gradient-to-t {
    background: #f3f4f6 !important;
    color: #111827 !important;
  }

  button {
    display: none;
  }
}
</style>
