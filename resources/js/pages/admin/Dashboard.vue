<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";
import { useUserStore } from "@/services/stores/user";
import { formatAmount } from "@/lib/utils";
import {
  FETCH_USER_SUMMARY,
  FETCH_BOOKING_DATA,
  FETCH_TOTAL_APPROVED_DEPOSIT,
  FETCH_DEPOSITS_DATA_AGENTS
} from "@/services/store/actions.type";

// UI Components
import FlightBookingChart from "@/components/common/FlightBookingChart.vue";
import depositChart from "@/components/common/depositChart.vue";
import TravelTodayWidget from "@/components/common/TravelTodayWidget.vue";

// Icons
import {
  Calendar as CalendarIcon,
  CheckCircle as CheckCircleIcon,
  Ban,
  CirclePause,
  CircleDollarSign,
  CircleChevronRight,
  Plane,
  ArrowRight,
  Eye,
  Hourglass,
  X,
  Search,
  RefreshCw,
  Copy,
  Check,
  TrendingUp,
  Users,
  FileText,
  Layers,
  Sparkles,
  Ticket,
  Plus,
  ShieldCheck,
  Activity
} from "lucide-vue-next";

const router = useRouter();
const store = useStore();
const userStore = useUserStore();
const authStore = useAuthStore();

// Reactive State
const emit = defineEmits(["filter-change"]);
const activeFilter = ref("all");
const bookingSearch = ref("");
const depositSearch = ref("");
const depositFilter = ref("all");
const copiedPnr = ref("");
const isRefreshing = ref(false);
const isDashboardLoading = ref(true);

// Store Getters & Computed State
const user = computed(() => authStore.user || {});
const users = computed(() => userStore.users);
const bookings = computed(() => store.getters["flight/bookingData"]);
const usersSummary = computed(() => store.getters["user/usersSummary"]);
const agentDepositTotals = computed(() => store.getters["deposit/totalApprovedDeposit"]);
const agentsDepositData = computed(() => store.getters["deposit/depositDataWithAgents"]);

const adminName = computed(() => {
  return user.value?.name || user.value?.username || "Administrator";
});

const todayDateFormatted = computed(() => {
  return moment().format("dddd, DD MMMM YYYY");
});

// Format money with fallback
function formatMoney(money, fallbackAmount = 0, fallbackCurrency = "AED") {
  const amount = money && typeof money === "object" ? money.amount : money;
  const currency = money && typeof money === "object" ? money.currency : fallbackCurrency;
  return formatAmount(amount ?? fallbackAmount, currency ?? fallbackCurrency);
}

// JSON flight data parsing
const parseFlightData = (flightDataString) => {
  if (!flightDataString) return null;
  if (typeof flightDataString === "object") return flightDataString;
  try {
    return JSON.parse(flightDataString);
  } catch (error) {
    return null;
  }
};

// Route & Flight Info Extractor
function getFlightRouteInfo(booking) {
  const flight = parseFlightData(booking?.flight_data);
  let origin = null;
  let destination = null;
  let originCity = null;
  let destCity = null;
  let carrierName = booking?.flight_provider || null;

  // 1. Source: leg.flights or flights
  const flights = flight?.leg?.flights || flight?.original?.leg?.flights || flight?.flights;
  if (Array.isArray(flights) && flights.length > 0) {
    const firstFlight = flights[0];
    const lastFlight = flights[flights.length - 1];

    origin = firstFlight?.from?.iata || firstFlight?.from?.code || (typeof firstFlight?.from === "string" ? firstFlight.from : null);
    originCity = firstFlight?.from?.city || firstFlight?.from?.city_name || null;

    destination = lastFlight?.to?.iata || lastFlight?.to?.code || (typeof lastFlight?.to === "string" ? lastFlight.to : null);
    destCity = lastFlight?.to?.city || lastFlight?.to?.city_name || null;

    carrierName = firstFlight?.carrier?.name || firstFlight?.airline?.name || carrierName;
  }

  // 2. Source: legs with stops
  if ((!origin || !destination) && Array.isArray(flight?.legs) && flight.legs.length > 0) {
    const firstLeg = flight.legs[0];
    const lastLeg = flight.legs[flight.legs.length - 1];

    const firstStop = firstLeg?.stops?.[0];
    origin = firstStop?.departure?.airport?.iata_code || firstLeg?.departure_airport || null;
    originCity = firstStop?.departure?.airport?.city_name || null;

    const lastStops = lastLeg?.stops;
    const lastStop = lastStops && lastStops.length > 0 ? lastStops[lastStops.length - 1] : null;
    destination = lastStop?.arrival?.airport?.iata_code || lastLeg?.arrival_airport || null;
    destCity = lastStop?.arrival?.airport?.city_name || null;

    carrierName = firstStop?.airline?.name || firstLeg?.airline_name || carrierName;
  }

  if (!origin) origin = booking?.origin || "—";
  if (!destination) destination = booking?.destination || "—";

  return {
    origin,
    destination,
    originCity,
    destCity,
    carrierName: carrierName || "Flight"
  };
}

