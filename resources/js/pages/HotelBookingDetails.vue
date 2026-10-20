<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { Printer } from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const errorMessage = ref("");
const showCancellationConfirmation = ref(false);
const booking = computed(() => hotelStore.getBooking);
const isLoading = computed(() => hotelStore.getIsLoadingBookingDetails);
const isCancelling = computed(() => hotelStore.getIsCancelling);
const isPaymentPending = computed(() => booking.value?.status === "payment_pending");
const guestsByRoom = computed(() => (booking.value?.guests || []).reduce((rooms, guest) => {
  const roomIndex = Number(guest.room_index || 0);
  rooms[roomIndex] = rooms[roomIndex] || [];
  rooms[roomIndex].push(guest);
  return rooms;
}, {}));

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || !money?.currency) {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const formatPolicy = (policy) => {
  const charge = policy?.CancellationCharge;
  const type = policy?.ChargeType ? ` (${policy.ChargeType})` : "";
  return `From ${policy?.FromDate || "the stated date"}: ${charge ?? "—"}${type}`;
};

const loadBooking = async () => {
  const bookingId = route.query.booking_id;

  if (!bookingId || typeof bookingId !== "string") {
    errorMessage.value = "No hotel booking was supplied.";
    return;
  }

  try {
    await hotelStore.fetchHotelBooking(bookingId);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load the hotel booking.";
  }
};

const cancelBooking = async () => {
  if (!booking.value?.booking_id) return;

  errorMessage.value = "";

  try {
    await hotelStore.cancelHotelBooking(booking.value.booking_id);
    showCancellationConfirmation.value = false;
  } catch (error) {
    errorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Unable to cancel this hotel booking.";
  }
};

const printBooking = () => window.print();
const payNow = () => {
  if (!booking.value?.booking_id) return;
  router.push({ name: "CustomerPaymentView", query: { booking_type: "hotel", hotel_booking_id: booking.value.booking_id } });
};

onMounted(loadBooking);
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase text-primary">{{ isPaymentPending ? 'Hotel reservation' : 'Hotel voucher' }}</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-950">{{ isPaymentPending ? 'Reservation summary' : 'Booking details' }}</h1>
      </div>
      <div class="flex flex-wrap gap-2 print:hidden">
        <Button class="border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="printBooking"><Printer class="mr-2 h-4 w-4" />Print</Button>
        <Button v-if="isPaymentPending" class="bg-primary px-4 py-2 text-sm font-bold text-white" @click="payNow">Pay now</Button>
        <Button class="border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="router.push({ name: 'HotelSearch' })">Hotels</Button>
      </div>
    </div>

    <div v-if="isLoading" class="border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm">Loading booking details...</div>
    <div v-if="errorMessage" class="mt-4 border border-red-200 bg-red-50 p-5 text-sm text-red-800">{{ errorMessage }}</div>

    <div v-if="booking && !isLoading" class="mt-5">
      <article class="border border-gray-200 bg-white shadow-sm">
        <header class="border-b p-5" :class="isPaymentPending ? 'border-amber-200 bg-amber-50' : 'border-emerald-200 bg-emerald-50'">
          <p class="text-xs font-bold uppercase" :class="isPaymentPending ? 'text-amber-700' : 'text-emerald-700'">{{ isPaymentPending ? 'Pending payment' : booking.status }}</p>
          <h2 class="mt-1 text-xl font-bold" :class="isPaymentPending ? 'text-amber-950' : 'text-emerald-950'">{{ booking.hotel?.name || 'Hotel booking' }}</h2>
          <p v-if="isPaymentPending" class="mt-2 text-sm text-amber-800">This is a reservation summary, not a confirmed hotel voucher. Complete payment to confirm the booking.</p>
          <p v-else-if="booking.hotel?.city || booking.hotel?.country" class="mt-1 text-sm text-emerald-800">{{ [booking.hotel.city, booking.hotel.country].filter(Boolean).join(', ') }}</p>
        </header>

        <div class="grid lg:grid-cols-2">
          <section class="p-5 lg:border-r lg:border-gray-100">
            <h2 class="font-semibold text-gray-950">Reservation details</h2>
            <dl class="mt-4 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-1">
              <div><dt class="text-gray-500">TBO confirmation number</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.confirmation_number || 'Pending' }}</dd></div>
              <div><dt class="text-gray-500">Hotel confirmation number</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.hotel_confirmation_number || 'Pending provider update' }}</dd></div>
              <div><dt class="text-gray-500">Booking reference</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.booking_reference_id }}</dd></div>
              <div><dt class="text-gray-500">Voucher status</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.voucher_status || 'Pending' }}</dd></div>
              <div><dt class="text-gray-500">Check in</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.check_in || '—' }}</dd></div>
              <div><dt class="text-gray-500">Check out</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.check_out || '—' }}</dd></div>
              <div><dt class="text-gray-500">Total fare</dt><dd class="mt-1 font-semibold text-gray-950">{{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</dd></div>
              <div><dt class="text-gray-500">Last checked</dt><dd class="mt-1 font-semibold text-gray-950">{{ booking.booking_details_checked_at ? new Date(booking.booking_details_checked_at).toLocaleString() : 'Not yet checked' }}</dd></div>
            </dl>
          </section>

          <section v-if="booking.guests?.length" class="border-t border-gray-100 p-5 lg:border-t-0">
            <h2 class="font-semibold text-gray-950">Guests</h2>
            <div v-for="(guests, roomIndex) in guestsByRoom" :key="roomIndex" class="mt-4 border-t border-gray-100 pt-4 first:mt-3 first:border-t-0 first:pt-0">
              <p class="text-xs font-bold uppercase text-gray-500">Room {{ Number(roomIndex) + 1 }}</p>
              <ul class="mt-2 space-y-1 text-sm text-gray-700">
                <li v-for="guest in guests" :key="`${guest.room_index}-${guest.guest_index}`">{{ guest.title }} {{ guest.first_name }} {{ guest.last_name }} <span class="text-gray-500">({{ guest.type }})</span></li>
              </ul>
            </div>
          </section>
        </div>

        <section class="border-t border-gray-100 p-5">
          <h2 class="font-semibold text-gray-950">Cancellation policy</h2>
          <ul v-if="booking.cancellation_policies?.length" class="mt-3 space-y-2 text-sm text-gray-600">
            <li v-for="(policy, index) in booking.cancellation_policies" :key="index">{{ formatPolicy(policy) }}</li>
          </ul>
          <p v-else class="mt-2 text-sm text-gray-500">No cancellation policy is currently available.</p>

          <div v-if="booking.status === 'confirmed' && booking.confirmation_number" class="mt-5 border-t border-gray-100 pt-5">
            <div v-if="showCancellationConfirmation" class="border border-red-200 bg-red-50 p-4">
              <p class="text-sm font-semibold text-red-900">Cancel this confirmed booking?</p>
              <p class="mt-1 text-xs text-red-800">The cancellation policy above will apply. This action cannot be undone.</p>
              <div class="mt-4 flex gap-3">
                <Button class="bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700" :is-loading="isCancelling" @click="cancelBooking">Confirm cancellation</Button>
                <Button class="border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" :disabled="isCancelling" @click="showCancellationConfirmation = false">Keep booking</Button>
              </div>
            </div>
            <Button v-else class="border border-red-300 bg-white px-4 py-2 text-sm font-bold text-red-700 hover:bg-red-50" @click="showCancellationConfirmation = true">Cancel booking</Button>
          </div>

          <p v-else-if="booking.status === 'cancelled'" class="mt-4 text-sm font-medium text-emerald-700">This booking was cancelled {{ booking.cancelled_at ? new Date(booking.cancelled_at).toLocaleString() : '' }}.</p>
          <p v-else-if="booking.status === 'cancellation_pending'" class="mt-4 text-sm font-medium text-amber-700">Cancellation is awaiting the provider result.</p>
        </section>
      </article>
    </div>
  </section>
</template>
