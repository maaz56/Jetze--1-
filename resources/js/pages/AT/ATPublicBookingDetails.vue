<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, DollarSign, BellIcon } from "lucide-vue-next";
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
import { cn, formatAmount, formatAmountWithCurrency, calculateLayoverDetails, calculateFinalPrice, sumMoney } from "@/lib/utils";

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
    FETCH_CUSTOMER_BOOKING_DETAILS,
    FETCH_CUSTOMER_MARGIN,
    SAVE_CONVERSATION,
    SAVE_REQUEST,
    FETCH_MODIFY_REQUEST_DATA,
    SEND_REPLY,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { SEND_EMAIL } from "@/services/store/actions.type";
import { ChatBubbleIcon } from "@radix-icons/vue";
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";
import ATETicket from "@/components/AT/ATETicket.vue";


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
const error = ref(null);
const isLoading = computed(() => isBookingDetailsLoading.value);
const CustomerMargin = computed(
    () => store.getters["customerMargin/customerMargin"],
);

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/customerBooking"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const pnrDetails = computed(() => store.getters["flight/pnrData"]);

/**
 * Read the permanent display amount created from this booking's checkout quote.
 */
const lockedBookingMoney = computed(() => {
    const booking = bookingDetails.value?.[0];
    const snapshot = booking?.price_snapshot;
    const amount = snapshot?.selling_amount ?? booking?.selling_amount;
    const currency = snapshot?.selling_currency ?? booking?.selling_currency;

    if (amount === null || amount === undefined || !currency) {
        return null;
    }

    return {
        amount: Number(amount),
        currency: String(currency).toUpperCase(),
    };
});

/** Format the booking's locked checkout total in its saved currency. */
function formatLockedBookingAmount() {
    if (!lockedBookingMoney.value || Number.isNaN(lockedBookingMoney.value.amount)) {
        return 'Price unavailable';
    }

    return formatAmountWithCurrency(
        lockedBookingMoney.value.amount,
        lockedBookingMoney.value.currency,
    );
}

/** Each booked leg with its selected fare, from the saved flight data. */
const bookedFareLegs = computed(() => {
    const booking = bookingDetails.value?.[0];
    const fareReference = parseFlightData(booking?.fare_reference);
    const fareRefs = Array.isArray(fareReference) ? fareReference : [fareReference];

    return (parseFlightData(booking?.flight_data)?.leg?.flights ?? []).flatMap((leg) =>
        (leg?.fares ?? [])
            .filter((fare) => fareRefs.includes(fare.ref_id))
            .map((fare) => ({ leg, fare })),
    );
});

/** Route across all booked legs, e.g. "DXB → DEL → DXB". */
const bookedFareRoute = computed(() => {
    const legs = bookedFareLegs.value.map(({ leg }) => leg);
    if (!legs.length) return '';

    return [
        legs[0].segments?.[0]?.from?.iata,
        ...legs.map((leg) => leg.segments?.[leg.segments.length - 1]?.to?.iata),
    ].join(' → ');
});

/** Sum the outbound and return fares; AT combined return fares are usually 0. */
function formatBookedFaresMoney() {
    const money = sumMoney(bookedFareLegs.value.map(({ fare }) => fare?.display_money));

    return money
        ? formatAmountWithCurrency(money.amount, money.currency)
        : formatLockedBookingAmount();
}

/** Format a zero add-on amount in the same locked booking currency. */
function formatBookingAddOnsAmount(amount) {
    if (Number(amount || 0) === 0 && lockedBookingMoney.value) {
        return formatAmountWithCurrency(0, lockedBookingMoney.value.currency);
    }

    return formatAmount(amount || 0);
}

const booking_id = route.query.booking_id;
const pnr = route.query.pnr;
const sooperResponse = ref(null);
const bookingId = ref("");
const custEmail = ref(null);
const isDialogOpen = ref(false);
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const pnrData = ref(null);
const isChatOpen = ref(false);



const booking = ref(null);
const atPnrReferences = computed(() => {
    const flight = pnrData.value?.Trips
        ?.flatMap((trip) => trip?.Journey || [])
        ?.flatMap((journey) => journey?.Segments || [])
        ?.map((segment) => segment?.Flight)
        ?.find(Boolean);

    return {
        crs: flight?.CRSPNR || booking.value?.itinerary_ref || "-",
        airline: flight?.APNR || flight?.AirlinePNR || booking.value?.airline_pnr || "-",
    };
});
const flightData = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref(0);
const modifyDialogOpen = ref(false);
const selectedReason = ref("");
const message = ref("");
const replyMessage = ref("");
const replyLoading = ref(false);
const selectedFares = ref([]);
const modifyRequestData = computed(() => store.getters["modifyRequest/requestData"]);