// Computed Bookings Data for KPI & Chart
const bookingsData = computed(() => {
  return {
    total_count: bookings?.value?.total_count || 0,
    total_ticketed: bookings?.value?.total_ticketed || 0,
    total_canceled: bookings?.value?.total_canceled || 0,
    total_booked: bookings?.value?.total_booked || 0,
  };
});

// Filtered Bookings for Table
const filteredBookings = computed(() => {
  let list = bookings.value?.bookings || [];

  if (activeFilter.value && activeFilter.value !== "all") {
    list = list.filter((b) => (b.status || "").toLowerCase() === activeFilter.value.toLowerCase());
  }

  if (bookingSearch.value.trim()) {
    const q = bookingSearch.value.trim().toLowerCase();
    list = list.filter((b) => {
      const pnr = (b.itinerary_reference || b.itinerary_ref || b.pnr || "").toLowerCase();
      const email = (b.agency_email || "").toLowerCase();
      const routeInfo = getFlightRouteInfo(b);
      const route = `${routeInfo.origin} ${routeInfo.destination} ${routeInfo.originCity || ""} ${routeInfo.destCity || ""}`.toLowerCase();
      return pnr.includes(q) || email.includes(q) || route.includes(q);
    });
  }

  return list.slice(0, 15);
});

// Filtered Deposits for Table
const filteredDeposits = computed(() => {
  let list = agentsDepositData.value?.deposits || [];

  if (depositFilter.value && depositFilter.value !== "all") {
    list = list.filter((d) => (d.deposit_status || "").toLowerCase() === depositFilter.value.toLowerCase());
  }

  if (depositSearch.value.trim()) {
    const q = depositSearch.value.trim().toLowerCase();
    list = list.filter((d) => {
      const name = (d?.agent?.agent_data?.company_name || d?.agent?.name || d?.agent?.email || "").toLowerCase();
      const currency = (d?.currency || "").toLowerCase();
      return name.includes(q) || currency.includes(q);
    });
  }

  return list.slice(0, 15);
});

// Copy PNR to clipboard
function copyPnrToClipboard(pnr) {
  if (!pnr) return;
  if (navigator?.clipboard?.writeText) {
    navigator.clipboard.writeText(pnr);
    copiedPnr.value = pnr;
    setTimeout(() => {
      if (copiedPnr.value === pnr) copiedPnr.value = "";
    }, 2000);
  }
}

// Filtering actions
function filterBookings(filter) {
  activeFilter.value = filter;
  emit("filter-change", filter);
  fetchBookings();
}

function filterDeposits(filter) {
  depositFilter.value = filter;
}

// Data fetching
function fetchUsers() {
  return userStore.fetchUsers({
    role: "agent",
  });
}

function fetchBookings() {
  return store.dispatch("flight/" + FETCH_BOOKING_DATA, {
    user_role: user.value?.role,
    bookingFilter: activeFilter.value,
  });
}

function fetchTotalApprovedDepost() {
  return store.dispatch("deposit/" + FETCH_TOTAL_APPROVED_DEPOSIT);
}

function fetchUserSummary() {
  return store.dispatch("user/" + FETCH_USER_SUMMARY);
}

function fetchAgentsDeposits() {
  return store.dispatch("deposit/" + FETCH_DEPOSITS_DATA_AGENTS);
}

async function loadDashboard() {
  isDashboardLoading.value = true;
  try {
    await Promise.allSettled([
      fetchBookings(),
      fetchUserSummary(),
      fetchTotalApprovedDepost(),
      fetchAgentsDeposits(),
      fetchUsers(),
    ]);
  } finally {
    isDashboardLoading.value = false;
  }
}

