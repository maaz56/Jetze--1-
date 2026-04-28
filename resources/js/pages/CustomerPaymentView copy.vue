<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Plane, Users, Briefcase, ClockIcon, PrinterIcon, X } from "lucide-vue-next";
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
  FETCH_ANCILLARIES,
  PATCH_ANCILLARIES,
  FETCH_CUSTOMER_MARGIN,
} from "@/services/store/actions.type";
import { cn, formatAmount, calculateFinalPrice, calculateTypeMargin } from "@/lib/utils";
import { toast } from "vue3-toastify";
import { reactive } from "vue";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

// Reactive state
const isBookingDetailsLoading = ref(true);
const isPnrDetailsLoading = ref(true);
const isAgentLoading = ref(true);
const isLoading = computed(() =>
  isBookingDetailsLoading.value || isPnrDetailsLoading.value || isAgentLoading.value
);

const error = ref(null);
const custEmail = ref("");
const isEmailDialogOpen = ref(false);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isVoidDialogOpen = ref(false);
const isChatOpen = ref(false);

const isDetailsInfoVisible = ref(true);

// Payment-related state
const paymentMethod = ref("pay"); // 'pay' (wallet), 'card', 'alrajhi'
const showPaymentDialog = ref(false);
const processing = ref(false);
const paymentError = ref("");
const clientSecret = ref("");
const amount = ref(0);
const loading = ref(false);
const extraTotal = ref(0);
const typeMargin = ref(0);
const customerMarginAmt = ref(0);
// Stripe
const stripe = ref(null);
const elements = ref(null);
const cardElement = ref(null);
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);

// Computed
const user = computed(() => authStore.user);
const finalPayableAmount = ref(0);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const isConfirmed = computed(() => store.getters["flight/isConfirmed"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const airportMargins = computed(() => store.getters["airport/airportMargin"] || {});

// Extra Services Dialog State - FIXED
const isExtraServicesOpen = ref(false);
// Initialize selectedAncillaries as a ref with proper structure
const selectedAncillaries = ref({}); // { passengerIndex: { groupCode: itemCode } }
const selectedAncillariesList = reactive([])

// Ancillaries computed properties - FIXED
const ancillariesData = computed(() => {
  try {
    return ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult || null;
  } catch {
    return null;
  }
});
const ancillarySegments = computed(() => {
  try {
    const data = ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult?.AncillaryItemResponses?.AncillaryItemResponse;
    return Array.isArray(data) ? data : (data ? [data] : []);
  } catch (e) {
    console.error("Failed to parse ancillary segments", e);
    return [];
  }
});
const CustomerMargin = computed(
  () => store.getters["customerMargin/customerMargin"],
);
const ancillaryGroups = computed(() => {
  // if (!ancillariesData.value?.AncillaryItemResponses?.AncillaryItemResponse?.AncillaryItemSets?.AncillaryItemSet) {
  //   return [];
  // }

  const sets = ancillariesData.value.AncillaryItemResponses.AncillaryItemResponse;
  return Array.isArray(sets) ? sets : [sets];
});

const passengers = computed(() => getPassengers(bookingDetails.value?.[0]) || []);
function fetchCustomerMarginValues() {
  store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}
// Extra Services Total - FIXED


// Helper function to find ancillary item by code
const findAncillaryItem = (itemCode) => {
  for (const group of ancillaryGroups.value) {
    const items = group.AncillaryItems?.AncillaryItem;
    if (items) {
      const itemArray = Array.isArray(items) ? items : [items];
      const foundItem = itemArray.find(item => item['@attributes'].ItemCode === itemCode);
      if (foundItem) return foundItem;
    }
  }
  return null;
};

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
    fetchAncillaries();
  } catch (err) {
    error.value = "Failed to fetch booking details.";
    toast.error(error.value);
  } finally {
    isBookingDetailsLoading.value = false;
  }
}


function toggleSegmentAncillary(segIdx, pIdx, groupCode, item, event, paxType) {
  const itemCode = item['@attributes'].ItemCode;
  const amount = Number(item['@attributes'].ChargeAmount || 0);
  const currency = item['@attributes'].ChargeCurrency || 'PKR';

  const key = { segIdx, pIdx, groupCode, itemCode };

  if (event.target.type === 'checkbox') {
    if (event.target.checked) {
      selectedAncillariesList.push({ ...key, item, amount, currency, paxType });
    } else {
      const idx = selectedAncillariesList.findIndex(x =>
        x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
      );
      if (idx > -1) selectedAncillariesList.splice(idx, 1);
    }
  } else {
    // Radio: clear others in same group, same passenger, same segment
    for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
      if (selectedAncillariesList[i].segIdx === segIdx &&
        selectedAncillariesList[i].pIdx === pIdx &&
        selectedAncillariesList[i].groupCode === groupCode) {
        selectedAncillariesList.splice(i, 1);
      }
    }
    selectedAncillariesList.push({ ...key, item, amount, currency, paxType });
  }
}
function getItemsArray(items) {
  return Array.isArray(items) ? items : [items];
}

// Updated total
const extraServicesTotal = computed(() => {
  return selectedAncillariesList.reduce((sum, anc) => sum + anc.amount, 0);
});
function hasAnySelectionInGroup(segIdx, pIdx, groupCode) {
  return selectedAncillariesList.some(x =>
    x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode
  );
}

// Apply and send to backend
function applyAncillariesAndClose() {
  patchAncillaryCharges();
  isExtraServicesOpen.value = false;
}
function isSegmentSelected(segIdx, pIdx, groupCode, itemCode) {
  return selectedAncillariesList.some(x =>
    x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
  );
}
function clearSegmentGroup(segIdx, pIdx, groupCode) {
  for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
    if (selectedAncillariesList[i].segIdx === segIdx &&
      selectedAncillariesList[i].pIdx === pIdx &&
      selectedAncillariesList[i].groupCode === groupCode) {
      selectedAncillariesList.splice(i, 1);
    }
  }
}
function clearRadioSelection(pIdx, groupCode) {
  for (let i = selectedAncillariesList.length - 1; i >= 0; i--) {
    if (selectedAncillariesList[i].pIdx === pIdx && selectedAncillariesList[i].groupCode === groupCode) {
      selectedAncillariesList.splice(i, 1)
    }
  }
}

function removeSelectedSeat(segmentRPH, passengerIndex) {
  const key = `${segmentRPH}-${passengerIndex}`
  delete selectedSeats.value[key]
}

function parseResponses() {
  const booking = bookingDetails.value?.[0];
  if (!booking) return;
  try { pnrData.value = JSON.parse(booking.pnr_response || null); } catch { }
  try { sooperResponse.value = JSON.parse(booking.sooper_response || null); } catch { }
}

function parseFlightData(data) {
  try { return JSON.parse(data); } catch { return {}; }
}

