<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";

// UI Components
import Button from "@/components/ui/button/Button.vue";
import Spinner from "@/components/common/Spinner.vue";
import DateRangePicker from "@/components/common/DateRangePicker.vue";

// Actions
import {
    FETCH_BOOKINGS,
    FETCH_TRANSACTIONS,
    FETCH_BOOKING_DATA
} from "@/services/store/actions.type";

// Icons
import {
    Calendar as CalendarIcon,
    CheckCircle as CheckCircleIcon,
    Ban,
    CirclePause,
    Search,
    ChevronRight,
    Plane,
    ArrowRight,
    Copy,
    Check,
    RefreshCw
} from "lucide-vue-next";

const store = useStore();
const router = useRouter();
const authStore = useAuthStore();

const searchQuery = ref("");
const dateRange = ref({
    start: null,
    end: null,
});

const bookings = computed(() => store.getters["flight/bookingData"]);
const isLoading = computed(() => store.getters["flight/isLoading"]);
const user = computed(() => authStore.user);
const emit = defineEmits(["filter-change"]);
const activeFilter = ref("all");
const copiedPnr = ref("");
const isRefreshing = ref(false);

function filterBookings(type) {
    activeFilter.value = type;
    fetchBookings();
}

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

function fetchBookings() {
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        user_role: user.value?.role,
        bookingFilter: activeFilter.value,
        searchQuery: searchQuery.value,
        dateRange: dateRange.value
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

onMounted(() => {
    fetchBookings();
});
</script>

<template>
    <section class="space-y-6">
        <!-- Page Header -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        B2B Agent Bookings
                    </h1>
                    <p class="text-xs text-slate-500 mt-1">
                        Monitor wholesale ticket issuance, agent bookings, itineraries, and PNR status
                    </p>
                </div>
                <button
                    @click="refreshAll"
                    :disabled="isRefreshing"
                    class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                >
                    <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                    <span>Sync</span>
                </button>
            </div>
        </div>

        <!-- KPI Filter Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <!-- All Bookings -->
            <div
                @click="filterBookings('all')"
                class="rounded border p-4 transition-all cursor-pointer"
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
                <span class="mt-1 block text-[11px] font-medium text-slate-500">Total B2B records</span>
            </div>

            <!-- Ticketed -->
            <div
                @click="filterBookings('ticketed')"
                class="rounded border p-4 transition-all cursor-pointer"
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
                <span class="mt-1 block text-[11px] font-medium text-emerald-700">Confirmed tickets</span>
            </div>

            <!-- On Hold -->
            <div
                @click="filterBookings('booked')"
                class="rounded border p-4 transition-all cursor-pointer"
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
                <span class="mt-1 block text-[11px] font-medium text-amber-700">Awaiting ticketing</span>
            </div>

            <!-- Total Canceled -->
            <div
                @click="filterBookings('canceled')"
                class="rounded border p-4 transition-all cursor-pointer"
                :class="activeFilter === 'canceled' ? 'border-rose-500 bg-rose-50/40 ring-1 ring-rose-500' : 'border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300'"
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

        <!-- Filter & Search Toolbar -->
        <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative w-full sm:w-64">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            @keyup.enter="fetchBookings"
                            type="text"
                            placeholder="Search PNR, agent, customer..."
                            class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>

                    <DateRangePicker @change="fetchBookings()" heading="Select Date Range" v-model="dateRange" />

                    <button
                        @click="fetchBookings"
                        class="rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-primary/90 transition cursor-pointer"
                    >
                        Search
                    </button>
                </div>

                <div class="flex items-center gap-1.5">
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
            </div>
        </div>

        <!-- Table Container -->
        <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div v-if="isLoading" class="flex items-center justify-center p-16">
                <Spinner color="#2563eb" />
            </div>

            <div v-else-if="bookings?.bookings?.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3">Booking Date</th>
                            <th scope="col" class="px-4 py-3">Booking ID</th>
                            <th scope="col" class="px-4 py-3">PNR</th>
                            <th scope="col" class="px-4 py-3">Agent Email</th>
                            <th scope="col" class="px-4 py-3">Supplier</th>
                            <th scope="col" class="px-4 py-3">Route</th>
                            <th scope="col" class="px-4 py-3">Passenger</th>
                            <th scope="col" class="px-4 py-3">Travel Date</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="booking in bookings?.bookings" :key="booking.id" class="transition-colors hover:bg-slate-50/80">
                            <!-- Booking Date -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                <div class="font-medium text-slate-900">
                                    {{ moment(booking.created_at).format("DD MMM YYYY") }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ moment(booking.created_at).format("HH:mm") }}
                                </div>
                            </td>

                            <!-- Booking ID -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-bold text-slate-800 border border-slate-200">
                                    {{ booking?.user?.agent_data?.agent_uid || booking?.user?.customer?.customer_uid }}_{{ booking.id + 1000 }}
                                </span>
                            </td>

                            <!-- PNR with Copy Action -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded border border-primary/30 bg-primary/5 px-2 py-0.5 font-mono text-xs font-bold text-primary">
                                        {{ booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr }}
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

                            <!-- Agent Email -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                <span class="max-w-[150px] truncate block" :title="booking.agency_email">
                                    {{ booking.agency_email || "—" }}
                                </span>
                            </td>

                            <!-- Supplier -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights?.slice(0, 1)" :key="flightIndex" class="flex items-center gap-1.5">
                                    <img v-if="flight?.marketing_carrier?.logo" :src="flight?.marketing_carrier?.logo" alt="Carrier Logo" class="h-5 w-5 object-contain" />
                                    <span class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ flight?.marketing_carrier?.name || parseFlightData(booking?.flight_data)?.provider?.name || 'GDS' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Route -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div v-if="booking?.booking_source == 1">
                                    <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights?.slice(0, 1)" :key="flightIndex" class="flex items-center gap-1">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            {{ flight?.from?.iata }}
                                        </span>
                                        <ArrowRight class="h-3 w-3 text-slate-400" />
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            {{ flight?.to?.iata }}
                                        </span>
                                    </div>
                                </div>
                                <div v-else>
                                    <span class="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800">
                                        Multi-leg
                                    </span>
                                </div>
                            </td>

                            <!-- Passenger -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                <div v-for="passenger in (booking.pessangers || booking.passengers || []).slice(0, 1)" :key="passenger.id">
                                    <span class="font-medium text-slate-900">
                                        {{ passenger.title }} {{ passenger.first_name }} {{ passenger.last_name }}
                                    </span>
                                </div>
                                <div v-if="(booking.pessangers || booking.passengers || []).length > 1" class="text-[10px] text-slate-500">
                                    +{{ (booking.pessangers || booking.passengers || []).length - 1 }} more
                                </div>
                            </td>

                            <!-- Travel Date -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
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
                                        'bg-rose-50 text-rose-700 border border-rose-200': booking.status === 'canceled',
                                        'bg-slate-100 text-slate-700 border border-slate-200': !['ticketed', 'booked', 'canceled'].includes(booking.status),
                                    }"
                                >
                                    {{ booking.status }}
                                    <span v-if="booking.is_manually_issued" class="text-[10px] ml-1 opacity-75">(manual)</span>
                                </span>
                            </td>

                            <!-- Action -->
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button
                                    @click="$router.push({
                                        name: 'AdminBookingsDetails',
                                        query: {
                                            booking_id: booking.id,
                                            pnr: booking?.itinerary_reference ?? booking?.itinerary_ref ?? booking.pnr,
                                            agent_id: booking.agent_id,
                                            booking_source: booking.booking_source,
                                            flight_provider: parseFlightData(booking?.flight_data)?.provider?.name || 'N/A',
                                            flight_mode: 'B2B',
                                        },
                                    })"
                                    class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:border-primary hover:text-primary transition cursor-pointer shadow-2xs"
                                >
                                    <span>Details</span>
                                    <ChevronRight class="h-3.5 w-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Empty State -->
            <div v-else class="p-16 text-center text-xs text-slate-500">
                <Plane class="mx-auto h-8 w-8 text-slate-300 mb-2 -rotate-45" />
                <p class="text-sm font-semibold text-slate-800">No bookings found</p>
                <p class="text-slate-400 mt-1">No bookings match the selected filters or date range.</p>
                <button
                    v-if="activeFilter !== 'all' || searchQuery || dateRange.start"
                    @click="activeFilter = 'all'; searchQuery = ''; dateRange = { start: null, end: null }; fetchBookings();"
                    class="mt-3 inline-flex items-center rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
                >
                    Reset Filters
                </button>
            </div>
        </div>
    </section>
</template>
