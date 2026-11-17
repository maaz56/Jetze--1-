<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { CircleChevronRight, Inbox, RefreshCw } from "lucide-vue-next";
import apiService from "@/services/store/apiService";
import { formatRequestDate } from "@/lib/bookingRequests";

const router = useRouter();
const requests = ref([]);
const isLoading = ref(false);
const errorMessage = ref("");

const totalLabel = computed(() => `${requests.value.length} request${requests.value.length === 1 ? "" : "s" }`);

const requestReference = (booking, bookingId) => booking?.itinerary_ref || booking?.pnr || `Booking #${bookingId}`;

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
                type: request.type,
                status: request.status,
                createdAt: request.created_at,
                booking: request.booking,
                bookingId: request.booking_id,
                customer: request.requester,
                detail: request.customer_remarks,
            })),
            ...modifyRequests.map((request) => ({
                key: `modify-${request.id}`,
                type: "modify",
                status: request.status,
                createdAt: request.created_at,
                booking: request.booking,
                bookingId: request.booking_id,
                customer: request.user,
                detail: request.message || request.reason,
            })),
        ].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
    } catch (error) {
        errorMessage.value = error.response?.data?.message || "Unable to load requests.";
    } finally {
        isLoading.value = false;
    }
}

function openBooking(request) {
    if (!request.booking) return;

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
    <div class="space-y-6 p-6">
        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900">All Requests</h1>
                <p class="mt-1 text-sm text-gray-500">{{ totalLabel }} across void, refund, and modification requests.</p>
            </div>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                @click="loadRequests"
            >
                <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': isLoading }" />
                Refresh
            </button>
        </div>

        <div v-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ errorMessage }}</div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="border-b border-gray-200 bg-gray-50/80 text-xs font-semibold uppercase tracking-wider text-gray-600">
                        <tr>
                            <th class="px-4 py-3.5">Requested At</th>
                            <th class="px-4 py-3.5">Type</th>
                            <th class="px-4 py-3.5">PNR / Ref</th>
                            <th class="px-4 py-3.5">Customer</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Details</th>
                            <th class="px-4 py-3.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="isLoading">
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">
                                <RefreshCw class="mr-2 inline h-4 w-4 animate-spin text-primary" /> Loading requests...
                            </td>
                        </tr>
                        <tr v-else-if="requests.length === 0">
                            <td colspan="7" class="px-4 py-16 text-center">
                                <Inbox class="mx-auto h-8 w-8 text-gray-300" />
                                <p class="mt-2 font-medium text-gray-700">No requests found</p>
                            </td>
                        </tr>
                        <tr v-for="request in requests" :key="request.key" class="transition hover:bg-gray-50/60">
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">{{ formatRequestDate(request.createdAt) }}</td>
                            <td class="px-4 py-3">
                                <span :class="[
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold uppercase',
                                    request.type === 'refund' ? 'bg-blue-100 text-blue-800' : request.type === 'modify' ? 'bg-amber-100 text-amber-800' : 'bg-purple-100 text-purple-800',
                                ]">{{ request.type }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900">{{ requestReference(request.booking, request.bookingId) }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800">{{ request.customer?.name || '—' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ request.customer?.email || '—' }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700">{{ request.status || 'pending' }}</span></td>
                            <td class="max-w-xs px-4 py-3 text-xs text-gray-600"><span class="line-clamp-2">{{ request.detail || '—' }}</span></td>
                            <td class="px-4 py-3 text-right">
                                <button v-if="request.booking" type="button" class="rounded-lg p-1.5 text-primary transition hover:bg-primary/10" title="View booking" @click="openBooking(request)">
                                    <CircleChevronRight class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
