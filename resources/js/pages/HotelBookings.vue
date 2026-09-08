<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();
const hotelStore = useHotelStore();
const bookings = computed(() => hotelStore.getBookings);
const bookingMeta = computed(() => hotelStore.getBookingMeta);
const isLoading = ref(true);
const errorMessage = ref("");

const loadBookings = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = "";

  try {
    await hotelStore.fetchHotelBookings({ page });
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load hotel bookings.";
  } finally {
    isLoading.value = false;
  }
};

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || !money?.currency) {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

onMounted(loadBookings);
</script>

<template>
  <section class="mx-auto max-w-5xl px-4 py-8 sm:py-12">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase text-primary">My hotels</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-950">Hotel booking history</h1>
      </div>
      <Button class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="router.push({ name: 'HotelSearch' })">Search hotels</Button>
    </div>

    <div v-if="isLoading" class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500">Loading bookings...</div>
    <div v-else-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800">{{ errorMessage }}</div>
    <div v-else-if="!bookings.length" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">No hotel bookings found.</div>

    <div v-else class="space-y-3">
      <article v-for="booking in bookings" :key="booking.booking_id" class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs font-bold uppercase text-primary">{{ booking.status }}</p>
          <h2 class="mt-1 font-semibold text-gray-950">{{ booking.hotel?.name || 'Hotel booking' }}</h2>
          <p class="mt-1 text-sm text-gray-500">{{ booking.check_in }} to {{ booking.check_out }} · {{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</p>
          <p class="mt-1 text-xs text-gray-500">Ref: {{ booking.booking_reference_id }}</p>
        </div>
        <Button class="rounded bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary/90" @click="router.push({ name: 'HotelBookingDetails', query: { booking_id: booking.booking_id } })">View voucher</Button>
      </article>

      <div v-if="bookingMeta?.last_page > 1" class="flex items-center justify-between pt-3 text-sm">
        <Button class="rounded border border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700" :disabled="bookingMeta.current_page <= 1 || isLoading" @click="loadBookings(bookingMeta.current_page - 1)">Previous</Button>
        <span class="text-gray-500">Page {{ bookingMeta.current_page }} of {{ bookingMeta.last_page }}</span>
        <Button class="rounded border border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700" :disabled="bookingMeta.current_page >= bookingMeta.last_page || isLoading" @click="loadBookings(bookingMeta.current_page + 1)">Next</Button>
      </div>
    </div>
  </section>
</template>
