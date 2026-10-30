
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
import { cn, formatAmountWithCurrency, calculateLayoverDetails, calculateFinalPrice } from "@/lib/utils";

import {
    FETCH_BOOKING_DATA,
    FETCH_AGENT_DATA,
    FETCH_BOOKING_DETAILS,
    FETCH_PNR_DETAILS,
    CANCEL_BOOKING,
    CONFIRM_BOOKING,
    FETCH_AGENT_LEDGER,
    VOID_BOOKING,
} from "@/services/store/actions.type";
import moment from "moment";
import ATETicket from "@/components/AT/ATETicket.vue";
import Badge from "@/components/ui/badge/Badge.vue";
import { SEND_EMAIL } from "../../services/store/actions.type";
import Label from "@/components/common/Label.vue";
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";
import { Textarea } from "@/components/ui/textarea";


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
const isLoading = computed(() => isBookingDetailsLoading.value );


const user = computed(() => authStore.user);
const isAdmin = computed(() => user.value?.role === 'admin');
const agentData = computed(() => store.getters["user/agentData"]);
// const offlineBookings = computed(() => store.getters["flight/bookingData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

/** Read the permanent AED accounting amount locked from the checkout quote. */
const lockedAdminBookingMoney = computed(() => {
    const booking = bookingDetails.value?.[0];
    const snapshot = booking?.price_snapshot;
    const amount = snapshot?.aed_amount ?? booking?.aed_amount;

    if (amount === null || amount === undefined) {
        return null;
    }

    return {
        amount: Number(amount),
        currency: "AED",
    };
});

