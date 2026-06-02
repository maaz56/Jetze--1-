<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Plane, Users, Briefcase, ClockIcon, PrinterIcon, Receipt } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";
import { computed, onMounted, ref, nextTick, watch } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import html2pdf from "html2pdf.js";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import Spinner from "@/components/common/Spinner.vue";
import { loadStripe } from "@stripe/stripe-js";
import axios from "axios";
import {
    FETCH_BOOKING_DETAILS,
    SEND_PAYMENT_REQUEST,
    CONFIRM_BOOKING,
    FETCH_CUSTOMER_MARGIN,
    FETCH_CUSTOMER_BOOKING_DETAILS,
    UPDATE_BOOKING_AMOUNT,
    FETCH_PNR_DETAILS,
} from "@/services/store/actions.type";
import { calculateFinalPrice, formatAmount } from "@/lib/utils";
import { toast } from "vue3-toastify";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Reactive state
const error = ref(null);
const showPaymentDialog = ref(false);
const processing = ref(false);
const paymentError = ref("");
const clientSecret = ref("");
const amount = ref(0);
const loading = ref(false);
const priceMismatchDialog = ref(false);
const selectedFares = ref([]);
const totalTicketPrice = ref(0);
const surcharge = ref(0);
const priceDifference = ref(0);
const paymentMethod = ref();
// Stripe
const stripe = ref(null);
const elements = ref(null);
const cardElement = ref(null);
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);

// Computed
const user = computed(() => authStore.user);
const bookingDetails = computed(() => store.getters["flight/customerBookingDetails"]);
const customerMargin = computed(() => store.getters["customerMargin/customerMargin"]);
const isConfirmed = computed(() => store.getters["flight/isConfirmed"]);
const pnrDetails = computed(() => store.getters["flight/pnrData"]);
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const isLoading = computed(() => store.getters['flight/isLoading']);



// Data
const pnrData = ref(null);
const sooperResponse = ref(null);
const flightData = ref(null);

let booking_id = route.query.booking_id;
let pnr = route.query.pnr;
let booking_source = route.query.booking_source;
let flight_provider = route.query.flight_provider || route.query.provider || null;


// Fetch functions
async function fetchCustomerMarginValues() {
    try {
        await store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
    } catch (err) {
        console.error("Failed to fetch customer margin", err);
    }
}

async function fetchBookingDetails() {
    if (!booking_id) {
        error.value = "No booking ID provided.";
        return;
    }

    try {
        await store.dispatch(`flight/${FETCH_CUSTOMER_BOOKING_DETAILS}`, {
            bookingId: booking_id,
            bookingSource: booking_source,
        });

        const booking = bookingDetails.value?.[0];
        if (booking) {
            parseResponses();
            flightData.value = parseFlightData(booking.flight_data);
            amount.value = calculatePnrFinalFare();
        }
    } catch (err) {
        console.log(err);
        error.value = "Failed to fetch booking details.";
        toast.error(error.value);
    } finally {
    }
}

function parseResponses() {
    const booking = bookingDetails.value?.[0];
    if (!booking) return;
    try { selectedFares.value = JSON.parse(booking.fare_reference || null); } catch { }
    try { pnrData.value = JSON.parse(booking.pnr_response || null); } catch { }
    try { sooperResponse.value = JSON.parse(booking.sooper_response || null); } catch { }
    fetchPnrDetails();
}

function parseFlightData(data) {
    try { return JSON.parse(data); } catch { return {}; }
}

// Margin & Fare Calculations
const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
    const total = parseFloat(price) || 0;
    const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
    const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

    return discountPercentage && parseFloat(discountPercentage) > 0 ? -discount : margin;
};

