<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import moment from "moment";
import { FETCH_REQUESTS } from "@/services/store/actions.type";
import apiService from "@/services/store/apiService";

// Icons
import {
    CircleChevronRight,
    Search,
    RefreshCw,
    Copy,
    Check,
    ArrowRight,
    FilePenLine
} from "lucide-vue-next";

const store = useStore();
const router = useRouter();

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
    highlightRequestId: {
        type: [String, Number],
        default: null,
    },
});

const searchQuery = ref("");
const statusFilter = ref("all");
const copiedPnr = ref("");
const isRefreshing = ref(false);

const requests = computed(() => store.getters["modifyRequest/requests"] || []);

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

function fetchRequests() {
    store.dispatch("modifyRequest/" + FETCH_REQUESTS);
}

async function refreshAll() {
    isRefreshing.value = true;
    try {
        await fetchRequests();
    } finally {
        setTimeout(() => {
            isRefreshing.value = false;
        }, 400);
    }
}

const filteredRequests = computed(() => {
    let list = requests.value;

    if (statusFilter.value !== "all") {
        list = list.filter((r) => (r.status || "").toLowerCase() === statusFilter.value.toLowerCase());
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        list = list.filter((r) => {
            const pnr = (r.booking?.itinerary_ref || r.booking?.pnr || "").toLowerCase();
            const email = (r.booking?.agency_email || "").toLowerCase();
            const reason = (r.reason || "").toLowerCase();
            const msg = (r.message || "").toLowerCase();
            return pnr.includes(q) || email.includes(q) || reason.includes(q) || msg.includes(q);
        });
    }

    return list;
});

function openBookingDetails(request) {
    if (!request.admin_seen_at) {
        request.admin_seen_at = new Date().toISOString();
        apiService.markAdminSeen("modify_requests", request.id).catch(() => {});
    }

    router.push({
        name: "AdminCustomerBookingsLayout",
        query: {
            booking_id: request.booking.id,
            modify_request_id: request.id,
            pnr: request.booking.itinerary_ref || request.booking.pnr,
            agent_id: request.booking.agent_id,
            booking_source: request.booking.booking_source,
            flight_provider: request.booking.flight_provider,
            flight_mode: request.booking.booking_mode,
        },
    });
}

onMounted(() => {
    fetchRequests();
});
</script>

