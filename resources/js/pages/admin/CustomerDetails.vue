<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useStore } from "vuex";
import { useRoute, useRouter } from "vue-router";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";

import {
    ArrowLeft,
    UserIcon,
    ImageIcon,
    UploadIcon,
    SaveIcon,
    UserPlusIcon,
    CalendarIcon,
    CheckCircleIcon,
    Plus,
    Receipt,
    TicketCheck,
    ShoppingCart,
} from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue
} from "@/components/ui/select";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import {
    FETCH_AGENT_DATA,
    UPDATE_USER_STATUS,
    FETCH_TOTAL_APPROVED_DEPOSIT,
    SAVE_AGENT_MARGIN,
    FETCH_AGENT_LEDGER,
    FETCH_BOOKING_DATA,
    SAVE_AGENT_CHARGES,
    UPDATE_CARD_ALLOWANCE,
    FETCH_CUSTOMER_DATA
} from "@/services/store/actions.type";
import { formatAmount } from "@/lib/utils";
import { useAuthStore } from "@/services/stores/auth";

const authStore = useAuthStore();
const authUser = computed(() => authStore.user);
const store = useStore();
const route = useRoute();
const router = useRouter();
const discount = ref(0);
let isOpen = ref(false);
const amount = ref(0);
const validationErrors = ref([]);

const loading = ref(true);
const error = ref(null);
const charges = ref("");
const chargesDate = ref("");
const chargesDec = ref("");
const chargeType = ref("charge");
const isDiscountOpen = ref(false);
const receipt = ref(null)
const maxFileSize = 2 * 1024 * 1024 // 2 MB
let isChargesOpen = ref(false);
let isEidOpen = ref(false);
let isLicenseOpen = ref(false);