const calculateFareMargin = (basePrice, marginAmount, marginType, amountType) => {
    const price = parseFloat(basePrice) || 0;
    let margin = 0;

    if (marginType === "discount") {
        if (amountType === "percent") {
            margin = -((price * (parseFloat(marginAmount) || 0)) / 100);
        } else {
            margin = -(parseFloat(marginAmount) || 0);
        }
    } else if (marginType === "markup") {
        if (amountType === "percent") {
            margin = (price * (parseFloat(marginAmount) || 0)) / 100;
        } else {
            margin = parseFloat(marginAmount) || 0;
        }
    }

    return margin;
};
function priceReValidation(bookedPrice, pnrPrice) {
    if ((parseFloat(bookedPrice)).toFixed(2) !== (parseFloat(pnrPrice)).toFixed(2)) {
        console.log('has Differnece');
        priceDifference.value = (parseFloat(pnrPrice) - parseFloat(bookedPrice)).toFixed(2);
        return false;
    }
    console.log('No differnece')
    return true;
}
function calculateBookedPrice() {
    flightData.value.leg.flights.forEach((flightItem, flightIndex) => {
        flightItem.fares.forEach(fare => {
            console.log('Selected Fares:', selectedFares.value);
            if (selectedFares.value.includes(fare.ref_id)) {
                const farePrice = fare.billable_price;
                totalTicketPrice.value += farePrice;


                let total = 0
                const billablePrice = pnrData?.value?.data?.billable_price;
                const airlineMargin = calculateFareMargin(
                    fare.base_price,
                    fare.margin_amount || 0,
                    fare.margin_type,
                    fare.amount_type
                );
                const passengerCount = parseInt(bookingDetails?.value?.[0].pessangers.length || 1);
                const agentMargin = parseFloat(bookingDetails?.value?.[0].agent_markup || 0);
                const agentDiscount = parseFloat(bookingDetails?.value?.[0].agent_discount || 0);
                const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);
                const airportMargin = parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0);
                const billable = parseFloat(fare.base_price) + parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0);


                total += parseFloat(billablePrice || 0)
                    + (parseFloat(agentMargin || 0)
                        - parseFloat(agentDiscount || 0)
                        + parseFloat(airlineMargin || 0)
                        + parseFloat(airportMargin || 0)
                        + parseFloat(margin || 0)) * passengerCount;
                surcharge.value = billablePrice - billable;
                amount.value = parseFloat(total.toFixed(2));
                console.log(amount);
            }
        });
    });
}
function calculatePassengerFare(passenger, flightIndex) {
    const flight = flightData.value?.original?.leg?.flights?.[flightIndex] ?? flightData.value?.leg?.flights?.[flightIndex];
    const fare = flight?.fares?.[0];
    if (!fare) return 0;

    const basePrice = parseFloat(passenger?.base_price || 0);
    const systemFare = basePrice + calculateFareMargin(basePrice, fare.margin_amount, fare.margin_type, fare.amount_type);

    // Apply customer margin (discount or markup)
    const custDiscount = customerMargin.value?.discount_percentage || 0;
    const custMarkup = customerMargin.value?.margin_percentage || 0;
    const customerAdjustment = calculateCustomerMargin(basePrice, custDiscount, custMarkup);

    return systemFare + customerAdjustment;
}

function calculatePassengerTaxes(passenger) {
    return (
        parseFloat(passenger?.fee || 0) +
        parseFloat(passenger?.taxes || 0) +
        parseFloat(passenger?.surcharge || 0) +
        parseFloat(passenger?.service_charges || 0) +
        parseFloat(passenger?.ancillaries_charges || 0)
    );
}

function calculatePassengerFinalFare(passenger, flightIndex) {
    return calculatePassengerFare(passenger, flightIndex) + calculatePassengerTaxes(passenger);
}

function calculatePnrFinalFare() {
    const legs = pnrData.value?.data?.providers?.[0]?.legs || [];
    if (!legs.length) return 0;

    let total = 0;

    legs.forEach((leg) => {
        leg.passengers?.forEach((pax, idx) => {
            const flightIdx = leg.flight_index || idx; // fallback
            total += calculatePassengerFinalFare(pax, flightIdx);
        });
    });

    return total;
}

// Helpers
const getFlightLegs = (booking) => {
    const data = parseFlightData(booking?.flight_data);
    return data?.original?.leg?.flights ?? data?.leg?.flights ?? [];
};

