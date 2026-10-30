<!-- <script setup>
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
    APPROVE_BOOKING
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";

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
// const pnrLive = ref(null);

const pnrLive = computed(() => 
  pnrDetails.value?.bookingId 
    ? pnrDetails.value.bookingId 
    : booking.value?.pnr || '-'
);



function fetchAgent() {
    if (agent_id != null) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: agent_id,
            });
            isLoading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            isLoading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        isLoading.value = false;
    }
}

function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isLoading.value = false;
        return;
    }
    try {
        isLoading.value = true;
        store.dispatch("flight/" + FETCH_BOOKING_DETAILS, {
            bookingId: booking_id,
        });
    } catch (err) {
        error.value = "Failed to fetch booking details.";
    }finally {
        isLoading.value = false;
    }
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

 function fetchAllData() {
    try {
        isLoading.value = true;
        Promise.all([fetchBookingDetails(), fetchPnrDetails()]);
        ////console.log("PNR Live:", pnrDetails);

        
    } catch (err) {
        error.value = "Failed to load data.";
        isLoading.value = false;
    }finally {
        isLoading.value = false;
    }
}


onMounted(() => {

    if (user.value == null) {
        authStore.fetchUser();
        // fetchAgent();
    } else {
        fetchAgent();
    }
    fetchAllData();


});
</script> -->

<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, EyeIcon } from "lucide-vue-next";
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
    VOID_BOOKING,
    VOID_REQUEST,
} from "@/services/store/actions.type";
import moment from "moment";
import ATETicket from "@/components/AT/ATETicket.vue";
import Badge from "@/components/ui/badge/Badge.vue";
import { SEND_EMAIL } from "../../services/store/actions.type";
import Label from "@/components/common/Label.vue";
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";


const store = useStore();
const route = useRoute();
const router = useRouter();

const authStore = useAuthStore();
const loading = ref(true);

// Loading states for individual API calls
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const actionLoading = ref(false);
const agent_id = route.query.agent_id;
const error = ref(null);
const isLoading = computed(() => isBookingDetailsLoading.value || isPnrDetailsLoading.value || isAgentLoading.value || actionLoading.value || store.getters['flight/isLoading']);


const user = computed(() => authStore.user);
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

const booking_id = route.query.booking_id;
const pnr = route.query.pnr;
const pnrData = ref(null);
const sooperResponse = ref(null);
const bookingId = ref("");
const airlinesMargin = ref([]);
const custEmail = ref(null);
const isDialogOpen = ref(false);
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const passengerCount = ref();
const agentAmount = ref();
const agentDiscount = ref();
const margin = ref();

const pnrDetails = computed(() => store.getters["flight/pnrData"]);



const booking = ref(null);
const flightData = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref(0);


function sendEmail() {

    //console.log("email",custEmail?.value);
    store.dispatch("flight/" + SEND_EMAIL, {
        email: custEmail?.value ? custEmail?.value : bookingDetails?.value?.[0]?.main_email,
        booking_id: bookingDetails.value?.[0]?.flight_id,
        booking_source: route?.query?.booking_source



    }
    );
    isEmailDialogOpen.value = false;
    custEmail.value = null;
}


function fetchAgentLedger() {
    if (agent_id) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
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

async function fetchAgent() {
    if (agent_id) {
        try {
            await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: agent_id });
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
        await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id, bookingSource: route.query.booking_source });
        parsePnrResponse();
        // parseSooperResponse();
        flightData.value = parseFlightData(bookingDetails?.value[0]?.flight_data);
        passengerCount.value = parseInt(bookingDetails?.value?.[0].pessangers.length || 1);
        agentAmount.value = parseFloat(bookingDetails?.value?.[0].agent_markup || 0);
        agentDiscount.value = parseFloat(bookingDetails?.value?.[0].agent_discount || 0);
        margin.value = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);

    } catch (err) {
        error.value = "Failed to fetch booking details.";
    } finally {
        isBookingDetailsLoading.value = false;
    }

}