// Refresh all dashboard metrics
async function refreshDashboard() {
  isRefreshing.value = true;
  try {
    await Promise.allSettled([
      fetchBookings(),
      fetchUserSummary(),
      fetchTotalApprovedDepost(),
      fetchAgentsDeposits(),
      fetchUsers()
    ]);
  } finally {
    setTimeout(() => {
      isRefreshing.value = false;
    }, 400);
  }
}

// Route Navigation
function openBookingDetails(booking) {
  router.push({
    name: "AdminCustomerBookingsLayout",
    query: {
      booking_id: booking.id,
      pnr: booking?.itinerary_ref ? booking?.itinerary_ref : booking.pnr,
      agent_id: booking.agent_id,
      flight_mode: "B2C",
      booking_source: booking?.booking_source,
      flight_provider: booking?.flight_provider,
    },
  });
}

function openDepositDetails(deposit) {
  router.push({
    name: "DepositDetails",
    query: {
      deposit_id: deposit.id,
    },
  });
}

onMounted(loadDashboard);
</script>

<template>
  <div v-if="isDashboardLoading" class="space-y-6 animate-pulse" aria-busy="true" aria-label="Loading dashboard">
    <div class="flex flex-col gap-4 rounded border border-slate-200 bg-white p-5 md:flex-row md:items-center md:justify-between">
      <div class="space-y-3">
        <div class="h-7 w-64 rounded bg-slate-200"></div>
        <div class="h-3 w-48 rounded bg-slate-100"></div>
      </div>
      <div class="h-9 w-24 rounded bg-slate-100"></div>
    </div>

    <div class="h-24 rounded border border-slate-200 bg-slate-100"></div>

    <div class="rounded border border-slate-200 bg-white overflow-hidden">
      <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 p-4">
        <div class="h-5 w-52 rounded bg-slate-200"></div>
        <div class="h-8 w-32 rounded bg-slate-200"></div>
      </div>
      <div class="grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
        <div v-for="card in 4" :key="`booking-kpi-${card}`" class="h-28 rounded border border-slate-200 bg-slate-50"></div>
      </div>
      <div class="grid grid-cols-1 gap-5 border-t border-slate-200 p-5 lg:grid-cols-12">
        <div class="h-96 rounded border border-slate-200 bg-slate-50 lg:col-span-8 xl:col-span-9"></div>
        <div class="h-72 rounded border border-slate-200 bg-slate-50 lg:col-span-4 xl:col-span-3"></div>
      </div>
    </div>

    <div class="rounded border border-slate-200 bg-white overflow-hidden">
      <div class="h-16 border-b border-slate-200 bg-slate-50"></div>
      <div class="grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
        <div v-for="card in 4" :key="`deposit-kpi-${card}`" class="h-28 rounded border border-slate-200 bg-slate-50"></div>
      </div>
      <div class="grid grid-cols-1 gap-5 border-t border-slate-200 p-5 lg:grid-cols-12">
        <div class="h-80 rounded border border-slate-200 bg-slate-50 lg:col-span-8 xl:col-span-9"></div>
        <div class="h-72 rounded border border-slate-200 bg-slate-50 lg:col-span-4 xl:col-span-3"></div>
      </div>
    </div>
  </div>

  <div v-else class="space-y-6">
    <!-- Top Welcome & Quick Actions Bar -->
    <div class="flex flex-col gap-4 rounded border border-slate-200 bg-white p-5 shadow-xs md:flex-row md:items-center md:justify-between">
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
            Welcome back, {{ adminName }}
          </h1>
          <span class="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-slate-600">
            <ShieldCheck class="h-3 w-3 text-primary" />
            {{ user?.role || 'Admin' }}
          </span>
          <span class="inline-flex items-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
            <span class="h-1.5 w-1.5 rounded-sm bg-emerald-500 animate-pulse"></span>
            Operational
          </span>
        </div>
        <p class="flex items-center gap-2 text-xs text-slate-500">
          <CalendarIcon class="h-3.5 w-3.5 text-slate-400" />
          <span>{{ todayDateFormatted }}</span>
          <span>·</span>
          <span>Central Admin Dashboard</span>
        </p>
      </div>

      <!-- Quick Action Shortcuts -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="refreshDashboard"
          :disabled="isRefreshing"
          class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900 cursor-pointer disabled:opacity-50"
          title="Refresh Dashboard Data"
        >
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
          <span>Sync</span>
        </button>

        <router-link
          :to="{ name: 'TravelCalendar' }"
          class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-900"
        >
          <CalendarIcon class="h-3.5 w-3.5 text-slate-500" />
          <span>Calendar</span>
        </router-link>
      </div>
    </div>

    <!-- Travel Today Live Alert Widget -->
    <TravelTodayWidget />

    <!-- SECTION 1: Booking Overview -->
    <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
      <!-- Section Header -->
      <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded bg-primary/10 text-primary">
            <Plane class="h-5 w-5 -rotate-45" />
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 sm:text-base">Booking Overview</h2>
            <p class="text-xs text-slate-500">Live PNR reservations, ticketing status, and flight route distribution</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="$router.push({ name: 'AdminCustomerBookings' })"
            class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-primary cursor-pointer"
          >
            <Eye class="h-3.5 w-3.5" />
            <span>View All Bookings</span>
          </button>
        </div>
      </div>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
        <!-- All Bookings -->
        <div
          @click="filterBookings('all')"
          class="relative rounded border p-4 transition-all cursor-pointer"
          :class="activeFilter === 'all' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50 hover:border-slate-300'"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-600">All Bookings</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-slate-200/70 text-slate-700">
              <CalendarIcon class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
            {{ bookings?.total_count || 0 }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-slate-500">Total system records</span>
        </div>

        <!-- Ticketed -->
        <div
          @click="filterBookings('ticketed')"
          class="relative rounded border p-4 transition-all cursor-pointer"
          :class="activeFilter === 'ticketed' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50 hover:border-slate-300'"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-emerald-700">Ticketed</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-emerald-100 text-emerald-700">
              <CheckCircleIcon class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-950">
            {{ bookings?.total_ticketed || 0 }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-emerald-700">
            {{ bookings?.total_count ? Math.round(((bookings?.total_ticketed || 0) / bookings.total_count) * 100) : 0 }}% of total bookings
          </span>
        </div>

        <!-- On Hold -->
        <div
          @click="filterBookings('booked')"
          class="relative rounded border p-4 transition-all cursor-pointer"
          :class="activeFilter === 'booked' ? 'border-amber-500 bg-amber-50/40 ring-1 ring-amber-500' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50 hover:border-slate-300'"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700">On Hold</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-amber-100 text-amber-700">
              <CirclePause class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-bold tracking-tight text-amber-950">
            {{ bookings?.total_booked || 0 }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-amber-700">Awaiting ticketing</span>
        </div>

        <!-- Canceled -->
        <div
          @click="filterBookings('canceled')"
          class="relative rounded border p-4 transition-all cursor-pointer"
          :class="activeFilter === 'canceled' ? 'border-rose-500 bg-rose-50/40 ring-1 ring-rose-500' : 'border-slate-200 bg-slate-50/40 hover:bg-slate-50 hover:border-slate-300'"
        >
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-rose-700">Total Canceled</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-rose-100 text-rose-700">
              <Ban class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-2xl font-bold tracking-tight text-rose-950">
            {{ bookings?.total_canceled || 0 }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-rose-700">Void / canceled flights</span>
        </div>
      </div>

      <!-- Main Booking Content: Table + Chart -->
      <div class="grid grid-cols-1 gap-5 border-t border-slate-200 p-5 lg:grid-cols-12">
        <!-- Table Column -->
        <div class="lg:col-span-8 xl:col-span-9 rounded border border-slate-200 bg-white overflow-hidden flex flex-col">
          <!-- Table Toolbar -->
          <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/50 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-1.5">
              <button
                @click="filterBookings('all')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="activeFilter === 'all' ? 'bg-primary text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                All
              </button>
              <button
                @click="filterBookings('ticketed')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="activeFilter === 'ticketed' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                Ticketed
              </button>
              <button
                @click="filterBookings('booked')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="activeFilter === 'booked' ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                On Hold
              </button>
              <button
                @click="filterBookings('canceled')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="activeFilter === 'canceled' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                Canceled
              </button>
            </div>

            <!-- Search Field -->
            <div class="relative w-full sm:w-56">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input
                v-model="bookingSearch"
                type="text"
                placeholder="Search PNR, email, route..."
                class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
          </div>

          <!-- Table Container -->
          <div class="custom-scrollbar max-h-96 overflow-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50 sticky top-0 z-10">
                <tr>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Booking Date
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    PNR
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Customer / Agency
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Flight Route
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Status
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr
                  v-for="booking in filteredBookings"
                  :key="booking.id"
                  class="transition-colors hover:bg-slate-50/80"
                >
                  <!-- Date -->
                  <td class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-700">
                    {{ booking.created_at ? moment(booking.created_at).format('DD MMM YYYY') : '—' }}
                  </td>

                  <!-- PNR with Copy Action -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <div class="flex items-center gap-1.5">
                      <span class="rounded border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800">
                        {{ booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr ?? '—' }}
                      </span>
                      <button
                        v-if="booking.itinerary_reference || booking.itinerary_ref || booking.pnr"
                        @click="copyPnrToClipboard(booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr)"
                        class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                        title="Copy PNR"
                      >
                        <Check v-if="copiedPnr === (booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr)" class="h-3 w-3 text-emerald-600" />
                        <Copy v-else class="h-3 w-3" />
                      </button>
                    </div>
                  </td>

                  <!-- Customer Email -->
                  <td class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-600">
                    <span class="max-w-[160px] truncate block" :title="booking.agency_email">
                      {{ booking.agency_email || '—' }}
                    </span>
                  </td>

                  <!-- Flight Route -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <div class="flex items-center gap-1.5 text-xs">
                      <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                        {{ getFlightRouteInfo(booking).origin }}
                      </span>
                      <ArrowRight class="h-3 w-3 text-slate-400" />
                      <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                        {{ getFlightRouteInfo(booking).destination }}
                      </span>
                    </div>
                  </td>

                  <!-- Status -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <span
                      class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wider"
                      :class="{
                        'bg-emerald-50 text-emerald-700 border border-emerald-200': booking.status === 'ticketed',
                        'bg-rose-50 text-rose-700 border border-rose-200': booking.status === 'canceled',
                        'bg-amber-50 text-amber-700 border border-amber-200': booking.status === 'booked',
                        'bg-slate-100 text-slate-700 border border-slate-200': !['ticketed', 'canceled', 'booked'].includes(booking.status),
                      }"
                    >
                      {{ booking.status || 'Unknown' }}
                    </span>
                  </td>

                  <!-- Action -->
                  <td class="whitespace-nowrap px-4 py-2.5 text-right">
                    <button
                      @click="openBookingDetails(booking)"
                      class="inline-flex items-center justify-center rounded p-1.5 text-slate-500 hover:bg-primary/10 hover:text-primary transition cursor-pointer"
                      title="View Booking Details"
                    >
                      <CircleChevronRight class="h-4 w-4" />
                    </button>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-if="filteredBookings.length === 0">
                  <td colspan="6" class="px-6 py-12 text-center text-xs text-slate-500">
                    <Plane class="mx-auto h-8 w-8 text-slate-300 mb-2 -rotate-45" />
                    <p class="font-medium text-slate-700">No bookings found</p>
                    <p class="text-slate-400 mt-1">Try clearing your search or switching the filter tab.</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Table Footer -->
          <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-2 text-xs text-slate-500 flex justify-between items-center">
            <span>Showing {{ filteredBookings.length }} latest bookings</span>
            <router-link :to="{ name: 'AdminCustomerBookings' }" class="font-semibold text-primary hover:underline flex items-center gap-1">
              <span>All Bookings</span>
              <ArrowRight class="h-3 w-3" />
            </router-link>
          </div>
        </div>

        <!-- Chart Column -->
        <div class="lg:col-span-4 xl:col-span-3 flex">
          <FlightBookingChart :bookings="bookingsData" />
        </div>
      </div>
    </div>

    <!-- SECTION 2: Financial & Deposits Overview -->
    <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
      <!-- Section Header -->
      <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded bg-emerald-50 text-emerald-600">
            <CircleDollarSign class="h-5 w-5" />
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 sm:text-base">Deposits & Wallets Overview</h2>
            <p class="text-xs text-slate-500">Agent balance top-ups, approval requests, and deposit logs</p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button
            @click="$router.push({ name: 'Users' })"
            class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-primary cursor-pointer"
          >
            <Eye class="h-3.5 w-3.5" />
            <span>Manage Agents</span>
          </button>
        </div>
      </div>

      <!-- KPI Stat Cards -->
      <div class="grid grid-cols-2 gap-4 p-5 lg:grid-cols-4">
        <!-- Total Deposits -->
        <div class="rounded border border-slate-200 bg-slate-50/40 p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-600">Total Deposits</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-slate-200/70 text-slate-700">
              <CircleDollarSign class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">
            {{ formatMoney(agentDepositTotals?.totalDeposits) }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-slate-500">All submitted top-ups</span>
        </div>

        <!-- Approved -->
        <div class="rounded border border-slate-200 bg-slate-50/40 p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-emerald-700">Approved Deposits</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-emerald-100 text-emerald-700">
              <CheckCircleIcon class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-xl font-bold tracking-tight text-emerald-950">
            {{ formatMoney(agentDepositTotals?.totalApprovedDeposits) }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-emerald-700">Cleared & credited</span>
        </div>

        <!-- Pending -->
        <div class="rounded border border-slate-200 bg-slate-50/40 p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700">Pending Approval</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-amber-100 text-amber-700">
              <Hourglass class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-xl font-bold tracking-tight text-amber-950">
            {{ formatMoney(agentDepositTotals?.totalPendingDeposits) }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-amber-700">Requires verification</span>
        </div>

        <!-- Rejected -->
        <div class="rounded border border-slate-200 bg-slate-50/40 p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-rose-700">Rejected Deposits</span>
            <span class="flex h-7 w-7 items-center justify-center rounded bg-rose-100 text-rose-700">
              <X class="h-3.5 w-3.5" />
            </span>
          </div>
          <p class="mt-2 text-xl font-bold tracking-tight text-rose-950">
            {{ formatMoney(agentDepositTotals?.totalRejectedDeposits) }}
          </p>
          <span class="mt-1 block text-[11px] font-medium text-rose-700">Declined transactions</span>
        </div>
      </div>

      <!-- Main Deposits Content: Table + Chart -->
      <div class="grid grid-cols-1 gap-5 border-t border-slate-200 p-5 lg:grid-cols-12">
        <!-- Table Column -->
        <div class="lg:col-span-8 xl:col-span-9 rounded border border-slate-200 bg-white overflow-hidden flex flex-col">
          <!-- Toolbar -->
          <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/50 p-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-1.5">
              <button
                @click="filterDeposits('all')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="depositFilter === 'all' ? 'bg-primary text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                All
              </button>
              <button
                @click="filterDeposits('approved')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="depositFilter === 'approved' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                Approved
              </button>
              <button
                @click="filterDeposits('pending')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="depositFilter === 'pending' ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                Pending
              </button>
              <button
                @click="filterDeposits('rejected')"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="depositFilter === 'rejected' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
              >
                Rejected
              </button>
            </div>

            <!-- Search Field -->
            <div class="relative w-full sm:w-56">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input
                v-model="depositSearch"
                type="text"
                placeholder="Search agent or currency..."
                class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
              />
            </div>
          </div>

          <!-- Table Container -->
          <div class="custom-scrollbar max-h-96 overflow-auto">
            <table class="min-w-full divide-y divide-slate-200">
              <thead class="bg-slate-50 sticky top-0 z-10">
                <tr>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Date
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Agent / Company
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Amount
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Status
                  </th>
                  <th scope="col" class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 bg-white">
                <tr
                  v-for="deposit in filteredDeposits"
                  :key="deposit?.id"
                  class="transition-colors hover:bg-slate-50/80"
                >
                  <!-- Date -->
                  <td class="whitespace-nowrap px-4 py-2.5 text-xs text-slate-700">
                    {{ deposit?.date ? moment(deposit?.date).format('DD MMM YYYY') : '—' }}
                  </td>

                  <!-- Agent / Company -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <p class="text-xs font-bold text-slate-900">
                      {{ deposit?.agent?.agent_data?.company_name || deposit?.agent?.name || '—' }}
                    </p>
                    <p class="text-[11px] text-slate-500">
                      {{ deposit?.agent?.email || deposit?.agent?.agent_data?.phone || '' }}
                    </p>
                  </td>

                  <!-- Amount -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <div class="text-xs font-bold text-slate-900">
                      {{ formatMoney(deposit?.base_money, deposit?.aed_amount, 'AED') }}
                    </div>
                    <div class="text-[11px] text-slate-500">
                      Deposited: {{ formatMoney(deposit?.source_money, deposit?.amount, deposit?.currency) }}
                    </div>
                  </td>

                  <!-- Status -->
                  <td class="whitespace-nowrap px-4 py-2.5">
                    <span
                      class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wider"
                      :class="{
                        'bg-amber-50 text-amber-700 border border-amber-200': deposit?.deposit_status === 'pending',
                        'bg-emerald-50 text-emerald-700 border border-emerald-200': deposit?.deposit_status === 'approved',
                        'bg-rose-50 text-rose-700 border border-rose-200': deposit?.deposit_status === 'rejected',
                        'bg-slate-100 text-slate-700 border border-slate-200': !['pending', 'approved', 'rejected'].includes(deposit?.deposit_status),
                      }"
                    >
                      {{ deposit?.deposit_status || 'Unknown' }}
                    </span>
                  </td>

                  <!-- Action -->
                  <td class="whitespace-nowrap px-4 py-2.5 text-right">
                    <button
                      @click="openDepositDetails(deposit)"
                      class="inline-flex items-center justify-center rounded p-1.5 text-slate-500 hover:bg-primary/10 hover:text-primary transition cursor-pointer"
                      title="View Deposit Details"
                    >
                      <CircleChevronRight class="h-4 w-4" />
                    </button>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-if="filteredDeposits.length === 0">
                  <td colspan="5" class="px-6 py-12 text-center text-xs text-slate-500">
                    <CircleDollarSign class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                    <p class="font-medium text-slate-700">No deposit records found</p>
                    <p class="text-slate-400 mt-1">Try modifying your search or filter selection.</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Table Footer -->
          <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-2 text-xs text-slate-500 flex justify-between items-center">
            <span>Showing {{ filteredDeposits.length }} latest deposits</span>
            <router-link :to="{ name: 'AdminLedger' }" class="font-semibold text-primary hover:underline flex items-center gap-1">
              <span>View Accounting Ledger</span>
              <ArrowRight class="h-3 w-3" />
            </router-link>
          </div>
        </div>

        <!-- Chart Column -->
        <div class="lg:col-span-4 xl:col-span-3 flex">
          <depositChart :deposits="agentDepositTotals" />
        </div>
      </div>
    </div>

    <!-- SECTION 3: Administrative Quick Hub -->
    <div class="rounded border border-slate-200 bg-white p-5 shadow-xs">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
        <div>
          <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Quick Administrative Hub</h3>
          <p class="text-xs text-slate-500">Fast access to key operational and accounting modules</p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
        <!-- Module 1: Customer Bookings -->
        <router-link
          :to="{ name: 'AdminCustomerBookings' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-primary/10 text-primary">
            <Ticket class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Bookings</span>
          <span class="text-[11px] text-slate-500">Manage all tickets</span>
        </router-link>

        <!-- Module 2: Request Inbox -->
        <router-link
          :to="{ name: 'BookingRequests' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-amber-100 text-amber-700">
            <Layers class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Request Inbox</span>
          <span class="text-[11px] text-slate-500">Modifications & voids</span>
        </router-link>

        <!-- Module 3: Group Tickets -->
        <router-link
          :to="{ name: 'GroupTickets' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-sky-100 text-sky-700">
            <Plane class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Group Fares</span>
          <span class="text-[11px] text-slate-500">Inventory & blocks</span>
        </router-link>

        <!-- Module 4: Users & Agents -->
        <router-link
          :to="{ name: 'Users' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-indigo-100 text-indigo-700">
            <Users class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Agents & Users</span>
          <span class="text-[11px] text-slate-500">Wallets & approvals</span>
        </router-link>

        <!-- Module 5: Ledger & Financials -->
        <router-link
          :to="{ name: 'AdminLedger' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-emerald-100 text-emerald-700">
            <FileText class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Admin Ledger</span>
          <span class="text-[11px] text-slate-500">Accounts & statements</span>
        </router-link>

        <!-- Module 6: Insights & Analytics -->
        <router-link
          :to="{ name: 'MarketingInsights' }"
          class="flex flex-col gap-2 rounded border border-slate-200 bg-slate-50/50 p-3 transition hover:border-primary/50 hover:bg-white hover:shadow-xs"
        >
          <div class="flex h-8 w-8 items-center justify-center rounded bg-violet-100 text-violet-700">
            <TrendingUp class="h-4 w-4" />
          </div>
          <span class="text-xs font-bold text-slate-800">Insights</span>
          <span class="text-[11px] text-slate-500">Trends & performance</span>
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* custom scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