<template>
    <section :class="props.embedded ? 'space-y-4 p-5' : 'mx-auto max-w-7xl space-y-6 p-6'">
        <!-- Subheader & Controls -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
            <div>
                <h2 class="text-sm font-bold text-slate-900 sm:text-base">Modification Requests</h2>
                <p class="text-xs text-slate-500 mt-0.5">Manage customer route changes, date modifications, and re-ticketing inquiries</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search PNR, email, reason..."
                        class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>

                <!-- Refresh Button -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                    :disabled="isRefreshing"
                    @click="refreshAll"
                >
                    <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                    <span class="hidden sm:inline">Sync</span>
                </button>
            </div>
        </div>

        <!-- Filter Pills Toolbar -->
        <div class="flex items-center gap-1.5">
            <button
                @click="statusFilter = 'all'"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="statusFilter === 'all' ? 'bg-primary text-white shadow-2xs' : 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100'"
            >
                All
            </button>
            <button
                @click="statusFilter = 'pending'"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="statusFilter === 'pending' ? 'bg-amber-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
            >
                Pending
            </button>
            <button
                @click="statusFilter = 'approved'"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="statusFilter === 'approved' ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
            >
                Approved
            </button>
            <button
                @click="statusFilter = 'rejected'"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="statusFilter === 'rejected' ? 'bg-rose-600 text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100'"
            >
                Rejected
            </button>
        </div>

        <!-- Table Container -->
        <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <tr>
                            <th scope="col" class="px-4 py-3">Request Date</th>
                            <th scope="col" class="px-4 py-3">PNR / Ref</th>
                            <th scope="col" class="px-4 py-3">Supplier</th>
                            <th scope="col" class="px-4 py-3">Customer Email</th>
                            <th scope="col" class="px-4 py-3">Route</th>
                            <th scope="col" class="px-4 py-3">Travel Date</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Reason</th>
                            <th scope="col" class="px-4 py-3">Message</th>
                            <th scope="col" class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-if="filteredRequests.length === 0">
                            <td colspan="10" class="px-4 py-16 text-center text-slate-500">
                                <FilePenLine class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                                <p class="text-sm font-semibold text-slate-800">No modification requests found</p>
                                <p class="text-slate-400 mt-1">No requests match the current filter selection.</p>
                            </td>
                        </tr>

                        <tr
                            v-else
                            v-for="request in filteredRequests"
                            :key="request.id"
                            class="transition-colors hover:bg-slate-50/80"
                            :class="{
                                'bg-primary/5': String(request.id) === String(props.highlightRequestId),
                                'row-unseen': !request.admin_seen_at,
                            }"
                        >
                            <!-- Request Date -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ moment(request.created_at).format("DD MMM YYYY, hh:mm A") }}
                            </td>

                            <!-- PNR / Ref with Copy Action -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800 border border-slate-200">
                                        {{ request.booking?.itinerary_ref || request.booking?.pnr || '—' }}
                                    </span>
                                    <button
                                        v-if="request.booking?.itinerary_ref || request.booking?.pnr"
                                        @click="copyPnrToClipboard(request.booking?.itinerary_ref || request.booking?.pnr)"
                                        class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                                        title="Copy PNR"
                                    >
                                        <Check v-if="copiedPnr === (request.booking?.itinerary_ref || request.booking?.pnr)" class="h-3 w-3 text-emerald-600" />
                                        <Copy v-else class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>

                            <!-- Supplier -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[11px] font-semibold text-slate-700 uppercase">
                                    {{ parseFlightData(request.booking?.flight_data)?.provider?.name || request.booking?.flight_provider || 'GDS' }}
                                </span>
                            </td>

                            <!-- Customer Email -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                <span class="max-w-[140px] truncate block" :title="request.booking?.agency_email">
                                    {{ request.booking?.agency_email || '—' }}
                                </span>
                            </td>

                            <!-- Route -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-1 text-xs">
                                    <template v-if="request.booking?.booking_source == 1">
                                        <div v-for="(flight, i) in parseFlightData(request.booking?.flight_data)?.leg?.flights?.slice(0, 1)" :key="i" class="flex items-center gap-1">
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">{{ flight.from?.iata }}</span>
                                            <ArrowRight class="h-3 w-3 text-slate-400" />
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">{{ flight.to?.iata }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            Multi-leg
                                        </span>
                                    </template>
                                </div>
                            </td>

                            <!-- Travel Date -->
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                <template v-if="request.booking?.booking_source == 1">
                                    <div v-for="(flight, i) in parseFlightData(request.booking?.flight_data)?.leg?.flights?.slice(0, 1)" :key="i">
                                        {{ formatDate(flight.departure_at) }}
                                    </div>
                                </template>
                                <template v-else>
                                    <div v-for="date in parseFlightData(request.booking?.flight_data)?.dates?.slice(0, 1)" :key="date.departureDate">
                                        {{ formatDate(date.departureDate) }}
                                    </div>
                                </template>
                            </td>

                            <!-- Status -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                    :class="{
                                        'bg-amber-50 text-amber-700 border border-amber-200': request.status === 'pending',
                                        'bg-emerald-50 text-emerald-700 border border-emerald-200': request.status === 'approved',
                                        'bg-rose-50 text-rose-700 border border-rose-200': request.status === 'rejected',
                                        'bg-slate-100 text-slate-700 border border-slate-200': !['pending', 'approved', 'rejected'].includes(request.status),
                                    }"
                                >
                                    {{ request.status || 'pending' }}
                                </span>
                            </td>

                            <!-- Reason -->
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-700">
                                {{ request.reason || '—' }}
                            </td>

                            <!-- Message -->
                            <td class="max-w-xs px-4 py-3 text-slate-600">
                                <p class="truncate" :title="request.message">{{ request.message || '—' }}</p>
                            </td>

                            <!-- Actions -->
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center rounded p-1 text-slate-500 hover:bg-primary/10 hover:text-primary transition cursor-pointer"
                                    title="View Booking Details"
                                    @click="openBookingDetails(request)"
                                >
                                    <CircleChevronRight class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 text-xs text-slate-500 flex justify-between items-center">
                <span>Showing {{ filteredRequests.length }} modification requests</span>
                <span class="font-medium text-slate-700">Filter: <span class="capitalize font-bold text-slate-900">{{ statusFilter }}</span></span>
            </div>
        </div>
    </section>
</template>
