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
const ticketNumber = ref([

]);
const selectedIndex = ref();

const pnrDetails = computed(() => store.getters["flight/pnrData"]);
const booking = ref(null);
const flightData = ref(null);
const custEmail = ref(null);
const sooperResponse = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref();
const isEmailDialogOpen = ref(false);
const ticketNo = ref("");
const airLinePnr = ref("");
const selectedFares = ref();

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

function sendEmail() {

    //console.log("email", custEmail?.value);
    store.dispatch("flight/" + SEND_EMAIL, {
        email: custEmail?.value ? custEmail?.value : bookingDetails?.value?.[0]?.main_email,
        booking_id: bookingDetails.value?.[0]?.flight_id,
        booking_source: route?.query?.booking_source



    }
    );
    isEmailDialogOpen.value = false;
    custEmail.value = null;
}
// Send reply function
function sendReply(newStatus = "pending") {
    if (!replyMessage.value.trim()) return;

    replyLoading.value = true;

    // Create new admin message object
    const adminMessage = {
        req_id: modifyRequestData.value.id,
        sender: "admin",
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
function fetchModifyRequestData() {
    const modify_request_id = route.query.modify_request_id;
    if (modify_request_id) {
        store.dispatch("modifyRequest/" + FETCH_MODIFY_REQUEST_DATA, {
            modify_request_id: modify_request_id,
        });
    }
}


function openChargesDialog() {
    isChargesOpen.value = true;
}

async function saveCharges() {
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
        // First charge dispatch
        store.dispatch("user/" + SAVE_AGENT_CHARGES, {
            amount: Number(charges.value),
            date: chargesDate.value,
            description: chargesDec.value,
            chargeType: 'charge',
            user_id: route.query.agent_id,
        });

        // Then refund dispatch
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
            booking_source: route.query.booking_source,
        });

        // Reset values
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
            //console.log("No pnr_response found in bookingDetails");
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
        //console.log(sooperResponseString);
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
const pnrLive = computed(() =>
    pnrDetails.value?.bookingId
        ? pnrDetails.value.bookingId
        : booking.value?.pnr || '-'
);

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
        await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { flight_provider: route.query.flight_provider, pnr: bookingDetails?.value?.[0]?.itinerary_ref?? route.query.pnr });
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

            //booking_uuid: pnrData.value?.data?.uuid ?? "null",
            //billable_price: pnrData.value?.data?.billable_price ?? "null",
            //currency: pnrData.value?.data?.currency?.code ?? "null",
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
        isLoading.value = false;
        fetchBookingDetails();
    }
};

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
        // amount: parseFloat(pnrData?.value?.data?.billable_price || 0),
    });

    // Close dialog after successful cancellation
    showDialog.value = false;
    fetchBookingDetails();
}
// function approveAction() {
//     store.dispatch(`flight/${APPROVE_BOOKING}`, {
//         airline_pnr: airLinePnr.value,
//         ticket_number: ticketNo.value,
//         booking_id: booking_id,
//         status: "ticketed",
//     });
//     closeDialog();
// }

async function approveAction() {
    // if (!airLinePnr.value || !ticketNo.value) {
    //     error.value = "Please provide both Airline PNR and Ticket Number.";
    //     return;
    // }

    isApproving.value = true;
    error.value = '';
    try {
        await store.dispatch(`flight/${APPROVE_BOOKING}`, {
            airline_pnr: airLinePnr.value,
            ticket_number: ticketNo.value,
            booking_id: booking_id,
            status: "ticketed",
        });
        // Refetch data to update ticket number and status
        await Promise.all([
            store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id }),
            store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { pnr: pnr })
        ]);
        successMessage.value = "Booking approved successfully!";
        // Clear inputs
        airLinePnr.value = '';
        ticketNo.value = '';
        // Close dialog after 2 seconds
        // setTimeout(() => {
        //     closeDialog();
        //     successMessage.value = '';
        // }, 4000);
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

