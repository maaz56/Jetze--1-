<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, EyeIcon, PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff, PrinterIcon, MailIcon, Download } from "lucide-vue-next";
import Input from "@/components/ui/input/Input.vue";
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
import { SEND_EMAIL } from "@/services/store/actions.type";
import Label from "@/components/common/Label.vue";
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";
import { Textarea } from "@/components/ui/textarea";
import apiService from "@/config/axios";

const props = defineProps({
    audience: {
        type: String,
        default: "admin",
    },
});

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const booking_id = route.query.booking_id || route.query.id;
const pnr = route.query.pnr;
const agent_id = route.query.agent_id;

const isBookingDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const actionLoading = ref(false);
const error = ref(null);
const localDetails = ref(null);

const isLoading = computed(() => isBookingDetailsLoading.value);

const user = computed(() => authStore.user);
const isAdmin = computed(() => user.value?.role === "admin" || props.audience === "admin");
const agentData = computed(() => store.getters["user/agentData"]);
const storeBookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

const booking = computed(() => {
    if (Array.isArray(storeBookingDetails.value) && storeBookingDetails.value.length > 0) {
        return storeBookingDetails.value[0];
    }
    if (localDetails.value?.booking) {
        return localDetails.value.booking;
    }
    return null;
});

const flightData = ref(null);
const isDetailsInfoVisible = ref(true);
const totalTicketPrice = ref(0);

const pnrData = computed(() => {
    try {
        if (localDetails.value?.hakimi) {
            return localDetails.value.hakimi;
        }
        const raw = booking.value?.pnr_response;
        return raw ? (typeof raw === "string" ? JSON.parse(raw) : raw) : null;
    } catch (e) {
        return null;
    }
});

const hakimi = computed(() => pnrData.value || {});

const lockedAdminBookingMoney = computed(() => {
    const b = booking.value;
    const snapshot = b?.price_snapshot;
    const aedAmount = snapshot?.aed_amount ?? b?.aed_amount;

    if (aedAmount !== null && aedAmount !== undefined && !Number.isNaN(Number(aedAmount))) {
        return {
            amount: Number(aedAmount),
            currency: "AED",
        };
    }

    const providerAmount = snapshot?.provider_amount ?? hakimi.value?.totalFare ?? b?.amount ?? 0;
    const providerCurrency = snapshot?.provider_currency ?? hakimi.value?.currency ?? "INR";

    return {
        amount: Number(providerAmount),
        currency: String(providerCurrency).toUpperCase(),
    };
});

const lockedCustomerBookingMoney = computed(() => {
    const b = booking.value;
    const snapshot = b?.price_snapshot;
    const amount = snapshot?.selling_amount ?? hakimi.value?.totalFare ?? b?.amount;
    const currency = snapshot?.selling_currency ?? hakimi.value?.currency ?? "INR";

    if (amount === null || amount === undefined || !currency) {
        return null;
    }

    return {
        amount: Number(amount),
        currency: String(currency).toUpperCase(),
    };
});

const lockedCommercialAdjustments = computed(() => {
    const adjustments = booking.value?.price_snapshot?.adjustments_snapshot;
    return Array.isArray(adjustments) ? adjustments : [];
});

function formatLockedCommercialAdjustment(adjustment) {
    const amount = Number(adjustment?.aed_amount || adjustment?.amount);
    const curr = adjustment?.aed_amount ? "AED" : (lockedCustomerBookingMoney.value?.currency || "INR");
    const sign = amount < 0 ? "-" : "+";
    return `${sign}${formatAmountWithCurrency(Math.abs(amount || 0), curr)}`;
}

function formatCustomerSelectedBookingAmount() {
    const money = lockedCustomerBookingMoney.value;
    if (!money || Number.isNaN(money.amount)) {
        return "Price unavailable";
    }
    return formatAmountWithCurrency(money.amount, money.currency);
}

function formatLockedBookingAmount() {
    if (!lockedAdminBookingMoney.value || Number.isNaN(lockedAdminBookingMoney.value.amount)) {
        return "Price unavailable";
    }
    return formatAmountWithCurrency(
        lockedAdminBookingMoney.value.amount,
        lockedAdminBookingMoney.value.currency,
    );
}

function formatSelectedFareMoney(fare) {
    const money = fare?.base_money?.total_price ?? fare?.base_money ?? fare?.display_money;
    if (money?.currency && Number.isFinite(Number(money.amount))) {
        return formatAmountWithCurrency(money.amount, money.currency);
    }
    if (fare?.total_price && fare?.currency?.code) {
        return formatAmountWithCurrency(fare.total_price, fare.currency.code);
    }
    return formatLockedBookingAmount();
}

function formatSelectedFareTaxesMoney(fare) {
    const money = fare?.passenger_fares?.[0]?.base_money?.taxes;
    if (money?.currency && Number.isFinite(Number(money.amount))) {
        return formatAmountWithCurrency(money.amount, money.currency);
    }
    if (fare?.taxes !== undefined && fare?.taxes !== null) {
        const curr = fare?.currency?.code || lockedCustomerBookingMoney.value?.currency || "INR";
        return formatAmountWithCurrency(Number(fare.taxes || 0), curr);
    }
    return formatAmountWithCurrency(0, lockedAdminBookingMoney.value?.currency || "INR");
}