const now = ref(Date.now())

onMounted(() => {
    setInterval(() => {
        now.value = Date.now()
    }, 1000)
})

const getRemainingTime = (expiry) => {
    if (!expiry) return 'N/A'

    // Parse expiry and get difference
    const expiryTime = new Date(expiry.replace(' ', 'T')).getTime()
    const diff = expiryTime - now.value
    if (diff <= 0) return 'Expired'

    // Calculate days, hours, minutes, seconds
    const days = Math.floor(diff / (1000 * 60 * 60 * 24))
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)

    let result = ''
    if (days > 0) result += `${days}d `
    if (hours > 0 || days > 0) result += `${hours}h `
    result += `${minutes}m ${seconds.toString().padStart(2, '0')}s`

    return result
}
function openModifyRequestDialog() {
    modifyDialogOpen.value = true;
}

function closeDialog() {
    modifyDialogOpen.value = false;
}
function handleLogin() {
    authStore.openDialog();
    // store.dispatch('auth/' + SHOW_LOGIN_DIALOG)
}
function fetchModifyRequestData() {
    const booking_id = route.query.booking_id;
    if (booking_id) {
        store.dispatch("modifyRequest/" + FETCH_MODIFY_REQUEST_DATA, {
            booking_id: booking_id,
        });
    }
}
function sendReply(newStatus = "pending") {
    if (!replyMessage.value.trim()) return;

    if (user_id.value == null) {
        handleLogin();
        return;
    }

    replyLoading.value = true;

    // Create new admin message object
    const adminMessage = {
        req_id: modifyRequestData.value.id,
        sender: "user",
        sender_id: user_id.value,   // logged-in admin ID
        message: replyMessage.value
    };

    // Push message to conversation array
    store.dispatch("modifyRequest/" + SEND_REPLY, adminMessage).then(() => {
        fetchModifyRequestData();
    });



    // Clear input and stop loading
    replyMessage.value = "";
    replyLoading.value = false;

}

const submitModifyRequest = () => {
    if (!user_id.value) {
        handleLogin();
        return;
    }
    if (!selectedReason.value) {
        alert('Please select a reason')
        return
    }
    if (!message.value.trim()) {
        alert('Please enter a message')
        return
    }
    store.dispatch('modifyRequest/' + SAVE_REQUEST, {
        booking_id: booking_id,
        reason: selectedReason.value,
        message: message.value,
        user_id: bookingDetails.value?.[0].agent_id ?? user_id.value,
    })
    store.dispatch

    // TODO: Call your API here
    // await api.modifyRequest({ reason: selectedReason.value, message: message.value })

    closeDialog()
}

function sendEmail() {

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
        await store.dispatch(`flight/${FETCH_CUSTOMER_BOOKING_DETAILS}`, { bookingId: booking_id, bookingSource: route.query.booking_source });
        parsePnrResponse();
        // parseSooperResponse();
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

const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
    const total = parseFloat(price) || 0;
    const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
    const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

    // If discount is provided, return negative discount value, else return margin value
    if (discountPercentage && parseFloat(discountPercentage) > 0) {
        return -discount;
    }
    return margin;
};
function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}
function handleConfirmDialogOpen() {
    if (agentLedger?.value.balance < totalTicketPrice?.value) {
        isLowBalanceDialogOpen.value = true;
        return;
    }

    isConfirmDialogOpen.value = true;
}

async function handleCancelBooking() {
    error.value = '';
    actionLoading.value = true;

    try {
        if (!pnr) {
            error.value = "No PNR provided.";
            return;
        }

        await store.dispatch("flight/" + CANCEL_BOOKING, {
            pnr: pnr,

            booking_uuid: pnrData.value?.data?.uuid ?? "null",
            billable_price: pnrData.value?.data?.billable_price ?? "null",
            currency: pnrData.value?.data?.currency?.code ?? "null",
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            booking_status: "canceled",
            booking_source: route.query.flight_provider,

        });

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
        await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { flight_provider: route.query.flight_provider, pnr: route.query.pnr });
    } catch (err) {
        error.value = "Failed to fetch PNR details.";
    } finally {
        isPnrDetailsLoading.value = false;
    }
}


