<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, Search } from "lucide-vue-next";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import { calculateLayover, formatDateTime } from "@/lib/utils";

import { useStore } from "vuex";
import { computed, onMounted, ref, watch } from "vue";
import { formatAmount } from "@/lib/utils";

import { useAuthStore } from "@/services/stores/auth";
import { useRouter } from "vue-router";

import {
    FETCH_BOOKING_DATA,
    FETCH_AGENT_DATA,
    FETCH_AGENT_LEDGER,
    FETCH_BOOKINGS,
    FETCH_DEPOSIT_DATA,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";

const store = useStore();
const authStore = useAuthStore();
const router = useRouter();
const loading = ref(true);
const error = ref(null);
const startDate = ref();
const endDate = ref();

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const bookings = computed(() => store.getters["flight/bookings"]);


const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const isDepositDialogOpen = ref(false);
const selectedDeposit = ref(null);
const isDepositLoading = ref(false);

function normalizeFlightProvider(provider) {
    if (!provider) return null;
    const normalized = String(provider).toLowerCase();
    if (normalized === "oneapi") return "OneApi";
    return normalized;
}

function getBookingRouteName() {
    return user.value?.role === "customer" ? "BookingsDetails" : "AgentBookingDetails";
}

function getReferenceLabel(transaction) {
    if (!transaction) return "-";
    const ref = transaction.reference_id || transaction.record_id || "-";
    switch ((transaction.transaction_type || "").toLowerCase()) {
        case "booking":
            return `FL-${ref}`;
        case "deposit":
            return `DP-${ref}`;
        case "offline_booking":
            return `OFF-${ref}`;
        case "direct_booking":
            return `DIR-${ref}`;
        default:
            return String(ref);
    }
}

function getDetailsLabel(transaction) {
    if (!transaction) return "-";
    const type = (transaction.transaction_type || "").toLowerCase();
    if (type === "booking" || type === "manually_issued") {
        const pnr = transaction.pnr_ref || transaction.details || transaction.reference_id;
        return `Flight booking details (${pnr || "-"})`;
    }
    if (type === "deposit") {
        return `Deposit details (${transaction.reference_id || transaction.record_id || "-"})`;
    }
    if (type === "offline_booking") {
        return `Offline booking (${transaction.pnr_ref || transaction.reference_id || "-"})`;
    }
    if (type === "direct_booking") {
        return `Direct booking (${transaction.pnr_ref || transaction.reference_id || "-"})`;
    }
    return transaction.details || "-";
}

function getReferenceRoute(transaction) {
    if (!transaction) return null;
    const type = (transaction.transaction_type || "").toLowerCase();

    if (type === "booking" || type === "manually_issued") {
        const flightProvider = normalizeFlightProvider(transaction.flight_provider);
        if (!flightProvider) return null;

        return {
            name: getBookingRouteName(),
            query: {
                booking_id: transaction.reference_id,
                pnr: transaction.pnr_ref || transaction.details || "",
                booking_source: transaction.booking_source ?? 1,
                flight_provider: flightProvider,
                flight_mode: user.value?.role === "customer" ? "B2C" : "B2B",
            },
        };
    }

    return null;
}

function canOpenReference(transaction) {
    if (!transaction) return false;
    const type = (transaction.transaction_type || "").toLowerCase();
    return type === "deposit" || type === "booking" || type === "manually_issued";
}

async function openDepositDialog(transaction) {
    if (!user_id.value) return;

    isDepositLoading.value = true;
    selectedDeposit.value = null;
    isDepositDialogOpen.value = true;

    try {
        await store.dispatch("deposit/" + FETCH_DEPOSIT_DATA, { userId: user_id.value });
        const deposits = store.getters["deposit/depositData"]?.deposits || [];

        selectedDeposit.value =
            deposits.find((d) => String(d.id) === String(transaction.record_id)) ||
            deposits.find((d) => String(d.receipt_reference) === String(transaction.reference_id)) ||
            null;
    } finally {
        isDepositLoading.value = false;
    }
}

function closeDepositDialog() {
    isDepositDialogOpen.value = false;
    selectedDeposit.value = null;
}

function openReferenceLink(transaction) {
    const type = (transaction?.transaction_type || "").toLowerCase();
    if (type === "deposit") {
        openDepositDialog(transaction);
        return;
    }

    const to = getReferenceRoute(transaction);
    if (!to) return;
    router.push(to);
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

function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch("ledger/" + FETCH_AGENT_LEDGER, {
                userId: user_id.value,
                startDate: startDate.value,
                endDate: endDate.value,
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

function fetchBookingsData() {
    if (!user_id.value) {
        error.value = "No user ID provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        userId: user_id.value,
        user_role: user.value.role,
    });
}

const parseFlightData = (flightDataString) => {
    try {
        return JSON.parse(flightDataString);
    } catch (error) {
        console.error("Error parsing flight data:", error);
        return null;
    }
};
const formatTime = (timeString) => {
    try {
        const date = new Date(timeString);
        return new Intl.DateTimeFormat("en-US", {
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        }).format(date);
    } catch (error) {
        return timeString;
    }
};

const formatDuration = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
};
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const formatBaggage = (baggage) => {
    if (baggage.pieces) {
        return `${baggage.pieces} piece${baggage.pieces > 1 ? "s" : ""}`;
    } else if (baggage.weight) {
        return `${baggage.weight}${baggage.unit || "kg"}`;
    }
    return "No baggage information";
};
const formatCurrency = (value) => {
    return parseFloat(value).toLocaleString("en-US", {
        style: "currency",
        currency: "PKR",
    });
};
const capitalize = (string) => {
    return string.charAt(0).toUpperCase() + string.slice(1);
};
const formatTransactionType = (transactionType) => {
    if (!transactionType) return "-";
    if (transactionType === "manually_issued") return "Manually Issued";
    return capitalize(transactionType.replaceAll("_", " "));
};
watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
       
        fetchAgentLedger();
    }
});
// Handle print functionality
const printLedger = () => {
  window.print();
}
onMounted(() => {
  
    if (user.value?.id) {
        fetchAgent();
        fetchAgentLedger();
    }
});
</script>

