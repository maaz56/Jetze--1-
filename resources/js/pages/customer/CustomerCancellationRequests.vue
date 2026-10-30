<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import apiService from "@/services/store/apiService";
import { toast } from "vue3-toastify";
import {
    Clock,
    CheckCircle2,
    XCircle,
    AlertTriangle,
    Ban,
    Search,
    Filter,
    CircleChevronRight,
    Plus,
    RefreshCw,
    X,
    Eye,
    Loader2,
} from "lucide-vue-next";
import {
    requestStatusStyle,
    statusLabel,
    SETTLED_PASSENGER_STATUSES,
    formatMoney,
    formatRequestDate,
    bookingRef,
    paxName,
} from "@/lib/bookingRequests";

const router = useRouter();

const requests = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const counts = ref({ all: 0, pending: 0, processing: 0, approved: 0, rejected: 0, failed: 0, cancelled: 0 });
const isLoading = ref(false);

const activeStatus = ref("all");
const filterType = ref("all");
const searchQuery = ref("");

const showNewRequestDialog = ref(false);
const showCancelDialog = ref(false);
const showDetailsDialog = ref(false);
const activeRequest = ref(null);
const isCancelling = ref(false);

const newReqPnr = ref("");
const newReqType = ref("void");
const newReqBooking = ref(null);
const newReqCurrency = ref(null);
const newReqPassengers = ref([]);
const newReqSelectedPax = ref([]);
const newReqRemarks = ref("");
const isSearchingPnr = ref(false);
const isSubmittingNew = ref(false);
const newReqError = ref("");

const statusCards = [
    { key: "all", label: "All", hint: "Total Requests", icon: Filter, text: "text-purple-700", iconColor: "text-purple-600", active: "border-purple-600 bg-purple-50/50 ring-1 ring-purple-600" },
    { key: "pending", label: "Pending", hint: "Under Review", icon: Clock, text: "text-amber-700", iconColor: "text-amber-500", active: "border-amber-500 bg-amber-50/50 ring-1 ring-amber-500" },
    { key: "processing", label: "Processing", hint: "In Progress", icon: RefreshCw, text: "text-blue-700", iconColor: "text-blue-500", active: "border-blue-500 bg-blue-50/50 ring-1 ring-blue-500" },
    { key: "approved", label: "Approved", hint: "Completed", icon: CheckCircle2, text: "text-emerald-700", iconColor: "text-emerald-500", active: "border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500" },
    { key: "rejected", label: "Rejected", hint: "Declined", icon: XCircle, text: "text-rose-700", iconColor: "text-rose-500", active: "border-rose-500 bg-rose-50/50 ring-1 ring-rose-500" },
    { key: "cancelled", label: "Cancelled", hint: "Withdrawn", icon: Ban, text: "text-gray-700", iconColor: "text-gray-500", active: "border-gray-500 bg-gray-50 ring-1 ring-gray-500" },
];

const errorMessage = (err, fallback) =>
    err?.response?.data?.message || Object.values(err?.response?.data?.errors || {}).flat()[0] || fallback;

const isSettled = (pax) => SETTLED_PASSENGER_STATUSES.includes(pax.status);

async function fetchCounts() {
    try {
        const res = await apiService.fetchBookingRequestCounts();
        counts.value = res.data;
    } catch (err) {
        console.error("Failed to load counts", err);
    }
}

async function fetchRequests(page = 1) {
    isLoading.value = true;
    try {
        const res = await apiService.fetchBookingRequests({
            page,
            status: activeStatus.value,
            type: filterType.value,
            search: searchQuery.value.trim() || undefined,
        });
        const data = res.data.data;
        requests.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            total: data.total,
            per_page: data.per_page,
        };
    } catch (err) {
        toast("Failed to load cancellation requests.", { type: "error" });
    } finally {
        isLoading.value = false;
    }
}

function refresh() {
    fetchCounts();
    fetchRequests(pagination.value.current_page);
}

function handleTabChange(status) {
    activeStatus.value = status;
    fetchRequests(1);
}

function resetFilters() {
    filterType.value = "all";
    searchQuery.value = "";
    fetchRequests(1);
}

