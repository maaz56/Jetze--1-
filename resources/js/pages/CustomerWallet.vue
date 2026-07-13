<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RefreshCcw, Search, ArrowLeft, ImageIcon, UploadIcon, SaveIcon, UserPlusIcon, CalendarIcon, CheckCircleIcon, Receipt, EyeIcon, TrashIcon, LoaderIcon, InboxIcon, Printer, Download, Share, CreditCard, ExternalLink } from "lucide-vue-next";
import { useAuthStore } from "@/services/stores/auth";
import { useStore } from "vuex";
import { computed, onMounted, ref, watch, nextTick } from "vue";
import { useRoute } from "vue-router";
import jsPDF from "jspdf";
import html2canvas from "html2canvas";
import { formatAmount, cn } from "@/lib/utils";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    FETCH_DEPOSIT_DATA,
    SAVE_DEPOSIT_DATA,
    DELETE_DEPOSIT_DATA,
    FETCH_AGENT_LEDGER,
    FETCH_BANKS,
    INITIALIZE_ABHI_PAY,
    CHECK_PAYMENT_STATUS,
} from "@/services/store/actions.type";
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { CalendarDate, DateFormatter, getLocalTimeZone } from '@internationalized/date';
import Calender from "@/components/common/Calender.vue";

const df = new DateFormatter('en-US', { dateStyle: 'medium' });

const value = ref({
    start: new CalendarDate(2022, 1, 20),
    end: new CalendarDate(2022, 1, 20).add({ days: 20 }),
});

const authStore = useAuthStore();
const store = useStore();
const route = useRoute();
const user = computed(() => authStore.user);
const hideButtons = ref(false);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const showDialog = ref(false);
const selectedDeposit = ref(null);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const banks = computed(() => store.getters["bank/banks"] || []);
const abhiPayResponse = computed(() => store.getters["payment/abhiPayResponse"]);
const deposit = computed(() => store.getters["payment/deposit"]);
const isCheckingPaymentStatus = computed(() => store.getters["payment/isCheckingPaymentStatus"]);
const date = ref(new Date().toISOString().slice(0, 10));
const amount = ref("");
const receiptImage = ref(null);
const paymentType = ref();
const selectedBank = ref(null);
const additionalDetails = ref("");
const user_id = computed(() => user.value?.id);
const loading = ref(true);
const error = ref(null);
const isLoading = ref(false);
const formErrors = ref([]);

const isBankTransferType = (type) => {
    if (!type) return false;
    const normalizedType = String(type).trim().toLowerCase();
    return normalizedType !== "abhipay-deposit" && normalizedType !== "cash";
};

// Abhi Pay specific refs
const abhiPayDate = ref(new Date().toISOString().slice(0, 10));
const abhiPayAmount = ref("");
const abhiPayAdditionalDetails = ref("");
const abhiPayCurrency = ref("PKR");
const generatedBillId = ref();
const showBillId = ref(false);
const banksWithOneBill = computed(() => {
    return [
        {
            id: "onebill",
            bank_name: "1Bill ",
            account_title: "Instant Digital Payment",
            account_number: "-",
            iban: "-",
            currency: "PKR",
            isOneBill: true,
        },
        ...(banks.value || [])
    ];
});
watch(abhiPayResponse, (newResponse) => {
    if (newResponse?.response?.payload?.consumerNumber) {
        generatedBillId.value = newResponse.response.payload.consumerNumber;
        showBillId.value = true;
    }
});
watch(deposit, (newDeposit) => {
    if (newDeposit) {
        selectedDeposit.value = newDeposit;
    }
});
watch(paymentType, (newType) => {
    if (!isBankTransferType(newType)) {
        receiptImage.value = null;
    }
});