<template>
   <div>
    <!-- Main content that won't be printed directly -->
    <div class="non-printable">
      <!-- Your other page content goes here -->
    </div>

    <!-- This section will be used for printing -->
    <div class="container mx-auto px-4 py-8 print-landscape" id="print-section">
      <!-- Header Section -->
      <header class="mb-8 print:mb-6">
        <div class="flex flex-col md:flex-row items-center justify-between">
          <div class="flex items-center mb-4 md:mb-0">
            
          
            <div>
              <img src="/public/assets/logo.png" alt="Company Logo" class="w-88 h-[90px] rounded-md" />

              <h1 class="text-2xl font-bold text-gray-800">{{ agentData?.agent_data?.company_name }}</h1>
              <p class="text-gray-600">{{ agentData?.agent_data?.phone }}</p>
              <p class="text-gray-600">{{ agentData?.agent_data?.company_email }}</p>
            </div>
          </div>
          <button 
            @click="printLedger"
            class="flex items-center px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/50 transition-colors print:hidden"
          >
            Print Ledger
          </button>
        </div>
      </header>
      <!-- Date Filter Section - Added -->
      <div class="mb-6 print:hidden">
        <div class="flex flex-col md:flex-row gap-4 items-end">
          <div class="w-full md:w-auto">
            <label for="start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input 
            v-model="startDate"
              type="date" 
              id="start-date"
              class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
            />
          </div>
          <div class="w-full md:w-auto">
            <label for="end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input 
            v-model="endDate"
              type="date" 
              id="end-date"
              class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary"
            />
          </div>
          <button @click="fetchAgentLedger"
            class="w-full md:w-auto flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/50 transition-colors"
          >
            <Search class="w-4 h-4" />
            Search
          </button>
        </div>
      </div>
     

      <!-- Ledger Section -->
      <section>
        <div>
          <div class="flex items-center justify-between mb-4">
            <span class="text-3xl font-medium">Ledgers</span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 shadow-md rounded-lg">
              <thead>
                <tr class="text-left bg-gray-100">
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Date
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Transaction Type
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Reference ID
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Details
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Debit
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Credit
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Balance
                  </th>
                </tr>
              </thead>
              <tbody>
                <!-- Loop through the agentLedger data -->
                <tr
                  v-for="(transaction, index) in agentLedger?.ledger"
                  :key="index"
                  class="border-t"
                >
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ formatDate(transaction.date) }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    <div>{{ formatTransactionType(transaction?.transaction_type) }}</div>
                    <p v-if="transaction?.transaction_type === 'manually_issued'"
                      class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                      Manually issued (no ledger deduction)
                    </p>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    <button
                      v-if="canOpenReference(transaction)"
                      @click="openReferenceLink(transaction)"
                      class="text-primary hover:underline font-medium"
                    >
                      {{ getReferenceLabel(transaction) }}
                    </button>
                    <span v-else>
                      {{ getReferenceLabel(transaction) }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    <div class="flex items-center gap-2">
                      <span>{{ getDetailsLabel(transaction) }}</span>
                      <button
                        v-if="canOpenReference(transaction)"
                        @click="openReferenceLink(transaction)"
                        class="text-xs text-primary hover:underline"
                      >
                        View
                      </button>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-sm text-red-600">
                    <!-- Display Debit value, if 0 show '-' -->
                    {{
                      transaction.debit !== "0"
                        ? formatAmount(transaction.debit)
                        : "-"
                    }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-600">
                    <!-- Display Credit value, if 0 show '-' -->
                    {{
                      transaction?.credit !== "0"
                        ? formatAmount(transaction?.credit)
                        : "-"
                    }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ formatAmount(transaction.balance) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
      <footer class="print-footer hidden print:block mt-8 pt-4 border-t border-gray-200">
        <div class="flex flex-col md:flex-row justify-between items-center text-sm text-gray-600">
          <div class="mb-2 md:mb-0">
            <p>Jetze Ledger</p>
          </div>
          <div class="text-right">
            <p class="print-page-number"></p>
          </div>
        </div>
      </footer>
    </div>

    <div
      v-if="isDepositDialogOpen"
      class="fixed inset-0 z-50 overflow-y-auto print:hidden"
      aria-labelledby="deposit-dialog-title"
      role="dialog"
      aria-modal="true"
    >
      <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDepositDialog"></div>
        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <h3 id="deposit-dialog-title" class="mb-4 text-2xl font-extrabold text-gray-900">
              Deposit Details
            </h3>

            <div v-if="isDepositLoading" class="py-8 text-center text-sm text-gray-500">
              Loading deposit details...
            </div>

            <template v-else-if="selectedDeposit">
              <img
                v-if="selectedDeposit?.receipt_image && selectedDeposit.receipt_image !== '/storage/'"
                :src="selectedDeposit.receipt_image"
                alt="Receipt"
                class="mb-4 w-full rounded"
              />

              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="font-medium text-gray-500">Date</p>
                  <p class="font-bold text-gray-900">{{ selectedDeposit?.date || "-" }}</p>
                </div>
                <div>
                  <p class="font-medium text-gray-500">Receipt Ref.</p>
                  <p class="font-bold text-gray-900">{{ selectedDeposit?.receipt_reference || "-" }}</p>
                </div>
                <div>
                  <p class="font-medium text-gray-500">Amount</p>
                  <p class="font-bold text-gray-900">{{ formatAmount(selectedDeposit?.amount || 0) }}</p>
                </div>
                <div>
                  <p class="font-medium text-gray-500">Payment Type</p>
                  <p class="font-bold text-gray-900">{{ selectedDeposit?.payment_type || "-" }}</p>
                </div>
                <div>
                  <p class="font-medium text-gray-500">Status</p>
                  <p
                    :class="{
                      'text-yellow-700': selectedDeposit?.deposit_status === 'pending',
                      'text-gray-600': selectedDeposit?.deposit_status === 'approved',
                      'text-red-600': selectedDeposit?.deposit_status === 'rejected',
                    }"
                    class="font-bold uppercase"
                  >
                    {{ selectedDeposit?.deposit_status || "-" }}
                  </p>
                </div>
              </div>

              <div class="mt-4">
                <p class="font-medium text-gray-500">Additional Details</p>
                <p class="text-gray-700">{{ selectedDeposit?.additional_details || "-" }}</p>
              </div>
            </template>

            <div v-else class="py-8 text-center text-sm text-gray-500">
              Deposit details not found.
            </div>
          </div>

          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              @click="closeDepositDialog"
              class="inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 sm:w-auto"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
/* Regular styles */


.print-landscape {
  width: 100%;
}

/* Focus styles for inputs */
.focus\:outline-none:focus {
  outline: none;
}

.focus\:ring-primary:focus {
  --tw-ring-color: #3b82f6;
  --tw-ring-opacity: 1;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, var(--tw-ring-opacity));
}

.focus\:border-primary:focus {
  border-color: #3b82f6;
}
/* Print-specific styles */
@media print {
  body * {
    visibility: hidden !important;
  }

  #print-section,
  #print-section * {
    visibility: visible !important;
  }

  #print-section {
    position: absolute;
    left: 0;
    top: 0;
    width: 100% !important;
    background: white !important;
  }
  
  /* Hide print button when printing */
  .print\:hidden {
    display: none !important;
  }
  
  .print\:mb-6 {
    margin-bottom: 1.5rem;
  }
  
  /* Table print styles */
  table {
    break-inside: auto;
    width: 100%;
  }
  
  tr {
    break-inside: avoid;
    break-after: auto;
  }
  
  thead {
    display: table-header-group;
  }
  
  tfoot {
    display: table-footer-group;
  }
  
  /* Set landscape orientation */
  @page {
    size: landscape;
    margin: 1cm;
  }
  /* Footer styles */
  .print-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    padding: 0.5cm 1cm;
    border-top: 1px solid #e5e7eb;
    background-color: white;
  }
}
</style>