const agentData = computed(() => store.getters["customer/customer"]);
const isSaving = computed(() => store.getters["user/isSaving"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const bookings = computed(() => store.getters["flight/bookingData"]);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const agentDepositTotals = computed(() => store.getters["deposit/totalApprovedDeposit"]);




const totalApprovedDeposit = computed(
    () => store.getters["deposit/totalApprovedDeposit"],
);
function fetchAgentLedger() {
    if (route.query.customer_id) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
                userId: route.query.customer_id,
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


function updateCardAllowance() {
    const newStatus = !agentData?.value.is_card_allowed;

    try {
        store.dispatch(`user/${UPDATE_CARD_ALLOWANCE}`, {
            is_card_allowed: newStatus ? 1 : 0,
            userId: route.query.customer_id,
        });
    } catch (err) {
        console.error("Failed to update user status:", err);
        // Optionally, show an error message to the user
    }
    fetchAgent();
}

function fetchTotalApprovedDepost() {
    if (route.query.customer_id) {
        try {
            store.dispatch(`deposit/${FETCH_TOTAL_APPROVED_DEPOSIT}`, {
                userId: route.query.customer_id,
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

function fetchAgent() {
    if (route.query.customer_id) {
        console.log('Fetching agent with ID: ' + route.query.customer_id);
        try {
            store.dispatch(`customer/${FETCH_CUSTOMER_DATA}`, {
                id: route.query.customer_id,
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
function openDialog() {
    isOpen.value = true;
}
function openDiscountDialog() {
    isDiscountOpen.value = true;
}

function closeDialog() {
    isOpen.value = false;
}
function closeDiscountDialog() {
    isDiscountOpen.value = false;
}
function openChargesDialog() {
    isChargesOpen.value = true;
}

function closeChargesDialog() {
    isChargesOpen.value = false;
}


function openEidDialog() {
    isEidOpen.value = true;
}

function closeEidDialog() {
    isEidOpen.value = false;
}


function openLicenseDialog() {
    isLicenseOpen.value = true;
}


function closeLicenseDialog() {
    isLicenseOpen.value = false;
}

function setMargin() {
    let errors = [];

    if (amount.value === "" || amount.value === null || amount.value === undefined) {
        errors.push("Discount amount is required.");
    }

    if (errors.length > 0) {
        validationErrors.value = errors;
        return;
    }

    validationErrors.value = [];

    store
        .dispatch("user/" + SAVE_AGENT_MARGIN, {
            margin_amount: amount.value,
            agentId: route.query.customer_id,
        })
        .then(() => {
            closeDialog();
            amount.value = null;
            fetchAgent();
        });
}

function setDiscount() {
    let errors = [];

    if (discount.value === "" || discount.value === null || discount.value === undefined) {
        errors.push("Discount amount is required.");
    }


    if (errors.length > 0) {
        validationErrors.value = errors;
        return;
    }

    validationErrors.value = [];

    store
        .dispatch("user/" + SAVE_AGENT_MARGIN, {
            discount_amount: discount.value,
            agentId: route.query.customer_id,
        })
        .then(() => {
            closeDiscountDialog();
            discount.value = null;
            fetchAgent();
        });
}
const capitalize = (string) => {
    return string.charAt(0).toUpperCase() + string.slice(1);
};
function saveCharges() {
    let errors = [];

    if (!charges.value) {
        errors.push("Amount is required.");
    }
    if (!chargesDate.value) {
        errors.push("Date is required.");
    }
    if (!chargesDec.value) {
        errors.push("Description is required.");
    }

    if (errors.length > 0) {
        validationErrors.value = errors;
        return;
    }

    validationErrors.value = [];
    // Create FormData object
    const formData = new FormData();
    formData.append("amount", charges.value);
    formData.append("date", chargesDate.value);
    formData.append("additional_details", chargesDec.value); // match backend field
    formData.append("chargeType", chargeType.value);       // match backend field
    formData.append("user_id", route?.query.customer_id);

    if (receipt.value) {
        formData.append("receipt", receipt.value);
    }

    store
        .dispatch("user/" + SAVE_AGENT_CHARGES, formData)
        .then(() => {
            closeChargesDialog();

            charges.value = null;
            chargesDate.value = null;
            chargesDec.value = null;
            receipt.value = null;

            fetchAgentLedger();
        });
}


// function updateUserStatus(event) {
//     const usreStatus = event.target.checked ? 1 : 0;

//     store.dispatch("user/" + UPDATE_USER_STATUS, {
//         status: usreStatus,
//         userId: route.query.customer_id,
//     });
// }

function updateUserStatus() {
    const newStatus = !agentData?.value.is_approved;

    try {
        store.dispatch(`user/${UPDATE_USER_STATUS}`, {
            status: newStatus ? 1 : 0,
            userId: route.query.customer_id,
        });
    } catch (err) {
        console.error("Failed to update user status:", err);
        // Optionally, show an error message to the user
    }
    fetchAgent();
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "decimal",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};


function handleFileUpload(event) {
    const file = event.target.files[0]
    error.value = null

    if (!file) {
        receipt.value = null
        return
    }

    // Check type
    if (file.type !== "application/pdf") {
        error.value = "Only PDF files are allowed."
        receipt.value = null
        return
    }

    // Check size
    if (file.size > maxFileSize) {
        error.value = "File size must not exceed 2 MB."
        receipt.value = null
        return
    }

    // If valid, store file
    receipt.value = file
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};
function fetchBookingsData() {
    if (!route.query.customer_id) {
        error.value = "No user ID provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        userRole: "agent",
        userId: route.query.customer_id,
    });
}

onMounted(() => {
    // fetchUser();

    fetchAgentLedger();
    fetchAgent();
    fetchTotalApprovedDepost();
    fetchBookingsData();
    //fetchAgentDeposits();

});
</script>
<template>


    <div class="min-h-screen p-6">
        <div class="max-w-full mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-4">
                    <Button @click="
                        authUser?.role === 'salesman'
                            ? $router.push({ name: 'SalesmanUsers' })
                            : authUser?.role === 'reservation'
                                ? $router.push({ name: 'ReservationUsers' })
                                : authUser?.role === 'admin'
                                    ? $router.push({ name: 'Users' })
                                    : null
                        " variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-1" />
                        Back
                    </Button>
                    <h1 class="text-3xl font-medium leading-none tracking-tight text-gray-900">
                        User Details
                    </h1>
                </div>
            </div>
            <div v-if="loading" class="text-center py-10">
                <p class="text-lg text-gray-600">Loading user details...</p>
            </div>

            <div v-else-if="error" class="text-center py-10">
                <p class="text-lg text-red-600">{{ error }}</p>
            </div>

            <div v-else-if="agentData" class="space-y-8">
                <!-- Profile Header Card -->
                <div class="bg-white rounded-xl border shadow-sm p-6">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                        <!-- Logo -->
                        <div class="flex items-center gap-4">
                            <div
                                class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden border">
                                <img v-if="agentData?.agent_data?.logo" :src="agentData.agent_data.logo"
                                    :alt="`Logo of ${agentData.agent_data.company_name}`"
                                    class="w-full h-full object-contain p-2" />
                                <UserIcon v-else class="w-12 h-12 text-gray-400" />
                            </div>

                            <div>
                                <h2 class="text-xl font-bold text-gray-900">
                                    {{ agentData.name }} {{ agentData.last_name }}
                                </h2>
                                <p class="text-sm text-gray-500">
                                    {{ agentData.agent_data?.company_name || 'Individual Customer' }}
                                </p>
                                <p class="text-xs text-muted-foreground mt-1">
                                    Member since {{ formatDate(agentData.created_at) }}
                                </p>
                            </div>
                        </div>

                        <!-- Contact Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Agent UID</p>
                                <p class="font-medium">{{ agentData.user?.id || agentData.id }}</p>
                            </div>
                            <div v-if="agentData?.preferred_currency">
                                <p class="text-gray-500">Preferred Currency</p>
                                <p class="font-medium">{{ agentData?.preferred_currency  }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Email</p>
                                <p class="font-medium">{{ agentData.email }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone</p>
                                <p class="font-medium">{{ agentData.phone || 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-gray-500">Address</p>
                                <p class="font-medium">{{ agentData.address || 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-2">
                            <Button v-if="authUser.role === 'admin' || authUser.role === 'reservation'"
                                class="text-white" @click="openChargesDialog" type="button">Add Charges</Button>

                        </div>
                        <Dialog class="bg-white" :open="isChargesOpen" @update:open="isChargesOpen = $event">

                            <DialogContent class="sm:max-w-[425px] bg-white">
                                <DialogHeader>
                                    <DialogTitle class="text-2xl">Add Charges</DialogTitle>
                                </DialogHeader>
                                <div v-if="validationErrors.length > 0">
                                    <ul
                                        class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                        <li v-for="error in validationErrors" :key="error.id">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                                <form @submit.prevent="saveCharges">
                                    <div class="mb-3">
                                        <Label for="chargeType">Type</Label>
                                        <Select v-model="chargeType">
                                            <SelectTrigger class="w-full bg-white mt-2 border rounded px-3 py-2"
                                                id="chargeType">
                                                <SelectValue placeholder="Select Type" />
                                            </SelectTrigger>
                                            <SelectContent class="bg-white">
                                                <SelectGroup>
                                                    <SelectItem value="charge">Charge</SelectItem>
                                                    <SelectItem value="refund">Refund</SelectItem>
                                                    <SelectItem value="ok_to_board">OK To Board</SelectItem>
                                                    <SelectItem value="re_issue">Re-issue</SelectItem>
                                                    <SelectItem value="void">Void</SelectItem>
                                                    <SelectItem value="umrah">Umrah</SelectItem>
                                                    <SelectItem value="visa">Visa</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Amount in </Label>
                                        <Input class="" type="number" v-model="charges" id="charges"
                                            placeholder="Amount in " />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Date</Label>
                                        <Input class="" type="date" v-model="chargesDate" id="chargesDate" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="receipt">Upload Receipt (PDF only)</Label>
                                        <Input type="file" id="receipt" @change="handleFileUpload" />
                                    </div>

                                    <div class="mb-3">
                                        <Label for="Description">Description</Label>
                                        <Textarea class="bg-white" type="text" v-model="chargesDec" id="chargesDec"
                                            placeholder="Description" />
                                    </div>
                                    <Button :disabled="!isFormValid && isSaving" type="submit"
                                        class="float-right text-white">
                                        {{ isSaving ? 'Saving...' : 'Save' }}
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>

                </div>



                <!-- Financial Summary -->
                <div class="bg-white rounded-xl border shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Financial Summary</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <p class="text-sm text-blue-600 font-medium">Current Balance</p>
                            <p class="text-2xl font-bold text-blue-800 mt-1">
                                {{ formatAmount(agentLedger?.balance) }}
                            </p>
                        </div>

                        <div
                            class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <p class="text-sm text-green-600 font-medium">Approved Deposits</p>
                            <p class="text-2xl font-bold text-green-800 mt-1">
                                {{ formatAmount(agentDepositTotals?.totalApprovedDeposits) }}
                            </p>
                        </div>

                        <div
                            class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
                            <p class="text-sm text-yellow-600 font-medium">Pending Deposits</p>
                            <p class="text-2xl font-bold text-yellow-800 mt-1">
                                {{ formatAmount(agentDepositTotals?.totalPendingDeposits) }}
                            </p>
                        </div>

                        <div
                            class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                            <p class="text-sm text-purple-600 font-medium">Total Bookings</p>
                            <p class="text-2xl font-bold text-purple-800 mt-1">
                                {{ bookings?.total_count || 0 }}
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Bookings Overview -->
                <div class="bg-white rounded-xl border shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Bookings Overview</h3>
                        <Button v-if="authUser?.role === 'admin'"
                            @click="$router.push({ name: 'AdminCustomerBookings', query: { userId: agentData?.user?.id, userRole: agentData?.role } })"
                            size="sm" class="flex items-center gap-2">
                            <ShoppingCart class="w-4 h-4" />
                            View All
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- All Bookings -->
                        <div @click="filterBookings('all')"
                            class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md border"
                            :class="{ 'ring-2 ring-gray-600 bg-gray-100': activeFilter === 'all' }">
                            <CalendarIcon class="w-9 h-9 text-gray-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">All Bookings</p>
                                <p class="text-xl font-bold text-gray-900">{{ bookings?.total_count || 0 }}</p>
                            </div>
                        </div>

                        <!-- Ticketed -->
                        <div @click="filterBookings('ticketed')"
                            class="bg-emerald-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md border"
                            :class="{ 'ring-2 ring-emerald-600 bg-emerald-100': activeFilter === 'ticketed' }">
                            <CheckCircleIcon class="w-9 h-9 text-emerald-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-emerald-700">Ticketed</p>
                                <p class="text-xl font-bold text-emerald-900">{{ bookings?.total_ticketed || 0 }}</p>
                            </div>
                        </div>

                        <!-- Canceled -->
                        <div @click="filterBookings('canceled')"
                            class="bg-red-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md border"
                            :class="{ 'ring-2 ring-red-600 bg-red-100': activeFilter === 'canceled' }">
                            <Ban class="w-9 h-9 text-red-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-red-700">Canceled</p>
                                <p class="text-xl font-bold text-red-900">{{ bookings?.total_canceled || 0 }}</p>
                            </div>
                        </div>

                        <!-- On Hold -->
                        <div @click="filterBookings('booked')"
                            class="bg-amber-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md border"
                            :class="{ 'ring-2 ring-amber-600 bg-amber-100': activeFilter === 'booked' }">
                            <CirclePause class="w-9 h-9 text-amber-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-amber-700">On Hold</p>
                                <p class="text-xl font-bold text-amber-900">{{ bookings?.total_booked || 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Deposits Overview -->
                <div class="bg-white rounded-xl border shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Deposits Overview</h3>
                        <Button v-if="authUser?.role === 'admin'"
                            @click="$router.push({ name: 'AgentDetailDeposits', query: { userId: agentData.id, userRole: agentData.role } })"
                            size="sm" class="flex items-center gap-2">
                            <Receipt class="w-4 h-4" />
                            View All
                        </Button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center">
                            <DollarSign class="w-9 h-9 text-blue-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-blue-700">Current Balance</p>
                                <p class="text-xl font-bold text-blue-900">
                                    {{ formatAmount(agentLedger?.balance) }}
                                </p>
                            </div>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 flex items-center">
                            <CheckCircle2 class="w-9 h-9 text-green-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-green-700">Approved Deposits</p>
                                <p class="text-xl font-bold text-green-900">
                                    {{ formatAmount(agentDepositTotals?.totalApprovedDeposits) }}
                                </p>
                            </div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center">
                            <Clock class="w-9 h-9 text-yellow-600 mr-3" />
                            <div>
                                <p class="text-sm font-medium text-yellow-700">Pending Deposits</p>
                                <p class="text-xl font-bold text-yellow-900">
                                    {{ formatAmount(agentDepositTotals?.totalPendingDeposits) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                            <tr v-for="(
transaction, index
                                ) in agentLedger?.ledger" :key="index" class="border-t">
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ transaction.date }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{
                                        capitalize(transaction?.transaction_type)
                                    }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ transaction?.reference_id }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ transaction?.details }}
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
                                        transaction.credit !== "0"
                                            ? formatAmount(transaction.credit)
                                            : "-"
                                    }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800">
                                    {{ formatAmount(transaction?.balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
