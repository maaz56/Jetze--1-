<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight } from "lucide-vue-next";
import { PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff } from "lucide-vue-next";
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

import { calculateLayover, formatDateTime, getFormattedDates, getDuration, calculateLayoverTime } from "@/lib/utils";
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
    APPROVE_BOOKING
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";

const store = useStore();
const route = useRoute();

const authStore = useAuthStore();
const loading = ref(true);
const isLoading = ref(false);
const isDialogOpen = ref(false);

const error = ref(null);

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agent_id = route.query.agent_id;
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const booking_id = route.query.booking_id;
const pnr = route.query.pnr;
const bookingId = ref("");
const isConfirmDialogOpen = ref(false);
const showDialog = ref(false);

const pnrDetails = computed(() => store.getters["flight/pnrData"]);

const booking = ref(null);
const flightData = ref(null);
const isDetailsInfoVisible = ref(true);
const ticketNo = ref("");
const airLinePnr = ref("");


function fetchAgent() {
    if (agent_id != null) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: agent_id,
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

function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        return;
    }

    store.dispatch("flight/" + FETCH_BOOKING_DETAILS, {
        bookingId: booking_id,
    });
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
        // Close dialog after successful cancellation
        isDialogOpen.value = false;

    } catch (err) {
        error.value = err.message || 'Failed to cancel booking';
    } finally {
        isLoading.value = false;
    }
};

function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_PNR_DETAILS, {
        pnr: pnr,
    });
}

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
    isDialogOpen.value = false;
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
        jsPDF: { unit: "mm", format: "a4", orientation: "landscape" },
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
const approveAction = () => {
    //Perform your approve logic here store
    store.dispatch("flight/" + APPROVE_BOOKING, {
        airline_pnr: airLinePnr.value,
        ticket_number: ticketNo.value,
        booking_id: booking_id,
        status: "ticketed",
    });
    closeDialog();
};
const openDialog = () => {
    showDialog.value = true;
};

const closeDialog = () => {
    showDialog.value = false;
};
function toggleDetailedInfo() {
    isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
    return {
        isDetailsInfoVisible,
        toggleFareInfo,
    };
}

onMounted(() => {
   
    if (user.value == null) {
        authStore.fetchUser();
        // fetchAgent();
    } else {
        fetchAgent();
    }
    fetchBookingDetails();
    fetchPnrDetails();
    
});
</script>