async function initializeAbhiPay() {
    formErrors.value = [];
    if (!amount.value) formErrors.value.push("Amount field is required.");
    const numericAmount = Number(amount.value);
    if (Number.isNaN(numericAmount) || numericAmount < 500) {
        formErrors.value.push("Amount must be at least 500 for OneBill.");
    }
    if (formErrors.value.length > 0) return;
    paymentType.value = "abhipay-deposit";
    isLoading.value = true;
    try {
        await store.dispatch('payment/' + INITIALIZE_ABHI_PAY, {
            amount: amount.value,
            currency: 'PKR',
            payment_type: 'abhipay-deposit',
            callback_url: `${window.location.origin}/customer-payment-view?booking_id=${route.query.booking_id || ''}&flight_mode=${route.query.flight_mode || ''}&flight_id=${route.query.flight_id || ''}&flight_provider=${route.query.flight_provider || ''}&booking_source=${route.query.booking_source || ''}&pnr=${route.query.pnr || ''}&payment=true`,

            agent_id: user.value.id,
            date: date.value,
            additional_details: additionalDetails.value

        });
    } catch (err) {
        console.error("Error initializing Abhi Pay1 7:", err);
        error.value = "Failed to initialize Abhi Pay. Please try again.";
    } finally {
        isLoading.value = false;
        fetchAgentDeposits();
    }
}

function resetAbhiPayForm() {
    abhiPayDate.value = new Date().toISOString().slice(0, 10);
    abhiPayAmount.value = "";
    abhiPayAdditionalDetails.value = "";
    generatedBillId.value = "";
    showBillId.value = false;
}

function openDialog(deposit) {
    selectedDeposit.value = deposit;
    showDialog.value = true;
}

function closeDialog() {
    showDialog.value = false;
    selectedDeposit.value = null;
}

function fetchBanks() {
    store.dispatch("bank/" + FETCH_BANKS);
}

const handleReceiptImage = (event) => {
    receiptImage.value = event.target.files[0];
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "decimal",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, { userId: user_id.value });
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

const selectBank = (bank) => {
    selectedBank.value = bank;

    if (bank.isOneBill) {
        paymentType.value = "abhipay-deposit";
        receiptImage.value = null;
    } else {
        paymentType.value = bank
            ? `${bank.bank_name} - ${bank.account_title} - ${bank.account_number}`
            : "";
    }
};

async function handleDepositData() {
    formErrors.value = [];
    if (!date.value) formErrors.value.push("Date field is required.");
    if (!amount.value) formErrors.value.push("Amount field is required.");
    if (!paymentType.value) formErrors.value.push("Payment type is required.");
    if (formErrors.value.length > 0) return;

    loading.value = true;
    error.value = null;

    try {
        const depositData = new FormData();
        depositData.append("date", date.value);
        depositData.append("amount", amount.value);
        if (receiptImage.value) {
            depositData.append("receipt_image", receiptImage.value);
        }
        depositData.append("payment_type", paymentType.value);
        depositData.append("additional_details", additionalDetails.value);
        depositData.append("agent_id", user.value.id);
        depositData.append("currency", selectedBank.value?.currency || "PKR");
        depositData.append("payment_method", "bank");

        await store.dispatch(`deposit/${SAVE_DEPOSIT_DATA}`, depositData);
        date.value = new Date().toISOString().slice(0, 10);
        amount.value = "";
        receiptImage.value = null;
        paymentType.value = "";
        additionalDetails.value = "";
        await fetchAgentDeposits();
    } catch (err) {
        console.error("Error saving deposit data:", err);
        error.value = "Failed to save deposit data. Please try again.";
    } finally {
        loading.value = false;
    }
}

async function fetchAgentDeposits() {
    if (!user_id.value) {
        error.value = "No user ID provided.";
        return;
    }
    loading.value = true;
    error.value = null;
    try {
        await store.dispatch(`deposit/${FETCH_DEPOSIT_DATA}`, { userId: user_id.value });
    } catch (err) {
        console.error("Error fetching agent deposits:", err);
        error.value = "Failed to load user deposit data. Please try again.";
    } finally {
        loading.value = false;
    }
}

async function deleteDeposit(id) {
    loading.value = true;
    error.value = null;
    try {
        await store.dispatch(`deposit/${DELETE_DEPOSIT_DATA}`, { id });
        fetchAgentDeposits();
    } catch (err) {
        console.error("Error deleting deposit:", err);
        error.value = "Failed to delete deposit. Please try again.";
    } finally {
        loading.value = false;
    }
}

