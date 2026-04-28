<script setup>
import { ref, computed, onMounted } from "vue";
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
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { MoveRight } from "lucide-vue-next";

import {
    FETCH_AGENT_DATA,
    UPDATE_USER_STATUS,
    FETCH_TOTAL_APPROVED_DEPOSIT,
    SAVE_AGENT_MARGIN,
    FETCH_AGENT_LEDGER,
    FETCH_BOOKING_DATA,
    SAVE_AGENT_CHARGES,
    FETCH_PNR_DETAILS,
    SAVE_ADMIN_BOOKING,
} from "@/services/store/actions.type";
import { calculateLayoverTime, formatAmount } from "@/lib/utils";

const store = useStore();
const route = useRoute();
const router = useRouter();
let isOpen = ref(false);
const amount = ref("");
const validationErrors = ref([]);

const loading = ref(true);
const error = ref(null);
const charges = ref("");
const chargesDate = ref("");
const chargesDec = ref("");
const chargeType = ref("charge");
let isChargesOpen = ref(false);


const agentData = computed(() => store.getters["user/agentData"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const bookings = computed(() => store.getters["flight/bookingData"]);
const agentDepositData = computed(() => store.getters["deposit/depositData"]);
const bookingData = computed(() => store.getters["flight/pnrData"]);
const pnr = ref(null);
const totalAmount = ref(null);
const cMargin = ref(null);


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

function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_PNR_DETAILS, {
        pnr: pnr.value,
    });

   
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

function closeDialog() {
    isOpen.value = false;
}
function openChargesDialog() {
    isChargesOpen.value = true;
}

function closeChargesDialog() {
    isChargesOpen.value = false;
}

function setMargin() {
    let errors = [];

    if (!amount.value) {
        errors.push("Margin amount is required.");
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
const capitalize = (string) => {
    return string.charAt(0).toUpperCase() + string.slice(1);
};
const formatDuration = (minutes) => {
    if (!minutes) return '';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
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

    store
        .dispatch("user/" + SAVE_AGENT_CHARGES, {
            amount: charges.value,
            date: chargesDate.value,
            description: chargesDec.value,
            chargeType: chargeType.value,
            user_id: route.query.user_id,
        })
        .then(() => {
            closeChargesDialog();

            charges.value = null;
            chargesDate.value = null;
            chargesDec.value = null;

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

function saveBooking() {
    if (!cMargin) {
        error.value = "No Margin provided.";
        return;
    }
    if (!bookingData) {
        error.value = "No Booking Data provided.";
        return;
    }
    store.dispatch("flight/" + SAVE_ADMIN_BOOKING, {
        bookingData: bookingData.value,
        margin: cMargin.value,
        agentId: route.query.user_id,
        pnr: pnr.value,
    }).then(function () {
        router.push(
            { name: "ImportPnr" }
        );
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
                    <Button @click="$router.push({ name: 'Dashboard' })" variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-1" />
                        Back
                    </Button>
                    <h1 class="text-3xl font-medium leading-none tracking-tight text-gray-900">
                        User Details(AddBooking)
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
                            Balance:
                            Rs
                            {{ formatCurrency(agentLedger?.balance) }}
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

                    <div class="grid grid-cols-1">
                        <div class="flex gap-4 p-4">
                        <Input v-model="pnr" type="text" class="w-[200px]" placeholder="PNR" />
                        <Button @click="fetchPnrDetails"> Import PNR</Button>

                    </div>
                    <div class="flex gap-4 p-4">
                        <Input v-model="cMargin" type="text" class="w-[200px]" placeholder="Margin" />
                        <Button @click="saveBooking"> Save Ticket</Button>

                    </div>

                    </div>
                </div>
            </div>
            <!-- <pre>{{ bookingData }}</pre> -->
            <div class="min-h-screen print-container">
                <div class="print:hidden bg-white p-4 flex justify-end space-x-2 max-w-6xl mx-auto" id="topBar">
                    <!-- <button
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                        Print
                    </button> -->
                    <!-- <button
                        @click="emailBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    >
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button> -->




                </div>

                <div id="print-section" v-if="bookingData"
                    class="max-w-6xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden print:shadow-none print:border print:border-gray-300">
                    <div class="relative justify-between h-48 bg-gradient-to-r from-gray-600 to-gray-800 flex items-center 
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
                                <p class="font-semibold"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p class="">GDS PNR:</p>
                                <p class="font-semibold capitalize">{{ bookingData?.bookingId }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p class=" ">Airline PNR:</p>
                                <p class="font-semibold capitalize">
                                    {{ bookingData?.flights[0]?.confirmationId }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p class=" ">Status:</p>
                                <p class="font-semibold capitalize">
                                    {{ bookingData?.isTicketed ? "Ticketed" : "Not Ticketed" }}
                                </p>
                            </div>

                        </div>
                    </div>


                    <!-- Flight Information -->
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">
                            FLIGHT INFORMATION
                        </h2>
                        <div class="space-y-3">
                            <div v-for="(flight, index) in bookingData?.flights" :key="index" class="space-y-2">

                                <!-- Flight info section -->
                                <div
                                    class="flex flex-col sm:flex-row justify-between gap-3 p-3 bg-gray-50 rounded-lg text-xs print:border-gray-400">
                                    <div class="flex items-center justify-between flex-1 w-full">
                                        <!-- Departure info -->
                                        <div class="flex-1 max-w-[40%]">
                                            <p class="text-gray-500 font-medium">Departure:</p>
                                            <p class="font-medium">{{ flight.departureDate

                                                }}-{{ flight.departureTime }}</p>
                                            <p class="text-gray-800 mt-0.5">
                                                {{ flight.fromAirportCode }}
                                                <!-- {{ stop.departure.airport.name }}-{{ stop.departure.airport.iata_code }} -->
                                            </p>
                                            <p class="text-gray-600">
                                                {{ flight.departureTerminalName }}
                                                <!-- Terminal: {{ stop.departure.terminal == null ? "N/A" :
                                                    stop.departure.terminal }} -->
                                            </p>
                                        </div>

                                        <!-- Flight duration -->
                                        <div class="flex flex-col items-center px-1">
                                            <MoveRight class="h-5 w-5 text-gray-400" />
                                            <div class="text-xs text-gray-500 mt-0.5">
                                                {{ formatDuration(flight.durationInMinutes) }}
                                            </div>
                                        </div>

                                        <!-- Arrival info -->
                                        <div
                                            class="flex-1 max-w-[40%] text-right print:border-l-2 print:border-gray-300">
                                            <p class="text-gray-500 font-medium">Arrival:</p>
                                            <p class="font-medium">
                                            </p>
                                            <p class="text-gray-800 mt-0.5">
                                                {{ flight.arrivalDate

                                                }}-{{ flight.arrivalTime }}
                                            </p>
                                            <p class="text-gray-600">
                                                {{ flight.arrivalTerminalName }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Airline info -->
                                    <div
                                        class="flex items-center gap-2 mt-2 sm:mt-0 border-t sm:border-t-0 pt-2 sm:pt-0 sm:border-l sm:pl-3">
                                        <!-- <img :src="stop.airline.logo_url" :alt="stop.airline.name"
                                            class="h-6 w-auto object-contain" /> -->
                                        <div>
                                            <p class="font-medium text-gray-800">
                                                {{ flight.airlineName }}
                                            </p>
                                            <p class="text-gray-500">
                                                Flight: {{ flight.flightNumber }}

                                            </p>
                                            <p class="text-gray-500">
                                                Aircraft: {{ flight.aircraftTypeName }}

                                            </p>
                                            <p class="font-medium text-gray-800">Class:
                                                {{ flight.cabinTypeName }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Layover information - show only between stops -->
                                <!-- <div v-if="index < bookingData.flights.length - 1"
                                    class="flex items-center justify-center mt-2 text-xs text-gray-600">
                                    <div 
                                        class="flex items-center gap-2 bg-amber-50 px-4  py-2 rounded-md border border-amber-200">
                                        <Clock class="h-4 w-4 text-amber-500" />
                                        <span class="font-medium">
                                            Layover: {{ layoverTime(flight.arrivalDate,flight.arrivalTime,flight.departureDate,flight.departureTime) }}
                                        </span>
                                        <span>at </span>
                                    </div>
                                </div>
                            -->
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            PASSENGER & TICKET DETAILS
                        </h2>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Traveller Name</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Check-In Baggage
                                        </th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Cabin Baggage</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Ticket No</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(traveller, index) in bookingData?.travelers" :key="index"
                                        class="hover:bg-gray-50 transition-colors">
                                        <td class="py-1.5 px-2 uppercase">
                                            {{ traveller.surname }} {{ traveller.givenName }}
                                            <span class="text-gray-500 text-xs ml-1">({{ traveller.passengerCode
                                            }})</span>
                                        </td>


                                        <td class="py-1.5 px-2">
                                            {{
                                                bookingData?.fareOffers[0]?.checkedBaggageAllowance?.totalWeightInKilograms
                                            }} (KG)
                                        </td>
                                        <td class="py-1.5 px-2">
                                            5-7Kg
                                        </td>

                                        <td class="py-1.5 px-2">
                                            {{ bookingData?.flightTickets[index]?.number }}
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">
                            FARE BREAKDOWN
                        </h2>


                        <div class="overflow-x-auto">
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
                                <!-- <tbody class="divide-y divide-gray-100 ">
                                    <tr v-for="(traveller, index) in bookingData?.travelers" :key="index"
                                        class="hover:bg-gray-50">
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.passengerCode }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{
                                            formatCurrency(bookingData?.fares[index]?.totals?.subtotal) }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{
                                            formatCurrency(bookingData?.fares[index]?.totals?.taxes
                                            ) }}

                                        </td>
                                        <td class="py-1.5 px-2 uppercase font-bold">{{ formatCurrency(
                                            bookingData?.fares[index]?.totals?.total
                                            ) }} </td>

                                    </tr>




                                </tbody> -->
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

                            <li>Lima Travels service charge: PKR 1000 per passenger.</li>
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
    </div>
</template>
