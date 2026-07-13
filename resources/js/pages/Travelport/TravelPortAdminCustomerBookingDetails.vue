<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff, PrinterIcon, MailIcon, Download, EyeIcon } from "lucide-vue-next";
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
    APPROVE_BOOKING,
    SAVE_AGENT_CHARGES,
    VOID_BOOKING,
    SEND_EMAIL,
    SEND_REPLY,
    FETCH_MODIFY_REQUEST_DATA,
    ASSIGN_TICKET_NUMBER,
    UPDATE_STATUS
} from "@/services/store/actions.type";

import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";
import Input from "@/components/common/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { Textarea } from "@/components/ui/textarea";
import { ChatBubbleIcon } from "@radix-icons/vue";

const store = useStore();
const route = useRoute();
const authStore = useAuthStore();

// Loading states for individual API calls
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const error = ref(null);

// Combined loading state
const isLoading = computed(() => isBookingDetailsLoading.value || isPnrDetailsLoading.value || isAgentLoading.value || store.getters["flight/isLoading"]);

// Dialog states
const showDialog = ref(false);
const isDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const successMessage = ref('');
const isApproving = ref(false);

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agent_id = route.query.agent_id;
const agentData = computed(() => store.getters["user/agentData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const booking_id = route.query.booking_id;
const pnr = route.query.pnr;
const bookingId = ref("");
const pnrData = ref(null);
const isChatOpen = ref(false);
const showTicketDialog = ref(false);
const ticketNumber = ref([]);
const selectedIndex = ref();

const pnrDetails = computed(() => store.getters["flight/pnrData"]);
const booking = ref(null);
const flightData = ref(null);
const custEmail = ref(null);
const sooperResponse = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref(0);
const isEmailDialogOpen = ref(false);
const ticketNo = ref("");
const airLinePnr = ref("");
const selectedFares = ref([]);

const isChargesOpen = ref(false);
const charges = ref('');
const chargesDate = ref('');
const chargesDec = ref('');
const chargeType = ref('charge');
const validationErrors = ref([]);
const router = useRouter();
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

const getVoidRemainingTime = (issuanceDate) => {
    if (!issuanceDate) return 'Unavailable';

    const issuedAt = moment(issuanceDate);
    if (!issuedAt.isValid()) return 'Unavailable';

    const cutoff = issuedAt.clone().add(1, 'day').startOf('day');
    const diff = cutoff.valueOf() - now.value;
    if (diff <= 0) return 'Void period expired';

    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    return `${hours}h ${minutes}m ${seconds.toString().padStart(2, '0')}s`;
};

const isVoidDisabled = (booking) => {
    const status = booking?.status?.toLowerCase();
    if (!['ticketed', 'issued'].includes(status) || !booking?.issuance_date) return true;

    const issuedAt = moment(booking.issuance_date);
    if (!issuedAt.isValid()) return true;

    return moment(now.value).isSameOrAfter(issuedAt.add(1, 'day').startOf('day'));
};

function sendEmail() {
    const recipientEmail = custEmail?.value?.trim() || bookingDetails?.value?.[0]?.main_email;
    store.dispatch("flight/" + SEND_EMAIL, {
        email: recipientEmail,
        booking_id: bookingDetails.value?.[0]?.id,
        booking_status: bookingDetails?.value?.[0]?.status,
        booking_source: route?.query?.booking_source,
        flight_provider: route?.query?.flight_provider,
    });
    isEmailDialogOpen.value = false;
    custEmail.value = null;
}

function sendReply(newStatus = "pending") {
    if (!replyMessage.value.trim()) return;
    replyLoading.value = true;
    const adminMessage = {
        req_id: modifyRequestData.value.id,
        sender: "admin",
        sender_id: user_id.value,
        message: replyMessage.value
    };
    store.dispatch("modifyRequest/" + SEND_REPLY, adminMessage).then(() => {
        fetchModifyRequestData();
    });
    replyMessage.value = "";
    replyLoading.value = false;
}

function fetchModifyRequestData() {
    const modify_request_id = route.query.modify_request_id;
    if (modify_request_id) {
        store.dispatch("modifyRequest/" + FETCH_MODIFY_REQUEST_DATA, {
            modify_request_id: modify_request_id,
        });
    }
}

function openChargesDialog() {
    if (isVoidDisabled(bookingDetails?.value?.[0])) {
        validationErrors.value = ['The void period for this booking has expired.'];
        return;
    }

    isChargesOpen.value = true;
}

async function saveCharges() {
    if (isVoidDisabled(bookingDetails?.value?.[0])) {
        validationErrors.value = ['The void period for this booking has expired.'];
        isChargesOpen.value = false;
        return;
    }

    const errors = [];
    if (!charges.value || charges.value <= 0) {
        errors.push("Amount is required and must be greater than 0.");
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
    try {
        store.dispatch("user/" + SAVE_AGENT_CHARGES, {
            amount: Number(charges.value),
            date: chargesDate.value,
            description: chargesDec.value,
            chargeType: 'charge',
            user_id: route.query.agent_id,
        });
        store.dispatch("user/" + SAVE_AGENT_CHARGES, {
            amount: Number(totalTicketPrice.value) - Number(charges.value),
            date: chargesDate.value,
            description: chargesDec.value,
            chargeType: 'refund',
            user_id: route.query.agent_id,
        });
        await store.dispatch(`flight/${VOID_BOOKING}`, {
            pnr: pnr,
            booking_uuid: pnrData.value?.ReservationResponse?.Reservation?.Identifier?.value ?? "null",
            flight_provider: route.query.flight_provider,
            pnr: route.query.pnr,
            bookingId: bookingDetails.value[0].id,
            booking_status: "voided",
            booking_source: bookingDetails.value[0].content_source,
        });
        charges.value = '';
        chargesDate.value = '';
        chargesDec.value = '';
        chargeType.value = 'charge';
        isChargesOpen.value = false;
        fetchAgent();
        fetchBookingDetails();
    } catch (error) {
        validationErrors.value = ['Something went wrong. Please try again.'];
        console.error(error);
    }
}

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

async function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isBookingDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id });
        parsePnrResponse();
        parseSooperResponse();
        flightData.value = parseFlightData(bookingDetails?.value[0]?.flight_data);
    } catch (err) {
        error.value = "Failed to fetch booking details.";
    } finally {
        isBookingDetailsLoading.value = false;
    }
}