async function captureDialogAsImage() {
    const dialogElement = document.querySelector("#deposit-dialog");
    if (!dialogElement) return;

    try {
        hideButtons.value = true;
        await nextTick();
        const canvas = await html2canvas(dialogElement, {
            backgroundColor: null,
            scale: 2,
        });
        const imageData = canvas.toDataURL("image/png");
        const link = document.createElement("a");
        link.href = imageData;
        link.download = `Deposit_Details_${selectedDeposit?.id}.png`;
        link.click();
        hideButtons.value = false;
        return imageData;
    } catch (error) {
        console.error("Error capturing dialog:", error);
        hideButtons.value = false;
    }
}
function checkPaymentStatus(type) {
    store?.dispatch('payment/' + CHECK_PAYMENT_STATUS, {
        paymentMethod: type,
        deposit_id: selectedDeposit?.value?.id,
    }).then(() => {
        fetchAgentDeposits();
        fetchAgentLedger();

    })
}
async function shareDialogOnWhatsApp() {
    const imageData = await captureDialogAsImage();
    if (imageData) {
        const whatsappURL = `https://wa.me/?text=${encodeURIComponent("Check out the deposit details:")}`;
        window.open(whatsappURL, "_blank");
    }
}

onMounted(() => {
    if (user.value?.id) {
        fetchAgentDeposits();
        fetchAgentLedger();
        fetchBanks();
    }
});

watch(user, (newUser) => {
    if (newUser?.id) {
        fetchAgentDeposits();
        fetchAgentLedger();
    }
});
</script>