function formatBookingAddOnsAmount(amount) {
    if (Number(amount || 0) === 0) {
        return formatAmountWithCurrency(0, lockedAdminBookingMoney.value?.currency || "AED");
    }
    return `${formatAmountWithCurrency(amount, lockedAdminBookingMoney.value?.currency || "AED")} (Included in total)`;
}

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
const passengerCount = ref(1);

const voidChargesTotalAmount = computed(() => lockedAdminBookingMoney.value?.amount ?? Number(totalTicketPrice.value || 0));
const voidRefundAmount = computed(() => {
    const total = Number(voidChargesTotalAmount.value || 0);
    const charge = Number(charges.value || 0);
    return Math.max(total - (Number.isFinite(charge) ? charge : 0), 0);
});

function sendEmail() {
    store.dispatch(`flight/${SEND_EMAIL}`, {
        email: custEmail.value ? custEmail.value : booking.value?.main_email,
        booking_id: booking.value?.flight_id || booking.value?.id,
        booking_source: route.query.booking_source || booking.value?.booking_source,
    });
    isEmailDialogOpen.value = false;
    custEmail.value = null;
}

function fetchAgentLedger() {
    const effectiveAgentId = agent_id || booking.value?.agent_id;
    if (effectiveAgentId) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
                userId: effectiveAgentId,
            });
        } catch (err) {
            console.error("Failed to load agent ledger:", err);
        }
    }
}

async function fetchAgent() {
    const effectiveAgentId = agent_id || booking.value?.agent_id;
    if (effectiveAgentId) {
        try {
            await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: effectiveAgentId });
        } catch (err) {
            console.error("Failed to load agent data:", err);
        } finally {
            isAgentLoading.value = false;
        }
    } else {
        isAgentLoading.value = false;
    }
}

const parseFlightData = (flightDataString) => {
    if (!flightDataString) return null;
    if (typeof flightDataString === "object") return flightDataString;
    try {
        return JSON.parse(flightDataString);
    } catch (error) {
        console.error("Error parsing flight data:", error);
        return null;
    }
};

async function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        isBookingDetailsLoading.value = false;
        return;
    }
    try {
        await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, {
            bookingId: booking_id,
            bookingSource: route.query.booking_source,
        });

        if (booking.value) {
            flightData.value = parseFlightData(booking.value.flight_data);
            passengerCount.value = parseInt(booking.value.pessangers?.length || 1);
        } else {
            const response = await apiService.get("/hakimi-booking-details", {
                params: { booking_id },
            });
            localDetails.value = response.data;
            flightData.value = response.data?.flight || parseFlightData(response.data?.booking?.flight_data);
            passengerCount.value = parseInt(response.data?.booking?.pessangers?.length || 1);
        }
        calculateGrandTotal();
    } catch (err) {
        try {
            const response = await apiService.get("/hakimi-booking-details", {
                params: { booking_id },
            });
            localDetails.value = response.data;
            flightData.value = response.data?.flight || parseFlightData(response.data?.booking?.flight_data);
            passengerCount.value = parseInt(response.data?.booking?.pessangers?.length || 1);
            calculateGrandTotal();
        } catch (fallbackErr) {
            error.value = "Failed to fetch booking details.";
        }
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
        errors.push(`Void charge must be 0 or greater.`);
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
            bookingId: booking.value?.id,
            flight_provider: "hakimi",
            void_charge_aed: voidChargeAmount,
            void_date: chargesDate.value,
            void_description: chargesDec.value,
        });

        charges.value = "";
        chargesDate.value = "";
        chargesDec.value = "";
        isChargesOpen.value = false;

        fetchAgentLedger();
        await fetchBookingDetails();
    } catch (err) {
        validationErrors.value = ["Something went wrong with voiding. Please try again."];
        console.error(err);
    } finally {
        actionLoading.value = false;
    }
}

function handleConfirmDialogOpen() {
    if (!isAdmin.value && agentLedger.value?.balance < totalTicketPrice.value) {
        isLowBalanceDialogOpen.value = true;
        return;
    }
    isConfirmDialogOpen.value = true;
}

async function cancelBooking() {
    if (actionLoading.value) return;

    error.value = "";
    actionLoading.value = true;

    try {
        await store.dispatch(`flight/${CANCEL_BOOKING}`, {
            pnr: pnr || hakimi.value?.hakimiBookingNo || booking.value?.itinerary_ref,
            booking_source: "hakimi",
            flight_provider: "hakimi",
            bookingId: booking.value?.id,
        });

        isDialogOpen.value = false;
        await fetchBookingDetails();
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Failed to cancel booking";
    } finally {
        actionLoading.value = false;
    }
}

