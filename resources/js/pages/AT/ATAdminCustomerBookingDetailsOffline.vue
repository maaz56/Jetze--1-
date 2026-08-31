
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

    return Array.isArray(adjustments) ? adjustments : [];
});

/** Format one locked AED commercial adjustment with its original sign. */
function formatLockedCommercialAdjustment(adjustment) {
    const amount = Number(adjustment?.aed_amount);
    const sign = amount < 0 ? '-' : '+';

    return `${sign}${formatAmountWithCurrency(Math.abs(amount || 0), 'AED')}`;
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



const booking = ref(null);
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
                        :disabled="['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
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
                        class="bg-white rounded-lg shadow-sm overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                        <div class="p-6 text-white print:text-black print:bg-white print:border-b print:border-gray-300">
                            <div class="flex justify-between items-center">
                                <div class="w-1/4">
                                    <img class="h-16 w-auto print:h-12"
                                        :src="agentData?.agent_data?.logo || '/public/assets/logo.png'" alt="Logo" />
                                </div>

                                <div
                                    class="w-2/4 text-black text-center border-l-2 border-black/30 pl-4 print:border-gray-400">
                                    <h1 class="text-2xl font-extrabold tracking-tight print:text-gray-900 mb-1">
                                        E-TICKET RECEIPT
                                    </h1>
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
                                    </div>
                                </div>

                                <div
                                    class="w-1/4 text-black text-right border-l-2 border-black/30 pl-4 print:border-gray-400">
                                    <p class="text-sm print:text-gray-700">
                                        <span class="font-medium">Booking Ref:</span> {{ booking.id }}
                                    </p>
                                    <p class="text-sm print:text-gray-700">
                                        <span class="font-medium">Status:</span>
                                        <span class="capitalize font-semibold print:text-gray-900">{{ booking.status
                                            }}</span>
                                    </p>
                                    <p class="text-sm print:text-gray-700">
                                        <span class="font-medium">Airline PNR:</span>
                                        {{ pnrDetails?.bookingId ? pnrDetails?.bookingId : booking?.itinerary_ref || "-"
                                        }}
                                    </p>
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
                                    <div
                                        class="border border-gray-200 rounded-lg p-6 hover:shadow-sm transition-shadow print:border-gray-400 print:shadow-none print:mb-6 print:p-8 print:text-base">
                                        <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex"
                                            class="bg-white overflow-hidden">
                                            <div v-if="segment?.layover_time" class="flex justify-center">
                                                <div
                                                    class="bg-blue-100 text-blue-800 text-xs sm:text-sm font-medium px-4 py-1.5 rounded-full shadow-sm">
                                                    Layover Time:
                                                    {{
                                                        Math.floor(segment.layover_time / 60) + "h " +
                                                        (segment.layover_time % 60) + "m"
                                                    }}
                                                </div>
                                            </div>

                                            <div class="p-2 sm:p-3 lg:p-6">
                                                <div class="flex items-center justify-center gap-6">
                                                    <div class="grid grid-cols-7 gap-4 items-center w-full">
                                                        <div class="w-20 flex-shrink-0 print:w-24">
                                                            <img :src="segment?.operating_carrier?.logo"
                                                                class="h-12 w-auto object-contain print:h-14"
                                                                :alt="segment?.operating_carrier?.name" />
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                {{ segment?.operating_carrier?.iata }}-{{
                                                                    segment?.flight_number ?? "N/A" }}
                                                            </div>
                                                        </div>

                                                        <div class="col-span-2 text-right print:text-right">
                                                            <p class="text-2xl font-bold text-gray-900 print:text-3xl">
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
                                                                {{ moment.parseZone(segment?.departure_at).format("HH:mm")
                                                                }}
                                                            </p>
                                                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                Terminal: {{ segment?.from_terminal?.Gate ?? "N/A" }}
                                                            </p>
                                                        </div>

                                                        <div class="col-span-1 flex flex-col items-center">
                                                            <div
                                                                class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center print:bg-transparent print:border print:border-gray-400">
                                                                <MoveRight class="h-5 w-5 text-gray-600 print:text-black" />
                                                            </div>
                                                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                {{ segment?.flight_time }}
                                                            </p>
                                                        </div>

                                                        <div class="col-span-2 text-left print:text-left">
                                                            <p class="text-2xl font-bold text-gray-900 print:text-3xl">
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
                                                                {{ moment.parseZone(segment?.arrival_at).format("HH:mm")
                                                                }}
                                                            </p>
                                                            <p class="text-sm text-gray-500 mt-2 print:text-base">
                                                                Terminal: {{ segment?.to_terminal?.Gate ?? "N/A" }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div
                                                    class="mt-5 pt-4 border-t border-gray-200 text-sm text-gray-600 flex items-center gap-6 print:text-base print:border-gray-400">
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

                                    <div
                                        class="border border-gray-200 rounded-xl bg-white p-8 min-h-[152px] print:border-gray-300 print:break-inside-avoid">
                                        <div class="space-y-6">
                                            <div class="flex flex-wrap justify-start items-start gap-8">
                                                <div v-for="(flightSegment, sIndex) in flight?.segments" :key="sIndex"
                                                    class="min-w-[260px]">
                                                    <div
                                                        class="mb-3 text-[10px] font-semibold text-slate-900 print:text-gray-800">
                                                        {{ flightSegment.from.iata }} → {{ flightSegment.to.iata }}
                                                    </div>
                                                    <div class="print:w-full">
                                                        <table
                                                            class="w-auto min-w-[260px] text-[10px] border-0 print:rounded-none">
                                                            <thead>
                                                                <tr
                                                                    class="border-b border-gray-300 print:border-gray-400 print:bg-white">
                                                                    <th
                                                                        class="py-2 px-3 text-left font-bold text-slate-950 print:text-gray-900 print:bg-white">
                                                                        Pax Type
                                                                    </th>
                                                                    <th
                                                                        class="py-2 px-3 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                                        Check-In Baggage
                                                                    </th>
                                                                    <th
                                                                        class="py-2 px-3 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                                        Cabin Baggage
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <pre class="hidden">{{ flight.fares }}</pre>
                                                            <tbody>
                                                                <template v-for="(fare, fIndex) in flight?.fares"
                                                                    :key="fIndex">
                                                                    <template
                                                                        v-if="parseFlightData(bookingDetails?.[0]?.fare_reference)?.includes(fare?.ref_id)">
                                                                        <tr v-for="(travelerType, tIndex) in [...new Set((fare.baggage_policies || []).map(bp => bp.traveler_type))]"
                                                                            :key="tIndex"
                                                                            class="hover:bg-gray-50 print:bg-transparent">
                                                                            <td
                                                                                class="py-3 px-3 uppercase font-medium text-slate-950 print:text-gray-800">
                                                                                {{ travelerType }}
                                                                            </td>
                                                                            <td class="py-3 px-3 uppercase text-slate-950 print:text-gray-800">
                                                                                {{ fare.baggage_policies?.find(bp => bp.traveler_type === travelerType && bp.type === 'checked')?.description || 'N/A' }}
                                                                            </td>
                                                                            <td class="py-3 px-3 uppercase text-slate-950 print:text-gray-800">
                                                                                {{ (fare.baggage_policies || []).find(bp => bp.traveler_type === travelerType && (bp.type === 'carry' || bp.type === 'carry-on'))?.description || 'N/A' }}
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
                    </div>
                    <div class="p-6 border-b border-gray-200 bg-white print:border-gray-300 print:break-inside-avoid"
                        v-for="booking in bookingDetails"
                        :key="booking.id">
                        <h2 class="mb-6 flex items-center text-base font-extrabold text-slate-950 print:text-gray-900">
                            <UserIcon class="mr-3 h-5 w-5 text-primary print:text-gray-700" />
                            PASSENGER & TICKET DETAILS
                        </h2>

                        <div class="overflow-x-auto">
                            <table
                                class="w-full border-0 text-[11px] print:rounded-none">
                                <thead>
                                    <tr class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                        <th
                                            class="px-4 py-2.5 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                            Traveller Name</th>
                                        <th
                                            class="px-4 py-2.5 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                            Gender</th>
                                        <th
                                            class="px-4 py-2.5 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                            Nationality/CNIC</th>
                                        <th
                                            class="px-4 py-2.5 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                            Ticket No</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(traveller, index) in booking?.pessangers" :key="index"
                                        class="print:bg-transparent">
                                        <td class="px-4 py-4 uppercase text-slate-950 print:text-gray-800">
                                            {{ traveller.title }} {{ traveller.first_name }} {{ traveller.last_name }}
                                            <span class="text-gray-500 text-xs ml-1 print:text-gray-600">({{
                                                traveller.type }})</span>
                                        </td>
                                        <td class="px-4 py-4 uppercase text-slate-950 print:text-gray-800">
                                            {{ traveller?.gender?.toUpperCase() || 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 text-slate-950 print:text-gray-800">
                                            {{ traveller.nationality || 'N/A' }}
                                        </td>
                                        <td class="px-4 py-4 font-mono text-slate-950 print:text-gray-800">
                                            {{ pnrDetails?.booking_details ? pnrDetails?.booking_details[index]?.ticket_no : "N/A" }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid bg-white"
                        v-if="isDetailsInfoVisible">
                        <h2 class="mb-6 text-base font-extrabold text-slate-950 print:text-gray-900">
                            FARE BREAKDOWN
                        </h2>
                        <div>
                            <div class="overflow-x-auto">


                                <table
                                    class="w-full border-0 text-[10px] print:rounded-none">
                                    <thead>
                                        <tr class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                            <th
                                                class="py-2 px-2 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                Sector</th>
                                            <th
                                                class="py-2 px-2 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                Subtotal</th>
                                            <th
                                                class="py-2 px-2 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                Taxes + Fees
                                            </th>
                                            <th
                                                class="py-2 px-2 text-left font-bold text-slate-950 print:text-gray-900 print:bg-transparent">
                                                Grand Total</th>
                                        </tr>
                                    </thead>
                                    <tbody v-if="pnrDetails?.fares?.length"
                                        class="print:divide-gray-400">
                                        <tr class="print:bg-transparent">
                                            <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                {{
                                                    parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.[0]
                                                        ?.segments?.[0]?.from?.iata || "-"
                                                }} →
                                                {{
                                                    parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights?.[0]
                                                        ?.segments?.slice(-1)?.[0]?.to?.iata || "-"
                                                }}
                                            </td>
                                            <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                {{ formatLockedBookingAmount() }}
                                            </td>

                                            <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                {{ formatPnrTaxesAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}
                                            </td>
                                            <td class="py-4 px-2 uppercase font-bold text-slate-950 print:text-gray-900">
                                                {{ formatLockedBookingAmount() }}
                                            </td>

                                        </tr>
                                    </tbody>

                                    <tbody v-else class="divide-y divide-gray-200 print:divide-gray-400">
                                        <template
                                            v-for="(flight, index) in parseFlightData(bookingDetails[0]?.flight_data)?.leg?.flights"
                                            :key="index">
                                            <!-- Flight route header -->


                                            <!-- Fare rows -->
                                            <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                                const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                                    ? parseFlightData(bookingDetails[0]?.fare_reference)
                                                    : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                                return fareRefs.includes(f.ref_id);
                                            })" :key="fareIndex" class="print:bg-transparent">
                                                <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                    {{ flight.segments?.[0]?.from?.iata }} → {{
                                                        flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                                </td>


                                                <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                    {{ formatSelectedFareMoney(fare) }}
                                                </td>

                                                <td class="py-4 px-2 uppercase text-slate-950 print:text-gray-800">
                                                    {{ formatSelectedFareTaxesMoney(fare) }}
                                                </td>

                                                <td class="py-4 px-2 uppercase font-bold text-slate-950 print:text-gray-900">
                                                    {{ formatSelectedFareMoney(fare) }}
                                                </td>
                                            </tr>
                                            
                                        </template>
                                    </tbody>
                                    <tfoot class="bg-gray-50 print:bg-transparent">
                                        <tr>
                                            <td colspan="3"
                                                class="py-4 px-2 text-right text-[11px] font-bold text-slate-950">
                                                Add-ons
                                            </td>
                                            <td class="py-4 px-2 text-[11px] font-bold text-primary">
                                                {{ formatBookingAddOnsAmount(bookingDetails?.[0]?.add_ones_amount) }}
                                            </td>
                                        </tr>
                                        <tr v-for="adjustment in lockedCommercialAdjustments" :key="`${adjustment.type}-${adjustment.rule_id}-${adjustment.rule_snapshot?.fare_ref_id}`">
                                            <td colspan="3"
                                                class="pb-2 px-2 text-right text-[11px] font-bold text-slate-950">
                                                {{ adjustment.type === 'segment_margin' ? 'Segment Margin' : 'Promotion' }}
                                                <span v-if="adjustment.title" class="font-normal text-slate-500">— {{ adjustment.title }}</span>
                                            </td>
                                            <td class="pb-2 px-2 text-[11px] font-bold text-primary">
                                                {{ formatLockedCommercialAdjustment(adjustment) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3"
                                                class="pb-2 px-2 text-right text-[11px] font-bold text-slate-950">
                                                Total Amount (AED)
                                            </td>
                                            <td class="pb-2 px-2 text-[11px] font-bold text-primary">
                                                {{ formatLockedBookingAmount() }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3"
                                                class="pb-6 px-2 text-right text-[11px] font-bold text-slate-950">
                                                Customer Selected Total
                                            </td>
                                            <td class="pb-6 px-2 text-[11px] font-bold text-primary">
                                                {{ formatCustomerSelectedBookingAmount() }}
                                            </td>
                                        </tr>
                                    </tfoot>


                                </table>
                            </div>
                        </div>


                    </div>
                   <div class="w-full p-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6 grid gap-3 grid-cols-2">
                            <!-- Travel documents -->
                            <div class="mb-8 col-span-1 ">
                                <div class="flex items-center mb-4">
                                    <div class="w-6 h-6 mr-3">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            class="w-6 h-6 text-primary">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14,2 14,8 20,8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                            <polyline points="10,9 9,9 8,9" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800">Travel documents</h3>
                                </div>
                                <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                                    Please be advised that you are required to produce various travel documents
                                    depending on your journey, destination and purpose of travel. The documents required
                                    may include the following:
                                </p>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A passport with a minimum validity of 6 months is required, with
                                            sufficient empty pages in the back.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A valid visa for the country you are visiting. Also check if a transit
                                            visa is required if you are transiting between other countries during your
                                            journey.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A valid National ID for GCC nationals travelling with the Arabian Gulf
                                            region; please check if the country you are visiting allows entry with your
                                            National ID card.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Immigration authorities require airlines to provide advance passenger
                                            information prior to departure, so please ensure that your bookings have
                                            been updated prior to your travel.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Passengers from SAARC countries like India and Pakistan travelling to the
                                            GCC may require OK to board approval; please ensure your booking is updated
                                            with approval 24 hours prior to travel.</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="col-span-1">
                                <!-- Don't miss your flight -->
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                class="w-6 h-6 text-primary">
                                                <path
                                                    d="M17.8 19.2 16 11l3.5-3.5C21 6 21 4 19 4s-3 2-4.5 3.5L11 16l-7.2 1.8a1 1 0 0 0-.8.8 1 1 0 0 0 .8.8L11 16l3.5 4.5C16 22 18 22 18 20s-2-3-3.5-4.5L16 11l1.8 7.2Z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Don't miss your flight</h3>
                                    </div>
                                    <ul class="space-y-2 text-sm text-gray-700">
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>Please make sure you're at the airport well ahead of your flight's
                                                departure time.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>For international flights, it's typically advised to arrive at the
                                                airport
                                                at least <span class="font-semibold">4 hours</span> before departure,
                                                but
                                                this can vary depending on circumstances.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span
                                                class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>For domestic flights, it's typically advised to arrive at the airport
                                                at
                                                least <span class="font-semibold">2 hours</span> before departure, but
                                                this
                                                can vary depending on circumstances.</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Boarding pass -->
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                class="w-6 h-6 text-primary">
                                                <path
                                                    d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
                                                <path d="M13 5v2" />
                                                <path d="M13 17v2" />
                                                <path d="M13 11v2" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Boarding pass</h3>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        If you'd like to get your boarding pass before heading to the airport, our team
                                        may
                                        be able to assist you.
                                    </p>
                                </div>

                                <!-- Extra baggage -->
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                class="w-6 h-6 text-primary">
                                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                                                <path d="M3 6h18" />
                                                <path d="M16 10a4 4 0 0 1-8 0" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Extra baggage</h3>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        You can contact a travel advisor to add extra baggage, subject to the airline's
                                        availability and rates.
                                    </p>
                                </div>
                            </div>

                            <!-- Cancellation & Amendment Policies -->
                            <!-- <div>
                                <h2 class="text-lg font-semibold mb-3 text-gray-800">Cancellation & Amendment Policies
                                </h2>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Cancellation of a ticket can be done either by emailing us or directly
                                            through the airline.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>For ticket amendments, please send us an email along with your booking
                                            reference (e.g., Trip 20 - AK 180XXXXXX) and the new travel details.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Cancellations or amendments must be made at least 24 hours prior to
                                            departure.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Requests made within 24 hours of departure should be directed to the
                                            airline.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Cancellation policy follows airline rules. Special and promotional fares
                                            are non-refundable.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>If you have canceled your booking directly with the airline, please email
                                            us to process the applicable refund.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span
                                            class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span><span class="text-blue-600">{{ agentData?.agent_data?.company_name
                                                }}</span> service charge: PKR 1000 per passenger.</span>
                                    </li>
                                </ul>
                            </div> -->
                        </div>
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

<style scoped>
/* Print styles - Only necessary overrides */
@media print {
    @page {
        margin: 0.5in;
        size: A4;
    }

    /* Hide all elements except print section */
    body * {
        visibility: hidden !important;
    }

    #print-section,
    #print-section * {
        visibility: visible !important;
    }

    #print-section {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: white !important;
    }

    /* Hide interactive elements */
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