const getPassengers = (booking) => {
    if (sooperResponse.value?.data?.booking?.booking?.providers?.[0]?.legs) {
        return sooperResponse.value.data.booking.booking.providers[0].legs
            .flatMap(l => l.passengers.map(p => p.passenger));
    }
    return booking.pessangers ?? [];
};

const getFareLegs = () => pnrData.value?.data?.providers?.[0]?.legs ?? [];
const getBaggageLegs = () => pnrData.value?.data?.providers?.[0]?.legs ?? [];
const getBaggage = (pax, type) => pax.baggage_policies?.find(bp => bp.type === type)?.description ?? 'N/A';

const formatDateTime = (date) => moment.parseZone(date).format('DD MMM YYYY, HH:mm');
const formatDuration = (minutes) => moment.utc(moment.duration(minutes, 'minutes').asMilliseconds()).format('HH[h] mm[m]');
const formatFlightDuration = (segment) => {
    const diff = moment(segment.arrival_at).diff(moment(segment.departure_at), 'minutes');
    return moment.utc(moment.duration(diff, 'minutes').asMilliseconds()).format('HH[h] mm[m]');
};

// Payment Methods
function handlePaymentMethod(type) {

    if (type === "stripe") {
        openPaymentDialog();
    } else if (type === "alrajhi") {
        payNow();
    }
}
function updateBookingAmount() {
    store.dispatch("flight/" + UPDATE_BOOKING_AMOUNT, {
        booking_id: booking_id,
        amount: amount.value,
    })

}
// Alrajhi Payment
const payNow = async () => {
    loading.value = true;
    try {
        const { data } = await axios.post('/api/arb/initiate', {
            amount: amount.value || calculatePnrFinalFare(),
            booking_id: booking_id,
            flight_provider: flight_provider,
            booking_uuid: pnrData.value?.data?.uuid ?? "null",
            pnr: pnr,
            flight_mode: 'B2C',
            booking_source: booking_source,
        });

        if (data.redirect_url) {
            window.location.href = data.redirect_url;
        } else {
            toast.error('Payment initialization failed.');
        }
    } catch (error) {
        console.error(error);
        toast.error('Error initiating Alrajhi payment');
    } finally {
        loading.value = false;
    }
};

// Stripe Payment
const openPaymentDialog = async () => {
    showPaymentDialog.value = true;
    await nextTick();
    await initializeStripe();

    const container = document.getElementById('card-element');
    if (container && !container.hasChildNodes()) {
        cardElement.value.mount('#card-element');
    }
};

const closePaymentDialog = () => {
    showPaymentDialog.value = false;
    paymentError.value = "";
    if (cardElement.value) cardElement.value.clear();
};

watch(isConfirmed, () => {
    if (isConfirmed.value == true) {
        router.push({
            name: "CustomerBookingDetails", // Replace with the name of your route
            query: {
                flight_id: route.query.flight_id,
                booking_id: bookingDetails?.value?.[0].id,
                pnr: bookingDetails?.value?.[0].itinerary_ref,
                flight_mode: "B2C",
                booking_source: route.query.booking_source,
                flight_provider: route.query.flight_provider,
            }, // Pass relevant query parameters if needed
        });
    }
});

const initializeStripe = async () => {
    if (stripe.value) return;

    try {
        stripe.value = await loadStripe(publicKey.value);
        if (!stripe.value) throw new Error("Stripe failed to load");

        elements.value = stripe.value.elements();
        cardElement.value = elements.value.create('card', {
            style: {
                base: { fontSize: '16px', color: '#32325d', '::placeholder': { color: '#aab7c4' } },
            },
        });

        cardElement.value.on('change', (event) => {
            paymentError.value = event.error ? event.error.message : '';
        });
    } catch (err) {
        console.error(err);
        toast.error("Failed to load payment form.");
    }
};

