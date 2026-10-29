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
                        <div v-for="booking in bookingDetails" :key="booking.id"
                            class="ticket-receipt bg-white rounded-lg shadow-sm overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                            <!-- Header - Professional Design -->
                            <div
                                class="ticket-receipt-header p-6 text-white print:text-black print:bg-white print:border-b print:border-gray-300">
                                <div class="ticket-receipt-header-grid flex justify-between items-center">
                                    <!-- Logo -->
                                    <div class="ticket-receipt-logo w-1/4">
                                        <img class="h-16 w-auto print:h-12" src="/public/assets/logo.png" alt="Logo" />
                                    </div>

                                    <!-- Agency Info -->
                                    <div
                                        class="ticket-receipt-title w-2/4 text-black text-center border-l-2 border-black/30 pl-4 print:border-gray-400">
                                        <h1 class="text-2xl font-extrabold tracking-tight print:text-gray-900 mb-1">
                                            E-TICKET RECEIPT</h1>

                                        <div class="mt-2">
                                            <p class="text-sm print:text-gray-700 mb-1">
                                                <span class="font-medium">Status:</span>
                                                <span :class="{
                                                    'text-green-600 font-bold': booking.status === 'booked',
                                                    'text-yellow-600 font-bold': booking.status === 'pending',
                                                    'text-red-600 font-bold': booking.status === 'cancelled' || booking.status === 'failed',
                                                    'text-blue-600 font-bold': booking.status === 'confirmed',
                                                    'capitalize font-semibold print:text-gray-900': true
                                                }">{{ booking.status }}</span>
                                            </p>

                                            <div v-if="booking.status === 'booked'"
                                                class="text-sm opacity-90 print:text-gray-700 mt-1">
                                                <span class="font-medium">Expiry Time: </span>
                                                <span class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
           bg-amber-100 text-amber-700 border border-amber-300
           ">
                                                    {{ getRemainingTime(booking.expiry_time) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Booking Reference -->
                                    <div
                                        class="ticket-receipt-reference w-1/4 text-black text-right border-l-2 border-black/30 pl-4 print:border-gray-400">
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Booking
                                                Ref:</span> {{ booking.id
                                                }}</p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Status:</span>
                                            <span class="capitalize font-semibold print:text-gray-900">{{ booking.status
                                            }}</span>
                                        </p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">PNR:</span> {{
                                            booking?.itinerary_ref || "-"
                                        }}</p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Airline
                                                PNR:</span> {{ pnrDetails?.bookingId ? pnrDetails?.bookingId :
                                                    booking?.itinerary_ref || "-"
                                                }}</p>
                                        <!-- <p class="text-sm print:text-gray-700"><span class="font-medium">Airline PNR:</span> {{
                                                pnrData?.ReservationResponse?.Reservation?.Receipt?.[1]?.Confirmation?.Locator?.value
                                                }}</p> -->
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Information -->
                            <div class="ticket-flight-information p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center print:text-gray-900">
                                    <PlaneIcon class="h-5 w-5 mr-2 text-primary print:text-gray-700" />
                                    FLIGHT INFORMATION
                                </h2>

                                <div v-if="parseFlightData(booking.flight_data)?.original?.leg || parseFlightData(booking.flight_data)?.leg"
                                    class="space-y-4">
                                    <div v-for="(flight, flightIndex) in (parseFlightData(booking.flight_data)?.original?.leg?.flights ?? parseFlightData(booking.flight_data)?.leg?.flights)"
                                        :key="flightIndex" class="space-y-3 print:break-inside-avoid">
                                        <!-- Main Flight Card -->
                                        <div
                                            class="ticket-flight-card hidden sm:block border border-gray-200 rounded-lg p-6 hover:shadow-sm transition-shadow print:block print:border-gray-400 print:shadow-none print:mb-6 print:p-8 print:text-base">

                                            <!-- Segments Container -->
                                            <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex"
                                                class="ticket-segment bg-white overflow-hidden">

                                                <!-- Layover Info -->
                                                <div v-if="segment?.layover_time" class="flex justify-center ">
                                                    <div
                                                        class="bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium px-4 py-1.5 rounded-full shadow-sm">
                                                        Layover Time:
                                                        {{
                                                            Math.floor(segment.layover_time / 60) + "h " +
                                                            (segment.layover_time % 60) + "m"
                                                        }}
                                                    </div>
                                                </div>

                                                <!-- Segment Details -->
                                                <div class="ticket-segment-details p-2 sm:p-3 lg:p-6">
                                                    <div class="flex items-center justify-center gap-6">
                                                        <div class="ticket-segment-grid grid grid-cols-7 gap-4 items-center w-full">

                                                            <!-- Airline Logo -->
                                                            <div class="ticket-airline w-20 flex-shrink-0 print:w-24">
                                                                <img :src="segment?.operating_carrier?.logo"
                                                                    class="h-12 w-auto object-contain print:h-14"
                                                                    :alt="segment?.operating_carrier?.name" />
                                                                <div class="text-xs text-gray-500 mt-1">
                                                                    {{ segment?.operating_carrier?.iata }}-{{
                                                                        segment?.flight_number ?? "N/A" }}
                                                                </div>
                                                            </div>

                                                            <!-- FROM (Right aligned content) -->
                                                            <div class="ticket-origin col-span-2 text-right print:text-right">
                                                                <p
                                                                    class="text-2xl font-bold text-gray-900 print:text-3xl">
                                                                    {{ segment?.from?.iata }}
                                                                </p>
                                                                <p class="text-lg text-gray-600 print:text-base">
                                                                    {{ segment?.from?.city?.name }}
                                                                </p>
                                                                <p
                                                                    class="text-lg font-semibold text-gray-600 print:text-base">
                                                                    {{ segment?.from?.name }}
                                                                </p>

                                                                <p
                                                                    class="text-base font-medium mt-3 text-gray-700 print:text-lg">
                                                                    {{ formatDate(segment?.departure_at) }}
                                                                </p>
                                                                <p
                                                                    class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                                                    {{
                                                                        moment.parseZone(segment?.departure_at).format("HH:mm")
                                                                    }}
                                                                </p>

                                                                <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                    Terminal: {{ segment?.from_terminal?.Gate ?? "N/A"
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <!-- Arrow with Duration -->
                                                            <div class="ticket-flight-path col-span-1 flex flex-col items-center">
                                                                <div
                                                                    class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center print:bg-transparent print:border print:border-gray-400">
                                                                    <MoveRight
                                                                        class="h-5 w-5 text-gray-600 print:text-black" />
                                                                </div>

                                                                <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                    {{ (segment.flight_time) }}
                                                                </p>
                                                            </div>

                                                            <!-- TO (Left aligned content) -->
                                                            <div class="ticket-destination col-span-2 text-left print:text-left">
                                                                <p
                                                                    class="text-2xl font-bold text-gray-900 print:text-3xl">
                                                                    {{ segment?.to?.iata }}
                                                                </p>

                                                                <p class="text-lg text-gray-600 print:text-base">
                                                                    {{ segment?.to?.city?.name }}
                                                                </p>
                                                                <p
                                                                    class="text-lg font-semibold text-gray-600 print:text-base">
                                                                    {{ segment?.to?.name }}
                                                                </p>

                                                                <p
                                                                    class="text-base font-medium mt-3 text-gray-700 print:text-lg">
                                                                    {{ formatDate(segment?.arrival_at) }}
                                                                </p>

                                                                <p
                                                                    class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                                                    {{
                                                                        moment.parseZone(segment?.arrival_at).format("HH:mm")
                                                                    }}
                                                                </p>

                                                                <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                    Terminal: {{ segment?.to_terminal?.Gate ?? "N/A" }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Aircraft Info (only show for first segment or per segment basis) -->
                                                    <div
                                                        class="ticket-segment-meta mt-5 pt-4 border-t border-gray-200 text-sm text-gray-600 flex items-center gap-6 print:text-base print:border-gray-400">
                                                        <span>
                                                            <span class="font-semibold">Aircraft:</span>
                                                            {{ segment?.aircraft?.model || "N/A" }}
                                                        </span>
                                                        <span>
                                                            <span class="font-semibold">Class:</span>
                                                            Economy
                                                        </span>
                                                        <span>
                                                            <span class="font-semibold">Flight:</span>
                                                            {{ segment?.flight_number }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Compact mobile ticket: mirrors the desktop ticket details without its seven-column squeeze. -->
                                        <div class="ticket-mobile-flight-card sm:hidden">
                                            <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex"
                                                class="ticket-mobile-segment">
                                                <div v-if="segment?.layover_time" class="ticket-mobile-layover">
                                                    Layover: {{ Math.floor(segment.layover_time / 60) + "h " + (segment.layover_time % 60) + "m" }}
                                                </div>

                                                <div class="ticket-mobile-airline">
                                                    <img :src="segment?.operating_carrier?.logo"
                                                        :alt="segment?.operating_carrier?.name" />
                                                    <div>
                                                        <p>{{ segment?.operating_carrier?.name || 'Airline' }}</p>
                                                        <span>{{ segment?.operating_carrier?.iata }}-{{ segment?.flight_number ?? 'N/A' }}</span>
                                                    </div>
                                                    <span class="ticket-mobile-class">Economy</span>
                                                </div>

                                                <div class="ticket-mobile-route">
                                                    <div class="ticket-mobile-place ticket-mobile-place--from">
                                                        <strong>{{ segment?.from?.iata }}</strong>
                                                        <span>{{ segment?.from?.city?.name }}</span>
                                                        <small>{{ formatDate(segment?.departure_at) }}</small>
                                                        <b>{{ moment.parseZone(segment?.departure_at).format('HH:mm') }}</b>
                                                    </div>

                                                    <div class="ticket-mobile-path">
                                                        <MoveRight />
                                                        <span>{{ segment?.flight_time || 'Direct' }}</span>
                                                    </div>

                                                    <div class="ticket-mobile-place ticket-mobile-place--to">
                                                        <strong>{{ segment?.to?.iata }}</strong>
                                                        <span>{{ segment?.to?.city?.name }}</span>
                                                        <small>{{ formatDate(segment?.arrival_at) }}</small>
                                                        <b>{{ moment.parseZone(segment?.arrival_at).format('HH:mm') }}</b>
                                                    </div>
                                                </div>

                                                <div class="ticket-mobile-meta">
                                                    <span>Terminal: {{ segment?.from_terminal?.Gate ?? 'N/A' }}</span>
                                                    <span>Flight {{ segment?.flight_number ?? 'N/A' }}</span>
                                                    <span>Terminal: {{ segment?.to_terminal?.Gate ?? 'N/A' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Baggage Section - Half Width Rounded Table (unchanged) -->
                                        <div
                                            class="ticket-baggage-section border border-gray-200 rounded-lg p-4 print:border-gray-300 print:break-inside-avoid">
                                            <div class="space-y-6">
                                                <div
                                                    class="ticket-baggage-list flex justify-start items-center divide-x divide-gray-300 print:divide-gray-400">
                                                    <div v-for="(flightSegment, sIndex) in flight?.segments"
                                                        :key="sIndex" class="ticket-baggage-item mb-4 p-4">
                                                        <div
                                                            class="text-xs font-medium text-gray-700 mb-2 print:text-gray-800">
                                                            {{ flightSegment.from.iata }} → {{ flightSegment.to.iata }}
                                                        </div>
                                                        <div class="print:w-full">
                                                            <table
                                                                class="ticket-baggage-table w-full text-xs border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
                                                                <thead>
                                                                    <tr
                                                                        class="border-b border-gray-300 print:border-gray-400 print:bg-white">
                                                                        <th
                                                                            class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-white">
                                                                            Pax Type
                                                                        </th>
                                                                        <th
                                                                            class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                                            Check-In Baggage
                                                                        </th>
                                                                        <th
                                                                            class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                                            Cabin Baggage
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <pre class="hidden">{{ flight.fares }}</pre>
                                                                <tbody
                                                                    class="divide-y divide-gray-200 print:divide-gray-400">
                                                                    <template v-for="(fare, fIndex) in flight?.fares"
                                                                        :key="fIndex">
                                                                        <template
                                                                            v-if="parseFlightData(bookingDetails?.[0]?.fare_reference)?.includes(fare?.ref_id)">
                                                                            <tr v-for="(travelerType, tIndex) in [...new Set(fare.baggage_policies.map(bp => bp.traveler_type))]"
                                                                                :key="tIndex"
                                                                                class="hover:bg-gray-50 print:bg-transparent">
                                                                                <td
                                                                                    class="py-2 px-3 uppercase font-medium print:text-gray-800">
                                                                                    {{ travelerType }}
                                                                                </td>
                                                                                <td
                                                                                    class="py-2 px-3 uppercase print:text-gray-800">
                                                                                    {{fare.baggage_policies.find(bp =>
                                                                                        bp.traveler_type === travelerType &&
                                                                                        bp.type === 'checked')?.description
                                                                                        || 'N/A'}}
                                                                                </td>
                                                                                <td
                                                                                    class="py-2 px-3 uppercase print:text-gray-800">
                                                                                    {{(fare.baggage_policies ||
                                                                                        []).find(bp => bp.traveler_type ===
                                                                                            travelerType && (bp.type === 'carry'
                                                                                                || bp.type ===
                                                                                                'carry-on'))?.description || 'N/A'
                                                                                    }}
                                                                                </td>
                                                                            </tr>
                                                                        </template>
                                                                    </template>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Passenger & Ticket Details -->
                            <div class="ticket-passenger-section p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center print:text-gray-900">
                                    <UserIcon class="h-5 w-5 mr-2 text-primary print:text-gray-700" />
                                    PASSENGER & TICKET DETAILS
                                </h2>

                                <div class="ticket-passenger-table-wrap overflow-x-auto">
                                    <table
                                        class="ticket-passenger-table w-full text-sm border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
                                        <thead>
                                            <tr
                                                class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                                <th
                                                    class="px-4 py-2.5 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                    Traveller Name
                                                </th>
                                                <th
                                                    class="px-4 py-2.5 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                    Gender
                                                </th>
                                                <th
                                                    class="px-4 py-2.5 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                    Nationality/CNIC
                                                </th>
                                                <th
                                                    class="px-4 py-2.5 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                    Ticket No
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 print:divide-gray-400">
                                            <tr v-for="(traveller, index) in booking?.pessangers" :key="index"
                                                class="even:bg-gray-50 hover:bg-gray-100/50 transition-colors print:bg-transparent">
                                                <td class="px-4 py-2.5 uppercase print:text-gray-800">
                                                    {{ traveller.title }} {{ traveller.first_name }} {{
                                                        traveller.last_name }}
                                                    <span class="text-gray-500 text-xs ml-1 print:text-gray-600">({{
                                                        traveller.type
                                                    }})</span>
                                                </td>
                                                <td class="px-4 py-2.5 uppercase print:text-gray-800">{{
                                                    traveller?.gender?.toUpperCase() ||
                                                    'N/A' }}</td>
                                                <td class="px-4 py-2.5 print:text-gray-800">{{ traveller.nationality
                                                    || 'N/A' }}</td>
                                                <td class="px-4 py-2.5 font-mono print:text-gray-800">
                                                    {{ Array.isArray(pnrDetails?.flightTickets) &&
                                                        pnrDetails.flightTickets[index] ?
                                                        pnrDetails.flightTickets[index].number : "N/A" }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- Special Service Requests (SSR) Section -->
                            <div class="p-2 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid"
                                v-if="hasSSR()">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center print:text-gray-900">
                                    <span class="h-5 w-5 mr-2 text-primary print:text-gray-700">
                                        <BellIcon />
                                    </span>
                                    SPECIAL SERVICES
                                </h2>

                                <div class="space-y-4">
                                    <!-- Seat Requests -->
                                    <div v-if="getSeatRequests().length > 0"
                                        class=" border-gray-200 rounded p-4 print:border-gray-300">
                                        <h3 class="text-md font-semibold text-gray-700 mb-3 print:text-gray-800">Seat
                                            Selections</h3>
                                        <div class="overflow-x-auto">
                                            <table
                                                class="w-full text-sm border border-gray-300 rounded-lg overflow-hidden print:border-gray-400">
                                                <thead>
                                                    <tr class="border-b border-gray-300 print:border-gray-400">
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Passenger</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Flight</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Seat</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Status</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Cost</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 print:divide-gray-400">
                                                    <tr v-for="(seat, index) in getSeatRequests()"
                                                        :key="'seat-' + index"
                                                        class="even:bg-gray-50 print:bg-transparent">
                                                        <td class="px-4 py-2 print:text-gray-800">{{ seat.passengerName
                                                        }}</td>
                                                        <td class="px-4 py-2 print:text-gray-800">Flight {{
                                                            seat['@attributes']?.FlightRefNumberRPHList }}</td>
                                                        <td class="px-4 py-2 font-mono print:text-gray-800">{{
                                                            seat['@attributes']?.RowNumber }}{{
                                                                seat['@attributes']?.SeatNumber }}</td>
                                                        <td class="px-4 py-2">
                                                            <span :class="getStatusClass(seat['@attributes']?.Status)">
                                                                {{ seat['@attributes']?.Status }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-2 print:text-gray-800">{{
                                                            formatAmount(seat?.TPA_Extensions?.SeatCost ||
                                                                0) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Extra Baggage SSR -->
                                    <div v-if="getBaggageSSR().length > 0"
                                        class=" border-gray-200 rounded-lg p-4 print:border-gray-300">
                                        <h3 class="text-md font-semibold text-gray-700 mb-3 print:text-gray-800">Extra
                                            Baggage</h3>
                                        <div class="overflow-x-auto">
                                            <table
                                                class="w-full text-sm border border-gray-300 rounded-lg overflow-hidden print:border-gray-400">
                                                <thead>
                                                    <tr class="border-b border-gray-300 print:border-gray-400">
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Passenger</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Flight</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Description</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Status</th>
                                                        <th
                                                            class="px-4 py-2 text-left font-bold text-gray-800 print:text-gray-900">
                                                            Charge</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 print:divide-gray-400">
                                                    <tr v-for="(ssr, index) in getBaggageSSR()" :key="'ssr-' + index"
                                                        class="even:bg-gray-50 print:bg-transparent">
                                                        <td class="px-4 py-2 print:text-gray-800">{{ ssr.passengerName
                                                        }}</td>
                                                        <td class="px-4 py-2 print:text-gray-800">Flight {{
                                                            ssr['@attributes']?.FlightRefNumberRPHList }}</td>
                                                        <td class="px-4 py-2 print:text-gray-800">{{
                                                            ssr['@attributes']?.ItemTitle || "Extra Baggage" }}</td>
                                                        <td class="px-4 py-2">
                                                            <span :class="getStatusClass(ssr['@attributes']?.Status)">
                                                                {{ ssr['@attributes']?.Status }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-2 print:text-gray-800">{{
                                                            formatAmount(ssr['@attributes']?.ChargeAmount)
                                                        }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Special Service Requests - Flight Wise -->

                            <!-- Fare Breakdown - Using existing fare logic -->
                            <div class="ticket-fare-section p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid"
                                v-if="isDetailsInfoVisible">
                                <h2 class="text-lg font-bold text-gray-800 mb-2 print:text-gray-900">
                                    FARE BREAKDOWN
                                </h2>
                                <div>
                                    <div class="ticket-fare-table-wrap overflow-x-auto">
                                        <table
                                            class="ticket-fare-table w-full text-xs border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
                                            <thead>
                                                <tr
                                                    class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                                    <th
                                                        class="py-1.5 px-2 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                        Sector</th>
                                                    <th
                                                        class="py-1.5 px-2 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                        Subtotal</th>
                                                    <th
                                                        class="py-1.5 px-2 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                        Taxes + Fees
                                                    </th>
                                                    <th
                                                        class="py-1.5 px-2 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                                        Grand Total</th>
                                                </tr>
                                            </thead>

                                            <tbody v-if="pnrDetails?.fares?.length"
                                                class="divide-y divide-gray-200 print:divide-gray-400">
                                                <tr class="hover:bg-gray-50 print:bg-transparent">
                                                    <td class="py-1.5 px-2 uppercase print:text-gray-800">
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
                                                    <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                        {{ formatAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}
                                                    </td>
                                                    <td class="py-1.5 px-2 uppercase font-bold print:text-gray-900">
                                                        {{ formatAmount(
                                                            parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0)
                                                            + marginPerFlight
                                                        ) }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tbody v-else class="divide-y divide-gray-200 print:divide-gray-400">
                                                <tr v-if="bookedFareLegs.length" class="hover:bg-gray-50 print:bg-transparent">
                                                    <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                        {{ bookedFareRoute }}
                                                    </td>
                                                    <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                        {{ formatBookedFaresMoney() }}
                                                    </td>
                                                    <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                        {{ formatAmount(bookedFareLegs.reduce((sum, { fare }) => sum + calculateTaxes(fare), 0)) }}
                                                    </td>
                                                    <td class="py-1.5 px-2 uppercase font-bold print:text-gray-900">
                                                        {{ formatBookedFaresMoney() }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                            <tfoot class=" border-gray-400 bg-gray-50 print:bg-transparent">
                                                <tr>
                                                    <td colspan="3"
                                                        class="py-3 px-2 text-right font-bold text-gray-900  text-sm">
                                                        Add-ons
                                                    </td>
                                                    <td class="py-3 px-2 font-bold text-primary text-sm">
                                                        {{
                                                            formatBookingAddOnsAmount(bookingDetails?.[0]?.add_ones_amount)
                                                        }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3"
                                                        class="py-3 px-2 text-right font-bold text-gray-900  text-sm">
                                                        Total Amount
                                                    </td>
                                                    <td class="py-3 px-2 font-bold text-primary text-sm">
                                                        {{ formatLockedBookingAmount() }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Important Information - UPDATED CONTENT -->
                            <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 print:text-gray-900">Important
                                    Information</h2>

                                <div
                                    class="bg-amber-50 border border-amber-200 rounded-lg p-5 print:bg-transparent print:border-gray-300">
                                    <ul class="space-y-3 text-sm text-gray-700 print:text-gray-800">
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span>Make sure you have valid travel documents before your trip (e.g.
                                                passport, visa, etc.). For any
                                                future reference please refer the above Trip ID.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span>We recommend you check-in at least 3 hours prior to departure of your
                                                domestic flight and 4 hours
                                                prior to your international flight.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span>Baggage more than specified units is subject to a charge to be paid at
                                                the airport during
                                                Check-in.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span>We are not responsible for any delay in cancellation of flight from
                                                the airline's end.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span>Please refer the Airline PNR Number when communicating with the
                                                airline regarding this
                                                booking.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0 print:bg-gray-600"></span>
                                            <span class="font-medium">Disclaimer:</span> Post-ticketing modifications or
                                            cancellations will be
                                            processed in accordance with the airline's policy.
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div
                                class="p-6 text-center bg-gray-50 border-t border-gray-200 print:bg-transparent print:border-gray-300">
                                <p class="text-sm text-gray-700 mb-1 font-medium print:text-gray-800">
                                    Thank you for choosing {{ agentData?.agent_data?.company_name || 'Jetze Travels' }}
                                </p>
                                <p class="text-xs text-gray-500 print:text-gray-600">
                                    For assistance, contact us at {{ agentData?.agent_data?.mobile || '+92 0000000000'
                                    }} or {{
                                        agentData?.agent_data?.company_email || 'support@Jetze.pk' }}
                                </p>
                            </div>
                        </div>
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
/* The desktop ticket is intentionally kept as-is. These rules make the same
   receipt hierarchy readable on narrow screens without changing print output. */
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

    .ticket-receipt-header {
        padding: 1rem 0.85rem;
    }

    .ticket-receipt-header-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        align-items: center;
        gap: 0;
    }

    .ticket-receipt-logo {
        display: flex;
        width: auto;
        align-items: center;
        justify-content: center;
        padding-top: 0;
    }

    .ticket-receipt-logo img {
        height: 1.75rem;
    }

    .ticket-receipt-title {
        display: flex;
        width: auto;
        height: 3.15rem;
        flex-direction: column;
        justify-content: center;
        padding: 0 0.45rem;
        text-align: center;
    }

    .ticket-receipt-title h1 {
        font-size: 0.9rem;
        line-height: 1.25;
        letter-spacing: -0.025em;
        margin-bottom: 0.2rem;
    }

    .ticket-receipt-title .text-sm,
    .ticket-receipt-reference .text-sm {
        font-size: 0.65rem;
        line-height: 1.35;
    }

    .ticket-receipt-title .mt-2 {
        margin-top: 0.3rem;
    }

    .ticket-receipt-title .mt-2 > p {
        display: none;
    }

    .ticket-receipt-title .px-3 {
        padding-left: 0.4rem;
        padding-right: 0.4rem;
    }

    .ticket-receipt-reference {
        display: flex;
        width: auto;
        height: 3.15rem;
        flex-direction: column;
        justify-content: center;
        padding: 0 0.45rem;
        text-align: center;
        overflow-wrap: anywhere;
    }

    .ticket-flight-information {
        padding: 1rem 0.85rem;
    }

    .ticket-flight-information > h2 {
        margin-bottom: 0.85rem;
        font-size: 0.95rem;
    }

    .ticket-flight-card {
        padding: 0.75rem;
        border-radius: 0.6rem;
    }

    .ticket-segment-details {
        padding: 0.5rem 0 0.2rem;
    }

    .ticket-segment-details > div {
        gap: 0;
    }

    .ticket-segment-grid {
        grid-template-columns: 0.7fr repeat(2, minmax(0, 1fr)) 0.45fr repeat(2, minmax(0, 1fr)) 0.1fr;
        gap: 0.25rem;
    }

    .ticket-airline {
        width: 2.25rem;
        align-self: start;
        padding-top: 0.2rem;
    }

    .ticket-airline img {
        height: 1.85rem;
    }

    .ticket-airline .text-xs {
        margin-top: 0.2rem;
        font-size: 0.52rem;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .ticket-origin .text-2xl,
    .ticket-destination .text-2xl {
        font-size: 1.05rem;
        line-height: 1.2;
    }

    .ticket-origin .text-lg,
    .ticket-destination .text-lg {
        font-size: 0.7rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ticket-origin .text-base,
    .ticket-destination .text-base {
        margin-top: 0.55rem;
        font-size: 0.62rem;
        line-height: 1.3;
    }

    .ticket-origin .text-xl,
    .ticket-destination .text-xl {
        font-size: 0.9rem;
        line-height: 1.25;
    }

    .ticket-origin .text-sm,
    .ticket-destination .text-sm {
        margin-top: 0.35rem;
        font-size: 0.58rem;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ticket-flight-path > div {
        width: 1.65rem;
        height: 1.65rem;
    }

    .ticket-flight-path svg {
        width: 0.85rem;
        height: 0.85rem;
    }

    .ticket-flight-path .text-sm {
        margin-top: 0.35rem;
        font-size: 0.54rem;
        line-height: 1.2;
        text-align: center;
        overflow-wrap: anywhere;
    }

    .ticket-segment-meta {
        margin-top: 0.85rem;
        padding-top: 0.65rem;
        gap: 0.35rem 0.65rem;
        justify-content: space-between;
        flex-wrap: wrap;
        font-size: 0.62rem;
        line-height: 1.3;
    }

    .ticket-segment-meta span {
        white-space: nowrap;
    }

    .ticket-mobile-flight-card {
        overflow: hidden;
        border: 1px solid #dbe5f1;
        border-radius: 0.7rem;
        background: linear-gradient(145deg, #ffffff 0%, #f7fbff 100%);
    }

    .ticket-mobile-segment {
        padding: 0.8rem;
    }

    .ticket-mobile-segment + .ticket-mobile-segment {
        border-top: 1px dashed #cbd5e1;
    }

    .ticket-mobile-layover {
        width: fit-content;
        margin: -0.05rem auto 0.7rem;
        padding: 0.22rem 0.55rem;
        border-radius: 9999px;
        background: #eaf4ff;
        color: #1d5c9e;
        font-size: 0.64rem;
        font-weight: 700;
    }

    .ticket-mobile-airline {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        padding-bottom: 0.65rem;
        border-bottom: 1px solid #e6edf5;
        color: #34445b;
    }

    .ticket-mobile-airline img {
        width: 1.85rem;
        height: 1.85rem;
        object-fit: contain;
    }

    .ticket-mobile-airline p {
        margin: 0;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .ticket-mobile-airline span {
        display: block;
        margin-top: 0.1rem;
        color: #718198;
        font-size: 0.62rem;
        line-height: 1.2;
    }

    .ticket-mobile-airline .ticket-mobile-class {
        margin: 0 0 0 auto;
        padding: 0.22rem 0.45rem;
        border-radius: 9999px;
        background: #eef5ff;
        color: #256fc0;
        font-size: 0.57rem;
        font-weight: 700;
    }

    .ticket-mobile-route {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 2.15rem minmax(0, 1fr);
        align-items: center;
        gap: 0.35rem;
        padding: 0.8rem 0 0.65rem;
    }

    .ticket-mobile-place {
        min-width: 0;
    }

    .ticket-mobile-place--from {
        text-align: left;
    }

    .ticket-mobile-place--to {
        text-align: right;
    }

    .ticket-mobile-place strong,
    .ticket-mobile-place span,
    .ticket-mobile-place small,
    .ticket-mobile-place b {
        display: block;
    }

    .ticket-mobile-place strong {
        color: #172033;
        font-size: 1.05rem;
        line-height: 1.1;
    }

    .ticket-mobile-place span {
        margin-top: 0.15rem;
        color: #526277;
        font-size: 0.68rem;
        line-height: 1.25;
        display: -webkit-box;
        min-height: 1.7rem;
        overflow: hidden;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .ticket-mobile-place small {
        margin-top: 0.55rem;
        color: #718198;
        font-size: 0.6rem;
        line-height: 1.2;
    }

    .ticket-mobile-place b {
        margin-top: 0.12rem;
        color: #277bd2;
        font-size: 0.9rem;
        line-height: 1.15;
    }

    .ticket-mobile-path {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.32rem;
        color: #718198;
    }

    .ticket-mobile-path svg {
        width: 1.45rem;
        height: 1.45rem;
        padding: 0.32rem;
        border-radius: 9999px;
        background: #eef5ff;
        color: #277bd2;
    }

    .ticket-mobile-path span {
        max-width: 2.5rem;
        font-size: 0.56rem;
        font-weight: 600;
        line-height: 1.2;
        text-align: center;
        overflow-wrap: anywhere;
    }

    .ticket-mobile-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.25rem;
        padding-top: 0.6rem;
        border-top: 1px solid #e6edf5;
        color: #718198;
        font-size: 0.56rem;
        line-height: 1.25;
        text-align: center;
    }

    .ticket-mobile-meta span:nth-child(1) {
        text-align: left;
    }

    .ticket-mobile-meta span:nth-child(3) {
        text-align: right;
    }

    .ticket-baggage-section {
        padding: 0.65rem;
        border-radius: 0.7rem;
    }

    .ticket-baggage-section > .space-y-6 > * + * {
        margin-top: 0;
    }

    .ticket-baggage-list {
        display: block;
    }

    .ticket-baggage-item {
        margin: 0;
        padding: 0.75rem 0;
    }

    .ticket-baggage-item + .ticket-baggage-item {
        border-top: 1px dashed #cbd5e1;
    }

    .ticket-baggage-list > .ticket-baggage-item {
        border-left-width: 0 !important;
    }

    .ticket-baggage-item .text-xs {
        margin-bottom: 0.4rem;
        font-size: 0.62rem;
    }

    .ticket-baggage-table {
        table-layout: fixed;
        font-size: 0.58rem;
        line-height: 1.3;
    }

    .ticket-baggage-table th,
    .ticket-baggage-table td {
        padding: 0.45rem 0.25rem;
        overflow-wrap: anywhere;
        text-align: left;
        vertical-align: middle;
    }

    .ticket-passenger-section,
    .ticket-fare-section {
        padding: 1rem 0.85rem;
    }

    .ticket-passenger-section > h2,
    .ticket-fare-section > h2 {
        margin-bottom: 0.8rem;
        font-size: 0.95rem;
    }

    .ticket-passenger-table-wrap,
    .ticket-fare-table-wrap {
        overflow: hidden;
    }

    .ticket-passenger-table {
        table-layout: fixed;
        font-size: 0.62rem;
        line-height: 1.35;
    }

    .ticket-passenger-table th,
    .ticket-passenger-table td {
        padding: 0.48rem 0.28rem;
        overflow-wrap: anywhere;
        vertical-align: top;
        text-align: left;
    }

    .ticket-passenger-table th:nth-child(1),
    .ticket-passenger-table td:nth-child(1) {
        width: 25%;
    }

    .ticket-passenger-table th:nth-child(2),
    .ticket-passenger-table td:nth-child(2) {
        width: 25%;
    }

    .ticket-passenger-table th:nth-child(3),
    .ticket-passenger-table td:nth-child(3) {
        width: 25%;
    }

    .ticket-passenger-table th:nth-child(4),
    .ticket-passenger-table td:nth-child(4) {
        width: 25%;
    }

    .ticket-passenger-table .text-xs {
        font-size: 0.54rem;
    }

    .ticket-fare-table {
        table-layout: fixed;
        font-size: 0.57rem;
        line-height: 1.3;
    }

    .ticket-fare-table th,
    .ticket-fare-table td {
        padding: 0.42rem 0.2rem;
        overflow-wrap: anywhere;
        text-align: left;
    }

    .ticket-fare-table th:nth-child(1),
    .ticket-fare-table td:nth-child(1) {
        width: 25%;
    }

    .ticket-fare-table th:nth-child(n + 2),
    .ticket-fare-table td:nth-child(n + 2) {
        width: 25%;
    }

    .ticket-fare-table tfoot td:first-child,
    .ticket-fare-table tfoot td:last-child {
        text-align: right;
    }

    .ticket-fare-table tfoot td {
        padding: 0.55rem 0.35rem;
        font-size: 0.68rem;
    }
}

/* Print styles - Only necessary overrides */
@media print {
    @page {
        margin: 0.4in;
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