async function voidRequest() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isBookingDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${VOID_REQUEST}`, {
            pnr: pnr,

            booking_uuid: pnrData.value?.data?.uuid ?? "null",
            billable_price: pnrData.value?.data?.billable_price ?? "null",
            currency: pnrData.value?.data?.currency?.code ?? "null",
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            booking_status: "voided",
            booking_source: route.query.booking_source,
        });
        isVoidDialogOpen.value = false;
    } catch (err) {
        error.value = "Failed to fetch booking details.";
    } finally {
        isBookingDetailsLoading.value = false;
        fetchBookingDetails();
    }
}

function calculatePassengerFare(passenger, flightIndex) {
    const flight =
        flightData?.value?.original?.leg?.flights?.[flightIndex] ??
        flightData?.value?.leg?.flights?.[flightIndex];

    const fare = flight?.fares?.[0];
    if (!fare) return 0;

    const basePrice = parseFloat(passenger?.base_price || 0);
    const marginAmount = fare?.margin_amount;
    const marginType = fare?.margin_type;
    const amountType = fare?.amount_type;

    // Calculate fare with system margin
    const finalPrice = calculateFinalPrice(basePrice, marginAmount, marginType, amountType);

    // Add agent margin
    const agentMargin = parseFloat(agentData?.value?.agent_data?.margin_amount || 0);
    const agentDiscount = parseFloat(agentData?.value?.agent_data?.agent_discount || 0);

    return finalPrice + agentMargin - agentDiscount;
}

function getMatchedFare(flight) {
    const fareReferences = parseFlightData(bookingDetails.value?.[0]?.fare_reference || "[]");
    return flight?.fares?.find(f => fareReferences.includes(f.ref_id));
}
function calculatePassengerFinalFare(passenger, flightIndex,) {
    const flight =
        flightData?.value?.original?.leg?.flights?.[flightIndex] ??
        flightData?.value?.leg?.flights?.[flightIndex];

    const fare = getMatchedFare(flight);
    if (!fare) return 0;

    const basePrice = parseFloat(passenger?.base_price || 0);

    // System margin
    const systemFare = calculateFinalPrice(
        basePrice,
        fare?.margin_amount,
        fare?.margin_type,
        fare?.amount_type || 0
    );
    // Extra passenger charges
    const extraCharges =
        parseFloat(passenger?.fee || 0) +
        parseFloat(passenger?.taxes || 0) +
        parseFloat(passenger?.surcharge || 0) +
        parseFloat(passenger?.service_charges || 0) +
        parseFloat(passenger?.ancillaries_charges || 0);

    // Agent margin
    const agentMargin = parseFloat(agentData?.value?.agent_data?.margin_amount || 0);
    const agentDiscount = parseFloat(agentData?.value?.agent_data?.agent_discount || 0);
    return systemFare + extraCharges + agentMargin - agentDiscount;
}
function calculatePnrFinalFare() {
    const leg = parseFlightData(bookingDetails.value?.[0]?.flight_data).leg;
    if (!leg?.flights?.length) return 0;

    const flights = leg.flights;

    // ✅ Collect unique carriers
    const uniqueCarriers = {};
    flights.forEach((flight) => {
        const carrier = flight?.marketing_carrier?.name;
        if (carrier && !uniqueCarriers[carrier]) {
            uniqueCarriers[carrier] = flight;
        }
    });

    // ✅ System fare (base_price + margin for each passenger in matched fares)
    const systemFare = flights.reduce((flightSum, flight) => {
        return (
            flightSum +
            (flight.fares || []).reduce((fareSum, fare) => {
                return (
                    fareSum +
                    (fare.passenger_fares || []).reduce((paxSum, passenger) => {
                        const basePrice = parseFloat(passenger?.base_price || 0);

                        return (
                            paxSum +
                            calculateFinalPrice(
                                basePrice,
                                fare?.margin_amount,
                                fare?.margin_type,
                                fare?.amount_type || 0
                            )
                        );
                    }, 0)
                );
            }, 0)
        );
    }, 0);

    // ✅ Extra charges
    const passengerCharges = flights.reduce((flightSum, flight) => {
        return (
            flightSum +
            (flight.fares || []).reduce((fareSum, fare) => {
                return (
                    fareSum +
                    (fare.passenger_fares || []).reduce((paxSum, passenger) => {
                        return (
                            paxSum +
                            parseFloat(passenger?.fee || 0) +
                            parseFloat(passenger?.taxes || 0) +
                            parseFloat(passenger?.surcharge || 0) +
                            parseFloat(passenger?.service_charges || 0) +
                            parseFloat(passenger?.ancillaries_charges || 0)
                        );
                    }, 0)
                );
            }, 0)
        );
    }, 0);

    // ✅ Agent margin / discount (applied per passenger)
    const totalPassengers = flights.reduce((count, flight) => {
        return (
            count +
            (flight.fares || []).reduce((fareCount, fare) => {
                return fareCount + (fare.passenger_fares?.length || 0);
            }, 0)
        );
    }, 0);

    const agentMargin =
        parseFloat(agentData?.value?.agent_data?.margin_amount || 0) *
        parseFloat(totalPassengers);
    const agentDiscount =
        parseFloat(agentData?.value?.agent_data?.agent_discount || 0) *
        parseFloat(totalPassengers);

    return systemFare + passengerCharges + agentMargin - agentDiscount;
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
    //console.log("agenledger", agentLedger?.value.balance);
    //console.log("totalTicketPrice", totalTicketPrice?.value);
    if (agentLedger?.value.balance < totalTicketPrice?.value) {
        isLowBalanceDialogOpen.value = true;
        return;
    }

    isConfirmDialogOpen.value = true;
}

function handleCancelBooking() {
    error.value = '';
    actionLoading.value = true;

    try {
        if (!pnr) {
            error.value = "No PNR provided.";
            return;
        }

        // store.dispatch("flight/" + CANCEL_BOOKING, {
        //     pnr: pnr,

        //     booking_uuid: pnrData.value?.data?.uuid ?? "null",
        //     billable_price: pnrData.value?.data?.billable_price ?? "null",
        //     currency: pnrData.value?.data?.currency?.code ?? "null",
        //     pnr: route.query.pnr,
        //     bookingId: bookingDetails.value[0].id,
        //     booking_status: "canceled",
        //     booking_source: route.query.booking_source,

        // });

        // store.dispatch("flight/" + CONFIRM_BOOKING, {
        //     pnr: route.query.pnr,
        //     bookingId: bookingDetails.value[0].id,
        //     booking_status: "canceled",
        // });

        // const updatedBooking = { ...bookingDetails.value[0], status: "canceled" };
        // store.commit("flight/SET_BOOKING_DETAILS", [updatedBooking]);
        // Close dialog after successful cancellation
        isDialogOpen.value = false;

    } catch (err) {
        error.value = err.message || 'Failed to cancel booking';
    } finally {
        actionLoading.value = false;
        fetchBookingDetails();
    }
};
async function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        isPnrDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { flight_provider: route.query.flight_provider, pnr: pnrData?.value?.TransactionID }).then(() => {
            //console.log("pnrDetails", pnrDetails.value);
            calculateGrandTotal();
        });
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
        booking_source: route.query.flight_provider,
        flight_source: parseFlightData(bookingDetails?.value?.[0]?.flight_data)?.provider?.source ?? null,
        bookingId: bookingDetails.value[0].id,
        orderId: parseFlightData(bookingDetails?.value?.[0]?.pnr_response)?.order?.id ?? null,


    }).then(() => {
        // Close dialog after successful cancellation
        isDialogOpen.value = false;
        fetchBookingDetails();
    }).catch((err) => {
        error.value = err.message || 'Failed to cancel booking';
    });

    // store.dispatch("flight/" + CONFIRM_BOOKING, {
    //     pnr: route.query.pnr,

    //     bookingId: bookingDetails.value[0].id,
    //     booking_status: "canceled",
    // });
}
async function confirmBooking() {
    error.value = '';
    actionLoading.value = true;
    if (!pnr) {
        error.value = "No PNR provided.";
        actionLoading.value = false;
        return;
    }
    try {
        await store.dispatch("flight/" + CONFIRM_BOOKING, {
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            TUI: pnrData.value?.TUI ?? "null",
            TransactionID: pnrData.value?.TransactionID ?? "null",
            net_amount: pnrData.value?.NetAmount ?? "null",
            booking_status: "ticketed",
            booking_source: route.query.booking_source,
            flight_provider: route.query.flight_provider,
            totalTicketPrice: totalTicketPrice.value,
        });

        isConfirmDialogOpen.value = false;
        await fetchBookingDetails();
    } finally {
        actionLoading.value = false;
    }
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

function parsePnrResponse() {
    try {
        const pnrResponseString = bookingDetails?.value?.[0]?.pnr_response;

        if (pnrResponseString) {
            pnrData.value = JSON.parse(pnrResponseString);
        } else {
            //console.log("No pnr_response found in bookingDetails");
            pnrData.value = null;
        }
    } catch (e) {
        console.error("Failed to parse pnr_response:", e);
        pnrData.value = null;
    }


            fetchPnrDetails();

}

function parseSooperResponse() {
    try {
        const sooperResponseString = bookingDetails?.value?.[0]?.sooper_response;

        if (sooperResponseString) {
            sooperResponse.value = JSON.parse(sooperResponseString);
        } else {
            //console.log("No sooper_response found in bookingDetails");
            sooperResponse.value = null;
        }
    } catch (e) {
        console.error("Failed to parse sooper_response:", e);
        sooperResponse.value = null;
    }
}




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
function calculateTaxes(fare) {
    return (
        parseFloat(fare?.taxes || 0) +
        parseFloat(fare?.surchage || 0) +
        parseFloat(fare?.fees || 0) +
        parseFloat(fare?.service_charges || 0) +
        parseFloat(fare?.ancillaries_charges || 0)
    );
}

function calculateTotalFare(fare) {
    const passengerCount = parseInt(bookingDetails?.value?.[0].pessangers.length || 1);
    const agentAmount = parseFloat(bookingDetails?.value?.[0].agent_markup || 0);
    const agentDiscount = parseFloat(bookingDetails?.value?.[0].agent_discount || 0);
    const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);

    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);

    const billable = parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));

    const total = billable + (agentAmount * passengerCount) + margin - (agentDiscount * passengerCount);
    return total;
}

function calculateGrandTotal() {
    let total = 0;
    let flightData = parseFlightData(bookingDetails?.value?.[0]?.flight_data);
    let selectedFares = parseFlightData(bookingDetails?.value?.[0]?.fare_reference || "[]");
    flightData?.leg?.flights?.forEach((flight, index) => {
        flight?.fares?.forEach(fare => {

            if (selectedFares.includes(fare.ref_id)) {
                total += calculateTotalFare(fare)
            }
        });
    });
    
    totalTicketPrice.value = total + (pnrDetails?.value?.SSRAmount ?? pnrData?.value?.SSRAmount) || 0;
    return totalTicketPrice.value;
}

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
        // fetchAgent();
    } else {
        fetchAgent();
    }
    fetchAgentLedger();
    fetchBookingDetails();
    // fetchPnrDetails();
});
</script>

<template>
    <section>
        <ATFlowLoader
            v-if="isLoading"
            title="Loading booking details"
            message="We are refreshing the AT booking, fare, and ticket information."
            :steps="['Booking', 'PNR', 'Ticket']"
        />

        <div v-else class="min-h-screen bg-gray-100">
            <div v-if="route?.query?.booking_source == 1">
                <div v-for="booking in bookingDetails" :key="booking.id"
                    class="bg-white p-4 gap-2 flex justify-end mx-auto " id="topBar">
                    <button @click="printBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                        Print
                    </button>
                    <!-- <a target="blank" :href="bookingDetails[0]?.booking_invoice?.invoice_url">
                        <button
                            class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <EyeIcon class="h-5 w-5 inline-block mr-1" />
                            View Invoice
                        </button>
                    </a> -->
                    <!-- <button @click="isEmailDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button> -->
                    <button @click="downloadPDF"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <Download class="h-5 w-5 inline-block mr-1" />
                        Download pdf
                    </button>
                    <button @click="toggleFareInfo"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <EyeOff class="h-5 w-5 inline-block mr-1" />
                        <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                        <span v-else>View Fare Details</span>
                    </button>

                    <!-- Trigger Button -->
                    <button
                        :hidden="['canceled', 'requested', 'booked', 'voided'].includes(booking?.status?.toLowerCase())"
                        :disabled="['canceled', 'requested', 'voided'].includes(booking?.status?.toLowerCase())"
                        @click="isVoidDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        {{ isVoided ? 'Void Request Sent' : 'Void Booking' }}

                    </button>
                    <Dialog :open="isVoidDialogOpen" @update:open="isVoidDialogOpen = $event">
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle class="text-lg font-semibold">Confirm Void</DialogTitle>
                            </DialogHeader>
                            <div class="text-sm text-gray-700 mb-4">
                                Are you sure you want to void this booking? This action cannot be undone.
                            </div>
                            <div class="flex justify-end gap-2">
                                <Button variant="secondary" @click="isVoidDialogOpen = false">
                                    Cancel
                                </Button>
                                <Button variant="destructive" @click="voidRequest">
                                    Confirm
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>

                    <button @click="isDialogOpen = true"
                        :disabled="['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed">
                        {{ booking?.status?.toLowerCase() === 'canceled'
                            ? 'Canceled'
                            : booking?.status?.toLowerCase() === 'ticketed'
                                ? 'Ticketed'
                                : booking?.status?.toLowerCase() === 'issued'
                                    ? 'Issued'
                                    : 'Cancel Booking'
                        }}

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
                                        agentData?.agent_data.company_email }}</Label>
                                    Or enter new one
                                    <Input type="text" v-model="custEmail"
                                        class="flex-1 mt-2 rounded-md border-gray-300 shadow-sm focus:border-[#0056FF] focus:ring-[#0056FF]"
                                        placeholder="Enter email" />
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button @click="isEmailDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                    Cancel
                                </button>
                                <button @click="sendEmail"
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
                                <button @click="cancelBooking"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Cancellation
                                </button>
                            </div>
                        </div>
                    </div>
                    <button
                        :disabled="['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status)"
                        @click="handleConfirmDialogOpen"
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
                    <ATETicket v-for="booking in bookingDetails" :key="booking.id" :booking="booking"
                        :agent="agentData?.agent_data"
                        :pnr="pnrDetails?.bookingId || booking?.itinerary_ref || '-'"
                        :airline-pnr="pnrDetails?.flights?.[0]?.confirmationId || booking?.airline_pnr || '-'"
                        :pnr-details="pnrDetails"
                        :source="parseFlightData(booking.flight_data)?.provider?.source || ''"
                        :show-fare="isDetailsInfoVisible">
                        <template #fare-rows>
                            <tr v-if="pnrDetails?.fares?.length">
                                <td>
                                    {{ parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.[0]?.segments?.[0]?.from?.iata || "-" }}
                                    →
                                    {{ parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.slice(-1)?.[0]?.segments?.slice(-1)?.[0]?.to?.iata || "-" }}
                                </td>
                                <td>
                                    {{ formatAmount(
                                        calculateFinalPrice(
                                            pnrDetails?.fares?.[0]?.totals?.subtotal,
                                            fare?.margin_amount,
                                            fare?.margin_type,
                                            fare?.amount_type || 0
                                        )
                                        + (
                                            (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                                                + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                                                - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                                            * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                                        )
                                    ) }}
                                </td>
                                <td>{{ formatAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}</td>
                                <td>
                                    {{ formatAmount(
                                        parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0)
                                        + (
                                            (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                                                + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                                                - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                                            * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                                        )
                                    ) }}
                                </td>
                            </tr>
                            <template v-else>
                                <template
                                    v-for="(flight, index) in parseFlightData(bookingDetails[0]?.flight_data)?.leg?.flights"
                                    :key="index">
                                    <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                        const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                            ? parseFlightData(bookingDetails[0]?.fare_reference)
                                            : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                        return fareRefs.includes(f.ref_id);
                                    })" :key="fareIndex">
                                        <td>{{ flight?.from?.iata }} → {{ flight?.to?.iata }}</td>
                                        <td>
                                            {{ formatAmount(
                                                calculateFinalPrice(
                                                    fare?.base_price,
                                                    fare?.margin_amount,
                                                    fare?.margin_type,
                                                    fare?.amount_type
                                                ) +
                                                parseFloat(agentAmount * passengerCount) -
                                                parseFloat(agentDiscount * passengerCount) +
                                                margin
                                            ) }}
                                        </td>
                                        <td>{{ formatAmount(calculateTaxes(fare)) }}</td>
                                        <td>{{ formatAmount(calculateTotalFare(fare)) }}</td>
                                    </tr>
                                </template>
                            </template>
                        </template>
                        <template #fare-summary>
                            <div class="et-tot"><span>Total amount</span><b>{{ formatAmount(totalTicketPrice) || "" }}</b></div>
                        </template>
                    </ATETicket>
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
                                        agentData?.agent_data.company_email }}</Label>
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
                                <button
                                    :disabled="['canceled', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                                    @click="handleCancelBooking"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    Confirm Cancellation
                                </button>
                            </div>
                        </div>
                    </div>
                    <button @click="handleConfirmDialogOpen"
                        :disabled="['canceled', 'ticketed',].includes(booking?.status?.toLowerCase())"
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
                    <ATETicket v-for="booking in bookingDetails" :key="booking.id" :booking="booking"
                        :agent="agentData?.agent_data"
                        :pnr="pnrDetails?.bookingId || booking?.pnr || '-'"
                        :airline-pnr="pnrDetails?.flights?.[0]?.confirmationId || booking?.airline_pnr || '-'"
                        :pnr-details="pnrDetails" :show-fare="isDetailsInfoVisible"
                        :fare-columns="['Pax type', 'Base fare', 'Tax + Fees', 'Total cost']">
                        <!-- Older bookings store legs/stops instead of leg.flights, so they fill the shared layout here. -->
                        <template #flights>
                            <section v-for="(leg, legIndex) in (parseFlightData(booking.flight_data)?.original?.legs ?? parseFlightData(booking.flight_data)?.legs)"
                                :key="legIndex" class="et-sec">
                                <div class="et-route">
                                    <strong>{{ legIndex === 0 ? 'Onward' : `Flight ${legIndex + 1}` }} ·
                                        {{ leg.stops?.[0]?.departure?.airport?.name }} →
                                        {{ leg.stops?.[leg.stops.length - 1]?.arrival?.airport?.name }}</strong>
                                    <span>{{ leg.stops?.length > 1 ? `${leg.stops.length - 1} stop(s)` : 'Non-stop' }}</span>
                                </div>
                                <div v-for="(stop, stopIndex) in leg.stops" :key="stopIndex" class="et-flight">
                                    <div>
                                        <span class="et-lbl">Flight</span>
                                        <span class="et-val">{{ stop.flightNumber }}</span>
                                        <div class="et-sub">{{ stop.airline?.name }}</div>
                                        <div class="et-sub">{{ stop.aircraft?.name }}</div>
                                    </div>
                                    <div>
                                        <span class="et-lbl">From</span>
                                        <span class="et-val">{{ stop.departure?.airport?.name }} ({{ stop.departure?.airport?.iata_code }})</span>
                                        <div v-if="stop.departure?.terminal" class="et-sub">Terminal {{ stop.departure.terminal }}</div>
                                    </div>
                                    <div>
                                        <span class="et-lbl">Departure</span>
                                        <span class="et-val">{{ getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                            stop?.departure?.time, stop?.adjustment).date }}</span>
                                        <div class="et-val et-time">{{ getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                            stop?.departure?.time, stop?.adjustment).time }}</div>
                                    </div>
                                    <div>
                                        <span class="et-lbl">To</span>
                                        <span class="et-val">{{ stop.arrival?.airport?.name }} ({{ stop.arrival?.airport?.iata_code }})</span>
                                        <div v-if="stop.arrival?.terminal" class="et-sub">Terminal {{ stop.arrival.terminal }}</div>
                                    </div>
                                    <div>
                                        <span class="et-lbl">Arrival</span>
                                        <span class="et-val">{{ getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                            stop?.arrival?.time, stop?.adjustment).date }}</span>
                                        <div class="et-val et-time">{{ getAdjustedDateTime(parseFlightData(booking?.flight_data).dates[0].departureDate,
                                            stop?.arrival?.time, stop?.adjustment).time }}</div>
                                    </div>
                                </div>
                            </section>
                        </template>

                        <template #fare-rows>
                            <tr v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo" :key="index">
                                <td>{{ passenger.passengerType }} x {{ passenger.passengerNumber }}</td>
                                <td>{{ formatCurrency(
                                    passenger.passengerTotalFare.equivalentAmount
                                    + parseFloat(agentData?.agent_data?.margin_amount),
                                    passenger.passengerTotalFare.currency
                                ) }}</td>
                                <td>{{ formatCurrency(passenger.passengerTotalFare.totalTaxAmount, passenger.passengerTotalFare.currency) }}</td>
                                <td>{{ formatCurrency(
                                    (passenger.passengerTotalFare.totalFare * passenger.passengerNumber)
                                    + parseFloat(agentData?.agent_data?.margin_amount),
                                    passenger.passengerTotalFare.currency
                                ) }}</td>
                            </tr>
                        </template>
                        <template #fare-summary>
                            <div class="et-tot">
                                <span>Total amount</span>
                                <b>{{ formatCurrency(totalTicketPrice =
                                    parseFlightData(booking.flight_data)?.pricing?.totalPrice
                                    + parseFloat(agentData?.agent_data?.margin_amount) *
                                    parseFlightData(booking.flight_data)?.passengerInfo?.length,
                                    parseFlightData(booking.flight_data)?.pricing.currency
                                ) }}</b>
                            </div>
                        </template>

                        <template #baggage>
                            <div class="et-bag">
                                <div>
                                    <h4>Carry-on</h4>
                                    <div v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo"
                                        :key="`carry-${index}`" class="et-kg">
                                        {{ passenger.passengerType }} – 5-7 kg
                                    </div>
                                </div>
                                <div>
                                    <h4>Checked baggage</h4>
                                    <div v-for="(passenger, index) in parseFlightData(booking.flight_data).passengerInfo"
                                        :key="`checked-${index}`" class="et-kg">
                                        {{ passenger.passengerType }} –
                                        {{ passenger?.baggage?.[0]?.weight ? `${passenger.baggage[0].weight} ${passenger.baggage[0].unit || ''}` : '-' }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </ATETicket>
                </div>
            </div>

        </div>

    </section>
</template>

<style>
/* Print-specific styles: only the cloned ticket (#print-container) prints; ATETicket styles it. */

@media print {
    @page {
        /* No page margin: Chrome then has no room to print its date/title/URL header and footer.
           The ticket adds its own 10px edge spacing. */
        margin: 0;
        size: A4;
    }

    /* Remove the rest of the app from layout (not just hide it), so it cannot add a second page. */
    body:has(> #print-container) > *:not(#print-container) {
        display: none !important;
    }

    #print-container {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .print\:hidden {
        display: none !important;
    }
}
</style>
