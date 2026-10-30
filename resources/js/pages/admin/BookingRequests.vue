<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import apiService from "@/services/store/apiService";
import { toast } from "vue3-toastify";
import {
    CheckCircle2,
    XCircle,
    Clock,
    AlertTriangle,
    Search,
    Filter,
    CircleChevronRight,
    Check,
    X,
    Eye,
    RefreshCw,
} from "lucide-vue-next";
import {
    requestStatusStyle,
    statusLabel,
    formatMoney,
    formatRequestDate,
    bookingRef,
    paxName,
} from "@/lib/bookingRequests";

const router = useRouter();

const requests = ref([]);
const pagination = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 });
const counts = ref({ all: 0, pending: 0, processing: 0, approved: 0, rejected: 0, failed: 0, providers: [] });
const isLoading = ref(false);

const activeStatus = ref("pending");
const filterType = ref("all");
const filterProvider = ref("all");
const dateFrom = ref("");
const dateTo = ref("");
const searchQuery = ref("");

const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const showDetailsDialog = ref(false);
const activeRequest = ref(null);
const isSubmitting = ref(false);

const penaltyAmount = ref(0);
const adminRemarks = ref("");
const rejectRemarks = ref("");

const statusCards = [
    { key: "pending", label: "Pending", hint: "Awaiting Review", icon: Clock, text: "text-amber-700", iconColor: "text-amber-500", active: "border-amber-500 bg-amber-50/60 ring-1 ring-amber-500" },
    { key: "processing", label: "Processing", hint: "In Flight", icon: RefreshCw, text: "text-blue-700", iconColor: "text-blue-500", active: "border-blue-500 bg-blue-50/60 ring-1 ring-blue-500" },
    { key: "approved", label: "Approved", hint: "Completed", icon: CheckCircle2, text: "text-emerald-700", iconColor: "text-emerald-500", active: "border-emerald-500 bg-emerald-50/60 ring-1 ring-emerald-500" },
    { key: "rejected", label: "Rejected", hint: "Declined", icon: XCircle, text: "text-rose-700", iconColor: "text-rose-500", active: "border-rose-500 bg-rose-50/60 ring-1 ring-rose-500" },
    { key: "failed", label: "Failed", hint: "Retry Available", icon: AlertTriangle, text: "text-red-700", iconColor: "text-red-600", active: "border-red-600 bg-red-50/60 ring-1 ring-red-600" },
    { key: "all", label: "All", hint: "Total Requests", icon: Filter, text: "text-purple-700", iconColor: "text-purple-600", active: "border-purple-600 bg-purple-50/60 ring-1 ring-purple-600" },
];

const ticketAmount = computed(() => Number(activeRequest.value?.ticket_amount || 0));
const penaltyTooHigh = computed(() => Number(penaltyAmount.value || 0) > ticketAmount.value);
const computedRefundAmount = computed(() => Math.max(0, ticketAmount.value - Number(penaltyAmount.value || 0)));

const errorMessage = (err, fallback) =>
    err?.response?.data?.message || Object.values(err?.response?.data?.errors || {}).flat()[0] || fallback;

const canReview = (req) => ["pending", "failed"].includes(req.status);

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
            provider: filterProvider.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
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
        toast("Failed to load booking requests.", { type: "error" });
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
    fetchCounts();
}

function resetFilters() {
    filterType.value = "all";
    filterProvider.value = "all";
    dateFrom.value = "";
    dateTo.value = "";
    searchQuery.value = "";
    fetchRequests(1);
}

function openApproveModal(req) {
    activeRequest.value = req;
    penaltyAmount.value = 0;
    adminRemarks.value = "";
    showApproveDialog.value = true;
}

function openRejectModal(req) {
    activeRequest.value = req;
    rejectRemarks.value = "";
    showRejectDialog.value = true;
}

function openDetailsModal(req) {
    activeRequest.value = req;
    showDetailsDialog.value = true;
}

