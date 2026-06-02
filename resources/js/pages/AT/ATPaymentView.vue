<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Plane, Users, Briefcase, ClockIcon, PrinterIcon, DollarSign, Check } from "lucide-vue-next";
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
  FETCH_AGENT_DATA,
  FETCH_AGENT_LEDGER,
  SEND_EMAIL,
  CANCEL_BOOKING,
  CONFIRM_BOOKING,
  VOID_REQUEST,
  SEND_PAYMENT_REQUEST,
  UPDATE_BOOKING_AMOUNT,
} from "@/services/store/actions.type";
import { cn, formatAmount, calculateFinalPrice } from "@/lib/utils";
import { toast } from "vue3-toastify";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Reactive state
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const isLoading = computed(() =>
  store.getters["flight/isLoading"]
);

const error = ref(null);
const custEmail = ref("");
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const priceMismatchDialog = ref(false);
const priceDifference = ref(0);
const isDetailsInfoVisible = ref(true);
const selectedFares = ref([]);
const totalTicketPrice = ref(0);
const surcharge = ref(0);
const isOpen = ref(false);
// Payment-related state
const paymentMethod = ref("pay"); // 'pay' (wallet), 'card', 'alrajhi'
const showPaymentDialog = ref(false);
const processing = ref(false);
const paymentError = ref("");
const clientSecret = ref("");
const amount = ref(0);
const loading = ref(false);
const countdown = ref('')
const timerInterval = ref(null)

// Stripe
const stripe = ref(null);
const elements = ref(null);
const cardElement = ref(null);
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);

// Computed
const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const isConfirmed = computed(() => store.getters["flight/isConfirmed"]);

const pnrData = ref(null);
const sooperResponse = ref(null);
const flightData = ref(null);
let bookingSource = route.query.booking_source;
let flight_provider = route.query.flight_provider || route.query.provider || null;
let booking_id = route.query.booking_id;
let pnr = route.query.pnr;

// Fetch functions
async function fetchAgent() {
  if (!user_id.value) return;
  try {
    await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: user_id.value });
  } finally {
    isAgentLoading.value = false;
  }
}

async function fetchAgentLedger() {
  if (!user_id.value) return;
  await store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, { userId: user_id.value });
}

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
    parseResponses();
    flightData.value = parseFlightData(bookingDetails.value[0]?.flight_data);
    amount.value = calculatePnrFinalFare(); // Set amount for payment
  } catch (err) {
    error.value = "Failed to fetch booking details.";
    toast.error(error.value);
  } finally {
    isBookingDetailsLoading.value = false;
  }
}

function parseResponses() {
  const booking = bookingDetails.value?.[0];
  if (!booking) return;

  try { selectedFares.value = JSON.parse(booking.fare_reference || null); } catch { }
  try { pnrData.value = JSON.parse(booking.pnr_response || null); } catch { }
  try { sooperResponse.value = JSON.parse(booking.sooper_response || null); } catch { }
}

function parseFlightData(data) {
  try { return JSON.parse(data); } catch { return {}; }
}

// Fare Calculations
function calculateBaseFare(base_price) {
  const flightCount = flightData.value?.original?.leg?.flights?.length ?? flightData.value?.leg?.flights?.length ?? 0;
  const flight = flightData.value?.original?.leg?.flights?.[0] ?? flightData.value?.leg?.flights?.[0];
  const fare = flight?.fares?.[0];
  if (!fare) return 0;

  const basePrice = parseFloat(base_price || 0);
  const systemFare = calculateFinalPrice(basePrice, fare.margin_amount, fare.margin_type, fare.amount_type);
  const agentMargin = parseFloat(agentData.value?.agent_data?.margin_amount || 0) * flightCount;
  const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0) * flightCount;

  return systemFare + agentMargin - agentDiscount;
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
  return calculateBaseFare(passenger, flightIndex) + calculatePassengerTaxes(passenger);
}

