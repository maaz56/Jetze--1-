<script setup>
import Button from "@/components/ui/button/Button.vue";
import DateRangePicker from "@/components/common/DateRangePicker.vue";
import CustomerWallet from "@/pages/CustomerWallet.vue";

import {
    MoveRight,
    RefreshCcw,
    Search,
    ArrowLeft,
    ImageIcon,
    UploadIcon,
    SaveIcon,
    UserPlusIcon,
    CalendarIcon,
    CheckCircleIcon,
    EyeIcon,
    TrashIcon,
    LoaderIcon,
    InboxIcon,
    Download,
    Share,
    ChevronRight,
} from "lucide-vue-next";
import { Plus, Receipt, TicketCheck, Ban, CirclePause } from "lucide-vue-next";
import jsPDF from "jspdf";
import html2canvas from "html2canvas";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import { useStore } from "vuex";
import { useRoute, useRouter } from "vue-router";
import { computed, onMounted, ref, watch, nextTick } from "vue";
import { formatAmount } from "@/lib/utils";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";
import { useTransactionStore } from "@/services/stores/transaction";

import {
    FETCH_TRANSACTIONS,
    SAVE_TRANSACTION,
    FETCH_AGENT_DATA,
    FETCH_TOTAL_APPROVED_DEPOSIT,
    FETCH_DEPOSIT_DATA,
    FETCH_BOOKING_DATA,
    FETCH_AGENT_LEDGER,
    DELETE_DEPOSIT_DATA,
    FETCH_CUSTOMER_DATA,
} from "@/services/store/actions.type";
import AgentLedger from "./agent/AgentLedger.vue";

/* ------------------------------------------------------------------ */
/*  STORES & ROUTE                                                    */
/* ------------------------------------------------------------------ */
const store = useStore();
const route = useRoute();
const router = useRouter();
const activeTab = ref(route.query.tab || 'profile')

const loading = ref(true);
const error = ref(null);
const authStore = useAuthStore();
const transactionStore = useTransactionStore();
const searchQuery = ref("");
const dateRange = ref({
    start: null,
    end: null,
});
const emit = defineEmits(['filter-change']);
const activeFilter = ref('all');

const editDialogOpen = ref(false);

const closeEditDialog = () => {
    editDialogOpen.value = false;
};
const openEditDialog = () => {
    editDialogOpen.value = true;
};

const user = computed(() => authStore.user);
const user_role = computed(() => user?.value?.role);
const user_id = computed(() => user?.value?.id);
const now = ref(Date.now())

onMounted(() => {
  setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

const getRemainingTime = (expiry) => {
  if (!expiry) return 'N/A'

  // Parse expiry and get difference
  const expiryTime = new Date(expiry.replace(' ', 'T')).getTime()
  const diff = expiryTime - now.value
  if (diff <= 0) return 'Expired'

  // Calculate days, hours, minutes, seconds
  const days = Math.floor(diff / (1000 * 60 * 60 * 24))
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
  const seconds = Math.floor((diff % (1000 * 60)) / 1000)

  let result = ''
  if (days > 0) result += `${days}d `
  if (hours > 0 || days > 0) result += `${hours}h `
  result += `${minutes}m ${seconds.toString().padStart(2, '0')}s`

  return result
}
function filterBookings(type) {
    activeFilter.value = type
    //console.log("Filter changed to:", type);
    fetchBookings();


}

function updateCustomerProfile() {
    // Here you would typically send the updated customer data to your backend API
    // For demonstration, we'll just log the updated customer data
    console.log("Updated Customer Profile:", customer.value);
    // Close the dialog after saving
    closeEditDialog();
}

/* ------------------------------------------------------------------ */
/*  CUSTOMER (from customers table)                                   */
/* ------------------------------------------------------------------ */

async function fetchCustomer() {
    if (!user_id.value) return;
    try {
        // ----> replace with your real endpoint <----
        // const res = await api.get(`/customers/${user_id.value}`);
        // customer.value = res.data;

        // ----> DUMMY DATA (the row you printed) <----
        store.dispatch("customer/" + FETCH_CUSTOMER_DATA, {
            id: user_id.value,
        })
    } catch (e) {
        console.error(e);
        error.value = "Failed to load profile.";
    }
}

/* ------------------------------------------------------------------ */
/*  BOOKINGS                                                          */
/* ------------------------------------------------------------------ */
const bookings = computed(() => store.getters["flight/bookingData"]);
const customer = computed(() => store.getters["customer/customer"]);

function fetchBookings() {
    if (!user_id?.value) return;
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        userRole: user_role?.value,
        booking_mode: 'B2C',
        userId: user_id.value,
        bookingFilter: "all",

        searchQuery: searchQuery.value,
        dateRange: dateRange.value
    });
}