async function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        isPnrDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { flight_provider: route.query.flight_provider, pnr: bookingDetails?.value?.[0]?.itinerary_ref ?? route.query.pnr });
    } catch (err) {
        error.value = "Failed to fetch PNR details.";
    } finally {
        isPnrDetailsLoading.value = false;
    }
}

async function fetchAllData() {
    try {
        await Promise.all([fetchBookingDetails(), fetchPnrDetails()]);
        if (route.query.modify_request_id) {
            fetchModifyRequestData();
        }
    } catch (err) {
        error.value = "Failed to load data.";
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

function confirmBooking() {
    error.value = '';
    isLoading.value = true;
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    if (agentData.value?.balance < bookingDetails.value[0]?.amount) {
        error.value = "Customer balance is not enough to confirm the booking.";
        return;
    }
    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: bookingDetails?.value?.[0]?.content_source === 'GDS' ? route.query.pnr : pnrData?.value?.ReservationResponse?.Reservation?.Receipt?.[2]?.Confirmation?.Locator?.value,
        pnrData: pnrData.value,
        bookingId: bookingDetails.value[0].id,
        booking_status: "ticketed",
        flight_provider: route.query.flight_provider,
        booking_source: route.query.booking_source,
    });
    showDialog.value = false;
    fetchBookingDetails();
}

async function approveAction() {
    isApproving.value = true;
    error.value = '';
    try {
        await store.dispatch(`flight/${APPROVE_BOOKING}`, {
            airline_pnr: airLinePnr.value,
            ticket_number: ticketNo.value,
            booking_id: booking_id,
            status: "ticketed",
        });
        await Promise.all([
            store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id }),
            store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { pnr: pnr })
        ]);
        successMessage.value = "Booking approved successfully!";
        airLinePnr.value = '';
        ticketNo.value = '';
    } catch (err) {
        error.value = err.message || "Failed to approve booking.";
    } finally {
        isApproving.value = false;
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
    const printContent = document.getElementById("print-section").innerHTML;
    const printContainer = document.createElement("div");
    printContainer.id = "print-container";
    printContainer.style.display = "none";
    printContainer.innerHTML = printContent;
    document.body.appendChild(printContainer);
    printContainer.style.display = "block";
    window.print();
    printContainer.style.display = "none";
    document.body.removeChild(printContainer);
};

