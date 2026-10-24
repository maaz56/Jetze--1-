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
  <div class="min-h-screen bg-slate-100/75 text-foreground py-8 sm:py-12">
    <section class="mx-auto max-w-5xl px-4 sm:px-6">
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-primary">My Reservations</p>
          <h1 class="mt-1 text-2xl font-black text-slate-900 sm:text-3xl">Hotel booking history</h1>
        </div>
        <Button
          class="h-10 rounded border border-slate-200 bg-white px-4 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900"
          @click="router.push({ name: 'HotelSearch' })"
        >
          Search hotels
        </Button>
      </div>

      <div v-if="isLoading" class="rounded border border-slate-200/90 bg-white p-8 text-center text-xs font-medium text-slate-500 shadow-sm animate-pulse">
        Loading reservations...
      </div>
      <div v-else-if="errorMessage" class="rounded border border-destructive/30 bg-white p-6 text-sm text-destructive shadow-sm">
        {{ errorMessage }}
      </div>
      <div v-else-if="!bookings.length" class="rounded border border-slate-200/90 bg-white p-12 text-center text-sm text-slate-500 shadow-sm">
        No hotel bookings found. Start exploring hotels for your next stay!
      </div>

      <div v-else class="space-y-3.5">
        <article
          v-for="booking in bookings"
          :key="booking.booking_id"
          class="flex flex-col gap-4 rounded border border-slate-200/90 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <span
              class="inline-block rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
              :class="
                booking.status === 'confirmed'
                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                  : booking.status === 'cancelled'
                  ? 'bg-rose-50 text-rose-700 border border-rose-200'
                  : 'bg-amber-50 text-amber-700 border border-amber-200'
              "
            >
              {{ booking.status }}
            </span>
            <h2 class="mt-1.5 text-base font-bold text-slate-900">{{ booking.hotel?.name || 'Hotel booking' }}</h2>
            <p class="mt-1 text-xs text-slate-600">
              <span class="font-medium text-slate-900">{{ booking.check_in }}</span> to <span class="font-medium text-slate-900">{{ booking.check_out }}</span> ·
              <span class="font-bold text-primary">{{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</span>
            </p>
            <p class="mt-1 font-mono text-[11px] text-slate-500">Ref: {{ booking.booking_reference_id }}</p>
          </div>
          <Button
            class="h-10 rounded bg-primary px-5 text-xs font-bold text-white shadow-xs hover:bg-primary/95 shrink-0"
            @click="router.push({ name: 'HotelBookingDetails', query: { booking_id: booking.booking_id } })"
          >
            View voucher
          </Button>
        </article>

        <div v-if="bookingMeta?.last_page > 1" class="flex items-center justify-between pt-4 text-xs font-medium text-slate-600">
          <Button
            variant="outline"
            class="h-9 rounded border-slate-200 bg-white px-3 font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
            :disabled="bookingMeta.current_page <= 1 || isLoading"
            @click="loadBookings(bookingMeta.current_page - 1)"
          >
            Previous
          </Button>
          <span>Page {{ bookingMeta.current_page }} of {{ bookingMeta.last_page }}</span>
          <Button
            variant="outline"
            class="h-9 rounded border-slate-200 bg-white px-3 font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
            :disabled="bookingMeta.current_page >= bookingMeta.last_page || isLoading"
            @click="loadBookings(bookingMeta.current_page + 1)"
          >
            Next
          </Button>
        </div>
      </div>
    </section>
  </div>
</template>
