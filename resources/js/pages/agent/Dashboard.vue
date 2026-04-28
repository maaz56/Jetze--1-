<script setup>
import Button from "@/components/ui/button/Button.vue";
import { MoveRight, RefreshCcw } from "lucide-vue-next";
import { Search } from "lucide-vue-next";
import { ArrowLeft } from "lucide-vue-next";
import {
    ImageIcon,
    UploadIcon,
    SaveIcon,
    UserPlusIcon,
    CalendarIcon,
    CheckCircleIcon,
} from "lucide-vue-next";

import { EyeIcon, TrashIcon, LoaderIcon, InboxIcon } from "lucide-vue-next";
import { Download } from "lucide-vue-next";
import { Share } from "lucide-vue-next";
import jsPDF from "jspdf"; // Ensure jsPDF is installed: `npm install jspdf`
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
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { Plus, Receipt, TicketCheck } from "lucide-vue-next";
import {
    FETCH_TRANSACTIONS,
    SAVE_TRANSACTION,
    FETCH_AGENT_DATA,
    FETCH_TOTAL_APPROVED_DEPOSIT,
    FETCH_DEPOSIT_DATA,
    FETCH_BOOKING_DATA,
    FETCH_AGENT_LEDGER,
    DELETE_DEPOSIT_DATA,
} from "@/services/store/actions.type";
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import { useStore } from "vuex";
import { useRoute, useRouter } from "vue-router";

import { computed, onMounted, ref, watch } from "vue";
import { formatAmount } from "@/lib/utils";
import moment from "moment";
import { Textarea } from "@/components/ui/textarea";
import { useAuthStore } from "@/services/stores/auth";
import { useTransactionStore } from "@/services/stores/transaction";

const store = useStore();
const route = useRoute();
const router = useRouter();

const loading = ref(true);
const error = ref(null);
const authStore = useAuthStore();
const transactionStore = useTransactionStore();
const user = computed(() => authStore.user);
const user_role = computed(() => user?.value?.role);
const user_id = computed(() => user?.value?.id);

const bookings = computed(() => store.getters["flight/bookingData"]);
const agentData = computed(() => store.getters["user/agentData"]);

// const totalApprovedDeposit = computed(
//     () => store.getters["deposit/totalApprovedDeposit"],
// );
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const showDialog = ref(false); // Controls dialog visibility
const selectedDeposit = ref(null); // Stores the deposit details to display
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const selectedImage = ref(null);

const handleFile = (event) => {
    selectedImage.value = event.target.files[0];
};
function fetchBookings() {
    if (!user_id?.value) {
        error.value = "No user ID provided.";
        return;
    }

    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        userRole: user_role?.value,
        userId: user_id.value,
        bookingFilter: "all",
    });
}
function deleteDeposit(id) {
    loading.value = true;
    error.value = null;

    try {
        store.dispatch(`deposit/${DELETE_DEPOSIT_DATA}`, { id });
        //console.log(`Deposit with ID: ${id} deleted successfully`);
        fetchAgentDeposits();
    } catch (err) {
        console.error("Error deleting deposit:", err);
        error.value = "Failed to delete deposit. Please try again.";
    } finally {
        loading.value = false;
    }
}

function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
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
function openDialog(deposit) {
    selectedDeposit.value = deposit;
    showDialog.value = true;
}

// Closes the dialog
function closeDialog() {
    showDialog.value = false;
    selectedDeposit.value = null;
}
// Capture the dialog as an image
async function captureDialogAsImage() {
    const dialogElement = document.querySelector("#deposit-dialog"); // Target the dialog
    if (!dialogElement) return;

    try {
        // Hide buttons before capturing
        hideButtons.value = true;

        // Wait for the DOM to update
        await nextTick();

        const canvas = await html2canvas(dialogElement, {
            backgroundColor: null, // Ensure transparent background
            scale: 2, // Increase resolution
        });
        const imageData = canvas.toDataURL("image/png");

        // Download the image
        const link = document.createElement("a");
        link.href = imageData;
        link.download = `Deposit_Details_${selectedDeposit?.id}.png`;
        link.click();
        // Show buttons again after capture
        hideButtons.value = false;

        return imageData;
    } catch (error) {
        console.error("Error capturing dialog:", error);
        hideButtons.value = false; // Restore buttons in case of an error
    }
}

