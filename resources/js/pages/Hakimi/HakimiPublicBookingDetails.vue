<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { MoveRight, CircleChevronRight, DollarSign, PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff, PrinterIcon, MailIcon, Download } from "lucide-vue-next";
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
import { cn, formatAmount, formatAmountWithCurrency, calculateLayoverDetails, calculateFinalPrice } from "@/lib/utils";

import {
    FETCH_BOOKING_DATA,
    FETCH_AGENT_DATA,
    FETCH_BOOKING_DETAILS,
    CANCEL_BOOKING,
    FETCH_CUSTOMER_BOOKING_DETAILS,
    FETCH_CUSTOMER_MARGIN,
    SAVE_REQUEST,
    FETCH_MODIFY_REQUEST_DATA,
    SEND_REPLY,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { ChatBubbleIcon } from "@radix-icons/vue";
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";
import apiService from "@/config/axios";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const booking_id = route.query.booking_id || route.query.id;
const pnr = route.query.pnr;

const isBookingDetailsLoading = ref(true);
const actionLoading = ref(false);
const error = ref(null);
const localDetails = ref(null);

const isLoading = computed(() => isBookingDetailsLoading.value);

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const customerBooking = computed(() => store.getters["flight/customerBooking"]);

const booking = computed(() => {
    if (Array.isArray(customerBooking.value) && customerBooking.value.length > 0) {
        return customerBooking.value[0];
    }
    if (localDetails.value?.booking) {
        return localDetails.value.booking;
    }
    return null;
});

const flightData = ref(null);
const isDetailsInfoVisible = ref(true);

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

const lockedBookingMoney = computed(() => {
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

function formatLockedBookingAmount() {
    if (!lockedBookingMoney.value || Number.isNaN(lockedBookingMoney.value.amount)) {
        return "Price unavailable";
    }

    return formatAmountWithCurrency(
        lockedBookingMoney.value.amount,
        lockedBookingMoney.value.currency,
    );
}

function formatSelectedFareMoney(fare) {
    const money = fare?.display_money ?? fare?.base_money;

    if (money?.currency && Number.isFinite(Number(money.amount))) {
        return formatAmountWithCurrency(money.amount, money.currency);
    }
    if (fare?.total_price && fare?.currency?.code) {
        return formatAmountWithCurrency(fare.total_price, fare.currency.code);
    }

    return formatLockedBookingAmount();
}

function formatBookingAddOnsAmount(amount) {
    if (Number(amount || 0) === 0 && lockedBookingMoney.value) {
        return formatAmountWithCurrency(0, lockedBookingMoney.value.currency);
    }
    return formatAmount(amount || 0);
}

const isDialogOpen = ref(false);
const isChatOpen = ref(false);
const modifyDialogOpen = ref(false);
const selectedReason = ref("");
const message = ref("");
const replyMessage = ref("");
const replyLoading = ref(false);

const modifyRequestData = computed(() => store.getters["modifyRequest/requestData"]);

const now = ref(Date.now());

const getRemainingTime = (expiry) => {
    if (!expiry) return "N/A";
    const expiryTime = new Date(String(expiry).replace(" ", "T")).getTime();
    const diff = expiryTime - now.value;
    if (diff <= 0) return "Expired";

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    let result = "";
    if (days > 0) result += `${days}d `;
    if (hours > 0 || days > 0) result += `${hours}h `;
    result += `${minutes}m ${seconds.toString().padStart(2, "0")}s`;

    return result;
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
    if (booking_id) {
        store.dispatch(`modifyRequest/${FETCH_MODIFY_REQUEST_DATA}`, {
            booking_id: booking_id,
        });
    }
}

function sendReply() {
    if (!replyMessage.value.trim()) return;

    if (user_id.value == null) {
        handleLogin();
        return;
    }

    replyLoading.value = true;

    const userMessage = {
        req_id: modifyRequestData.value?.id,
        sender: "user",
        sender_id: user_id.value,
        message: replyMessage.value,
    };

    store.dispatch(`modifyRequest/${SEND_REPLY}`, userMessage).then(() => {
        fetchModifyRequestData();
        replyMessage.value = "";
        replyLoading.value = false;
    }).catch(() => {
        replyLoading.value = false;
    });
}

const submitModifyRequest = () => {
    if (!user_id.value) {
        handleLogin();
        return;
    }
    if (!selectedReason.value) {
        alert("Please select a reason");
        return;
    }
    if (!message.value.trim()) {
        alert("Please enter a message");
        return;
    }

    const payload = {
        booking_id: booking.value?.id || booking_id,
        reason: selectedReason.value,
        description: message.value,
        user_id: user_id.value,
    };

    store.dispatch(`modifyRequest/${SAVE_REQUEST}`, payload).then(() => {
        closeDialog();
        fetchModifyRequestData();
        isChatOpen.value = true;
    }).catch((err) => {
        alert(err?.response?.data?.message || "Failed to submit request");
    });
};

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
        await store.dispatch(`flight/${FETCH_CUSTOMER_BOOKING_DETAILS}`, {
            bookingId: booking_id,
            bookingSource: route.query.booking_source,
        });

        if (booking.value) {
            flightData.value = parseFlightData(booking.value.flight_data);
        } else {
            const response = await apiService.get("/hakimi-booking-details", {
                params: { booking_id },
            });
            localDetails.value = response.data;
            flightData.value = response.data?.flight || parseFlightData(response.data?.booking?.flight_data);
        }
    } catch (err) {
        try {
            const response = await apiService.get("/hakimi-booking-details", {
                params: { booking_id },
            });
            localDetails.value = response.data;
            flightData.value = response.data?.flight || parseFlightData(response.data?.booking?.flight_data);
        } catch (fallbackErr) {
            error.value = "Failed to fetch booking details.";
        }
    } finally {
        isBookingDetailsLoading.value = false;
    }
}

async function handleCancelBooking() {
    error.value = "";
    actionLoading.value = true;

    try {
        await store.dispatch(`flight/${CANCEL_BOOKING}`, {
            pnr: pnr || hakimi.value?.hakimiBookingNo || booking.value?.itinerary_ref,
            bookingId: booking.value?.id,
            booking_source: "hakimi",
            flight_provider: "hakimi",
        });

        isDialogOpen.value = false;
        await fetchBookingDetails();
    } catch (err) {
        error.value = err?.response?.data?.message || err?.message || "Failed to cancel booking";
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

const toggleChatOpen = () => {
    isChatOpen.value = !isChatOpen.value;
};

function goToPaymentView() {
    router.push({
        name: "CustomerPaymentView",
        query: {
            booking_id: booking.value?.id || booking_id,
            flight_mode: "B2C",
            flight_provider: "hakimi",
            booking_source: route.query.booking_source || booking.value?.booking_source || "web",
            pnr: pnr || hakimi.value?.hakimiBookingNo || booking.value?.itinerary_ref,
        },
    });
}

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

onMounted(() => {
    if (user.value == null) {
        authStore.fetchUser();
    }
    setInterval(() => {
        now.value = Date.now();
    }, 1000);
    fetchModifyRequestData();
    fetchBookingDetails();
});
</script>

<template>
    <section>
        <div class="mx-auto min-h-screen max-w-7xl bg-gray-100 p-4">
            <!-- Action Buttons Top Bar (Hidden in Print) -->
            <div
                v-if="booking"
                class="mb-4 flex flex-wrap items-center justify-end gap-2 rounded-lg bg-white p-3 py-4 shadow-sm print:hidden"
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
                    :disabled="!!modifyRequestData"
                    @click="openModifyRequestDialog"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Modify Request
                </button>

                <button
                    :disabled="['ticketed', 'issued', 'canceled', 'voided'].includes(String(booking.status).toLowerCase())"
                    @click="goToPaymentView"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <DollarSign class="h-4 w-4" />
                    Pay Now
                </button>

                <button
                    :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(String(booking.status).toLowerCase())"
                    @click="isDialogOpen = true"
                    class="flex items-center gap-2 rounded-md bg-primary/90 px-4 py-2 text-sm font-medium text-white hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Cancel Booking
                </button>

                <button
                    @click="toggleChatOpen"
                    class="flex items-center gap-2 rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                >
                    <ChatBubbleIcon class="h-4 w-4" />
                    Chat
                </button>
            </div>

            <!-- Cancel Booking Dialog -->
            <div
                v-if="isDialogOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4"
                @click.self="isDialogOpen = false"
            >
                <div class="w-full max-w-md transform rounded-lg bg-white p-6 shadow-xl transition-all">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                        <button @click="isDialogOpen = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
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
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            @click="handleCancelBooking"
                            :disabled="actionLoading"
                            class="rounded-md border border-transparent bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ actionLoading ? "Cancelling..." : "Confirm Cancellation" }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Flow Loader -->
            <ATFlowLoader
                v-if="isLoading"
                title="Loading Hakimi booking details"
                message="We are refreshing your Hakimi booking, flight itinerary, and customer fare details."
                :steps="['Booking', 'PNR', 'Details']"
            />

            <!-- Error fallback -->
            <div v-else-if="error && !booking" class="rounded-xl border border-red-200 bg-red-50 p-6 text-center text-red-700">
                <p class="font-medium">{{ error }}</p>
                <button
                    class="mt-4 rounded-md bg-primary px-4 py-2 text-sm text-white hover:bg-primary/90"
                    @click="router.back()"
                >
                    Back to Bookings
                </button>
            </div>

            <!-- Main Content Area with Chat Drawer -->
            <div v-else-if="booking" class="flex gap-4">
                <!-- Main Print Section Container -->
                <div :class="isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full'" id="print-section">
                    <!-- Test Booking Banner -->
                    <div
                        v-if="hakimi?.testBooking !== false"
                        class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-center text-xs font-semibold text-amber-900 print:hidden"
                    >
                        Hakimi test booking — not valid for travel.
                    </div>

                    <div
                        class="mb-4 overflow-hidden rounded-lg bg-white shadow-sm print:mb-0 print:rounded-none print:border print:border-gray-300 print:shadow-none"
                    >
                        <!-- E-Ticket Header -->
                        <div class="p-6 print:border-b print:border-gray-300 print:bg-white print:text-black">
                            <div class="flex items-center justify-between">
                                <div class="w-1/4">
                                    <img class="h-16 w-auto print:h-12" src="/public/assets/logo.png" alt="Logo" />
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

                                        <div
                                            v-if="String(booking.status).toLowerCase() === 'booked' && booking.expiry_time"
                                            class="mt-1 text-sm opacity-90 print:text-gray-700"
                                        >
                                            <span class="font-medium">Expiry Time: </span>
                                            <span
                                                class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700"
                                            >
                                                {{ getRemainingTime(booking.expiry_time) }}
                                            </span>
                                        </div>
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
                                    <!-- Main Flight Card -->
                                    <div
                                        class="rounded-lg border border-gray-200 p-6 transition-shadow hover:shadow-sm print:mb-6 print:rounded-none print:border-gray-400 print:p-8 print:text-base print:shadow-none"
                                    >
                                        <div
                                            v-for="(segment, segmentIndex) in flight?.segments"
                                            :key="segmentIndex"
                                            class="overflow-hidden bg-white"
                                        >
                                            <!-- Layover Badge -->
                                            <div v-if="segment?.layover_time" class="mb-3 flex justify-center">
                                                <div
                                                    class="rounded-full bg-blue-100 px-4 py-1.5 text-xs font-medium text-blue-800 shadow-sm sm:text-sm"
                                                >
                                                    Layover Time:
                                                    {{ Math.floor(segment.layover_time / 60) + "h " + (segment.layover_time % 60) + "m" }}
                                                </div>
                                            </div>

                                            <!-- Segment Details Grid -->
                                            <div class="p-2 sm:p-3 lg:p-6">
                                                <div class="flex items-center justify-center gap-6">
                                                    <div class="grid w-full grid-cols-7 items-center gap-4">
                                                        <!-- Carrier Logo -->
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

                                                        <!-- Departure (From) -->
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

                                                        <!-- Duration Arrow -->
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

                                                        <!-- Arrival (To) -->
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

                                                <!-- Segment Details Footer -->
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

                        <!-- Fare Breakdown Section -->
                        <div
                            class="border-b border-gray-200 bg-white p-6 print:break-inside-avoid print:border-gray-300"
                            v-if="isDetailsInfoVisible"
                        >
                            <h2 class="mb-4 text-base font-extrabold text-slate-950 print:text-gray-900">
                                FARE BREAKDOWN
                            </h2>

                            <div class="overflow-x-auto">
                                <table class="w-full border border-gray-300 text-xs print:rounded-none print:border-gray-400">
                                    <thead>
                                        <tr class="border-b border-gray-300 print:border-gray-400 print:bg-transparent">
                                            <th class="px-3 py-2 text-left font-bold text-gray-800 print:bg-transparent print:text-gray-900">
                                                Sector
                                            </th>
                                            <th class="px-3 py-2 text-left font-bold text-gray-800 print:bg-transparent print:text-gray-900">
                                                Subtotal
                                            </th>
                                            <th class="px-3 py-2 text-left font-bold text-gray-800 print:bg-transparent print:text-gray-900">
                                                Taxes + Fees
                                            </th>
                                            <th class="px-3 py-2 text-left font-bold text-gray-800 print:bg-transparent print:text-gray-900">
                                                Grand Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 print:divide-gray-400">
                                        <template
                                            v-for="(flight, index) in (flightData?.original?.leg?.flights ?? flightData?.leg?.flights ?? [])"
                                            :key="index"
                                        >
                                            <tr
                                                v-for="(fare, fareIndex) in flight.fares || []"
                                                :key="fareIndex"
                                                class="hover:bg-gray-50 print:bg-transparent"
                                            >
                                                <td class="px-3 py-3 uppercase print:text-gray-800">
                                                    {{ flight.segments?.[0]?.from?.iata }} →
                                                    {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                                </td>
                                                <td class="px-3 py-3 uppercase print:text-gray-800">
                                                    {{ formatSelectedFareMoney(fare) }}
                                                </td>
                                                <td class="px-3 py-3 uppercase print:text-gray-800">
                                                    {{ formatAmountWithCurrency(calculateTaxes(fare), lockedBookingMoney?.currency || 'INR') }}
                                                </td>
                                                <td class="px-3 py-3 font-bold uppercase print:text-gray-900">
                                                    {{ formatSelectedFareMoney(fare) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot class="border-t border-gray-400 bg-gray-50 print:bg-transparent">
                                        <tr>
                                            <td colspan="3" class="px-3 py-3 text-right text-sm font-bold text-gray-900">
                                                Add-ons
                                            </td>
                                            <td class="px-3 py-3 text-sm font-bold text-primary">
                                                {{ formatBookingAddOnsAmount(booking?.add_ones_amount) }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" class="px-3 py-3 text-right text-sm font-bold text-gray-900">
                                                Total Amount
                                            </td>
                                            <td class="px-3 py-3 text-sm font-bold text-primary">
                                                {{ formatLockedBookingAmount() }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Important Information -->
                        <div class="border-b border-gray-200 p-6 print:break-inside-avoid print:border-gray-300">
                            <h2 class="mb-4 text-lg font-bold text-gray-800 print:text-gray-900">
                                Important Information
                            </h2>

                            <div
                                class="rounded-lg border border-amber-200 bg-amber-50 p-5 print:border-gray-300 print:bg-transparent"
                            >
                                <ul class="space-y-3 text-sm text-gray-700 print:text-gray-800">
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500 print:bg-gray-600"></span>
                                        <span>Please verify your travel documents (passport validity minimum 6 months, required visas) before traveling.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500 print:bg-gray-600"></span>
                                        <span>We recommend arriving at the airport at least 3 hours prior for domestic flights and 4 hours prior for international flights.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500 print:bg-gray-600"></span>
                                        <span>Excess baggage beyond specified allowance is subject to airline charges payable at the check-in counter.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="mr-3 mt-1.5 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-amber-500 print:bg-gray-600"></span>
                                        <span>Flight schedules are subject to airline operational requirements and regulatory authorities.</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Drawer / Sidebar -->
                <div v-if="isChatOpen && modifyRequestData" class="w-4/12 flex-shrink-0 print:hidden">
                    <div class="flex h-[600px] flex-col rounded-lg border border-gray-200 bg-white shadow-sm">
                        <!-- Chat Header -->
                        <div class="flex items-center justify-between border-b border-gray-200 p-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">Modify Request Chat</h3>
                                <p class="text-xs text-gray-500">Status: {{ modifyRequestData?.status || "Pending" }}</p>
                            </div>
                            <button @click="isChatOpen = false" class="text-gray-400 hover:text-gray-600">
                                &times;
                            </button>
                        </div>

                        <!-- Chat Messages List -->
                        <div class="flex-1 space-y-4 overflow-y-auto bg-gray-50/50 p-4">
                            <div
                                v-if="!parseFlightData(modifyRequestData?.messages)?.length"
                                class="py-8 text-center text-sm text-gray-500"
                            >
                                No conversation messages yet.
                            </div>
                            <div
                                v-else
                                v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)"
                                :key="index"
                                class="flex"
                                :class="msg.sender === 'user' ? 'justify-end' : 'justify-start'"
                            >
                                <div
                                    class="max-w-[85%] rounded-lg px-4 py-3 text-sm shadow-sm"
                                    :class="msg.sender === 'user' ? 'bg-primary/10 text-gray-800' : 'border border-gray-200 bg-white text-gray-800'"
                                >
                                    <p class="whitespace-pre-wrap">{{ msg?.message }}</p>
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ moment(msg?.created_at).format("DD MMM YYYY, HH:mm") }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Reply Input -->
                        <div class="border-t border-gray-200 p-4">
                            <form @submit.prevent="sendReply">
                                <textarea
                                    v-model="replyMessage"
                                    rows="2"
                                    placeholder="Type your message..."
                                    class="w-full resize-none rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                    required
                                ></textarea>
                                <div class="mt-2 flex justify-end">
                                    <button
                                        type="submit"
                                        :disabled="replyLoading"
                                        class="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-60"
                                    >
                                        <span
                                            v-if="replyLoading"
                                            class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                                        ></span>
                                        Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modify Request Modal Dialog -->
            <div
                v-if="modifyDialogOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4 print:hidden"
                @click.self="closeDialog"
            >
                <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                    <h2 class="mb-5 text-xl font-semibold text-gray-900">Modify Request</h2>

                    <form @submit.prevent="submitModifyRequest" class="space-y-5">
                        <div>
                            <label class="mb-3 block text-sm font-medium text-gray-700">
                                Reason for modification
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="selectedReason"
                                        value="refund"
                                        class="h-4 w-4 border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="ml-3 text-sm text-gray-700">Request Refund</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="selectedReason"
                                        value="change_scope"
                                        class="h-4 w-4 border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="ml-3 text-sm text-gray-700">Change Flight Date / Sector</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        type="radio"
                                        v-model="selectedReason"
                                        value="other"
                                        class="h-4 w-4 border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <span class="ml-3 text-sm text-gray-700">Other Modification</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label for="modifyMessage" class="mb-2 block text-sm font-medium text-gray-700">
                                Description <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                id="modifyMessage"
                                v-model="message"
                                rows="4"
                                placeholder="Please explain the changes you need..."
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                                required
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="closeDialog"
                                class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
                            >
                                Submit Request
                            </button>
                        </div>
                    </form>
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
