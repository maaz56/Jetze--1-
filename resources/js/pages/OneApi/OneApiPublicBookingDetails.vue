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
import { computed, nextTick, onMounted, ref, watch } from "vue";
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
    FETCH_CUSTOMER_BOOKING_DETAILS,
    FETCH_CUSTOMER_MARGIN,
    SAVE_CONVERSATION,
    SAVE_REQUEST,
    FETCH_MODIFY_REQUEST_DATA,
    SEND_REPLY,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";
import { SEND_EMAIL } from "@/services/store/actions.type";
import { ChatBubbleIcon } from "@radix-icons/vue";


const store = useStore();
const route = useRoute();
const router = useRouter();

const authStore = useAuthStore();
const loading = ref(true);

// Loading states for individual API calls
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isPdfExporting = ref(false);
const hasAutoDownloadedPdf = ref(false);
const isAgentLoading = ref(true);
const error = ref(null);
const isLoading = computed(() => store.getters['flight/isLoading']);
const CustomerMargin = computed(
    () => store.getters["customerMargin/customerMargin"],
);

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/customerBooking"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const pnrDetails = computed(() => store.getters["flight/pnrData"]?.data);

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
const selectedFares = ref([]);

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
const modifyRequestData = computed(() => store.getters["modifyRequest/requestData"]);

const now = ref(Date.now())

onMounted(() => {
  setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

const getRemainingTime = (expiry) => {
  if (!expiry) return 'N/A'

  const expiryTime = new Date(expiry.replace(' ', 'T')).getTime()
  const diff = expiryTime - now.value
  if (diff <= 0) return 'Expired'

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

const isVoidDisabled = (booking) => {
    const status = booking?.status?.toLowerCase();
    if (!['ticketed', 'issued'].includes(status) || !booking?.issuance_date) return true;

    const issuanceDate = moment(booking.issuance_date);
    if (!issuanceDate.isValid()) return true;

    return moment(now.value).startOf('day').isAfter(issuanceDate.startOf('day'));
};

function openModifyRequestDialog() {
    modifyDialogOpen.value = true;
}

function closeDialog() {
    modifyDialogOpen.value = false;
}

function handleLogin() {
    authStore.openDialog();
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

    const adminMessage = {
        req_id: modifyRequestData.value.id,
        sender: "user",
        sender_id: user_id.value,
        message: replyMessage.value
    };

    store.dispatch("modifyRequest/" + SEND_REPLY, adminMessage).then(() => {
        fetchModifyRequestData();
    });

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
    closeDialog()
}

function sendEmail() {
    store.dispatch("flight/" + SEND_EMAIL, {
        email: custEmail?.value ? custEmail?.value : bookingDetails?.value?.[0]?.main_email,
        booking_id: bookingDetails.value?.[0]?.flight_id,
        booking_source: route?.query?.booking_source
    });
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

async function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isBookingDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_CUSTOMER_BOOKING_DETAILS}`, { bookingId: booking_id, bookingSource: route.query.booking_source });
        parsePnrResponse();
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
    if (isVoidDisabled(bookingDetails?.value?.[0])) {
        error.value = "The void period for this booking has expired.";
        isVoidDialogOpen.value = false;
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

const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
    const total = parseFloat(price) || 0;
    const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
    const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

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
            booking_uuid: pnrData.value?.data?.uuid ?? "null",
            billable_price: pnrData.value?.data?.billable_price ?? "null",
            currency: pnrData.value?.data?.currency?.code ?? "null",
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            booking_status: "canceled",
            booking_source: route.query.flight_provider,
        });

        isDialogOpen.value = false;

    } catch (err) {
        error.value = err.message || 'Failed to cancel booking';
    } finally {
        isLoading.value = false;
        fetchBookingDetails();
    }
}

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
        isDialogOpen.value = false;
        fetchBookingDetails();
    }).catch((err) => {
        error.value = err.message || 'Failed to cancel booking';
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
        booking_uuid: pnrData.value?.data?.uuid ?? "null",
        booking_status: "ticketed",
        booking_source: route.query.booking_source,
        amount: parseFloat(pnrData?.data?.billable_price || 0),
    });

    isConfirmDialogOpen.value = false;
    fetchBookingDetails();
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

const formatCurrency = (amount, currency) => {
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

const agentAmount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0));
const agentDiscount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0));
const margin = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0));
const airportMargin = computed(() => parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0));
const addOnesAmount = computed(() => parseFloat(bookingDetails?.value?.[0]?.add_ones_amount || 0));
const savedMarginTotal = computed(
    () => agentAmount.value + margin.value + airportMargin.value - agentDiscount.value,
);

const normalizeToArray = (val) => {
    if (Array.isArray(val)) return val;
    if (val == null) return [];
    return [val];
};

const sumAttrAmounts = (items) => {
    return normalizeToArray(items).reduce((sum, item) => {
        const attrs = item?.["@attributes"];
        const currency = String(attrs?.CurrencyCode || "PKR").toUpperCase();
        if (currency !== "PKR") return sum;
        return sum + (parseFloat(attrs?.Amount || 0) || 0);
    }, 0);
};

const pnrItinTotalFare = computed(
    () => pnrDetails?.value?.data?.Body?.OTA_AirBookRS?.AirReservation?.PriceInfo?.ItinTotalFare ?? null,
);

const pnrPkrBaseFare = computed(() => {
    const attrs = pnrItinTotalFare.value?.BaseFare?.["@attributes"];
    if (!attrs) return 0;
    if (attrs.CurrencyCode && String(attrs.CurrencyCode).toUpperCase() !== "PKR") return 0;
    return parseFloat(attrs.Amount || 0) || 0;
});

const pnrPkrTaxes = computed(() => sumAttrAmounts(pnrItinTotalFare.value?.Taxes?.Tax));
const pnrPkrFees = computed(() => sumAttrAmounts(pnrItinTotalFare.value?.Fees?.Fee));
const pnrPkrTaxesAndFees = computed(() => pnrPkrTaxes.value + pnrPkrFees.value);

const pnrPkrTotalFare = computed(() => {
    const attrs = pnrItinTotalFare.value?.TotalFare?.["@attributes"];
    if (attrs && attrs.CurrencyCode && String(attrs.CurrencyCode).toUpperCase() !== "PKR") {
        return pnrPkrBaseFare.value + pnrPkrTaxes.value + pnrPkrFees.value;
    }
    const total = parseFloat(attrs?.Amount || 0);
    if (!Number.isFinite(total) || total === 0) {
        return pnrPkrBaseFare.value + pnrPkrTaxes.value + pnrPkrFees.value;
    }
    return total;
});

const pnrPkrSubtotalWithMargins = computed(
    () => pnrPkrBaseFare.value + savedMarginTotal.value + addOnesAmount.value,
);

const pnrPkrGrandTotal = computed(
    () => pnrPkrSubtotalWithMargins.value + pnrPkrTaxesAndFees.value,
);

function calculateTotalFare(fare) {
    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);
    const billable = parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));
    const total = billable + savedMarginTotal.value;
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
  return total + addOnesAmount.value;
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
}

const printBooking = () => {
    window.print();
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

const downloadPDF = async () => {
    const element = document.getElementById("print-section");
    isPdfExporting.value = true;
    await nextTick();

    const options = {
        margin: 2,
        filename: `booking_${booking_id}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2pdf: {
            scale: 2,
            logging: true,
            useCORS: true,
            windowWidth: element.scrollWidth,
            windowHeight: element.scrollHeight,
        },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
    };

    try {
        await html2pdf().from(element).set(options).save();
    } catch (e) {
        console.error("PDF Export error:", e);
    } finally {
        isPdfExporting.value = false;
    }
};

watch([isBookingDetailsLoading, isPnrDetailsLoading], async ([bookingLoading, pnrLoading]) => {
    if (route.query.download !== 'pdf' || bookingLoading || pnrLoading || hasAutoDownloadedPdf.value) return;

    hasAutoDownloadedPdf.value = true;
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 500));
    await downloadPDF();
}, { immediate: true });

