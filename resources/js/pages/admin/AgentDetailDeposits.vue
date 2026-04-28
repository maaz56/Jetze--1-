<script setup>
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { RefreshCcw } from "lucide-vue-next";
import { Search } from "lucide-vue-next";
import { ArrowLeft } from "lucide-vue-next";
import { ImageIcon, UploadIcon, SaveIcon, UserPlusIcon } from "lucide-vue-next";

import { useAuthStore } from "@/services/stores/auth";
import { useStore } from "vuex";
import { computed, onMounted, ref, watch, nextTick } from "vue";
import { EyeIcon, TrashIcon, LoaderIcon, InboxIcon } from "lucide-vue-next";
import { Download } from "lucide-vue-next";
import { Share } from "lucide-vue-next";
import jsPDF from "jspdf"; // Ensure jsPDF is installed: `npm install jspdf`
import html2canvas from "html2canvas";
import { useRoute } from "vue-router";


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
} from "@/services/store/actions.type";

const authStore = useAuthStore();
const route = useRoute();

const store = useStore();
const user = computed(() => authStore.user);
const hideButtons = ref(false); // Boolean to control button visibility

const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const showDialog = ref(false); // Controls dialog visibility
const selectedDeposit = ref(null); // Stores the deposit details to display
const user_id = computed(() => route.query.userId);

const date = ref("");
const amount = ref("");
const receiptImage = ref(null);
const paymentType = ref("");
const additionalDetails = ref("");
const loading = ref(true);
const error = ref(null);
const isLoading = ref(false);
const formErrors = ref([]);
// Opens the dialog with deposit details
function openDialog(deposit) {
    selectedDeposit.value = deposit;
    showDialog.value = true;
}

// Closes the dialog
function closeDialog() {
    showDialog.value = false;
    selectedDeposit.value = null;
}

const handleReceiptImage = (event) => {
    receiptImage.value = event.target.files[0];
};

// function handleDepositData() {
//     let errors = [];

//     // Form validation
//     if (!date.value) {
//         errors.push("Date field is required.");
//     }
//     if (!amount.value) {
//         errors.push("Amount field is required.");
//     }
//     if (!paymentType.value) {
//         errors.push("Payment type is required.");
//     }
//     if (errors.length > 0) {
//         console.error(errors); // Handle displaying errors to the user
//         return;
//     }

//     // Preparing form data
//     const depositData = new FormData();
//     depositData.append("date", date.value);
//     depositData.append("amount", amount.value);
//     if (receiptImage.value) {
//         depositData.append("receipt_image", receiptImage.value); // Append file
//     }
//     depositData.append("payment_type", paymentType.value);
//     depositData.append("additional_details", additionalDetails.value);
//     depositData.append("agent_id", user.value.id);

//     store.dispatch("deposit/" + SAVE_DEPOSIT_DATA, depositData);
//     //console.log("Deposit data saved successfully");

//     // Reset form fields
//     date.value = "";
//     amount.value = "";
//     receiptImage.value = null;
//     paymentType.value = "";
//     additionalDetails.value = "";
//     fetchAgentDeposits();
// }