const downloadPDF = () => {
    const element = document.getElementById("print-section");
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
    html2pdf().from(element).set(options).save();
};

const toggleChatOpen = () => {
    isChatOpen.value = !isChatOpen.value;
}

function toggleModifyRequestStatus(status){
    store.dispatch("modifyRequest/" + UPDATE_STATUS, {
        modify_request_id: modifyRequestData.value.id,
        status: status,
    }).then(() => {
        fetchModifyRequestData();
    });
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

const agentAmount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0) || 0);
const agentDiscount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0) || 0);
const margin = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0) || 0);
const airportMargin = computed(() => parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0) || 0);
const savedMarginTotal = computed(() => (agentAmount.value + margin.value + airportMargin.value - agentDiscount.value) || 0);

const marginPerFlight = computed(() => {
    const flightCount = flightData?.value?.leg?.flights?.length || 0;
    if (!flightCount) return 0;
    return (savedMarginTotal.value / flightCount) || 0;
});

function calculateTotalFare(fare) {
    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);
    const billable = parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));
    const total = billable + parseFloat(marginPerFlight.value || 0);
    return total;
}

function calculateGrandTotal() {
    let total = 0;
    flightData?.value?.leg?.flights?.forEach((flight, index) => {
        flight?.fares?.forEach(fare => {
            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare)
            }
        });
    });
    totalTicketPrice.value = total;
    return total;
}

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

const openDialog = () => {
    showDialog.value = true;
};

const closeDialog = () => {
    showDialog.value = false;
};

function toggleDetailedInfo() {
    isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
}

function getPassengerTicketNumber(index) {
    const reservation = pnrDetails?.value?.ReservationResponse?.Reservation;
    const receipts = Array.isArray(reservation?.Receipt)
        ? reservation.Receipt
        : reservation?.Receipt
          ? [reservation.Receipt]
          : [];

    if (!receipts.length) return "Not Ticketed";

    const travelerId =
        reservation?.Traveler?.[index]?.id ||
        reservation?.Traveler?.[index]?.TravelerRef ||
        null;

    const ticketDocs = receipts.flatMap((receipt) => {
        const isPaymentReceipt =
            receipt?.["@type"] === "ReceiptPayment" ||
            Array.isArray(receipt?.Document) ||
            !!receipt?.Document;

        if (!isPaymentReceipt) return [];

        const docs = Array.isArray(receipt?.Document)
            ? receipt.Document
            : receipt?.Document
              ? [receipt.Document]
              : [];

        return docs.filter((doc) => doc && (doc?.["@type"] === "DocumentTicket" || doc?.Number));
    });

    if (!ticketDocs.length) return "Not Ticketed";

    if (travelerId) {
        const byTraveler = ticketDocs.find(
            (doc) =>
                doc?.TravelerIdentifierRef?.id === travelerId ||
                doc?.TravelerIdentifierRef?.TravelerRef === travelerId,
        );
        if (byTraveler?.Number) return byTraveler.Number;
    }

    return ticketDocs[index]?.Number || ticketDocs[0]?.Number || "Not Ticketed";
}

function getBaggageForSegment(booking, flight, segment, passengerType) {
    const selectedFare = (flight?.fares || []).find((fare) => selectedFares.value.includes(fare?.ref_id));
    const baggagePolicies = selectedFare?.baggage_policies || [];
    const paxCode = String(passengerType || "").toLowerCase();
    const matched = baggagePolicies.find((bp) => {
        const bpType = String(bp?.traveler_type || "").toLowerCase();
        return bpType === paxCode && bp?.type === "checked";
    });
    return matched?.description || "N/A";
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

function getSegmentPassengerRowsTP(booking, flight, segment, segmentIndex) {
    const passengers = booking?.pessangers || [];
    return passengers.map((traveller, index) => {
        return {
            key: `${segmentIndex}-${index}`,
            passengerName: `${traveller?.title || ""} ${traveller?.first_name || ""} ${traveller?.last_name || ""}`.trim() || "N/A",
            passengerType: traveller?.type || "Adult",
            ticketNo: getPassengerTicketNumber(index),
            frequentFlyerNo: traveller?.frequent_flyer_number || traveller?.frequent_flyer_no || "N/A",
            mealType: "N/A",
            baggage: getBaggageForSegment(booking, flight, segment, traveller?.type),
            excessBaggage: "N/A",
            seatNo: "N/A",
        };
    });
}

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
    } else {
        fetchAgent();
    }
    fetchAllData();
});
</script>

