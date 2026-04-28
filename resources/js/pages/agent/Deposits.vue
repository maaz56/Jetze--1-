```vue
<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RefreshCcw, Search, ArrowLeft, ImageIcon, UploadIcon, SaveIcon, UserPlusIcon, CalendarIcon, CheckCircleIcon, Receipt, EyeIcon, TrashIcon, LoaderIcon, InboxIcon, Printer, Download, Share } from "lucide-vue-next";
import { useAuthStore } from "@/services/stores/auth";
import { useStore } from "vuex";
import { computed, onMounted, ref, watch, nextTick } from "vue";
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
const user = computed(() => authStore.user);
const hideButtons = ref(false);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const showDialog = ref(false);
const selectedDeposit = ref(null);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const banks = computed(() => store.getters["bank/banks"] || []);

const date = ref(new Date().toISOString().slice(0, 10));
const amount = ref("");
const receiptImage = ref(null);
const paymentType = ref("");
const selectedBank = ref(null);
const additionalDetails = ref("");
const user_id = computed(() => user.value?.id);
const loading = ref(true);
const error = ref(null);
const isLoading = ref(false);
const formErrors = ref([]);

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
    paymentType.value = bank ? `${bank.bank_name} - ${bank.account_title} - ${bank.account_number}` : "";
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
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-4 mb-3">
            <Button @click="$router.push({ name: 'Dashboard' })" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <span class="block text-3xl font-medium leading-none tracking-tight text-gray-900">
                Deposits
            </span>
        </div>
    </div>
    <div class="mb-3">
        <div class="max-w-full mx-auto">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        Deposits Overview
                    </h1>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 rounded-lg p-4 flex items-center">
                            <Receipt class="h-8 w-8 text-gray-500 mr-4" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Balance</p>
                                <p class="text-2xl font-bold text-gray-800">
                                    {{ formatAmount(agentLedger?.balance || 0) }}
                                </p>
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-4 flex items-center">
                            <CheckCircleIcon class="h-8 w-8 text-gray-500 mr-4" />
                            <div>
                                <p class="text-sm font-medium text-gray-600">Approved Deposits</p>
                                <p class="text-2xl font-bold text-gray-800">
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
    </div>
    <div class="mb-3">
        <div class="max-w-full mx-auto">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Select Banks to Deposit</h1>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div v-for="bank in banks" :key="bank.id"
                            class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer hover:bg-gray-100 transition"
                            :class="{ 'ring-2 ring-primary': selectedBank?.id === bank.id }" @click="selectBank(bank)">
                            <Receipt v-if="!bank?.logo_path" class="h-8 w-8 text-yellow-500 mr-4" />
                            <img v-else :src="bank?.logo_path" alt="Bank Logo" class="h-8 w-8 mr-4" />
                            <div>
                                <p class="text-md font-medium text-gray-600">{{ bank?.bank_name || 'N/A' }}</p>
                                <p class="text-xl font-semibold text-gray-800">{{ bank?.account_title || 'N/A' }}</p>
                                <p class="text-xl font-medium text-gray-800">
                                    Account: <span class="text-lg font-normal">{{ bank?.account_number || 'N/A'
                                        }}</span>
                                </p>
                                <p class="text-xl font-medium text-gray-800">
                                    IBAN : <span class="text-lg font-normal">{{ bank?.iban || 'N/A'
                                        }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white p-4 rounded-lg mb-3">
        <span class="text-2xl font-bold">New Deposit</span>
        <form @submit.prevent="handleDepositData" class="space-y-6">
            <div class="grid grid-cols-5 gap-2">
                <div class="grid">
                    <label>Date</label>
                    <Calender v-model="date" :type="'date'" />
                </div>
                <div class="grid">
                    <label>Amount</label>
                    <Input type="number" placeholder="Enter amount" v-model="amount" />
                </div>
                <div class="grid">
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
                                <SelectItem 
                                    value="cash">
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
                                        <span class="truncate max-w-[150px] block">
                                            {{ bank.bank_name }} - {{ bank.account_title }} - {{ bank.account_number }}
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
                        class="flex h-10 w-full rounded-md transition-all duration-75 border border-input hover:border-2 hover:border-primary/50 px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus:ring-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :value="selectedBank?.currency || 'N/A'"  />
                </div>
                <div class="grid">
                    <label>Additional details</label>
                    <Input placeholder="Details..." v-model="additionalDetails" />
                </div>
            </div>
            <div class="mx-auto bg-white">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Important Notes</h2>
                <ul class="list-disc pl-5 text-gray-600 space-y-2">
                    <li>Payments made after banking hours will be processed on the next working day.</li>
                    <li>Attach a proper payment slip including reference, sender, and beneficiary bank details to avoid
                        delays in processing.</li>
                    <li>Please ensure to transfer payments only from your Travel Agency Bank Accounts.</li>
                    <li>
                        To register your bank account with us, please send an email to
                        <a href="mailto:support@Jetze.pk"
                            class="text-gray-600 font-medium hover:underline">support@Jetze.pk</a>.
                    </li>
                </ul>
            </div>
            <div class="mt-4 flex items-center justify-end">
                <Button type="submit"
                    class="bg-primary hover:bg-primary/50 text-white font-semibold py-3 rounded-lg transition duration-300 ease-in-out transform hover:scale-105">
                    <SaveIcon class="w-5 h-5 mr-2" />
                    Deposit
                </Button>
            </div>
        </form>
    </div>
    <div>
        <div class="bg-white p-8 rounded-lg border">
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
                            :class="cn('w-[280px] justify-start text-left font-normal', !value && 'text-muted-foreground')">
                            <CalendarIcon class="mr-2 h-4 w-4" />
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
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent class="w-auto p-0">
                        <RangeCalendar v-model="value" initial-focus :number-of-months="2"
                            @update:start-value="(startDate) => (value.start = startDate)" />
                    </PopoverContent>
                </Popover>
                <div
                    class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3">
                    <Button variant="outline" @click="fetchAgentDeposits" class="flex items-center">
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
    <div v-if="showDialog" id="deposit-dialog" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title"
        role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <transition enter-active-class="ease-out duration-300" enter-from-class="opacity-0"
                enter-to-class="opacity-100" leave-active-class="ease-in duration-200" leave-from-class="opacity-100"
                leave-to-class="opacity-0">
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
                                <h3 class="text-2xl font-extrabold text-gray-900 mb-4" id="modal-title">Deposit Details
                                </h3>
                                <div class="mt-4 space-y-6">
                                    <div v-if="selectedDeposit?.receipt_image">
                                        <img :src="selectedDeposit.receipt_image" alt="Receipt Image"
                                            class="inset-0 w-full h-auto" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Date</p>
                                            <p class="font-bold text-gray-900">{{ selectedDeposit?.date || 'N/A' }}</p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Receipt Reference</p>
                                            <p class="font-bold text-gray-900">{{ selectedDeposit?.receipt_reference ||
                                                'N/A' }}</p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Amount</p>
                                            <p class="font-bold text-gray-900">{{ selectedDeposit?.amount || 'N/A' }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Payment Type</p>
                                            <p class="font-bold text-gray-900">{{ selectedDeposit?.payment_type || 'N/A'
                                                }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Currency</p>
                                            <p class="font-bold text-gray-900">{{ selectedDeposit?.currency || 'N/A' }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">Status</p>
                                            <p :class="{
                                                'text-yellow-500': selectedDeposit?.deposit_status === 'pending',
                                                'text-gray-500': selectedDeposit?.deposit_status === 'approved',
                                                'text-red-500': selectedDeposit?.deposit_status === 'rejected',
                                            }" class="font-bold uppercase">
                                                {{ selectedDeposit?.deposit_status || 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="font-medium text-gray-500">Details</p>
                                        <p class="text-gray-700">{{ selectedDeposit?.additional_details || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!hideButtons" class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="closeDialog"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
```