async function confirmBooking() {
    if (actionLoading.value) return;

    error.value = "";
    actionLoading.value = true;

    try {
        await store.dispatch(`flight/${CONFIRM_BOOKING}`, {
            pnr: pnr || hakimi.value?.hakimiBookingNo || booking.value?.itinerary_ref,
            bookingId: booking.value?.id,
            booking_status: "ticketed",
            booking_source: "hakimi",
            flight_provider: "hakimi",
            totalTicketPrice: totalTicketPrice.value,
        });

        isConfirmDialogOpen.value = false;
        await fetchBookingDetails();
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Failed to confirm booking";
    } finally {
        actionLoading.value = false;
    }
}

const formatDate = (dateString) => {
    if (!dateString) return "—";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const formatTime = (timeString) => {
    if (!timeString) return "—";
    try {
        const date = new Date(timeString);
        return new Intl.DateTimeFormat("en-US", {
            hour: "2-digit",
            minute: "2-digit",
            hour12: true,
        }).format(date);
    } catch (e) {
        return timeString;
    }
};

const formatDuration = (minutes) => {
    if (!minutes) return "—";
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
};

function toggleFareInfo() {
    isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
}

const printBooking = () => {
    const printContent = document.getElementById("print-section")?.innerHTML;
    if (!printContent) return;

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
    if (!element) return;

    const options = {
        margin: 2,
        filename: `hakimi_booking_${booking_id || booking.value?.id}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: {
            scale: 2,
            logging: false,
            useCORS: true,
            windowWidth: element.scrollWidth,
            windowHeight: element.scrollHeight,
        },
        jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
    };

    html2pdf().from(element).set(options).save();
};

function calculateTaxes(fare) {
    return (
        parseFloat(fare?.taxes || 0) +
        parseFloat(fare?.surchage || fare?.surcharge || 0) +
        parseFloat(fare?.fees || 0) +
        parseFloat(fare?.service_charges || 0) +
        parseFloat(fare?.ancillaries_charges || 0)
    );
}

function calculateTotalFare(fare) {
    const paxCount = parseInt(booking.value?.pessangers?.length || 1);
    const agentAmount = parseFloat(booking.value?.agent_markup || 0);
    const agentDiscount = parseFloat(booking.value?.agent_discount || 0);
    const margin = parseFloat(booking.value?.agent_margin || 0);

    const airlineMargin = calculateFinalPrice(
        parseFloat(fare?.base_price || 0),
        parseFloat(fare?.margin_amount || 0),
        fare?.margin_type,
        fare?.amount_type
    );

    const billable =
        parseFloat(fare?.surchage || fare?.surcharge || 0) +
        parseFloat(fare?.taxes || 0) +
        parseFloat(fare?.fees || 0) +
        parseFloat(fare?.service_charges || 0) +
        parseFloat(fare?.ancillaries_charges || 0) +
        parseFloat(airlineMargin || 0);

    return billable + agentAmount * paxCount + margin - agentDiscount * paxCount;
}

function calculateGrandTotal() {
    let total = 0;
    const parsed = flightData.value || parseFlightData(booking.value?.flight_data);
    const flights = parsed?.leg?.flights || parsed?.original?.leg?.flights || [];

    flights.forEach((flight) => {
        flight?.fares?.forEach((fare) => {
            total += calculateTotalFare(fare);
        });
    });

    totalTicketPrice.value = total || Number(lockedAdminBookingMoney.value?.amount || 0);
    return totalTicketPrice.value;
}

watch(user, (newUser) => {
    if (newUser) {
        fetchAgent();
    }
});

onMounted(async () => {
    if (user.value == null) {
        await authStore.fetchUser();
    }
    fetchAgent();
    fetchAgentLedger();
    await fetchBookingDetails();
});
</script>

<template>
    <section>
        <ATFlowLoader
            v-if="isLoading"
            title="Loading Hakimi booking details"
            message="We are refreshing the Hakimi booking, flight itinerary, and ticket information."
            :steps="['Booking', 'PNR', 'Ticket']"
        />

        <div v-else-if="error && !booking" class="mx-auto max-w-5xl p-6">
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-red-700">
                <p class="font-medium">{{ error }}</p>
                <button
                    class="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-white hover:bg-primary/90"
                    @click="router.back()"
                >
                    Back to Bookings
                </button>
            </div>
        </div>

        <div v-else class="mx-auto min-h-screen bg-gray-100 p-4">
            <!-- Top Actions Bar (Hidden in Print) -->
            <div
                class="mb-4 flex flex-wrap items-center justify-end gap-2 rounded-lg bg-white p-3 py-4 shadow-sm print:hidden"
                id="topBar"
            >
                <button
                    @click="printBooking"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <PrinterIcon class="h-4 w-4" />
                    Print
                </button>

                <button
                    @click="downloadPDF"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <Download class="h-4 w-4" />
                    Download PDF
                </button>

                <button
                    @click="toggleFareInfo"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                >
                    <EyeOff class="h-4 w-4" />
                    <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                    <span v-else>View Fare Details</span>
                </button>

                <!-- Void Booking Dialog -->
                <Dialog :open="isChargesOpen" @update:open="isChargesOpen = $event">
                    <button
                        :hidden="['canceled', 'requested', 'booked', 'voided'].includes(booking?.status?.toLowerCase())"
                        :disabled="['canceled', 'requested', 'voided'].includes(booking?.status?.toLowerCase())"
                        @click="openChargesDialog"
                        class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Void Booking
                    </button>
                    <DialogContent class="sm:max-w-[425px]">
                        <DialogHeader>
                            <DialogTitle class="text-2xl">Void Booking</DialogTitle>
                        </DialogHeader>

                        <div v-if="validationErrors.length > 0">
                            <ul class="list-inside list-disc rounded-md border border-destructive bg-red-100 p-4 text-destructive">
                                <li v-for="err in validationErrors" :key="err">
                                    {{ err }}
                                </li>
                            </ul>
                        </div>

                        <form @submit.prevent="saveCharges">
                            <div class="mb-3">
                                <Label for="totalCharges" :title="`Locked Booking Total (${lockedAdminBookingMoney?.currency || 'AED'})`" />
                                <Input
                                    type="number"
                                    :model-value="voidChargesTotalAmount"
                                    readonly
                                    id="totalCharges"
                                    :placeholder="`Amount in ${lockedAdminBookingMoney?.currency || 'AED'}`"
                                />
                            </div>
                            <div class="mb-3">
                                <Label for="charges" :title="`Void Charges (${lockedAdminBookingMoney?.currency || 'AED'})`" />
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model="charges"
                                    id="charges"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="mb-3">
                                <Label for="refundAmount" :title="`Net Wallet Refund (${lockedAdminBookingMoney?.currency || 'AED'})`" />
                                <Input
                                    type="number"
                                    :model-value="voidRefundAmount"
                                    readonly
                                    id="refundAmount"
                                />
                            </div>
                            <div class="mb-3">
                                <Label for="chargesDate" title="Void Settlement Date" />
                                <Input type="date" v-model="chargesDate" id="chargesDate" />
                            </div>
                            <div class="mb-3">
                                <Label for="chargesDec" title="Void Reason / Notes" />
                                <Textarea
                                    v-model="chargesDec"
                                    id="chargesDec"
                                    placeholder="Enter void reason or notes"
                                />
                            </div>
                            <Button type="submit" class="float-right" :disabled="actionLoading">
                                {{ actionLoading ? "Voiding..." : "Confirm Void" }}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>

                <!-- Cancel Booking Dialog -->
                <button
                    @click="isDialogOpen = true"
                    :disabled="actionLoading || ['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                    class="flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    {{
                        booking?.status?.toLowerCase() === "canceled"
                            ? "Canceled"
                            : booking?.status?.toLowerCase() === "ticketed"
                            ? "Ticketed"
                            : booking?.status?.toLowerCase() === "issued"
                            ? "Issued"
                            : "Cancel Booking"
                    }}
                </button>

                <div
                    v-if="isDialogOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                    @click.self="isDialogOpen = false"
                >
                    <div class="w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                            <button
                                @click="isDialogOpen = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to cancel this Hakimi booking? This action cannot be undone.
                            </p>
                            <div v-if="error" class="mt-3 rounded-md bg-red-100 p-3 text-sm text-red-700">
                                {{ error }}
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button
                                @click="isDialogOpen = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Cancel
                            </button>
                            <button
                                @click="cancelBooking"
                                :disabled="actionLoading"
                                class="rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ actionLoading ? "Cancelling..." : "Confirm Cancellation" }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Confirm Booking Button & Dialog -->
                <button
                    :disabled="actionLoading || ['canceled', 'issued', 'requested', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                    @click="handleConfirmDialogOpen"
                    class="flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                    Confirm Booking
                </button>

                <div
                    v-if="isConfirmDialogOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                    @click.self="isConfirmDialogOpen = false"
                >
                    <div class="w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                            <button
                                @click="isConfirmDialogOpen = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to confirm and ticket this Hakimi booking?
                            </p>
                            <div v-if="error" class="mt-3 rounded-md bg-red-100 p-3 text-sm text-red-700">
                                {{ error }}
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button
                                @click="isConfirmDialogOpen = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Cancel
                            </button>
                            <button
                                @click="confirmBooking"
                                :disabled="actionLoading"
                                class="rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ actionLoading ? "Confirming..." : "Yes, Confirm Booking" }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Low Balance Warning Dialog -->
                <div
                    v-if="isLowBalanceDialogOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                    @click.self="isLowBalanceDialogOpen = false"
                >
                    <div class="w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
                            <button
                                @click="isLowBalanceDialogOpen = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Your balance is insufficient to confirm this booking. Please add funds to your account.
                            </p>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button
                                @click="isLowBalanceDialogOpen = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Close
                            </button>
                            <button
                                @click="router.push({ name: 'Deposits' })"
                                class="rounded-md border border-transparent bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            >
                                Go To Deposit
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Email Dialog -->
                <div
                    v-if="isEmailDialogOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                    @click.self="isEmailDialogOpen = false"
                >
                    <div class="w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all">
                        <div class="mb-4 flex items-start justify-between">
                            <h3 class="text-lg font-medium text-gray-900">Enter Email to Send</h3>
                            <button
                                @click="isEmailDialogOpen = false"
                                class="text-gray-400 hover:text-gray-500 focus:outline-none"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-2">
                            <Label class="mb-1 block text-sm font-medium text-gray-700">
                                Agency Email: {{ agentData?.agent_data?.company_email || "N/A" }}
                            </Label>
                            <span class="text-xs text-gray-500">Or enter recipient email</span>
                            <Input
                                type="text"
                                v-model="custEmail"
                                class="mt-2"
                                placeholder="Enter email"
                            />
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button
                                @click="isEmailDialogOpen = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                @click="sendEmail"
                                class="rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                            >
                                Send Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Print Section (Clean E-Ticket Presentation) -->
            <div id="print-section">
                <!-- Test Booking Warning Banner -->
                <div
                    v-if="hakimi?.testBooking !== false"
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-xs font-semibold text-amber-900 print:hidden"
                >
                    Hakimi test booking — not valid for travel.
                </div>

                <div
                    v-if="booking"
                    class="mb-4 overflow-hidden rounded-lg bg-white shadow-sm print:mb-0 print:rounded-none print:border print:border-gray-300 print:shadow-none"
                >
                    <!-- Header -->
                    <div class="p-6 print:border-b print:border-gray-300 print:bg-white print:text-black">
                        <div class="flex items-center justify-between">
                            <div class="w-1/4">
                                <img
                                    class="h-16 w-auto print:h-12"
                                    :src="agentData?.agent_data?.logo || '/public/assets/logo.png'"
                                    alt="Logo"
                                />
                            </div>

                            <div class="w-2/4 border-l-2 border-black/30 pl-4 text-center text-black print:border-gray-400">
                                <h1 class="mb-1 text-2xl font-extrabold tracking-tight print:text-gray-900">
                                    E-TICKET RECEIPT
                                </h1>
                                <div class="mt-2">
                                    <p class="mb-1 text-sm print:text-gray-700">
                                        <span class="font-medium">Status:</span>
                                        <span
                                            :class="{
                                                'font-bold text-green-600': ['booked', 'confirmed', 'ticketed', 'issued'].includes(String(booking.status).toLowerCase()),
                                                'font-bold text-yellow-600': ['pending', 'held'].includes(String(booking.status).toLowerCase()),
                                                'font-bold text-red-600': ['cancelled', 'canceled', 'failed', 'voided'].includes(String(booking.status).toLowerCase()),
                                                'capitalize font-semibold print:text-gray-900': true,
                                            }"
                                        >
                                            {{ hakimi?.status || booking.status }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="w-1/4 border-l-2 border-black/30 pl-4 text-right text-black print:border-gray-400">
                                <p class="text-sm print:text-gray-700">
                                    <span class="font-medium">Booking Ref:</span> {{ booking.id }}
                                </p>
                                <p class="text-sm print:text-gray-700">
                                    <span class="font-medium">Status:</span>
                                    <span class="capitalize font-semibold print:text-gray-900">{{ hakimi?.status || booking.status }}</span>
                                </p>
                                <p class="text-sm print:text-gray-700">
                                    <span class="font-medium">Airline PNR:</span>
                                    {{ hakimi?.airlinePnr || booking.airline_pnr || booking.itinerary_ref || "-" }}
                                </p>
                                <p v-if="hakimi?.hakimiBookingNo" class="text-xs text-gray-500 print:text-gray-600">
                                    <span class="font-medium">Hakimi Ref:</span>
                                    {{ hakimi.hakimiBookingNo }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Information Section -->
                    <div class="border-b border-gray-200 p-6 print:break-inside-avoid print:border-gray-300">
                        <h2 class="mb-4 flex items-center text-lg font-bold text-gray-800 print:text-gray-900">
                            <PlaneIcon class="mr-2 h-5 w-5 text-primary print:text-gray-700" />
                            FLIGHT INFORMATION
                        </h2>

                        <div
                            v-if="flightData?.original?.leg?.flights || flightData?.leg?.flights"
                            class="space-y-4"
                        >
                            <div
                                v-for="(flight, flightIndex) in (flightData?.original?.leg?.flights ?? flightData?.leg?.flights)"
                                :key="flightIndex"
                                class="space-y-3 print:break-inside-avoid"
                            >
                                <div
                                    class="rounded-lg border border-gray-200 p-6 transition-shadow hover:shadow-sm print:mb-6 print:rounded-none print:border-gray-400 print:p-8 print:text-base print:shadow-none"
                                >
                                    <div
                                        v-for="(segment, segmentIndex) in flight?.segments"
                                        :key="segmentIndex"
                                        class="overflow-hidden bg-white"
                                    >
                                        <!-- Layover Time Badge -->
                                        <div v-if="segment?.layover_time" class="mb-3 flex justify-center">
                                            <div
                                                class="rounded-full bg-blue-100 px-4 py-1.5 text-xs font-medium text-blue-800 shadow-sm sm:text-sm"
                                            >
                                                Layover Time:
                                                {{ Math.floor(segment.layover_time / 60) + "h " + (segment.layover_time % 60) + "m" }}
                                            </div>
                                        </div>

                                        <div class="p-2 sm:p-3 lg:p-6">
                                            <div class="flex items-center justify-center gap-6">
                                                <div class="grid w-full grid-cols-7 items-center gap-4">
                                                    <!-- Airline Logo -->
                                                    <div class="w-20 flex-shrink-0 print:w-24">
                                                        <img
                                                            :src="segment?.operating_carrier?.logo || segment?.marketing_carrier?.logo"
                                                            class="h-12 w-auto object-contain print:h-14"
                                                            :alt="segment?.operating_carrier?.name || 'Airline'"
                                                        />
                                                        <div class="mt-1 text-xs text-gray-500">
                                                            {{ segment?.operating_carrier?.iata || segment?.marketing_carrier?.iata }}-{{ segment?.flight_number ?? "N/A" }}
                                                        </div>
                                                    </div>

                                                    <!-- FROM -->
                                                    <div class="col-span-2 text-right print:text-right">
                                                        <p class="text-2xl font-bold text-gray-900 print:text-3xl">
                                                            {{ segment?.from?.iata }}
                                                        </p>
                                                        <p class="text-lg text-gray-600 print:text-base">
                                                            {{ segment?.from?.city?.name || segment?.from?.city }}
                                                        </p>
                                                        <p class="text-lg font-semibold text-gray-600 print:text-base">
                                                            {{ segment?.from?.name }}
                                                        </p>
                                                        <p class="mt-3 text-base font-medium text-gray-700 print:text-lg">
                                                            {{ formatDate(segment?.departure_at) }}
                                                        </p>
                                                        <p class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                                            {{ formatTime(segment?.departure_at) }}
                                                        </p>
                                                        <p class="mt-2 text-sm text-gray-500 print:text-base">
                                                            Terminal: {{ segment?.from_terminal?.Gate ?? segment?.from_terminal ?? "N/A" }}
                                                        </p>
                                                    </div>

                                                    <!-- Arrow and Duration -->
                                                    <div class="col-span-1 flex flex-col items-center">
                                                        <div
                                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 print:border print:border-gray-400 print:bg-transparent"
                                                        >
                                                            <MoveRight class="h-5 w-5 text-gray-600 print:text-black" />
                                                        </div>
                                                        <p class="mt-2 text-sm text-gray-500 print:text-base">
                                                            {{ segment?.flight_time || (segment?.travel_time ? formatDuration(segment.travel_time) : "—") }}
                                                        </p>
                                                    </div>

                                                    <!-- TO -->
                                                    <div class="col-span-2 text-left print:text-left">
                                                        <p class="text-2xl font-bold text-gray-900 print:text-3xl">
                                                            {{ segment?.to?.iata }}
                                                        </p>
                                                        <p class="text-lg text-gray-600 print:text-base">
                                                            {{ segment?.to?.city?.name || segment?.to?.city }}
                                                        </p>
                                                        <p class="text-lg font-semibold text-gray-600 print:text-base">
                                                            {{ segment?.to?.name }}
                                                        </p>
                                                        <p class="mt-3 text-base font-medium text-gray-700 print:text-lg">
                                                            {{ formatDate(segment?.arrival_at) }}
                                                        </p>
                                                        <p class="text-xl font-bold text-primary print:text-2xl print:text-black">
                                                            {{ formatTime(segment?.arrival_at) }}
                                                        </p>
                                                        <p class="mt-2 text-sm text-gray-500 print:text-base">
                                                            Terminal: {{ segment?.to_terminal?.Gate ?? segment?.to_terminal ?? "N/A" }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div
                                                class="mt-5 flex items-center gap-6 border-t border-gray-200 pt-4 text-sm text-gray-600 print:border-gray-400 print:text-base"
                                            >
                                                <span>
                                                    <span class="font-semibold">Aircraft:</span>
                                                    {{ segment?.aircraft?.model || "Commercial Aircraft" }}
                                                </span>
                                                <span>
                                                    <span class="font-semibold">Class:</span>
                                                    {{ segment?.cabin_class || "Economy" }}
                                                </span>
                                                <span>
                                                    <span class="font-semibold">Flight:</span>
                                                    {{ segment?.operating_carrier?.iata || segment?.marketing_carrier?.iata }} {{ segment?.flight_number }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Baggage Policies Table -->
                                <div
                                    class="min-h-[120px] rounded-xl border border-gray-200 bg-white p-6 print:break-inside-avoid print:border-gray-300"
                                >
                                    <div class="space-y-4">
                                        <div class="flex flex-wrap items-start justify-start gap-8">
                                            <div
                                                v-for="(flightSegment, sIndex) in flight?.segments"
                                                :key="sIndex"
                                                class="min-w-[260px]"
                                            >
                                                <div class="mb-3 text-[11px] font-semibold text-slate-900 print:text-gray-800">
                                                    {{ flightSegment.from?.iata }} → {{ flightSegment.to?.iata }} (Baggage Allowance)
                                                </div>
                                                <div class="print:w-full">
                                                    <table class="w-auto min-w-[260px] border-0 text-[10px] print:rounded-none">
                                                        <thead>
                                                            <tr class="border-b border-gray-300 print:border-gray-400 print:bg-white">
                                                                <th class="px-3 py-2 text-left font-bold text-slate-950 print:bg-white print:text-gray-900">
                                                                    Pax Type
                                                                </th>
                                                                <th class="px-3 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                                                    Check-In Baggage
                                                                </th>
                                                                <th class="px-3 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                                                    Cabin Baggage
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <template v-for="(fare, fIndex) in flight?.fares" :key="fIndex">
                                                                <tr
                                                                    v-for="(travelerType, tIndex) in [...new Set((fare.baggage_policies || []).map(bp => bp.traveler_type))]"
                                                                    :key="tIndex"
                                                                    class="hover:bg-gray-50 print:bg-transparent"
                                                                >
                                                                    <td class="px-3 py-3 font-medium uppercase text-slate-950 print:text-gray-800">
                                                                        {{ travelerType }}
                                                                    </td>
                                                                    <td class="px-3 py-3 uppercase text-slate-950 print:text-gray-800">
                                                                        {{ fare.baggage_policies?.find(bp => bp.traveler_type === travelerType && bp.type === 'checked')?.description || '20kg' }}
                                                                    </td>
                                                                    <td class="px-3 py-3 uppercase text-slate-950 print:text-gray-800">
                                                                        {{ (fare.baggage_policies || []).find(bp => bp.traveler_type === travelerType && (bp.type === 'carry' || bp.type === 'carry-on'))?.description || '7kg' }}
                                                                    </td>
                                                                </tr>
                                                            </template>
                                                            <tr v-if="!flight?.fares?.[0]?.baggage_policies?.length">
                                                                <td class="px-3 py-3 uppercase text-slate-950">Adult</td>
                                                                <td class="px-3 py-3 uppercase text-slate-950">20kg</td>
                                                                <td class="px-3 py-3 uppercase text-slate-950">7kg</td>
                                                            </tr>
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
                    <div class="border-b border-gray-200 bg-white p-6 print:break-inside-avoid print:border-gray-300">
                        <h2 class="mb-6 flex items-center text-base font-extrabold text-slate-950 print:text-gray-900">
                            <UserIcon class="mr-3 h-5 w-5 text-primary print:text-gray-700" />
                            PASSENGER & TICKET DETAILS
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="w-full border-0 text-[11px] print:rounded-none">
                                <thead>
                                    <tr class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                        <th class="px-4 py-2.5 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Traveller Name
                                        </th>
                                        <th class="px-4 py-2.5 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Gender
                                        </th>
                                        <th class="px-4 py-2.5 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Nationality/CNIC
                                        </th>
                                        <th class="px-4 py-2.5 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Ticket No / Airline PNR
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(traveller, index) in booking.pessangers || []"
                                        :key="index"
                                        class="border-b border-gray-100 hover:bg-gray-50 print:border-gray-200 print:bg-transparent"
                                    >
                                        <td class="px-4 py-4 uppercase text-slate-950 print:text-gray-800">
                                            {{ traveller.title }} {{ traveller.first_name }} {{ traveller.last_name }}
                                            <span class="ml-1 text-xs text-gray-500 print:text-gray-600">({{ traveller.type }})</span>
                                        </td>
                                        <td class="px-4 py-4 uppercase text-slate-950 print:text-gray-800">
                                            {{ traveller.gender?.toUpperCase() || "N/A" }}
                                        </td>
                                        <td class="px-4 py-4 text-slate-950 print:text-gray-800">
                                            {{ traveller.nationality || traveller.cnic || "N/A" }}
                                        </td>
                                        <td class="font-mono px-4 py-4 text-slate-950 print:text-gray-800">
                                            {{ traveller.ticket_no || hakimi?.airlinePnr || booking.airline_pnr || booking.itinerary_ref || "Issued" }}
                                        </td>
                                    </tr>
                                    <tr v-if="!booking.pessangers?.length">
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">
                                            No passenger records found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Fare Breakdown -->
                    <div
                        class="border-b border-gray-200 bg-white p-6 print:break-inside-avoid print:border-gray-300"
                        v-if="isDetailsInfoVisible"
                    >
                        <h2 class="mb-6 text-base font-extrabold text-slate-950 print:text-gray-900">
                            FARE BREAKDOWN
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="w-full border-0 text-[10px] print:rounded-none">
                                <thead>
                                    <tr class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                        <th class="px-2 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Sector
                                        </th>
                                        <th class="px-2 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Subtotal
                                        </th>
                                        <th class="px-2 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Taxes + Fees
                                        </th>
                                        <th class="px-2 py-2 text-left font-bold text-slate-950 print:bg-transparent print:text-gray-900">
                                            Grand Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template
                                        v-for="(flight, index) in (flightData?.original?.leg?.flights ?? flightData?.leg?.flights ?? [])"
                                        :key="index"
                                    >
                                        <tr
                                            v-for="(fare, fareIndex) in flight.fares || []"
                                            :key="fareIndex"
                                            class="hover:bg-gray-50 print:bg-transparent"
                                        >
                                            <td class="px-2 py-4 uppercase text-slate-950 print:text-gray-800">
                                                {{ flight.segments?.[0]?.from?.iata }} →
                                                {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                            </td>
                                            <td class="px-2 py-4 uppercase text-slate-950 print:text-gray-800">
                                                {{ formatSelectedFareMoney(fare) }}
                                            </td>
                                            <td class="px-2 py-4 uppercase text-slate-950 print:text-gray-800">
                                                {{ formatSelectedFareTaxesMoney(fare) }}
                                            </td>
                                            <td class="px-2 py-4 font-bold uppercase text-slate-950 print:text-gray-900">
                                                {{ formatSelectedFareMoney(fare) }}
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot class="bg-gray-50 print:bg-transparent">
                                    <tr>
                                        <td colspan="3" class="px-2 py-4 text-right text-[11px] font-bold text-slate-950">
                                            Add-ons
                                        </td>
                                        <td class="px-2 py-4 text-[11px] font-bold text-primary">
                                            {{ formatBookingAddOnsAmount(booking?.add_ones_amount) }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="adjustment in lockedCommercialAdjustments"
                                        :key="`${adjustment.type}-${adjustment.rule_id}`"
                                    >
                                        <td colspan="3" class="px-2 pb-2 text-right text-[11px] font-bold text-slate-950">
                                            {{ adjustment.type === "segment_margin" ? "Segment Margin" : "Promotion" }}
                                            <span v-if="adjustment.title" class="font-normal text-slate-500">— {{ adjustment.title }}</span>
                                        </td>
                                        <td class="px-2 pb-2 text-[11px] font-bold text-primary">
                                            {{ formatLockedCommercialAdjustment(adjustment) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-2 pb-2 text-right text-[11px] font-bold text-slate-950">
                                            Locked Total Amount ({{ lockedAdminBookingMoney?.currency || 'AED' }})
                                        </td>
                                        <td class="px-2 pb-2 text-[11px] font-bold text-primary">
                                            {{ formatLockedBookingAmount() }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="px-2 pb-6 text-right text-[11px] font-bold text-slate-950">
                                            Customer Selected Total
                                        </td>
                                        <td class="px-2 pb-6 text-[11px] font-bold text-primary">
                                            {{ formatCustomerSelectedBookingAmount() }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Travel Documents and Advice -->
                    <div class="w-full p-6">
                        <div class="grid grid-cols-1 gap-6 rounded-lg border border-gray-200 bg-white p-6 md:grid-cols-2">
                            <!-- Travel documents -->
                            <div class="col-span-1">
                                <div class="mb-4 flex items-center">
                                    <div class="mr-3 h-6 w-6 text-primary">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14,2 14,8 20,8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                            <polyline points="10,9 9,9 8,9" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800">Travel documents</h3>
                                </div>
                                <p class="mb-4 text-sm leading-relaxed text-gray-600">
                                    Please be advised that you are required to produce various travel documents depending on your journey, destination and purpose of travel:
                                </p>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                                        <span>A passport with a minimum validity of 6 months is required, with sufficient empty pages.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                                        <span>A valid visa for the destination country. Please check transit visa rules if transiting.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                                        <span>Immigration authorities require advance passenger information prior to departure.</span>
                                    </li>
                                </ul>
                            </div>

                            <div class="col-span-1 space-y-6">
                                <!-- Don't miss your flight -->
                                <div>
                                    <div class="mb-3 flex items-center">
                                        <div class="mr-3 h-6 w-6 text-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6">
                                                <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21 4 19 4s-3 2-4.5 3.5L11 16l-7.2 1.8a1 1 0 0 0-.8.8 1 1 0 0 0 .8.8L11 16l3.5 4.5C16 22 18 22 18 20s-2-3-3.5-4.5L16 11l1.8 7.2Z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Don't miss your flight</h3>
                                    </div>
                                    <ul class="space-y-2 text-sm text-gray-700">
                                        <li class="flex items-start">
                                            <span class="mr-3 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                                            <span>For international flights, arrive at least <span class="font-semibold">4 hours</span> before departure.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span class="mr-3 mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-gray-400"></span>
                                            <span>For domestic flights, arrive at least <span class="font-semibold">2 hours</span> before departure.</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Boarding pass & baggage -->
                                <div>
                                    <div class="mb-3 flex items-center">
                                        <div class="mr-3 h-6 w-6 text-primary">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-6 w-6">
                                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                                                <path d="M3 6h18" />
                                                <path d="M16 10a4 4 0 0 1-8 0" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Baggage & check-in</h3>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        Baggage allowance is subject to airline rules. For extra baggage or boarding pass assistance, please contact support.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
@media print {
    @page {
        margin: 0.5in;
        size: A4;
    }

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

    button,
    .fixed,
    .sticky,
    .print\:hidden {
        display: none !important;
    }

    .print\:break-inside-avoid {
        page-break-inside: avoid !important;
    }

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

    .text-primary {
        color: #2563eb !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    thead tr,
    th {
        background-color: white !important;
        border-bottom: 1px solid #d1d5db !important;
        font-weight: 600 !important;
    }

    table,
    td,
    th {
        border-color: #e5e7eb !important;
    }

    img {
        max-height: 4rem !important;
        width: auto !important;
    }
}
</style>