const handlePayment = async () => {
    if (!stripe.value || !cardElement.value) {
        toast.error("Payment form not ready.");
        return;
    }

    processing.value = true;

    try {
        const currency = localStorage.getItem("currencyCode");

        const response = await store.dispatch('flight/' + SEND_PAYMENT_REQUEST, {
            amount: Math.round(totalTicketPrice.value * 100),
            currency: currency || 'SAR',
            booking_id: booking_id,
        });

        clientSecret.value = response?.clientSecret;
        if (!clientSecret.value) throw new Error("No client secret");

        const result = await stripe.value.confirmCardPayment(clientSecret.value, {
            payment_method: { card: cardElement.value },
        });

        if (result.error) {
            paymentError.value = result.error.message;
            toast.error(result.error.message);
        } else {
            toast.success("Payment successful!");
            closePaymentDialog();
            confirmBooking();
        }
    } catch (err) {
        console.log(err);
        toast.error("Payment failed. Try again.");
    } finally {
        processing.value = false;
    }
};
const closeDialogue = () => {
    isOpen.value = false;
    updateBookingAmount();

    router.push({
        name: "CustomerBookingDetails", // Replace with the name of your route
        query: {
            booking_id: bookingDetails?.value?.id,
            pnr: bookingDetails?.value?.itinerary_ref,
            fare_reference: selectedFares.value,
            flight_mode: "B2B",
            booking_source: route.query.flight_source,
            flight_provider: route.query.flight_provider,
        }, // Pass relevant query parameters if needed
    });
};
// Confirm Booking
function confirmBooking() {



    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: route.query.pnr,
        bookingId: bookingDetails.value[0].id,
        TUI: pnrData.value?.TUI ?? "null",
        TransactionID: pnrData.value?.TransactionID ?? "null",
        net_amount: pnrData.value?.NetAmount ?? "null",
        booking_status: "ticketed",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
        totalTicketPrice: totalTicketPrice.value,
    }).then(() => {
        fetchBookingDetails();
    }).catch(() => {
        toast.error("Failed to confirm booking.");
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
const agentAmount = parseFloat(bookingDetails?.value?.[0].agent_markup || 0);
const agentDiscount = parseFloat(bookingDetails?.value?.[0].agent_discount || 0);
const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);
const airportMargin = parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0);
function calculateTotalFare(fare) {

    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);

    const billable = parseFloat(fare?.surchage || fare?.surcharge || 0) + parseFloat(fare?.taxes || 0) + parseFloat(fare?.fees || 0) + parseFloat(fare?.service_charges || 0) + parseFloat(fare?.ancillaries_charges || 0) + (parseFloat(airlineMargin));

    const total = billable + (agentAmount * passengerCount) + margin - (agentDiscount * passengerCount) + (airportMargin * passengerCount);
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

// Print & PDF
const printBooking = () => window.print();
const downloadPDF = () => {
    const element = document.getElementById("print-section");
    html2pdf().from(element).set({
        margin: 5,
        filename: `booking_${booking_id}.pdf`,
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    }).save();
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
            console.log('called GrandTotal')
            calculateGrandTotal();
        });
    } catch (err) {
        error.value = "Failed to fetch PNR details.";
    } finally {
        isPnrDetailsLoading.value = false;
    }
}
// Lifecycle
onMounted(async () => {
    await Promise.all([
        fetchCustomerMarginValues(),
        fetchBookingDetails(),
        fetchPnrDetails(),


    ]);
});
</script>

