<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, Search, CalendarIcon } from "lucide-vue-next";
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
  FETCH_PROFIT_LOSS_REPORT,
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
const profitLossReport = computed(() => store.getters["ledger/profitLossReport"]);

const grandTotal = computed(() => {
  const total = profitLossReport.value?.data?.reduce((sum, item) => {
    return sum + (parseFloat(item.total_agent_margin) || 0);
  }, 0) || 0;
  return Number(total);
});

const totals = computed(() => {
  return profitLossReport.value?.data?.reduce(
    (acc, item) => {
      acc.totalBookings += parseInt(item.total_bookings || 0);
      acc.totalRevenue += parseFloat(item.total_revenue || 0);
      acc.totalProfit += parseFloat(item.total_agent_margin || 0);
      acc.totalCost += (parseFloat(item.total_cost || 0) + 
                       parseFloat(item.total_airline_margin || 0) + 
                       parseFloat(item.total_profit || 0));
      return acc;
    },
    {
      totalBookings: 0,
      totalRevenue: 0,
      totalProfit: 0,
      totalCost: 0,
    }
  ) || {
    totalBookings: 0,
    totalRevenue: 0,
    totalProfit: 0,
    totalCost: 0,
  };
});

function fetchProfitLossReport() {
  if (user_role.value) {
    try {
      store.dispatch("ledger/" + FETCH_PROFIT_LOSS_REPORT, {
        agent_id: user.value?.id,
        startDate: startDate.value,
        endDate: endDate.value,
      });
      loading.value = false;
    } catch (err) {
      error.value = "Failed to load profit/loss data. Please try again.";
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

watch(user_role, (newUserRole) => {
  if (newUserRole) {
    fetchProfitLossReport();
  }
});

const printLedger = () => {
  const printContent = document.getElementById("print-section").innerHTML;
  const printContainer = document.createElement("div");
  printContainer.id = "print-container";
  printContainer.style.display = "none";
  printContainer.innerHTML = printContent;
  printContainer.classList.add("print-landscape");
  document.body.appendChild(printContainer);
  printContainer.style.display = "block";
  window.print();
  printContainer.style.display = "none";
  document.body.removeChild(printContainer);
};

onMounted(() => {
  if (user.value?.role) {
    fetchProfitLossReport();
  }
});
</script>

<template>
  <div>
    <div class="non-printable">
    </div>
    <div class="container mx-auto px-4 py-8 print-landscape border" id="print-section">
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
            Print Report
          </button>
        </div>
      </header>

      <section>
        <div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 print:hidden">
            <div
              class="relative overflow-hidden bg-primary/20 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
              <div class="z-10 flex items-center">
                <div>
                  <p class="text-sm font-medium text-primary">Total Profit</p>
                  <p class="text-3xl font-bold text-primary">{{ formatAmount(grandTotal) }}</p>
                </div>
              </div>
            </div>
            <div
              class="relative overflow-hidden bg-primary/20 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
              <div class="z-10 flex items-center">
                <div>
                  <p class="text-sm font-medium text-primary">Total Bookings</p>
                  <p class="text-3xl font-bold text-primary">{{ totals.totalBookings }}</p>
                </div>
              </div>
            </div>
            <div
              class="relative overflow-hidden bg-primary/20 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
              <div class="z-10 flex items-center">
                <div>
                  <p class="text-sm font-medium text-primary">Total Revenue</p>
                  <p class="text-3xl font-bold text-primary">{{ formatAmount(totals.totalRevenue) }}</p>
                </div>
              </div>
            </div>
          </div>

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
              <button @click="fetchProfitLossReport"
                class="w-full md:w-auto flex items-center justify-center gap-2 px-4 py-2 bg-primary text-white rounded-md shadow hover:bg-[#dbcaa4] transition-colors">
                <Search class="w-4 h-4" />
                Search
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between mb-4">
            <span class="text-3xl font-medium">Profit/Loss Report</span>
            <span v-if="startDate && endDate" class="ml-4 text-base text-gray-600">
              (From {{ formatDate(startDate) }} to {{ formatDate(endDate) }})
            </span>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-200 shadow-md rounded-lg">
              <thead>
                <tr class="text-left bg-gray-100">
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">Agent ID</th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">Email</th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">Total Bookings</th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">Profit</th>
                  <th class="px-6 py-3 text-sm font-medium text-gray-700">Total Revenue</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in profitLossReport?.data" :key="index" class="border-t">
                  <td class="px-6 py-4 text-sm text-gray-800">{{ item?.user?.agent_data?.agent_uid }}</td>
                  <td class="px-6 py-4 text-sm text-gray-800">{{ item?.user?.email }}</td>
                  <td class="px-6 py-4 text-sm text-gray-800">{{ item?.total_bookings }}</td>
                  <td class="px-6 py-4 text-sm text-green-600">{{ formatAmount(item?.total_agent_margin) }}</td>
                  <td class="px-6 py-4 text-sm text-gray-800">{{ formatAmount(item?.total_revenue) }}</td>
                </tr>
              </tbody>
              <tfoot class="print:hidden">
                <tr class="bg-gray-50 font-semibold">
                  <td class="px-6 py-4 text-right text-gray-700" colspan="2">Totals</td>
                  <td class="px-6 py-4 text-gray-800">{{ totals.totalBookings }}</td>
                  <td class="px-6 py-4 text-green-600">{{ formatAmount(totals.totalProfit) }}</td>
                  <td class="px-6 py-4 text-gray-800">{{ formatAmount(totals.totalRevenue) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <div class="mt-4 text-right text-gray-700 font-semibold">
            Total Profit: {{ formatAmount(grandTotal) }}
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style>
/* Print-specific styles */
@media print {
  body>*:not(#print-container) {
    display: none;
  }

  #print-container {
    display: block !important;
    width: 100%;
    position: absolute;
    left: 0;
    top: 0;
  }

  .print\:hidden {
    display: none !important;
  }

  .print\:mb-6 {
    margin-bottom: auto;
  }

  table {
    break-inside: auto;
    width: 100%;
    margin: 0.2cm 0 !important;
    border-spacing: 0;
  }

  th,
  td {
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

  @page {
    size: landscape;
    margin: 0.5cm;
  }

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