/* ------------------------------------------------------------------ */
/*  DEPOSITS & LEDGER                                                 */
/* ------------------------------------------------------------------ */
const agentData = computed(() => store.getters["user/agentData"]);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

async function fetchAgentDeposits() {
    if (!user_id.value) return;
    loading.value = true;
    try {
        await store.dispatch(`deposit/${FETCH_DEPOSIT_DATA}`, {
            userId: user_id.value,
        });
    } catch (e) {
        error.value = "Failed to load deposits.";
    } finally {
        loading.value = false;
    }
}

function fetchAgentLedger() {
    if (!user_id.value) return;
    store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, { userId: user_id.value });
}

function fetchAgent() {
    if (!user_id.value) return;
    store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: user_id.value });
}
function parseFlightData(flight_data) {
    try {
        return JSON.parse(flight_data);
    } catch (e) {
        console.error("Failed to parse flight data:", e);
        return {};
    }

}
/* ------------------------------------------------------------------ */
/*  DEPOSIT DIALOG                                                    */
/* ------------------------------------------------------------------ */
const showDialog = ref(false);
const selectedDeposit = ref(null);
const hideButtons = ref(false);

function openDialog(deposit) {
    selectedDeposit.value = deposit;
    showDialog.value = true;
}
function closeDialog() {
    showDialog.value = false;
    selectedDeposit.value = null;
}

/* ---- capture dialog as image (download) ---- */
async function captureDialogAsImage() {
    const el = document.querySelector("#deposit-dialog");
    if (!el) return;
    hideButtons.value = true;
    await nextTick();
    try {
        const canvas = await html2canvas(el, { scale: 2, backgroundColor: null });
        const url = canvas.toDataURL("image/png");
        const a = document.createElement("a");
        a.href = url;
        a.download = `Deposit_${selectedDeposit.value?.id}.png`;
        a.click();
    } catch (e) {
        console.error(e);
    } finally {
        hideButtons.value = false;
    }
}

/* ---- share on WhatsApp (opens WhatsApp with a generic text) ---- */
async function shareDialogOnWhatsApp() {
    await captureDialogAsImage(); // just to ensure image is ready
    const text = encodeURIComponent("Check out my deposit details:");
    window.open(`https://wa.me/?text=${text}`, "_blank");
}

/* ---- delete deposit ---- */
function deleteDeposit(id) {
    loading.value = true;
    store
        .dispatch(`deposit/${DELETE_DEPOSIT_DATA}`, { id })
        .then(() => fetchAgentDeposits())
        .catch(() => (error.value = "Failed to delete deposit"))
        .finally(() => (loading.value = false));
}

/* ------------------------------------------------------------------ */
/*  HELPERS                                                           */
/* ------------------------------------------------------------------ */
const formatCurrency = (v) =>
    new Intl.NumberFormat("en-US", {
        style: "decimal",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(v);

const formatDate = (d) =>
    new Date(d).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });

/* ------------------------------------------------------------------ */
/*  MOUNT / WATCH                                                     */
/* ------------------------------------------------------------------ */
onMounted(() => {
    fetchCustomer();
    fetchAgent();
    fetchAgentDeposits();
    fetchAgentLedger();
    fetchBookings();
});

watch(user_id, (id) => {
    if (id) {
        fetchCustomer();
        fetchAgent();
        fetchAgentDeposits();
        fetchAgentLedger();
        fetchBookings();
    }
});
</script>