<template>
    <div v-if="isLoading " class="fixed inset-0 bg-white/75 flex items-center justify-center z-50">
        <Spinner />
    </div>
    <div v-else-if="!isLoading && bookingDetails" class=" min-h-screen bg-gray-50 py-6 print:bg-white relative z-50 top-20 print:py-0">
       
        <div  class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 print:px-4">
            <!-- Header -->
            <div class="bg-white shadow-sm rounded-lg mb-6 print:shadow-none print:border print:border-gray-300">
                <div class="p-6 border-b border-gray-200 print:border-b print:border-gray-300">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">Booking Preview</h1>
                            <p class="text-sm text-gray-600 mt-1">Review your flight details before confirmation</p>
                        </div>
                        <div class="hidden print:flex items-center gap-2 text-gray-700 text-sm">
                            <PrinterIcon class="h-5 w-5" />
                            <span>Print-friendly</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6 print:hidden">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Complete Your Booking</h2>
                <div class="space-y-4">
                    <Button 
                        class="w-full bg-primary hover:bg-primary/90 text-white font-medium">
                        Pay & Confirm Booking ({{ formatAmount(totalTicketPrice) }})
                    </Button>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">


                        <button @click="handlePaymentMethod('stripe')"
                            class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 transition">
                            <img src="/public/assets/credit-card.png" alt="Card" class="h-8 w-8 mb-2" />
                            <span class="text-sm font-medium">Credit Card (Stripe)</span>
                        </button>

                        <button @click="handlePaymentMethod('alrajhi')"
                            class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 transition">
                            <img src="/public/assets/alrajhi.png" alt="Alrajhi" class="h-7 w-7 mb-2" />
                            <span class="text-sm font-medium">Alrajhi Bank</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Rest of your print section (unchanged) -->
            <div id="print-section" class="space-y-6">

                <!-- Flight Itinerary -->
                <div
                    class="bg-white rounded-lg shadow-sm border border-gray-200 print:shadow-none print:border print:border-gray-300">
                    <div
                        class="bg-primary text-white p-4 rounded-t-lg print:bg-white print:text-gray-900 print:border-b">
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <Plane class="h-5 w-5" /> Flight Itinerary
                        </h2>
                    </div>
                    <div class="p-5 space-y-6">
                        <div v-for="(booking, idx) in bookingDetails" :key="booking?.id">
                            <div v-for="(flight, fIdx) in getFlightLegs(booking)" :key="fIdx" class="space-y-4">
                                <div class="text-sm font-medium text-gray-700">
                                    {{ flight?.from?.city?.name }} ({{ flight?.from?.iata }}) to {{
                                        flight?.to?.city?.name }} ({{ flight?.to?.iata
                                    }})
                                </div>
                                <div v-for="(segment, sIdx) in flight.segments" :key="sIdx">
                                    <div v-if="segment.layover_time"
                                        class="bg-amber-50 border-l-4 border-amber-500 p-3 mb-3 rounded-r text-xs">
                                        <div class="flex items-center justify-center text-amber-800">
                                            <ClockIcon class="h-4 w-4 mr-1" />
                                            Layover: {{ formatDuration(segment.layover_time) }}
                                        </div>
                                    </div>
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg print:bg-white">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2">
                                                <img :src="segment.operating_carrier.logo" class="h-7 w-7 rounded" />
                                                <div>
                                                    <p class="text-sm font-semibold">{{ segment.operating_carrier.name
                                                    }}</p>
                                                    <p class="text-xs text-gray-600">{{ segment.flight_number }}</p>
                                                </div>
                                            </div>
                                            <p class="font-medium text-gray-900">{{ formatDateTime(segment.departure_at)
                                            }}</p>
                                            <p class="text-sm text-gray-600">{{ segment.from.name }} ({{
                                                segment.from.iata }})</p>
                                            <p class="text-xs text-gray-500">Terminal: {{ segment.from_terminal?.Gate ||
                                                'N/A' }}</p>
                                        </div>
                                        <div class="flex items-center justify-center">
                                            <div class="text-center">
                                                <p class="text-xs font-medium text-gray-700">{{
                                                    formatFlightDuration(segment) }}</p>
                                                <div class="flex items-center my-2">
                                                    <div class="w-2 h-2 bg-primary rounded-full"></div>
                                                    <div class="flex-1 h-0.5 bg-primary mx-1"></div>
                                                    <div class="w-2 h-2 bg-primary rounded-full"></div>
                                                </div>
                                                <p class="text-xs text-gray-500">{{ segment.from.iata }} to {{
                                                    segment.to.iata }}</p>
                                            </div>
                                        </div>
                                        <div class="space-y-2 text-right">
                                            <p class="font-medium text-gray-900">{{ formatDateTime(segment.arrival_at)
                                            }}</p>
                                            <p class="text-sm text-gray-600">{{ segment.to.name }} ({{ segment.to.iata
                                            }})</p>
                                            <p class="text-xs text-gray-500">Terminal: {{ segment.to_terminal?.Gate ||
                                                'N/A' }}</p>
                                            <p class="text-xs text-gray-500">{{ segment.aircraft?.model || 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Passenger Details -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 print:shadow-none print:border">
                    <div class="bg-gray-50 p-4 border-b print:bg-white print:border-b">
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <Users class="h-5 w-5 text-blue-600" /> Passenger Details
                        </h2>
                    </div>
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="pb-2">Name</th>
                                    <th class="pb-2">Type</th>
                                    <th class="pb-2">Gender</th>
                                    <th class="pb-2">Nationality</th>
                                    <th class="pb-2 hidden print:table-cell">Ticket No</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(pax, i) in getPassengers(bookingDetails?.[0])" :key="i"
                                    class="border-b last:border-0">
                                    <td class="py-3">{{ pax.title }} {{ pax.first_name }} {{ pax.last_name }}</td>
                                    <td class="py-3 text-gray-600">{{ pax.type || pax.traveler_type }}</td>
                                    <td class="py-3 text-gray-600">{{ pax.gender?.toUpperCase() }}</td>
                                    <td class="py-3 text-gray-600">{{ pax.nationality }}</td>
                                    <td class="py-3 hidden print:table-cell">{{ pax.ticket_number || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Fare Breakdown -->
                <!-- Fare Breakdown -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 print:shadow-none print:border">
                    <!-- Header -->
                    <div class="bg-gray-50 p-4 border-b print:bg-white print:border-b">
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <Receipt class="h-5 w-5 text-blue-600" />
                            Fare Breakdown
                        </h2>
                    </div>

                    <!-- Body -->
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="border-b text-left text-gray-600">
                                    <th class="pb-2">Subtotal</th>
                                    <th class="pb-2">Taxes & Fees</th>
                                    <th class="pb-2">Grand Total</th>
                                </tr>
                            </thead>

                            <!-- Case 1: PNR Fares -->
                            <tbody v-if="pnrDetails?.fares" class="divide-y divide-gray-100">
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 uppercase">
                                        {{
                                            formatAmount(
                                                calculateFinalPrice(
                                                    pnrDetails?.fares?.[0]?.totals?.subtotal,
                                                    fare?.margin_amount,
                                                    fare?.margin_type,
                                                    fare?.amount_type || 0
                                                ) +
                                                (
                                                    (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                                                        + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                                                        - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                                                    * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                                                ) +
                                                (parseFloat(bookingDetails?.[0]?.airport_margin_amount || 0) * passengerCount)
                                            )
                                        }}
                                    </td>

                                    <td class="py-3 uppercase">
                                        {{ formatAmount(pnrDetails?.fares?.[0]?.totals?.taxes) }}
                                    </td>

                                    <td class="py-3 uppercase font-semibold text-gray-900">
                                        {{
                                            formatAmount(
                                                parseFloat(pnrDetails?.fares?.[0]?.totals?.total || 0) +
                                                (
                                                    (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                                                        + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                                                        - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                                                    * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                                                ) +
                                                (parseFloat(bookingDetails?.[0]?.airport_margin_amount || 0) * passengerCount)
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>

                            <!-- Case 2: Flight Fare Breakdown -->
                            <tbody v-else class="divide-y divide-gray-100">
                                <template
                                    v-for="(flight, index) in parseFlightData(bookingDetails[0]?.flight_data)?.leg?.flights"
                                    :key="index">
                                    <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                        const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                            ? parseFlightData(bookingDetails[0]?.fare_reference)
                                            : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                        return fareRefs.includes(f.ref_id);
                                    })" :key="fareIndex" class="hover:bg-gray-50">
                                        <td class="py-3 uppercase">
                                            {{
                                                formatAmount(
                                                    calculateFinalPrice(
                                                        fare?.base_price,
                                                        fare?.margin_amount,
                                                        fare?.margin_type,
                                                        fare?.amount_type
                                                    ) +
                                                    (agentAmount * passengerCount) -
                                                    (agentDiscount * passengerCount) +
                                                    (parseFloat(bookingDetails?.[0]?.airport_margin_amount || 0) *
                                                        passengerCount) +
                                                    margin
                                                )
                                            }}
                                        </td>

                                        <td class="py-3 uppercase">
                                            {{ formatAmount(calculateTaxes(fare)) }}
                                        </td>

                                        <td class="py-3 uppercase font-semibold text-gray-900">
                                            {{ formatAmount(calculateTotalFare(fare)) }}
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <pre>{{ pnrData }}</pre>
                <!-- Baggage -->
                <div class="bg-white border border-gray-300 p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 border-b border-primary pb-2">
                        Baggage & Cancellation Details
                    </h2>

                    <!-- First Trip: DXB to BOM -->
                    <div v-for="trip in pnrData?.Trips" :key="trip" class="mb-8">
                        <div v-for="journey in trip?.Journey" :key="journey" class="space-y-6">
                            <div v-for="segment in journey?.Segments" :key="segment" class="mb-6">
                                <!-- Flight Header -->
                                <div class="text-sm font-semibold text-gray-800 mb-3">
                                    {{ segment?.Flight?.DepartureCode }} to {{ segment?.Flight?.ArrivalCode }}
                                    <span class="text-xs text-gray-600 ml-2">
                                        (FUID: {{ segment?.Flight?.FUID }})
                                    </span>
                                </div>

                                <!-- Flight Details -->
                                <div class="text-xs font-medium text-gray-700 mb-2">
                                    {{ segment?.Flight?.FlightNo }} • {{ segment?.Flight?.Airline?.split('|')[0] }}
                                    <span class="ml-3">
                                        {{ new Date(segment?.Flight?.DepartureTime).toLocaleDateString() }}
                                    </span>
                                </div>

                                <!-- Baggage Details Table -->
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Baggage Allowance</h3>
                                    <table class="w-full text-xs border-2 border-gray-400 mb-4">
                                        <thead>
                                            <tr class="bg-gray-100">
                                                <th class="py-2 px-3 text-left font-semibold text-gray-700">Pax Type
                                                </th>
                                                <th class="py-2 px-3 text-left font-semibold text-gray-700">Check-In
                                                    Baggage</th>
                                                <th class="py-2 px-3 text-left font-semibold text-gray-700">Cabin
                                                    Baggage</th>
                                                <th class="py-2 px-3 text-left font-semibold text-gray-700">Cabin
                                                    Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-300">
                                            <!-- Filter SSRs for current FUID -->
                                            <template
                                                v-for="ssr in pnrDetails?.SSR?.filter(s => s.FUID === segment?.Flight?.FUID)"
                                                :key="ssr">
                                                <tr>
                                                    <td class="py-2 px-3 uppercase">{{ ssr.PTC }}</td>
                                                    <td class="py-2 px-3">
                                                        {{ ssr.Description?.split(',')[0] || 'N/A' }}
                                                    </td>
                                                    <td class="py-2 px-3">
                                                        {{ ssr.Description?.split(',')[1]?.trim() || 'N/A' }}
                                                    </td>
                                                    <td class="py-2 px-3">
                                                        {{ formatAmount(ssr.Charge) || 'N/A' }}
                                                    </td>
                                                </tr>
                                            </template>
                                            <!-- Fallback if no SSR found -->
                                            <tr v-if="!pnrData?.SSR?.some(s => s.FUID === segment?.Flight?.FUID)">
                                                <td colspan="3" class="py-2 px-3 text-center text-gray-500">
                                                    No baggage information available
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Cancellation Rules -->
                                <div class="mt-6">
                                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Cancellation Rules</h3>
                                    <!-- Filter rules for current FUID -->
                                    <template
                                        v-for="rule in pnrData?.Rules?.filter(r => r.FUID === segment?.Flight?.FUID)"
                                        :key="rule">
                                        <div v-for="ruleGroup in rule?.Rule" :key="ruleGroup" class="mb-3">
                                            <h4 class="text-xs font-semibold text-gray-700 mb-1">{{ ruleGroup.Head
                                            }}</h4>
                                            <table class="w-full text-xs border border-gray-300">
                                                <thead>
                                                    <tr class="bg-gray-50">
                                                        <th class="py-1 px-2 text-left font-medium text-gray-600">
                                                            Description</th>
                                                        <th class="py-1 px-2 text-left font-medium text-gray-600">
                                                            Adult Amount</th>
                                                        <th v-if="ruleGroup.Info[0]?.CurrencyCode"
                                                            class="py-1 px-2 text-left font-medium text-gray-600">
                                                            Currency</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <tr v-for="info in ruleGroup?.Info" :key="info"
                                                        class="hover:bg-gray-50">
                                                        <td class="py-1 px-2">{{ info.Description }}</td>
                                                        <td class="py-1 px-2">{{ info.AdultAmount || '0' }}</td>
                                                        <td v-if="info.CurrencyCode" class="py-1 px-2">{{
                                                            info.CurrencyCode }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </template>
                                    <!-- Fallback if no rules found -->
                                    <div v-if="!pnrData?.Rules?.some(r => r.FUID === segment?.Flight?.FUID)"
                                        class="text-xs text-gray-500 text-center py-2">
                                        No cancellation rules available
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>

                    <!-- Summary Section -->
                    <div class="mt-6 pt-4 border-t border-gray-300">
                        <div class="flex justify-between items-center text-sm">

                            <div class="text-xs text-gray-500">
                                Transaction ID: {{ pnrData?.TransactionID }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Dialogs -->
        <!-- Low Balance -->
        <div v-if="isLowBalanceDialogOpen"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
            @click.self="isLowBalanceDialogOpen = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Low Balance</h3>
                <p class="text-sm text-gray-600 mb-6">Your wallet balance is insufficient. Please deposit funds.</p>
                <div class="flex justify-end gap-3">
                    <button @click="isLowBalanceDialogOpen = false" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button @click="router.push({ name: 'Deposits' })"
                        class="px-4 py-2 bg-primary text-white rounded-lg">Deposit
                        Now</button>
                </div>
            </div>
        </div>

        <!-- Stripe Dialog -->
        <div v-if="showPaymentDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-medium mb-4">Pay with Card</h3>
                <div id="card-element" class="mb-4 p-3 border rounded bg-gray-50"></div>
                <div v-if="paymentError" class="text-red-500 text-sm mb-3">{{ paymentError }}</div>
                <div class="flex justify-end gap-3">
                    <Button @click="closePaymentDialog">Cancel</Button>
                    <Button @click="handlePayment" :disabled="processing" class="bg-primary text-white">
                        <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                        Pay {{ formatAmount(totalTicketPrice) }}
                    </Button>
                </div>
            </div>
        </div>
        <div v-if="priceMismatchDialog"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
            @click.self="priceMismatchDialog = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                <div class="flex items-start justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Price Mismatched</h3>
                    <button @click="priceMismatchDialog = false" class="text-gray-400 hover:text-gray-500">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-6">
                    The airline has updated the fare by difference of <span class="text-red-500">AED {{ priceDifference
                    }}</span>.
                    Would you like to proceed with the new price?
                </p>

                <div class="flex justify-end space-x-3">
                    <button @click="closeDialogue"
                        class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                    <button @click="confirmBooking"
                        class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
                        Continue</button>
                </div>
            </div>
        </div>
        <!-- Confirm Dialog -->
        <div v-if="isConfirmDialogOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
            @click.self="isConfirmDialogOpen = false">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Confirm Booking</h3>
                <p class="text-sm text-gray-600 mb-6">Deduct {{ formatAmount(amount) }} from wallet and confirm?</p>
                <div class="flex justify-end gap-3">
                    <button @click="isConfirmDialogOpen = false" class="px-4 py-2 border rounded">Cancel</button>
                    <button @click="confirmBooking" class="px-4 py-2 bg-primary text-white rounded">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading -->
   
</template>