function calculatePassengerFinalFare(passenger, flightIndex,) {
    const flight =
        flightData?.value?.original?.leg?.flights?.[flightIndex] ??
        flightData?.value?.leg?.flights?.[flightIndex];

    const fare = flight?.fares?.[0];
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
    const legs = pnrData?.value?.data?.providers?.[0]?.legs || [];
    if (!legs.length) return 0;

    const flights = flightData?.value?.original?.leg?.flights ?? flightData?.value?.leg?.flights;
    if (!flights.length) return 0;

    // ✅ Collect unique carriers (avoid duplicate margin application)
    const uniqueCarriers = {};
    flights.forEach((flight) => {
        const carrier = flight?.marketing_carrier?.name;
        if (carrier && !uniqueCarriers[carrier]) {
            uniqueCarriers[carrier] = flight;
        }
    });

    // Calculate system fare per passenger per unique carrier
    const systemFare = legs.reduce((legSum, leg) => {
        return (
            legSum +
            (leg.passengers || []).reduce((passengerSum, passenger) => {
                const passengerBase = parseFloat(passenger?.base_price || 0);

                const passengerFare = Object.values(uniqueCarriers).reduce(
                    (carrierSum, flight) => {
                        const fare = flight?.fares?.[0];
                        if (!fare) return carrierSum;

                        return (
                            carrierSum +
                            calculateFinalPrice(
                                passengerBase,
                                fare?.margin_amount,
                                fare?.margin_type,
                                fare?.amount_type || 0
                            )
                        );
                    },
                    0
                );

                return passengerSum + passengerFare;
            }, 0)
        );
    }, 0);

    // Extra charges (fees, taxes, surcharges, etc.)
    const passengerCharges = legs.reduce((total, leg) => {
        return (
            total +
            (leg.passengers || []).reduce((sum, passenger) => {
                return (
                    sum +
                    parseFloat(passenger?.fee || 0) +
                    parseFloat(passenger?.taxes || 0) +
                    parseFloat(passenger?.surcharge || 0) +
                    parseFloat(passenger?.service_charges || 0) +
                    parseFloat(passenger?.ancillaries_charges || 0)
                );
            }, 0)
        );
    }, 0);

    // Agent margin × (legs × passengers count)
    const passengerCount = legs[0].passengers.length;
    const agentMargin =
        parseFloat(agentData?.value?.agent_data?.margin_amount || 0) *
        parseFloat(legs.length) *
        parseFloat(passengerCount || 0);
    const agentDiscount =
        parseFloat(agentData?.value?.agent_data?.agent_discount || 0) *
        parseFloat(legs.length) *
        parseFloat(passengerCount || 0);
    return systemFare + passengerCharges + agentMargin - agentDiscount;
}
function openTicketNumberDialog(index) {
    selectedIndex.value = index;
    showTicketDialog.value = !showTicketDialog.value;
}
function assignTicketNumber() {
    const passenger =
        bookingDetails.value?.[0]?.pessangers?.[selectedIndex.value];
    if (passenger) {
        passenger.ticketNumber = ticketNumber.value;
    }

store.dispatch("traveller/" +  ASSIGN_TICKET_NUMBER, {
        bookingId: bookingDetails.value?.[0]?.id,
        pessangers: bookingDetails.value?.[0]?.pessangers,
    });
    ticketNumber.value = "";
    showTicketDialog.value = false;
}
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
    const a4Width = 210;
    const a4Height = 297;
    const contentWidth = element.scrollWidth;
    const contentHeight = element.scrollHeight;
    const contentWidthMM = contentWidth * 0.264583;
    const contentHeightMM = contentHeight * 0.264583;
    const scaleWidth = a4Width / contentWidthMM;
    const scaleHeight = a4Height / contentHeightMM;
    const scale = Math.min(scaleWidth, scaleHeight);

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

    html2pdf()
        .from(element)
        .set(options)
        .save()
        .then(() => {
            element.style.display = "";
            element.style.visibility = "";
            element.style.opacity = "";
        });
};
const toggleChatOpen = () => {
    isChatOpen.value = !isChatOpen.value;
}
function toggleModifyRequestStatus(status){
    store.dispatch("modifyRequest/" + UPDATE_STATUS, {
        modify_request_id: modifyRequestData.value.id,
        status: status,
    }).then(()=>{
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
const passengerCount = parseInt(bookingDetails?.value?.[0].pessangers.length || 1);
const agentAmount = parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0) || 0;
const agentDiscount = parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0) || 0;
const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0) || 0;
const airportMargin = parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0) || 0;
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

    const total = billable + marginPerFlight;
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