function cancelBooking() {
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    if (user_id.value == null) {
        handleLogin();
        return;
    }

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
        fetchPnrDetails();
    }).catch((err) => {
        error.value = err.message || 'Failed to cancel booking';
    });
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
            booking_uuid: pnrData.value?.data?.uuid ?? "null",
            booking_status: "ticketed",
            booking_source: route.query.booking_source,
            amount: parseFloat(pnrData?.data?.billable_price || 0),
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
            flightData.value = parseFlightData(bookingDetails?.value?.[0]?.flight_data);
            selectedFares.value = bookingDetails?.value?.[0]?.fare_reference ? JSON.parse(bookingDetails.value[0].fare_reference) : [];
        } else {
            pnrData.value = null;
        }
    } catch (e) {
        console.error("Failed to parse pnr_response:", e);
        pnrData.value = null;
    }
}
function calculateTaxes(fare) {
    return (
        parseFloat(fare?.taxes || 0) +
        parseFloat(fare?.surchage || 0) +
        parseFloat(fare?.fees || 0) +
        parseFloat(fare?.service_charges || 0) +
        parseFloat(fare?.ancillaries_charges || 0)
    );
}
const passengerCount = parseInt(bookingDetails?.value?.[0].pessangers.length || 1);
const agentAmount = parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0);
const agentDiscount = parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0);
const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);
const airportMargin = parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0);
const savedMarginTotal =
    (agentAmount + margin + airportMargin - agentDiscount) || 0;
