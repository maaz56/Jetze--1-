<script setup>
import Button from "@/components/ui/button/Button.vue";
import apiClient from "@/config/axios";
import DateRangePicker from "@/components/common/DateRangePicker.vue";


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
    UserRound,
    WalletCards,
    ListChecks,
    Building2,
} from "lucide-vue-next";
import { Plus, Receipt, TicketCheck, Ban, CirclePause, CircleX } from "lucide-vue-next";
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
import { computed, defineAsyncComponent, onMounted, onBeforeUnmount, ref, watch, nextTick } from "vue";
import { formatAmount } from "@/lib/utils";
import { statusLabel } from "@/lib/bookingRequests";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";
import { useTransactionStore } from "@/services/stores/transaction";
import { useHotelStore } from "@/services/stores/hotel";

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
const CustomerWallet = defineAsyncComponent(() => import("@/pages/CustomerWallet.vue"));
const AgentLedger = defineAsyncComponent(() => import("./customer/CustomerLedger.vue"));
const CancellationRequests = defineAsyncComponent(() => import("./customer/CustomerCancellationRequests.vue"));

/* ------------------------------------------------------------------ */
/*  STORES & ROUTE                                                    */
/* ------------------------------------------------------------------ */
const store = useStore();
const route = useRoute();
const router = useRouter();
const sections = ['profile', 'bookings', 'cancellation-requests', 'hotel-bookings', 'deposits', 'wallet'];
const activeTab = computed(() => {
    const section = route.params.section || route.query.tab || 'bookings';
    return sections.includes(section) ? section : 'bookings';
});

const loading = ref(true);
const bookingsLoading = ref(true);
const bookingsError = ref(null);
const error = ref(null);
const authStore = useAuthStore();
const transactionStore = useTransactionStore();
const hotelStore = useHotelStore();
const searchQuery = ref("");
const dateRange = ref({
    start: null,
    end: null,
});
const emit = defineEmits(['filter-change']);
const activeFilter = ref('all');
const hotelBookingsLoading = ref(false);
const hotelBookingsError = ref("");
const hotelBookingsLoaded = ref(false);
const hotelBookings = computed(() => hotelStore.getBookings);
const hotelBookingMeta = computed(() => hotelStore.getBookingMeta);

const editingProfile = ref(false);
const profileSaving = ref(false);
const profileError = ref('');
const profileSuccess = ref('');
const profileForm = ref({});

function startEditingProfile() {
    profileError.value = '';
    profileSuccess.value = '';
    profileForm.value = Object.fromEntries(['name', 'last_name', 'phone', 'address', 'company_name', 'preferred_currency']
        .map((field) => [field, customer.value?.[field] || '']));
    editingProfile.value = true;
}

async function saveProfile() {
    profileSaving.value = true;
    profileError.value = '';
    try {
        await apiClient.put('/customer-profile', profileForm.value);
        await fetchCustomer();
        editingProfile.value = false;
        profileSuccess.value = 'Your profile has been updated.';
    } catch (err) {
        profileError.value = Object.values(err.response?.data?.errors || {}).flat().join(' ') || 'Unable to save your profile. Please try again.';
    } finally {
        profileSaving.value = false;
    }
}

const user = computed(() => authStore.user);
const user_role = computed(() => user?.value?.role);
const user_id = computed(() => user?.value?.id);
const now = ref(Date.now())