function openCancelModal(req) {
    activeRequest.value = req;
    showCancelDialog.value = true;
}

async function confirmCancelRequest() {
    if (!activeRequest.value || isCancelling.value) return;
    isCancelling.value = true;
    try {
        const res = await apiService.cancelBookingRequest(activeRequest.value.id);
        toast(res.data.message || "Request cancelled.", { type: "info" });
        showCancelDialog.value = false;
        refresh();
    } catch (err) {
        toast(errorMessage(err, "Failed to cancel request."), { type: "error" });
    } finally {
        isCancelling.value = false;
    }
}

function openNewModal() {
    newReqPnr.value = "";
    newReqType.value = "void";
    newReqBooking.value = null;
    newReqCurrency.value = null;
    newReqPassengers.value = [];
    newReqSelectedPax.value = [];
    newReqRemarks.value = "";
    newReqError.value = "";
    showNewRequestDialog.value = true;
}

async function searchPnr() {
    const pnr = newReqPnr.value.trim();
    if (!pnr) {
        newReqError.value = "Please enter a PNR or Reference.";
        return;
    }
    newReqError.value = "";
    newReqBooking.value = null;
    newReqPassengers.value = [];
    newReqSelectedPax.value = [];
    isSearchingPnr.value = true;
    try {
        const res = await apiService.lookupBookingForRequest({ pnr });
        newReqBooking.value = res.data.booking;
        newReqCurrency.value = res.data.currency;
        newReqPassengers.value = res.data.passengers || [];
        newReqSelectedPax.value = newReqPassengers.value.filter((p) => !isSettled(p)).map((p) => p.id);
    } catch (err) {
        newReqError.value = errorMessage(err, "No booking found for this PNR.");
    } finally {
        isSearchingPnr.value = false;
    }
}

async function submitNewRequest() {
    if (!newReqBooking.value) return;
    if (newReqSelectedPax.value.length === 0) {
        newReqError.value = "Please select at least one passenger.";
        return;
    }
    newReqError.value = "";
    isSubmittingNew.value = true;
    try {
        const res = await apiService.submitBookingRequest({
            booking_id: newReqBooking.value.id,
            type: newReqType.value,
            passenger_ids: newReqSelectedPax.value,
            remarks: newReqRemarks.value.trim() || undefined,
        });
        toast(res.data.message || "Request submitted successfully.", { type: "success" });
        showNewRequestDialog.value = false;
        activeStatus.value = "all";
        fetchRequests(1);
        fetchCounts();
    } catch (err) {
        newReqError.value = errorMessage(err, "Failed to submit request.");
    } finally {
        isSubmittingNew.value = false;
    }
}

function openDetailsModal(req) {
    activeRequest.value = req;
    showDetailsDialog.value = true;
}

function navigateToBookingDetails(req) {
    const booking = req.booking;
    if (!booking) return;
    router.push({
        name: "BookingsDetails",
        query: {
            booking_id: booking.id,
            pnr: booking.itinerary_ref || booking.pnr,
            booking_source: booking.booking_source,
            flight_provider: booking.flight_provider,
            flight_mode: "B2C",
        },
    });
}