<template>
    <section>
        <div class="min-h-screen bg-gray-100">
            <!-- Loading Spinner -->
            <div v-if="isLoading" class="fixed inset-0 bg-gray-100 bg-opacity-75 flex items-center justify-center z-50">
                <Spinner />
            </div>
            <div v-else class="min-h-screen bg-gray-100 p-4">
                <div v-if="route?.query?.booking_source == 1">
                    <div v-for="booking in bookingDetails" :key="booking.id"
                        class="bg-white rounded-lg shadow-sm p-3 py-4 mb-4 flex flex-wrap gap-2 justify-end print:hidden" id="topBar">
                        <div v-if="booking?.status?.toLowerCase() === 'booked'"
                            class="mr-auto inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                            <ClockIcon class="h-4 w-4" />
                            Expires in: {{ getRemainingTime(booking.expiry_time) }}
                        </div>
                        <!-- <div v-else-if="['ticketed', 'issued'].includes(booking?.status?.toLowerCase())"
                            :class="[
                                'mr-auto inline-flex items-center gap-2 rounded-md border px-3 py-1.5 text-xs font-semibold',
                                isVoidDisabled(booking)
                                    ? 'border-red-300 bg-red-50 text-red-700'
                                    : 'border-blue-300 bg-blue-50 text-blue-700'
                            ]">
                            <ClockIcon class="h-4 w-4" />
                            Void available for: {{ getVoidRemainingTime(booking.issuance_date) }}
                        </div> -->
                        <button @click="printBooking"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5">
                            <PrinterIcon class="h-3.5 w-3.5" />
                            Print
                        </button>
                        <a target="blank" :href="bookingDetails[0]?.booking_invoice?.invoice_url">
                            <button
                                class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-1.5">
                                <EyeIcon class="h-3.5 w-3.5" />
                                View Invoice
                            </button>
                        </a>
                        <button @click="toggleDetailedInfo"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5">
                            <EyeOff class="h-3.5 w-3.5" />
                            {{ isDetailsInfoVisible ? "Hide Details" : "Show Details" }}
                        </button>
                        <button @click="isEmailDialogOpen = true"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center gap-1.5">
                            <MailIcon class="h-3.5 w-3.5" />
                            Email
                        </button>
                        <!-- <button @click="downloadPDF"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-1.5">
                            <Download class="h-3.5 w-3.5" />
                            Download PDF
                        </button> -->
                        <Dialog  class="" :open="isChargesOpen" @update:open="isChargesOpen = $event">
                            <button @click="openChargesDialog()"
                                :hidden="['canceled', 'booked', 'voided'].includes(booking?.status)"
                                :disabled="isVoidDisabled(booking)"
                                :title="isVoidDisabled(booking) ? 'Void is only available until midnight on the issuance date' : 'Void booking'"
                                class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                                Void Booking
                            </button>
                            <DialogContent class="sm:max-w-[425px]">
                                <DialogHeader>
                                    <DialogTitle class="text-xl">Add Charges</DialogTitle>
                                </DialogHeader>
                                <div v-if="validationErrors.length > 0">
                                    <ul class="bg-red-100 p-3 rounded-md border border-destructive list-disc list-inside text-destructive text-sm">
                                        <li v-for="error in validationErrors" :key="error.id">{{ error }}</li>
                                    </ul>
                                </div>
                                <form @submit.prevent="saveCharges">
                                    <div class="mb-3">
                                        <RadioGroup class="flex" default-value="comfortable" :orientation="'horizontal'" v-model="chargeType">
                                            <div class="flex items-center space-x-2">
                                                <RadioGroupItem id="charge" value="charge" />
                                                <Label for="charge">Charge</Label>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <RadioGroupItem id="refund" value="refund" />
                                                <Label for="refund">Refund</Label>
                                            </div>
                                        </RadioGroup>
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Total Amount:</Label>
                                        <Input type="number" v-model="totalTicketPrice" readonly id="charges" placeholder="Amount in PKR" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Amount in PKR</Label>
                                        <Input type="number" v-model="charges" id="charges" placeholder="Amount in PKR" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Date</Label>
                                        <Input type="date" v-model="chargesDate" id="chargesDate" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="Description">Description</Label>
                                        <Textarea type="text" v-model="chargesDec" id="chargesDec" placeholder="Description" />
                                    </div>
                                    <Button type="submit" class="float-right">Save</Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <button
                            :disabled="['canceled', 'issued', 'ticketed', 'requested', 'voided'].includes(booking?.status)"
                            @click="openDialog"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                            Approve
                        </button>
                        <button
                            :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                            @click="isDialogOpen = true"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-primary/90 rounded-md hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5">
                            Cancel Booking
                        </button>
                        <button @click="toggleChatOpen"
                            class="px-3 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-1.5">
                            <ChatBubbleIcon class="h-3.5 w-3.5" />
                            Chat
                        </button>
                    </div>

                    <!-- Cancel Dialog -->
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

                    <div class="flex gap-4">
                        <!-- Print Section - Main Content -->
                        <div :class="isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full'" id="print-section">
                            <div v-for="booking in bookingDetails" :key="booking.id"
                                class="bg-white rounded-lg shadow-sm overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                                <!-- Header - Compact Design -->
                                <div class="px-5 pt-5 print:pt-3 print:px-4">
                                    <div class="relative mb-4 flex items-center justify-center print:mb-3">
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

                                    <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 print:mt-3 print:border-gray-300 print:rounded-md">
                                        <div class="grid grid-cols-1 text-xs md:grid-cols-[1.3fr_1fr_1fr_1fr] print:grid-cols-[1.3fr_1fr_1fr_1fr]">
                                            <div class="bg-white px-3 py-2 print:px-2 print:py-1.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[9px]">Prepared For</p>
                                                <p class="text-sm font-bold uppercase leading-4 text-primary print:text-xs">
                                                    {{ booking?.pessangers?.[0]?.title }} {{ booking?.pessangers?.[0]?.first_name }} {{ booking?.pessangers?.[0]?.last_name }}
                                                    <span class="normal-case text-xs font-semibold print:text-[10px]">({{ booking?.pessangers?.[0]?.type || "Adult" }})</span>
                                                </p>
                                            </div>
                                            <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[9px]">PNR</p>
                                                <p class="text-sm font-semibold text-gray-900 print:text-xs">
                                                    {{ pnrDetails?.Response?.Data?.pnrDetail?.PNRN ?? pnrDetails?.bookingId ?? booking?.itinerary_ref }}
                                                </p>
                                            </div>
                                            <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[9px]">Airline PNR</p>
                                                <p class="text-sm font-semibold text-gray-900 print:text-xs">{{ pnrData?.ReservationResponse?.Reservation?.Receipt?.[1]?.Confirmation?.Locator?.value || "N/A" }}</p>
                                            </div>
                                            <div class="border-t border-gray-200 bg-[#f0f2f6] px-3 py-2 md:border-l md:border-t-0 print:px-2 print:py-1.5">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 print:text-[9px]">Booking Date</p>
                                                <p class="text-sm font-semibold text-gray-900 print:text-xs">{{ formatShortDate(booking?.created_at) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Flight Information - OneApi Style -->
                                <div class="space-y-2 p-5 pt-3 print:p-3 print:space-y-1.5">
                                    <template v-for="(itinerary, itineraryIndex) in getItineraryBlocks(booking)" :key="itineraryIndex">
                                        <!-- Itinerary header -->
                                        <div class="overflow-hidden rounded-t-sm print:rounded-none">
                                            <div class="grid grid-cols-1 bg-primary text-white md:grid-cols-[1.2fr_1fr_1fr] print:grid-cols-[1.2fr_1fr_1fr]">
                                                <div class="px-3 py-2 text-sm font-semibold border-b border-white/20 md:border-b-0 md:border-r md:border-white/20 print:px-2 print:py-1.5 print:text-xs">
                                                    {{ itinerary?.from?.city?.name || itinerary?.from?.iata }} → {{ itinerary?.to?.city?.name || itinerary?.to?.iata }}
                                                </div>
                                                <div class="px-3 py-2 border-b border-white/20 md:border-b-0 md:border-r md:border-white/20 print:px-2 print:py-1.5">
                                                    <p class="text-[10px] uppercase tracking-wide text-white/80 print:text-[8px]">Departure Date</p>
                                                    <p class="text-xs font-medium print:text-[10px]">{{ formatLongDate(itinerary?.departureAt) }}</p>
                                                </div>
                                                <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                    <p class="text-[10px] uppercase tracking-wide text-white/80 print:text-[8px]">Arrival Date</p>
                                                    <p class="text-xs font-medium print:text-[10px]">{{ formatLongDate(itinerary?.arrivalAt) }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <template v-for="(segment, segmentIndex) in itinerary?.segments" :key="`${itineraryIndex}-${segmentIndex}`">
                                            <div v-if="segmentIndex > 0 && getLayoverLabel(segment)" class="flex items-center justify-center py-0.5 print:py-0 mt-2">
                                                <div class="flex items-center gap-2 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-medium text-primary shadow-sm print:px-2 print:py-0.5 print:text-[10px]">
                                                    <span class="inline-flex h-2 w-2 rounded-full bg-primary print:h-1.5 print:w-1.5"></span>
                                                    <span>Stopover</span>
                                                    <span class="text-primary/70">Layover {{ getLayoverLabel(segment) }}</span>
                                                </div>
                                            </div>

                                            <!-- Segment info panel -->
                                            <div class="overflow-hidden border border-gray-200 bg-white print:border-gray-300 mt-2">
                                                <div class="grid grid-cols-1 md:grid-cols-[280px_1fr_1fr] print:grid-cols-[240px_1fr_1fr]">
                                                    <div class="border-b border-gray-200 bg-[#f8f8f8] md:border-b-0 md:border-r print:border-b print:border-gray-300">
                                                        <div class="flex items-center gap-2 px-3 py-3 print:px-2 print:py-2">
                                                            <img v-if="segment?.operating_carrier?.logo" :src="segment?.operating_carrier?.logo" :alt="segment?.operating_carrier?.name" class="h-8 w-auto object-contain print:h-6 print:max-w-[50px]" />
                                                            <div class="min-w-0 flex-1">
                                                                <p class="text-sm font-bold text-gray-900 break-words print:text-xs">
                                                                    {{ segment?.operating_carrier?.name || "Airline" }}
                                                                    <span class="font-medium">{{ segment?.operating_carrier?.iata }} {{ segment?.flight_number }}</span>
                                                                </p>
                                                                <p class="text-xs text-gray-700 print:text-[10px]">{{ segment?.aircraft || "Aircraft N/A" }}</p>
                                                            </div>
                                                        </div>
                                                        <div class="grid grid-cols-2 border-t border-gray-200 print:border-gray-300">
                                                            <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                                <p class="text-xs text-gray-600 print:text-[10px]">Status</p>
                                                                <p class="mt-1 inline-flex rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary print:mt-0.5 print:px-1.5 print:py-0 print:text-[10px]">
                                                                    {{ getDisplayStatus(booking?.status) }}
                                                                </p>
                                                            </div>
                                                            <div class="border-l border-gray-200 px-3 py-2 print:px-2 print:py-1.5">
                                                                <p class="text-xs text-gray-600 print:text-[10px]">Class</p>
                                                                <p class="mt-1 text-xs font-semibold text-gray-900 print:mt-0.5 print:text-[10px]">
                                                                    {{ getCabinClass(itinerary?.flight) }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="border-b border-gray-200 md:border-b-0 md:border-r print:border-b print:border-gray-300">
                                                        <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                            <p class="text-xl font-bold leading-tight text-gray-900 print:text-lg">{{ segment?.from?.city?.name || segment?.from?.iata }}</p>
                                                            <p class="mt-0.5 text-sm text-gray-500 break-words print:text-xs print:mt-0">[{{ segment?.from?.iata }}] {{ segment?.from?.name }}</p>
                                                        </div>
                                                        <div class="border-t border-gray-200 px-3 py-2 print:px-2 print:py-1.5">
                                                            <p class="text-xl font-bold text-gray-900 print:text-lg">{{ formatTicketTime(segment?.departure_at) }}</p>
                                                            <p class="text-sm text-gray-500 print:text-xs">{{ formatShortDate(segment?.departure_at) }}</p>
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <div class="px-3 py-2 print:px-2 print:py-1.5">
                                                            <p class="text-xl font-bold leading-tight text-gray-900 print:text-lg">{{ segment?.to?.city?.name || segment?.to?.iata }}</p>
                                                            <p class="mt-0.5 text-sm text-gray-500 break-words print:text-xs print:mt-0">[{{ segment?.to?.iata }}] {{ segment?.to?.name }}</p>
                                                        </div>
                                                        <div class="border-t border-gray-200 px-3 py-2 print:px-2 print:py-1.5">
                                                            <p class="text-xl font-bold text-gray-900 print:text-lg">{{ formatTicketTime(segment?.arrival_at) }}</p>
                                                            <p class="text-sm text-gray-500 print:text-xs">{{ formatShortDate(segment?.arrival_at) }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Inline passenger table per segment -->
                                            <div class="custom-scrollbar overflow-x-auto border border-gray-200 print:border-gray-300 print:overflow-visible mt-2 mb-3">
                                                <table class="w-full min-w-[860px] text-left print:min-w-0 print:table-auto">
                                                    <thead class="bg-[#f0f2f6]">
                                                        <tr class="text-primary">
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Name</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">E-Ticket</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Frequent Flyer No.</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Meal Type</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Baggage</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Excess Baggage</th>
                                                            <th class="px-3 py-2 text-sm font-bold print:px-2 print:py-1.5 print:text-xs">Seat No.</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="row in getSegmentPassengerRowsTP(booking, itinerary.flight, segment, segmentIndex)"
                                                            :key="row.key"
                                                            class="border-t border-gray-200 text-sm text-gray-900 print:border-gray-300 print:text-xs"
                                                        >
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">
                                                                <span class="block uppercase">{{ row.passengerName || "N/A" }}</span>
                                                                <span class="block text-xs text-gray-600 print:text-[10px]">({{ row.passengerType || "Adult" }})</span>
                                                            </td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.ticketNo }}</td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.frequentFlyerNo }}</td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.mealType }}</td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.baggage }}</td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.excessBaggage }}</td>
                                                            <td class="px-3 py-2 align-top print:px-2 print:py-1.5">{{ row.seatNo }}</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </template>
                                    </template>
                                </div>

                                <!-- Fare Breakdown - Compact -->
                                <div class="px-5 pb-5 print:px-3 print:pb-3" v-if="isDetailsInfoVisible">
                                    <div class="overflow-hidden border border-gray-200 print:border-gray-300">
                                        <div class="bg-[#f0f2f6] px-3 py-2 print:px-2 print:py-1.5">
                                            <h2 class="text-base font-bold uppercase tracking-wide text-primary print:text-sm">Fare Breakdown</h2>
                                        </div>
                                        <div class="custom-scrollbar overflow-x-auto print:overflow-visible">
                                            <table class="w-full min-w-[600px] text-left print:min-w-0 print:table-auto">
                                                <thead class="border-b border-gray-200 bg-white print:border-gray-300">
                                                    <tr class="text-sm font-semibold text-gray-600 print:text-xs">
                                                        <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Sector</th>
                                                        <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Subtotal</th>
                                                        <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Taxes + Fees</th>
                                                        <th class="px-3 py-2 whitespace-nowrap print:px-2 print:py-1.5">Grand Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody v-if="pnrDetails?.fares?.length">
                                                    <tr class="border-b border-gray-200 text-sm text-gray-900 print:border-gray-300 print:text-xs">
                                                        <td class="px-3 py-2 print:px-2 print:py-1.5">
                                                            {{ getBookingSegments(booking)?.[0]?.from?.iata }} → {{ getBookingSegments(booking)?.[getBookingSegments(booking).length - 1]?.to?.iata }}
                                                        </td>
                                                        <td class="px-3 py-2 print:px-2 print:py-1.5">
                                                            {{ formatAmount(calculateFinalPrice(pnrDetails?.fares?.[0]?.totals?.subtotal || 0, 0, null, 0) + marginPerFlight) }}
                                                         </td>
                                                        <td class="px-3 py-2 print:px-2 print:py-1.5">
                                                            {{ formatAmount((parseFloat(pnrDetails?.fares?.[0]?.totals?.taxes || 0) + parseFloat(pnrDetails?.fares?.[0]?.totals?.fees || 0))) }}
                                                         </td>
                                                        <td class="px-3 py-2 font-bold print:px-2 print:py-1.5">
                                                            {{ formatAmount(parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0) + marginPerFlight) }}
                                                         </td>
                                                     </tr>
                                                </tbody>
                                                <tbody v-else>
                                                    <template v-for="(flight, index) in getBookingFlights(booking)" :key="index">
                                                        <tr v-for="(fare, fareIndex) in flight.fares.filter((f) => selectedFares.includes(f.ref_id))"
                                                            :key="fareIndex"
                                                            class="border-b border-gray-200 text-sm text-gray-900 print:border-gray-300 print:text-xs">
                                                            <td class="px-3 py-2 print:px-2 print:py-1.5">
                                                                {{ flight.segments?.[0]?.from?.iata }} → {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                                             </td>
                                                            <td class="px-3 py-2 print:px-2 print:py-1.5">{{ formatAmount(calculateFinalPrice(fare?.base_price, fare?.margin_amount, fare?.margin_type, fare?.amount_type) + marginPerFlight) }}</td>
                                                            <td class="px-3 py-2 print:px-2 print:py-1.5">{{ formatAmount(calculateTaxes(fare)) }}</td>
                                                            <td class="px-3 py-2 font-bold print:px-2 print:py-1.5">{{ formatAmount(calculateTotalFare(fare)) }}</td>
                                                         </tr>
                                                    </template>
                                                </tbody>
                                                <tfoot class="bg-[#faf7f9] print:bg-gray-50">
                                                    <tr class="text-sm font-bold text-gray-900 print:text-xs">
                                                        <td colspan="3" class="px-3 py-2 text-right print:px-2 print:py-1.5">Total Amount</td>
                                                        <td class="px-3 py-2 text-primary print:px-2 print:py-1.5">{{ formatAmount(calculateGrandTotal()) }}</td>
                                                     </tr>
                                                </tfoot>
                                             </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Important Information -->
                                <div class="ticket-section p-5 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid print:p-3">
                                <h2 class="text-base font-bold text-gray-800 mb-3 print:text-gray-900 print:text-sm print:mb-1.5">Important Information</h2>

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
                                            <p>Passengers may be asked for a valid photo ID at check-in. If the ticket name and ID do not match, travel can be denied and re-issuance may be required as per airline fare rules. Children should travel with a parent/legal guardian, and unaccompanied minors may have additional airline requirements.</p>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold text-gray-900 mb-0.5 print:mb-0">Cancellation Fees</h3>
                                            <p>Fees may apply for cancellations, changes, or no-shows, according to airline policy.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="ticket-section p-5 text-center bg-gray-50 border-t border-gray-200 print:bg-white print:border-gray-300 print:p-1 print:break-inside-avoid">
                                <p class="text-sm text-gray-700 mb-0.5 font-medium print:text-gray-800 print:text-[9px]">
                                    Thank you for choosing {{ agentData?.agent_data?.company_name || 'Jetze Travels' }}
                                </p>
                                <p class="text-[11px] text-gray-500 print:text-gray-600 print:text-[8px]">
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
                                        <div class="flex items-center gap-4">
                                            <label class="inline-flex items-center cursor-pointer">
                                                <input type="checkbox" :checked="modifyRequestData?.status === 'approved'"
                                                    @change="toggleModifyRequestStatus(modifyRequestData?.status === 'approved' ? 'pending' : 'approved')"
                                                    class="sr-only peer" />
                                                <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                            </label>
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
                                    <div v-else v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)" :key="index" class="flex" :class="msg.sender === 'user' ? 'justify-start' : 'justify-end'">
                                        <div class="max-w-[85%] px-3 py-2 text-xs rounded-lg shadow-sm" :class="msg.sender === 'admin' ? 'bg-primary/10 text-gray-800' : 'bg-white border border-gray-200 text-gray-800'">
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

                <!-- Confirm Dialog -->
                <div v-if="showDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="showDialog = false">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-5 transform transition-all">
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="text-base font-medium text-gray-900">Confirm Booking</h3>
                            <button @click="showDialog = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-2">
                            <p class="text-xs text-gray-500">Are you sure you want to confirm this booking?</p>
                            <div v-if="error" class="mt-2 p-2 bg-red-100 text-red-700 rounded-md text-xs">{{ error }}</div>
                        </div>
                        <div class="mt-5 flex justify-end space-x-2">
                            <button @click="showDialog = false" class="px-3 py-1.5 bg-white border border-gray-300 rounded-md text-xs font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button @click="confirmBooking" class="px-3 py-1.5 bg-primary border border-transparent rounded-md text-xs font-medium text-white hover:bg-green-700">Confirm Booking</button>
                        </div>
                    </div>
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