async function confirmApprove() {
    if (!activeRequest.value || isSubmitting.value || penaltyTooHigh.value) return;
    isSubmitting.value = true;
    try {
        const res = await apiService.approveBookingRequest(activeRequest.value.id, {
            penalty_amount: Number(penaltyAmount.value || 0),
            remarks: adminRemarks.value.trim() || null,
        });
        toast(res.data.message || "Request approved successfully.", { type: "success" });
        showApproveDialog.value = false;
        refresh();
    } catch (err) {
        toast(errorMessage(err, "Failed to approve request."), { type: "error" });
    } finally {
        isSubmitting.value = false;
    }
}

async function confirmReject() {
    if (!activeRequest.value || isSubmitting.value) return;
    if (!rejectRemarks.value.trim()) {
        toast("Please provide rejection remarks.", { type: "warning" });
        return;
    }
    isSubmitting.value = true;
    try {
        const res = await apiService.rejectBookingRequest(activeRequest.value.id, {
            remarks: rejectRemarks.value.trim(),
        });
        toast(res.data.message || "Request rejected successfully.", { type: "info" });
        showRejectDialog.value = false;
        refresh();
    } catch (err) {
        toast(errorMessage(err, "Failed to reject request."), { type: "error" });
    } finally {
        isSubmitting.value = false;
    }
}

function navigateToBookingDetails(req) {
    const booking = req.booking;
    if (!booking) return;
    router.push({
        name: "AdminCustomerBookingsLayout",
        query: {
            booking_id: booking.id,
            pnr: booking.itinerary_ref || booking.pnr,
            agent_id: booking.agent_id,
            flight_mode: "B2C",
            booking_source: booking.booking_source,
            flight_provider: booking.flight_provider,
        },
    });
}

onMounted(() => {
    fetchCounts();
    fetchRequests(1);
});
</script>