onMounted(() => {
    fetchCounts();
    fetchRequests(1);
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Cancellation & Refund Requests</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Submit ticket void or refund requests and track their status in real time.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="openNewModal"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg shadow-sm transition"
                >
                    <Plus class="w-4 h-4" />
                    New Request
                </button>
                <button
                    @click="refresh"
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm transition"
                >
                    <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                    Refresh
                </button>
            </div>
        </div>

        <!-- Status cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <button
                v-for="card in statusCards"
                :key="card.key"
                @click="handleTabChange(card.key)"
                :class="[
                    'p-4 rounded-xl border text-left transition-all',
                    activeStatus === card.key ? `${card.active} shadow-sm` : 'border-gray-200 bg-white hover:border-gray-300',
                ]"
            >
                <div class="flex items-center justify-between">
                    <span :class="['text-xs font-semibold uppercase tracking-wider', card.text]">{{ card.label }}</span>
                    <component :is="card.icon" :class="['w-4 h-4', card.iconColor]" />
                </div>
                <div class="text-2xl font-bold text-gray-900 mt-2">{{ counts[card.key] || 0 }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ card.hint }}</div>
            </button>
        </div>

        <!-- Filter bar -->
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-64">
                    <Search class="w-4 h-4 text-gray-400 absolute left-3 top-3 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="fetchRequests(1)"
                        type="text"
                        maxlength="100"
                        placeholder="Search PNR or pax name..."
                        class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    />
                </div>
                <select
                    v-model="filterType"
                    @change="fetchRequests(1)"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                >
                    <option value="all">All Types</option>
                    <option value="void">Void Only</option>
                    <option value="refund">Refund Only</option>
                </select>
            </div>
            <button @click="resetFilters" class="text-xs text-gray-500 hover:text-gray-800 transition">
                Reset Filters
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-700">
                    <thead class="bg-gray-50/80 border-b border-gray-200 text-xs font-semibold text-gray-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-4 py-3.5">Requested At</th>
                            <th class="px-4 py-3.5">Type</th>
                            <th class="px-4 py-3.5">PNR / Ref</th>
                            <th class="px-4 py-3.5">Provider</th>
                            <th class="px-4 py-3.5">Passengers</th>
                            <th class="px-4 py-3.5">Ticket Amount</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Refund / Penalty</th>
                            <th class="px-4 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="isLoading">
                            <td colspan="9" class="px-4 py-12 text-center text-gray-500">
                                <div class="inline-flex items-center gap-2">
                                    <RefreshCw class="w-4 h-4 animate-spin text-primary" />
                                    Loading requests...
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="requests.length === 0">
                            <td colspan="9" class="px-4 py-16 text-center">
                                <div class="max-w-sm mx-auto text-center space-y-2">
                                    <Clock class="w-8 h-8 text-gray-300 mx-auto" />
                                    <p class="font-medium text-gray-700">No requests found</p>
                                    <p class="text-xs text-gray-400">You haven't submitted any {{ activeStatus !== 'all' ? activeStatus : '' }} cancellation requests.</p>
                                </div>
                            </td>
                        </tr>
                        <tr v-else v-for="req in requests" :key="req.id" class="hover:bg-gray-50/60 transition">
                            <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                                <div>{{ formatRequestDate(req.created_at) }}</div>
                                <div v-if="req.customer_remarks" class="text-gray-400 italic mt-0.5 truncate max-w-[140px]" :title="req.customer_remarks">
                                    "{{ req.customer_remarks }}"
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold uppercase', req.type === 'refund' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800']">
                                    {{ req.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                {{ bookingRef(req.booking, req.booking_id) }}
                            </td>
                            <td class="px-4 py-3 text-xs font-medium text-gray-700 capitalize">
                                {{ req.booking?.flight_provider || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                <span class="font-medium text-gray-800">{{ req.passengers?.length || 0 }}</span> pax
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ formatMoney(req.ticket_amount, req.currency) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium', requestStatusStyle(req.status).badge]">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="requestStatusStyle(req.status).dot"></span>
                                    {{ req.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <div v-if="req.status === 'approved'">
                                    <div class="text-emerald-700 font-semibold">+ {{ formatMoney(req.refund_amount, req.currency) }}</div>
                                    <div v-if="Number(req.penalty_amount) > 0" class="text-rose-600 text-[11px]">- {{ formatMoney(req.penalty_amount, req.currency) }} penalty</div>
                                </div>
                                <div v-else-if="req.admin_remarks" class="text-gray-500 italic max-w-[140px] truncate" :title="req.admin_remarks">
                                    "{{ req.admin_remarks }}"
                                </div>
                                <div v-else class="text-gray-300">—</div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button @click="openDetailsModal(req)" class="p-1.5 text-gray-500 hover:text-gray-800 rounded-lg hover:bg-gray-100 transition" title="View Details">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button @click="navigateToBookingDetails(req)" class="p-1.5 text-primary rounded-lg hover:bg-primary/10 transition" title="View Booking">
                                        <CircleChevronRight class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="req.status === 'pending'"
                                        @click="openCancelModal(req)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition"
                                        title="Cancel this request"
                                    >
                                        <Ban class="w-3.5 h-3.5" />
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.total > 0" class="px-4 py-3 border-t border-gray-200 bg-gray-50/50 flex items-center justify-between text-xs text-gray-500">
                <div>
                    Showing <span class="font-medium text-gray-700">{{ ((pagination.current_page - 1) * pagination.per_page) + 1 }}</span>
                    to <span class="font-medium text-gray-700">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                    of <span class="font-medium text-gray-700">{{ pagination.total }}</span> results
                </div>
                <div class="flex items-center gap-2">
                    <button
                        :disabled="pagination.current_page <= 1"
                        @click="fetchRequests(pagination.current_page - 1)"
                        class="px-2.5 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none transition"
                    >
                        Previous
                    </button>
                    <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
                    <button
                        :disabled="pagination.current_page >= pagination.last_page"
                        @click="fetchRequests(pagination.current_page + 1)"
                        class="px-2.5 py-1 bg-white border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none transition"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel confirmation modal -->
        <div v-if="showCancelDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2 text-rose-700 font-bold text-lg">
                        <Ban class="w-5 h-5" />
                        Cancel Request
                    </div>
                    <button @click="showCancelDialog = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>
                <p class="text-sm text-gray-600">
                    Are you sure you want to cancel this {{ activeRequest?.type }} request for PNR <strong>{{ bookingRef(activeRequest?.booking) }}</strong>?
                </p>
                <div class="flex items-center justify-end gap-3 pt-3 border-t">
                    <button @click="showCancelDialog = false" :disabled="isCancelling" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                        Keep Request
                    </button>
                    <button
                        @click="confirmCancelRequest"
                        :disabled="isCancelling"
                        class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg inline-flex items-center gap-2"
                    >
                        <Loader2 v-if="isCancelling" class="w-4 h-4 animate-spin" />
                        Yes, Cancel Request
                    </button>
                </div>
            </div>
        </div>

        <!-- New request modal -->
        <div v-if="showNewRequestDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="font-bold text-gray-900 text-lg">Submit Void or Refund Request</div>
                    <button @click="showNewRequestDialog = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="flex gap-2">
                    <button
                        v-for="option in [{ value: 'void', label: 'Void Request' }, { value: 'refund', label: 'Refund Request' }]"
                        :key="option.value"
                        type="button"
                        @click="newReqType = option.value"
                        :class="[
                            'flex-1 py-2 text-xs font-semibold rounded-lg border transition',
                            newReqType === option.value ? 'bg-primary text-white border-primary' : 'bg-gray-50 text-gray-700 border-gray-200 hover:bg-gray-100',
                        ]"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold uppercase text-gray-600">Enter Booking PNR / Reference</label>
                    <div class="flex gap-2">
                        <input
                            v-model="newReqPnr"
                            @keyup.enter="searchPnr"
                            type="text"
                            maxlength="100"
                            placeholder="e.g. 7X3KQZ"
                            class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                        <button
                            type="button"
                            @click="searchPnr"
                            :disabled="isSearchingPnr"
                            class="px-4 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg inline-flex items-center gap-1.5 disabled:opacity-50"
                        >
                            <Loader2 v-if="isSearchingPnr" class="w-4 h-4 animate-spin" />
                            Search
                        </button>
                    </div>
                </div>

                <div v-if="newReqBooking" class="space-y-3 pt-2 border-t">
                    <div class="p-3 bg-gray-50 rounded-lg text-xs space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Provider:</span>
                            <span class="font-medium text-gray-800 capitalize">{{ newReqBooking.flight_provider || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Current Status:</span>
                            <span class="font-medium text-gray-800 capitalize">{{ statusLabel(newReqBooking.status) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold uppercase text-gray-600">Select Passengers</label>
                        <div class="border rounded-lg divide-y max-h-48 overflow-y-auto">
                            <label
                                v-for="p in newReqPassengers"
                                :key="p.id"
                                :class="['flex items-center justify-between p-2.5 text-xs', isSettled(p) ? 'opacity-60 cursor-not-allowed' : 'hover:bg-gray-50 cursor-pointer']"
                            >
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        :value="p.id"
                                        v-model="newReqSelectedPax"
                                        :disabled="isSettled(p)"
                                        class="rounded text-primary focus:ring-primary/20"
                                    />
                                    <span>{{ paxName(p) }}</span>
                                    <span v-if="p.status" class="px-1.5 py-0.5 rounded bg-gray-100 text-[10px] uppercase text-gray-600">{{ statusLabel(p.status) }}</span>
                                </div>
                                <span class="text-gray-500 font-medium">{{ formatMoney(p.amount, newReqCurrency) }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase text-gray-600">Remarks (Optional)</label>
                        <textarea
                            v-model="newReqRemarks"
                            rows="2"
                            maxlength="1000"
                            placeholder="Reason for cancellation..."
                            class="w-full text-xs px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                        ></textarea>
                    </div>
                </div>

                <div v-if="newReqError" class="p-3 rounded-lg bg-red-50 text-red-700 text-xs flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 shrink-0" />
                    {{ newReqError }}
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button @click="showNewRequestDialog = false" :disabled="isSubmittingNew" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">
                        Cancel
                    </button>
                    <button
                        v-if="newReqBooking"
                        @click="submitNewRequest"
                        :disabled="isSubmittingNew || newReqSelectedPax.length === 0"
                        class="px-5 py-2 text-sm font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg inline-flex items-center gap-2 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmittingNew" class="w-4 h-4 animate-spin" />
                        Submit Request
                    </button>
                </div>
            </div>
        </div>

        <!-- Details modal -->
        <div v-if="showDetailsDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="font-bold text-gray-900 text-lg">Request #{{ activeRequest?.id }} Details</div>
                    <button @click="showDetailsDialog = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 block">Type</span>
                        <span class="font-semibold uppercase text-gray-800">{{ activeRequest?.type }}</span>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 block">Status</span>
                        <span class="font-semibold uppercase text-gray-800">{{ activeRequest?.status }}</span>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 block">Ticket Amount</span>
                        <span class="font-bold text-gray-900">{{ formatMoney(activeRequest?.ticket_amount, activeRequest?.currency) }}</span>
                    </div>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <span class="text-xs text-gray-400 block">Refund Amount</span>
                        <span class="font-bold text-emerald-700">{{ formatMoney(activeRequest?.refund_amount, activeRequest?.currency) }}</span>
                    </div>
                </div>

                <div v-if="activeRequest?.customer_remarks" class="p-3 bg-gray-50 rounded-lg text-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Your Remarks</span>
                    <p class="text-gray-700">{{ activeRequest.customer_remarks }}</p>
                </div>

                <div v-if="activeRequest?.admin_remarks" class="p-3 bg-gray-50 rounded-lg text-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Admin Remarks</span>
                    <p class="text-gray-700">{{ activeRequest.admin_remarks }}</p>
                </div>

                <div v-if="activeRequest?.failure_reason" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                    <span class="text-xs font-semibold text-red-600 uppercase block mb-1">Status Reason</span>
                    <p>{{ activeRequest.failure_reason }}</p>
                </div>

                <div v-if="activeRequest?.passengers?.length > 0" class="space-y-1.5">
                    <span class="text-xs font-semibold text-gray-500 uppercase block">Selected Passengers</span>
                    <div class="border rounded-lg divide-y text-xs">
                        <div v-for="p in activeRequest.passengers" :key="p.id" class="p-2.5 flex items-center justify-between">
                            <span class="font-medium text-gray-800">
                                {{ paxName(p) }}
                                <span v-if="p.status" class="ml-1 px-1.5 py-0.5 rounded bg-gray-100 text-[10px] uppercase text-gray-600">{{ statusLabel(p.status) }}</span>
                            </span>
                            <span class="text-gray-500">{{ formatMoney(p.pivot?.amount, activeRequest.currency) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t">
                    <button @click="showDetailsDialog = false" class="px-4 py-2 text-sm font-medium bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
