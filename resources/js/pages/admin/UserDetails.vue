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
    UPDATE_CARD_ALLOWANCE
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


const agentData = computed(() => store.getters["user/agentData"]);
const isSaving = computed(() => store.getters["user/isSaving"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const bookings = computed(() => store.getters["flight/bookingData"]);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const agentDepositTotals = computed(() => store.getters["deposit/totalApprovedDeposit"]);




const totalApprovedDeposit = computed(
    () => store.getters["deposit/totalApprovedDeposit"],
);
function fetchAgentLedger() {
    if (route.query.user_id) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
                userId: route.query.user_id,
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
            userId: route.query.user_id,
        });
    } catch (err) {
        console.error("Failed to update user status:", err);
        // Optionally, show an error message to the user
    }
    fetchAgent();
}

function fetchTotalApprovedDepost() {
    if (route.query.user_id) {
        try {
            store.dispatch(`deposit/${FETCH_TOTAL_APPROVED_DEPOSIT}`, {
                userId: route.query.user_id,
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
    if (route.query.user_id) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: route.query.user_id,
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
            agentId: route.query.user_id,
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
            agentId: route.query.user_id,
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
    formData.append("user_id", route.query.user_id);

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

            fetchAgent();
        });
}


// function updateUserStatus(event) {
//     const usreStatus = event.target.checked ? 1 : 0;

//     store.dispatch("user/" + UPDATE_USER_STATUS, {
//         status: usreStatus,
//         userId: route.query.user_id,
//     });
// }

function updateUserStatus() {
    const newStatus = !agentData?.value.is_approved;

    try {
        store.dispatch(`user/${UPDATE_USER_STATUS}`, {
            status: newStatus ? 1 : 0,
            userId: route.query.user_id,
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
    if (!route.query.user_id) {
        error.value = "No user ID provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        userRole: "agent",
        userId: route.query.user_id,
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

            <div v-else-if="agentData" class="bg-white rounded-lg border p-6 mb-6">

                <!-- User Profile Section -->
                <div class="flex items-center justify-between mb-8">
                    <div class="w-24 h-24 flex items-center justify-center">
                        <div v-if="agentData">
                            <img :src="agentData?.agent_data?.logo"
                                :alt="`Profile picture of ${agentData?.agent_data?.company_name}`"
                                class="w-28 h-auto bg-gray-200 object-contain p-4" />
                        </div>
                        <div v-else>
                            <UserIcon class="h-6 w-6 text-gray-500" />
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm">
                            Agent Uid:
                            {{ agentData?.agent_data?.agent_uid }}
                        </p>
                        <p class="text-sm">{{ agentData?.email }}</p>
                        <p class="text-sm">
                            Company Name:
                            {{ agentData?.agent_data?.company_name }}
                        </p>
                        <p class="text-sm">
                            CEO Name: {{ agentData?.agent_data?.ceo_name }}
                        </p>
                        <p class="text-sm">
                            Member Since
                            {{ formatDate(agentData?.created_at) }}
                        </p>
                        <p class="text-sm">
                            Agent Code:
                            {{ agentData?.agent_code }}
                        </p>


                    </div>
                    <div class="ml-4">
                        <p class="text-sm">
                            Mobile: {{ agentData?.agent_data?.mobile }}
                        </p>
                        <p class="text-sm">
                            CEO Contact:
                            {{ agentData?.agent_data?.ceo_contact }}
                        </p>
                        <p class="text-sm">
                            Government Number:
                            {{ agentData?.agent_data?.govt_number }}
                        </p>
                        <p class="text-sm">
                            Address
                            {{ agentData?.agent_data?.address }}
                        </p>
                    </div>
                    <div class="flex flex-col space-y-2">
                        <Button v-if="authUser.role === 'admin' || authUser.role === 'reservation'" class="text-white"
                            @click="openChargesDialog" type="button">Add Charges</Button>
                        <Button class="text-white" @click="openEidDialog" type="button">View E-Id</Button>
                        <Button class="text-white" @click="openLicenseDialog" type="button">View Trader license</Button>


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
                                    <Input type="file" id="receipt" @change="handleFileUpload"
                                        />
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

                    <Dialog class="" :open="isEidOpen" @update:open="isEidOpen = $event">

                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle class="text-2xl">Emirates ID</DialogTitle>
                            </DialogHeader>
                            <img :src=agentData?.agent_data?.e_id>


                        </DialogContent>
                    </Dialog>



                    <Dialog class="" :open="isLicenseOpen" @update:open="isLicenseOpen = $event">

                        <DialogContent class="sm:max-w-[625px]">
                            <DialogHeader>
                                <DialogTitle class="text-2xl">Trade Licenses</DialogTitle>
                            </DialogHeader>
                            <img :src=agentData?.agent_data?.trade_license>


                        </DialogContent>
                    </Dialog>
                </div>

                <!-- Financial Information -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Balance</p>
                        <p class="text-2xl font-semibold">
                            
                            {{ formatAmount(agentLedger?.balance) }}
                        </p>
                    </div>
                    <div v-if="authUser && authUser.role === 'admin'"
                        class="p-4 bg-gray-50 rounded-lg flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Price Margin
                            </p>
                            <p class="text-2xl font-semibold">
                                
                                {{
                                    formatAmount(
                                        agentData?.agent_data?.margin_amount,
                                    )
                                }}
                            </p>
                        </div>
                        <Dialog :open="isOpen" @update:open="isOpen = $event">
                            <Button class="text-white" @click="openDialog" type="button">Set Margin</Button>
                            <DialogContent class="sm:max-w-[425px] ">
                                <DialogHeader>
                                    <DialogTitle class="text-2xl">Set Margin</DialogTitle>
                                </DialogHeader>
                                <div v-if="validationErrors.length > 0">
                                    <ul
                                        class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                        <li v-for="error in validationErrors" :key="error.id">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                                <form @submit.prevent="setMargin">
                                    <div class="mb-3">
                                        <Label for="amount">Amount in </Label>
                                        <Input class="mt-4" type="number" v-model="amount" id="amount"
                                            placeholder="Amount" />
                                    </div>
                                    <Button type="submit" class="float-right">
                                        Save
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                    <div v-if="authUser && authUser.role === 'admin'"
                        class="p-4 bg-gray-50 rounded-lg flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">
                                Price Discount
                            </p>
                            <p class="text-2xl font-semibold">
                                
                                {{
                                    formatAmount(
                                        agentData?.agent_data?.agent_discount,
                                    )
                                }}
                            </p>
                        </div>
                        <Dialog :open="isDiscountOpen" @update:open="isDiscountOpen = $event">
                            <Button class="text-white" @click="openDiscountDialog" type="button">Set Discount</Button>
                            <DialogContent class="sm:max-w-[425px] ">
                                <DialogHeader>
                                    <DialogTitle class="text-2xl">Set Discount</DialogTitle>
                                </DialogHeader>
                                <div v-if="validationErrors.length > 0">
                                    <ul
                                        class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                        <li v-for="error in validationErrors" :key="error.id">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                                <form @submit.prevent="setDiscount">
                                    <div class="mb-3">
                                        <Label for="discount">Amount in </Label>
                                        <Input class="mt-4" type="number" v-model="discount" id="discount"
                                            placeholder="Amount" />
                                    </div>
                                    <Button type="submit" class="float-right">
                                        Save
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>

                    <div v-if="authUser && authUser.role === 'admin'" class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Status</p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div :class="[
                                    'h-3 w-3 rounded-full mr-2',
                                    agentData.is_approved
                                        ? 'bg-gray-500'
                                        : 'bg-yellow-500',
                                ]"></div>
                                <span>{{
                                    agentData.is_approved
                                        ? "Approved"
                                        : "Pending"
                                }}</span>
                            </div>
                            <div>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :checked="agentData.is_approved" @change="updateUserStatus"
                                        class="sr-only peer" />
                                    <div
                                        class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <div v-if="authUser && authUser.role === 'admin'" class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500 mb-1">Card Payment Allowance</p>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div :class="[
                                    'h-3 w-3 rounded-full mr-2',
                                    agentData.is_card_allowed
                                        ? 'bg-gray-500'
                                        : 'bg-yellow-500',
                                ]"></div>
                                <span>{{
                                    agentData.is_card_allowed
                                        ? "Allowed"
                                        : "Not Allowed"
                                }}</span>
                            </div>
                            <div>
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :checked="agentData.is_card_allowed"
                                        @change="updateCardAllowance" class="sr-only peer" />
                                    <div
                                        class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="p-4">
                        <button
                            @click="openAgentDialog"
                            class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                        >
                            Open Agent Dialog
                        </button>

                        <div
                            v-if="isAgentOpen"
                            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center"
                        >
                            <div class="bg-white rounded-lg p-6 w-96">
                                <h2 class="text-2xl font-bold mb-4">
                                    Set Agent Number
                                </h2>

                                <div class="mb-4">
                                    <label
                                        for="agentNumber"
                                        class="block text-sm font-medium text-gray-700"
                                        >Agent Number</label
                                    >
                                    <Input
                                        type="text"
                                        id="agentNumber"
                                        v-model="agentNumber"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50"
                                    />
                                </div>

                                

                                <div class="flex justify-end space-x-2">
                                    <button
                                        @click="closeAgentDialog"
                                        class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        @click="saveAgent"
                                        class="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-opacity-50"
                                    >
                                        Save
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div> -->
                </div>
                <div class="">
                    <div class="max-w-full mx-auto">
                        <div class="bg-white rounded-lg overflow-hidden">
                            <div class="">
                                <div class="flex items-center gap-4 justify-between mb-2">
                                    <h1 class="text-1xl font-bold text-gray-800">
                                        Bookings Overview
                                    </h1>
                                    <Button v-if="authUser && authUser.role === 'admin'" @click="
                                        $router.push({
                                            name: 'AgentDetailBookings',
                                            query: {
                                                userId: agentData?.id,
                                                userRole: agentData?.role,

                                            },
                                        })
                                        " class="flex gap-2 text-white text-xs">
                                        <ShoppingCart class="h-4 w-4 text-white" />
                                        View All
                                    </Button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <!-- Total Bookings -->
                                    <div class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                        :class="{ 'ring-2 ring-gray-500': activeFilter === 'all' }"
                                        @click="filterBookings('all')">
                                        <CalendarIcon class="h-8 w-8 text-gray-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-600">
                                                All Bookings
                                            </p>
                                            <p class="text-2xl font-bold text-gray-800">
                                                {{ bookings?.total_count || 0 }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Ticketed -->
                                    <div class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                        :class="{ 'ring-2 ring-gray-500': activeFilter === 'ticketed' }"
                                        @click="filterBookings('ticketed')">
                                        <CheckCircleIcon class="h-8 w-8 text-gray-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-600">
                                                Ticketed
                                            </p>
                                            <p class="text-2xl font-bold text-gray-800">
                                                {{ bookings?.total_ticketed || 0 }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Total Canceled -->
                                    <div class="bg-red-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                        :class="{ 'ring-2 ring-red-500': activeFilter === 'canceled' }"
                                        @click="filterBookings('canceled')">
                                        <Ban class="h-8 w-8 text-red-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-red-600">
                                                Total Canceled
                                            </p>
                                            <p class="text-2xl font-bold text-red-800">
                                                {{ bookings?.total_canceled || 0 }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- On Hold -->
                                    <div class="bg-yellow-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                        :class="{ 'ring-2 ring-yellow-500': activeFilter === 'booked' }"
                                        @click="filterBookings('booked')">
                                        <CirclePause class="h-8 w-8 text-yellow-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-yellow-600">
                                                On Hold
                                            </p>
                                            <p class="text-2xl font-bold text-yellow-800">
                                                {{ bookings?.total_booked || 0 }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Company Information -->
                <!-- <div v-if="agentData.agent_data" class="border-t pt-6">
                    <h3 class="text-lg font-semibold mb-4">Company Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Company Name
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.company_name }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        CEO Name
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.ceo_name }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Mobile
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.mobile }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Government Number
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.govt_number }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <div>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Company Email
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.company_email }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        CEO Contact
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.ceo_contact }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Address
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ agentData.agent_data.address }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm text-gray-500">
                                        Member Since
                                    </dt>
                                    <dd class="text-sm font-medium">
                                        {{ formatDate(agentData.created_at) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div> -->
                <div class="mt-4">
                    <div class="max-w-full mx-auto">
                        <div class="bg-white rounded-lg overflow-hidden">
                            <div class="">

                                <div class="flex items-center gap-4 justify-between mb-2">
                                    <h1 class="text-1xl font-bold text-gray-800">
                                        Deposits Overview
                                    </h1>
                                    <Button v-if="authUser && authUser.role === 'admin'" @click="
                                        $router.push({
                                            name: 'AgentDetailDeposits',
                                            query: {
                                                userId: agentData?.id,
                                                userRole: agentData?.role,

                                            },
                                        })
                                        " class="flex gap-2 text-white text-xs">
                                        <ShoppingCart class="h-4 w-4 text-white" />
                                        View All
                                    </Button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="bg-gray-50 rounded-lg p-4 flex items-center">
                                        <Receipt class="h-8 w-8 text-gray-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-600">
                                                Balance
                                            </p>
                                            <p class="text-2xl font-bold text-gray-800">
                                                
                                                {{
                                                    formatAmount(
                                                        agentLedger?.balance,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 rounded-lg p-4 flex items-center">
                                        <CheckCircleIcon class="h-8 w-8 text-gray-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-gray-600">
                                                Approved Deposits
                                            </p>

                                            <p class="text-2xl font-bold text-gray-800">
                                                
                                                {{
                                                    formatAmount(
                                                        agentDepositTotals?.totalApprovedDeposits,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-4 flex items-center">
                                        <Receipt class="h-8 w-8 text-yellow-500 mr-4" />
                                        <div>
                                            <p class="text-sm font-medium text-yellow-600">
                                                Total Pending Deposits
                                            </p>
                                            <p class="text-2xl font-bold text-yellow-800">
                                                {{
                                                    formatAmount(
                                                        agentDepositTotals?.totalPendingDeposits,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
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