<template>
    <div class="container mx-auto px-4 gap-6 md:gap-4 p-4 md:p-6">

        <div class="bg-white border-b border-gray-200 rounded-t-lg">
            <div class="flex overflow-x-auto">
                <button @click="activeTab = 'profile'" :class="[
                    'px-6 py-4 font-medium text-sm whitespace-nowrap border-b-2 transition-colors',
                    activeTab === 'profile'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    My Profile
                </button>
                <button @click="activeTab = 'bookings'" :class="[
                    'px-6 py-4 font-medium text-sm whitespace-nowrap border-b-2 transition-colors',
                    activeTab === 'bookings'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    Bookings Overview
                </button>
                <button @click="activeTab = 'deposits'" :class="[
                    'px-6 py-4 font-medium text-sm whitespace-nowrap border-b-2 transition-colors',
                    activeTab === 'deposits'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    Deposit Overview
                </button>
                
                <button @click="activeTab = 'wallet'" :class="[
                    'px-6 py-4 font-medium text-sm whitespace-nowrap border-b-2 transition-colors',
                    activeTab === 'wallet'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    Transactions Overview
                </button>
            </div>
        </div>

        <!-- Tabs Content -->
        <div class="bg-white rounded-b-lg shadow-sm">
            <!-- Profile Tab -->
            <div v-show="activeTab === 'profile'" class="p-6">
                <Card v-if="customer" class="overflow-hidden border-none shadow-none">
                    <CardHeader class="bg-primary text-white">
                        <div class="flex items-center justify-between">

                            <div>
                                <CardTitle class="text-2xl">My Profile</CardTitle>
                                <CardDescription class="text-indigo-100">
                                    Account information
                                </CardDescription>
                            </div>
                            <Button @click="openEditDialog"
                                class="bg-white text-primary hover:bg-gray-100 ">Edit</Button>

                            <!-- Edit Profile Dialog -->
                            <div v-if="editDialogOpen"
                                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                                <div class="bg-white rounded-lg w-full max-w-lg p-6 shadow-lg">
                                    <div class="flex items-center justify-between mb-4">
                                        <h3 class="text-lg font-semibold">Edit Profile</h3>
                                        <button @click="closeEditDialog"
                                            class="text-gray-500 hover:text-gray-700">✕</button>
                                    </div>

                                    <form @submit.prevent="updateCustomerProfile"
                                        class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <Label class="text-gray-600">First Name</Label>
                                            <Input v-model="customer.name" />
                                        </div>

                                        <div>
                                            <Label class="text-gray-600">Last Name</Label>
                                            <Input v-model="customer.last_name" />
                                        </div>

                                        <div>
                                            <Label class="text-gray-600">Email</Label>
                                            <Input type="email" v-model="customer.email" />
                                        </div>

                                        <div>
                                            <Label class="text-gray-600">Phone</Label>
                                            <Input v-model="customer.phone" />
                                        </div>

                                        <div class="md:col-span-2">
                                            <Label class="text-gray-600">Address</Label>
                                            <Input v-model="customer.address" />
                                        </div>

                                        <!-- Inline actions so save uses the new function and model -->
                                        <div class="md:col-span-2 mt-4 flex justify-end gap-3">
                                            <Button type="button" variant="outline"
                                                @click="closeEditDialog">Cancel</Button>
                                            <Button type="submit" class="bg-primary text-white">Save</Button>
                                        </div>
                                    </form>


                                </div>
                            </div>
                        </div>


                    </CardHeader>

                    <CardContent class="pt-6 bg-white border-none">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <Label class="text-gray-600">Full Name</Label>
                                <p class="font-semibold">
                                    {{ customer.name }} {{ customer.last_name }}
                                </p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Email</Label>
                                <p class="font-semibold">{{ customer.email }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Phone</Label>
                                <p class="font-semibold">{{ customer.phone }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Address</Label>
                                <p class="font-semibold">{{ customer.address }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Preferred Currency</Label>
                                <p class="font-semibold">{{ customer.preferred_currency }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Company Name</Label>
                                <p class="font-semibold">{{ customer.company_name }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Member Since</Label>
                                <p class="font-semibold">{{ formatDate(customer.created_at) }}</p>
                            </div>
                            <div>
                                <Label class="text-gray-600">Last Updated</Label>
                                <p class="font-semibold">{{ formatDate(customer.updated_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bookings Overview Tab -->
            <div v-show="activeTab === 'bookings'" class="p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Bookings Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-primary/10 rounded-md p-4 flex items-center cursor-pointer transition hover:shadow-md"
                        :class="{ 'ring-1 ring-primary': activeFilter === 'all' }" @click="filterBookings('all')">
                        <CalendarIcon class="h-8 w-8 text-primary mr-4" />
                        <div>
                            <p class="text-sm font-medium text-primary">All Bookings</p>
                            <p class="text-2xl font-bold text-primary">
                                {{ bookings?.total_count ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-green-50 rounded-md p-4 flex items-center cursor-pointer transition hover:shadow-md"
                        :class="{ 'ring-1 ring-green-500': activeFilter === 'ticketed' }"
                        @click="filterBookings('ticketed')">
                        <CheckCircleIcon class="h-8 w-8 text-green-500 mr-4" />
                        <div>
                            <p class="text-sm font-medium text-green-600">Ticketed</p>
                            <p class="text-2xl font-bold text-green-800">
                                {{ bookings?.total_ticketed ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-red-50 rounded-md p-4 flex items-center cursor-pointer transition hover:shadow-md"
                        :class="{ 'ring-1 ring-red-500': activeFilter === 'canceled' }"
                        @click="filterBookings('canceled')">
                        <Ban class="h-8 w-8 text-red-500 mr-4" />
                        <div>
                            <p class="text-sm font-medium text-red-600">Total Canceled</p>
                            <p class="text-2xl font-bold text-red-800">
                                {{ bookings?.total_canceled ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-yellow-50 rounded-md p-4 flex items-center cursor-pointer transition hover:shadow-md"
                        :class="{ 'ring-1 ring-yellow-500': activeFilter === 'booked' }"
                        @click="filterBookings('booked')">
                        <CirclePause class="h-8 w-8 text-yellow-500 mr-4" />
                        <div>
                            <p class="text-sm font-medium text-yellow-600">On Hold</p>
                            <p class="text-2xl font-bold text-yellow-800">
                                {{ bookings?.total_booked ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-1/2 mb-2 mt-2 ">
                    <div class="flex items-center gap-2">
                        <label for="simple-search" class="sr-only">Search</label>
                        <div class="relative w-full">

                            <input v-model="searchQuery" type="text" id="simple-search"
                                class="ring-0 outline-none border bg-white h-10 border-gray-300 text-gray-900 text-sm rounded-md focus:ring-primary focus:border-primary block w-full pl-10 p-2 "
                                placeholder="Search" required="" />
                        </div>
                        <DateRangePicker heading="Select Date Range" v-model="dateRange" />
                        <Button class=" text-white" @click="fetchBookings">Search</Button>
                    </div>
                </div>
                <div class="bg-white  relative sm:rounded-lg border border-gray-300 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                            v-if="bookings?.bookings?.length > 0">

                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="px-4 py-3">
                                        Booking Date
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        Booking ID
                                    </th>
                                    <th scope="col" class="px-4 py-3">PNR</th>
                                    <th scope="col" class="px-4 py-3">Source</th>

                                    <th scope="col" class="px-4 py-3">Supplier</th>
                                    <th scope="col" class="px-4 py-3">Rout</th>
                                    <th scope="col" class="px-4 py-3">
                                        Passenger Name
                                    </th>
                                    <th scope="col" class="px-4 py-3">
                                        Travel Date
                                    </th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-4 py-3">Expiry on</th>
                                    <th scope="col" class="px-4 py-3">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr v-for="booking in bookings?.bookings" :key="booking.id"
                                    class="border-b dark:border-gray-700">

                                    <td class="px-4 py-3">
                                        {{
                                            moment(booking.created_at).format(
                                                "DD-MMM-YYYY HH:mm",
                                            )
                                        }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ agentData?.agent_data?.agent_uid }}_{{ booking.id + 1000 }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ booking.itinerary_ref ?? booking.itinerary_ref ?? booking.pnr }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ parseFlightData(booking?.flight_data)?.provider?.source || parseFlightData(booking?.flight_data)?.provider?.identifier  }}

                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                            :key="flightIndex" class="flex gap-2 justify-start items-center">

                                            <span class="relative group">
                                                <img :src="flight?.marketing_carrier?.logo" alt="Carrier Logo"
                                                    class="h-6 w-6" />
                                                <span v-if="flight?.marketing_carrier?.name"
                                                    class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 px-2 py-1 rounded bg-primary text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                                    {{ flight?.marketing_carrier?.name }}
                                                </span>
                                            </span>
                                        </div>

                                    </td>
                                    <td px-4 py-3>
                                        <div v-if="booking?.booking_source == 1">
                                            <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                                :key="flightIndex" class="flex gap-2 justify-start items-center">
                                                <div class="flex gap-2">

                                                    <p>
                                                        {{ flight?.from?.iata }}
                                                    </p>
                                                    <p>To</p>
                                                    <p>

                                                        {{ flight?.to?.iata }}
                                                    </p>
                                                </div>

                                            </div>
                                        </div>
                                       
                                    </td>

                                    <td>
                                        <div v-for="passenger in booking.pessangers" :key="passenger.id" class="mb-2">
                                            <div class="flex">
                                                <div>
                                                    <p>
                                                        {{ passenger.title }}
                                                        {{ passenger.first_name }}
                                                        {{ passenger.last_name }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td px-4 py-3>
                                        <div v-if="booking?.booking_source == 1">
                                            <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                                :key="flightIndex">
                                                <p class="font-semibold">{{ formatDate(flight?.departure_at) }}</p>
                                            </div>
                                        </div>
                                        <div v-else>
                                            <p class="font-semibold"
                                                v-for="(date, dateIndex) in parseFlightData(booking.flight_data,).dates"
                                                :key="dateIndex">
                                                {{ formatDate(date.departureDate) }}
                                                <!-- {{ date.departureLocation }} to
                                    {{ date.arrivalLocation }} -->
                                            </p>
                                        </div>

                                    </td>
                                    <td class="py-2 px-4 uppercase">{{ booking.status }}</td>
                                    <td v-if="booking?.status === 'booked'" class="py-2 px-4">
  <span
    class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
           bg-amber-100 text-amber-700 border border-amber-300
           "
  >
    {{ getRemainingTime(booking.expiry_time) }}
  </span>
</td>
                                    <td v-else class="py-2 px-4  uppercase"><span
    class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
           bg-amber-100 text-amber-700 border border-amber-300
           "
  >
    N/A
  </span></td>
                                    <td class="px-1 py-4">
                                        <div class="flex space-x-2">
                                            <div class="flex space-x-2">
                                                <Button size="sm" variant="outline" @click="
                                                    $router.push({
                                                        name: 'BookingsDetails',
                                                        query: {
                                                            booking_id:
                                                                booking.id,

                                                            pnr: booking?.itinerary_reference ??
                                                                booking?.itinerary_ref ??
                                                                booking.pnr,
                                                            booking_source:
                                                                booking?.booking_source,
                                                            flight_provider: parseFlightData(booking.flight_data,)?.provider?.name,
                                                            flight_mode: 'B2C',
                                                        },
                                                    })
                                                    " class="flex gap-2 text-primary hover:text-white">
                                                    Details
                                                    <ChevronRight class="w-5 h-5" />
                                                </Button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                        <div v-else class="flex flex-col items-center justify-center py-12 px-4 w-full ">
                            <img class="h-16 md:h-48 max-w-md w-full object-contain mx-auto"
                                src="/public/assets/no-data.svg" alt="No data available">
                            <p
                                class="text-gray-600 text-lg mt-6 text-center font-medium bg-gray-50 px-6 py-3 rounded-full shadow-sm border border-gray-100 max-w-md mx-auto">
                                <span class="inline-block mr-2">🔍</span>
                                No bookings found for the selected filter
                                <span class="block text-sm text-gray-400 mt-1 font-normal">Try selecting a different
                                    category</span>

                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deposits Overview Tab -->
            <div v-show="activeTab === 'deposits'" class="p-6">

                <CustomerWallet />

            </div>
            <div v-show="activeTab === 'wallet'" class="p-6">

                <AgentLedger />

            </div>

            <!-- Recent Deposits Tab -->
            <div v-show="activeTab === 'recent'" class="p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Deposits</h2>

                <!-- Loading -->
                <div v-if="loading" class="flex flex-col items-center py-12">
                    <LoaderIcon class="w-10 h-10 animate-spin text-gray-500" />
                    <p class="mt-2 text-gray-600">Loading deposits…</p>
                </div>

                <!-- Table -->
                <div v-else-if="agentDepositData?.deposits && agentDepositData.deposits.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left text-gray-600">
                        <thead class="text-xs uppercase bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-3 py-3">Receipt Ref.</th>
                                <th class="px-1 py-3">Date</th>
                                <th class="px-1 py-3">Payment Type</th>
                                <th class="px-1 py-3">Details</th>
                                <th class="px-1 py-3">Status</th>
                                <th class="px-1 py-3">Amount</th>
                                <th class="px-1 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="d in agentDepositData.deposits" :key="d.id" class="border-b hover:bg-gray-50">
                                <td class="px-3 py-4 font-medium text-gray-900">
                                    {{ d.receipt_reference || "-" }}
                                </td>
                                <td class="px-1 py-4">{{ d.date || "-" }}</td>
                                <td class="px-1 py-4">{{ d.payment_type || "-" }}</td>
                                <td class="px-1 py-4">{{ d.additional_details || "-" }}</td>
                                <td class="px-1 py-4">
                                    <span :class="{
                                        'bg-yellow-200 text-yellow-800': d.deposit_status === 'pending',
                                        'bg-gray-200 text-gray-700': d.deposit_status === 'approved',
                                        'bg-red-200 text-red-700': d.deposit_status === 'rejected',
                                    }" class="rounded-lg px-2 py-1 text-xs font-medium uppercase">
                                        {{ d.deposit_status || "-" }}
                                    </span>
                                </td>
                                <td class="px-1 py-4">{{ formatCurrency(d.amount) }}</td>
                                <td class="px-1 py-4">
                                    <div class="flex space-x-2">
                                        <button @click="openDialog(d)" class="text-gray-600 hover:text-gray-900">
                                            <EyeIcon class="w-5 h-5" />
                                        </button>
                                        <button @click="deleteDeposit(d.id)" :disabled="d.deposit_status === 'approved'"
                                            :class="{
                                                'opacity-50 cursor-not-allowed': d.deposit_status === 'approved',
                                            }" class="text-red-600 hover:text-red-900">
                                            <TrashIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty -->
                <div v-else class="text-center py-12">
                    <InboxIcon class="w-16 h-16 mx-auto text-gray-400" />
                    <p class="mt-4 text-lg font-semibold text-gray-600">No deposits found</p>
                    <p class="text-gray-500">There are no deposits to display at the moment.</p>
                </div>
            </div>
        </div>

        <!-- Deposit Details Dialog -->
        <teleport to="body">
            <div v-if="showDialog" id="deposit-dialog" class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeDialog"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <h3 class="mb-4 text-2xl font-extrabold text-gray-900" id="modal-title">
                                Deposit Details
                            </h3>

                            <img v-if="selectedDeposit?.receipt_image" :src="selectedDeposit.receipt_image"
                                alt="Receipt" class="mb-4 w-full rounded" />

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="font-medium text-gray-500">Date</p>
                                    <p class="font-bold text-gray-900">{{ selectedDeposit?.date }}</p>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Receipt Ref.</p>
                                    <p class="font-bold text-gray-900">
                                        {{ selectedDeposit?.receipt_reference }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Amount</p>
                                    <p class="font-bold text-gray-900">
                                        {{ formatCurrency(selectedDeposit?.amount) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Payment Type</p>
                                    <p class="font-bold text-gray-900">
                                        {{ selectedDeposit?.payment_type }}
                                    </p>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-500">Status</p>
                                    <p :class="{
                                        'text-yellow-700': selectedDeposit?.deposit_status === 'pending',
                                        'text-gray-600': selectedDeposit?.deposit_status === 'approved',
                                        'text-red-600': selectedDeposit?.deposit_status === 'rejected',
                                    }" class="font-bold uppercase">
                                        {{ selectedDeposit?.deposit_status }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="font-medium text-gray-500">Additional Details</p>
                                <p class="text-gray-700">{{ selectedDeposit?.additional_details }}</p>
                            </div>
                        </div>

                        <div v-if="!hideButtons" class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button @click="shareDialogOnWhatsApp"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-gray-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm">
                                Share on WhatsApp
                            </button>
                            <button @click="captureDialogAsImage"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-transparent bg-gray-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Download Image
                            </button>
                            <button @click="closeDialog"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:mt-0 sm:w-auto sm:text-sm">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </div>
</template>