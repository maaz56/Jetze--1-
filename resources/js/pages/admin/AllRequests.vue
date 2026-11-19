<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { CircleChevronRight, Inbox, RefreshCw, Search, Copy, Check, Filter } from "lucide-vue-next";
import apiService from "@/services/store/apiService";
import { formatRequestDate } from "@/lib/bookingRequests";

const router = useRouter();
const requests = ref([]);
const isLoading = ref(false);
const errorMessage = ref("");
const searchQuery = ref("");
const statusFilter = ref("all");
const copiedPnr = ref("");

const totalLabel = computed(() => `${requests.value.length} request${requests.value.length === 1 ? "" : "s"}`);

const requestReference = (booking, bookingId) =>
    booking?.itinerary_ref || booking?.pnr || `Booking #${bookingId}`;

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

async function fetchAllBookingRequests(page = 1, collected = []) {
    const response = await apiService.fetchBookingRequests({ status: "all", type: "all", per_page: 100, page });
    const paginated = response.data?.data;
    const next = [...collected, ...(paginated?.data || [])];

    return paginated?.current_page < paginated?.last_page
        ? fetchAllBookingRequests(page + 1, next)
        : next;
}

async function loadRequests() {
    isLoading.value = true;
    errorMessage.value = "";

    try {
        const [bookingRequestsResponse, modifyRequestsResponse] = await Promise.all([
            fetchAllBookingRequests(),
            apiService.fetchRequests(),
        ]);

        const bookingRequests = bookingRequestsResponse;
        const modifyRequests = modifyRequestsResponse.data?.data || [];

        requests.value = [
            ...bookingRequests.map((request) => ({
                key: `booking-${request.id}`,
                id: request.id,
                type: request.type,
                status: request.status,
                createdAt: request.created_at,
                booking: request.booking,
                bookingId: request.booking_id,
                customer: request.requester,
                detail: request.customer_remarks,
                adminSeenAt: request.admin_seen_at,
            })),
            ...modifyRequests.map((request) => ({
                key: `modify-${request.id}`,
                id: request.id,
                type: "modify",
                status: request.status,
                createdAt: request.created_at,
                booking: request.booking,
                bookingId: request.booking_id,
                customer: request.user,
                detail: request.message || request.reason,
                adminSeenAt: request.admin_seen_at,
            })),
        ].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    } catch (error) {
        errorMessage.value = error.response?.data?.message || "Unable to load requests.";
    } finally {
        isLoading.value = false;
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
            const ref = requestReference(r.booking, r.bookingId).toLowerCase();
            const cust = `${r.customer?.name || ""} ${r.customer?.email || ""}`.toLowerCase();
            const type = (r.type || "").toLowerCase();
            const detail = (r.detail || "").toLowerCase();
            return ref.includes(q) || cust.includes(q) || type.includes(q) || detail.includes(q);
        });
    }

    return list;
});

function openBooking(request) {
    if (!request.booking) return;

    if (!request.adminSeenAt) {
        request.adminSeenAt = new Date().toISOString();
        const type = request.type === "modify" ? "modify_requests" : "booking_requests";
        apiService.markAdminSeen(type, request.id).catch(() => {});
    }

    router.push({
        name: "AdminCustomerBookingsLayout",
        query: {
            booking_id: request.booking.id,
            pnr: request.booking.itinerary_ref || request.booking.pnr,
            agent_id: request.booking.agent_id,
            flight_mode: "B2C",
            booking_source: request.booking.booking_source,
            flight_provider: request.booking.flight_provider,
        },
    });
}

onMounted(loadRequests);
</script>