const marginPerFlight = computed(() => {
    const flightCount = flightData?.value?.leg?.flights?.length || 0;
    if (!flightCount) return 0;
    return (savedMarginTotal / flightCount) || 0;
});
function calculateTotalFare(fare) {

    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);

    const billable = parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));

    const total = billable + marginPerFlight.value;
    return total;
}
function calculateGrandTotal() {
    let total = 0;

    flightData.value?.leg?.flights?.forEach((flightItem, flightIndex) => {
        flightItem?.fares?.forEach(fare => {
            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare);
            }
        });
    });
    const addOnesAmount = parseFloat(bookingDetails?.value?.[0]?.add_ones_amount || 0) || 0;
    return total + addOnesAmount;
}
function parseSooperResponse() {
    try {
        const sooperResponseString = bookingDetails?.value?.[0]?.sooper_response;

        if (sooperResponseString) {
            sooperResponse.value = JSON.parse(sooperResponseString);
        } else {
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



const toggleChatOpen = () => {
    isChatOpen.value = !isChatOpen.value;
}
function goToPaymentView() {
    router.push({
        name: "CustomerPaymentView",
        query: {
            booking_id: route?.query?.booking_id,
            flight_mode: route.query.flight_mode,
            flight_id: route.query.flight_id,
            flight_provider: route.query.flight_provider,
            booking_source: route.query.booking_source,
            pnr: route.query.pnr,
        },
    });
}
function goToAncillariesView() {
    router.push({
        name: "AncillariesView",
        query: {
            booking_id: route?.query?.booking_id,
            flight_mode: route.query.flight_mode,
            flight_id: route.query.flight_id,
            flight_provider: route.query.flight_provider,
            booking_source: route.query.booking_source,
            pnr: route.query.pnr,
        },
    });
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






const getOtherSSR = () => {
    try {
        const pnr = pnrDetails.value;
        if (!pnr) return [];

        const specialReqs = pnr?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo?.SpecialReqDetails;
        if (!specialReqs || !Array.isArray(specialReqs)) return [];

        const ssrReq = specialReqs.find(req => req.SpecialServiceRequests?.SpecialServiceRequest);
        if (!ssrReq) return [];

        const allSSR = Array.isArray(ssrReq.SpecialServiceRequests.SpecialServiceRequest)
            ? ssrReq.SpecialServiceRequests.SpecialServiceRequest
            : [ssrReq.SpecialServiceRequests.SpecialServiceRequest];

        // Filter out baggage-related SSR
        return allSSR.filter(ssr =>
            !ssr['@attributes']?.SSRCode?.includes('XB') &&
            !ssr['@attributes']?.ItemCode?.includes('BAG')
        );
    } catch (e) {
        console.error('Error parsing other SSR:', e);
        return [];
    }
};

const getStatusClass = (status) => {
    const baseClass = 'px-2 py-1 text-xs font-medium rounded-full ';
    switch (status?.toLowerCase()) {
        case 'held':
            return baseClass + 'bg-yellow-100 text-yellow-800 print:bg-transparent print:text-gray-800';
        case 'confirmed':
        case 'ok':
            return baseClass + 'bg-green-100 text-green-800 print:bg-transparent print:text-gray-800';
        case 'cancelled':
            return baseClass + 'bg-red-100 text-red-800 print:bg-transparent print:text-gray-800';
        default:
            return baseClass + 'bg-gray-100 text-gray-800 print:bg-transparent print:text-gray-800';
    }
};




const hasSSR = () => {
    return getSeatRequests().length > 0 || getBaggageSSR().length > 0 || getOtherSSR().length > 0;
};

// Helper to get all SSR for debugging
const getAllSSR = () => {
    try {
        const pnr = pnrDetails.value;
        if (!pnr) return null;

        return pnr?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo?.SpecialReqDetails;
    } catch (e) {
        console.error('Error getting SSR:', e);
        return null;
    }
};
//-----------------
// Updated function to get passenger name from TravelerInfo
const getPassengerName = (travelerRefRPH) => {
    try {
        if (!travelerRefRPH || !pnrDetails.value) return 'Unknown';

        // Navigate to the TravelerInfo.AirTraveler array
        const travelerInfo = pnrDetails.value?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;

        if (!travelerInfo?.AirTraveler || !Array.isArray(travelerInfo.AirTraveler)) {
            return 'Unknown';
        }

        // Find the traveler with matching TravelerRefNumber.RPH
        const traveler = travelerInfo.AirTraveler.find(t =>
            t.TravelerRefNumber?.['@attributes']?.RPH === travelerRefRPH
        );

        if (traveler?.PersonName) {
            const title = traveler.PersonName.NameTitle || '';
            const givenName = traveler.PersonName.GivenName || '';
            const surname = traveler.PersonName.Surname || '';
            return `${title} ${givenName} ${surname}`.trim();
        }

        return `Passenger (${travelerRefRPH.substring(0, 8)}...)`;
    } catch (e) {
        console.error('Error getting passenger name:', e);
        return 'Unknown';
    }
};

// Alternative: Get passenger name with passenger type
const getPassengerNameWithType = (travelerRefRPH) => {
    try {
        if (!travelerRefRPH || !pnrDetails.value) return 'Unknown';

        const travelerInfo = pnrDetails.value?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;

        if (!travelerInfo?.AirTraveler || !Array.isArray(travelerInfo.AirTraveler)) {
            return 'Unknown';
        }

        const traveler = travelerInfo.AirTraveler.find(t =>
            t.TravelerRefNumber?.['@attributes']?.RPH === travelerRefRPH
        );

        if (traveler?.PersonName) {
            const title = traveler.PersonName.NameTitle || '';
            const givenName = traveler.PersonName.GivenName || '';
            const surname = traveler.PersonName.Surname || '';
            const passengerType = traveler['@attributes']?.PassengerTypeCode || '';

            let typeLabel = '';
            if (passengerType === 'ADT') typeLabel = '(Adult)';
            else if (passengerType === 'CHD') typeLabel = '(Child)';
            else if (passengerType === 'INF') typeLabel = '(Infant)';

            return `${title} ${givenName} ${surname} ${typeLabel}`.trim();
        }

        return `Passenger (${travelerRefRPH.substring(0, 8)}...)`;
    } catch (e) {
        console.error('Error getting passenger name:', e);
        return 'Unknown';
    }
};

// Get all travelers as a map for quick lookup
const getTravelerMap = () => {
    try {
        const travelerInfo = pnrDetails.value?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;

        if (!travelerInfo?.AirTraveler || !Array.isArray(travelerInfo.AirTraveler)) {
            return new Map();
        }

        const travelerMap = new Map();
        travelerInfo.AirTraveler.forEach(traveler => {
            const rph = traveler.TravelerRefNumber?.['@attributes']?.RPH;
            if (rph) {
                const title = traveler.PersonName?.NameTitle || '';
                const givenName = traveler.PersonName?.GivenName || '';
                const surname = traveler.PersonName?.Surname || '';
                travelerMap.set(rph, {
                    fullName: `${title} ${givenName} ${surname}`.trim(),
                    type: traveler['@attributes']?.PassengerTypeCode,
                    givenName: givenName,
                    surname: surname
                });
            }
        });

        return travelerMap;
    } catch (e) {
        console.error('Error creating traveler map:', e);
        return new Map();
    }
};

// Usage in your SSR display functions
const getSeatRequests = () => {
    try {
        const pnr = pnrDetails.value;
        if (!pnr) return [];

        const travelerInfo =
            pnr?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;

        let specialReqs = travelerInfo?.SpecialReqDetails;


        // ✅ Normalize SpecialReqDetails to array
        if (!specialReqs) specialReqs = [];
        if (!Array.isArray(specialReqs)) specialReqs = [specialReqs];

        // Find seat requests
        const seatReq = specialReqs.find(req => req?.SeatRequests?.SeatRequest);
        if (!seatReq) return [];

        let seats = seatReq.SeatRequests.SeatRequest;

        // ✅ Normalize SeatRequest to array
        if (!seats) seats = [];
        if (!Array.isArray(seats)) seats = [seats];


        const travelerMap = getTravelerMap();

        return seats.map(seat => {
            const travelerRef =
                seat?.['@attributes']?.TravelerRefNumberRPHList;

            return {
                ...seat,
                passengerName:
                    travelerMap.get(travelerRef)?.fullName ||
                    getPassengerName(travelerRef),
                passengerType:
                    travelerMap.get(travelerRef)?.type || null,
            };
        });

    } catch (e) {
        console.error('Error parsing seat requests:', e);
        return [];
    }
};

const getBaggageSSR = () => {
    try {
        const pnr = pnrDetails.value;
        if (!pnr) return [];

        const travelerInfo = pnr?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;
        const specialReqs = travelerInfo?.SpecialReqDetails;

        if (!specialReqs || !Array.isArray(specialReqs)) return [];

        const ssrReq = specialReqs.find(req => req.SpecialServiceRequests?.SpecialServiceRequest);
        if (!ssrReq) return [];

        const allSSR = Array.isArray(ssrReq.SpecialServiceRequests.SpecialServiceRequest)
            ? ssrReq.SpecialServiceRequests.SpecialServiceRequest
            : [ssrReq.SpecialServiceRequests.SpecialServiceRequest];

        // Filter for baggage-related SSR (XB30, etc.)
        const baggageSSR = allSSR.filter(ssr =>
            ssr['@attributes']?.SSRCode?.includes('XB') ||
            ssr['@attributes']?.ItemCode?.includes('BAG')
        );

        // Enrich with passenger names
        const travelerMap = getTravelerMap();
        return baggageSSR.map(ssr => {
            const travelerRef = ssr['@attributes']?.TravelerRefNumberRPHList;
            return {
                ...ssr,
                passengerName: travelerMap.get(travelerRef)?.fullName || getPassengerName(travelerRef),
                passengerType: travelerMap.get(travelerRef)?.type
            };
        });
    } catch (e) {
        console.error('Error parsing baggage SSR:', e);
        return [];
    }
};

// For flight-wise grouping with enriched data


// Debug function to see all travelers
const getAllTravelers = () => {
    try {
        const travelerInfo = pnrDetails.value?.Body?.ReadResponse?.ReadResult?.AirReservation?.TravelerInfo;
        return travelerInfo?.AirTraveler || [];
    } catch (e) {
        console.error('Error getting travelers:', e);
        return [];
    }
};

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
        // fetchAgent();
    } else {
        fetchAgent();
    }
    // fetchAgentLedger();
    fetchModifyRequestData();
    fetchBookingDetails();
    fetchCustomerMarginValues();
    fetchPnrDetails();
});
</script>


<template>
    <section>


        <div class="max-w-7xl mx-auto min-h-screen bg-gray-100 p-4">
            <!-- Action Buttons -->
            <div v-if="route?.query?.booking_source == 1">
                <div v-for="booking in bookingDetails" :key="booking?.id"
                    class="ticket-actions bg-white rounded-lg shadow-sm p-3 py-4 mb-4 flex flex-wrap gap-1 justify-end print:hidden">
                    <button @click="printBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                        <PrinterIcon class="h-4 w-4" />
                        Print
                    </button>
                    <button @click="downloadPDF"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2">
                        <Download class="h-4 w-4" />
                        Download PDF
                    </button>
                    <button :disabled="modifyRequestData" @click="openModifyRequestDialog"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                        Modify Request
                    </button>
                    <button
                        :disabled="(bookingDetails?.[0]?.status === 'ticketed') || (bookingDetails?.[0]?.status === 'canceled')"
                        @click="goToPaymentView"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <DollarSign class="h-4 w-4" />
                        Pay Now
                    </button>
                    <button
                        :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status.toLowerCase())"
                        @click="isDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancel Booking
                    </button>
                    <button @click="toggleChatOpen"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                        <ChatBubbleIcon class="h-4 w-4" />
                        Chat
                    </button>

                </div>
                <div v-if="isDialogOpen"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                    @click.self="isDialogOpen = false">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">
                                Cancel Booking
                            </h3>
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
                                Are you sure you want to cancel this
                                booking? This action cannot be undone.
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
                <ATFlowLoader v-if="isLoading" title="Loading booking details"
                    message="We are refreshing the AT booking, PNR, and customer fare details."
                    :steps="['Booking', 'PNR', 'Details']" />
                <!-- Main Content with Chat Sidebar -->
                <div class="flex gap-4">
                    <!-- Print Section - Main Content -->
                    <div :class="isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full'" id="print-section">
                        <ATETicket v-for="booking in bookingDetails" :key="booking.id" :booking="booking"
                            :agent="agentData?.agent_data" logo="/public/assets/logo.png"
                            :pnr="atPnrReferences.crs" :airline-pnr="atPnrReferences.airline"
                            :pnr-details="pnrDetails"
                            :expiry="booking.status === 'booked' ? getRemainingTime(booking.expiry_time) : ''"
                            :show-fare="isDetailsInfoVisible">
                            <template #extras>
                                <section v-if="hasSSR()" class="et-sec">
                                    <div class="et-sec-h">
                                        <BellIcon />
                                        Special services
                                    </div>
                                    <template v-if="getSeatRequests().length > 0">
                                        <div class="et-sub-h">Seat selections</div>
                                        <div class="et-scroll">
                                            <table class="et-table">
                                                <thead>
                                                    <tr>
                                                        <th>Passenger</th>
                                                        <th>Flight</th>
                                                        <th>Seat</th>
                                                        <th>Status</th>
                                                        <th>Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(seat, index) in getSeatRequests()" :key="'seat-' + index">
                                                        <td>{{ seat.passengerName }}</td>
                                                        <td>Flight {{ seat['@attributes']?.FlightRefNumberRPHList }}</td>
                                                        <td>{{ seat['@attributes']?.RowNumber }}{{ seat['@attributes']?.SeatNumber }}</td>
                                                        <td><span :class="getStatusClass(seat['@attributes']?.Status)">{{ seat['@attributes']?.Status }}</span></td>
                                                        <td>{{ formatAmount(seat?.TPA_Extensions?.SeatCost || 0) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                    <template v-if="getBaggageSSR().length > 0">
                                        <div class="et-sub-h">Extra baggage</div>
                                        <div class="et-scroll">
                                            <table class="et-table">
                                                <thead>
                                                    <tr>
                                                        <th>Passenger</th>
                                                        <th>Flight</th>
                                                        <th>Description</th>
                                                        <th>Status</th>
                                                        <th>Charge</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(ssr, index) in getBaggageSSR()" :key="'ssr-' + index">
                                                        <td>{{ ssr.passengerName }}</td>
                                                        <td>Flight {{ ssr['@attributes']?.FlightRefNumberRPHList }}</td>
                                                        <td>{{ ssr['@attributes']?.ItemTitle || "Extra Baggage" }}</td>
                                                        <td><span :class="getStatusClass(ssr['@attributes']?.Status)">{{ ssr['@attributes']?.Status }}</span></td>
                                                        <td>{{ formatAmount(ssr['@attributes']?.ChargeAmount) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </section>
                            </template>

                            <template #fare-rows>
                                <tr v-if="pnrDetails?.fares?.length">
                                    <td>{{ bookedFareRoute || '-' }}</td>
                                    <td>
                                        {{ formatAmount(
                                            calculateFinalPrice(
                                                pnrDetails?.fares?.[0]?.totals?.subtotal,
                                                fare?.margin_amount,
                                                fare?.margin_type,
                                                fare?.amount_type || 0
                                            )
                                            + marginPerFlight
                                        ) }}
                                    </td>
                                    <td>{{ formatAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}</td>
                                    <td>
                                        {{ formatAmount(
                                            parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0)
                                            + marginPerFlight
                                        ) }}
                                    </td>
                                </tr>
                                <tr v-else-if="bookedFareLegs.length">
                                    <td>{{ bookedFareRoute }}</td>
                                    <td>{{ formatBookedFaresMoney() }}</td>
                                    <td>{{ formatAmount(bookedFareLegs.reduce((sum, { fare }) => sum + calculateTaxes(fare), 0)) }}</td>
                                    <td>{{ formatBookedFaresMoney() }}</td>
                                </tr>
                            </template>
                            <template #fare-summary>
                                <div><span>Add-ons</span><b>{{ formatBookingAddOnsAmount(bookingDetails?.[0]?.add_ones_amount) }}</b></div>
                                <div class="et-tot"><span>Total amount</span><b>{{ formatLockedBookingAmount() }}</b></div>
                            </template>
                        </ATETicket>
                    </div>

                    <!-- Chat Panel - Right Side (UNCHANGED) -->
                    <div v-if="modifyRequestData && isChatOpen" class="w-4/12 print:hidden">
                        <div
                            class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col h-[calc(100vh-200px)] sticky top-4">
                            <!-- Header -->
                            <div class="p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg flex-shrink-0">
                                <h3 class="text-lg font-semibold text-gray-800">Modify Request</h3>
                            </div>

                            <!-- Status -->
                            <div class="p-4 border-b border-gray-200 flex-shrink-0">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-600">Status</span>
                                    <span :class="{
                                        'px-3 py-1 text-xs font-semibold rounded-full': true,
                                        'bg-yellow-100 text-yellow-800': modifyRequestData?.status === 'pending',
                                        'bg-green-100 text-green-800': modifyRequestData?.status === 'approved',
                                        'bg-red-100 text-red-800': modifyRequestData?.status === 'rejected',
                                        'bg-blue-100 text-blue-800': modifyRequestData?.status === 'processing'
                                    }">
                                        {{ modifyRequestData?.status || 'Pending' }}
                                    </span>
                                </div>
                                <div class="mt-2 text-sm">
                                    <span class="font-medium text-gray-600">Reason:</span>
                                    <p class="mt-1 text-gray-800">
                                        {{ modifyRequestData?.reason === 'change_scope' ? 'Change Scope' :
                                            modifyRequestData?.reason === 'extend_deadline' ? 'Extend Deadline' :
                                                modifyRequestData?.reason === 'refund' ? 'Request Refund' :
                                                    modifyRequestData?.reason === 'cancel' ? 'Cancel Booking' :
                                                        modifyRequestData?.reason || 'Not specified' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50/50">
                                <div v-if="!parseFlightData(modifyRequestData?.messages)?.length"
                                    class="text-center text-gray-500 text-sm py-8">
                                    No messages yet.
                                </div>
                                <div v-else v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)"
                                    :key="index" class="flex"
                                    :class="msg.sender === 'admin' ? 'justify-start' : 'justify-end'">
                                    <div class="max-w-[85%] px-4 py-3 text-sm rounded-lg shadow-sm" :class="msg.sender === 'user'
                                        ? 'bg-primary/10 text-gray-800'
                                        : 'bg-white border border-gray-200 text-gray-800'">
                                        <p class="whitespace-pre-wrap">{{ msg?.message }}</p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            {{ moment(msg?.created_at).format('DD MMM YYYY, HH:mm') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Reply Box -->
                            <div v-if="modifyRequestData?.status === 'pending'"
                                class="p-4 border-t border-gray-200 bg-white rounded-b-lg flex-shrink-0">
                                <form @submit.prevent="sendReply">
                                    <textarea v-model="replyMessage" rows="2" placeholder="Type your reply..."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
                                        required></textarea>
                                    <div class="mt-3 flex justify-end">
                                        <button type="submit" :disabled="replyLoading"
                                            class="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-md disabled:opacity-60 flex items-center gap-2">
                                            <span v-if="replyLoading"
                                                class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                                            Send
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Request Dialog (UNCHANGED) -->
            <div v-if="modifyDialogOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4 print:hidden"
                @click.self="closeDialog">
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                    <h2 class="mb-5 text-xl font-semibold text-gray-900">Modify Request</h2>

                    <form @submit.prevent="submitModifyRequest" class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                Reason for modification
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input type="radio" v-model="selectedReason" value="refund"
                                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300" />
                                    <span class="ml-3 text-sm text-gray-700">Refund</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" v-model="selectedReason" value="other"
                                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300" />
                                    <span class="ml-3 text-sm text-gray-700">Other</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea id="message" v-model="message" rows="5"
                                placeholder="Please explain the changes you need..."
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                                required></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="button" @click="closeDialog"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary">
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>

<style>
/* Mobile layout for the action bar; the ticket itself is styled by ATETicket. */
@media screen and (max-width: 639px) {
    .ticket-actions {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.3rem;
        padding: 0.6rem;
    }

    .ticket-actions button {
        width: 100%;
        min-width: 0;
        justify-content: center;
        gap: 0.2rem;
        padding: 0.45rem 0.2rem;
        font-size: 0.62rem;
        line-height: 1;
        white-space: nowrap;
    }

    .ticket-actions button svg {
        width: 0.75rem;
        height: 0.75rem;
        flex: 0 0 auto;
    }
}

/* Print styles - Only necessary overrides */
@media print {
    @page {
        /* No page margin: Chrome then has no room to print its date/title/URL header and footer.
           The ticket adds its own 10px edge spacing. */
        margin: 0;
        size: A4;
    }

    /* When the print clone exists, remove the whole app from layout
       so only the ticket prints, starting at the top of page 1 */
    body:has(> #print-container)>*:not(#print-container) {
        display: none !important;
    }

    #print-container {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-size: 9pt !important;
        line-height: 1.35 !important;
    }

    /* Compact print layout so the ticket fits on one page */
    #print-container .text-xs {
        font-size: 7pt !important;
    }

    #print-container .text-sm {
        font-size: 8pt !important;
    }

    #print-container .text-base {
        font-size: 9pt !important;
    }

    #print-container .text-lg {
        font-size: 10.5pt !important;
    }

    #print-container .text-xl {
        font-size: 11.5pt !important;
    }

    #print-container .text-2xl {
        font-size: 13pt !important;
    }

    #print-container .p-6 {
        padding: 10px !important;
    }

    #print-container .p-4 {
        padding: 8px !important;
    }

    #print-container .p-3 {
        padding: 6px !important;
    }

    #print-container .px-6 {
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    #print-container .py-4 {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
    }

    #print-container .py-3 {
        padding-top: 5px !important;
        padding-bottom: 5px !important;
    }

    #print-container .mb-4 {
        margin-bottom: 8px !important;
    }

    #print-container .mb-6 {
        margin-bottom: 10px !important;
    }

    #print-container .gap-4 {
        gap: 8px !important;
    }

    #print-container .space-y-4>*+* {
        margin-top: 6px !important;
    }

    #print-container .space-y-3>*+* {
        margin-top: 5px !important;
    }

    #print-container th,
    #print-container td {
        padding: 4px 8px !important;
    }

    #print-container img {
        max-height: 2.75rem !important;
    }

    /* Let large cards flow across pages (prevents blank gaps),
       but keep table rows intact */
    #print-container .print\:break-inside-avoid {
        page-break-inside: auto !important;
    }

    #print-container tr {
        page-break-inside: avoid !important;
    }

    /* Hide navigation chrome and interactive elements */
    nav,
    aside,
    footer,
    button,
    .fixed,
    .sticky,
    .print\:hidden {
        display: none !important;
    }

    /* Ensure proper page breaks */
    .print\:break-inside-avoid {
        page-break-inside: avoid !important;
    }

    /* Preserve background colors that should stay */
    .bg-amber-50 {
        background-color: #fffbeb !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .bg-gray-50 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Keep primary color for icons and accents */
    .text-primary {
        color: #2563eb !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Keep bullet points color */
    .bg-amber-500 {
        background-color: #f59e0b !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Table header styling - white background */
    thead tr {
        background-color: white !important;
    }

    th {
        background-color: white !important;
        font-weight: 600 !important;
        border-bottom: 1px solid #d1d5db !important;
    }

    /* Table borders - lighter */
    table,
    td,
    th {
        border-color: #e5e7eb !important;
    }

    /* Remove rounded corners from baggage tables */
    .border.border-gray-200.rounded-lg {
        border-radius: 0 !important;
    }

    /* Baggage section - keep at half width */
    .w-1\/2 {
        width: 50% !important;
    }

    /* Adjust font sizes for better print readability */
    .text-xs {
        font-size: 0.75rem !important;
    }

    .text-sm {
        font-size: 0.875rem !important;
    }

    .text-base {
        font-size: 1rem !important;
    }

    .text-lg {
        font-size: 1.125rem !important;
    }

    .text-xl {
        font-size: 1.25rem !important;
    }

    /* Reduce boldness */
    .font-bold {
        font-weight: 600 !important;
    }

    .font-semibold {
        font-weight: 500 !important;
    }

    .font-medium {
        font-weight: 400 !important;
    }

    /* Ensure logo prints at correct size */
    img {
        max-height: 4rem !important;
        width: auto !important;
    }
}
</style>