// Helper functions
const ensureArray = (data) => {
    if (!data) return [];
    return Array.isArray(data) ? data : [data];
};

const getAirTravelers = () => {
    return ensureArray(pnrDetails.value?.Body?.OTA_AirBookRS?.AirReservation?.TravelerInfo?.AirTraveler);
};

const normalizeDocumentNumber = (value) => {
    return String(value || "").trim().toUpperCase().replace(/[^A-Z0-9]/g, "");
};

const getTravelerResponseDocumentNumber = (traveler) => {
    const documentAttributes = traveler?.Document?.["@attributes"] || {};
    return normalizeDocumentNumber(documentAttributes.DocID || documentAttributes.DocNumber || documentAttributes.DocumentNumber || documentAttributes.DocNo || documentAttributes.Number);
};

const getTravelerTicketNumber = (traveller, index) => {
    const matchedTraveler = getAirTravelers().find((airTraveler) => {
        return getTravelerResponseDocumentNumber(airTraveler) === normalizeDocumentNumber(traveller?.document_no);
    });
    const traveler = matchedTraveler || getAirTravelers()[index];
    const ticketInfo = ensureArray(traveler?.ETicketInfo?.ETicketInfomation);
    const ticketNumber = ticketInfo[0]?.['@attributes']?.eTicketNo;
    if (ticketNumber) {
        return ticketNumber;
    }
    return Array.isArray(pnrDetails.value?.flightTickets) && pnrDetails.value.flightTickets[index] ? pnrDetails.value.flightTickets[index].number : "N/A";
};

const getFlightInfoFromRPH = (rph) => {
    try {
        if (!rph || !pnrData.value) return null;
        const parts = rph.split('$');
        if (parts.length >= 2) {
            const route = parts[1];
            const [from, to] = route.split('/');
            const flightNumber = parts[0];
            return { from, to, flightNumber };
        }
        return null;
    } catch (e) {
        console.error('Error parsing flight RPH:', e);
        return null;
    }
};