<template>
    <div class="space-y-4 p-5">
        <!-- Subheader & Controls -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
            <div>
                <h1 class="text-sm font-bold text-slate-900 sm:text-base">All Operational Requests</h1>
                <p class="text-xs text-slate-500 mt-0.5">{{ totalLabel }} across ticket voids, refunds, and segment modifications.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Search Input -->
                <div class="relative w-full sm:w-64">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Search PNR, customer, type..."
                        class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                    />
                </div>

                <!-- Refresh Button -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                    :disabled="isLoading"
                    @click="loadRequests"
                >
                    <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isLoading }" />
                    <span class="hidden sm:inline">Sync</span>
                </button>
            </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="rounded border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
            {{ errorMessage }}
        </div>

        <!-- Filter Pills Toolbar -->
        <div class="flex items-center gap-1.5">
            <button
                @click="statusFilter = 'all'"
                class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                :class="statusFilter === 'all' ? 'bg-primary text-white shadow-2xs' : 'bg-slate-50 text-slate-700 border border-slate-200 hover:bg-slate-100'"
            >
                All Statuses
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
                            <th scope="col" class="px-4 py-3">Requested At</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="px-4 py-3">PNR / Reference</th>
                            <th scope="col" class="px-4 py-3">Customer</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Details / Reason</th>
                            <th scope="col" class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-if="isLoading">
                            <td colspan="7" class="px-4 py-16 text-center text-slate-500">
                                <RefreshCw class="mr-2 inline h-4 w-4 animate-spin text-primary" /> Loading requests...
                            </td>
                        </tr>
                        <tr v-else-if="filteredRequests.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center text-slate-500">
                                <Inbox class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                                <p class="text-sm font-semibold text-slate-800">No requests found</p>
                                <p class="text-slate-400 mt-1">No requests match the selected filters or search query.</p>
                            </td>
                        </tr>
                        <tr
                            v-else
                            v-for="request in filteredRequests"
                            :key="request.key"
                            class="transition-colors hover:bg-slate-50/80"
                            :class="{ 'row-unseen': !request.adminSeenAt }"
                        >
                            <!-- Date -->
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                {{ formatRequestDate(request.createdAt) }}
                            </td>

                            <!-- Type -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                    :class="{
                                        'bg-blue-50 text-blue-700 border border-blue-200': request.type === 'refund',
                                        'bg-amber-50 text-amber-700 border border-amber-200': request.type === 'modify',
                                        'bg-purple-50 text-purple-700 border border-purple-200': request.type === 'void',
                                        'bg-slate-100 text-slate-700 border border-slate-200': !['refund', 'modify', 'void'].includes(request.type),
                                    }"
                                >
                                    {{ request.type }}
                                </span>
                            </td>

                            <!-- Reference / PNR -->
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800 border border-slate-200">
                                        {{ requestReference(request.booking, request.bookingId) }}
                                    </span>
                                    <button
                                        v-if="request.booking?.pnr || request.booking?.itinerary_ref"
                                        @click="copyPnrToClipboard(request.booking?.itinerary_ref || request.booking?.pnr)"
                                        class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                                        title="Copy PNR"
                                    >
                                        <Check v-if="copiedPnr === (request.booking?.itinerary_ref || request.booking?.pnr)" class="h-3 w-3 text-emerald-600" />
                                        <Copy v-else class="h-3 w-3" />
                                    </button>
                                </div>
                            </td>

                            <!-- Customer -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <p class="font-bold text-slate-900">{{ request.customer?.name || '—' }}</p>
                                <p class="text-[11px] text-slate-500">{{ request.customer?.email || '—' }}</p>
                            </td>

                            <!-- Status -->
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                    :class="{
                                        'bg-amber-50 text-amber-700 border border-amber-200': (request.status || 'pending').toLowerCase() === 'pending',
                                        'bg-emerald-50 text-emerald-700 border border-emerald-200': (request.status || '').toLowerCase() === 'approved',
                                        'bg-rose-50 text-rose-700 border border-rose-200': (request.status || '').toLowerCase() === 'rejected',
                                        'bg-slate-100 text-slate-700 border border-slate-200': !['pending', 'approved', 'rejected'].includes((request.status || '').toLowerCase()),
                                    }"
                                >
                                    {{ request.status || 'pending' }}
                                </span>
                            </td>

                            <!-- Details / Message -->
                            <td class="max-w-xs px-4 py-3 text-xs text-slate-600">
                                <span class="line-clamp-2" :title="request.detail">{{ request.detail || '—' }}</span>
                            </td>

                            <!-- Action -->
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button
                                    v-if="request.booking"
                                    type="button"
                                    class="inline-flex items-center justify-center rounded p-1 text-slate-500 hover:bg-primary/10 hover:text-primary transition cursor-pointer"
                                    title="View Booking Details"
                                    @click="openBooking(request)"
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
                <span>Showing {{ filteredRequests.length }} requests</span>
                <span class="font-medium text-slate-700">Filter: <span class="capitalize font-bold text-slate-900">{{ statusFilter }}</span></span>
            </div>
        </div>
    </div>
</template>
