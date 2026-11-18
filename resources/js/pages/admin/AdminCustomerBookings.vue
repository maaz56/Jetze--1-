<script setup>
import { computed, onMounted, onUnmounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";
import { useUserStore } from "@/services/stores/user";

// UI Components
import Button from "@/components/ui/button/Button.vue";
import { Badge } from "@/components/ui/badge";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import HotelBookings from "@/pages/admin/HotelBookings.vue";

// Actions
import {
    CUSTOMER_BOOKINGS,
    APPROVE_BOOKING,
    FETCH_AGENT_LEDGER,
} from "@/services/store/actions.type";

// Icons
import {
    Calendar as CalendarIcon,
    CheckCircle as CheckCircleIcon,
    CirclePause,
    CircleX,
    Ban,
    TicketCheck,
    Building2,
    Search,
    Copy,
    Check,
    ArrowRight,
    CircleChevronRight,
    RefreshCw,
    Clock,
    User,
    Plane,
    AlertTriangle,
    Eye,
} from "lucide-vue-next";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Booking Type: Flights vs Hotels
const activeBookingType = computed(() => (route.query.type === "hotels" ? "hotels" : "flights"));

function selectBookingType(type) {
    const query = { ...route.query };
    if (type === "hotels") {
        query.type = "hotels";
    } else {
        delete query.type;
    }
    router.replace({ name: "AdminCustomerBookings", query });
}

// Reactive State
const bookings = computed(() => store.getters["flight/customerBookings"]);
const user = computed(() => authStore.user);
const emit = defineEmits(["filter-change"]);
const activeFilter = ref("all");
const tableSearch = ref("");
const copiedPnr = ref("");
const isRefreshing = ref(false);

// Live countdown
const now = ref(Date.now());
let countdownInterval;

// Status update dialog
const isStatusDialogOpen = ref(false);
const isNegativeLedgerWarningOpen = ref(false);
const ledgerWarningType = ref("negative");
const isStatusUpdating = ref(false);
const statusForm = ref({
    booking_id: null,
    status: "booked",
    required_aed: null,
});

const statusOptions = [
    "booked",
    "ticketed",
    "requested",
    "voided",
    "canceled",
];

// Ledger calculations
const selectedCustomerLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const selectedCustomerLedgerBalance = computed(() => {
    const amount = selectedCustomerLedger.value?.balance ?? selectedCustomerLedger.value?.balance_money?.amount;
    const balance = typeof amount === "string" ? Number(amount.replace(/,/g, "")) : Number(amount);
    return Number.isFinite(balance) ? balance : null;
});

const isIssuingStatus = computed(() => ["ticketed", "issued"].includes(String(statusForm.value.status).toLowerCase()));
const hasNegativeSelectedCustomerLedger = computed(() => selectedCustomerLedgerBalance.value !== null && selectedCustomerLedgerBalance.value < 0);
const hasInsufficientSelectedCustomerLedger = computed(() => {
    const required = Number(statusForm.value.required_aed);
    return (
        selectedCustomerLedgerBalance.value !== null &&
        selectedCustomerLedgerBalance.value >= 0 &&
        Number.isFinite(required) &&
        required > 0 &&
        selectedCustomerLedgerBalance.value < required
    );
});

// Filtering
function filterBookings(type) {
    activeFilter.value = type;
    fetchBookings();
}

// Formatters
const formatAmount = (amount) => {
    return new Intl.NumberFormat("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0);
};

const parseFlightData = (flightDataString) => {
    if (!flightDataString) return null;
    if (typeof flightDataString === "object") return flightDataString;
    try {
        return JSON.parse(flightDataString);
    } catch (error) {
        return null;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return "—";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const getRemainingTime = (expiry) => {
    if (!expiry) return "N/A";
    const expiryTime = new Date(String(expiry).replace(" ", "T")).getTime();
    const diff = expiryTime - now.value;
    if (Number.isNaN(expiryTime) || diff <= 0) return "Expired";

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    let result = "";
    if (days > 0) result += `${days}d `;
    if (hours > 0 || days > 0) result += `${hours}h `;
    return `${result}${minutes}m ${seconds.toString().padStart(2, "0")}s`;
};

// Route & Flight Info Extractor
function getRouteData(booking) {
    const flight = parseFlightData(booking?.flight_data);
    let origin = null;
    let destination = null;

    const flights = flight?.leg?.flights || flight?.original?.leg?.flights || flight?.flights;
    if (Array.isArray(flights) && flights.length > 0) {
        const firstFlight = flights[0];
        const lastFlight = flights[flights.length - 1];
        origin = firstFlight?.from?.iata || firstFlight?.from?.code || (typeof firstFlight?.from === "string" ? firstFlight.from : null);
        destination = lastFlight?.to?.iata || lastFlight?.to?.code || (typeof lastFlight?.to === "string" ? lastFlight.to : null);
    }

    if ((!origin || !destination) && Array.isArray(flight?.legs) && flight.legs.length > 0) {
        const firstLeg = flight.legs[0];
        const lastLeg = flight.legs[flight.legs.length - 1];
        const firstStop = firstLeg?.stops?.[0];
        origin = firstStop?.departure?.airport?.iata_code || firstLeg?.departure_airport || null;
        const lastStops = lastLeg?.stops;
        const lastStop = lastStops && lastStops.length > 0 ? lastStops[lastStops.length - 1] : null;
        destination = lastStop?.arrival?.airport?.iata_code || lastLeg?.arrival_airport || null;
    }

    if (!origin) origin = booking?.origin || "—";
    if (!destination) destination = booking?.destination || "—";

    return { origin, destination };
}

// Copy PNR
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

// Filtered bookings list (with local instant search)
const filteredBookingsList = computed(() => {
    let list = bookings.value?.bookings || [];
    if (!tableSearch.value.trim()) return list;

    const q = tableSearch.value.trim().toLowerCase();
    return list.filter((b) => {
        const pnr = (b.itinerary_reference || b.itinerary_ref || b.pnr || "").toLowerCase();
        const id = String(b.id || "").toLowerCase();
        const email = (b.agency_email || "").toLowerCase();
        const uid = (b?.user?.customer?.customer_uid || "").toLowerCase();
        const pax = (b.pessangers || b.passengers || []).map((p) => `${p.first_name || ""} ${p.last_name || ""}`).join(" ").toLowerCase();
        const routeInfo = getRouteData(b);
        const route = `${routeInfo.origin} ${routeInfo.destination}`.toLowerCase();
        return pnr.includes(q) || id.includes(q) || email.includes(q) || uid.includes(q) || pax.includes(q) || route.includes(q);
    });
});

// Data fetching
function fetchBookings() {
    const selectedUserId = route.query.user_id || route.query.userId;
    store.dispatch("flight/" + CUSTOMER_BOOKINGS, {
        user_id: selectedUserId,
        userId: selectedUserId,
        userRole: user.value?.role,
        bookingFilter: activeFilter.value,
    });
}

async function refreshAll() {
    isRefreshing.value = true;
    try {
        await fetchBookings();
    } finally {
        setTimeout(() => {
            isRefreshing.value = false;
        }, 400);
    }
}

// Status dialog actions
async function openStatusDialog(booking) {
    statusForm.value.booking_id = booking.id;
    statusForm.value.status = booking.status || "booked";
    statusForm.value.required_aed = booking.price_snapshot?.aed_amount ?? booking.aed_amount ?? booking.amount ?? null;

    if (booking.agent_id) {
        await store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
            userId: booking.agent_id,
            currency_code: "AED",
        });
    }

    isStatusDialogOpen.value = true;
}

function requestBookingStatusUpdate() {
    if (!statusForm.value.booking_id || !statusForm.value.status) return;

    if (isIssuingStatus.value && hasNegativeSelectedCustomerLedger.value) {
        ledgerWarningType.value = "negative";
        isNegativeLedgerWarningOpen.value = true;
        return;
    }

    if (isIssuingStatus.value && hasInsufficientSelectedCustomerLedger.value) {
        ledgerWarningType.value = "insufficient";
        isNegativeLedgerWarningOpen.value = true;
        return;
    }

    updateBookingStatus();
}

function continueWithNegativeLedger() {
    isNegativeLedgerWarningOpen.value = false;
    updateBookingStatus();
}

function updateBookingStatus() {
    if (isStatusUpdating.value) return;

    isStatusUpdating.value = true;
    store.dispatch("flight/" + APPROVE_BOOKING, {
        booking_id: statusForm.value.booking_id,
        status: statusForm.value.status,
    })
        .then(() => {
            isStatusDialogOpen.value = false;
            fetchBookings();
        })
        .finally(() => {
            isStatusUpdating.value = false;
        });
}

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

onMounted(() => {
    countdownInterval = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    fetchBookings();
});

onUnmounted(() => clearInterval(countdownInterval));
</script>

<template>
    <section class="space-y-6">
        <!-- Top Navigation Tabs Bar -->
        <div class="rounded border border-slate-200 bg-white p-2 shadow-xs">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-1.5" role="tablist" aria-label="Booking categories">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                        :class="activeBookingType === 'flights' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        :aria-selected="activeBookingType === 'flights'"
                        role="tab"
                        @click="selectBookingType('flights')"
                    >
                        <TicketCheck class="h-4 w-4" />
                        <span>Flight Bookings</span>
                        <span
                            v-if="bookings?.total_count"
                            class="rounded px-1.5 py-0.2 text-[10px] font-bold"
                            :class="activeBookingType === 'flights' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ bookings?.total_count }}
                        </span>
                    </button>

                    <button
                        type="button"
                        class="flex items-center gap-2 rounded px-4 py-2 text-xs font-semibold transition cursor-pointer"
                        :class="activeBookingType === 'hotels' ? 'bg-primary text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                        :aria-selected="activeBookingType === 'hotels'"
                        role="tab"
                        @click="selectBookingType('hotels')"
                    >
                        <Building2 class="h-4 w-4" />
                        <span>Hotel Bookings</span>
                    </button>
                </div>

                <div class="flex items-center gap-2 pr-2">
                    <button
                        @click="refreshAll"
                        :disabled="isRefreshing"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                        title="Refresh Bookings"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                        <span class="hidden sm:inline">Refresh</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab 1: Hotel Bookings Embedded -->
        <div v-if="activeBookingType === 'hotels'">
            <HotelBookings embedded />
        </div>

        <!-- Tab 2: Flight Bookings Overview -->
        <div v-else class="space-y-6">
            <!-- Filter KPI Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <!-- All Bookings -->
                <div
                    @click="filterBookings('all')"
                    class="relative rounded border p-4 transition-all cursor-pointer"
                    :class="activeFilter === 'all' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-600">All Bookings</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-slate-100 text-slate-700">
                            <CalendarIcon class="h-3.5 w-3.5" />
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900">
                        {{ bookings?.total_count || 0 }}
                    </p>
                    <span class="mt-1 block text-[11px] font-medium text-slate-500">Total records</span>
                </div>

                <!-- Ticketed -->
                <div
                    @click="filterBookings('ticketed')"
                    class="relative rounded border p-4 transition-all cursor-pointer"
                    :class="activeFilter === 'ticketed' ? 'border-emerald-600 bg-emerald-50/40 ring-1 ring-emerald-600' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
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
                    <span class="mt-1 block text-[11px] font-medium text-emerald-700">Issued & confirmed</span>
                </div>

                <!-- On Hold -->
                <div
                    @click="filterBookings('booked')"
                    class="relative rounded border p-4 transition-all cursor-pointer"
                    :class="activeFilter === 'booked' ? 'border-amber-500 bg-amber-50/40 ring-1 ring-amber-500' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
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
                    <span class="mt-1 block text-[11px] font-medium text-amber-700">Awaiting payment / issue</span>
                </div>

                <!-- Voided -->
                <div
                    @click="filterBookings('voided')"
                    class="relative rounded border p-4 transition-all cursor-pointer"
                    :class="activeFilter === 'voided' ? 'border-purple-600 bg-purple-50/40 ring-1 ring-purple-600' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-purple-700">Voided</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-purple-100 text-purple-700">
                            <CircleX class="h-3.5 w-3.5" />
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-purple-950">
                        {{ bookings?.total_voided || 0 }}
                    </p>
                    <span class="mt-1 block text-[11px] font-medium text-purple-700">Voided within 24h</span>
                </div>

                <!-- Canceled -->
                <div
                    @click="filterBookings('canceled')"
                    class="relative rounded border p-4 transition-all cursor-pointer"
                    :class="activeFilter === 'canceled' ? 'border-rose-500 bg-rose-50/40 ring-1 ring-rose-500' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-rose-700">Canceled</span>
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-rose-100 text-rose-700">
                            <Ban class="h-3.5 w-3.5" />
                        </span>
                    </div>
                    <p class="mt-2 text-2xl font-bold tracking-tight text-rose-950">
                        {{ bookings?.total_canceled || 0 }}
                    </p>
                    <span class="mt-1 block text-[11px] font-medium text-rose-700">Canceled reservations</span>
                </div>
            </div>

            <!-- Main Bookings Card & Table -->
            <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
                <!-- Toolbar -->
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            @click="filterBookings('all')"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="activeFilter === 'all' ? 'bg-primary text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            All Bookings
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
                            @click="filterBookings('voided')"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="activeFilter === 'voided' ? 'bg-purple-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            Voided
                        </button>
                        <button
                            @click="filterBookings('canceled')"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="activeFilter === 'canceled' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
                        >
                            Canceled
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-72">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="tableSearch"
                            type="text"
                            placeholder="Search PNR, ID, customer, route, pax..."
                            class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>

                <!-- Table Container -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left">Booking Date</th>
                                <th scope="col" class="px-4 py-3 text-left">Booking ID</th>
                                <th scope="col" class="px-4 py-3 text-left">PNR</th>
                                <th scope="col" class="px-4 py-3 text-left">Supplier</th>
                                <th scope="col" class="px-4 py-3 text-left">Customer</th>
                                <th scope="col" class="px-4 py-3 text-left">Route</th>
                                <th scope="col" class="px-4 py-3 text-left">Passenger</th>
                                <th scope="col" class="px-4 py-3 text-left">Travel Date</th>
                                <th scope="col" class="px-4 py-3 text-left">Status</th>
                                <th scope="col" class="px-4 py-3 text-left">Expiry</th>
                                <th scope="col" class="px-4 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr
                                v-for="booking in filteredBookingsList"
                                :key="booking.id"
                                class="transition-colors hover:bg-slate-50/80"
                            >
                                <!-- Booking Date -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-700">
                                    <div class="font-medium text-slate-900">
                                        {{ booking.created_at ? moment(booking.created_at).format('DD MMM YYYY') : '—' }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        {{ booking.created_at ? moment(booking.created_at).format('hh:mm A') : '' }}
                                    </div>
                                </td>

                                <!-- Booking ID -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-800 border border-slate-200">
                                        #{{ booking.id }}
                                    </span>
                                </td>

                                <!-- PNR with Copy Action -->
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <span class="rounded border border-primary/30 bg-primary/5 px-2 py-0.5 font-mono text-xs font-bold text-primary">
                                            {{ booking.itinerary_ref ? booking?.itinerary_ref : (booking.pnr || '—') }}
                                        </span>
                                        <button
                                            v-if="booking.itinerary_ref || booking.pnr"
                                            @click="copyPnrToClipboard(booking.itinerary_ref || booking.pnr)"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                                            title="Copy PNR"
                                        >
                                            <Check v-if="copiedPnr === (booking.itinerary_ref || booking.pnr)" class="h-3 w-3 text-emerald-600" />
                                            <Copy v-else class="h-3 w-3" />
                                        </button>
                                    </div>
                                </td>

                                <!-- Supplier -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    <span class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-700 uppercase">
                                        {{ parseFlightData(booking?.flight_data)?.provider?.name || booking?.flight_provider || 'GDS' }}
                                    </span>
                                </td>

                                <!-- Customer -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    <div class="font-medium text-slate-900">
                                        {{ booking?.user?.customer?.customer_uid || "—" }}
                                    </div>
                                    <div class="text-[11px] text-slate-500 max-w-[140px] truncate" :title="booking.agency_email">
                                        {{ booking.agency_email || "" }}
                                    </div>
                                </td>

                                <!-- Route -->
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex items-center gap-1 text-xs">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            {{ getRouteData(booking).origin }}
                                        </span>
                                        <ArrowRight class="h-3 w-3 text-slate-400" />
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            {{ getRouteData(booking).destination }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Passenger Name -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-700">
                                    <div v-for="passenger in (booking.pessangers || booking.passengers || []).slice(0, 1)" :key="passenger.id">
                                        <span class="font-medium text-slate-900">
                                            {{ passenger.title }} {{ passenger.first_name }} {{ passenger.last_name }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="(booking.pessangers || booking.passengers || []).length > 1"
                                        class="text-[10px] text-slate-500 mt-0.5"
                                    >
                                        +{{ (booking.pessangers || booking.passengers || []).length - 1 }} more passenger(s)
                                    </div>
                                </td>

                                <!-- Travel Date -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-700">
                                    <template v-if="booking?.booking_source == 1">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights?.slice(0, 1)" :key="flightIndex">
                                            <span class="font-medium text-slate-900">{{ formatDate(flight?.departure_at) }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div v-for="(date, dateIndex) in parseFlightData(booking.flight_data)?.dates?.slice(0, 1)" :key="dateIndex">
                                            <span class="font-medium text-slate-900">{{ formatDate(date.departureDate) }}</span>
                                        </div>
                                    </template>
                                </td>

                                <!-- Status -->
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border border-emerald-200': booking.status === 'ticketed',
                                            'bg-amber-50 text-amber-700 border border-amber-200': booking.status === 'booked',
                                            'bg-purple-50 text-purple-700 border border-purple-200': booking.status === 'voided',
                                            'bg-rose-50 text-rose-700 border border-rose-200': booking.status === 'canceled',
                                            'bg-slate-100 text-slate-700 border border-slate-200': !['ticketed', 'booked', 'voided', 'canceled'].includes(booking.status),
                                        }"
                                    >
                                        {{ booking.status || 'Unknown' }}
                                    </span>
                                </td>

                                <!-- Expiry Countdown -->
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    <span
                                        v-if="booking?.status?.toLowerCase() === 'booked'"
                                        class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 font-mono text-[11px] font-semibold text-amber-800"
                                    >
                                        <Clock class="h-3 w-3 text-amber-600" />
                                        {{ getRemainingTime(booking.expiry_time) }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500"
                                    >
                                        —
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            @click="openStatusDialog(booking)"
                                            class="rounded border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 transition hover:border-primary hover:text-primary cursor-pointer shadow-2xs"
                                        >
                                            Status
                                        </button>
                                        <button
                                            @click="openBookingDetails(booking)"
                                            class="inline-flex items-center justify-center rounded p-1 text-slate-500 hover:bg-primary/10 hover:text-primary transition cursor-pointer"
                                            title="View Details"
                                        >
                                            <CircleChevronRight class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="filteredBookingsList.length === 0">
                                <td colspan="11" class="px-6 py-16 text-center text-xs text-slate-500">
                                    <Plane class="mx-auto h-8 w-8 text-slate-300 mb-2 -rotate-45" />
                                    <p class="text-sm font-semibold text-slate-800">No bookings found</p>
                                    <p class="text-slate-400 mt-1">No bookings match the selected filters or search query.</p>
                                    <button
                                        v-if="activeFilter !== 'all' || tableSearch"
                                        @click="activeFilter = 'all'; tableSearch = ''; fetchBookings();"
                                        class="mt-3 inline-flex items-center rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
                                    >
                                        Clear Filters
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer -->
                <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-500 flex justify-between items-center">
                    <span>Showing {{ filteredBookingsList.length }} booking records</span>
                    <span class="font-medium text-slate-700">Filter: <span class="capitalize font-bold text-slate-900">{{ activeFilter }}</span></span>
                </div>
            </div>
        </div>

        <!-- Status Dialog -->
        <Dialog v-model:open="isStatusDialogOpen">
            <DialogContent class="sm:max-w-md rounded border border-slate-200 bg-white p-5 shadow-lg">
                <DialogHeader>
                    <DialogTitle class="text-base font-bold text-slate-900">Update Booking Status</DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Select a new status and confirm changes for booking #{{ statusForm.booking_id }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Status State
                        </label>
                        <div class="relative">
                            <select
                                v-model="statusForm.status"
                                class="w-full appearance-none rounded border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-900 shadow-2xs focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            >
                                <option disabled value="">Select status</option>
                                <option v-for="status in statusOptions" :key="status" :value="status">
                                    {{ status.toUpperCase() }}
                                </option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400 text-xs">
                                ▼
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="isStatusDialogOpen = false"
                        class="rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="requestBookingStatusUpdate"
                        :disabled="isStatusUpdating"
                        class="rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90 disabled:opacity-50 cursor-pointer"
                    >
                        {{ isStatusUpdating ? "Saving..." : "Save Changes" }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Negative Ledger Warning Dialog -->
        <Dialog v-model:open="isNegativeLedgerWarningOpen">
            <DialogContent class="sm:max-w-md rounded border border-rose-200 bg-white p-5 shadow-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-base font-bold text-rose-700">
                        <AlertTriangle class="h-5 w-5 text-rose-600" />
                        {{ ledgerWarningType === "negative" ? "Negative Ledger Balance" : "Insufficient Balance" }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500">
                        Review customer balance before setting booking to {{ statusForm.status.toUpperCase() }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="rounded border border-rose-200 bg-rose-50/70 p-3.5 my-2">
                    <p v-if="ledgerWarningType === 'negative'" class="text-xs font-bold text-rose-900" dir="ltr">
                        Customer Ledger Balance: -AED {{ formatAmount(Math.abs(selectedCustomerLedgerBalance || 0)) }}
                    </p>
                    <template v-else>
                        <p class="text-xs font-bold text-rose-900" dir="ltr">
                            Customer Ledger Balance: AED {{ formatAmount(selectedCustomerLedgerBalance || 0) }}
                        </p>
                        <p class="mt-1 text-xs font-bold text-rose-900" dir="ltr">
                            Required Amount: AED {{ formatAmount(Number(statusForm.required_aed) || 0) }}
                        </p>
                    </template>
                    <p class="mt-2 text-xs text-rose-700">
                        {{ ledgerWarningType === "negative"
                            ? "This customer’s ledger is currently in negative standing."
                            : "This customer’s available balance is not sufficient to cover this booking." }}
                        Are you sure you wish to continue and set this booking to {{ statusForm.status.toUpperCase() }}?
                    </p>
                </div>

                <DialogFooter class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        @click="isNegativeLedgerWarningOpen = false"
                        class="rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        @click="continueWithNegativeLedger"
                        :disabled="isStatusUpdating"
                        class="rounded bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50 cursor-pointer"
                    >
                        {{ isStatusUpdating ? "Updating..." : "Continue & Update Status" }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