<template>
    <div class="p-6 max-w-7xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Void & Refund Requests</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Manage customer ticket cancellation and refund requests with safe two-step verification.
                </p>
            </div>
            <button
                @click="refresh"
                class="inline-flex items-center gap-2 px-3.5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 shadow-sm transition"
            >
                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
                Refresh
            </button>
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
        <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="relative w-full sm:w-64">
                    <Search class="w-4 h-4 text-gray-400 absolute left-3 top-3 pointer-events-none" />
                    <input
                        v-model="searchQuery"
                        @keyup.enter="fetchRequests(1)"
                        type="text"
                        maxlength="100"
                        placeholder="Search PNR, customer, pax..."
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
                <select
                    v-model="filterProvider"
                    @change="fetchRequests(1)"
                    class="py-2 px-3 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                >
                    <option value="all">All Providers</option>
                    <option v-for="provider in counts.providers || []" :key="provider" :value="provider">{{ provider }}</option>
                </select>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                <input
                    v-model="dateFrom"
                    @change="fetchRequests(1)"
                    type="date"
                    class="py-1.5 px-2.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
                <span class="text-gray-400 text-xs">to</span>
                <input
                    v-model="dateTo"
                    @change="fetchRequests(1)"
                    type="date"
                    class="py-1.5 px-2.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                />
                <button @click="resetFilters" class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-gray-800 transition" title="Reset filters">
                    Clear
                </button>
            </div>
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
                            <th class="px-4 py-3.5">Customer</th>
                            <th class="px-4 py-3.5">Provider</th>
                            <th class="px-4 py-3.5">Passengers</th>
                            <th class="px-4 py-3.5">Ticket Amount</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5">Reviewer</th>
                            <th class="px-4 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="isLoading">
                            <td colspan="10" class="px-4 py-12 text-center text-gray-500">
                                <div class="inline-flex items-center gap-2">
                                    <RefreshCw class="w-4 h-4 animate-spin text-primary" />
                                    Loading requests...
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="requests.length === 0">
                            <td colspan="10" class="px-4 py-16 text-center">
                                <div class="max-w-sm mx-auto text-center space-y-2">
                                    <Clock class="w-8 h-8 text-gray-300 mx-auto" />
                                    <p class="font-medium text-gray-700">No requests found</p>
                                    <p class="text-xs text-gray-400">There are no {{ activeStatus !== 'all' ? activeStatus : '' }} requests matching your current filter criteria.</p>
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
                                <div class="flex items-center gap-1.5">
                                    <span>{{ bookingRef(req.booking, req.booking_id) }}</span>
                                    <span v-if="req.booking_status_before" class="text-[10px] text-gray-400 font-normal">({{ statusLabel(req.booking_status_before) }})</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div class="font-medium text-gray-900">{{ req.requester?.name || 'N/A' }}</div>
                                <div class="text-gray-400 text-[11px]">{{ req.requester?.email }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs font-medium text-gray-700 capitalize">
                                {{ req.booking?.flight_provider || 'N/A' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                <span class="font-medium text-gray-800">{{ req.passengers?.length || 0 }}</span> pax
                                <div v-if="req.passengers?.length" class="text-[11px] text-gray-400 truncate max-w-[140px]" :title="req.passengers.map(paxName).join(', ')">
                                    {{ req.passengers[0].first_name }} {{ req.passengers[0].last_name }}
                                    <span v-if="req.passengers.length > 1">+{{ req.passengers.length - 1 }} more</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">
                                {{ formatMoney(req.ticket_amount, req.currency) }}
                                <div v-if="Number(req.penalty_amount) > 0" class="text-[11px] text-rose-600">
                                    -{{ formatMoney(req.penalty_amount) }} penalty
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span :class="['inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium', requestStatusStyle(req.status).badge]">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="requestStatusStyle(req.status).dot"></span>
                                    {{ req.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                <div v-if="req.reviewer">{{ req.reviewer.name }}</div>
                                <div v-if="req.reviewed_at" class="text-[10px] text-gray-400">{{ formatRequestDate(req.reviewed_at) }}</div>
                                <div v-if="!req.reviewer && !req.reviewed_at" class="text-gray-300">—</div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button @click="openDetailsModal(req)" class="p-1.5 text-gray-500 hover:text-gray-800 rounded-lg hover:bg-gray-100 transition" title="View Details">
                                        <Eye class="w-4 h-4" />
                                    </button>
                                    <button @click="navigateToBookingDetails(req)" class="p-1.5 text-primary rounded-lg hover:bg-primary/10 transition" title="Go to Booking Details">
                                        <CircleChevronRight class="w-4 h-4" />
                                    </button>
                                    <button
                                        v-if="canReview(req)"
                                        @click="openApproveModal(req)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition"
                                    >
                                        <Check class="w-3.5 h-3.5" />
                                        Approve
                                    </button>
                                    <button
                                        v-if="canReview(req)"
                                        @click="openRejectModal(req)"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition"
                                    >
                                        <X class="w-3.5 h-3.5" />
                                        Reject
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

        <!-- Approve modal -->
        <div v-if="showApproveDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-5 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2 text-emerald-700 font-bold text-lg">
                        <CheckCircle2 class="w-5 h-5" />
                        Approve {{ activeRequest?.type?.toUpperCase() }} Request
                    </div>
                    <button @click="showApproveDialog = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm border border-gray-100">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Booking / PNR:</span>
                        <span class="font-semibold text-gray-800">{{ bookingRef(activeRequest?.booking) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Flight Provider:</span>
                        <span class="font-medium text-gray-800 capitalize">{{ activeRequest?.booking?.flight_provider || 'N/A' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Requested By:</span>
                        <span class="font-medium text-gray-800">{{ activeRequest?.requester?.name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Passengers:</span>
                        <span class="font-medium text-gray-800 text-right">{{ activeRequest?.passengers?.map(paxName).join(', ') }}</span>
                    </div>
                    <div class="flex justify-between pt-1 border-t border-gray-200">
                        <span class="text-gray-700 font-semibold">Total Ticket Amount:</span>
                        <span class="font-bold text-gray-900">{{ formatMoney(activeRequest?.ticket_amount, activeRequest?.currency) }}</span>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-3 text-xs text-blue-800 space-y-1">
                    <p class="font-semibold flex items-center gap-1">
                        <AlertTriangle class="w-4 h-4 text-blue-600 flex-shrink-0" />
                        Internal cancellation
                    </p>
                    <p>
                        Approval updates the booking and passenger statuses. For wallet-paid bookings the refund amount is
                        credited to the customer's wallet ledger and the penalty is charged. Card-paid bookings get no wallet
                        entry, and no airline or provider call is made — handle those outside the system.
                    </p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">
                            Penalty Amount <span v-if="activeRequest?.currency">({{ activeRequest.currency }})</span>
                        </label>
                        <input
                            v-model.number="penaltyAmount"
                            type="number"
                            min="0"
                            :max="ticketAmount"
                            step="any"
                            placeholder="0.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        />
                        <p v-if="penaltyTooHigh" class="text-[11px] text-rose-600 mt-1">Penalty cannot be more than the ticket amount.</p>
                        <p v-else class="text-[11px] text-gray-400 mt-1">Deducted from the ticket price on void/refund.</p>
                    </div>

                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-sm">
                        <span class="text-emerald-800 font-medium">Refund Amount to Customer:</span>
                        <span class="text-emerald-900 font-bold text-base">{{ formatMoney(computedRefundAmount, activeRequest?.currency) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">Admin Remarks (Optional)</label>
                        <textarea
                            v-model="adminRemarks"
                            rows="2"
                            maxlength="1000"
                            placeholder="Remarks or reason for customer reference..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t">
                    <button @click="showApproveDialog = false" :disabled="isSubmitting" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button
                        @click="confirmApprove"
                        :disabled="isSubmitting || penaltyTooHigh"
                        class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm transition disabled:opacity-50 inline-flex items-center gap-2"
                    >
                        <RefreshCw v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                        Confirm Approval
                    </button>
                </div>
            </div>
        </div>

        <!-- Reject modal -->
        <div v-if="showRejectDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl space-y-5">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2 text-rose-700 font-bold text-lg">
                        <XCircle class="w-5 h-5" />
                        Reject {{ activeRequest?.type?.toUpperCase() }} Request
                    </div>
                    <button @click="showRejectDialog = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <p class="text-xs text-gray-500">
                    Rejecting this request keeps the booking and passenger statuses unchanged.
                </p>

                <div>
                    <label class="block text-xs font-semibold uppercase text-gray-600 mb-1">
                        Reason for Rejection <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        v-model="rejectRemarks"
                        rows="3"
                        maxlength="1000"
                        placeholder="Explain why this request is being rejected..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-rose-200 focus:border-rose-500"
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-3 border-t">
                    <button @click="showRejectDialog = false" :disabled="isSubmitting" class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">
                        Cancel
                    </button>
                    <button
                        @click="confirmReject"
                        :disabled="isSubmitting"
                        class="px-5 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg shadow-sm transition disabled:opacity-50 inline-flex items-center gap-2"
                    >
                        <RefreshCw v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                        Reject Request
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
                    <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Customer Remarks</span>
                    <p class="text-gray-700">{{ activeRequest.customer_remarks }}</p>
                </div>

                <div v-if="activeRequest?.admin_remarks" class="p-3 bg-gray-50 rounded-lg text-sm">
                    <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Admin Remarks</span>
                    <p class="text-gray-700">{{ activeRequest.admin_remarks }}</p>
                </div>

                <div v-if="activeRequest?.failure_reason" class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
                    <span class="text-xs font-semibold text-red-600 uppercase block mb-1">Failure Reason</span>
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