function calculatePnrFinalFare() {
  const legs = pnrData.value?.data?.providers?.[0]?.legs || [];
  if (!legs.length) return 0;

  const flights = flightData.value?.original?.leg?.flights ?? flightData.value?.leg?.flights ?? [];
  const uniqueCarriers = {};
  flights.forEach(f => { if (f?.marketing_carrier?.name) uniqueCarriers[f.marketing_carrier.name] = f; });

  const systemFare = legs.reduce((legSum, leg) => {
    return legSum + (leg.passengers || []).reduce((pSum, p) => {
      const base = parseFloat(p?.base_price || 0);
      const carrierFare = Object.values(uniqueCarriers).reduce((cSum, f) => {
        const fare = f?.fares?.[0];
        return fare ? cSum + calculateFinalPrice(base, fare.margin_amount, fare.margin_type, fare.amount_type) : cSum;
      }, 0);
      return pSum + carrierFare;
    }, 0);
  }, 0);

  const extraCharges = legs.reduce((total, leg) => total + (leg.passengers || []).reduce((s, p) => s + calculatePassengerTaxes(p), 0), 0);
  const passengerCount = legs[0]?.passengers?.length || 0;
  const agentMargin = parseFloat(agentData.value?.agent_data?.margin_amount || 0) * legs.length * passengerCount;
  const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0) * legs.length * passengerCount;

  return systemFare + extraCharges + agentMargin - agentDiscount;
}
const calculateLayover = (prevSegment, nextSegment) => {
  const diff = moment(nextSegment.departure_at).diff(moment(prevSegment.arrival_at), 'minutes')
  return diff
}

const calculateTotalJourneyTime = (segments) => {
  if (!segments?.length) return 0
  const first = segments[0]
  const last = segments[segments.length - 1]
  return moment(last.arrival_at).diff(moment(first.departure_at), 'minutes')
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
  return booking?.pessangers ?? [];
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

// Payment Methods Handler
function handlePaymentMethod(type) {
  totalTicketPrice.value = 0; // Reset before recalculation
  // calculateBookedPrice();
  
  priceMismatchDialog.value = false;
  paymentMethod.value = type;

  if (type === "wallet") {
    handleConfirmDialogOpen();
  } else if (type === "stripe") {
    openPaymentDialog();
  } else if (type === "alrajhi") {
    payNow();
  }
}

// Wallet Confirmation
function handleConfirmDialogOpen() {
  const total = bookingDetails?.value?.[0]?.amount;
  amount.value = total;

  if (agentLedger.value?.balance < total) {
    isLowBalanceDialogOpen.value = true;
  } else {
    isConfirmDialogOpen.value = true;
  }
}
function priceReValidation(bookedPrice, pnrPrice) {
  if ((parseFloat(bookedPrice)).toFixed(2) !== (parseFloat(pnrPrice)).toFixed(2)) {
    console.log('has Differnece');
    priceDifference.value = (parseFloat(pnrPrice) - parseFloat(bookedPrice)).toFixed(2);
    return false;
  }
  console.log('No differnece')
  return true;
}
// Add these computed properties
const passengerCount = computed(() => {
  return parseInt(route.query.passenger_count || bookingDetails.value?.[0]?.pessangers?.length || 1);
});

const selectedExtras = ref({}); // This should come from your ancillaries state

const hasExtras = computed(() => {
  return Object.keys(selectedExtras.value).length > 0;
});

// Add these helper methods
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
  const passengerCount = parseInt(route.query.passenger_count || 1);
  const agentAmount = parseFloat(agentData?.value?.agent_data?.margin_amount || 0);
  const agentDiscount = parseFloat(agentData?.value?.agent_data?.agent_discount || 0);
  const agentMargin = parseFloat(agentData?.value?.agent_data?.agent_margin || 0);

  const airlineMargin = calculateFinalPrice(
    parseFloat(fare.base_price || 0),
    parseFloat(fare.margin_amount),
    fare.margin_type,
    fare.amount_type
  );

  const billable = parseFloat(fare.surchage || 0) +
    parseFloat(fare.taxes || 0) +
    parseFloat(fare.fees || 0) +
    parseFloat(fare.service_charges || 0) +
    parseFloat(fare.ancillaries_charges || 0) +
    parseFloat(airlineMargin);

  const total = billable + (agentAmount * passengerCount) + (agentMargin * passengerCount) - (agentDiscount * passengerCount);
  return total;
}