/** Read the locked customer-selected/display amount saved with the booking. */
const lockedCustomerBookingMoney = computed(() => {
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

/** Read commercial adjustments from the permanent booking snapshot for admin audit only. */
const lockedCommercialAdjustments = computed(() => {
    const adjustments = bookingDetails.value?.[0]?.price_snapshot?.adjustments_snapshot;

    return Array.isArray(adjustments)
        ? [...adjustments].sort((left, right) => Number(left.sequence || 0) - Number(right.sequence || 0))
        : [];
});

const lockedCommercialReconciliation = computed(
    () => bookingDetails.value?.[0]?.price_snapshot?.commercial_reconciliation ?? null,
);

/** Format one locked AED commercial adjustment with its original sign. */
function formatLockedCommercialAdjustment(adjustment) {
    const amount = Number(adjustment?.aed_amount);
    const sign = amount < 0 ? '-' : '+';

    return `${sign}${formatAmountWithCurrency(Math.abs(amount || 0), 'AED')}`;
}

/** Explain the exact locked rule formula without recalculating it in the browser. */
function describeLockedCommercialAdjustment(adjustment) {
    const value = Number(adjustment?.configured_value || 0);
    if (adjustment?.calculation_type === 'percentage') {
        return `${value}% of fare`;
    }

    const passengers = Number(adjustment?.passenger_count || 1);
    const segments = Number(adjustment?.segment_count || 1);
    return `${formatAmountWithCurrency(value, 'AED')} × ${segments} segment(s) × ${passengers} passenger(s)`;
}

/** Format the currency amount selected by the customer at booking time. */
function formatCustomerSelectedBookingAmount() {
    const money = lockedCustomerBookingMoney.value;

    if (!money || Number.isNaN(money.amount)) {
        return 'Price unavailable';
    }

    return formatAmountWithCurrency(money.amount, money.currency);
}

/** Format the booking's locked total in AED for admin pages. */
function formatLockedBookingAmount() {
    if (!lockedAdminBookingMoney.value || Number.isNaN(lockedAdminBookingMoney.value.amount)) {
        return 'Price unavailable';
    }

    return formatAmountWithCurrency(
        lockedAdminBookingMoney.value.amount,
        lockedAdminBookingMoney.value.currency,
    );
}

/** Format an individual selected fare in AED for admin pages. */
function formatSelectedFareMoney(fare) {
    const money = fare?.base_money?.total_price ?? fare?.base_money;

    if (money?.currency && Number.isFinite(Number(money.amount))) {
        return formatAmountWithCurrency(money.amount, money.currency);
    }

    return formatLockedBookingAmount();
}

/** Format selected fare taxes in AED for admin pages. */
function formatSelectedFareTaxesMoney(fare) {
    const money = fare?.passenger_fares?.[0]?.base_money?.taxes;

    if (money?.currency && Number.isFinite(Number(money.amount))) {
        return formatAmountWithCurrency(money.amount, money.currency);
    }

    return formatAmountWithCurrency(0, "AED");
}

/** Format PNR tax totals as AED on admin pages. */
function formatPnrTaxesAmount(amount) {
    return formatAmountWithCurrency(amount || 0, "AED");
}

/** Format add-ons in AED on admin pages. */
function formatBookingAddOnsAmount(amount) {
    if (Number(amount || 0) === 0) {
        return formatAmountWithCurrency(0, "AED");
    }

    return "Included in AED total";
}

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
const isChargesOpen = ref(false);
const charges = ref("");
const chargesDate = ref("");
const chargesDec = ref("");
const validationErrors = ref([]);
const passengerCount = ref();
const agentAmount = ref();
const agentDiscount = ref();
const margin = ref();

const pnrDetails = computed(() => store.getters["flight/pnrData"]);



const booking = computed(() => bookingDetails.value?.[0] ?? null);
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
const voidChargesTotalAmount = computed(() => lockedAdminBookingMoney.value?.amount ?? Number(totalTicketPrice.value || 0));
const voidRefundAmount = computed(() => {
    const total = Number(voidChargesTotalAmount.value || 0);
    const charge = Number(charges.value || 0);

    return Math.max(total - (Number.isFinite(charge) ? charge : 0), 0);
});


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

function openChargesDialog() {
    validationErrors.value = [];
    chargesDate.value ||= new Date().toISOString().slice(0, 10);
    isChargesOpen.value = true;
}

async function saveCharges() {
    const errors = [];
    const voidChargeAmount = Number(charges.value);
    const totalAmount = Number(voidChargesTotalAmount.value || 0);

    if (!Number.isFinite(voidChargeAmount) || voidChargeAmount < 0) {
        errors.push("Void charge must be AED 0 or greater.");
    }

    if (voidChargeAmount > totalAmount) {
        errors.push("Void charge cannot be greater than the locked booking total.");
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
    actionLoading.value = true;

    try {
        await store.dispatch(`flight/${VOID_BOOKING}`, {
            bookingId: bookingDetails.value?.[0]?.id,
            flight_provider: "at",
            void_charge_aed: voidChargeAmount,
            void_date: chargesDate.value,
            void_description: chargesDec.value,
        });

        charges.value = "";
        chargesDate.value = "";
        chargesDec.value = "";
        isChargesOpen.value = false;

        fetchAgentLedger();
        fetchBookingDetails();
    } catch (error) {
        validationErrors.value = ["Something went wrong. Please try again."];
        console.error(error);
    } finally {
        actionLoading.value = false;
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
    // Admins confirm operational bookings without using the customer's wallet.
    // Non-admin customers still require the balance check before confirmation.
    if (!isAdmin.value && agentLedger?.value.balance < totalTicketPrice?.value) {
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

async function cancelBooking() {
    if (actionLoading.value) return;

    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }

    error.value = '';
    actionLoading.value = true;

    try {
        await store.dispatch("flight/" + CANCEL_BOOKING, {
            pnr: pnr,
            booking_source: route.query.flight_provider,
            flight_source: parseFlightData(bookingDetails?.value?.[0]?.flight_data)?.provider?.source ?? null,
            bookingId: bookingDetails.value[0].id,
            orderId: parseFlightData(bookingDetails?.value?.[0]?.pnr_response)?.order?.id ?? null,
        });

        isDialogOpen.value = false;
        await fetchBookingDetails();
    } catch (err) {
        error.value = err?.message || 'Failed to cancel booking';
    } finally {
        actionLoading.value = false;
    }
}
async function confirmBooking() {
    if (actionLoading.value) return;

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
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || 'Failed to confirm booking';
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

onMounted(async () => {
    if (user.value == null) {
        await authStore.fetchUser();
    }
    fetchAgent();
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

        <div v-else class=" mx-auto min-h-screen bg-gray-100 p-4">
            <div v-if="route?.query?.booking_source == 1">
                <div v-for="booking in bookingDetails" :key="booking.id"
                    class="bg-white rounded-lg shadow-sm p-3 py-4 mb-4 flex flex-wrap gap-1 justify-end print:hidden"
                    id="topBar">
                    <button @click="printBooking"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                        <PrinterIcon class="h-4 w-4" />
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
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2">
                        <Download class="h-4 w-4" />
                        Download PDF
                    </button>
                    <button @click="toggleFareInfo"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2">
                        <EyeOff class="h-4 w-4" />
                        <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                        <span v-else>View Fare Details</span>
                    </button>

                    <!-- Void charges dialog -->
                    <Dialog :open="isChargesOpen" @update:open="isChargesOpen = $event">
                        <button
                            :hidden="['canceled', 'requested', 'booked', 'voided'].includes(booking?.status?.toLowerCase())"
                            :disabled="['canceled', 'requested', 'voided'].includes(booking?.status?.toLowerCase())"
                            @click="openChargesDialog"
                            class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                            Void Booking
                        </button>
                        <DialogContent class="sm:max-w-[425px]">
                            <DialogHeader>
                                <DialogTitle class="text-2xl">Void Booking</DialogTitle>
                            </DialogHeader>

                            <div v-if="validationErrors.length > 0">
                                <ul class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                    <li v-for="error in validationErrors" :key="error">
                                        {{ error }}
                                    </li>
                                </ul>
                            </div>

                            <form @submit.prevent="saveCharges">
                                <div class="mb-3">
                                    <Label for="totalCharges" title="Locked Booking Total (AED)" />
                                    <Input type="number" :model-value="voidChargesTotalAmount" readonly id="totalCharges"
                                        placeholder="Amount in AED" />
                                </div>
                                <div class="mb-3">
                                    <Label for="charges" title="Void Charges (AED)" />
                                    <Input type="number" min="0" step="0.01" v-model="charges" id="charges" placeholder="0.00" />
                                </div>
                                <div class="mb-3">
                                    <Label for="refundAmount" title="Net Wallet Refund (AED)" />
                                    <Input type="number" :model-value="voidRefundAmount" readonly id="refundAmount" />
                                </div>
                                <div class="mb-3">
                                    <Label for="chargesDate" title="Void Settlement Date" />
                                    <Input type="date" v-model="chargesDate" id="chargesDate" />
                                </div>
                                <div class="mb-3">
                                    <Label for="chargesDec" title="Void Reason / Notes" />
                                    <Textarea type="text" v-model="chargesDec" id="chargesDec" placeholder="Enter void reason or notes" />
                                </div>
                                <Button type="submit" class="float-right" :disabled="actionLoading">
                                    {{ actionLoading ? "Voiding..." : "Confirm Void" }}
                                </Button>
                            </form>
                        </DialogContent>
                    </Dialog>

                    <button @click="isDialogOpen = true"
                        :disabled="actionLoading || ['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center gap-2">
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
                                <button @click="cancelBooking" :disabled="actionLoading"
                                    class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    {{ actionLoading ? 'Cancelling...' : 'Confirm Cancellation' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <button
                        :disabled="actionLoading || ['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                        @click="handleConfirmDialogOpen"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 disabled:bg-gray-300 disabled:cursor-not-allowed flex items-center gap-2">
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
                                <button @click="confirmBooking" :disabled="actionLoading"
                                    class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    {{ actionLoading ? 'Confirming...' : 'Confirm Booking' }}
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
                        :agent="agentData?.agent_data" :pnr="atPnrReferences.crs"
                        :airline-pnr="atPnrReferences.airline" :pnr-details="pnrDetails"
                        :show-fare="isDetailsInfoVisible">
                        <template #fare-rows>
                            <tr v-if="pnrDetails?.fares?.length">
                                <td>
                                    {{ parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.[0]?.segments?.[0]?.from?.iata || "-" }}
                                    →
                                    {{ parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.[0]?.segments?.slice(-1)?.[0]?.to?.iata || "-" }}
                                </td>
                                <td>{{ formatLockedBookingAmount() }}</td>
                                <td>{{ formatPnrTaxesAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}</td>
                                <td>{{ formatLockedBookingAmount() }}</td>
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
                                    <td>{{ flight.segments?.[0]?.from?.iata }} → {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}</td>
                                    <td>{{ formatSelectedFareMoney(fare) }}</td>
                                    <td>{{ formatSelectedFareTaxesMoney(fare) }}</td>
                                    <td>{{ formatSelectedFareMoney(fare) }}</td>
                                </tr>
                            </template>
                            </template>
                        </template>
                        <template #fare-summary>
                            <div><span>Add-ons</span><b>{{ formatBookingAddOnsAmount(bookingDetails?.[0]?.add_ones_amount) }}</b></div>
                            <div v-for="adjustment in lockedCommercialAdjustments"
                                :key="`${adjustment.sequence}-${adjustment.type}-${adjustment.rule_id}-${adjustment.fare_reference}`">
                                <span>
                                    {{ adjustment.type === 'segment_margin' ? 'Segment Margin' : 'Promotion' }}
                                    <template v-if="adjustment.title">— {{ adjustment.title }}</template>
                                    <small>{{ describeLockedCommercialAdjustment(adjustment) }}</small>
                                </span>
                                <b>{{ formatLockedCommercialAdjustment(adjustment) }}</b>
                            </div>
                            <div v-if="lockedCommercialReconciliation">
                                <span>Pricing Audit</span>
                                <b :class="lockedCommercialReconciliation.is_reconciled ? 'et-text-ok' : 'et-text-bad'">
                                    {{ lockedCommercialReconciliation.is_reconciled ? 'Reconciled' : 'Review Required' }}
                                </b>
                            </div>
                            <div><span>Customer Selected Total</span><b>{{ formatCustomerSelectedBookingAmount() }}</b></div>
                            <div class="et-tot"><span>Total amount (AED)</span><b>{{ formatLockedBookingAmount() }}</b></div>
                        </template>
                    </ATETicket>
                </div>
            </div>
            
        </div>

    </section>
</template>

<style>
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
    body:has(> #print-container) > *:not(#print-container) {
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
    #print-container .text-xs { font-size: 7pt !important; }
    #print-container .text-sm { font-size: 8pt !important; }
    #print-container .text-base { font-size: 9pt !important; }
    #print-container .text-lg { font-size: 10.5pt !important; }
    #print-container .text-xl { font-size: 11.5pt !important; }
    #print-container .text-2xl { font-size: 13pt !important; }

    #print-container .p-6 { padding: 10px !important; }
    #print-container .p-4 { padding: 8px !important; }
    #print-container .p-3 { padding: 6px !important; }
    #print-container .px-6 { padding-left: 10px !important; padding-right: 10px !important; }
    #print-container .py-4 { padding-top: 6px !important; padding-bottom: 6px !important; }
    #print-container .py-3 { padding-top: 5px !important; padding-bottom: 5px !important; }
    #print-container .mb-4 { margin-bottom: 8px !important; }
    #print-container .mb-6 { margin-bottom: 10px !important; }
    #print-container .gap-4 { gap: 8px !important; }
    #print-container .space-y-4 > * + * { margin-top: 6px !important; }
    #print-container .space-y-3 > * + * { margin-top: 5px !important; }
    #print-container th,
    #print-container td { padding: 4px 8px !important; }
    #print-container img { max-height: 2.75rem !important; }

    /* Let large cards flow across pages (prevents blank gaps),
       but keep table rows intact */
    #print-container .print\:break-inside-avoid { page-break-inside: auto !important; }
    #print-container tr { page-break-inside: avoid !important; }

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