// Fare Calculations
function calculatePassengerFare(passenger, flightIndex) {
  const flight = flightData.value?.original?.leg?.flights?.[flightIndex] ?? flightData.value?.leg?.flights?.[flightIndex];
  const fare = flight?.fares?.[0];
  if (!fare) return 0;

  const basePrice = parseFloat(passenger?.base_price || 0);
  const systemFare = calculateFinalPrice(basePrice, fare.margin_amount, fare.margin_type, fare.amount_type);
  const agentMargin = parseFloat(agentData.value?.agent_data?.margin_amount || 0);
  const agentDiscount = parseFloat(agentData.value?.agent_data?.agent_discount || 0);

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
  return calculatePassengerFare(passenger, flightIndex) + calculatePassengerTaxes(passenger);
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

const addExtraServicesToBooking = () => {
  const selected = extraServices.value.filter(s => s.selected);

  extraTotal.value = extraServicesTotal.value;

  // update booking total

  isExtraServicesOpen.value = false;

};
const isSeatMapOpen = ref(false)

// Selected seats state
const selectedSeats = ref({})

// Process seat map data
const processedSeatMapData = computed(() => {
  if (!ancillaries.value?.seatMap) return []

  const seatMapResponse = ancillaries.value.seatMap.Body?.AirSeatMapResponse?.AirSeatMapResult?.SeatMapResponses?.SeatMapResponse

  if (!seatMapResponse) return []

  // Convert to array if it's an object
  return Array.isArray(seatMapResponse) ? seatMapResponse : [seatMapResponse]
})

// Get seat CSS classes
const getSeatClasses = (seat, segmentIndex, traveler, rowNumber, seatNumber) => {
  const classes = []
  const TravelerRefNumber =
    traveler?.TravelerRefNumber?.["@attributes"]?.RPH
  // Base classes
  if (seat.Availability === 'NoSeatHere') {
    classes.push('bg-gray-100 border-gray-300 cursor-default')
  } else if (seat.Availability === 'SeatOccupied') {
    classes.push('bg-red-100 border-red-500 cursor-not-allowed')
  } else if (isSeatSelected(segmentIndex, TravelerRefNumber, rowNumber, seatNumber)) {
    classes.push('bg-primary/20 border-primary shadow-md')
  } else {
    classes.push('bg-green-100 border-green-500 hover:bg-green-200')
  }

  // Feature-based classes
  if (seat.Features) {
    if (seat.Features.includes('ExitRow')) {
      classes.push('border-orange-300')
    }
    if (seat.Features.includes('Overwing')) {
      classes.push('border-blue-300')
    }
  }

  return classes.join(' ')
}

// Get seat tooltip
const getSeatTooltip = (seat) => {
  if (seat.Availability === 'NoSeatHere') return 'No seat'

  const features = []
  let price = 'Free'

  if (seat.Features) {
    if (seat.Features.includes('Window')) features.push('Window')
    if (seat.Features.includes('Aisle')) features.push('Aisle')
    if (seat.Features.includes('ExitRow')) features.push('Exit Row')
    if (seat.Features.includes('Overwing')) features.push('Overwing')
  }

  if (seat.Service?.Fee?.['@attributes']?.Amount) {
    price = `${seat.Service.Fee['@attributes'].Amount} ${seat.Service.Fee['@attributes'].CurrencyCode}`
  }

  const status = seat.Availability === 'SeatOccupied' ? 'Occupied' : 'Available'
  const featureText = features.length > 0 ? `Features: ${features.join(', ')}` : ''

  return `${status} | ${price}${featureText ? ` | ${featureText}` : ''}`
}

// Check if seat is selected
const isSeatSelected = (segmentIndex, passengerIndex, rowNumber, seatNumber) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber
}

// Select seat
const selectSeat = (segmentRPH, traveler, rowNumber, seatNumber, seatData) => {
  if (seatData.Availability === 'NoSeatHere' || seatData.Availability === 'SeatOccupied') {
    return
  }
  const TravelerRefNumber =
    traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null;
  const key = `${segmentRPH}-${TravelerRefNumber}`

  // If already selected, deselect it
  if (selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber) {
    delete selectedSeats.value[key]
  } else {
    // Select new seat
    selectedSeats.value[key] = {
      row: rowNumber,
      seat: seatNumber,
      travelerRefNumber: TravelerRefNumber,
      RPH: segmentRPH,
      price: seatData.Service?.Fee?.['@attributes']?.Amount || 0,
      currency: seatData.Service?.Fee?.['@attributes']?.CurrencyCode || 'PKR',
      features: seatData.Features ? seatData.Features.filter(f => !f.includes('Other_')).join(', ') : ''
    }
  }
}

// Get selected seat info
const getSelectedSeat = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  const selected = selectedSeats.value[key]
  return selected ? `${selected.row}${selected.seat}` : null
}

const getSelectedSeatPrice = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.price || 0
}

const getSelectedSeatFeatures = (segmentIndex, passengerIndex) => {
  const key = `${segmentIndex}-${passengerIndex}`
  return selectedSeats.value[key]?.features || ''
}

// Total seats cost
const totalSeatsCost = computed(() => {
  return Object.values(selectedSeats.value).reduce((total, seat) => {
    return total + (parseFloat(seat.price) || 0)
  }, 0)
})

// Get passenger traveler reference
const getPassengerTravelerRef = (pIdx) => {
  if (!pnrData.value?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler) return null;

  const travelers = pnrData.value.Body.AirBookResponse.AirBookResult.AirReservation.TravelerInfo.AirTraveler;
  const traveler = Array.isArray(travelers) ? travelers[pIdx] : travelers;

  return traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null;
}

// Format date time

// Dialog methods
const openSeatMap = () => {
  isSeatMapOpen.value = true
}

const closeSeatMap = () => {
  isSeatMapOpen.value = false
}

// Confirm seat selection
const confirmSeatSelection = () => {
  const seatData = {
    selectedSeats: selectedSeats.value,
    totalCost: totalSeatsCost.value,
    segments: processedSeatMapData.value.map((segment, index) => ({
      segmentIndex: index,
      flightNumber: segment.FlightSegmentInfo['@attributes'].FlightNumber,
      departure: segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode,
      arrival: segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode
    }))
  }

  store.dispatch("flight/" + PATCH_ANCILLARIES, {
    flight_provider: flight_provider,
    pnr: pnrData.value,
    selectedSeats: seatData,
  })
  closeSeatMap()
}

// Reset selections when dialog closes
watch(isSeatMapOpen, (newVal) => {
  if (!newVal) {
    // Optional: Keep selections or reset them
    // selectedSeats.value = {}
  }
})
// Initialize selectedAncillaries when passengers change - NEW
watch(passengers, (newPassengers) => {
  if (newPassengers.length > 0) {
    // Initialize structure for each passenger
    const initialSelections = {};
    newPassengers.forEach((_, index) => {
      initialSelections[index] = {};
    });
    selectedAncillaries.value = initialSelections;
  }
}, { immediate: true });

