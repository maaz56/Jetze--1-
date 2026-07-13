<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight } from "lucide-vue-next";
import { PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff } from "lucide-vue-next";
import Input from "@/components/ui/input/Input.vue";

import { PrinterIcon, MailIcon, Download } from "lucide-vue-next";
import html2pdf from "html2pdf.js";

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

import { calculateLayover, formatDateTime, getFormattedDates, getDuration, calculateLayoverTime, getAdjustedDateTime } from "@/lib/utils";
import { useRoute, useRouter } from "vue-router";

import { useStore } from "vuex";
import { computed, onMounted, ref, watch } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import html2canvas from "html2canvas";
import { cn, formatAmount, calculateLayoverDetails, calculateFinalPrice } from "@/lib/utils";

import {
    FETCH_BOOKING_DATA,
    FETCH_AGENT_DATA,
    FETCH_BOOKING_DETAILS,
    FETCH_PNR_DETAILS,
    CANCEL_BOOKING,
    CONFIRM_BOOKING,
    FETCH_AGENT_LEDGER,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";


const store = useStore();
const route = useRoute();
const router = useRouter();

const authStore = useAuthStore();
const loading = ref(true);

// Loading states for individual API calls
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const error = ref(null);
const isLoading = computed(() => isBookingDetailsLoading.value || isPnrDetailsLoading.value || isAgentLoading.value);


const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

const booking_id = route.query.booking_id;
const pnr = route.query.pnr;
const bookingId = ref("");
const isDialogOpen = ref(false);
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);

const pnrDetails = computed(() => store.getters["flight/pnrData"]);

const booking = ref(null);
const flightData = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref(0);

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

async function fetchAgent() {
    if (user_id.value) {
        try {
            await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: user_id.value });
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
        } finally {
            isAgentLoading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        isAgentLoading.value = false;
    }
}

// function fetchAgent() {
//     if (user_id.value) {
//         try {
//             store.dispatch(`user/${FETCH_AGENT_DATA}`, {
//                 userId: user_id.value,
//             });
//             loading.value = false;
//         } catch (err) {
//             error.value = "Failed to load user data. Please try again.";
//             loading.value = false;
//         }
//     } else {
//         error.value = "No user ID provided.";
//         loading.value = false;
//     }
// }


async function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isBookingDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id });
    } catch (err) {
        error.value = "Failed to fetch booking details.";
    } finally {
        isBookingDetailsLoading.value = false;
    }
}

// function fetchBookingDetails() {
//     if (!booking_id) {
//         error.value = "No booking ID provided.";
//         isBookingDetailsLoading.value = false;

//         return;
//     }

//     store.dispatch("flight/" + FETCH_BOOKING_DETAILS, {
//         bookingId: booking_id,
//     }).finally(() => {
//         isBookingDetailsLoading.value = false;
//     });
// }

function handleConfirmDialogOpen() {
    //console.log("agenledger",agentLedger?.value.balance);
    //console.log("totalTicketPrice",totalTicketPrice?.value);
    if (agentLedger?.value.balance < totalTicketPrice?.value) {
        isLowBalanceDialogOpen.value = true;
        return;
    }

    isConfirmDialogOpen.value = true;
}

function handleCancelBooking() {
    error.value = '';
    isLoading.value = true;

    try {
        if (!pnr) {
            error.value = "No PNR provided.";
            return;
        }

        store.dispatch("flight/" + CANCEL_BOOKING, {
            pnr: pnr,
        });

        store.dispatch("flight/" + CONFIRM_BOOKING, {
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            booking_status: "canceled",
        });

        // const updatedBooking = { ...bookingDetails.value[0], status: "canceled" };
        // store.commit("flight/SET_BOOKING_DETAILS", [updatedBooking]);
        // Close dialog after successful cancellation
        isDialogOpen.value = false;

    } catch (err) {
        error.value = err.message || 'Failed to cancel booking';
    } finally {
        isLoading.value = false;
    }
};
async function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        isPnrDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { pnr: pnr });
    } catch (err) {
        error.value = "Failed to fetch PNR details.";
    } finally {
        isPnrDetailsLoading.value = false;
    }
}


// function fetchPnrDetails() {
//     if (!pnr) {
//         error.value = "No PNR provided.";
//         isPnrDetailsLoading.value = false;

//         return;
//     }
//     store.dispatch("flight/" + FETCH_PNR_DETAILS, {
//         pnr: pnr,
//     }).finally(() => {
//         isPnrDetailsLoading.value = false;
//     });
// }