<template>
    <section>
        <div class="min-h-screen bg-gray-100">

            <div v-for="booking in bookingDetails" :key="booking.id"
                class="bg-white p-4 gap-2 flex justify-end  mx-auto " id="topBar">
                <button @click="printBooking"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                    Print
                </button>
                <!-- <button
                        @click="emailBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    >
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button> -->
                <button @click="toggleDetailedInfo"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <EyeOff class="h-5 w-5 inline-block mr-1" />
                    Hide Details
                </button>
                <button @click="downloadPDF"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                    <Download class="h-5 w-5 inline-block mr-1" />
                    Download PDF
                </button>
                <button @click="openDialog"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700">
                    Approve
                </button>
                <!-- Dialog -->

                <button class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700">
                    Reject Booking
                </button>
                <button @click="isDialogOpen = true"
                    class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-green-700">
                    Cancel Booking
                </button>

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


            </div>
            <div id="print-section">
                <div v-for="booking in bookingDetails" :key="booking.id"
                    class=" bg-white  overflow-hidden print:shadow-none print:border print:border-gray-300">
                    <div class="relative justify-between h-48 bg-gradient-to-r from-green-600 to-green-800 flex items-center 
                    print:bg-white print:border-b-2 print:border-gray-800">
                    
                        <div>
                            <img class="h-24 w-24 justify-start ms-7" :src="agentData?.agent_data?.logo || '/logo.png'"
                                alt="Logo" />
                        </div>
                        <div class="flex flex-col justify-start text-white ps-4 print:text-black">

                            <h1 class="text-2xl font-bold">
                                {{
                                    agentData?.agent_data?.company_name ||
                                    "Your Branding Text"
                                }}
                            </h1>
                            <p class="">
                                {{
                                    agentData?.agent_data?.mobile ||
                                    "Your Branding Text"
                                }}
                            </p>
                            <p class="">
                                {{
                                    agentData?.agent_data?.address ||
                                    "Your Branding Text"
                                }}
                            </p>
                            <p class="">
                                {{
                                    agentData?.agent_data?.company_email ||
                                    ""
                                }}
                            </p>
                        </div>


                        <div class="flex flex-col text-white px-4 border-l-2 print:text-black print:border-gray-800">
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
                                    <!-- {{ date.departureLocation }} to
                                    {{ date.arrivalLocation }} -->
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
                            <div class="grid grid-cols-2 gap-4">
                                <p class="">Ticke Number:</p>
                                <p class="font-semibold" v-if="pnrDetails?.flightTickets?.length">
                                    {{ pnrDetails?.flightTickets[0]?.number }}

                                </p>
                                <p class="font-semibold" v-else> {{ booking?.ticket_number }}</p>
                            </div>
                        </div>
                    </div>


                    <!-- Flight Information -->
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">
                            FLIGHT INFORMATION
                        </h2>
                        <div v-if="parseFlightData(booking.flight_data)" class="space-y-3">
                            <div v-for="(leg, legIndex) in parseFlightData(booking.flight_data).legs" :key="legIndex"
                                class="space-y-2">
                                <div v-for="(stop, stopIndex) in leg.stops" :key="stopIndex">
                                    <div
                                        class="flex flex-col sm:flex-row justify-between gap-3 p-3 bg-gray-50 rounded-lg text-xs print:border-gray-400">
                                        <div class="flex items-center justify-between flex-1 w-full">
                                            <!-- Departure info -->
                                            <div class="flex-1 max-w-[40%]">
                                                <p class="text-gray-500 font-medium">Departure:</p>
                                                <p class="font-medium">{{ stop.departure.time }}</p>
                                                <p class="text-gray-800 mt-0.5">
                                                    {{ stop.departure.airport.name }}-{{
                                                    stop.departure.airport.iata_code }}
                                                </p>
                                                <p class="text-gray-600">
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
                                                <p class="text-gray-500 font-medium">Arrival:</p>
                                                <p class="font-medium"> {{ stop.arrival.time }}</p>
                                                <p class="text-gray-800 mt-0.5">
                                                    {{ stop.arrival.airport.name }}-{{ stop.arrival.airport.iata_code }}
                                                </p>
                                                <p class="text-gray-600">
                                                    Terminal: {{ stop.arrival.terminal == null ? "N/A" :
                                                        stop.arrival.terminal }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Airline info -->
                                        <div
                                            class="flex items-center gap-2 mt-2 sm:mt-0 border-t sm:border-t-0 pt-2 sm:pt-0 sm:border-l sm:pl-3">
                                            <img :src="stop.airline.logo_url" :alt="stop.airline.name"
                                                class="h-6 w-auto object-contain" />
                                            <div>
                                                <p class="font-medium text-gray-800">
                                                    {{ stop.airline.name }}
                                                </p>
                                                <p class="text-gray-500">
                                                    Flight: {{ stop.flightNumber }} | Aircraft: {{ stop.aircraft.name }}

                                                </p>
                                                <p class="font-medium text-gray-800">Class:</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

               

                <div  v-if="isDetailsInfoVisible">
                    <div class="p-6 border-b border-gray-200 bg-white"  v-for="booking in bookingDetails" :key="booking.id" >
                    <h2 class="text-lg font-bold text-gray-800 mb-2">
                        PASSENGER & TICKET DETAILS
                    </h2>


                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Type</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Title</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Last Name</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">First Name</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Gender</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">DOB</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Nationality</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Doc Type</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">ID/Passport</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Expiry Date</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Issue Country</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Check-In Baggage</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Cabin Baggage</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Ticket No</th>

                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(traveller, index) in booking?.pessangers" :key="index"
                                    class="hover:bg-gray-50">
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.type }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.title }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.last_name }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.first_name }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.gender?.toUpperCase() }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.dob }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.nationality }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.document_type }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.document_no }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.expiry_date }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ traveller?.issue_country }}</td>
                                    <td class="py-1.5 px-2">
                                        {{ parseFlightData(booking.flight_data).passengerInfo[index]?.baggage[0].weight
                                        }}
                                        {{ parseFlightData(booking.flight_data).passengerInfo[index]?.baggage[0].unit }}
                                    </td>
                                    <td class="py-1.5 px-2">5-7Kg</td>
                                    <td class="py-1.5 px-2"> {{ Array.isArray(pnrDetails?.flightTickets) && pnrDetails.flightTickets[index] ?
                                            pnrDetails.flightTickets[index].number : "-" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
                
                
                <div v-if="!isDetailsInfoVisible">
                    <div class="p-6 bg-white" v-for="booking in bookingDetails" :key="booking.id">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">
                        PASSENGER & TICKET DETAILS
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Traveller Name</th>

                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Gender</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Country</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Check-In Baggage</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Cabin Baggage</th>
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
                                        {{ parseFlightData(booking.flight_data)?.passengerInfo[index]?.baggage[0].weight
                                        }}
                                        {{ parseFlightData(booking.flight_data)?.passengerInfo[index]?.baggage[0].unit
                                        }}
                                    </td>
                                    <td class="py-1.5 px-2">5-7Kg</td>

                                    <td class="py-1.5 px-2">
                                        {{ Array.isArray(pnrDetails?.flightTickets) && pnrDetails.flightTickets[index] ?
                                            pnrDetails.flightTickets[index].number : "-" }}
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                </div>
                </div>

                <div class="p-6  bg-white" v-if="isDetailsInfoVisible">
                    <h2 class="text-lg font-bold text-gray-800 mb-2">
                        FARE BREAKDOWN
                    </h2>

                    <div class="overflow-x-auto" v-for="booking in bookingDetails" :key="booking.id">
                        <table class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Type</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Base Fare</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Tax + Fees</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Pax Count</th>
                                    <th class="py-1.5 px-2 text-left font-medium text-gray-600">Total Cost</th>

                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100 ">
                                <tr v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo"
                                    :key="index" class="hover:bg-gray-50">
                                    <td class="py-1.5 px-2 uppercase">{{ passenger.passengerType }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{ formatCurrency(
                                        passenger.passengerTotalFare.equivalentAmount
                                        + parseFloat(agentData?.agent_data?.margin_amount),
                                        passenger.passengerTotalFare.currency
                                        ) }}</td>
                                    <td class="py-1.5 px-2 uppercase">{{
                                        formatCurrency(passenger.passengerTotalFare.totalTaxAmount,
                                            passenger.passengerTotalFare.currency
                                        ) }}

                                    </td>
                                    <td class="py-1.5 px-2 uppercase">{{ passenger.passengerNumber }}</td>
                                    <td class="py-1.5 px-2 uppercase font-bold">{{ formatCurrency(
                                        (passenger.passengerTotalFare.totalFare *
                                            passenger.passengerNumber)
                                        + parseFloat(agentData?.agent_data?.margin_amount),
                                        passenger.passengerTotalFare.currency
                                        ) }} </td>
                                </tr>

                                <tr class="bg-gray-100 font-semibold">
                                    <td class="py-2 px-2 uppercase">TOTAL</td>
                                    <td class="py-2 px-2 uppercase">{{ }}</td>
                                    <td class="py-2 px-2 uppercase">{{ }}</td>
                                    <td class="py-2 px-2 uppercase">{{ }}</td>
                                    <td class="py-2 px-2 uppercase font-bold">{{
                                        formatCurrency(parseFlightData(booking.flight_data)?.pricing?.totalPrice
                                            + parseFloat(agentData?.agent_data?.margin_amount) *
                                            parseFlightData(booking.flight_data)?.passengerInfo?.length,
                                            parseFlightData(booking.flight_data).pricing.currency
                                        ) }}
                                    </td>
                                </tr>

                                {{ }}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-4">
                    <h2 class="text-lg font-semibold mb-3">Cancellation & Amendment Policies</h2>
                    <ul class="list-disc pl-5 space-y-2 text-gray-700">
                        <li>Cancellation of a ticket can be done either by emailing us or directly through the airline.
                        </li>
                        <li>For ticket amendments, please send us an email along with your booking reference (e.g., Trip
                            20 - AK 180XXXXXX) and the new travel details.</li>
                        <li>Cancellations or amendments must be made at least 24 hours prior to departure.</li>
                        <li>Requests made within 24 hours of departure should be directed to the airline.</li>
                        <li>Cancellation policy follows airline rules. Special and promotional fares are non-refundable.
                        </li>
                        <li>If you have canceled your booking directly with the airline, please email us to process the
                            applicable refund.</li>
                       
             
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
        <div v-if="showDialog" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50">
            <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-lg">
                <h2 class="text-lg font-bold text-gray-800">
                    Approve Action
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Please enter the required input to approve.
                </p>

                <!-- Input Field -->
                <div class="mt-4">
                    <label for="approvalInput" class="block text-sm font-medium text-gray-700">Enter Airline
                        PNR</label>
                    <input id="approvalInput" type="text" v-model="airLinePnr"
                        class="w-full px-4 py-2 mt-2 text-gray-700 border rounded-md focus:outline-none focus:ring focus:ring-gray-300" />
                </div>
                <div class="mt-4">
                    <label for="approvalInput" class="block text-sm font-medium text-gray-700">Enter Ticket
                        Number</label>
                    <input id="approvalInput" type="text" v-model="ticketNo"
                        class="w-full px-4 py-2 mt-2 text-gray-700 border rounded-md focus:outline-none focus:ring focus:ring-gray-300" />
                </div>

                <!-- Actions -->
                <div class="flex justify-end mt-6 space-x-4">
                    <button @click="closeDialog"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Cancel
                    </button>
                    <button @click="approveAction"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700">
                        Approve
                    </button>
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