// Payment Methods Handler
function handlePaymentMethod(type) {
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
  const total = finalPayableAmount.value;
  amount.value = total;

  if (agentLedger.value?.balance < total) {
    isLowBalanceDialogOpen.value = true;
  } else {
    isConfirmDialogOpen.value = true;
  }
}


function confirmBooking() {
  const total = finalPayableAmount.value;


  store.dispatch("flight/" + CONFIRM_BOOKING, {
    pnr: route.query.pnr,
    bookingId: bookingDetails.value[0].id,
    booking_uuid: pnrData.value?.data?.uuid ?? "null",
    booking_status: "ticketed",
    paymentMethod: paymentMethod.value,
    booking_source: route.query.booking_source,
    flight_provider: route.query.flight_provider,
    amount: total,
    // selectedAncillaries: selectedAncillaries.value, // Include selected ancillaries
  }).then(() => {
    fetchBookingDetails();
  }).catch(() => {
    toast.error("Failed to confirm booking.");
  });

  isConfirmDialogOpen.value = false;
}

// Alrajhi Payment
const payNow = async () => {
  loading.value = true;
  try {
    const { data } = await axios.post('/api/arb/initiate', {
      amount: amount.value || finalPayableAmount.value,
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
      name: "BookingsDetails",
      query: {
        flight_id: route.query.flight_id,
        booking_id: bookingDetails?.value?.[0].id,
        pnr: bookingDetails?.value?.[0].itinerary_ref,
        flight_mode: "B2C",
        booking_source: route.query.booking_source,
        flight_provider: route.query.flight_provider,
      },
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
  if (!stripe.value || !cardElement.value) {
    toast.error("Payment form not ready.");
    return;
  }

  processing.value = true;

  try {
    const response = await store.dispatch('flight/' + SEND_PAYMENT_REQUEST, {
      amount: Math.round(finalPayableAmount.value * 100),
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
function patchAncillaryCharges() {
  store.dispatch("flight/" + PATCH_ANCILLARIES, {
    flight_provider: flight_provider,
    pnr: pnrData.value,
    selectedAncillaries: selectedAncillariesList.map(item => ({
      segIdx: item.segIdx,
      segmentRPH: ancillarySegments.value[item.segIdx]?.FlightSegmentInfo['@attributes'].RPH,
      pIdx: item.pIdx,
      TravelerRefNumber: getPassengerTravelerRef(item.pIdx),
      groupCode: item.groupCode,
      itemCode: item.itemCode,
      amount: item.amount,
      currency: item.currency
    }))
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
const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
  // console.log("customer margin",{price,discountPercentage,marginPercentage})
  const total = parseFloat(price) || 0;
  const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
  const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

  // If discount is provided, return negative discount value, else return margin value
  if (discountPercentage && parseFloat(discountPercentage) > 0) {
    // console.log("Applying discount:", -discount);
    return -discount;
  }
  customerMarginAmt.value = margin;
  // console.log("Applying margin:", margin);
  return margin;
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
    // Margin can be percent or amount
    if (amountType === "percent") {
      margin = (price * (parseFloat(marginAmount) || 0)) / 100;
    } else {
      margin = parseFloat(marginAmount) || 0;
    }
  }
  return margin;
};
const passengerCount = parseInt(bookingDetails?.value?.[0]?.pessangers.length || 1);
const agentAmount = parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0);
const agentDiscount = parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0);
const margin = parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0);
const airportMargin = parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0);

function calculateTotalFare(fare) {
  // 1️⃣ Customer margin OR discount (returns NEGATIVE if discount % is applied)
  const customerMargin = parseFloat(
    calculateCustomerMargin(
      fare.base_price,
      CustomerMargin?.value?.discount || 0,
      CustomerMargin?.value?.margin_amount || 0
    )
  );

  // 2️⃣ Airport / Type margin (per passenger)
  const typeMargin =
    parseFloat(
      calculateTypeMargin(
        user.value,
        airportMargins.value,
      )
    ) * passengerCount;

  // 3️⃣ Airline margin (markup/discount according to API)
  const airlineMargin = parseFloat(
    calculateFareMargin(
      fare.base_price,
      fare.margin_amount,
      fare.margin_type,
      fare.amount_type
    )
  );

  // 4️⃣ Taxes + Fees + Service Charges + Ancillaries
  const taxes =
    parseFloat(fare.surchage || 0) +
    parseFloat(fare.taxes || 0) +
    parseFloat(fare.fees || 0) +
    parseFloat(fare.service_charges || 0) +
    parseFloat(fare.ancillaries_charges || 0);

  // 5️⃣ The main billable fare
  const billable =
    parseFloat(fare.base_price || 0) +
    taxes +
    airlineMargin * passengerCount +
    typeMargin;

  // 6️⃣ Apply customer margin (per passenger) + other charges
  const total =
    billable +
    customerMargin * passengerCount + // discount OR margin
    parseFloat(CustomerMargin?.value?.other_charges || 0) +
    extraServicesTotal.value +
    totalSeatsCost.value;

  finalPayableAmount.value = total;
  return total;
}

const getPassengerExtraBaggage = (pIdx, travelerType) => {
  return selectedAncillariesList.filter(x =>
    x.pIdx === pIdx &&
    x.groupCode.toLowerCase().includes("bag") &&   // XBAG matches
    x.type?.toUpperCase() === travelerType.toUpperCase()
  )
}

function fetchAncillaries() {
  store.dispatch("flight/" + FETCH_ANCILLARIES, {
    bookingId: bookingDetails?.value?.[0]?.id || booking_id,
    flight_provider: flight_provider,
  });
}

function parseData(data) {
  try {
    return JSON.parse(data);
  } catch (e) {
    console.error("Failed to parse data:", e);
    return [];
  }
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
// Add this computed property
const allPassengersHaveSeats = computed(() => {
  if (!passengers.value?.length) return false;
  
  // Check each passenger has a seat selected for each segment
  for (let pIdx = 0; pIdx < passengers.value.length; pIdx++) {
    const travelerRef = getPassengerTravelerRef(pIdx);
    // Check if passenger has seat in every segment
    const hasSeatInAllSegments = processedSeatMapData.value.every(segment => {
      const segmentRPH = segment.FlightSegmentInfo['@attributes'].RPH;
      const key = `${segmentRPH}-${travelerRef}`;
      return selectedSeats.value[key] !== undefined;
    });
    
    if (!hasSeatInAllSegments) return false;
  }
  return true;
});


// Lifecycle
onMounted(async () => {
  if (!user.value) await authStore.fetchUser();
  bookingSource = route.query.booking_source;
  flight_provider = route.query.flight_provider || route.query.provider || null;
  booking_id = route.query.booking_id;
  pnr = route.query.pnr;
  await Promise.all([fetchAgent(), fetchAgentLedger(), fetchBookingDetails(), fetchCustomerMarginValues(),
  ]);
});
</script>

<template>
  <div v-if="bookingDetails" class="min-h-screen container bg-gray-50 py-6 print:bg-white print:py-0">
    <div class=" mx-auto px-4 sm:px-6 lg:px-8 print:px-4">
      <!-- Header -->
      <div class="bg-white border border-gray-300 mb-6 print:border print:shadow-none">
        <div class="p-6 border-b border-gray-300 print:border-b">
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
      :disabled="!allPassengersHaveSeats"
      :class="!allPassengersHaveSeats ? 'opacity-50 cursor-not-allowed' : 'bg-primary hover:bg-primary/90'"
      class="w-full text-white font-medium">
      {{ !allPassengersHaveSeats ? `Select Seats for All Passengers First` : `Pay & Confirm Booking (${formatAmount(finalPayableAmount)})` }}
    </Button>

          <!-- <div v-if="Object.keys(selectedSeats).length === 0" class="bg-red-50 border border-red-200 rounded p-3">
        <p class="text-sm text-red-700">⚠️ Please select seats for all passengers before proceeding with payment.</p>
          </div> -->

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <button 
          @click="handlePaymentMethod('wallet')"
             :disabled="!allPassengersHaveSeats"
      :class="!allPassengersHaveSeats ? 'opacity-50 cursor-not-allowed' : ''"
          class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 transition disabled:hover:bg-white">
          <img src="/public/assets/wallet.png" alt="Wallet" class="h-8 w-8 mb-2" />
          <span class="text-sm font-medium">Wallet</span>
          <span class="text-xs text-gray-600">{{ formatAmount(agentLedger?.balance) }}</span>
        </button>

        <!-- :disabled="!allPassengersHaveSeats" -->
        <!-- :class="!allPassengersHaveSeats ?'opacity-50 cursor-not-allowed' : ''" -->
        <button 
           
          @click="handlePaymentMethod('stripe')"
          class="flex flex-col items-center p-4 border rounded-lg hover:bg-gray-50 transition disabled:hover:bg-white">
          <img src="/public/assets/credit-card.png" alt="Card" class="h-8 w-8 mb-2" />
          <span class="text-sm font-medium">Credit Card</span>
        </button>
          </div>
        </div>
      </div>


      <!-- Selected Seats Preview -->
      <div v-if="Object.keys(selectedSeats).length > 0" class="bg-white border border-gray-300 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
          <Briefcase class="h-5 w-5 text-primary" />
          Selected Seats
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="(seat, key) in selectedSeats" :key="key"
            class="border border-primary/20 bg-primary/5 rounded-lg p-4 relative">
            <button @click="removeSelectedSeat(seat.RPH, seat.travelerRefNumber)"
              class="absolute top-2 right-2 text-gray-500 hover:text-red-500">
              <X class="h-4 w-4" />
            </button>
            <div class="font-semibold text-gray-900">{{ seat.row }}{{ seat.seat }}</div>
            <div class="text-sm text-gray-600 mt-1">
              Passenger: {{passengers.find((p, idx) => getPassengerTravelerRef(idx) ===
                seat.travelerRefNumber)?.first_name || 'N/A' }}
            </div>
            <div class="text-sm text-gray-600">Flight Segment: {{ seat.RPH }}</div>
            <div v-if="seat.features" class="text-xs text-gray-500 mt-1">Features: {{ seat.features }}</div>
            <div class="text-primary font-semibold mt-2">
              {{ formatAmount(seat.price) }} {{ seat.currency }}
            </div>
          </div>
        </div>
        <div class="mt-4 pt-4 border-t border-gray-200">
          <div class="flex justify-between items-center">
            <span class="font-semibold text-gray-900">Total Seats Cost:</span>
            <span class="text-primary font-bold text-lg">{{ formatAmount(totalSeatsCost) }}</span>
          </div>
        </div>
      </div>

      <!-- Print Section -->
      <div id="print-section" class="space-y-6">
        <!-- Extra Services Button -->
        <!-- <div class="mt-4">
          <button @click="isExtraServicesOpen = true" type="button"
            class="w-full border-2 border-primary text-primary hover:bg-primary/5 font-medium py-3 px-4 transition flex items-center justify-center gap-2">
            <Briefcase class="h-5 w-5" />
            Add Extra Services (Baggage, Wheelchair, etc.)
            <span v-if="extraServicesTotal > 0" class="ml-2 text-green-600 font-bold">
              +{{ formatAmount(extraServicesTotal) }}
            </span>
          </button>
        </div> -->
        <!-- Seat Selection Mandatory Warning -->
        
        <!-- Seat Selection Button -->
        <!-- <div class="mt-4">
          <button @click="openSeatMap" type="button"
            class="w-full border-2 border-primary text-primary hover:bg-primary/5 font-medium py-3 px-4 transition flex items-center justify-center gap-2">
            <Users class="h-5 w-5" />
            Select Seats
            <span v-if="totalSeatsCost > 0" class="ml-2 text-green-600 font-bold">
              +{{ formatAmount(totalSeatsCost) }}
            </span>
          </button>
        </div> -->

        <!-- Flight Itinerary -->
        <div class="bg-white border border-gray-300 print:border">
          <div
            class="bg-primary text-white p-4 border-b border-primary print:bg-white print:text-gray-900 print:border-b">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <Plane class="h-5 w-5" /> Flight Itinerary
            </h2>
          </div>
          <div class="p-5 space-y-6">
            <div v-for="(booking, idx) in bookingDetails" :key="booking?.id">
              <div v-for="(flight, fIdx) in getFlightLegs(booking)" :key="fIdx" class="space-y-4">
                <div class="text-sm font-medium text-gray-700">
                  {{ flight?.from?.city?.name }} ({{ flight?.from?.iata }}) to {{ flight?.to?.city?.name }} ({{
                    flight?.to?.iata }})
                </div>
                <div v-for="(segment, sIdx) in flight.segments" :key="sIdx">
                  <div v-if="segment.layover_time" class="bg-amber-50 border-l-4 border-amber-500 p-3 mb-3 text-xs">
                    <div class="flex items-center justify-center text-amber-800">
                      <ClockIcon class="h-4 w-4 mr-1" />
                      Layover: {{ formatDuration(segment.layover_time) }}
                    </div>
                  </div>
                  <div
                    class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 border border-gray-300 print:bg-white">
                    <div class="space-y-2">
                      <div class="flex items-center gap-2">
                        <img :src="segment.operating_carrier.logo" class="h-7 w-7" />
                        <div>
                          <p class="text-sm font-semibold">{{ segment.operating_carrier.name }}</p>
                          <p class="text-xs text-gray-600">{{ segment.flight_number }}</p>
                        </div>
                      </div>
                      <p class="font-medium text-gray-900">{{ formatDateTime(segment.departure_at) }}</p>
                      <p class="text-sm text-gray-600">{{ segment.from.name }} ({{ segment.from.iata }})</p>
                      <p class="text-xs text-gray-500">Terminal: {{ segment.from_terminal?.Gate || 'N/A' }}</p>
                    </div>
                    <div class="flex items-center justify-center">
                      <div class="text-center">
                        <p class="text-xs font-medium text-gray-700">{{ formatFlightDuration(segment) }}</p>
                        <div class="flex items-center my-2">
                          <div class="w-2 h-2 bg-primary"></div>
                          <div class="flex-1 h-0.5 bg-primary mx-1"></div>
                          <div class="w-2 h-2 bg-primary"></div>
                        </div>
                        <p class="text-xs text-gray-500">{{ segment.from.iata }} to {{ segment.to.iata }}</p>
                      </div>
                    </div>
                    <div class="space-y-2 text-right">
                      <p class="font-medium text-gray-900">{{ formatDateTime(segment.arrival_at) }}</p>
                      <p class="text-sm text-gray-600">{{ segment.to.name }} ({{ segment.to.iata }})</p>
                      <p class="text-xs text-gray-500">Terminal: {{ segment.to_terminal?.Gate || 'N/A' }}</p>
                      <p class="text-xs text-gray-500">{{ segment.aircraft?.model || 'N/A' }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Passenger Details -->
        <div class="bg-white border border-gray-300 print:border">
          <div class="bg-gray-50 p-4 border-b border-gray-300 print:bg-white print:border-b">
            <h2 class="text-lg font-semibold flex items-center gap-2">
              <Users class="h-5 w-5 text-primary" /> Passenger Details
            </h2>
          </div>
          <div class="p-5 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
              <thead>
                <tr class="border-b text-left text-gray-600">
                  <th class="pb-2 font-medium">Name</th>
                  <th class="pb-2 font-medium">Type</th>
                  <th class="pb-2 font-medium">Gender</th>
                  <th class="pb-2 font-medium">Nationality</th>
                  <th class="pb-2 font-medium">Selected Seat</th>
                  <th class="pb-2 hidden print:table-cell font-medium">Ticket No</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(pax, i) in getPassengers(bookingDetails[0])" :key="i" class="border-b last:border-0">
                  <td class="py-3">{{ pax.title }} {{ pax.first_name }} {{ pax.last_name }}</td>
                  <td class="py-3 text-gray-600">{{ pax.type || pax.traveler_type }}</td>
                  <td class="py-3 text-gray-600">{{ pax.gender?.toUpperCase() }}</td>
                  <td class="py-3 text-gray-600">{{ pax.nationality }}</td>
                  <td class="py-3">
                    <span
                      v-if="getSelectedSeat(processedSeatMapData[0]?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(i))"
                      class="bg-primary/10 text-primary px-2 py-1 rounded text-xs font-medium">
                      {{ getSelectedSeat(processedSeatMapData[0]?.FlightSegmentInfo['@attributes']?.RPH,
                      getPassengerTravelerRef(i)) }}
                    </span>
                    <span v-else class="text-gray-400 text-xs">Not selected</span>
                  </td>
                  <td class="py-3 hidden print:table-cell">{{ pax.ticket_number || '-' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Fare Breakdown -->
        <div class="bg-white border border-gray-300 p-6" v-if="isDetailsInfoVisible">
          <h2 class="text-lg font-bold text-gray-800 mb-4 border-b border-primary pb-2">
            FARE BREAKDOWN
          </h2>
          <div>

            <div>
              <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                  <thead>
                    <tr class="bg-gray-50">
                      <th class="py-1.5 px-2 text-left font-medium text-gray-600">Subtotal</th>
                      <th class="py-1.5 px-2 text-left font-medium text-gray-600">Taxes + Fees
                      </th>
                      <th class="py-1.5 px-2 text-left font-medium text-gray-600">Grand Total</th>
                    </tr>
                  </thead>
                  <tbody v-if="pnrDetails?.totalPrice" class="divide-y divide-gray-100 ">
                    <tr class="hover:bg-gray-50">
                      <td class="py-1.5 px-2 uppercase">
                        {{ formatAmount(
                          calculateFinalPrice(
                            pnrDetails?.totalPrice?.baseAmount?.amount || 0,
                            fare?.margin_amount,
                            fare?.margin_type,
                            fare?.amount_type || 0
                          )
                          + (
                            (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                              + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                              - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                            * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                          )
                        ) }}
                      </td>

                      <td class="py-1.5 px-2 uppercase">
                        {{ formatAmount(pnrDetails?.totalPrice?.totalTaxAmount?.amount || 0) }}
                      </td>
                      <td class="py-1.5 px-2 uppercase font-bold">
                        {{ formatAmount(
                          parseFloat(pnrDetails?.totalAmount?.amount || 0)
                          + (
                            (parseFloat(bookingDetails?.[0]?.agent_markup || 0)
                              + parseFloat(bookingDetails?.[0]?.agent_margin || 0)
                              - parseFloat(bookingDetails?.[0]?.agent_discount || 0))
                            * parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                          )
                        ) }}
                      </td>

                    </tr>
                  </tbody>
                  <tbody v-else-if="pnrDetails?.fares?.length" class="divide-y divide-gray-100">
                    <tr v-for="(fare, index) in pnrDetails.fares" :key="index" class="hover:bg-gray-50">
                      <td class="py-1.5 px-2 uppercase">
                        {{ formatAmount(
                          calculateFinalPrice(
                            parseFloat(fare?.totals?.subtotal) || 0,
                            fare?.margin_amount,
                            fare?.margin_type,
                            fare?.amount_type || 0
                          ) +
                          (parseFloat(bookingDetails?.[0]?.agent_markup || 0) +
                            parseFloat(bookingDetails?.[0]?.agent_margin || 0) -
                            parseFloat(bookingDetails?.[0]?.agent_discount || 0)) *
                          parseInt(bookingDetails?.[0]?.pessangers?.length || 1)
                        ) }}
                      </td>

                      <td class="py-1.5 px-2 uppercase">
                        {{ formatAmount(fare?.totals?.taxes || 0) }}
                      </td>

                      <td class="py-1.5 px-2 uppercase font-bold">
                        {{ formatAmount(parseFloat(fare?.totals?.total || 0)) }}
                      </td>
                    </tr>
                  </tbody>


                  <tbody v-else class="divide-y divide-gray-100 ">
                    <template v-for="(flight, index) in parseFlightData(bookingDetails[0]?.flight_data)?.leg?.flights"
                      :key="index">
                      <tr v-for="(fare, fareIndex) in flight.fares.filter(f => {
                        // fare_reference can be an array or a single value
                        const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                          ? parseFlightData(bookingDetails[0]?.fare_reference)
                          : [parseFlightData(bookingDetails[0]?.fare_reference)];
                        return fareRefs.includes(f.ref_id);
                      })" :key="fareIndex" class="hover:bg-gray-50">
                        <td class="py-1.5 px-2 uppercase">
                          {{ formatAmount(calculateFinalPrice(fare?.base_price,
                            fare?.margin_amount,
                            fare?.margin_type, fare?.amount_type) +
                            parseFloat((agentAmount
                              * passengerCount)) - parseFloat((agentDiscount
                                * passengerCount)) + margin) }}
                        </td>
                        <td class="py-1.5 px-2 uppercase">
                          {{ formatAmount(calculateTaxes(fare)) }}
                        </td>
                        <td class="py-1.5 px-2 uppercase font-bold">
                          <!-- {{ formatAmount(fare?.billable_price) }} -->
                          {{
                            formatAmount(calculateTotalFare(fare))
                          }}
                        </td>
                      </tr>
                    </template>
                  </tbody>

                </table>
              </div>
            </div>


          </div>

          <!-- Detailed Breakdown -->
          <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">


            <div v-if="extraServicesTotal > 0" class="bg-green-50 p-4 rounded border border-green-200">
              <h3 class="font-semibold text-green-700 mb-2">Extra Services</h3>
              <div class="space-y-1 text-green-600">
                <div class="flex justify-between">
                  <span>Additional Services:</span>
                  <span>+ {{ formatAmount(extraServicesTotal) }}</span>
                </div>
              </div>
            </div>

            <div v-if="totalSeatsCost > 0" class="bg-blue-50 p-4 rounded border border-blue-200">
              <h3 class="font-semibold text-blue-700 mb-2">Seat Selection</h3>
              <div class="space-y-1 text-blue-600">
                <div class="flex justify-between">
                  <span>Selected Seats:</span>
                  <span>+ {{ formatAmount(totalSeatsCost) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Baggage Details -->
        <div class="bg-white border border-gray-300 p-6">
          <h2 class="text-lg font-bold text-gray-800 mb-4 border-b border-primary pb-2">
            Baggage Details
          </h2>
          <div v-for="booking in bookingDetails" :key="booking?.id" class="space-y-6">
            <div v-for="(flight, index) in flightData?.leg?.flights" :key="index" class="mb-6">
              <div class="text-sm font-semibold text-gray-800 mb-3">
                {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name }}
              </div>
              <div v-for="(segment, sIndex) in flight?.segments" :key="sIndex" class="mb-4">
                <div class="text-xs font-medium text-gray-700 mb-2">
                  {{ segment.from.iata }} → {{ segment.to.iata }}
                  <span v-for="(code, cIndex) in flight?.fares?.[0]?.booking_codes?.filter(
                    c => c.segment_ref_id === segment.ref_id
                  )" :key="cIndex" class="ml-2 text-primary font-medium">
                    | {{ code.booking_code }}
                  </span>
                </div>
                <table class="w-full text-xs border-2 border-gray-400">
                  <thead>
                    <tr class="bg-gray-100">
                      <th class="py-2 px-3 text-left font-semibold text-gray-700">Pax Type</th>
                      <th class="py-2 px-3 text-left font-semibold text-gray-700">Check-In Baggage</th>
                      <th class="py-2 px-3 text-left font-semibold text-gray-700">Cabin Baggage</th>
                      <th class="py-2 px-3 text-left font-semibold text-gray-700">Extra Baggage</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-300">
                    <template v-for="(fare, fIndex) in flight?.fares" :key="fIndex">

                      <template v-if="parseData(bookingDetails?.[0]?.fare_reference)?.includes(fare?.ref_id)">
                        <tr
                          v-for="(travelerType, tIndex) in [...new Set(fare.baggage_policies.map(bp => bp.traveler_type))]"
                          :key="tIndex">

                          <td class="py-2 px-3 uppercase">{{ travelerType }}</td>
                          <td class="py-2 px-3 uppercase">
                            {{fare.baggage_policies.find(bp => bp.traveler_type === travelerType && bp.type ===
                              'checked')?.description || 'N/A'}}
                          </td>
                          <td class="py-2 px-3 uppercase">
                            {{fare.baggage_policies.find(bp => bp.traveler_type === travelerType && bp.type ===
                              'carry')?.description || 'N/A'}}
                          </td>
                          <td class="py-2 px-3 uppercase text-blue-700">
                            {{ selectedAncillariesList[tIndex]?.item['@attributes']?.ItemTitle }}
                          </td>
                        </tr>

                        <!-- EXTRA BAGGAGE FROM SELECTED ANCILLARIES -->
                        <tr v-for="extra in getPassengerExtraBaggage(tIndex)" :key="'ex-' + tIndex" class="bg-blue-50">
                          <td class="py-2 px-3 font-semibold text-blue-700">
                            EXTRA BAGGAGE
                          </td>
                          <td class="py-2 px-3 uppercase text-blue-700">
                            {{ extra.item['@attributes'].ItemTitle }}
                          </td>
                          <td class="py-2 px-3 text-blue-700 font-bold">
                            {{ extra.item['@attributes'].ChargeAmount }}
                            {{ extra.item['@attributes'].ChargeCurrency }}
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

    <!-- Extra Services Dialog - FIXED -->
    <div v-if="isExtraServicesOpen"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="isExtraServicesOpen = false">
      <div class="bg-white rounded-lg max-w-5xl w-full max-h-screen overflow-y-auto border-2 border-gray-300">
        <div class="sticky top-0 bg-white border-b border-gray-300 p-5 flex justify-between items-center z-10">
          <h3 class="text-xl font-bold text-gray-900">Extra Services</h3>
          <button @click="isExtraServicesOpen = false" class="text-gray-500 hover:text-gray-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 space-y-10">
          <!-- Loop through each flight segment -->
          <div v-for="(segment, segIdx) in ancillarySegments" :key="segIdx"
            class="border-2 border-blue-200 rounded-lg p-6 bg-blue-50/30">
            <div class="flex items-center gap-3 mb-5">
              <div class="bg-blue-600 text-white px-3 py-1 rounded text-sm font-bold">Segment {{ segIdx + 1 }}</div>
              <div class="font-medium">
                {{ segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode }}
                → {{ segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode }}
                | {{ formatDateTime(segment.FlightSegmentInfo['@attributes'].DepartureDateTime) }}
                | Flight {{ segment.FlightSegmentInfo['@attributes'].FlightNumber }}
              </div>
            </div>

            <!-- Groups inside this segment -->
            <div v-for="(group, gIdx) in segment.AncillaryItemSets.AncillaryItemSet" :key="gIdx"
              class="border border-gray-300 rounded-lg p-5 bg-white mb-6 shadow-sm">

              <div class="flex items-start justify-between mb-4">
                <div>
                  <h4 class="text-lg font-semibold text-gray-900">
                    {{ group['@attributes'].GroupTitle }}
                    <span class="text-sm text-gray-500">(Segment {{ segIdx + 1 }})</span>
                  </h4>
                  <p class="text-sm text-gray-600 mt-1" v-if="group['@attributes'].GroupDescription">
                    {{ group['@attributes'].GroupDescription }}
                  </p>
                </div>
                <Badge v-if="group['@attributes'].MultipleChoice === 'true'" variant="secondary">
                  Multiple allowed
                </Badge>
              </div>

              <div class="space-y-5">
                <!-- Per Passenger -->
                <div v-for="(passenger, pIdx) in passengers" :key="pIdx" class="bg-gray-50 p-4 rounded-lg border">
                  <p class="font-medium text-sm mb-3 text-gray-800">
                    {{ passenger.title }} {{ passenger.first_name }} {{ passenger.last_name }} ({{ passenger.type }})
                  </p>

                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <!-- Ancillary Items -->
                    <label v-for="item in getItemsArray(group.AncillaryItems.AncillaryItem)"
                      :key="item['@attributes'].ItemCode"
                      class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-100 transition"
                      :class="{
                        'border-primary bg-primary/10 ring-2 ring-primary/50': isSegmentSelected(segIdx, pIdx, group['@attributes'].GroupCode, item['@attributes'].ItemCode),
                        'opacity-50 cursor-not-allowed': item['@attributes'].Available === 'false'
                      }">
                      <div class="flex items-center gap-3">
                        <input :type="group['@attributes'].MultipleChoice === 'true' ? 'checkbox' : 'radio'"
                          :name="`ancillary-${segIdx}-${gIdx}-${pIdx}`" :value="item['@attributes'].ItemCode"
                          :checked="isSegmentSelected(segIdx, pIdx, group['@attributes'].GroupCode, item['@attributes'].ItemCode)"
                          :disabled="item['@attributes'].Available === 'false'"
                          @change="toggleSegmentAncillary(segIdx, pIdx, group['@attributes'].GroupCode, item, $event, passenger.type)"
                          class="h-4 w-4 text-primary focus:ring-primary" />
                        <div>
                          <p class="font-medium text-sm">{{ item['@attributes'].ItemTitle }}</p>
                          <p v-if="item['@attributes'].Description" class="text-xs text-gray-500">
                            {{ item['@attributes'].Description }}
                          </p>
                        </div>
                      </div>
                      <span class="font-bold text-primary">
                        {{ formatAmount(item['@attributes'].ChargeAmount) }} {{ item['@attributes'].ChargeCurrency }}
                      </span>
                    </label>

                    <!-- None Option for Radio Groups -->
                    <label v-if="group['@attributes'].MultipleChoice === 'false'"
                      class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-100"
                      :class="{ 'border-primary bg-primary/10 ring-2 ring-primary/50': !hasAnySelectionInGroup(segIdx, pIdx, group['@attributes'].GroupCode) }">
                      <div class="flex items-center gap-3">
                        <input type="radio" :name="`ancillary-${segIdx}-${gIdx}-${pIdx}`" value=""
                          :checked="!hasAnySelectionInGroup(segIdx, pIdx, group['@attributes'].GroupCode)"
                          @change="clearSegmentGroup(segIdx, pIdx, group['@attributes'].GroupCode)">
                        <span class="text-sm font-medium">None</span>
                      </div>
                      <span class="text-gray-400">Free</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Total & Actions -->
        <div
          class="sticky bottom-0 bg-white border-t border-gray-300 p-5 flex flex-col sm:flex-row justify-between items-center gap-4">
          <p class="text-lg font-semibold">
            Total Extra Services Cost:
            <span class="text-primary text-xl">{{ formatAmount(extraServicesTotal) }} PKR</span>
          </p>
          <div class="flex gap-3">
            <button @click="isExtraServicesOpen = false"
              class="px-6 py-3 border-2 border-gray-400 hover:bg-gray-50 font-medium rounded-lg">
              Cancel
            </button>
            <button @click="applyAncillariesAndClose"
              class="px-6 py-3 bg-primary text-white font-medium hover:bg-primary/90 rounded-lg">
              Add to Booking ({{ formatAmount(extraServicesTotal) }} PKR)
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Seat Map Dialog -->
    <div v-if="isSeatMapOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="closeSeatMap">

      <div class="bg-white rounded-lg max-w-6xl w-full max-h-screen overflow-y-auto border-2 border-gray-300">
        <!-- Header -->
        <div class="sticky top-0 bg-white border-b border-gray-300 p-5 flex justify-between items-center">
          <h3 class="text-xl font-bold text-gray-900">Select Seats</h3>
          <button @click="closeSeatMap" class="text-gray-500 hover:text-gray-700">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Seat Map Content -->
        <div class="p-6 space-y-8" v-if="processedSeatMapData.length > 0">
          <!-- Loop through each segment -->
          <div v-for="(segment, segmentIndex) in processedSeatMapData" :key="segmentIndex"
            class="border border-gray-200 rounded-lg p-5 bg-gray-50">

            <!-- Segment Header -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-gray-900 mb-2">
                Flight {{ segment.FlightSegmentInfo['@attributes'].FlightNumber }}
              </h4>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="font-medium">Departure:</span>
                  <p>{{ formatDateTime(segment.FlightSegmentInfo['@attributes'].DepartureDateTime) }}</p>
                  <p>{{ segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode }}</p>
                </div>
                <div>
                  <span class="font-medium">Arrival:</span>
                  <p>{{ formatDateTime(segment.FlightSegmentInfo['@attributes'].ArrivalDateTime) }}</p>
                  <p>{{ segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode }}</p>
                </div>
                <div>
                  <span class="font-medium">Aircraft:</span>
                  <p>{{ segment.FlightSegmentInfo.Equipment['@attributes'].AirEquipType }}</p>
                </div>
                <div>
                  <span class="font-medium">Class:</span>
                  <p>{{ segment.FlightSegmentInfo['@attributes'].CabinClass }}</p>
                </div>
              </div>
            </div>
            <!-- Passenger-wise Seat Selection -->
            <div v-for="(passenger, pIdx) in passengers" :key="pIdx" class="mb-8">
              <div class="bg-white p-4 rounded border mb-4">
                <p class="font-medium text-gray-900 mb-3">
                  {{ passenger.title }} {{ passenger.first_name }} {{ passenger.last_name }} ({{ passenger.type }})
                  <span
                    v-if="getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pIdx))"
                    class="text-primary font-semibold ml-2">
                    - Selected: {{ getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH,
                    getPassengerTravelerRef(pIdx)) }}
                  </span>
                </p>

                <!-- Seat Map Grid -->
                <div class="seat-map-container overflow-x-auto">
                  <!-- Cabin Class Header -->
                  <div class="text-center mb-4">
                    <h5 class="font-semibold text-gray-700">Cabin Class</h5>
                  </div>

                  <!-- Seat Legend -->
                  <div class="flex flex-wrap gap-4 mb-4 text-xs">
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 bg-green-100 border border-green-500 rounded"></div>
                      <span>Available</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 bg-red-100 border border-red-500 rounded"></div>
                      <span>Occupied</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 bg-primary/20 border border-primary rounded"></div>
                      <span>Selected</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 bg-gray-100 border border-gray-300 rounded"></div>
                      <span>No Seat</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 border-2 border-orange-300 rounded"></div>
                      <span>Exit Row</span>
                    </div>
                    <div class="flex items-center gap-1">
                      <div class="w-4 h-4 border-2 border-blue-300 rounded"></div>
                      <span>Overwing</span>
                    </div>
                  </div>

                  <!-- Seat Map Grid -->
                  <div class="inline-block min-w-full">
                    <!-- Column Headers -->
                    <div class="flex mb-2">
                      <div class="w-12"></div> <!-- Row number space -->
                      <div v-for="col in ['A', 'B', 'C', '', 'D', 'E', 'F']" :key="col"
                        class="w-10 h-6 flex items-center justify-center text-xs font-semibold">
                        {{ col }}
                      </div>
                    </div>
                    <!-- Rows -->
                    <div v-for="row in segment?.SeatMapDetails?.CabinClass?.RowInfo"
                      :key="row['@attributes']?.RowNumber" class="flex items-center mb-1">
                      <!-- Row Number -->
                      <div class="w-12 h-8 flex items-center justify-center text-sm font-semibold">
                        {{ row['@attributes'].RowNumber }}
                      </div>

                      <!-- Seats -->
                      <div v-for="(seat, seatIndex) in row.SeatInfo" :key="seatIndex"
                        class="w-10 h-8 mx-1 flex items-center justify-center">

                        <!-- No Seat Here -->
                        <div v-if="seat.Availability === 'NoSeatHere'"
                          class="w-6 h-6 bg-gray-100 border border-gray-300 rounded">
                        </div>

                        <!-- Actual Seat -->
                        <div v-else
                          @click="selectSeat(segment?.FlightSegmentInfo['@attributes']?.RPH, pnrData?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler?.Email ? [pnrData?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler][pIdx] : pnrData?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler[pIdx], row['@attributes'].RowNumber, seat.Summary['@attributes'].SeatNumber, seat)"
                          class="relative w-6 h-6 border-2 rounded flex items-center justify-center text-xs font-semibold cursor-pointer transition-all"
                          :class="getSeatClasses(seat, segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pIdx), row['@attributes'].RowNumber, seat.Summary['@attributes'].SeatNumber)"
                          :title="getSeatTooltip(seat)">

                          {{ seat.Summary['@attributes'].SeatNumber }}

                          <!-- Seat Features Icons -->
                          <div v-if="seat.Features" class="absolute -top-1 -right-1 flex">
                            <span v-if="seat.Features.includes('ExitRow')" class="text-xs text-orange-500">🚪</span>
                            <span v-if="seat.Features.includes('Overwing')" class="text-xs text-blue-500">✈️</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Seat Price Info -->
                <div
                  v-if="getSelectedSeatPrice(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pIdx))"
                  class="mt-3 p-3 bg-primary/10 rounded border border-primary/20">
                  <p class="text-sm font-semibold">
                    Selected Seat: {{ getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH,
                    getPassengerTravelerRef(pIdx)) }} -
                    {{ formatAmount(getSelectedSeatPrice(segment?.FlightSegmentInfo['@attributes']?.RPH,
                    getPassengerTravelerRef(pIdx))) }}
                  </p>
                  <p v-if="getSelectedSeatFeatures(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pIdx))"
                    class="text-xs text-gray-600 mt-1">
                    Features: {{ getSelectedSeatFeatures(segment?.FlightSegmentInfo['@attributes']?.RPH,
                    getPassengerTravelerRef(pIdx)) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="p-6 text-center text-gray-500">
          No seat map data available.
        </div>

        <!-- Footer -->
        <div class="sticky bottom-0 bg-white border-t border-gray-300 p-5 flex justify-between items-center">
          <p class="text-lg font-semibold">
            Total Seats Cost:
            <span class="text-primary">{{ formatAmount(totalSeatsCost) }}</span>
          </p>

          <div class="flex gap-3">
            <button @click="closeSeatMap" class="px-6 py-3 border-2 border-gray-400 hover:bg-gray-50 font-medium">
              Cancel
            </button>

            <button @click="confirmSeatSelection"
              class="px-6 py-3 bg-primary text-white font-medium hover:bg-primary/90">
              Confirm Seats ({{ formatAmount(totalSeatsCost) }})
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Other Dialogs (Low Balance, Confirm, etc.) -->
    <div v-if="isLowBalanceDialogOpen"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="isLowBalanceDialogOpen = false">
      <div class="bg-white border-2 border-gray-300 max-w-md w-full p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Low Balance</h3>
        <p class="text-sm text-gray-600 mb-6">Your wallet balance is insufficient. Please deposit funds.</p>
        <div class="flex justify-end gap-3">
          <button @click="isLowBalanceDialogOpen = false"
            class="px-4 py-2 border-2 border-gray-400 hover:bg-gray-50">Cancel</button>
          <button @click="router.push({ name: 'Deposits' })"
            class="px-4 py-2 bg-primary text-white border-2 border-primary">Deposit Now</button>
        </div>
      </div>
    </div>
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
    <!-- Confirm Dialog -->
    <div v-if="isConfirmDialogOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="isConfirmDialogOpen = false">
      <div class="bg-white border-2 border-gray-300 p-6 max-w-md w-full">
        <h3 class="text-lg font-semibold mb-4">Confirm Booking</h3>
        <p class="text-sm text-gray-600 mb-6">Deduct {{ formatAmount(finalPayableAmount) }} from wallet and confirm?</p>
        <div class="flex justify-end gap-3">
          <button @click="isConfirmDialogOpen = false"
            class="px-4 py-2 border-2 border-gray-400 hover:bg-gray-50">Cancel</button>
          <button @click="confirmBooking"
            class="px-4 py-2 bg-primary text-white border-2 border-primary">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Loading -->
  <div v-else class="fixed inset-0 bg-white/75 flex items-center justify-center z-50">
    <Spinner />
  </div>
</template>
<style scoped>
.seat-map-container {
  min-width: fit-content;
}

/* Custom scrollbar for seat map */
.seat-map-container::-webkit-scrollbar {
  height: 8px;
}

.seat-map-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.seat-map-container::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.seat-map-container::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}
</style>