function calculateExtrasTotal(flightIndex) {
  let extrasAmount = 0;
  const extrasForFlight = selectedExtras.value?.[flightIndex] || {};

  ["baggage", "seat", "meal"].forEach(group => {
    const groupData = extrasForFlight[group];
    if (!groupData) return;

    Object.values(groupData).forEach(segment => {
      if (!segment) return;
      Object.values(segment).forEach(pax => {
        if (!pax) return;
        Object.values(pax).forEach(item => {
          const price = Number(item?.Charge ?? item?.SSRNetAmount ?? 0);
          extrasAmount += price;
        });
      });
    });
  });

  return extrasAmount;
}

function calculateGrandTotal() {
  let total = 0;

  flightData.value?.leg?.flights?.forEach((flightItem, flightIndex) => {
    flightItem?.fares?.forEach(fare => {
      if (selectedFares.value.includes(fare.ref_id)) {
        total += calculateTotalFare(fare) + calculateExtrasTotal(flightIndex);
      }
    });
  });

  amount.value = total + parseFloat(pnrData.value?.SSRAmount )|| 0;
  return total + parseFloat(pnrData.value?.SSRAmount) || 0;
}

function processPayment() {
  if (!paymentMethod.value) return;

  // Call the appropriate payment method handler
  if (paymentMethod.value === 'wallet') {
    handleConfirmDialogOpen();
  } else if (paymentMethod.value === 'stripe') {
    openPaymentDialog();
  } else if (paymentMethod.value === 'alrajhi') {
    payNow();
  }
}
function calculateBookedPrice() {
  const flightCount = flightData.value?.original?.leg?.flights?.length ?? flightData.value?.leg?.flights?.length ?? 0;
  flightData.value.leg.flights.forEach((flightItem, flightIndex) => {
    flightItem.fares.forEach(fare => {
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
        const agentMargin = parseFloat(bookingDetails?.value?.[0].agent_markup * flightCount || 0);
        const agentDiscount = parseFloat(bookingDetails?.value?.[0].agent_discount * flightCount || 0);
        const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin * flightCount || 0);
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
function updateBookingAmount() {
  store.dispatch("flight/" + UPDATE_BOOKING_AMOUNT, {
    booking_id: booking_id,
    amount: amount.value,
  })

}
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
    // Margin can be percent or amount
    if (amountType === "percent") {
      margin = (price * (parseFloat(marginAmount) || 0)) / 100;
    } else {
      margin = parseFloat(marginAmount) || 0;
    }
  }
  return margin;
};
const closeDialogue = () => {
  isOpen.value = false;
  updateBookingAmount();

  router.push({
    name: "AgentBookingDetails", // Replace with the name of your route
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
        TUI: pnrData.value?.TUI ?? "null",
        TransactionID: pnrData.value?.TransactionID ?? "null",
        net_amount: pnrData.value?.NetAmount ?? "null",
        booking_status: "ticketed",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
        totalTicketPrice: totalTicketPrice.value,
    });

    // Close dialog after successful cancellation
    isConfirmDialogOpen.value = false;
    fetchBookingDetails();
}

// Alrajhi Payment
const payNow = async () => {
  calculateBookedPrice();

  updateBookingAmount();
  loading.value = true;
  try {
    const { data } = await axios.post('/api/arb/initiate', {
      amount: amount.value || calculatePnrFinalFare(),
      flight_id: route.query.flight_id || flightData.value?.id || null,
      booking_id: bookingDetails?.value?.[0]?.id || booking_id,
      booking_uuid: pnrData.value?.data?.uuid ?? "null",
      pnr: bookingDetails?.value?.[0]?.itinerary_ref || pnr,
      flight_mode: "B2B",
      booking_source: route.query.booking_source,
      flight_provider: route.query.flight_provider || route.query.provider || null,
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
watch(isConfirmed, () => {
  if (isConfirmed.value == true) {
    router.push({
      name: "AgentBookingDetails", // Replace with the name of your route
      query: {
        flight_id: route.query.flight_id,
        booking_id: bookingDetails?.value?.[0].id,
        pnr: bookingDetails?.value?.[0].itinerary_ref,
        flight_mode: "B2B",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
      }, // Pass relevant query parameters if needed
    });
  }
});
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

const initializeStripe = async () => {
  if (stripe.value) return;

  try {
    stripe.value = await loadStripe(publicKey.value);
    if (!stripe.value) throw new Error("Stripe failed to load");

    elements.value = stripe.value.elements();
    cardElement.value = elements.value.create('card', {
      style: {
        base: {
          fontSize: '16px',
          color: '#32325d',
          '::placeholder': { color: '#aab7c4' },
        },
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
  calculateBookedPrice();
  updateBookingAmount();
  if (!stripe.value || !cardElement.value) {
    toast.error("Payment form not ready.");
    return;
  }

  processing.value = true;

  try {
    const response = await store.dispatch('flight/' + SEND_PAYMENT_REQUEST, {
      amount: Math.round(amount.value * 100),
      currency: 'sar',
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
    console.error(err);
    toast.error("Payment failed. Try again.");
  } finally {
    processing.value = false;
  }
};

// Void & Print
function voidRequest() {
  store.dispatch("flight/" + VOID_REQUEST, {
    pnr: pnr,
    booking_uuid: pnrData.value?.data?.uuid ?? "null",
    billable_price: pnrData.value?.data?.billable_price ?? "null",
    currency: pnrData.value?.data?.currency?.code ?? "null",
    bookingId: bookingDetails.value[0].id,
    booking_status: "voided",
    booking_source: route.query.booking_source,
  });
  isVoidDialogOpen.value = false;
  fetchBookingDetails();
}

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
const startCountdown = (expiryTime) => {
  const expiry = moment(expiryTime)

  if (timerInterval.value) clearInterval(timerInterval.value)

  timerInterval.value = setInterval(() => {
    const now = moment()
    const diff = expiry.diff(now)

    if (diff <= 0) {
      clearInterval(timerInterval.value)
      countdown.value = 'Expired'
    } else {
      const duration = moment.duration(diff)
      const hours = Math.floor(duration.asHours())
      const minutes = duration.minutes()
      const seconds = duration.seconds()

      if (hours > 0) {
        countdown.value = `${hours}h ${minutes}m ${seconds}s`
      } else {
        countdown.value = `${minutes}m ${seconds}s`
      }
    }
  }, 1000)
}


// Lifecycle
onMounted(async () => {
  if (!user.value) await authStore.fetchUser();
  bookingSource = route.query.booking_source;
  flight_provider = route.query.flight_provider || route.query.provider || null;
  booking_id = route.query.booking_id;
  pnr = route.query.pnr;
  await Promise.all([fetchAgent(), fetchAgentLedger(), fetchBookingDetails()]);
  if (pnrData.value?.data?.expiry_at) {
    startCountdown(pnrData.value.data.expiry_at)
  }
});
</script>

<template>
  <div v-if="bookingDetails && !isLoading" class="min-h-screen bg-gray-50 py-6 print:bg-white print:py-0">
    <div class="">
      <!-- Header -->
      <div class="mb-6">
        <div class="bg-white shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 mb-1">Complete Payment</h1>
              <p class="text-sm text-gray-600">Review your booking and proceed to payment</p>
            </div>
            <Badge variant="outline" class="text-primary border-primary bg-primary/5 px-3 py-1">
              PNR: {{ pnrData?.data?.providers?.[0]?.pnr || 'N/A' }}
            </Badge>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Flight Details -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Flight Itinerary Card -->
          <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 p-4">
              <div class="flex items-center gap-2">
                <Plane class="h-5 w-5 text-primary" />
                <h2 class="text-lg font-semibold text-gray-900">Flight Itinerary</h2>
              </div>
            </div>

            <!-- Flight Itinerary Card -->


            <div class="p-6">
              <div v-for="(flight, flightIdx) in flightData?.leg?.flights" :key="flightIdx" class="mb-8 last:mb-0">
                <!-- Route Header -->
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center gap-2">
                    <img :src="flight.marketing_carrier?.logo" :alt="flight.marketing_carrier?.name"
                      class="h-8 w-8 rounded-full border border-gray-200" />
                    <div>
                      <p class="font-medium text-gray-900">{{ flight.marketing_carrier?.name }}</p>
                      <p class="text-xs text-gray-500">Flight {{ flight.segments?.[0]?.flight_number }}</p>
                    </div>
                  </div>
                  <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                    {{ flight.segments?.[0]?.cabin_class === 'E' ? 'Economy' : flight.segments?.[0]?.cabin_class ||
                    'Economy' }}
                  </span>
                </div>

                <!-- Flight Segments -->
                <div v-for="(segment, sIdx) in flight.segments" :key="sIdx" class="relative">
                  <!-- Flight Segment -->
                  <div class="flex flex-col md:flex-row md:items-center gap-4 p-4 bg-gray-50 rounded-lg">
                    <!-- Departure -->
                    <div class="flex-1">
                      <div class="flex items-start gap-3">
                        <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                        <div>
                          <p class="text-sm font-semibold text-gray-900">
                            {{ formatDateTime(segment.departure_at) }}
                          </p>
                          <p class="text-base font-bold text-gray-900 mt-1">
                            {{ segment.from?.iata }}
                          </p>
                          <p class="text-xs text-gray-600">{{ segment.from?.name }}</p>
                        </div>
                      </div>
                    </div>

                    <!-- Flight Path -->
                    <div class="flex-1 flex flex-col items-center">
                      <p class="text-xs text-gray-500 mb-1">{{ segment.flight_time }}</p>
                      <div class="w-full flex items-center">
                        <div class="w-2 h-2 bg-primary rounded-full"></div>
                        <div class="flex-1 h-0.5 bg-gradient-to-r from-primary to-primary/30"></div>
                        <div class="w-2 h-2 bg-primary rounded-full"></div>
                      </div>
                      <p class="text-xs text-gray-500 mt-1">Direct</p>
                    </div>

                    <!-- Arrival -->
                    <div class="flex-1 text-right">
                      <div class="flex items-start justify-end gap-3">
                        <div class="text-right">
                          <p class="text-sm font-semibold text-gray-900">
                            {{ formatDateTime(segment.arrival_at) }}
                          </p>
                          <p class="text-base font-bold text-gray-900 mt-1">
                            {{ segment.to?.iata }}
                          </p>
                          <p class="text-xs text-gray-600">{{ segment.to?.name }}</p>
                        </div>
                        <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                      </div>
                    </div>
                  </div>
                </div>


              </div>
            </div>
          </div>

          <!-- Important Information -->
          <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 border-b border-gray-200 p-4">
              <h2 class="text-lg font-semibold text-gray-900">Important Information</h2>
            </div>
            <div class="p-6 space-y-3">
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-xs text-blue-600 font-bold">i</span>
                </div>
                <p class="text-sm text-gray-600">This booking is subject to the airline's terms and conditions.</p>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-xs text-amber-600 font-bold">!</span>
                </div>
                <p class="text-sm text-gray-600">Booking expires at {{ formatDateTime(pnrData?.data?.expiry_at) }}.
                  Please complete payment before expiry.</p>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-5 h-5 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                  <span class="text-xs text-green-600 font-bold">✓</span>
                </div>
                <p class="text-sm text-gray-600">E-ticket will be sent to {{ pnrData?.data?.email_address }} after
                  confirmation.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column - Payment Summary -->
        <div class="lg:col-span-1">
          <div class="sticky top-6 space-y-6">
            <!-- Price Breakdown Card - Using flightData fares -->
            <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
              <div class="bg-gray-50 border-b border-gray-200 p-4">
                <h2 class="text-lg font-semibold text-gray-900">Price Summary</h2>
              </div>

              <div class="p-5">
                <div v-for="(flightItem, flightIndex) in flightData?.leg?.flights" :key="flightIndex">
                  <div v-for="(fare, fareIndex) in flightItem?.fares" :key="fareIndex">
                    <div v-if="selectedFares?.includes(fare.ref_id)" class="space-y-3">
                      <!-- Base Fare with Margins -->
                      <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Base Fare</span>
                        <span class="font-medium">
                          {{ formatAmount(calculateFinalPrice(fare?.base_price,
                            fare?.margin_amount,
                            fare?.margin_type, fare?.amount_type) +
                            parseFloat((agentData?.agent_data?.margin_amount || 0) * passengerCount) -
                            parseFloat((agentData?.agent_data?.agent_discount || 0) * passengerCount) +
                            (parseFloat(agentData?.agent_data?.agent_margin || 0) * passengerCount)) }}
                        </span>
                      </div>

                      <!-- Taxes & Fees -->
                      <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-600">Taxes & Fees</span>
                        <span class="font-medium">{{ formatAmount(calculateTaxes(fare)) }}</span>
                      </div>

                      <!-- Add-ons (if any) -->
                     

                      <!-- Divider -->
                      <div class="border-t border-gray-200 my-3"></div>

                      <!-- Fare Total -->
                      <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg">
                        <span class="text-sm font-medium text-gray-700">Flight Total</span>
                        <span class="text-sm font-bold text-primary">
                          {{ formatAmount(calculateTotalFare(fare) + calculateExtrasTotal(flightIndex)) }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Grand Total -->
                 
                <div class="mt-4 pt-4 border-t-2 border-gray-300">
                   <div  class="flex justify-between items-center text-sm my-4">
                        <span class="text-gray-600">Add-ons</span>
                        <span class="font-medium">{{ formatAmount(pnrData?.SSRAmount) }}</span>
                      </div>
                  <div class="flex justify-between items-center bg-primary/5 p-4 rounded-lg">
                    <span class="text-base font-semibold text-gray-900">Total Amount</span>
                    <span class="text-xl font-bold text-primary">
                      {{ formatAmount(calculateGrandTotal()) }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 mt-2">All prices in {{ pnrData?.data?.currency?.code || 'SAR' }}</p>
                </div>
              </div>
            </div>

            <!-- Payment Methods Card -->
            <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
              <div class="bg-gray-50 border-b border-gray-200 p-4">
                <h2 class="text-lg font-semibold text-gray-900">Payment Method</h2>
              </div>

              <div class="p-5 space-y-4">
                <!-- Wallet Payment -->
                <button @click="handlePaymentMethod('wallet')"
                  class="w-full p-4 border-2 rounded-lg hover:border-primary transition-colors text-left"
                  :class="paymentMethod === 'wallet' ? 'border-primary bg-primary/5' : 'border-gray-200'">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <img src="/public/assets/wallet.png" alt="Wallet" class="h-8 w-8" />
                      <div>
                        <p class="font-medium text-gray-900">Pay with Wallet</p>
                        <p class="text-xs text-gray-500">Balance: {{ formatAmount(agentLedger?.balance) }}</p>
                      </div>
                    </div>
                    <div v-if="paymentMethod === 'wallet'"
                      class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                      <Check class="h-3 w-3 text-white" />
                    </div>
                  </div>
                </button>

                <!-- Card Payment -->
                <button v-if="agentData?.is_card_allowed" @click="handlePaymentMethod('stripe')"
                  class="w-full p-4 border-2 rounded-lg hover:border-primary transition-colors text-left"
                  :class="paymentMethod === 'stripe' ? 'border-primary bg-primary/5' : 'border-gray-200'">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <img src="/public/assets/credit-card.png" alt="Card" class="h-8 w-8" />
                      <div>
                        <p class="font-medium text-gray-900">Credit / Debit Card</p>
                        <p class="text-xs text-gray-500">Visa, Mastercard, Amex</p>
                      </div>
                    </div>
                    <div v-if="paymentMethod === 'stripe'"
                      class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                      <Check class="h-3 w-3 text-white" />
                    </div>
                  </div>
                </button>

                <!-- Alrajhi Payment -->
                <button @click="handlePaymentMethod('alrajhi')"
                  class="w-full p-4 border-2 rounded-lg hover:border-primary transition-colors text-left"
                  :class="paymentMethod === 'alrajhi' ? 'border-primary bg-primary/5' : 'border-gray-200'">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                      <img src="/public/assets/alrajhi.png" alt="Alrajhi" class="h-8 w-8" />
                      <div>
                        <p class="font-medium text-gray-900">Alrajhi Bank</p>
                        <p class="text-xs text-gray-500">Mada / Alrajhi Payments</p>
                      </div>
                    </div>
                    <div v-if="paymentMethod === 'alrajhi'"
                      class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                      <Check class="h-3 w-3 text-white" />
                    </div>
                  </div>
                </button>

                <!-- Confirm Button -->
                <Button @click="processPayment" :disabled="!paymentMethod || processing"
                  class="w-full bg-primary hover:bg-primary/90 text-white font-medium py-3 mt-4">
                  <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                  {{ processing ? 'Processing...' : `Pay ${formatAmount(calculateGrandTotal())}` }}
                </Button>

                <!-- Terms -->
                <p class="text-xs text-center text-gray-500 mt-2">
                  By confirming, you agree to our
                  <a href="#" class="text-primary hover:underline">Terms of Service</a> and
                  <a href="#" class="text-primary hover:underline">Privacy Policy</a>
                </p>
              </div>
            </div>

            <!-- Expiry Timer -->
            <!-- <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
              <div class="flex items-center gap-3">
                <ClockIcon class="h-5 w-5 text-amber-600" />
                <div>
                  <p class="text-sm font-medium text-amber-800">Booking expires in</p>
                  <p class="text-lg font-bold text-amber-900">{{ countdown || 'Expired' }}</p>
                </div>
              </div>
            </div> -->
          </div>
        </div>
      </div>
    </div>

    <!-- Dialogs (keep existing dialogs) -->
    <!-- ... -->
     <div v-if="isLowBalanceDialogOpen"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="isLowBalanceDialogOpen = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Low Balance</h3>
        <p class="text-sm text-gray-600 mb-6">Your wallet balance is insufficient. Please deposit funds.</p>
        <div class="flex justify-end gap-3">
          <button @click="isLowBalanceDialogOpen = false" class="px-4 py-2 border rounded-lg">Cancel</button>
          <button @click="router.push({ name: 'Deposits' })" class="px-4 py-2 bg-primary text-white rounded-lg">Deposit
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
            Pay {{ formatAmount(amount) }}
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
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <p class="text-sm text-gray-600 mb-6">
          The airline has updated the fare by difference of <span class="text-red-500">SAR {{ priceDifference }}</span>.
          Would you like to proceed with the new price?
        </p>

        <div class="flex justify-end space-x-3">
          <button @click="closeDialogue"
            class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
          <button v-if="paymentMethod === 'pay'" @click="confirmBooking"
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
  <div v-else class="fixed inset-0 bg-white/75 flex items-center justify-center z-50">
    <Spinner />
  </div>
</template>