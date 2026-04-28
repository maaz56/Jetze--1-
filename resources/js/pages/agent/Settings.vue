<script setup>
import Button from "@/components/ui/button/Button.vue";
import ApprovelNotice from "@/components/common/ApprovelNotice.vue";
import { RefreshCcw } from "lucide-vue-next";
import { Search } from "lucide-vue-next";
import { ArrowLeft } from "lucide-vue-next";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon } from "lucide-vue-next";

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

const agentData = computed(() => store.getters["user/agentData"]);
const totalApprovedDeposit = computed(
    () => store.getters["deposit/totalApprovedDeposit"],
);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const showDialog = ref(false); // Controls dialog visibility
const selectedDeposit = ref(null); // Stores the deposit details to display

const transactions = computed(() => transactionStore.transactions);
const user_id = computed(() => user.value?.id);

const selectedImage = ref(null);

const handleFile = (event) => {
    selectedImage.value = event.target.files[0];
};
function fetchTotalApprovedDepost() {
    if (user_id.value) {
        try {
            store.dispatch(`deposit/${FETCH_TOTAL_APPROVED_DEPOSIT}`, {
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
    // fetchTransactions();

    if (user.value?.id) {
        fetchAgent();
        fetchAgentDeposits();
    }
});

watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
        fetchAgentDeposits();
    }
});
</script>

<template>
    <div v-if="user?.is_approved" class="flex flex-1 flex-col gap-4 md:gap-2">
        <div v-if="agentData && agentData.agent_data" class="p-6 bg-white rounded-lg border">
            <div class="mb-6 flex justify-between items-center gap-6">
                <img :src="agentData.agent_data?.logo" :alt="`Profile picture of ${agentData.agent_data.company_name}`"
                    class="w-28 h-auto object-cover" />
                <div class="w-full">
                    <h3 class="text-xl font-semibold text-gray-800">
                        Company Details
                    </h3>
                    <p class="text-sm text-gray-500">
                        {{ agentData.agent_data.company_name }}
                    </p>
                </div>
                <!-- <div class="flex items-center w-full justify-end">
                    <Button
                        @click="
                            $router.push({
                                name: 'UpdateAgentData',
                                query: {
                                    user_id: user.id,
                                },
                            })
                        "
                        >Edit</Button
                    >
                </div> -->
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- First Column -->
                <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-100">
                    <dl class="divide-y divide-gray-200">
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">Company Name</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.company_name }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">CEO Name</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.ceo_name }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">Mobile</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.mobile }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">
                                Government Number
                            </dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.govt_number }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Second Column -->
                <div class="bg-gray-50 p-4 rounded-lg shadow-sm border border-gray-100">
                    <dl class="divide-y divide-gray-200">
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">Company Email</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.company_email }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">CEO Contact</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.ceo_contact }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">Address</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ agentData.agent_data.address }}
                            </dd>
                        </div>
                        <div class="py-3">
                            <dt class="text-sm text-gray-600">Member Since</dt>
                            <dd class="text-base font-medium text-gray-900">
                                {{ formatDate(agentData.created_at) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>




    </div>
    <div v-else>
        <ApprovelNotice />
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
