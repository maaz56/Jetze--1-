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

import {
  FETCH_BOOKING_DATA,
  FETCH_AGENT_DATA,
  FETCH_AGENT_LEDGER,
  FETCH_BOOKINGS,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";

const store = useStore();
const authStore = useAuthStore();
const loading = ref(true);
const error = ref(null);
const startDate = ref();
const endDate = ref();

const user = computed(() => authStore.user);
const user_role = computed(() => user.value?.role);
const agentData = computed(() => store.getters["user/agentData"]);
const bookings = computed(() => store.getters["flight/bookings"]);


const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

function fetchAdminLedger() {
  if (user_role.value) {
    try {
      store.dispatch("ledger/" + FETCH_AGENT_LEDGER, {
        userRole: user.value.role,
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

const capitalize = (string) => {
  return string.charAt(0).toUpperCase() + string.slice(1);
};
const formatTransactionType = (transactionType) => {
  if (!transactionType) return "-";
  if (transactionType === "manually_issued") return "Manually Issued";
  return capitalize(transactionType.replaceAll("_", " "));
};
watch(user_role, (newUserRole) => {
  if (newUserRole) {
    fetchAdminLedger();
  }
});
// Handle print functionality
const printLedger = () => {
  const printContent = document.getElementById("print-section").innerHTML;

  // Create a hidden print container
  const printContainer = document.createElement("div");
  printContainer.id = "print-container";
  printContainer.style.display = "none";
  printContainer.innerHTML = printContent;

  // Add landscape print styles to the container
  printContainer.classList.add("print-landscape");

  // Append the print container to the body
  document.body.appendChild(printContainer);

  // Show the print container and print
  printContainer.style.display = "block";
  window.print();

  // Hide and remove the print container after printing
  printContainer.style.display = "none";
  document.body.removeChild(printContainer);
}
onMounted(() => {

  if (user.value?.role) {

    fetchAdminLedger();
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
    <div class="container mx-auto px-4 py-8 print-landscape border" id="print-section">
      <!-- Header Section -->
      <header class="mb-8 print:mb-6">
        <div class="flex mx-auto justify-between items-center">
          <div class="flex items-center mb-4 md:mb-0">

            <div>
              <img src="/public/assets/logo.png" alt="Company Logo" class="w-24 rounded-md" />
              <h1 class="text-2xl font-bold text-gray-800">Jetze</h1>
              <span class="flex gap-4">
                <p class="text-gray-600">+971 52 901 3757</p>
                <p class="text-gray-600">+923111448111</p>
              </span>
              <p class="text-gray-600">support@Jetze.pk</p>
            </div>
          </div>
          <button @click="printLedger"
            class="px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-[#dbcaa4] transition-colors print:hidden">
            Print Ledger
          </button>
        </div>
      </header>
      <!-- Date Filter Section - Added -->
      <div class="mb-6 print:hidden">
        <div class="flex flex-col md:flex-row gap-4 items-end">
          <div class="w-full md:w-auto">
            <label for="start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input v-model="startDate" type="date" id="start-date"
              class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" />
          </div>
          <div class="w-full md:w-auto">
            <label for="end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input v-model="endDate" type="date" id="end-date"
              class="w-full md:w-48 px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary focus:border-primary" />
          </div>
          <button @click="fetchAdminLedger"
            class="w-full md:w-auto flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-primary/30 transition-colors">
            <Search class="w-4 h-4" />
            Search
          </button>
        </div>
      </div>

      <!-- Ledger Section -->
      <section>
        <div>
          <div class="flex items-center justify-between mb-4">
            <span class="text-3xl font-medium">Ledger</span>
            <span v-if="startDate && endDate" class="ml-4 text-base text-gray-600">
              (From {{ formatDate(startDate) }} to {{ formatDate(endDate) }})
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 shadow-md rounded-lg">
              <thead>
                <tr class="text-left bg-gray-100">
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Date
                  </th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">
                    Agent
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
                <tr v-for="(transaction, index) in agentLedger?.ledger" :key="index" class="border-t">
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ formatDate(transaction.date) }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ transaction?.agent?.agent_data?.agent_uid ?? '-' }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    <div>{{ formatTransactionType(transaction?.transaction_type) }}</div>
                    <p v-if="transaction?.transaction_type === 'manually_issued'"
                      class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">
                      Manually issued (no ledger deduction)
                    </p>
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ transaction.reference_id }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ transaction.details }}
                  </td>
                  <td class="px-6 py-4 text-sm text-red-600">
                    <!-- Display Debit value, if 0 show '-' -->
                    {{
                      transaction.credit !== "0"
                        ? formatAmount(transaction.credit)
                        : "-"
                    }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-600">
                    <!-- Display Credit value, if 0 show '-' -->
                    {{
                      transaction?.debit !== "0"
                        ? formatAmount(transaction?.debit)
                        : "-"
                    }}
                  </td>
                  <td class="px-6 py-4 text-sm text-gray-800">
                    {{ formatAmount(transaction.balance) }}
                  </td>
                </tr>
              </tbody>
              <!-- Only show the total balance in normal view, not in print -->
              <tfoot class="print:hidden">
                <tr class="bg-gray-50 font-semibold">
                  <td colspan="7" class="px-6 py-4 text-right text-gray-700">
                    Total Balance
                  </td>
                  <td class="px-6 py-4 text-gray-900">
                    {{ formatAmount(agentLedger?.balance ?? 0) }}
                  </td>
                </tr>
              </tfoot>
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
      <div class=" hidden print:block bg-gray-50 font-semibold mt-4">
        <p class="px-6 py-4 text-right text-gray-700">
          Total Balance:  {{ formatAmount(agentLedger?.balance ?? 0) }}
        </p>
      </div>
    </div>
  </div>
</template>

<style>


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

  /* Hide everything by default */
  body>*:not(#print-container) {
    display: none;
  }

  /* Show only the print container */
  #print-container {
    display: block !important;
    width: 100%;
    position: absolute;
    left: 0;
    top: 0;
  }

  /* Hide print button when printing */
  .print\:hidden {
    display: none !important;
  }

  .print\:mb-6 {
    margin-bottom:auto;
  }

  /* Table print styles */
  table {
    break-inside: auto;
    width: 100%;
    margin: 0.2cm 0 !important;
    border-spacing: 0;
  }

  th, td {
    padding: 0.2cm 0.3cm !important;
    white-space: nowrap !important;
    vertical-align: middle;
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
    margin: 0.5cm;
  }

  /* Footer styles */
  .print-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    padding: 0.2cm 0.5cm;
    border-top: 1px solid #e5e7eb;
    background-color: white;
  }
}
</style>