// Share dialog image on WhatsApp
async function shareDialogOnWhatsApp() {
    const imageData = await captureDialogAsImage();
    if (imageData) {
        const whatsappURL = `https://wa.me/?text=${encodeURIComponent(
            "Check out the deposit details:",
        )}`;
        window.open(whatsappURL, "_blank");
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
        await store.dispatch(`deposit/${FETCH_DEPOSIT_DATA}`, {
            userId: user_id.value,
        });
    } catch (err) {
        console.error("Error fetching agent deposits:", err);
        error.value = "Failed to load user deposit data. Please try again.";
    } finally {
        loading.value = false;
    }
}
const formatCurrency = (value) => {
    return new Intl.NumberFormat("en-US", {
        style: "decimal",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString("en-US", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
};

onMounted(() => {

    fetchAgent();
    
    //fetchTransactions();

    if (user.value?.id) {
        fetchAgent();
        fetchAgentDeposits();
        fetchAgentLedger();
        fetchBookings();    }
});

watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
        fetchAgentDeposits();
        fetchAgentLedger();
        fetchBookings(); 
    }
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 md:gap-2">
        <div class="">
            <div class="bg-gradient-to-br from-gray-100 to-gray-200 mb-4">
                <div class="max-w-full mx-auto">
                    <div class="bg-white rounded-lg overflow-hidden">
                        <div class="p-6">

                            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                                Bookings Overview
                            </h1>

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
        </div>
        <div class="">
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
                                    <p class="text-sm font-medium text-gray-600">
                                        Balance
                                    </p>
                                    <p class="text-2xl font-bold text-gray-800">

                                        {{
                                            formatAmount(agentLedger?.balance)
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
                                                agentDepositData?.totalApprovedDeposits,
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
                                                agentDepositData?.totalPendingDeposits,
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

        <div>
            <div class="bg-white rounded-lg p-6">
                <div
                    class="flex flex-col space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4">
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-bold text-gray-800 mb-2">
                            Recent Deposits
                        </h1>
                    </div>
                    <div
                        class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3">
                        <Button variant="outline" @click="
                            () => {
                                $router.push({ name: 'Deposits' });
                                fetchUsers();
                            }
                        " class="flex items-center">Show All
                            <MoveRight class="w-4 h-4 ml-2" />
                        </Button>
                    </div>
                </div>

                <section v-if="isLoading" class="p-24 flex items-center justify-center">
                    <Spinner />
                </section>
                <section v-if="
                    agentDepositData.deposits &&
                    agentDepositData.deposits.length > 0 &&
                    !isLoading
                " class="bg-gray-50">
                    <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm text-left text-gray-500">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-3 py-3">
                                            Receipt Reference
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Date
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Payment Type
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Additional Details
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Deposit Status
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Amount
                                        </th>
                                        <th scope="col" class="px-1 py-3">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="deposit in agentDepositData.deposits" :key="deposit.id"
                                        class="bg-white border-b hover:bg-gray-50">
                                        <td class="px-3 py-4 font-medium text-gray-900 whitespace-nowrap">
                                            {{
                                                deposit.receipt_reference || "_"
                                            }}
                                        </td>
                                        <td class="px-1 py-4">
                                            {{ deposit.date || "_" }}
                                        </td>
                                        <td class="px-1 py-4">
                                            <span>
                                                {{
                                                    deposit.payment_type || "_"
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-1 py-4">
                                            {{
                                                deposit.additional_details ||
                                                "_"
                                            }}
                                        </td>
                                        <td class="px-1 py-4">
                                            <span :class="{
                                                'text-yellow-700 bg-yellow-200 rounded-lg p-2':
                                                    deposit.deposit_status ===
                                                    'pending',
                                                'text-gray-600 bg-gray-200 rounded-lg p-2':
                                                    deposit.deposit_status ===
                                                    'approved',
                                                'text-red-500 bg-red-200 p-2 rounded-lg':
                                                    deposit.deposit_status ===
                                                    'rejected',
                                                uppercase: true,
                                            }">
                                                {{
                                                    deposit.deposit_status ||
                                                    "_"
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-1 py-4">
                                            {{ deposit.amount || "_" }}
                                        </td>
                                        <td class="px-1 py-4">
                                            <div class="flex space-x-2">
                                                <button @click="openDialog(deposit)"
                                                    class="text-gray-600 hover:text-gray-900">
                                                    <EyeIcon class="w-5 h-5" />
                                                </button>
                                                <button @click="
                                                    deleteDeposit(
                                                        deposit.id,
                                                    )
                                                    " class="text-red-600 hover:text-red-900" :disabled="deposit.deposit_status ===
                                                        'approved'
                                                        " :class="{
                                                        'opacity-50 cursor-not-allowed':
                                                            deposit.deposit_status ===
                                                            'approved',
                                                    }">
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
                    <p class="mt-4 text-lg font-semibold text-gray-600">
                        No deposits found
                    </p>
                    <p class="mt-2 text-gray-500">
                        There are no deposits to display at the moment.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Dialog -->
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
                                <h3 class="text-2xl font-extrabold text-gray-900 mb-4" id="modal-title">
                                    Deposit Details
                                </h3>
                                <div class="mt-4 space-y-6">
                                    <div v-if="selectedDeposit?.receipt_image">
                                        <img :src="selectedDeposit.receipt_image" alt="Receipt Image"
                                            class="inset-0 w-full h-auto" />
                                    </div>

                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">
                                                Date
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{ selectedDeposit?.date }}
                                            </p>
                                        </div>

                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">
                                                Receipt Reference
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{
                                                    selectedDeposit?.receipt_reference
                                                }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">
                                                Amount
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{ selectedDeposit?.amount }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">
                                                Payment Type
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{
                                                    selectedDeposit?.payment_type
                                                }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p class="font-medium text-gray-500">
                                                Status
                                            </p>
                                            <p :class="{
                                                'text-red-500':
                                                    selectedDeposit?.deposit_status ===
                                                    'pending',
                                                'text-gray-500':
                                                    selectedDeposit?.deposit_status ===
                                                    'approved',
                                            }" class="font-bold uppercase">
                                                {{
                                                    selectedDeposit?.deposit_status
                                                }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="space-y-1">
                                        <p class="font-medium text-gray-500">
                                            Details
                                        </p>
                                        <p class="text-gray-700">
                                            {{
                                                selectedDeposit?.additional_details
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!hideButtons" class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="shareDialogOnWhatsApp"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Share on WhatsApp
                        </button>
                        <button @click="captureDialogAsImage"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Download as Image
                        </button>
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