const openDialog = () => {
    showDialog.value = true;
};

const closeDialog = () => {
    showDialog.value = false;
};

function toggleDetailedInfo() {
    isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
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
            <div v-else class="  min-h-screen bg-gray-100">
                <div v-if="route?.query?.booking_source == 1">
                    <div v-for="booking in bookingDetails" :key="booking.id"
                        class="bg-white p-4 gap-2 flex justify-end mx-auto" id="topBar">
                        <button @click="printBooking"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <PrinterIcon class="h-5 w-5 inline-block mr-1" />
                            Print
                        </button>
                        <a target="blank" :href="bookingDetails[0]?.booking_invoice?.invoice_url">
                            <button
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <EyeIcon class="h-5 w-5 inline-block mr-1" />
                                View Invoice
                            </button>
                        </a>
                        <!-- <button
                        @click="emailBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                    >
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button> -->
                        <button @click="toggleDetailedInfo"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <EyeOff class="h-5 w-5 inline-block mr-1" />
                            {{
                                isDetailsInfoVisible
                                    ? "Hide Details"
                                    : "Show Details"
                            }}
                        </button>
                        <button @click="downloadPDF"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                            <Download class="h-5 w-5 inline-block mr-1" />
                            Download PDF
                        </button>
                        <Dialog v-if = "flightData?.provider?.contentSource === 'NDC'" class="" :open="isChargesOpen" @update:open="isChargesOpen = $event">
                            <button @click="openChargesDialog()"
                                :hidden="['canceled', 'booked', 'voided'].includes(booking?.status)"
                                :disabled="['canceled', 'voided'].includes(booking?.status)"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 disabled:cursor-not-allowed">
                                Void Booking</button>
                            <DialogContent class="sm:max-w-[425px]">
                                <DialogHeader>
                                    <DialogTitle class="text-2xl">Add Charges</DialogTitle>
                                </DialogHeader>
                                <div v-if="validationErrors.length > 0">
                                    <ul
                                        class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                        <li v-for="error in validationErrors" :key="error.id">
                                            {{ error }}
                                        </li>
                                    </ul>
                                </div>
                                <form @submit.prevent="saveCharges">
                                    <div class="">
                                        <RadioGroup class="flex" default-value="comfortable" :orientation="'horizontal'"
                                            v-model="chargeType">
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
                                    <div></div>
                                    <div class="mb-3">
                                        <Label for="amount">Total Amount:</Label>
                                        <Input class="" type="number" v-model="totalTicketPrice" readonly id="charges"
                                            placeholder="Amount in PKR" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Amount in PKR</Label>
                                        <Input class="" type="number" v-model="charges" id="charges"
                                            placeholder="Amount in PKR" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="amount">Date</Label>
                                        <Input class="" type="date" v-model="chargesDate" id="chargesDate" />
                                    </div>
                                    <div class="mb-3">
                                        <Label for="Description">Description</Label>
                                        <Textarea class="" type="text" v-model="chargesDec" id="chargesDec"
                                            placeholder="Description" />
                                    </div>
                                    <Button type="submit" class="float-right">
                                        Save
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <button
                            :disabled="['canceled', 'issued', 'ticketed', 'requested', 'voided'].includes(booking?.status)"
                            @click="openDialog"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 disabled:cursor-not-allowed">
                            Approve
                        </button>
                        <!-- Dialog -->

                        <button
                            :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status.toLowerCase())"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 disabled:cursor-not-allowed">
                            Reject Booking
                        </button>
                        <button
                            :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status.toLowerCase())"
                            @click="isDialogOpen = true"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 disabled:cursor-not-allowed">
                            Cancel Booking
                        </button>

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
<button @click="toggleChatOpen"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <ChatBubbleIcon class="h-5 w-5  inline-block mr-1" />
                        Chat
                    </button>
                    </div>
                    <div class="flex gap-4">
                    <!-- Print Section - Main Content -->
                    <div :class="isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full'" id="print-section">
                        <div v-for="booking in bookingDetails" :key="booking.id"
                            class="bg-white rounded-lg shadow-sm overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                            <!-- Header - Professional Design -->
                            <div
                                class="p-6 text-white print:text-black print:bg-white print:border-b print:border-gray-300">
                                <div class="flex justify-between items-center">
                                    <!-- Logo -->
                                    <div class="w-1/4">
                                        <img class="h-16 w-auto print:h-12" src="/public/assets/logo.png" alt="Logo" />
                                    </div>

                                    <!-- Agency Info -->
                                    <div class="w-2/4 text-black text-center border-l-2 border-black/30 pl-4 print:border-gray-400">
            <h1 class="text-2xl font-extrabold tracking-tight print:text-gray-900 mb-1">E-TICKET RECEIPT</h1>
            
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
                
                <div v-if="booking.status === 'booked'" class="text-sm opacity-90 print:text-gray-700 mt-1">
                    <span class="font-medium">Expiry Time: </span>