<template>


    <div class="space-y-6">
        <div class="max-w-full mx-auto">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        Deposits Overview
                    </h1>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-primary/10 rounded-lg p-4 flex items-center">
                            <Receipt class="h-8 w-8 text-primary mr-4" />
                            <div>
                                <p class="text-sm font-medium text-primary">Balance</p>
                                <p class="text-2xl font-bold text-primary">
                                    {{ formatAmount(agentLedger?.balance || 0) }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 flex items-center">
                            <CheckCircleIcon class="h-8 w-8 text-green-500 mr-4" />
                            <div>
                                <p class="text-sm font-medium text-green-600">Approved Deposits</p>
                                <p class="text-2xl font-bold text-green-800">
                                    {{ formatAmount(agentDepositData?.totalApprovedDeposits || 0) }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 flex items-center">
                            <Receipt class="h-8 w-8 text-yellow-500 mr-4" />
                            <div>
                                <p class="text-sm font-medium text-yellow-600">Total Pending Deposits</p>
                                <p class="text-2xl font-bold text-yellow-800">
                                    {{ formatAmount(agentDepositData?.totalPendingDeposits || 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <div class="max-w-full mx-auto">
                <div class="">
                    <div class="">
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">Select Banks to Deposit</h1>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div v-for="bank in banksWithOneBill" :key="bank.id"
                                class="bg-primary/10 rounded-lg p-4 flex items-center cursor-pointer hover:bg-gray-100 h-full"
                                :class="{
                                    'ring-1 ring-primary': selectedBank?.id === bank.id,
                                    'bg-green-50': bank.isOneBill
                                }" @click="selectBank(bank)">
                                <img v-if="bank.isOneBill" src="https://1link.net.pk/assets/images/logo.png"
                                    alt="1 BILL" class="h-8 w-8 text-primary mr-4 hero-logo flex-shrink-0">
                                <Receipt v-else-if="!bank?.logo_path" class="h-8 w-8 text-primary mr-4 flex-shrink-0" />
                                <img v-else :src="bank?.logo_path" class="h-8 w-8 mr-4 flex-shrink-0" />

                                <div class="flex-1">
                                    <p class="text-xs sm:text-sm font-medium text-gray-600">{{ bank.bank_name }}</p>
                                    <p class="text-sm sm:text-base font-semibold text-gray-800 break-words">{{ bank.account_title }}</p>
                                    <p v-if="!bank.isOneBill" class="text-xs sm:text-sm text-gray-700 break-words">
                                        Account: {{ bank.account_number }}
                                    </p>
                                    <p v-if="!bank.isOneBill" class="text-xs sm:text-sm text-gray-700 break-words">
                                        IBAN: {{ bank.iban }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bank Deposit Section (Existing) -->
        <div class="bg-white rounded-lg mb-3">
            <span class="text-2xl font-bold">New Deposit - Bank Transfer</span>
            <div class="space-y-6">
                <div class="grid grid-cols-5 max-[425px]:grid-cols-2 gap-2">
                    <div class="grid">
                        <label>Date</label>
                        <Input class="text-black" v-model="date" type="date" />
                    </div>
                    <div class="grid">
                        <label>Amount</label>
                        <Input type="number" placeholder="Enter amount" v-model="amount" />
                    </div>
                    <div v-if="isBankTransferType(paymentType)" class="grid">
                        <label>Receipt image</label>
                        <Input type="file" @change="handleReceiptImage" />
                    </div>
                    <div class="grid">
                        <label>Payment type</label>
                        <Select v-model="paymentType">
                            <SelectTrigger>
                                <SelectValue :placeholder="'Select a payment type'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Banks</SelectLabel>
                                    <SelectItem value="abhipay-deposit">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate max-w-[150px] block">
                                                One-Bill
                                            </span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem value="cash">
                                        <div class="flex items-center gap-2">
                                            <span class="truncate max-w-[150px] block">
                                                Cash
                                            </span>
                                        </div>
                                    </SelectItem>
                                    <SelectItem v-for="bank in banks" :key="bank.id"
                                        :value="`${bank.bank_name} - ${bank.account_title} - ${bank.account_number}`">
                                        <div class="flex items-center gap-2">
                                            <img v-if="bank?.logo_path" :src="bank.logo_path" :alt="bank.bank_name"
                                                class="w-5 h-5 object-contain shrink-0" />
                                            <span class="truncate max-w-[150px] max-[320px]:max-w-[93px] block">
                                                {{ bank.bank_name }} - {{ bank.account_title }} - {{ bank.account_number
                                                }}
                                            </span>
                                        </div>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid">
                        <label>Currency</label>
                        <input
                            class="flex h-10 w-full rounded-md transition-all duration-75 border border-primary hover:border-2 hover:border-primary/50 px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus:ring-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            :value="selectedBank?.currency || 'PKR'" />
                    </div>

                    <div class="grid">
                        <label>Additional details</label>
                        <Input placeholder="Details..." v-model="additionalDetails" />
                    </div>
                </div>
                <div v-if="showBillId && generatedBillId"
                    class="w-1/2 bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <CreditCard class="h-6 w-6 text-green-600" />
                        <div>
                            <p class="text-sm font-medium text-green-600">Bill ID Generated Successfully</p>
                            <p class="text-xl font-mono font-bold text-green-700">{{ generatedBillId }}</p>
                            <p class="text-xs text-green-600 mt-1">Use this Bill ID for payment reference</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="mx-auto bg-white">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Important Notes</h2>
                        <ul class="list-disc pl-5 text-gray-600 space-y-2">
                            <li>Payments made after banking hours will be processed on the next working day.</li>
                            <li>Attach a proper payment slip including reference, sender, and beneficiary bank details
                                to
                                avoid
                                delays in processing.</li>
                            <li>Please ensure to transfer payments only from your Travel Agency Bank Accounts.</li>
                            <li>
                                To register your bank account with us, please send an email to
                                <a href="mailto:support@Jetze.pk"
                                    class="text-gray-600 font-medium hover:underline">support@Jetze.pk</a>.
                            </li>
                        </ul>
                    </div>
                    <div v-if="paymentType === 'abhipay-deposit'" class=" bg-white">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Abhi Pay Information</h2>
                        <ul class="list-disc pl-5 text-gray-600 space-y-2">
                            <li>Instant digital payment through Abhi Pay</li>
                            <li>Generate Bill ID and complete payment online</li>
                            <li>No receipt image upload required</li>
                            <li>Payment will be automatically verified</li>
                            <li>Rs 500 service fee will be charged for OneBill bank transfer</li>
                            <li class="">

                                <a :href="$router.resolve({ name: 'HowToPay' }).href" target="_blank" rel="noopener"
                                    class="flex text-sm items-start gap-2 text-primary underline hover:underline">
                                    <!-- Icon -->


                                    How To Use AbhiPay Bank Transfer

                                    <ExternalLink class="w-3 h-3 mt-1" />
                                </a>


                            </li>
                        </ul>
                    </div>
                </div>
                <div class="mt-4 gap-4 flex items-center justify-end">
                    <!-- Abhi Pay Button - visible when payment type is abhipay-deposit -->
                    <Button v-if="paymentType === 'abhipay-deposit'" :disabled="generatedBillId && !isLoading"
                        @click="initializeAbhiPay"
                        class="bg-primary hover:bg-primary/50 text-white font-semibold py-3 rounded-md transition duration-300 ease-in-out transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed">
                        <LoaderIcon v-if="isLoading" class="w-5 h-5 mr-2 animate-spin" />
                        <CreditCard v-else class="w-5 h-5 mr-2" />
                        {{ isLoading ? 'Generating...' : (generatedBillId ? 'Generate New Bill ID' : 'Generate Bill ID')
                        }}
                    </Button>

                    <!-- Bank Transfer Button - visible for all other payment types -->
                    <Button v-else @click="handleDepositData"
                        class="bg-primary hover:bg-primary/50 text-white font-semibold py-3 rounded-md transition duration-300 ease-in-out transform hover:scale-105">
                        <SaveIcon class="w-5 h-5 mr-2" />
                        Deposit
                    </Button>
                </div>
            </div>
        </div>



        <div>
            <div class="bg-white px-4 rounded-lg border border-gray-200">
                <div
                    class="flex flex-col py-4 space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4">
                    <div class="flex items-center flex-1 space-x-4">
                        <div class="relative w-full max-w-sm items-center">
                            <Input @input="
                                (event) => {
                                    $router.push({
                                        path: $route.path,
                                        query: {
                                            ...Object.fromEntries(Object.entries($route.query).filter(([key]) => key !== 'search_query')),
                                            ...(event.target.value ? { search_query: event.target.value } : {}),
                                        },
                                    });
                                    fetchAgentDeposits();
                                }
                            " id="search" type="text" placeholder="Search..." class="pl-10" />
                            <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                                <Search class="size-4 text-muted-foreground" />
                            </span>
                        </div>
                    </div>
                    <Popover>
                        <PopoverTrigger as-child>
                            <Button variant="outline"
                                :class="cn('w-full sm:w-[280px] justify-start text-left font-normal bg-white text-xs sm:text-sm', !value && 'text-muted-foreground')">
                                <CalendarIcon class="mr-2 h-3 w-3 sm:h-4 sm:w-4 flex-shrink-0" />
                                <span class="truncate">
                                    <template v-if="value.start">
                                        <template v-if="value.end">
                                            {{ df.format(value.start.toDate(getLocalTimeZone())) }} - {{
                                                df.format(value.end.toDate(getLocalTimeZone())) }}
                                        </template>
                                        <template v-else>
                                            {{ df.format(value.start.toDate(getLocalTimeZone())) }}
                                        </template>
                                    </template>
                                    <template v-else> Pick a date </template>
                                </span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[95vw] sm:w-auto p-0 bg-white">
                            <RangeCalendar v-model="value" initial-focus :number-of-months="2"
                                @update:start-value="(startDate) => (value.start = startDate)" />
                        </PopoverContent>
                    </Popover>
                    <div
                        class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3">
                        <Button variant="outline" @click="fetchAgentDeposits" class="flex items-center bg-white">
                            <Printer class="w-4 h-4 mr-2" /> Print
                        </Button>
                    </div>
                </div>
                <section v-if="isLoading" class="p-24 flex items-center justify-center">
                    <LoaderIcon class="w-8 h-8 animate-spin" />
                </section>
                <section v-if="agentDepositData.deposits && agentDepositData.deposits.length > 0 && !isLoading"
                    class="bg-gray-50">
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-3 py-3">Receipt Reference</th>
                                        <th scope="col" class="px-1 py-3">Date</th>
                                        <th scope="col" class="px-1 py-3">Payment Method</th>
                                        <th scope="col" class="px-1 py-3">Payment Type</th>
                                        <th scope="col" class="px-1 py-3">Additional Details</th>
                                        <th scope="col" class="px-1 py-3">Deposit Status</th>
                                        <th scope="col" class="px-1 py-3">Amount</th>
                                        <th scope="col" class="px-1 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="deposit in agentDepositData.deposits" :key="deposit.id"
                                        class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-3 py-4 font-medium text-gray-900 whitespace-nowrap">
                                            {{ deposit.receipt_reference || "_" }}
                                        </td>
                                        <td class="px-1 py-4">{{ deposit.date || "_" }}</td>
                                        <td class="px-1 py-4">
                                            <span class="inline-flex items-center gap-1">
                                                <CreditCard v-if="deposit.payment_method === 'abhipay'"
                                                    class="w-3 h-3 text-green-600" />
                                                <span>{{ deposit.payment_method === 'abhipay' ? 'Abhi Pay' : 'Bank Transfer' }}</span>
                                            </span>
                                        </td>
                                        <td class="px-1 py-4">{{ deposit?.payment_type || "_" }}</td>
                                        <td class="px-1 py-4">{{ deposit.additional_details || "_" }}</td>
                                        <td class="px-1 py-4">
                                            <span :class="{
                                                'text-yellow-700 bg-yellow-200 rounded-lg p-2': deposit.deposit_status === 'pending',
                                                'text-gray-600 bg-gray-200 rounded-lg p-2': deposit.deposit_status === 'approved',
                                                'text-red-500 bg-red-200 p-2 rounded-lg': deposit.deposit_status === 'rejected',
                                                uppercase: true,
                                            }">
                                                {{ deposit.deposit_status || "_" }}
                                            </span>
                                        </td>
                                        <td class="px-1 py-4">{{ deposit.amount || "_" }}</td>
                                        <td class="px-1 py-4">
                                            <div class="flex space-x-2">
                                                <button @click="openDialog(deposit)"
                                                    class="text-gray-600 hover:text-gray-900">
                                                    <EyeIcon class="w-5 h-5" />
                                                </button>
                                                <button @click="deleteDeposit(deposit.id)"
                                                    class="text-red-600 hover:text-red-900"
                                                    :hidden="deposit.deposit_status === 'approved'"
                                                    :class="{ 'opacity-50 cursor-not-allowed': deposit.deposit_status === 'approved' }">
                                                    <TrashIcon class="w-5 h-5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <div v-else-if="isLoading" class="p-6 text-center">
                    <LoaderIcon class="w-8 h-8 animate-spin mx-auto text-gray-500" />
                    <p class="mt-2 text-gray-600">Loading deposits...</p>
                </div>
                <div v-else class="p-6 text-center">
                    <InboxIcon class="w-16 h-16 mx-auto text-gray-400" />
                    <p class="mt-4 text-lg font-semibold text-gray-600">No deposits found</p>
                    <p class="mt-2 text-gray-500">There are no deposits to display at the moment.</p>
                </div>
            </div>
        </div>
        <div v-if="showDialog" id="deposit-dialog" class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0"
                    enter-to-class="opacity-100" leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100" leave-to-class="opacity-0">
                    <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true"
                        @click="closeDialog"></div>
                </transition>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <transition enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100" leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    <div
                        class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-2xl font-extrabold text-gray-900 mb-4" id="modal-title">Deposit
                                        Details
                                    </h3>
                                    <div class="mt-4 space-y-6">
                                        <!-- Receipt Image if available -->
                                        <div v-if="isBankTransferType(selectedDeposit?.payment_type) &&
                                            selectedDeposit?.receipt_image &&
                                            selectedDeposit.receipt_image !== '/storage/'">
                                            <img :src="selectedDeposit.receipt_image" alt="Receipt Image"
                                                class="inset-0 w-full h-auto rounded-lg border border-gray-200" />
                                        </div>

                                        <!-- Main Details Grid -->
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <!-- Invoice ID / Receipt Reference (this is your invoice_id from the table) -->
                                            <div class="space-y-1 col-span-2 bg-gray-50 p-3 rounded-lg">
                                                <p class="font-medium text-gray-500">Invoice / Receipt Reference</p>
                                                <p class="font-mono font-bold text-gray-900 text-lg">{{
                                                    selectedDeposit?.receipt_reference || 'N/A' }}</p>
                                            </div>

                                            <!-- Date -->
                                            <div class="space-y-1">
                                                <p class="font-medium text-gray-500">Date</p>
                                                <p class="font-bold text-gray-900">{{ selectedDeposit?.date || 'N/A' }}
                                                </p>
                                            </div>

                                            <!-- Transaction ID (from your table structure - the second column) -->
                                            <div v-if="selectedDeposit?.transaction_id" class="space-y-1">
                                                <p class="font-medium text-gray-500">Transaction ID</p>
                                                <p class="font-mono font-bold text-gray-900">{{
                                                    selectedDeposit.transaction_id
                                                    }}</p>
                                            </div>

                                            <!-- Amount -->
                                            <div class="space-y-1">
                                                <p class="font-medium text-gray-500">Amount</p>
                                                <p class="font-bold text-gray-900">{{
                                                    formatAmount(selectedDeposit?.amount) ||
                                                    'N/A' }}
                                                </p>
                                            </div>

                                            <!-- Payment Method/Type -->
                                            <div class="space-y-1">
                                                <p class="font-medium text-gray-500">Payment Method</p>
                                                <p class="font-bold text-gray-900 flex items-center gap-1">
                                                    <span v-if="selectedDeposit?.payment_type === 'abhipay-deposit'"
                                                        class="inline-flex items-center">
                                                        <CreditCard class="w-4 h-4 mr-1 text-green-600" />
                                                        Abhi Pay
                                                    </span>
                                                    <span v-else>{{ selectedDeposit?.payment_type || 'N/A' }}</span>
                                                </p>
                                            </div>

                                            <!-- Currency -->
                                            <div class="space-y-1">
                                                <p class="font-medium text-gray-500">Currency</p>
                                                <p class="font-bold text-gray-900">{{ selectedDeposit?.currency || 'PKR'
                                                    }}
                                                </p>
                                            </div>

                                            <!-- Status -->
                                            <div class="space-y-1">
                                                <p class="font-medium text-gray-500">Status</p>

                                                <div class="flex items-center gap-3 bg-gray-50 p-3 rounded-lg">

                                                    <!-- Status -->
                                                    <p :class="{
                                                        'text-yellow-700 bg-yellow-100 px-3 py-1 rounded-full': selectedDeposit?.deposit_status === 'pending',
                                                        'text-green-700 bg-green-100 px-3 py-1 rounded-full': selectedDeposit?.deposit_status === 'approved',
                                                        'text-red-700 bg-red-100 px-3 py-1 rounded-full': selectedDeposit?.deposit_status === 'rejected',
                                                    }" class="font-semibold uppercase text-xs">
                                                        {{ selectedDeposit?.deposit_status || 'N/A' }}
                                                    </p>

                                                    <!-- Message -->


                                                    <!-- Refresh Button -->
                                                    <button @click="checkPaymentStatus('abhipay-deposit')"
                                                        :disabled="isCheckingPaymentStatus"
                                                        class="flex items-center justify-center w-9 h-9 rounded-md border hover:bg-gray-200 text-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                        <RefreshCcw class="w-4 h-4 text-primary"
                                                            :class="{ 'animate-spin': isCheckingPaymentStatus }" />
                                                    </button>

                                                </div>
                                            </div>

                                            <!-- Reference ID (REF_ID from your table) -->
                                            <div v-if="selectedDeposit?.invoice_id" class="space-y-1 col-span-2">
                                                <p class="font-medium text-gray-500">Invoice ID</p>
                                                <p class="font-semibold text-gray-700 bg-gray-50 p-2 rounded">{{
                                                    selectedDeposit.invoice_id }}</p>
                                                <a :href="$router.resolve({ name: 'HowToPay' }).href" target="_blank"
                                                    rel="noopener"
                                                    class="flex items-start gap-1 text-primary underline hover:underline">
                                                    <!-- Icon -->


                                                    How To pay ?

                                                    <ExternalLink class="w-3 h-3 mt-1" />
                                                </a>

                                            </div>
                                        </div>

                                        <!-- Additional Details -->
                                        <div v-if="selectedDeposit?.additional_details" class="space-y-1 border-t pt-4">
                                            <p class="font-medium text-gray-500">Additional Details</p>
                                            <p class="text-gray-700 bg-gray-50 p-3 rounded-lg">{{
                                                selectedDeposit.additional_details }}</p>
                                        </div>

                                        <!-- Timestamps (created_at/updated_at) - Optional, can be shown if needed -->
                                        <div v-if="selectedDeposit?.created_at"
                                            class="text-xs text-gray-400 border-t pt-2 mt-2">
                                            <p>Created: {{ new Date(selectedDeposit.created_at).toLocaleString() }}</p>
                                            <p class="text-sm text-gray-500">
                                                Click refresh to check latest payment status
                                            </p>
                                            <p
                                                v-if="selectedDeposit.updated_at && selectedDeposit.updated_at !== selectedDeposit.created_at">
                                                Updated: {{ new Date(selectedDeposit.updated_at).toLocaleString() }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!hideButtons"
                            class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse sm:justify-between">
                            <button @click="closeDialog"
                                class="w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Close
                            </button>

                            <!-- Optional: Add a print/download button for the invoice -->
                            <button v-if="selectedDeposit?.receipt_reference" @click="captureDialogAsImage"
                                class="mt-3 sm:mt-0 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto sm:text-sm">
                                <Printer class="w-4 h-4 mr-2" />
                                Save as Image
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>
<style scoped>
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