let clockInterval;
onMounted(() => {
  clockInterval = setInterval(() => { now.value = Date.now(); }, 1000);
});
onBeforeUnmount(() => clearInterval(clockInterval));

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
    fetchBookings();


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
        await store.dispatch("customer/" + FETCH_CUSTOMER_DATA, {
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

async function fetchBookings() {
    if (!user_id?.value) return;
    bookingsLoading.value = true;
    bookingsError.value = null;

    try {
        await store.dispatch("flight/" + FETCH_BOOKING_DATA, {
            userRole: user_role?.value,
            booking_mode: 'B2C',
            userId: user_id.value,
            bookingFilter: activeFilter.value,
            searchQuery: searchQuery.value,
            dateRange: dateRange.value
        });
    } catch (e) {
        bookingsError.value = "Failed to load bookings. Please try again.";
    } finally {
        bookingsLoading.value = false;
    }
}

async function fetchHotelBookings(page = 1) {
    hotelBookingsLoading.value = true;
    hotelBookingsError.value = "";

    try {
        await hotelStore.fetchHotelBookings({ page });
        hotelBookingsLoaded.value = true;
    } catch (e) {
        hotelBookingsError.value = e.response?.data?.message || "Unable to load hotel bookings.";
    } finally {
        hotelBookingsLoading.value = false;
    }
}

const formatHotelMoney = (booking) => {
    const money = booking.price_snapshot?.selling_money || {
        amount: booking.total_fare,
        currency: booking.currency,
    };

    if (money?.amount === null || money?.amount === undefined || !money?.currency) {
        return "Unavailable";
    }

    return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })}`;
};


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
watch([user_id, activeTab], ([id, tab]) => {
    if (!id) return;
    if (tab === 'profile') fetchCustomer();
    if (tab === 'bookings') {
        fetchAgent();
        fetchBookings();
    }
    if (tab === 'hotel-bookings') fetchHotelBookings();
}, { immediate: true });
</script>

<template>
    <div class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:py-10">
        <div class="mb-6 rounded-2xl border border-primary/15 bg-primary/5 px-6 py-7 text-slate-900 sm:px-8">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Your account</p>
            <h1 class="mt-2 text-2xl font-bold sm:text-3xl">Customer dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Manage your profile, bookings, deposits and transactions.</p>
        </div>

        <div class="bg-white border-b border-gray-200 rounded-t-lg">
            <div class="flex overflow-x-auto sm:overflow-x-visible scrollbar-hide">
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'bookings' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'bookings'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <TicketCheck class="h-4 w-4" aria-hidden="true" />
                    Bookings Overview
                </router-link>
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'cancellation-requests' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'cancellation-requests'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <Ban class="h-4 w-4" aria-hidden="true" />
                    Cancellation Requests
                </router-link>
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'hotel-bookings' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'hotel-bookings'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <Building2 class="h-4 w-4" aria-hidden="true" />
                    Hotel Bookings
                </router-link>
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'deposits' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'deposits'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <WalletCards class="h-4 w-4" aria-hidden="true" />
                    Deposit Overview
                </router-link>
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'wallet' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'wallet'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <ListChecks class="h-4 w-4" aria-hidden="true" />
                    Transactions Overview
                </router-link>
                <router-link :to="{ name: 'CustomerProfile', params: { section: 'profile' } }" :class="[
                    'px-3 sm:px-6 py-3 sm:py-4 font-medium text-xs sm:text-sm whitespace-nowrap border-b-2 transition-colors flex-shrink-0 inline-flex items-center gap-2',
                    activeTab === 'profile'
                        ? 'border-primary text-primary'
                        : 'border-transparent text-gray-600 hover:text-gray-900'
                ]">
                    <UserRound class="h-4 w-4" aria-hidden="true" />
                    My Profile
                </router-link>
            </div>
        </div>

        <!-- Tabs Content -->
        <div class="bg-white rounded-b-lg shadow-sm">
            <!-- Profile -->
            <section v-if="activeTab === 'profile'" class="p-4 sm:p-8">
                <div v-if="customer" class="space-y-6">
                    <div class="flex flex-col gap-5 rounded-2xl border border-primary/10 bg-primary/5 p-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-primary text-2xl font-bold text-white">{{ (customer.name || user?.email || 'U').charAt(0).toUpperCase() }}</div>
                            <div><p class="text-xs font-semibold uppercase tracking-wider text-primary">My account</p>
                                <h2 class="mt-1 text-xl font-bold text-slate-900 sm:text-2xl">{{ [customer.name, customer.last_name].filter(Boolean).join(' ') || 'Your profile' }}</h2>
                                <p class="mt-1 text-sm text-slate-500">{{ customer.email || user?.email }}</p>
                            </div>
                        </div>
                        <Button v-if="!editingProfile" @click="startEditingProfile" class="text-white">Edit profile</Button>
                    </div>
                    <p v-if="profileSuccess" role="status" class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ profileSuccess }}</p>
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 p-5 sm:p-6 lg:col-span-2">
                            <div class="mb-5"><h3 class="text-lg font-semibold text-slate-900">Personal information</h3><p class="mt-1 text-sm text-slate-500">Your contact details and travel preferences.</p></div>
                            <form v-if="editingProfile" @submit.prevent="saveProfile" class="grid gap-4 sm:grid-cols-2">
                                <div><Label for="profile-name">First name</Label><Input id="profile-name" v-model="profileForm.name" required maxlength="255" /></div>
                                <div><Label for="profile-last-name">Last name</Label><Input id="profile-last-name" v-model="profileForm.last_name" maxlength="255" /></div>
                                <div><Label for="profile-phone">Phone</Label><Input id="profile-phone" v-model="profileForm.phone" type="tel" maxlength="30" /></div>
                                <div><Label for="profile-company">Company</Label><Input id="profile-company" v-model="profileForm.company_name" maxlength="255" /></div>
                                <div><Label for="profile-currency">Preferred currency</Label><Input id="profile-currency" v-model="profileForm.preferred_currency" placeholder="e.g. AED" minlength="3" maxlength="3" /></div>
                                <div class="sm:col-span-2"><Label for="profile-address">Address</Label><Input id="profile-address" v-model="profileForm.address" maxlength="500" /></div>
                                <p v-if="profileError" role="alert" class="text-sm text-red-600 sm:col-span-2">{{ profileError }}</p>
                                <div class="flex gap-3 sm:col-span-2"><Button type="submit" :disabled="profileSaving" class="text-white">{{ profileSaving ? 'Saving...' : 'Save changes' }}</Button><Button type="button" variant="outline" @click="editingProfile = false" :disabled="profileSaving">Cancel</Button></div>
                            </form>
                            <dl v-else class="grid gap-5 text-sm sm:grid-cols-2">
                                <div><dt class="text-slate-500">Full name</dt><dd class="mt-1 font-medium text-slate-900">{{ [customer.name, customer.last_name].filter(Boolean).join(' ') || 'Not provided' }}</dd></div>
                                <div><dt class="text-slate-500">Email</dt><dd class="mt-1 break-all font-medium text-slate-900">{{ customer.email || user?.email || 'Not provided' }}</dd></div>
                                <div><dt class="text-slate-500">Phone</dt><dd class="mt-1 font-medium text-slate-900">{{ customer.phone || 'Not provided' }}</dd></div>
                                <div><dt class="text-slate-500">Company</dt><dd class="mt-1 font-medium text-slate-900">{{ customer.company_name || 'Not provided' }}</dd></div>
                                <div><dt class="text-slate-500">Preferred currency</dt><dd class="mt-1 font-medium text-slate-900">{{ customer.preferred_currency || 'Not provided' }}</dd></div>
                                <div><dt class="text-slate-500">Address</dt><dd class="mt-1 font-medium text-slate-900">{{ customer.address || 'Not provided' }}</dd></div>
                            </dl>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-slate-200 p-5"><h3 class="font-semibold text-slate-900">Account</h3><p class="mt-3 text-sm text-slate-500">Member since</p><p class="mt-1 text-sm font-medium text-slate-900">{{ customer.created_at ? formatDate(customer.created_at) : 'Not available' }}</p></div>
                            <div class="rounded-2xl border border-slate-200 p-5"><h3 class="font-semibold text-slate-900">Quick links</h3><div class="mt-3 flex flex-col gap-3 text-sm font-medium text-primary"><router-link :to="{ name: 'CustomerProfile', params: { section: 'bookings' } }">View flight bookings →</router-link><router-link :to="{ name: 'CustomerProfile', params: { section: 'hotel-bookings' } }">View hotel bookings →</router-link><router-link :to="{ name: 'CustomerProfile', params: { section: 'deposits' } }">Manage deposits →</router-link><router-link :to="{ name: 'CustomerProfile', params: { section: 'wallet' } }">View transactions →</router-link></div></div>
                        </div>
                    </div>
                </div>
                <div v-else class="py-12 text-center text-sm text-slate-500">Loading your profile...</div>
            </section>

            <!-- Bookings Overview Tab -->
            <div v-if="activeTab === 'bookings'" class="p-6 ">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Bookings Overview</h2>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
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

                    <div class="bg-purple-50 rounded-md p-4 flex items-center cursor-pointer transition hover:shadow-md"
                        :class="{ 'ring-1 ring-purple-500': activeFilter === 'voided' }"
                        @click="filterBookings('voided')">
                        <CircleX class="h-8 w-8 text-purple-500 mr-4" />
                        <div>
                            <p class="text-sm font-medium text-purple-600">Voided</p>
                            <p class="text-2xl font-bold text-purple-800">
                                {{ bookings?.total_voided ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="w-full md:w-1/2 mb-2 mt-2">
                    <div class="flex flex-row flex-nowrap items-center gap-2">
                        <label for="simple-search" class="sr-only">Search</label>
                        <div class="relative flex-1 min-w-[60px] sm:min-w-[80px] md:min-w-[100px]">
                            <input v-model="searchQuery" type="text" id="simple-search"
                                class="ring-0 outline-none border bg-white h-10 border-gray-300 text-gray-900 text-sm rounded-md focus:ring-primary focus:border-primary block w-full pl-10 p-2"
                                placeholder="Search" required="" />
                        </div>
                        <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                            <div class="hidden xs:block">
                                <DateRangePicker heading="Select Date Range" v-model="dateRange" />
                            </div>
                            <Button class="text-white whitespace-nowrap px-3 sm:px-4 text-xs sm:text-sm" @click="fetchBookings"
                                :disabled="bookingsLoading">
                                <LoaderIcon v-if="bookingsLoading" class="mr-2 h-4 w-4 animate-spin" />
                                {{ bookingsLoading ? 'Loading…' : 'Search' }}
                            </Button>
                        </div>
                    </div>
                </div>
                <div class="bg-white relative sm:rounded-lg border-2 sm:border border-gray-300 overflow-x-auto">
                    <div class="overflow-x-auto">
                        <div v-if="bookingsLoading" class="flex min-h-64 flex-col items-center justify-center px-4 py-12"
                            role="status" aria-live="polite">
                            <LoaderIcon class="h-10 w-10 animate-spin text-primary" />
                            <p class="mt-3 text-sm font-medium text-gray-600">Loading bookings…</p>
                        </div>

                        <div v-else-if="bookingsError" class="flex min-h-64 flex-col items-center justify-center px-4 py-12 text-center">
                            <InboxIcon class="h-10 w-10 text-red-400" />
                            <p class="mt-3 font-medium text-red-600">{{ bookingsError }}</p>
                            <Button class="mt-4 text-white" @click="fetchBookings">Try again</Button>
                        </div>

                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                            v-else-if="bookings?.bookings?.length > 0">

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
                                                        <span v-if="passenger.status && passenger.status !== booking.status"
                                                            class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-600">
                                                            {{ statusLabel(passenger.status) }}
                                                        </span>
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
                                    <td class="py-2 px-4">
                                        {{ statusLabel(booking.status).toUpperCase() }}{{ booking.is_manually_issued ? ' (manually issued)' : '' }}
                                    </td>
                                    <td v-if="booking?.status === 'booked'" class="py-2 px-4">
                                        <span
                                            class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
                                                bg-amber-100 text-amber-700 border border-amber-300
                                                "
                                        >
                                            {{ getRemainingTime(booking.expiry_time) }}
                                        </span>
                                    </td>
                                    <td v-else class="py-2 px-4  uppercase">
                                        <span
                                        class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
                                            bg-amber-100 text-amber-700 border border-amber-300
                                            "
                                    >
                                        N/A
                                        </span>
                                    </td>
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
                                                             flight_provider: booking?.flight_provider,
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

            <!-- Hotel Bookings Tab -->
            <!-- Cancellation Requests Tab -->
            <div v-if="activeTab === 'cancellation-requests'" class="p-6">
                <CancellationRequests />
            </div>

            <div v-if="activeTab === 'hotel-bookings'" class="p-6">
                <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Hotel Bookings</h2>
                        <p class="mt-1 text-sm text-gray-500">Your confirmed hotel reservations and vouchers.</p>
                    </div>
                    <Button class="text-white" @click="router.push({ name: 'HotelSearch' })">
                        Search Hotels
                    </Button>
                </div>

                <div v-if="hotelBookingsLoading" class="flex min-h-64 flex-col items-center justify-center rounded-lg border border-gray-200 px-4 py-12" role="status" aria-live="polite">
                    <LoaderIcon class="h-10 w-10 animate-spin text-primary" />
                    <p class="mt-3 text-sm font-medium text-gray-600">Loading hotel bookings…</p>
                </div>

                <div v-else-if="hotelBookingsError" class="flex min-h-64 flex-col items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-12 text-center">
                    <InboxIcon class="h-10 w-10 text-red-400" />
                    <p class="mt-3 font-medium text-red-600">{{ hotelBookingsError }}</p>
                    <Button class="mt-4 text-white" @click="fetchHotelBookings()">Try again</Button>
                </div>

                <div v-else-if="!hotelBookings.length" class="rounded-lg border border-gray-200 px-4 py-12 text-center">
                    <Building2 class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-3 font-medium text-gray-700">No hotel bookings found.</p>
                    <p class="mt-1 text-sm text-gray-500">Your hotel reservations will appear here once booked.</p>
                </div>

                <div v-else class="space-y-3">
                    <article v-for="booking in hotelBookings" :key="booking.booking_id" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase" :class="booking.status === 'cancelled' ? 'bg-red-100 text-red-700' : booking.status === 'confirmed' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                    {{ booking.status || 'pending' }}
                                </span>
                                <span class="text-xs text-gray-500">Ref: {{ booking.booking_reference_id || '—' }}</span>
                            </div>
                            <h3 class="mt-2 truncate text-base font-semibold text-gray-950">{{ booking.hotel?.name || 'Hotel booking' }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ booking.check_in || '—' }} to {{ booking.check_out || '—' }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-800">{{ formatHotelMoney(booking) }}</p>
                        </div>
                        <Button class="shrink-0 text-white" @click="router.push({ name: 'HotelBookingDetails', query: { booking_id: booking.booking_id } })">
                            View Voucher
                            <ChevronRight class="ml-1 h-4 w-4" />
                        </Button>
                    </article>

                    <div v-if="hotelBookingMeta?.last_page > 1" class="flex items-center justify-between pt-3 text-sm">
                        <Button variant="outline" :disabled="hotelBookingMeta.current_page <= 1 || hotelBookingsLoading" @click="fetchHotelBookings(hotelBookingMeta.current_page - 1)">
                            Previous
                        </Button>
                        <span class="text-gray-500">Page {{ hotelBookingMeta.current_page }} of {{ hotelBookingMeta.last_page }}</span>
                        <Button variant="outline" :disabled="hotelBookingMeta.current_page >= hotelBookingMeta.last_page || hotelBookingsLoading" @click="fetchHotelBookings(hotelBookingMeta.current_page + 1)">
                            Next
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Deposits Overview Tab -->
            <div v-if="activeTab === 'deposits'" class="p-6">

                <CustomerWallet />

            </div>
            <div v-if="activeTab === 'wallet'" class="p-6">

                <AgentLedger />

            </div>

            <!-- Recent Deposits Tab -->
            <div v-if="activeTab === 'recent'" class="p-6">
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

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