<span
    class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full
           bg-amber-100 text-amber-700 border border-amber-300
           "
  >
    {{ getRemainingTime(booking.expiry_time) }}
  </span>                </div>
            </div>
        </div>

                                    <!-- Booking Reference -->
                                    <div
                                        class="w-1/4 text-black text-right border-l-2 border-black/30 pl-4 print:border-gray-400">
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Booking
                                                Ref:</span> {{ booking.id
                                                }}</p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Status:</span>
                                            <span class="capitalize font-semibold print:text-gray-900">{{ booking.status
                                            }}</span>
                                        </p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">GDS PNR:</span>
                                            {{
                                                pnrDetails?.Response?.Data?.pnrDetail?.PNRN ?? pnrDetails?.bookingId ??
                                                booking?.itinerary_ref }}</p>
                                        <p class="text-sm print:text-gray-700"><span class="font-medium">Airline
                                                PNR:</span> {{
                                                    pnrData?.ReservationResponse?.Reservation?.Receipt?.[1]?.Confirmation?.Locator?.value
                                                }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Flight Information -->
                            <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center print:text-gray-900">
                                    <PlaneIcon class="h-5 w-5 mr-2 text-primary print:text-gray-700" />
                                    FLIGHT INFORMATION
                                </h2>

                                <div v-if="parseFlightData(booking.flight_data)?.original?.leg || parseFlightData(booking.flight_data)?.leg"
                                    class="space-y-4">
                                   <div v-for="(flight, flightIndex) in (parseFlightData(booking.flight_data)?.original?.leg?.flights ?? parseFlightData(booking.flight_data)?.leg?.flights)"
    :key="flightIndex" class="space-y-3 print:break-inside-avoid">
    <!-- Main Flight Card -->
    <div class="border border-gray-200 rounded-lg p-6 hover:shadow-sm transition-shadow print:border-gray-400 print:shadow-none print:mb-6 print:p-8 print:text-base">
        
        <!-- Segments Container -->
        <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex" class="bg-white overflow-hidden">
            
            <!-- Layover Info -->
            <div v-if="segment?.layover_time" class="flex justify-center mb-4">
  <div class="bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium px-4 py-1.5 rounded-full shadow-sm">
    Layover Time: 
    {{
      Math.floor(segment.layover_time / 60) + "h " +
      (segment.layover_time % 60) + "m"
    }}
  </div>
</div>

            <!-- Segment Details -->
            <div class="p-2 sm:p-3 lg:p-6">
                <div class="flex items-center justify-center gap-6">
                    <div class="grid grid-cols-7 gap-4 items-center w-full">
                        
                        <!-- Airline Logo -->
                        <div class="w-20 flex-shrink-0 print:w-24">
                            <img :src="segment?.operating_carrier?.logo" 
                                 class="h-12 w-auto object-contain print:h-14" 
                                 :alt="segment?.operating_carrier?.name" />
                            <div class="text-xs text-gray-500 mt-1">
                                {{ segment?.operating_carrier?.iata }}-{{ segment?.flight_number ?? "N/A" }}
                            </div>
                        </div>

                        <!-- FROM (Right aligned content) -->
                        <div class="col-span-2 text-right print:text-right">
                            <p class="text-2xl font-bold text-gray-900 print:text-3xl">
                                {{ segment?.from?.iata }}
                            </p>
                            <p class="text-lg text-gray-600 print:text-base">
                                {{ segment?.from?.city?.name }}
                            </p>
                            <p class="text-lg font-semibold text-gray-600 print:text-base">
                                {{ segment?.from?.name }}
                            </p>

                            <p class="text-base font-medium mt-3 text-gray-700 print:text-lg">
                                {{ formatDate(segment?.departure_at) }}
                            </p>
                            <p class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                {{ moment.parseZone(segment?.departure_at).format("HH:mm") }}
                            </p>

                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                Terminal: {{ segment?.from_terminal?.Gate ?? "N/A" }}
                            </p>
                        </div>
                        <!-- Arrow with Duration -->
                        <div class="col-span-1 flex flex-col items-center">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center print:bg-transparent print:border print:border-gray-400">
                                <MoveRight class="h-5 w-5 text-gray-600 print:text-black" />
                            </div>

                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                {{ moment.utc(moment.duration(segment.flight_time, "minutes").asMilliseconds()).format("HH:mm") }}
                            </p>
                        </div>

                        <!-- TO (Left aligned content) -->
                        <div class="col-span-2 text-left print:text-left">
                            <p class="text-2xl font-bold text-gray-900 print:text-3xl">
                                {{ segment?.to?.iata }}
                            </p>

                            <p class="text-lg text-gray-600 print:text-base">
                                {{ segment?.to?.city?.name }}
                            </p>
                            <p class="text-lg font-semibold text-gray-600 print:text-base">
                                {{ segment?.to?.name }}
                            </p>

                            <p class="text-base font-medium mt-3 text-gray-700 print:text-lg">
                                {{ formatDate(segment?.arrival_at) }}
                            </p>

                            <p class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                {{ moment.parseZone(segment?.arrival_at).format("HH:mm") }}
                            </p>

                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                Terminal: {{ segment?.to_terminal?.Gate ?? "N/A" }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aircraft Info (only show for first segment or per segment basis) -->
                <div class="mt-5 pt-4 border-t border-gray-200 text-sm text-gray-600 flex items-center gap-6 print:text-base print:border-gray-400">
                    <span>
                        <span class="font-semibold">Aircraft:</span>
                        {{ segment?.aircraft || "N/A" }}
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

    <!-- Baggage Section - Half Width Rounded Table (unchanged) -->
    <div class="border border-gray-200 rounded-lg p-4 print:border-gray-300 print:break-inside-avoid">
        <div class="space-y-6">
            <div class="flex justify-start items-center divide-x divide-gray-300 print:divide-gray-400">
                <div v-for="(flightSegment, sIndex) in flight?.segments" :key="sIndex" class="mb-4 p-4">
                    <div class="text-xs font-medium text-gray-700 mb-2 print:text-gray-800">
                        {{ flightSegment.from.iata }} → {{ flightSegment.to.iata }}
                    </div>
                    <div class="print:w-full">
                        <table class="w-full text-xs border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
                            <thead>
                                <tr class="border-b border-gray-300 print:border-gray-400 print:bg-white">
                                    <th class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-white">
                                        Pax Type
                                    </th>
                                    <th class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                        Check-In Baggage
                                    </th>
                                    <th class="py-2 px-3 text-left font-bold text-gray-800 print:text-gray-900 print:bg-transparent">
                                        Cabin Baggage
                                    </th>
                                </tr>
                            </thead>
                            <pre class="hidden">{{ flight.fares }}</pre>
                            <tbody class="divide-y divide-gray-200 print:divide-gray-400">
                                <template v-for="(fare, fIndex) in flight?.fares" :key="fIndex">
                                    <template v-if="parseFlightData(bookingDetails?.[0]?.fare_reference)?.includes(fare?.ref_id)">
                                        <tr v-for="(travelerType, tIndex) in [...new Set(fare.baggage_policies.map(bp => bp.traveler_type))]"
                                            :key="tIndex" class="hover:bg-gray-50 print:bg-transparent">
                                            <td class="py-2 px-3 uppercase font-medium print:text-gray-800">
                                                {{ travelerType }}
                                            </td>
                                            <td class="py-2 px-3 uppercase print:text-gray-800">
                                                {{fare.baggage_policies.find(bp => bp.traveler_type === travelerType && bp.type === 'checked')?.description || 'N/A'}}
                                            </td>
                                            <td class="py-2 px-3 uppercase print:text-gray-800">
                                                {{(fare.baggage_policies || []).find(bp => bp.traveler_type === travelerType && (bp.type === 'carry' || bp.type === 'carry-on'))?.description || 'N/A' }}
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
                            <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center print:text-gray-900">
                                    <UserIcon class="h-5 w-5 mr-2 text-primary print:text-gray-700" />
                                    PASSENGER & TICKET DETAILS
                                </h2>

                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full text-sm border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
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
                                                    {{ Array.isArray(pnrDetails?.ReservationResponse?.Reservation?.Receipt?.[2]?.Document) &&
                                                       pnrDetails?.ReservationResponse?.Reservation?.Receipt?.[2]?.Document[index] ?
                                                        pnrDetails?.ReservationResponse?.Reservation?.Receipt?.[2]?.Document[index].Number : "N/A" }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Fare Breakdown - Using existing fare logic -->
                            <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid"
                                v-if="isDetailsInfoVisible">
                                <h2 class="text-lg font-bold text-gray-800 mb-2 print:text-gray-900">
                                    FARE BREAKDOWN
                                </h2>
                                <div>
                                    <div class="overflow-x-auto">
                                        <table
                                            class="w-full text-xs border border-gray-300 rounded-lg overflow-hidden print:border-gray-400 print:rounded-none">
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
                                                <template
                                                    v-for="(flight, index) in parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights"
                                                    :key="index">
                                                    <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                                        const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                                            ? parseFlightData(bookingDetails[0]?.fare_reference)
                                                            : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                                        return fareRefs.includes(f.ref_id);
                                                    })" :key="fareIndex" class="hover:bg-gray-50 print:bg-transparent">
                                                        <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                            {{ flight.segments?.[0]?.from?.iata }} → {{
                                                                flight.segments?.[flight.segments.length - 1]?.to?.iata
                                                            }}
                                                        </td>
                                                        <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                            {{ formatAmount(calculateFinalPrice(fare?.base_price,
                                                                fare?.margin_amount,
                                                                fare?.margin_type, fare?.amount_type) +
                                                                parseFloat((agentAmount
                                                                    * passengerCount)) - parseFloat((agentDiscount
                                                                        * passengerCount)) + margin) }}
                                                        </td>
                                                        <td class="py-1.5 px-2 uppercase print:text-gray-800">
                                                            {{ formatAmount(calculateTaxes(fare)) }}
                                                        </td>
                                                        <td class="py-1.5 px-2 uppercase font-bold print:text-gray-900">
                                                            {{
                                                                formatAmount(calculateTotalFare(fare))
                                                            }}
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                            <tfoot class=" border-gray-400 bg-gray-50 print:bg-transparent">
                    <tr>
                        <td colspan="3" class="py-3 px-2 text-right font-bold text-gray-900  text-sm">
                            Total Amount
                        </td>
                        <td class="py-3 px-2 font-bold text-primary text-sm">
                            {{ formatAmount(calculateGrandTotal()) }}
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
                                    For assistance, contact us at {{ agentData?.agent_data?.mobile || '+92 3111711123'
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
                <div v-if="modifyRequestData && isChatOpen" class="lg:col-span-1 lg:w-5/12 order-first lg:order-last">
                    <div class="bg-white border border-gray-300 shadow-sm flex flex-col 
                    h-screen lg:h-1/2 
                    max-h-screen lg:max-h-1/2-screen 
                    overflow-hidden">

                        <!-- Header -->
                        <div class="p-5 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                            <h3 class="text-lg font-semibold text-gray-800">Modify Request Conversation</h3>
                        </div>

                        <!-- Status -->
                        <div class="p-5 border-b border-gray-200 flex-shrink-0">
                            <div class="flex justify-between items-center text-sm">
                                <div class = "flex items-center gap-4">                                
                                    <span class="font-medium text-gray-600">Status</span>
                                 <label class="inline-flex items-center cursor-pointer">
                                    <input type="checkbox" :checked="modifyRequestData?.status === 'approved'"
                                        @change="toggleModifyRequestStatus(modifyRequestData?.status === 'approved' ? 'pending' : 'approved')"
                                        class="sr-only peer" />
                                    <div
                                        class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                                    </div>
                                </label>
                                </div>

                                <span :class="{
                                    'px-3 py-1.5 text-xs font-bold rounded-full uppercase tracking-wider': true,
                                    'bg-yellow-100 text-yellow-800': modifyRequestData?.status === 'pending',
                                    'bg-green-100 text-green-800': modifyRequestData?.status === 'approved',
                                    'bg-red-100 text-red-800': modifyRequestData?.status === 'rejected',
                                    'bg-blue-100 text-blue-800': modifyRequestData?.status === 'processing'
                                }">
                                    {{ modifyRequestData?.status || 'Pending' }}
                                </span>
                            </div>
                        </div>

                        <!-- Reason -->
                        <div class="p-5 border-b border-gray-200 text-sm flex-shrink-0">
                            <span class="font-medium text-gray-600">Reason:</span>
                            <p class="mt-1 font-medium text-gray-900">
                                {{
                                    modifyRequestData?.reason === 'change_scope' ? 'Change Scope / Requirements' :
                                        modifyRequestData?.reason === 'extend_deadline' ? 'Extend Deadline' :
                                            modifyRequestData?.reason === 'refund' ? 'Request Refund' :
                                                modifyRequestData?.reason === 'cancel' ? 'Cancel Booking' :
                                                    modifyRequestData?.reason || 'Not specified'
                                }}
                            </p>
                        </div>

                        <!-- Scrollable Messages Area -->
                        <div class="flex-1 overflow-y-auto p-5 space-y-4 bg-gray-50/30 min-h-0">
                            <div v-if="!parseFlightData(modifyRequestData?.messages)?.length"
                                class="text-center text-gray-500 text-sm py-12">
                                No messages yet.
                            </div>
                            <div v-else v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)"
                                :key="index" class="flex"
                                :class="msg.sender === 'user' ? 'justify-start' : 'justify-end'">
                                <div class="max-w-[85%] px-4 py-3 text-sm leading-relaxed shadow-sm border rounded-lg"
                                    :class="msg.sender === 'admin'
                                        ? 'bg-blue-50 border-blue-200 text-blue-900'
                                        : 'bg-white border-gray-200 text-gray-800'">
                                    <p class="whitespace-pre-wrap">{{ msg?.message }}</p>
                                    <p class="text-xs text-gray-500 mt-2 text-right">
                                        {{ msg?.time || moment(msg?.created_at).format('DD MMM YYYY, HH:mm') }}
                                    </p>
                                    <p class="text-xs text-gray-500  text-right" v-if="msg.sender === 'user'">{{  modifyRequestData?.user?.email}}</p>
                                    <p class="text-xs text-gray-500  text-right" v-else-if="msg.sender === 'admin'">{{  user?.email}}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Reply Box - Sticky at Bottom -->
                        <div v-if="modifyRequestData?.status === 'pending'"
                            class="border-t border-gray-200 p-5 bg-white flex-shrink-0">
                            <form @submit.prevent="sendReply">
                                <textarea v-model="replyMessage" rows="3" placeholder="Type your reply..."
                                    class="w-full px-4 py-3 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none"
                                    required>
                                </textarea>
                                <div class="mt-3 flex justify-end">
                                    <button type="submit" :disabled="replyLoading"
                                        class="px-5 py-2.5 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-md disabled:opacity-60 flex items-center gap-2 transition">
                                        <svg v-if="replyLoading" class="animate-spin h-4 w-4" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4" fill="none" />
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8v8H4z" />
                                        </svg>
                                        Send Message
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Closed State -->
                        <div v-else
                            class="p-5 text-center text-sm text-gray-500 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                            This request has been processed.
                        </div>
                    </div>
                </div>
                

            </div>
        </div>
        <!-- <div v-if="showDialog" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-50">
            <div class="w-full max-w-md p-6 bg-white rounded-lg shadow-lg">
                <h2 class="text-lg font-bold text-gray-800">
                    Approve Action
                </h2>
                <p class="mt-2 text-sm text-gray-600">
                    Please enter the required input to approve.
                </p>

                
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
        </div> -->

        <div v-if="showDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
            @click.self="showDialog = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                    <button @click="showDialog = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
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
                    <button @click="showDialog = false"
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