function cancelBooking() {
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    //console.log(pnr);

    store.dispatch("flight/" + CANCEL_BOOKING, {
        pnr: pnr,
    });

    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: route.query.pnr,
        bookingId: bookingDetails.value[0].id,
        booking_status: "canceled",
    });
}
function confirmBooking() {

    error.value = '';
    isLoading.value = true;




    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: route.query.pnr,
        bookingId: bookingDetails.value[0].id,
        booking_status: "confirmed",
    });

    // Close dialog after successful cancellation
    isConfirmDialogOpen.value = false;
}

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const
    formatCurrency = (amount, currency) => {
        return new Intl.NumberFormat("en-US", {
            style: "currency",
            currency: currency,
        }).format(amount);
    };

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

const formatBaggage = (baggage) => {
    if (baggage.pieces) {
        return `${baggage.pieces} piece${baggage.pieces > 1 ? "s" : ""}`;
    } else if (baggage.weight) {
        return `${baggage.weight}${baggage.unit || "kg"}`;
    }
    return "No baggage information";
};

watch(user, (newUser) => {
    if (newUser) {
        fetchAgent();
    }
});
function toggleFareInfo() {
    isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
    return {
        isDetailsInfoVisible,
        toggleFareInfo,
    };
}
const printBooking = () => {
    const printContent = document.getElementById("print-section").innerHTML;

    // Create a hidden print container
    const printContainer = document.createElement("div");
    printContainer.id = "print-container";
    printContainer.style.display = "none";
    printContainer.innerHTML = printContent;

    // Append the print container to the body
    document.body.appendChild(printContainer);

    // Show the print container and print
    printContainer.style.display = "block";
    window.print();

    // Hide and remove the print container after printing
    printContainer.style.display = "none";
    document.body.removeChild(printContainer);
};
const toggleChatOpen = () => {
    isChatOpen.value = !isChatOpen.value;
}