async function handleDepositData() {
    formErrors.value = [];

    // Form validation
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

        await store.dispatch(`deposit/${SAVE_DEPOSIT_DATA}`, depositData);
        //console.log("Deposit data saved successfully");

        // Reset form fields
        date.value = "";
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

// function fetchAgentDeposits() {
//     if (user_id.value) {
//         //console.log("Fetch Data:  " + user_id.value);
//         try {
//             store.dispatch(`deposit/${FETCH_DEPOSIT_DATA}`, {
//                 userId: user_id.value,
//             });
//             loading.value = false;
//         } catch (err) {
//             error.value = "Failed to load user deposit data. Please try again.";
//             loading.value = false;
//         }
//     } else {
//         error.value = "No user ID provided.";
//         loading.value = false;
//     }
// }

 function fetchAgentDeposits() {
    if (!user_id.value) {
        error.value = "No user ID provided.";
        return;
    }

    loading.value = true;
    error.value = null;

    try {
         store.dispatch(`deposit/${FETCH_DEPOSIT_DATA}`, {
            userId: user_id.value,
        });
    } catch (err) {
        console.error("Error fetching agent deposits:", err);
        error.value = "Failed to load user deposit data. Please try again.";
    } finally {
        loading.value = false;
    }
}

// const deleteDeposit = (id) => {
//     //console.log(`Delete deposit with ID: ${id}`);

//     store.dispatch("deposit/" + DELETE_DEPOSIT_DATA, {
//         id: id,
//     });
//     fetchAgentDeposits();
// };

async function deleteDeposit(id) {
    loading.value = true;
    error.value = null;

    try {
        await store.dispatch(`deposit/${DELETE_DEPOSIT_DATA}`, { id });
        //console.log(`Deposit with ID: ${id} deleted successfully`);
        fetchAgentDeposits();
    } catch (err) {
        console.error("Error deleting deposit:", err);
        error.value = "Failed to delete deposit. Please try again.";
    } finally {
        loading.value = false;
    }
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
onMounted(() => {
    if (user.value?.id) {
        fetchAgentDeposits();
    }
});

watch(user, (newUser) => {
    if (newUser?.id) {
        fetchAgentDeposits();
    }
});
</script>

<template>
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-4 mb-3">
            <Button
                @click="$router.push({ name: 'Dashboard' })"
                variant="outline"
                size="sm"
            >
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <span
                class="block text-3xl font-medium leading-none tracking-tight text-gray-900"
            >
                Deposits
            </span>
        </div>
    </div>
    

    <div>
        <div class="bg-white p-8 rounded-lg border">
            <div
                class="flex flex-col py-4 space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4"
            >
                <div class="flex items-center flex-1 space-x-4">
                    <div class="relative w-full max-w-sm items-center">
                        <Input
                            @input="
                                (event) => {
                                    $router.push({
                                        path: $route.path,
                                        query: {
                                            ...Object.fromEntries(
                                                Object.entries(
                                                    $route.query,
                                                ).filter(
                                                    ([key]) =>
                                                        key !== 'search_query',
                                                ),
                                            ),
                                            ...(event.target.value
                                                ? {
                                                      search_query:
                                                          event.target.value,
                                                  }
                                                : {}),
                                        },
                                    });
                                    fetchUsers();
                                }
                            "
                            id="search"
                            type="text"
                            placeholder="Search..."
                            class="pl-10"
                        />
                        <span
                            class="absolute start-0 inset-y-0 flex items-center justify-center px-2"
                        >
                            <Search class="size-4 text-muted-foreground" />
                        </span>
                    </div>
                </div>
                <div
                    class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3"
                >
                    <Button
                        variant="outline"
                        @click="
                            () => {
                                $router.push({ name: 'Users' });
                                fetchUsers();
                            }
                        "
                        class="flex items-center"
                        ><RefreshCcw class="w-4 h-4 mr-2" /> Reset</Button
                    >
                </div>
            </div>

            <section
                v-if="isLoading"
                class="p-24 flex items-center justify-center"
            >
                <Spinner />
            </section>
            <section
                v-if="
                    agentDepositData.deposits &&
                    agentDepositData.deposits.length > 0 &&
                    !isLoading
                "
                class="bg-gray-50"
            >
                <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-500">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-100"
                            >
                                <tr>
                                    <th scope="col" class="px-3 py-3">
                                        Receipt Reference
                                    </th>
                                    <th scope="col" class="px-1 py-3">Date</th>
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
                                <tr
                                    v-for="deposit in agentDepositData.deposits"
                                    :key="deposit.id"
                                    class="bg-white border-b hover:bg-gray-50"
                                >
                                    <td
                                        class="px-3 py-4 font-medium text-gray-900 whitespace-nowrap"
                                    >
                                        {{ deposit.receipt_reference || "_" }}
                                    </td>
                                    <td class="px-1 py-4">
                                        {{ deposit.date || "_" }}
                                    </td>
                                    <td class="px-1 py-4">
                                        <span>
                                            {{ deposit.payment_type || "_" }}
                                        </span>
                                    </td>
                                    <td class="px-1 py-4">
                                        {{ deposit.additional_details || "_" }}
                                    </td>
                                    <td class="px-1 py-4">
                                        <span
                                            :class="{
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
                                            }"
                                        >
                                            {{ deposit.deposit_status || "_" }}
                                        </span>
                                    </td>
                                    <td class="px-1 py-4">
                                        {{ deposit.amount || "_" }}
                                    </td>
                                    <td class="px-1 py-4">
                                        <div class="flex space-x-2">
                                            <button
                                                @click="openDialog(deposit)"
                                                class="text-gray-600 hover:text-gray-900"
                                            >
                                                <EyeIcon class="w-5 h-5" />
                                            </button>
                                            <button
                                                @click="
                                                    deleteDeposit(deposit.id)
                                                "
                                                class="text-red-600 hover:text-red-900"
                                            >
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
                <LoaderIcon
                    class="w-8 h-8 animate-spin mx-auto text-gray-500"
                />
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

    <!-- Dialog -->
    <div
        v-if="showDialog"
        id="deposit-dialog"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0"
        >
            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75"
                    aria-hidden="true"
                    @click="closeDialog"
                ></div>
            </transition>

            <span
                class="hidden sm:inline-block sm:align-middle sm:h-screen"
                aria-hidden="true"
                >&#8203;</span
            >

            <transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    class="inline-block overflow-hidden text-left align-bottom transition-all transform bg-white rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                >
                    <div class="px-4 pt-5 pb-4 bg-white sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full"
                            >
                                <h3
                                    class="text-2xl font-extrabold text-gray-900 mb-4"
                                    id="modal-title"
                                >
                                    Deposit Details
                                </h3>
                                <div class="mt-4 space-y-6">
                                    <div v-if="selectedDeposit?.receipt_image">
                                        <img
                                            :src="selectedDeposit.receipt_image"
                                            alt="Receipt Image"
                                            class="inset-0 w-full h-auto"
                                        />
                                    </div>

                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div class="space-y-1">
                                            <p
                                                class="font-medium text-gray-500"
                                            >
                                                Date
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{ selectedDeposit?.date }}
                                            </p>
                                        </div>

                                        <div class="space-y-1">
                                            <p
                                                class="font-medium text-gray-500"
                                            >
                                                Receipt Reference
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{
                                                    selectedDeposit?.receipt_reference
                                                }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p
                                                class="font-medium text-gray-500"
                                            >
                                                Amount
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{ selectedDeposit?.amount }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p
                                                class="font-medium text-gray-500"
                                            >
                                                Payment Type
                                            </p>
                                            <p class="font-bold text-gray-900">
                                                {{
                                                    selectedDeposit?.payment_type
                                                }}
                                            </p>
                                        </div>
                                        <div class="space-y-1">
                                            <p
                                                class="font-medium text-gray-500"
                                            >
                                                Status
                                            </p>
                                            <p
                                                :class="{
                                                    'text-red-500':
                                                        selectedDeposit?.deposit_status ===
                                                        'pending',
                                                    'text-gray-500':
                                                        selectedDeposit?.deposit_status ===
                                                        'approved',
                                                }"
                                                class="font-bold uppercase"
                                            >
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
                    <div
                        v-if="!hideButtons"
                        class="px-4 py-3 bg-gray-50 sm:px-6 sm:flex sm:flex-row-reverse"
                    >
                        <button
                            @click="shareDialogOnWhatsApp"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Share on WhatsApp
                        </button>
                        <button
                            @click="captureDialogAsImage"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-gray-600 text-base font-medium text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                        >
                            Download as Image
                        </button>
                        <button
                            @click="closeDialog"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </transition>
        </div>
    </div>
</template>