const getStatusClass = (status) => {
    const baseClass = 'px-2 py-1 text-xs font-medium rounded-full ';
    switch(status?.toLowerCase()) {
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

const getTravelerMap = () => {
    try {
        if (!pnrData.value) return new Map();
        const travelerInfo = pnrData.value?.Body?.OTA_AirBookRS?.AirReservation?.TravelerInfo;
        if (!travelerInfo?.AirTraveler) return new Map();
        const travelers = ensureArray(travelerInfo.AirTraveler);
        const travelerMap = new Map();
        travelers.forEach(traveler => {
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

const getPassengerName = (travelerRefRPH) => {
    try {
        if (!travelerRefRPH || !pnrData.value) return 'Unknown';
        const travelerMap = getTravelerMap();
        return travelerMap.get(travelerRefRPH)?.fullName || `Passenger (${travelerRefRPH.substring(0, 8)}...)`;
    } catch (e) {
        console.error('Error getting passenger name:', e);
        return 'Unknown';
    }
};

const getSeatRequests = () => {
    try {
        const pnr = pnrData.value;
        if (!pnr) return [];
        let seatRequests = [];
        const travelerInfo = pnr?.Body?.OTA_AirBookRS?.AirReservation?.TravelerInfo;
        if (!travelerInfo) return [];
        const specialReqs = ensureArray(travelerInfo.SpecialReqDetails);
        specialReqs.forEach(req => {
            if (req?.SeatRequests?.SeatRequest) {
                const seats = ensureArray(req.SeatRequests.SeatRequest);
                seatRequests.push(...seats);
            }
        });
        const travelerMap = getTravelerMap();
        const mapped = seatRequests.map(seat => {
            const attrs = seat['@attributes'] || {};
            const travelerRef = attrs.TravelerRefNumberRPHList;
            const flightRef = attrs.FlightRefNumberRPHList;
            return {
                ...seat,
                travelerRef,
                flightNumber: attrs.FlightNumber || attrs.flightNumber || "",
                passengerName: travelerMap.get(travelerRef)?.fullName || getPassengerName(travelerRef),
                seatNumber: attrs.SeatNumber || 'N/A',
                status: attrs.Status || 'Confirmed',
                flightInfo: getFlightInfoFromRPH(flightRef)
            };
        });
        return mapped.filter((seat) => {
            const seatNumber = String(seat?.seatNumber || '').trim();
            if (!seatNumber || seatNumber.toUpperCase() === 'N/A') return false;
            if (!seat?.flightInfo?.from || !seat?.flightInfo?.to) return false;
            return true;
        });
    } catch (e) {
        console.error('Error parsing seat requests:', e);
        return [];
    }
};

const getMealRequests = () => {
    try {
        const pnr = pnrData.value;
        if (!pnr) return [];
        let mealRequests = [];
        const travelerInfo = pnr?.Body?.OTA_AirBookRS?.AirReservation?.TravelerInfo;
        if (!travelerInfo) return [];
        const specialReqs = ensureArray(travelerInfo.SpecialReqDetails);
        specialReqs.forEach(req => {
            if (req?.MealRequests?.MealRequest) {
                const meals = ensureArray(req.MealRequests.MealRequest);
                mealRequests.push(...meals);
            }
        });
        const travelerMap = getTravelerMap();
        return mealRequests.map(meal => {
            const attrs = meal['@attributes'] || {};
            const travelerRef = attrs.TravelerRefNumberRPHList;
            const flightRef = attrs.FlightRefNumberRPHList;
            return {
                ...meal,
                travelerRef,
                flightNumber: attrs.FlightNumber || attrs.flightNumber || "",
                passengerName: travelerMap.get(travelerRef)?.fullName || getPassengerName(travelerRef),
                mealCode: attrs.mealCode,
                mealDescription: attrs.mealDescription || attrs.mealCode,
                quantity: attrs.mealQuantity || 1,
                flightInfo: getFlightInfoFromRPH(flightRef)
            };
        });
    } catch (e) {
        console.error('Error parsing meal requests:', e);
        return [];
    }
};

const getBaggageRequests = () => {
    try {
        const pnr = pnrData.value;
        if (!pnr) return [];
        let baggageRequests = [];
        const travelerInfo = pnr?.Body?.OTA_AirBookRS?.AirReservation?.TravelerInfo;
        if (!travelerInfo) return [];
        const specialReqs = ensureArray(travelerInfo.SpecialReqDetails);
        specialReqs.forEach(req => {
            if (req?.BaggageRequests?.BaggageRequest) {
                const baggages = ensureArray(req.BaggageRequests.BaggageRequest);
                baggageRequests.push(...baggages);
            }
        });
        const travelerMap = getTravelerMap();
        const mapped = baggageRequests.map(baggage => {
            const attrs = baggage['@attributes'] || {};
            const travelerRef = attrs.TravelerRefNumberRPHList;
            const flightRef = attrs.FlightRefNumberRPHList;
            return {
                ...baggage,
                travelerRef,
                flightNumber: attrs.FlightNumber || attrs.flightNumber || "",
                passengerName: travelerMap.get(travelerRef)?.fullName || getPassengerName(travelerRef),
                baggageCode: attrs.baggageCode || attrs.BaggageCode || 'N/A',
                flightInfo: getFlightInfoFromRPH(flightRef)
            };
        });
        return mapped.filter((baggage) => {
            const baggageCode = String(baggage?.baggageCode || '').trim();
            if (!baggageCode || baggageCode.toUpperCase() === 'N/A') return false;
            if (!baggage?.flightInfo?.from || !baggage?.flightInfo?.to) return false;
            return true;
        });
    } catch (e) {
        console.error('Error parsing baggage requests:', e);
        return [];
    }
};

const hasSSR = () => {
    return getSeatRequests().length > 0 || getMealRequests().length > 0 || getBaggageRequests().length > 0;
};

// Helper functions for the new layout
function formatShortDate(dateString) {
    if (!dateString) return "N/A";
    return moment(dateString).format("D MMM YYYY");
}

function formatLongDate(dateString) {
    if (!dateString) return "N/A";
    return moment(dateString).format("dddd, MMMM D, YYYY");
}

function formatTicketTime(dateString) {
    if (!dateString) return "N/A";
    return moment.parseZone(dateString).format("hh:mm A");
}

function getDisplayStatus(status) {
    if (!status) return "N/A";
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function getItineraryDocumentLabel(status) {
    const normalized = (status || "").toLowerCase();
    if (["ticketed", "issued"].includes(normalized)) return "E-Ticket";
    if (["canceled", "cancelled"].includes(normalized)) return "Canceled Itinerary";
    if (normalized === "booked") return "On Hold Itinerary";
    if (!normalized) return "Itinerary";
    return `${getDisplayStatus(normalized)} Itinerary`;
}

function getCabinClass(flight) {
    const selectedFare = (flight?.fares || []).find((fare) => selectedFares.value.includes(fare?.ref_id));
    const cabinValue = selectedFare?.cabin || selectedFare?.cabin_class || selectedFare?.brand_name;
    if (!cabinValue) return "Economy";
    if (typeof cabinValue === "string") return cabinValue;
    if (Array.isArray(cabinValue)) {
        const normalizedCabins = [...new Set(cabinValue.filter(Boolean))];
        return normalizedCabins.join(", ") || "Economy";
    }
    if (typeof cabinValue === "object") {
        const normalizedCabins = [...new Set(Object.values(cabinValue).filter(Boolean))];
        return normalizedCabins.join(", ") || "Economy";
    }
    return "Economy";
}

function getLayoverLabel(segment) {
    if (!segment?.layover_time) return null;
    const hours = Math.floor(segment.layover_time / 60);
    const minutes = segment.layover_time % 60;
    if (hours > 0) return `${hours}h ${minutes}m`;
    return `${minutes}m`;
}

function getBookingFlights(booking) {
    const parsed = parseFlightData(booking?.flight_data);
    return parsed?.original?.leg?.flights || parsed?.leg?.flights || [];
}

function getBookingSegments(booking) {
    return getBookingFlights(booking).flatMap((flight) => flight?.segments || []);
}

function getItineraryBlocks(booking) {
    return getBookingFlights(booking).map((flight) => {
        const segments = flight?.segments || [];
        const firstSegment = segments[0] || {};
        const lastSegment = segments[segments.length - 1] || {};
        return {
            flight,
            segments,
            from: firstSegment?.from,
            to: lastSegment?.to,
            departureAt: firstSegment?.departure_at,
            arrivalAt: lastSegment?.arrival_at,
        };
    });
}

function normalizeName(value) {
    return String(value || "").trim().toUpperCase().replace(/\s+/g, " ");
}

function getSegmentKey(segment) {
    const flightNo = String(segment?.flight_number || "").toUpperCase().replace(/\s+/g, "");
    return `${segment?.from?.iata || "N/A"}-${segment?.to?.iata || "N/A"}-${flightNo || "N/A"}`;
}

function getPassengerTypeCode(type) {
    const normalized = String(type || "").toUpperCase();
    if (["ADULT", "ADT"].includes(normalized)) return "ADT";
    if (["CHILD", "CHD", "CNN"].includes(normalized)) return "CHD";
    if (["INFANT", "INF"].includes(normalized)) return "INF";
    return normalized || "ADT";
}

function normalizePaxCode(value) {
    const code = String(value || "").toUpperCase();
    if (["ADT", "ADULT"].includes(code)) return "ADT";
    if (["CHD", "CHILD", "CNN"].includes(code)) return "CHD";
    if (["INF", "INFANT"].includes(code)) return "INF";
    return code;
}

function getLegacyPassengerBaggage(booking, passengerIndex) {
    const flightParsed = parseFlightData(booking?.flight_data);
    const bag = flightParsed?.passengerInfo?.[passengerIndex]?.baggage?.[0];
    if (!bag) return null;
    if (bag?.description) return bag.description;
    if (bag?.weight) return `${bag.weight}${bag?.unit || "kg"}`;
    if (bag?.pieces) return `${bag.pieces} piece${bag.pieces > 1 ? "s" : ""}`;
    return null;
}

function getBaggageTextForSegment(booking, flight, segment, passengerType, passengerIndex) {
    const selectedFare = (flight?.fares || []).find((fare) => selectedFares.value.includes(fare?.ref_id));
    const baggagePolicies = selectedFare?.baggage_policies || [];
    const paxCode = getPassengerTypeCode(passengerType);
    const matched = baggagePolicies.filter((bp) => {
        if (normalizePaxCode(bp?.traveler_type) !== paxCode) return false;
        if (bp?.type && !["checked", "checkin", "check-in","carry"].includes(String(bp.type).toLowerCase())) return false;
        const segmentRefs = ensureArray(bp?.segment_ref_ids || bp?.segment_ref_id);
        if (!segmentRefs.length) return true;
        return segmentRefs.includes(segment?.ref_id);
    });
    return matched[0]?.description || getLegacyPassengerBaggage(booking, passengerIndex) || "N/A";
}

function getTravelerRphToPassengerIndexMap(booking) {
    const map = new Map();
    const travelers = getAirTravelers();
    const bookingPassengers = booking?.pessangers || [];
    const usedPassengerIndexes = new Set();
    const byDoc = new Map();
    const byNameAndType = new Map();
    const byName = new Map();
    const byType = new Map();

    const pushToGroup = (group, key, index) => {
        if (!key) return;
        if (!group.has(key)) group.set(key, []);
        group.get(key).push(index);
    };

    const takeFromGroup = (group, key) => {
        if (!key || !group.has(key)) return -1;
        const queue = group.get(key);
        while (queue.length) {
            const candidate = queue.shift();
            if (!usedPassengerIndexes.has(candidate)) return candidate;
        }
        return -1;
    };

    bookingPassengers.forEach((passenger, index) => {
        const paxType = getPassengerTypeCode(passenger?.type);
        const fullName = normalizeName(`${passenger?.title || ""} ${passenger?.first_name || ""} ${passenger?.last_name || ""}`);
        const doc = normalizeDocumentNumber(passenger?.document_no);

        pushToGroup(byDoc, doc, index);
        pushToGroup(byNameAndType, `${paxType}|${fullName}`, index);
        pushToGroup(byName, fullName, index);
        pushToGroup(byType, paxType, index);
    });

    travelers.forEach((airTraveler, travelerIndex) => {
        const rph = airTraveler?.TravelerRefNumber?.["@attributes"]?.RPH;
        if (!rph) return;

        const airDoc = getTravelerResponseDocumentNumber(airTraveler);
        const airType = getPassengerTypeCode(airTraveler?.["@attributes"]?.PassengerTypeCode);
        const airName = normalizeName(`${airTraveler?.PersonName?.NameTitle || ""} ${airTraveler?.PersonName?.GivenName || ""} ${airTraveler?.PersonName?.Surname || ""}`);

        let index = takeFromGroup(byDoc, airDoc);
        if (index === -1) {
            index = takeFromGroup(byNameAndType, `${airType}|${airName}`);
        }
        if (index === -1) {
            index = takeFromGroup(byName, airName);
        }
        if (index === -1) {
            index = takeFromGroup(byType, airType);
        }
        if (index === -1 && !usedPassengerIndexes.has(travelerIndex) && travelerIndex < bookingPassengers.length) {
            index = travelerIndex;
        }

        if (index >= 0) {
            usedPassengerIndexes.add(index);
            map.set(String(rph), index);
        }
    });

    return map;
}

function getSegmentPassengerRows(booking, flight, segment, segmentIndex) {
    const passengers = booking?.pessangers || [];
    const segmentKey = getSegmentKey(segment);
    const rphMap = getTravelerRphToPassengerIndexMap(booking);
    const seatRequests = getSeatRequests();
    const mealRequests = getMealRequests();
    const baggageRequests = getBaggageRequests();

    return passengers.map((traveller, index) => {
        const requestMatcher = (request, travelerRph) => {
        const reqFlightNo = String(
                request?.flightNumber || request?.flightInfo?.flightNumber || ""
            )
            .toUpperCase()
            .replace(/\s+/g, "")
            .slice(2);
            const requestKey = `${request?.flightInfo?.from || "N/A"}-${request?.flightInfo?.to || "N/A"}-${reqFlightNo || "N/A"}`;
            const rphIndex = rphMap.get(String(travelerRph || ""));
            return requestKey === segmentKey && (rphIndex === index || request?.passengerName === `${traveller?.title || ""} ${traveller?.first_name || ""} ${traveller?.last_name || ""}`.trim());
        };

        const seat = seatRequests.find((req) => requestMatcher(req, req?.travelerRef));
        const meal = mealRequests.find((req) => requestMatcher(req, req?.travelerRef));
        const extraBaggage = baggageRequests.find((req) => requestMatcher(req, req?.travelerRef));

        const otherRequests = [seat?.status, seat?.seatNumber && seat?.seatNumber !== "N/A" ? `Seat ${seat.seatNumber}` : null, meal?.mealDescription, extraBaggage?.baggageCode]
            .filter(Boolean)
            .join(", ");
        return {
            key: `${segmentIndex}-${index}`,
            passengerName: `${traveller?.title || ""} ${traveller?.first_name || ""} ${traveller?.last_name || ""}`.trim() || "N/A",
            passengerType: traveller?.type || "Adult",
            ticketNo: getTravelerTicketNumber(traveller, index) || "N/A",
            frequentFlyerNo: traveller?.frequent_flyer_number || traveller?.frequent_flyer_no || "N/A",
            mealType: meal?.mealDescription || meal?.mealCode || traveller?.meal_type || "N/A",
            baggage: getBaggageTextForSegment(booking, flight, segment, traveller?.type, index),
            excessBaggage: extraBaggage?.baggageCode || "N/A",
            seatNo: seat?.seatNumber || traveller?.seat_no || "N/A",
            otherRequests: otherRequests || "N/A",
        };
    });
}

function getAirlinePnrValue() {
    return pnrData?.value?.ReservationResponse?.Reservation?.Receipt?.[1]?.Confirmation?.Locator?.value || "N/A";
}

function getPassengerTicketNumberNew(index) {
    const documents = pnrData?.value?.ReservationResponse?.Reservation?.Receipt?.[2]?.Document;
    return Array.isArray(documents) && documents[index]?.Number ? documents[index].Number : "Not Ticketed";
}

function getFareAdjustmentTotal() {
    return (
        (parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0) +
            parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0) -
            parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0)) *
        parseInt(bookingDetails?.value?.[0]?.pessangers?.length || 1)
    );
}

function getDisplayGrandTotal() {
    if (pnrDetails?.value?.fares?.length) {
        return parseFloat(pnrDetails.value.fares[0]?.totals?.total || 0) + getFareAdjustmentTotal();
    }
    return calculateGrandTotal();
}

const marginPerFlight = computed(() => {
    const flightCount = flightData?.value?.leg?.flights?.length || 0;
    if (!flightCount) return 0;
    return savedMarginTotal.value / flightCount;
});

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
    } else {
        fetchAgent();
    }
    fetchModifyRequestData();
    fetchBookingDetails();
    fetchCustomerMarginValues();
    fetchPnrDetails();
});
</script>

<template>
    <section>
        <div v-if="isPnrDetailsLoading || isLoading" class="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50">
            <Spinner />
        </div>

        <div class="max-w-7xl mx-auto min-h-screen bg-gray-100 p-4">
            <!-- Action Buttons -->
            <div v-if="route?.query?.booking_source == 1">
                <div v-for="booking in bookingDetails" :key="booking?.id"
                    class="bg-white rounded-lg shadow-sm p-3 mb-3 flex flex-wrap gap-2 justify-end print:hidden">
                    <button @click="printBooking"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-1.5">
                        <PrinterIcon class="h-3.5 w-3.5" />
                        Print
                    </button>
                    <!-- <button @click="downloadPDF"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5">
                        <Download class="h-3.5 w-3.5" />
                        Download PDF
                    </button> -->
                    <button :disabled="modifyRequestData" @click="openModifyRequestDialog"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                        Modify Request
                    </button>
                    <button
                        :disabled="isVoidDisabled(booking)"
                        :title="isVoidDisabled(booking) ? 'Void is only available until midnight on the issuance date' : 'Void booking'"
                        @click="isVoidDialogOpen = true"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed">
                        Void Booking
                    </button>
                    <Dialog :open="isVoidDialogOpen" @update:open="isVoidDialogOpen = $event">
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle class="text-lg font-semibold">Confirm Void</DialogTitle>
                            </DialogHeader>
                            <p class="text-sm text-gray-700">Are you sure you want to void this booking? This action cannot be undone.</p>
                            <DialogFooter>
                                <Button variant="secondary" @click="isVoidDialogOpen = false">Cancel</Button>
                                <Button variant="destructive" :disabled="isVoidDisabled(booking)" @click="voidRequest">Confirm</Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <button :disabled="(bookingDetails?.[0]?.status === 'ticketed') || (bookingDetails?.[0]?.status === 'canceled')" @click="goToPaymentView"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                        <DollarSign class="h-3.5 w-3.5" />
                        Pay Now
                    </button>
                    <button
                        :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                        @click="isDialogOpen = true"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                        Cancel Booking
                    </button>
                    <button @click="toggleChatOpen"
                        class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-1.5">
                        <ChatBubbleIcon class="h-3.5 w-3.5" />
                        Chat
                    </button>
                    <button @click="toggleFareInfo"
                        class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center gap-1.5">
                        <EyeOff class="h-3.5 w-3.5" />
                        <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                        <span v-else>View Fare Details</span>
                    </button>
                </div>

                <!-- Cancel Dialog -->
                <div v-if="isDialogOpen"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                    @click.self="isDialogOpen = false">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-5 transform transition-all">
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="text-base font-medium text-gray-900">Cancel Booking</h3>
                            <button @click="isDialogOpen = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs text-gray-500">Are you sure you want to cancel this booking? This action cannot be undone.</p>
                            <div v-if="error" class="mt-2 p-2 bg-red-100 text-red-700 rounded-md text-xs">{{ error }}</div>
                        </div>
                        <div class="mt-5 flex justify-end space-x-2">
                            <button @click="isDialogOpen = false" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button @click="handleCancelBooking" class="px-3 py-1.5 bg-red-600 border border-transparent rounded-md text-xs font-medium text-white hover:bg-red-700">Confirm Cancellation</button>
                        </div>
                    </div>
                </div>

                <!-- Main Content with Chat Sidebar -->
                <div class="flex gap-4">
                    <!-- Print Section - Main Content -->
                    <div
                        :class="[
                            isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full',
                            'ticket-sheet',
                            { 'pdf-export-mode': isPdfExporting }
                        ]"
                        id="print-section"
                    >
                        <div v-for="booking in bookingDetails" :key="booking.id"
                            class="bg-white rounded-lg shadow-sm overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                            <!-- Header -->
                            <div class="px-5 pt-5 print:pt-3 print:px-4">
                                <div class="relative mb-5 flex items-center justify-center print:mb-3">
                                    <div class="absolute left-0 right-0 h-px bg-primary print:hidden"></div>
                                    <div class="relative rounded-md bg-primary px-4 py-1 text-sm font-bold uppercase tracking-wide text-white print:bg-primary print:text-white print:px-3 print:py-0.5">
                                        {{ getItineraryDocumentLabel(booking?.status) }}
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1.15fr] print:grid-cols-[1fr_1.15fr] print:gap-3">
                                    <div class="flex items-start justify-center md:justify-start print:justify-start">
                                        <img class="h-10 w-auto object-contain print:h-8 print:max-w-full" src="/public/assets/logo.png" alt="Logo" />
                                    </div>
                                    <div class="text-center md:text-left print:text-left">
                                        <h2 class="text-3xl font-extrabold uppercase tracking-tight text-gray-900 md:text-4xl print:text-2xl print:leading-tight">
                                            {{ agentData?.customer?.company_name || "" }}
                                        </h2>
                                        <p class="mt-1 text-sm leading-5 text-gray-700 print:text-xs print:mt-0.5">
                                            {{ agentData?.customer?.address || "Pakistan" }}
                                        </p>
                                        <p class="text-sm text-gray-700 print:text-xs">
                                            Phone: {{ agentData?.customer?.phone || "N/A" }}
                                        </p>
                                        <p class="text-sm text-gray-700 print:text-xs">
                                            Email: {{ agentData?.customer?.email || "N/A" }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 print:mt-2 print:border-gray-300 print:rounded-md">
                                    <div class="grid grid-cols-1 text-xs md:grid-cols-[1.3fr_1fr_1fr_1fr] print:grid-cols-[1.3fr_1fr_1fr_1fr]">
                                        <div class="bg-primary px-3 py-2 print:px-2 print:py-1.5 print:bg-primary">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-white/80 print:text-[8px] print:text-white/90">Prepared For</p>
                                            <p class="text-sm font-bold uppercase leading-4 text-white print:text-xs">
                                                {{ booking?.pessangers?.[0]?.title }} {{ booking?.pessangers?.[0]?.first_name }} {{ booking?.pessangers?.[0]?.last_name }}
                                                <span class="normal-case text-xs font-semibold print:text-[10px] text-white/90">({{ booking?.pessangers?.[0]?.type || "Adult" }})</span>
                                            </p>
                                        </div>
                                        <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5 print:bg-[#f0f2f6] print:border-l print:border-gray-300">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[8px]">PNR</p>
                                            <p class="text-sm font-semibold text-gray-900 print:text-xs">
                                                {{ pnrDetails?.Response?.Data?.pnrDetail?.PNRN ?? pnrDetails?.bookingId ?? booking?.itinerary_ref }}
                                            </p>
                                        </div>
                                        <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5 print:bg-[#f0f2f6] print:border-l print:border-gray-300">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[8px]">Airline PNR</p>
                                            <p class="text-sm font-semibold text-gray-900 print:text-xs">{{ getAirlinePnrValue() }}</p>
                                        </div>
                                        <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5 print:bg-[#f0f2f6] print:border-l print:border-gray-300">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[8px]">Booking Date</p>
                                            <p class="text-sm font-semibold text-gray-900 print:text-xs">{{ formatShortDate(booking?.created_at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Information -->
                            <div class="space-y-2 p-5 pt-3 print:p-1 print:space-y-1">
                                <template v-for="(itinerary, itineraryIndex) in getItineraryBlocks(booking)" :key="itineraryIndex">
                                    <div class="overflow-hidden rounded-t-sm print:rounded-none mt-4 print:mt-2">
                                        <div class="grid grid-cols-1 bg-primary text-white md:grid-cols-[1.2fr_1fr_1fr] print:grid-cols-[1.2fr_1fr_1fr] print:bg-primary">
                                            <div class="px-3 py-1.5 text-md font-semibold border-b border-white/20 md:border-b-0 md:border-r md:border-white/20 print:px-2 print:py-1 print:text-[10px]">
                                                {{ itinerary?.from?.city?.name || itinerary?.from?.iata }} → {{ itinerary?.to?.city?.name || itinerary?.to?.iata }}
                                            </div>
                                            <div class="px-3 py-1.5 border-b border-white/20 md:border-b-0 md:border-r md:border-white/20 print:px-2 print:py-1">
                                                <p class="text-[9px] uppercase tracking-wide text-white/80 print:text-[7px]">Departure Date</p>
                                                <p class="text-[11px] font-medium print:text-[9px]">{{ formatLongDate(itinerary?.departureAt) }}</p>
                                            </div>
                                            <div class="px-3 py-1.5 print:px-2 print:py-1">
                                                <p class="text-[9px] uppercase tracking-wide text-white/80 print:text-[7px]">Arrival Date</p>
                                                <p class="text-[11px] font-medium print:text-[9px]">{{ formatLongDate(itinerary?.arrivalAt) }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <template v-for="(segment, segmentIndex) in itinerary?.segments" :key="`${itineraryIndex}-${segmentIndex}`">
                                        <div v-if="segmentIndex > 0 && getLayoverLabel(segment)" class="flex items-center justify-center py-0.5 print:py-0 mt-1">
                                            <div class="flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-2 py-0.5 text-[10px] font-medium text-primary shadow-sm print:px-1.5 print:py-0 print:text-[8px]">
                                                <span class="inline-flex h-1.5 w-1.5 rounded-full bg-primary print:h-1 print:w-1"></span>
                                                <span>Stopover</span>
                                                <span class="text-primary/70">Layover {{ getLayoverLabel(segment) }}</span>
                                            </div>
                                        </div>

                                        <!-- Segment info panel -->
                                        <div class="overflow-hidden border border-gray-200 bg-white print:border-gray-300 mt-2 print:mt-1">
                                            <div class="grid grid-cols-1 md:grid-cols-[260px_1fr_1fr] print:grid-cols-[220px_1fr_1fr]">
                                                <div class="border-b border-gray-200 bg-[#f8f8f8] md:border-b-0 md:border-r print:border-b print:border-gray-300">
                                                    <div class="flex items-center gap-2 px-3 py-2 print:px-2 print:py-1.5">
                                                        <img v-if="segment?.operating_carrier?.logo" :src="segment?.operating_carrier?.logo" :alt="segment?.operating_carrier?.name" class="h-7 w-auto object-contain print:h-5 print:max-w-[40px]" />
                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-xs font-bold text-gray-900 break-words print:text-[10px]">
                                                                {{ segment?.operating_carrier?.name || "Airline" }}
                                                                <span class="font-medium">{{ segment?.operating_carrier?.iata }} {{ segment?.flight_number }}</span>
                                                            </p>
                                                            <p class="text-[10px] text-gray-700 print:text-[8px]">{{ segment?.aircraft || "Aircraft N/A" }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="grid grid-cols-2 border-t border-gray-200 print:border-gray-300">
                                                        <div class="px-3 py-1.5 print:px-2 print:py-1">
                                                            <p class="text-[10px] text-gray-600 print:text-[8px]">Status</p>
                                                            <p class="mt-0.5 inline-flex rounded-md border border-primary/30 bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold text-primary print:mt-0.5 print:px-1 print:py-0 print:text-[8px]">
                                                                {{ getDisplayStatus(booking?.status) }}
                                                            </p>
                                                        </div>
                                                        <div class="border-l border-gray-200 px-3 py-1.5 print:px-2 print:py-1">
                                                            <p class="text-[10px] text-gray-600 print:text-[8px]">Class</p>
                                                            <p class="mt-0.5 text-[10px] font-semibold text-gray-900 print:mt-0.5 print:text-[8px]">
                                                                {{ getCabinClass(itinerary?.flight) }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="border-b border-gray-200 md:border-b-0 md:border-r print:border-b print:border-gray-300">
                                                    <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                        <p class="text-lg font-bold leading-tight text-gray-900 print:text-base">{{ segment?.from?.city?.name || segment?.from?.iata }}</p>
                                                        <p class="mt-0.5 text-[11px] text-gray-500 break-words print:text-[9px] print:mt-0">[{{ segment?.from?.iata }}] {{ segment?.from?.name }}</p>
                                                    </div>
                                                    <div class="border-t border-gray-200 px-3 py-2 print:px-2 print:py-1.5">
                                                        <p class="text-lg font-bold text-gray-900 print:text-base">{{ formatTicketTime(segment?.departure_at) }}</p>
                                                        <p class="text-[11px] text-gray-500 print:text-[9px]">{{ formatShortDate(segment?.departure_at) }}</p>
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                        <p class="text-lg font-bold leading-tight text-gray-900 print:text-base">{{ segment?.to?.city?.name || segment?.to?.iata }}</p>
                                                        <p class="mt-0.5 text-[11px] text-gray-500 break-words print:text-[9px] print:mt-0">[{{ segment?.to?.iata }}] {{ segment?.to?.name }}</p>
                                                    </div>
                                                    <div class="border-t border-gray-200 px-3 py-2 print:px-2 print:py-1.5">
                                                        <p class="text-lg font-bold text-gray-900 print:text-base">{{ formatTicketTime(segment?.arrival_at) }}</p>
                                                        <p class="text-[11px] text-gray-500 print:text-[9px]">{{ formatShortDate(segment?.arrival_at) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto custom-scrollbar border border-gray-200 print:border-gray-300 print:overflow-visible mt-2 mb-1 print:mt-1 print:mb-1">
                                            <table class="w-full min-w-[900px] text-left print:min-w-0 print:table-auto">
                                                <thead class="bg-[#f0f2f6]">
                                                    <tr class="text-primary">
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Name</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">E-Ticket</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Frequent Flyer Number</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Meal Type</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Baggage</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Excess Baggage</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Seat Number</th>
                                                        <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Other Requests</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="row in getSegmentPassengerRows(booking, itinerary.flight, segment, segmentIndex)" :key="row.key" class="border-t border-gray-200 text-sm text-gray-900 print:border-gray-300 print:text-xs">
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">
                                                            <span class="block uppercase">{{ row.passengerName || "N/A" }}</span>
                                                            <span class="block text-xs text-gray-600 print:text-[10px]">({{ row.passengerType || "Adult" }})</span>
                                                        </td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.ticketNo || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.frequentFlyerNo || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.mealType || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.baggage || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.excessBaggage || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.seatNo || "N/A" }}</td>
                                                        <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.otherRequests || "N/A" }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                </template>
                            </div>

                            <!-- Fare Breakdown -->
                            <div class="px-5 pb-5 print:px-3 print:pb-3" v-if="isDetailsInfoVisible">
                                <div class="overflow-hidden border border-gray-200 print:border-gray-300">
                                    <div class="bg-[#f0f2f6] px-3 py-2 print:px-2 print:py-1.5">
                                        <h2 class="text-base font-bold uppercase tracking-wide text-primary print:text-sm">Fare Breakdown</h2>
                                    </div>
                                   <div class="custom-scrollbar table-container overflow-x-auto">
                                        <table class="w-full min-w-[600px] text-left print:min-w-0 print:table-auto">
                                            <thead class="border-b border-gray-200 bg-white print:border-gray-300">
                                                <tr class="text-sm font-semibold text-gray-600 print:text-xs">
                                                    <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Sector</th>
                                                    <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Subtotal</th>
                                                    <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Taxes + Fees</th>

                                                    <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Grand Total</th>
                                                </tr>
                                            </thead>
                                            <tbody v-if="pnrItinTotalFare" class="divide-y divide-gray-200 print:divide-gray-400">
                                                <tr class="hover:bg-gray-50 print:bg-transparent">
                                                    <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">PNR</td>
                                                    <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(pnrPkrSubtotalWithMargins) }}</td>
                                                    <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(pnrPkrTaxesAndFees) }}</td>
                                                    <td class="px-3 py-2 uppercase font-bold print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(pnrPkrGrandTotal) }}</td>
                                                </tr>
                                            </tbody>
                                            <tbody v-else-if="pnrDetails?.fares?.length" class="divide-y divide-gray-200 print:divide-gray-400">
                                                <tr class="hover:bg-gray-50 print:bg-transparent">
                                                    <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(calculateFinalPrice(pnrDetails?.fares?.[0]?.totals?.subtotal, fare?.margin_amount, fare?.margin_type, fare?.amount_type || 0) + savedMarginTotal) }}</td>
                                                    <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}</td>
                                                    <td class="px-3 py-2 uppercase font-bold print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0) + savedMarginTotal) }}</td>
                                                </tr>
                                            </tbody>
                                            <tbody v-else class="divide-y divide-gray-200 print:divide-gray-400">
                                                <template v-for="(flight, index) in parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights" :key="index">
                                                    <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                                        const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                                            ? parseFlightData(bookingDetails[0]?.fare_reference)
                                                            : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                                        return fareRefs.includes(f.ref_id);
                                                    })" :key="fareIndex" class="hover:bg-gray-50 print:bg-transparent">
                                                        <td class="px-3 py-2 uppercase print:px-2 print:py-1.5">
                                                            {{ flight.segments?.[0]?.from?.iata }} → {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                                        </td>
                                                        <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">
                                                            {{ formatAmount(calculateFinalPrice(fare?.base_price, fare?.margin_amount, fare?.margin_type, fare?.amount_type) + agentAmount - agentDiscount + margin + airportMargin) }}
                                                        </td>
                                                        <td class="px-3 py-2 uppercase print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(calculateTaxes(fare)) }}</td>
                                                        <td class="px-3 py-2 uppercase font-bold print:px-2 print:py-1.5 text-xs max-sm:text-sm">{{ formatAmount(calculateTotalFare(fare)) }}</td>
                                                    </tr>
                                                </template>
                                            </tbody>

                                            <tfoot v-if="!pnrItinTotalFare" class="border-gray-400 bg-gray-50 print:bg-transparent">
                                                <tr>
                                                    <td colspan="3" class="py-2 px-2 text-right font-bold text-gray-900 text-xs">Add-Ones:</td>
                                                    <td class="py-2 px-2 font-bold text-primary text-xs">{{ formatAmount(addOnesAmount) }}</td>
                                                </tr>
                                                <tr>
                                                    <td colspan="3" class="py-2 px-2 text-right font-bold text-gray-900 text-xs">Total Amount</td>
                                                    <td class="py-2 px-2 font-bold text-primary text-xs">{{ formatAmount(calculateGrandTotal()) }}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Important Information -->
                            <div class="px-5 pb-5 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid print:px-3 print:pb-3">
                                <h2 class="text-base font-bold text-gray-800 mb-3 print:text-gray-900 print:text-sm print:mb-0.5">Important Information</h2>

                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 print:bg-transparent print:border-gray-300 print:p-1">
                                    <div class="space-y-3 text-xs text-gray-700 print:text-gray-800 print:space-y-0.5 print:text-[7.5pt]">
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-0.5 print:mb-0">Check-in Recommendations</h3>
                                            <p>We suggest reporting at the airline check-in counter: 3 hours prior to departure for Economy and 2 hours for Business/First Class.</p>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-0.5 print:mb-0">Important Notices</h3>
                                            <p>Airlines reserve the right to cancel or change schedules without notice. Schedules shown are based on expected flying times provided by the airline.</p>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-0.5 print:mb-0">Document Verification</h3>
                                            <p>Passengers may be asked for a valid photo ID at check-in. If the ticket name and ID do not match, travel can be denied and re-issuance may be required as per airline fare rules.</p>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-0.5 print:mb-0">Cancellation Fees</h3>
                                            <p>Fees may apply for cancellations, changes, or no-shows, according to airline policy.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="px-5 py-3 text-center bg-gray-50 border-t border-gray-200 print:bg-transparent print:border-gray-300 print:px-3 print:py-1 print:break-inside-avoid">
                                <p class="text-xs text-gray-700 mb-0.5 font-medium print:text-gray-800 print:text-[9px]">
                                    Thank you for choosing {{ agentData?.agent_data?.company_name || 'Jetze Travels' }}
                                </p>
                                <p class="text-[10px] text-gray-500 print:text-gray-600 print:text-[8px]">
                                    For assistance, contact us at {{ agentData?.agent_data?.mobile || '+92 3111711123' }} or {{ agentData?.agent_data?.company_email || 'support@Jetze.pk' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Chat Panel - Right Side -->
                    <div v-if="modifyRequestData && isChatOpen" class="w-4/12 print:hidden">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col h-[calc(100vh-200px)] sticky top-4">
                            <div class="p-3 border-b border-gray-200 bg-gray-50 rounded-t-lg flex-shrink-0">
                                <h3 class="text-base font-semibold text-gray-800">Modify Request</h3>
                            </div>
                            <div class="p-3 border-b border-gray-200 flex-shrink-0">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-medium text-gray-600">Status</span>
                                    <span :class="{
                                        'px-2 py-0.5 text-[10px] font-semibold rounded-full': true,
                                        'bg-yellow-100 text-yellow-800': modifyRequestData?.status === 'pending',
                                        'bg-green-100 text-green-800': modifyRequestData?.status === 'approved',
                                        'bg-red-100 text-red-800': modifyRequestData?.status === 'rejected',
                                        'bg-blue-100 text-blue-800': modifyRequestData?.status === 'processing'
                                    }">
                                        {{ modifyRequestData?.status || 'Pending' }}
                                    </span>
                                </div>
                                <div class="mt-1.5 text-xs">
                                    <span class="font-medium text-gray-600">Reason:</span>
                                    <p class="mt-0.5 text-gray-800">
                                        {{ modifyRequestData?.reason === 'change_scope' ? 'Change Scope' :
                                            modifyRequestData?.reason === 'extend_deadline' ? 'Extend Deadline' :
                                            modifyRequestData?.reason === 'refund' ? 'Request Refund' :
                                            modifyRequestData?.reason === 'cancel' ? 'Cancel Booking' :
                                            modifyRequestData?.reason || 'Not specified' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50/50">
                                <div v-if="!parseFlightData(modifyRequestData?.messages)?.length" class="text-center text-gray-500 text-xs py-6">
                                    No messages yet.
                                </div>
                                <div v-else v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)" :key="index" class="flex" :class="msg.sender === 'admin' ? 'justify-start' : 'justify-end'">
                                    <div class="max-w-[85%] px-3 py-2 text-xs rounded-lg shadow-sm" :class="msg.sender === 'user' ? 'bg-primary/10 text-gray-800' : 'bg-white border border-gray-200 text-gray-800'">
                                        <p class="whitespace-pre-wrap">{{ msg?.message }}</p>
                                        <p class="text-[10px] text-gray-500 mt-1">{{ moment(msg?.created_at).format('DD MMM YYYY, HH:mm') }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="modifyRequestData?.status === 'pending'" class="p-3 border-t border-gray-200 bg-white rounded-b-lg flex-shrink-0">
                                <form @submit.prevent="sendReply">
                                    <textarea v-model="replyMessage" rows="2" placeholder="Type your reply..." class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none" required></textarea>
                                    <div class="mt-2 flex justify-end">
                                        <button type="submit" :disabled="replyLoading" class="px-3 py-1.5 text-xs font-medium text-white bg-primary hover:bg-primary/90 rounded-md disabled:opacity-60 flex items-center gap-1.5">
                                            <span v-if="replyLoading" class="animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></span>
                                            Send
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Request Dialog -->
            <div v-if="modifyDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4 print:hidden" @click.self="closeDialog">
                <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl" @click.stop>
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Modify Request</h2>
                    <form @submit.prevent="submitModifyRequest" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reason for modification</label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" v-model="selectedReason" value="refund" class="h-3.5 w-3.5 text-primary focus:ring-primary border-gray-300" />
                                    <span class="ml-2 text-sm text-gray-700">Refund</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" v-model="selectedReason" value="other" class="h-3.5 w-3.5 text-primary focus:ring-primary border-gray-300" />
                                    <span class="ml-2 text-sm text-gray-700">Other</span>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Description <span class="text-red-500">*</span></label>
                            <textarea id="message" v-model="message" rows="4" placeholder="Please explain the changes you need..." class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none" required></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-3">
                            <button type="button" @click="closeDialog" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">Cancel</button>
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary">Submit Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Base styles */
.ticket-sheet {
    margin: 0 auto;
}

.ticket-card {
    width: 100%;
    page-break-inside: avoid;
    break-inside: avoid;
}

.ticket-four-col-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
}

.ticket-four-col-table th,
.ticket-four-col-table td {
    vertical-align: top;
    word-break: break-word;
}

.ticket-col-1 {
    width: 32%;
}

.ticket-col-2 {
    width: 18%;
}

.ticket-col-3 {
    width: 25%;
}

.ticket-col-4 {
    width: 25%;
}

/* Ensure primary backgrounds have white text */
.bg-primary,
.bg-primary\/90,
button.bg-primary\/90,
.relative.rounded-md.bg-primary {
    color: white !important;
}

.bg-primary *,
.bg-primary\/90 * {
    color: white !important;
}

/* Override any black text on primary backgrounds */
.bg-primary .text-gray-900,
.bg-primary\/90 .text-gray-900,
.relative.rounded-md.bg-primary .text-gray-900 {
    color: white !important;
}

/* PDF Export Mode Styles */
.pdf-export-mode {
    background: white !important;
    font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    width: 100% !important;
    max-width: 800px !important;
    margin: 0 auto !important;
}

.pdf-export-mode .ticket-section {
    padding: 2pt 6pt !important;
}

.pdf-export-mode .ticket-top {
    padding-top: 0.5rem !important;
}

.pdf-export-mode .overflow-x-auto {
    overflow: visible !important;
}

.pdf-export-mode .ticket-four-col-table {
    min-width: 0 !important;
    table-layout: auto !important;
    width: 100% !important;
    border-collapse: collapse !important;
}

.pdf-export-mode .ticket-four-col-table th,
.pdf-export-mode .ticket-four-col-table td {
    padding: 4pt 3pt !important;
    font-size: 8.5pt !important;
    line-height: 1.1 !important;
    border: 0.5pt solid #d1d5db !important;
    vertical-align: middle !important;
}

.pdf-export-mode thead th {
    font-weight: 700 !important;
    text-transform: uppercase !important;
    color: #9b201a !important;
}

.pdf-export-mode .ticket-card {
    box-shadow: none !important;
    border: 1pt solid #d1d5db !important;
    margin-bottom: 0 !important;
    page-break-inside: avoid;
    break-inside: avoid;
}

.pdf-export-mode .print\:bg-primary {
    background-color: #9b201a !important;
    color: white !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.pdf-export-mode .print\:bg-\[\#f0f2f6\] {
    background-color: #f0f2f6 !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.pdf-export-mode .print\:hidden {
    display: none !important;
}

.pdf-export-mode .mt-4, .pdf-export-mode .mt-5, .pdf-export-mode .mb-4, .pdf-export-mode .mb-5 {
    margin-top: 2pt !important;
    margin-bottom: 2pt !important;
}

.pdf-export-mode .p-5, .pdf-export-mode .p-4 {
    padding: 2pt !important;
}

/* custom scrollbar for mobile */
.custom-scrollbar::-webkit-scrollbar {
    height: 2px;
    width: 2px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}


.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #9ca3af #e5e7eb;
}

/* For Firefox to make it even thinner, use this hack */
@-moz-document url-prefix() {
    .custom-scrollbar {
        scrollbar-width: auto;
        scrollbar-color: #9ca3af #e5e7eb;
    }
}


/* Print styles */
@media print {
    @page {
        size: A4 portrait;
        margin: 6mm;
    }

    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Hide all elements except print container */
    body * {
        visibility: hidden !important;
    }

    #print-container,
    #print-container * {
        visibility: visible !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    #print-container {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }

    #print-section {
        width: 100% !important;
        max-width: 100% !important;
        background: white !important;
        transform-origin: top left;
        zoom: 0.92;
    }

    .ticket-sheet {
        max-width: 100% !important;
    }

    .ticket-section {
        padding: 0.25rem 0.3rem !important;
    }

    /* Hide interactive elements */
    button,
    .fixed,
    .sticky,
    .print\:hidden {
        display: none !important;
    }

    /* Ensure proper page breaks - content fits on one page */
    .print\:break-inside-avoid,
    .ticket-card {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Force content to stay on one page */
    html, body {
        height: auto !important;
        overflow: visible !important;
    }

    #print-container,
    #print-section,
    .ticket-card {
        page-break-after: avoid !important;
        page-break-before: avoid !important;
        margin-bottom: 0 !important;
    }

    /* Font consistency */
    #print-section, #print-section * {
        font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    }

    /* Keep original browser colors and avoid print utility color swaps */
    .print\:bg-white {
        background-color: #ffffff !important;
    }

    .print\:bg-primary {
        background-color: #9b201a !important; /* Force primary color (red) */
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print\:bg-\[\#f0f2f6\] {
        background-color: #f0f2f6 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Fix table alignment and borders */
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        table-layout: auto !important;
        margin: 0 !important;
    }

    th, td {
        border: 0.5pt solid #d1d5db !important;
        padding: 4pt 3pt !important;
        font-size: 8.5pt !important;
        line-height: 1.1 !important;
        vertical-align: middle !important;
    }

    thead th {
        font-weight: 700 !important;
        text-transform: uppercase !important;
        color: #9b201a !important;
    }

    /* Compact sections for A4 fit */
    #print-section {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        zoom: 0.92;
    }

    .ticket-section {
        padding: 2pt 6pt !important;
    }

    .mt-4, .mt-5, .mb-4, .mb-5 {
        margin-top: 2pt !important;
        margin-bottom: 2pt !important;
    }

    .p-5, .p-4 {
        padding: 2pt !important;
    }

    .ticket-card {
        border: 1pt solid #d1d5db !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Remove redundant borders in print */
    .print\:border-none {
        border: none !important;
    }

    /* Ensure specific borders don't repeat weirdly */
    .overflow-hidden, .border {
        border-width: 0.5pt !important;
    }

    .p-5, .p-4, .p-6 {
        padding: 1pt !important;
    }

    .space-y-3, .space-y-2, .space-y-4 {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    .space-y-3 > *, .space-y-2 > *, .space-y-4 > * {
        margin-top: 1pt !important;
        margin-bottom: 0 !important;
    }

    .print\:break-inside-avoid {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* Prevent any stray elements from creating extra pages */
    * {
        max-height: 100000px;
    }

    /* Hide redundant elements */
    .print\:hidden {
        display: none !important;
    }
}
</style>