const downloadPDF = () => {
    const element = document.getElementById("print-section");

    // Temporarily make the element visible for PDF generation
    const a4Width = 210; // A4 width in mm
    const a4Height = 297; // A4 height in mm
    const contentWidth = element.scrollWidth; // Content width in pixels
    const contentHeight = element.scrollHeight; // Content height in pixels

    // Convert pixels to mm (1px ≈ 0.264583mm)
    const contentWidthMM = contentWidth * 0.264583;
    const contentHeightMM = contentHeight * 0.264583;

    // Calculate the scaling factor
    const scaleWidth = a4Width / contentWidthMM;
    const scaleHeight = a4Height / contentHeightMM;
    const scale = Math.min(scaleWidth, scaleHeight);

    // Options for the PDF
    const options = {
        margin: 2,
        filename: `booking_${booking_id}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: {
            scale: 2,
            logging: true,
            useCORS: true,
            windowWidth: element.scrollWidth,
            windowHeight: element.scrollHeight,
        },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
    };

    // Generate and download the PDF
    html2pdf()
        .from(element)
        .set(options)
        .save()
        .then(() => {
            // Restore the original styles
            element.style.display = "";
            element.style.visibility = "";
            element.style.opacity = "";
        });
};

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
        // fetchAgent();
    } else {
        fetchAgent();
    }
    fetchAgentLedger();
    fetchBookingDetails();
    fetchPnrDetails();
});
</script>


<template>
    <section>
        <div v-if="isLoading" class="fixed inset-0 bg-gray-100 bg-opacity-75 flex items-center justify-center z-50">
            <Spinner />
        </div>

        <div v-else class="min-h-screen bg-gray-100">
            <div v-if="route?.query?.booking_source == 1">
                <!-- <div v-for="booking in bookingDetails" :key="booking.id"
                    class="bg-white p-4 gap-2 flex justify-end mx-auto " id="topBar">
                    <button @click="printBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                        Print
                    </button>
                    <button @click="isEmailDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button>
                    <button @click="downloadPDF"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <Download class="h-5 w-5 inline-block mr-1" />
                        Download pdf
                    </button>
                    <button @click="toggleFareInfo"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <EyeOff class="h-5 w-5 inline-block mr-1" />
                        <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                        <span v-else>View Fare Details</span>
                    </button>

                    <button @click="isDialogOpen = true"
                        :disabled="['canceled', 'ticketed'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        {{ booking?.status?.toLowerCase() === 'canceled' ? 'Canceled' : booking?.status?.toLowerCase()
                            ===
                            'ticketed' ? 'Ticketed' : 'Cancel Booking' }}
                    </button>
                    <div v-if="isEmailDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isEmailDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Enter Email to Send</h3>
                                <button @click="isEmailDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <div>
                                    <Label class="block text-sm font-medium text-gray-700 mb-1">Agency Email: {{
                                        agentData?.agent_data?.company_email }}</Label>
                                    Or enter new one
                                    <Input type="text"
                                        class="flex-1 mt-2 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter email" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isEmailDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="isEmailDialogOpen = false"
                                    class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Send Email
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="isDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                                <button @click="isDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to cancel this booking? This action cannot be undone.
                                </p>

                                <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="handleCancelBooking"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Cancellation
                                </button>
                            </div>
                        </div>
                    </div>
                    <button @click="handleConfirmDialogOpen"
                        :disabled="['canceled', 'ticketed'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        Confirm Booking
                    </button>
                    <div v-if="isConfirmDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isConfirmDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                                <button @click="isConfirmDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to confrim this booking?
                                </p>

                                <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isConfirmDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="confirmBooking"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Booking
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLowBalanceDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isLowBalanceDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
                                <button @click="isConfirmDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    You balance is insufitient to confirm this booking. Please add funds to your
                                    account.
                                </p>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isLowBalanceDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="$router.push({
                                    name: 'Deposits',

                                })"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Go To Desposit
                                </button>
                            </div>
                        </div>
                    </div>

                </div> -->
                <div id="print-section">
                    <div v-for="booking in bookingDetails" :key="booking.id"
                        class=" bg-white  overflow-hidden print:shadow-none print:border print:border-gray-300">

                        <div class="relative grid grid-cols-8 justify-between h-48 bg-gradient-to-r from-green-600 to-green-800 items-center 
                    print:bg-white print:border-b-2 print:border-green-800">
                            <div class="col-span-1">
                                <img class="h-16 w-52 print:h-10 print:w-24 justify-start ms-7"
                                    src="/public/assets/logo.png" />
                            </div>
                            <div class="col-span-5 text-white ps-4 print:text-black border-l-2 print:border-gray-800">

                                <h1 class="text-2xl font-bold">
                                    {{
                                        agentData?.agent_data?.company_name ||
                                        "Jetze Travels"
                                    }}
                                </h1>
                                <p class="">
                                    {{
                                        agentData?.agent_data?.mobile ||
                                        "+(+92) 3111711123"
                                    }}
                                </p>
                                <p class="line-clamp-2">
                                    {{
                                        agentData?.agent_data?.address ||
                                        "Address line 1,Sheikhpura Road,Lahore,Pakistan"}}
                                </p>
                                <p class="">
                                    {{
                                        agentData?.agent_data?.company_email ||
                                        "support@Jetze.pk"
                                    }}
                                </p>
                            </div>


                            <div
                                class="col-span-2 mx-6 flex flex-col ps-1 text-white text-xs border-l-2 print:text-black print:border-gray-800">
                                <div class="grid grid-cols-2 gap-4">
                                    <p class=" ">Booking Reference:</p>
                                    <p class="font-semibold">{{ booking.id }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">Travel Date:</p>

                                    <!-- <p class="font-semibold" v-for="(date, dateIndex) in parseFlightData(
                                    booking.flight_data,
                                ).dates" :key="dateIndex">
                                    {{ formatDate(date.departureDate) }}
                                   
                                </p> -->
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class=" ">Booking Status:</p>
                                    <p class="font-semibold capitalize">
                                        {{ booking.status }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">GDS PNR:</p>
                                    <p class="font-semibold">
                                        {{ pnrDetails?.bookingId ? pnrDetails?.bookingId : booking?.pnr || "-" }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">Airline PNR:</p>

                                    <p class="font-semibold" v-if="pnrDetails?.flights?.length">
                                        {{ pnrDetails?.flights[0]?.confirmationId }}

                                    </p>
                                    <p class="font-semibold" v-else>{{ booking?.airline_pnr }}</p>
                                </div>

                            </div>
                        </div>


                        <!-- Flight Information -->
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-bold text-gray-800 mb-4">
                                FLIGHT INFORMATION
                            </h2>
                            <div v-if="parseFlightData(booking.flight_data)?.original?.leg" class="space-y-3">
                                <div v-for="(flight, flightIndex) in parseFlightData(booking.flight_data)?.original?.leg?.flights"
                                    :key="flightIndex" class="space-y-2">




                                    <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex"
                                        class="flex flex-col sm:flex-row justify-between gap-3 p-3 bg-gray-50 rounded-lg text-xs print:border-gray-400">

                                        <div
                                            class="flex items-center gap-2 mt-2 sm:mt-0 border-t sm:border-t-0 pt-2 sm:pt-0 sm:border-r sm:pr-3">
                                            <img :src="segment?.operating_carrier.logo"
                                                class="h-6 w-auto object-contain" />
                                            <div>
                                                <p class="font-semibold text-gray-800">
                                                    {{ segment.from.iata }}
                                                </p>
                                                <p class="text-gray-800 font-semibold">
                                                    Flight: {{ segment?.flight_number }} | Aircraft: {{
                                                        segment?.aircraft?.model }}

                                                </p>
                                                <p class="font-semibold text-gray-800">Class:</p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between flex-1 w-full">

                                            <div class="flex-1 max-w-[40%]">

                                                <p class="text-gray-800 mt-0.5 font-semibold">
                                                    {{ segment?.from?.city?.name }}-{{
                                                        segment?.from?.city?.code }}
                                                </p>
                                                <p class="text-gray-800 font-semibold">Departure: {{
                                                    formatDate(segment?.departure_at) }} </p>

                                                <p class="text-gray-800 font-semibold">
                                                    Terminal: {{ segment?.from_terminal?.Gate == null ? "N/A" :
                                                        segment?.from_terminal?.Gate }}
                                                </p>

                                            </div>


                                            <div class="flex flex-col items-center px-1">
                                                <MoveRight class="h-5 w-5 text-gray-400" />
                                                <div class="text-xs text-gray-500 mt-0.5">

                                                </div>
                                            </div>


                                            <div
                                                class="flex-1 max-w-[40%] text-right print:border-l-2 print:border-gray-300">

                                                <p class="text-gray-800 mt-0.5 font-semibold">
                                                    {{ segment?.to?.city?.name }}-{{ segment?.to?.city?.code }}
                                                </p>

                                                <p class="text-gray-800 font-semibold">Arrival: {{
                                                    formatDate(segment?.arrival_at) }} </p>
                                                <p class="text-gray-800 font-semibold">
                                                    Terminal: {{ segment?.from_terminal?.Gate == null ? "N/A" :
                                                        segment?.to_terminal?.Gate }}
                                                </p>
                                            </div>
                                        </div>


                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-b border-gray-200 bg-white" v-for="booking in bookingDetails"
                        :key="booking.id">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            PASSENGER & TICKET DETAILS
                        </h2>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Traveller Name</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Gender</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Country</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Ticket No</th>

                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(traveller, index) in booking?.pessangers" :key="index"
                                        class="hover:bg-gray-50 transition-colors">
                                        <td class="py-1.5 px-2 uppercase">
                                            {{ traveller.title }} {{ traveller.last_name }} {{ traveller.first_name }}
                                            <span class="text-gray-500 text-xs ml-1">({{ traveller.type }})</span>
                                        </td>


                                        <td class="py-1.5 px-2 uppercase">{{ traveller?.gender?.toUpperCase() }}</td>

                                        <td class="py-1.5 px-2">
                                            {{ traveller.nationality }}
                                        </td>


                                        <td class="py-1.5 px-2">
                                            {{ Array.isArray(pnrDetails?.flightTickets) &&
                                                pnrDetails.flightTickets[index] ?
                                                pnrDetails.flightTickets[index].number : "-" }}
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>



                    <div class="p-6 border-b border-gray-200" v-if="isDetailsInfoVisible">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            FARE BREAKDOWN
                        </h2>
                        <div v-for="booking in bookingDetails" :key="booking.id">
                            <div class="overflow-x-auto"
                                v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.original?.leg?.flights"
                                :key="flightIndex">

                                <table
                                    class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Type
                                            </th>

                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Base Fare
                                            </th>
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Tax + Fees
                                            </th>
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Total Cost
                                            </th>

                                        </tr>
                                    </thead>

                                    <tbody class="divide-y divide-gray-100 "
                                        v-for="(fare, index) in flight?.fares?.filter(f => f?.ref_id === booking?.fare_reference)"
                                        :key="index">
                                        <tr class="hover:bg-gray-50"
                                            v-for="(passengerFare, index) in fare?.passenger_fares" :key="index">
                                            <td class="py-1.5 px-2 uppercase">{{ passengerFare.traveler_type }} x {{
                                                passengerFare.total_passenger }}</td>


                                            <td class="py-1.5 px-2 uppercase">
                                                {{ formatAmount(passengerFare.base_price) }}
                                            </td>
                                            <td class="py-1.5 px-2 uppercase"> {{ formatAmount(
                                                passengerFare?.fees
                                                + passengerFare?.taxes + passengerFare?.surchage
                                                + passengerFare?.service_charges
                                                + passengerFare?.ancillaries_charges
                                               ) }}
                                            </td>
                                            <td class="py-1.5 px-2 uppercase font-bold">{{ formatAmount(
                                                passengerFare.total_price 
                                               ) }} </td>
                                        </tr>

                                        <tr class="bg-gray-100 font-semibold">
                                            <td class="py-2 px-2 uppercase">TOTAL</td>
                                            <td class="py-2 px-2 uppercase">{{ }}</td>
                                            <td class="py-2 px-2 uppercase">{{ }}</td>


                                            <td class="py-2 px-2 uppercase font-bold">{{
                                                formatAmount(totalTicketPrice =  fare?.billable_price
                                                    
                                                )
                                            }}
                                            </td>
                                        </tr>

                                        {{ }}
                                    </tbody>
                                </table>


                            </div>
                        </div>

                    </div>

                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            Baggage Details
                        </h2>

                        <div class="overflow-x-auto" v-for="booking in bookingDetails" :key="booking.id">
                            <div v-for="(flight, index) in parseFlightData(booking.flight_data).original?.leg?.flights"
                                :key="index">

                                <table
                                    class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Type</th>
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Check-In
                                                Baggage
                                            </th>
                                            <th class="py-1.5 px-2 text-left font-medium text-gray-600">Cabin Baggage
                                            </th>

                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100"
                                        v-for="(fare, index) in flight?.fares?.filter(f => f?.ref_id === booking?.fare_reference)"
                                        :key="index">
                                        <!-- Group by traveler_type -->
                                        <tr class="hover:bg-gray-50"
                                            v-for="(travelerType, idx) in [...new Set(fare.baggage_policies.map(bp => bp.traveler_type))]"
                                            :key="idx">
                                            <td class="py-1.5 px-2 uppercase">{{ travelerType }}</td>
                                            <!-- Cabin/Carry Baggage -->
                                            <td class="py-1.5 px-2 uppercase">
                                                {{
                                                    fare.baggage_policies.find(bp => bp.traveler_type === travelerType &&
                                                        bp.type === 'carry')?.description || 'N/A'
                                                }}
                                            </td>
                                            <!-- Checked Baggage -->
                                            <td class="py-1.5 px-2 uppercase">
                                                {{
                                                    fare.baggage_policies.find(bp => bp.traveler_type === travelerType &&
                                                        bp.type === 'checked')?.description || 'N/A'
                                                }}
                                            </td>


                                        </tr>
                                    </tbody>
                                </table>
                            </div>


                        </div>
                    </div>

                    <div class="bg-white p-4">
                        <h2 class="text-lg font-semibold mb-3">Cancellation & Amendment Policies</h2>
                        <ul class="list-disc pl-5 space-y-2 text-gray-700">
                            <li>Cancellation of a ticket can be done either by emailing us or directly through the
                                airline.
                            </li>
                            <li>For ticket amendments, please send us an email along with your booking reference (e.g.,
                                Trip
                                20 - AK 180XXXXXX) and the new travel details.</li>
                            <li>Cancellations or amendments must be made at least 24 hours prior to departure.</li>
                            <li>Requests made within 24 hours of departure should be directed to the airline.</li>
                            <li>Cancellation policy follows airline rules. Special and promotional fares are
                                non-refundable.
                            </li>
                            <li>If you have canceled your booking directly with the airline, please email us to process
                                the
                                applicable refund.</li>

                            <li><span class="text-primary">{{ agentData?.agent_data?.company_name }}</span> service
                                charge:
                                PKR 1000 per passenger.</li>
                        </ul>
                    </div>


                    <!-- Customer Support -->
                    <div class="p-6 text-center text-gray-700 text-sm bg-gray-50">
                        <p>Thank you for choosing {{ agentData?.agent_data?.company_name }}</p>
                        <p>For assistance, please contact us at {{ agentData?.agent_data?.mobile }} or {{
                            agentData?.agent_data?.company_email }}</p>
                    </div>
                </div>
            </div>
            <div v-else>
                <div v-for="booking in bookingDetails" :key="booking.id"
                    class="bg-white p-4 gap-2 flex justify-end mx-auto " id="topBar">
                    <button @click="printBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                        Print
                    </button>
                    <button @click="isEmailDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button>
                    <button @click="downloadPDF"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <Download class="h-5 w-5 inline-block mr-1" />
                        Download pdf
                    </button>
                    <button @click="toggleFareInfo"
                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <EyeOff class="h-5 w-5 inline-block mr-1" />
                        <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                        <span v-else>View Fare Details</span>
                    </button>

                    <button @click="isDialogOpen = true"
                        :disabled="['canceled', 'ticketed'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        {{ booking?.status?.toLowerCase() === 'canceled' ? 'Canceled' : booking?.status?.toLowerCase()
                            ===
                            'ticketed' ? 'Ticketed' : 'Cancel Booking' }}
                    </button>
                    <div v-if="isEmailDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isEmailDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Enter Email to Send</h3>
                                <button @click="isEmailDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <div>
                                    <Label class="block text-sm font-medium text-gray-700 mb-1">Agency Email: {{
                                        agentData?.agent_data?.company_email }}</Label>
                                    Or enter new one
                                    <Input type="text"
                                        class="flex-1 mt-2 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter email" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isEmailDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="isEmailDialogOpen = false"
                                    class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    Send Email
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-if="isDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                                <button @click="isDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to cancel this booking? This action cannot be undone.
                                </p>

                                <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="handleCancelBooking"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Cancellation
                                </button>
                            </div>
                        </div>
                    </div>
                    <button @click="handleConfirmDialogOpen"
                        :disabled="['canceled', 'ticketed'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        Confirm Booking
                    </button>
                    <div v-if="isConfirmDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isConfirmDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                                <button @click="isConfirmDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to confrim this booking?
                                </p>

                                <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">
                                    {{ error }}
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isConfirmDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="confirmBooking"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Booking
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="isLowBalanceDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isLowBalanceDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
                                <button @click="isConfirmDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    You balance is insufitient to confirm this booking. Please add funds to your
                                    account.
                                </p>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isLowBalanceDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="$router.push({
                                    name: 'Deposits',

                                })"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Go To Desposit
                                </button>
                            </div>
                        </div>
                    </div>



                </div>
                <div id="print-section">
                    <div v-for="booking in bookingDetails" :key="booking.id"
                        class=" bg-white  overflow-hidden print:shadow-none print:border print:border-gray-300">

                        <div class="relative grid grid-cols-8 justify-between h-48 bg-gradient-to-r from-green-600 to-green-800 items-center 
                    print:bg-white print:border-b-2 print:border-green-800">
                            <div class="col-span-1">
                                <img class="h-16 w-52 print:h-10 print:w-24 justify-start ms-7"
                                    src="/public/assets/logo.png" />
                            </div>
                            <div class="col-span-5 text-white ps-4 print:text-black border-l-2 print:border-gray-800">

                                <h1 class="text-2xl font-bold">
                                    {{
                                        agentData?.agent_data?.company_name ||
                                        "Jetze Travels"
                                    }}
                                </h1>
                                <p class="">
                                    {{
                                        agentData?.agent_data?.mobile ||
                                        "+(+92) 3111711123"
                                    }}
                                </p>
                                <p class="line-clamp-2">
                                    {{
                                        agentData?.agent_data?.address ||
                                        "Address line 1,Sheikhpura Road,Lahore,Pakistan"}}
                                </p>
                                <p class="">
                                    {{
                                        agentData?.agent_data?.company_email ||
                                        "support@Jetze.pk"
                                    }}
                                </p>
                            </div>


                            <div
                                class="col-span-2 flex flex-col ps-1 text-white text-xs border-l-2 print:text-black print:border-gray-800">
                                <div class="grid grid-cols-2 gap-4">
                                    <p class=" ">Booking Reference:</p>
                                    <p class="font-semibold">{{ booking.id }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">Travel Date:</p>

                                    <p class="font-semibold" v-for="(date, dateIndex) in parseFlightData(
                                        booking.flight_data,
                                    ).dates" :key="dateIndex">
                                        {{ formatDate(date.departureDate) }}

                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class=" ">Booking Status:</p>
                                    <p class="font-semibold capitalize">
                                        {{ booking.status }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">GDS PNR:</p>
                                    <p class="font-semibold">
                                        {{ pnrDetails?.bookingId ? pnrDetails?.bookingId : booking?.pnr || "-" }}
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <p class="">Airline PNR:</p>

                                    <p class="font-semibold" v-if="pnrDetails?.flights?.length">
                                        {{ pnrDetails?.flights[0]?.confirmationId }}

                                    </p>
                                    <p class="font-semibold" v-else>{{ booking?.airline_pnr }}</p>
                                </div>

                            </div>
                        </div>


                        <!-- Flight Information -->
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-lg font-bold text-gray-800 mb-4">
                                FLIGHT INFORMATION
                            </h2>

                            <div v-if="parseFlightData(booking.flight_data)" class="space-y-3">
                                <div v-for="(leg, legIndex) in parseFlightData(booking.flight_data).legs"
                                    :key="legIndex" class="space-y-2">
                                    <div v-for="(stop, stopIndex) in leg.stops" :key="stopIndex">


                                        <div
                                            class="flex flex-col sm:flex-row justify-between gap-3 p-3 bg-gray-50 rounded-lg text-xs print:border-gray-400">
                                            <!-- Airline info -->
                                            <div
                                                class="flex items-center gap-2 mt-2 sm:mt-0 border-t sm:border-t-0 pt-2 sm:pt-0 sm:border-r sm:pr-3">
                                                <img :src="stop.airline.logo_url" :alt="stop.airline.name"
                                                    class="h-6 w-auto object-contain" />
                                                <div>
                                                    <p class="font-semibold text-gray-800">
                                                        {{ stop.airline.name }}
                                                    </p>
                                                    <p class="text-gray-800 font-semibold">
                                                        Flight: {{ stop.flightNumber }} | Aircraft: {{
                                                            stop.aircraft.name }}

                                                    </p>
                                                    <p class="font-semibold text-gray-800">Class:</p>
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-between flex-1 w-full">
                                                <!-- Departure info -->
                                                <div class="flex-1 max-w-[40%]">

                                                    <p class="text-gray-800 mt-0.5 font-semibold">
                                                        {{ stop.departure.airport.name }}-{{
                                                            stop.departure.airport.iata_code }}
                                                    </p>
                                                    <p class="text-gray-800 font-semibold">Departure: {{
                                                        getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                                            stop?.departure?.time,
                                                            stop?.adjustment).date }} - {{
                                                            getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                                                stop?.departure?.time,
                                                                stop?.adjustment).time }}</p>

                                                    <p class="text-gray-800 font-semibold">
                                                        Terminal: {{ stop.departure.terminal == null ? "N/A" :
                                                            stop.departure.terminal }}
                                                    </p>

                                                </div>

                                                <!-- Flight duration -->
                                                <div class="flex flex-col items-center px-1">
                                                    <MoveRight class="h-5 w-5 text-gray-400" />
                                                    <div class="text-xs text-gray-500 mt-0.5">

                                                    </div>
                                                </div>

                                                <!-- Arrival info -->
                                                <div
                                                    class="flex-1 max-w-[40%] text-right print:border-l-2 print:border-gray-300">

                                                    <p class="text-gray-800 mt-0.5 font-semibold">
                                                        {{ stop.arrival.airport.name }}-{{
                                                            stop.arrival.airport.iata_code }}
                                                    </p>

                                                    <p class="font-semibold">Arrival:
                                                        {{
                                                            getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                                                stop?.arrival?.time,
                                                                stop?.adjustment).date }} - {{
                                                            getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                                                stop?.arrival?.time,
                                                                stop?.adjustment).time }}</p>

                                                    <!-- // formatDateTime((parseFlightData(booking.pnr_response)
                                                    //     .CreatePassengerNameRecordRS
                                                    //     .TravelItineraryRead
                                                    //     .TravelItinerary
                                                    //     .ItineraryInfo
                                                    //     .ReservationItems.Item[stopIndex]
                                                    //     .Product
                                                    //     .ProductDetails
                                                    //     .Air
                                                    //     .ArrivalDateTime).toString()) -->
                                                    <p class="text-gray-800 font-semibold">
                                                        Terminal: {{ stop.arrival.terminal == null ? "N/A" :
                                                            stop.arrival.terminal }}
                                                    </p>
                                                </div>
                                            </div>


                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 border-b border-gray-200 bg-white" v-for="booking in bookingDetails"
                        :key="booking.id">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            PASSENGER & TICKET DETAILS
                        </h2>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Traveller Name</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Gender</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Country</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Ticket No</th>

                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(traveller, index) in booking?.pessangers" :key="index"
                                        class="hover:bg-gray-50 transition-colors">
                                        <td class="py-1.5 px-2 uppercase">
                                            {{ traveller.title }} {{ traveller.last_name }} {{ traveller.first_name }}
                                            <span class="text-gray-500 text-xs ml-1">({{ traveller.type }})</span>
                                        </td>


                                        <td class="py-1.5 px-2 uppercase">{{ traveller?.gender?.toUpperCase() }}</td>

                                        <td class="py-1.5 px-2">
                                            {{ traveller.nationality }}
                                        </td>


                                        <td class="py-1.5 px-2">
                                            {{ Array.isArray(pnrDetails?.flightTickets) &&
                                                pnrDetails.flightTickets[index] ?
                                                pnrDetails.flightTickets[index].number : "-" }}
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>



                    <div class="p-6 border-b border-gray-200" v-if="isDetailsInfoVisible">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            FARE BREAKDOWN
                        </h2>

                        <div class="overflow-x-auto" v-for="booking in bookingDetails" :key="booking.id">
                            <table
                                class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Type</th>

                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Base Fare</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Tax + Fees</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Total Cost</th>

                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 ">
                                    <tr v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo"
                                        :key="index" class="hover:bg-gray-50">
                                        <td class="py-1.5 px-2 uppercase">{{ passenger.passengerType }} x {{
                                            passenger.passengerNumber }}</td>


                                        <td class="py-1.5 px-2 uppercase">{{ formatCurrency(
                                            passenger.passengerTotalFare.equivalentAmount
                                            ,
                                            passenger.passengerTotalFare.currency
                                        ) }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{
                                            formatCurrency(passenger.passengerTotalFare.totalTaxAmount,
                                                passenger.passengerTotalFare.currency
                                            ) }}

                                        </td>
                                        <td class="py-1.5 px-2 uppercase font-bold">{{ formatCurrency(
                                            (passenger.passengerTotalFare.totalFare *
                                                passenger.passengerNumber)
                                            ,
                                            passenger.passengerTotalFare.currency
                                        ) }} </td>
                                    </tr>

                                    <tr class="bg-gray-100 font-semibold">
                                        <td class="py-2 px-2 uppercase">TOTAL</td>
                                        <td class="py-2 px-2 uppercase">{{ }}</td>
                                        <td class="py-2 px-2 uppercase">{{ }}</td>


                                        <td class="py-2 px-2 uppercase font-bold">{{

                                            formatCurrency(totalTicketPrice =
                                                parseFlightData(booking.flight_data)?.pricing?.totalPrice,
                                                parseFlightData(booking.flight_data)?.pricing.currency
                                            ) }}
                                        </td>
                                    </tr>

                                    {{ }}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            Baggage Details
                        </h2>

                        <div class="overflow-x-auto" v-for="booking in bookingDetails" :key="booking.id">
                            <table
                                class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Type</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Check-In Baggaget
                                        </th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Cabin Baggage</th>

                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 ">
                                    <tr v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo"
                                        :key="index" class="hover:bg-gray-50">
                                        <td class="py-1.5 px-2 uppercase">{{ passenger.passengerType }} x {{
                                            passenger.passengerNumber }}</td>
                                        <td class="py-1.5 px-2 uppercase">
                                            {{
                                                parseFlightData(booking.flight_data)?.passengerInfo[index]?.baggage[0]?.weight
                                                    ?
                                                    parseFlightData(booking.flight_data)?.passengerInfo[index]?.baggage[0]?.weight
                                                    :
                                                    "-"
                                            }}
                                            {{
                                                parseFlightData(booking.flight_data)?.passengerInfo[index]?.baggage[0]?.unit
                                            }}
                                        </td>
                                        <td class="py-1.5 px-2">5-7Kg</td>

                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="bg-white p-4">
                        <h2 class="text-lg font-semibold mb-3">Cancellation & Amendment Policies</h2>
                        <ul class="list-disc pl-5 space-y-2 text-gray-700">
                            <li>Cancellation of a ticket can be done either by emailing us or directly through the
                                airline.
                            </li>
                            <li>For ticket amendments, please send us an email along with your booking reference (e.g.,
                                Trip
                                20 - AK 180XXXXXX) and the new travel details.</li>
                            <li>Cancellations or amendments must be made at least 24 hours prior to departure.</li>
                            <li>Requests made within 24 hours of departure should be directed to the airline.</li>
                            <li>Cancellation policy follows airline rules. Special and promotional fares are
                                non-refundable.
                            </li>
                            <li>If you have canceled your booking directly with the airline, please email us to process
                                the
                                applicable refund.</li>

                            <li><span class="text-primary">{{ agentData?.agent_data?.company_name }}</span> service
                                charge:
                                PKR 1000 per passenger.</li>
                        </ul>
                    </div>


                    <!-- Customer Support -->
                    <div class="p-6 text-center text-gray-700 text-sm bg-gray-50">
                        <p>Thank you for choosing {{ agentData?.agent_data?.company_name }}</p>
                        <p>For assistance, please contact us at {{ agentData?.agent_data?.mobile }} or {{
                            agentData?.agent_data?.company_email }}</p>
                    </div>
                </div>
            </div>

        </div>

    </section>
</template>

<style>
/* Print-specific styles */


@media print {
    @page {
        margin: 0;
        size: auto;
    }

    html,
    body * {
        visibility: hidden;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        color: #111827;
        /* dark text for better printing */
        font-size: 9pt;
        /* Reduce font size for printing */
    }


    #print-container,
    #print-container * {
        visibility: visible;
    }

    #print-container {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }


    /* Make content more compact for printing */
    .p-6 {
        padding: 10px !important;
    }

    .space-y-4>*+* {
        margin-top: 4px !important;
    }

    /* Reduce some element heights */
    .relative.justify-between.h-auto.md\:h-48 {
        height: auto !important;
        padding: 10px !important;
    }

    /* Ensure tables print well but more compact */
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 8pt;
    }

    th,
    td {
        border: 1px solid #374151;
        padding: 4px;
        text-align: left;
    }

    th {
        background-color: #f3f4f6 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Ensure background colors print */
    .bg-gray-50,
    .bg-gray-100 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Avoid page breaks inside critical elements */
    table,
    tr,
    .border-b-2 {
        page-break-inside: avoid;
    }

    /* Hide unnecessary elements when printing */
    .print\:hidden {
        display: none !important;
    }

    /* Make footer smaller */
    .text-center.text-gray-700.text-sm {
        font-size: 7pt !important;
        padding: 5px !important;
    }
}
</style>
