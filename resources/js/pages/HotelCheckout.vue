<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { CalendarDays, Clock3, Hotel, ShieldCheck, Users } from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
hotelStore.resetCheckout();

const isLoading = ref(true);
const errorMessage = ref("");
const remainingSeconds = ref(0);
const customerDetails = ref([]);
const email = ref("");
const phoneNumber = ref("");
let countdownTimer;

const prebook = computed(() => hotelStore.getPrebook);
const isSavingReservation = computed(() => hotelStore.getIsBooking);
const isExpired = computed(() => remainingSeconds.value <= 0);
const finalFare = computed(() => prebook.value?.price_quote?.selling_money || null);
const roomName = computed(() => {
  const name = prebook.value?.room?.name;
  return Array.isArray(name) ? name.filter(Boolean).join(", ") : name || "Selected room";
});
const stayNights = computed(() => {
  const checkIn = prebook.value?.stay?.check_in;
  const checkOut = prebook.value?.stay?.check_out;
  return checkIn && checkOut ? Math.max(0, Math.round((new Date(checkOut) - new Date(checkIn)) / 86400000)) : null;
});

const formatMoney = (money) => {
  if (!money || money.amount === null || money.amount === undefined) return "Unavailable";
  return `${money.currency || ""} ${Number(money.amount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
};

const formatPolicy = (policy) => {
  const type = policy?.ChargeType ? ` (${policy.ChargeType})` : "";
  return `From ${policy?.FromDate || "the stated date"}: ${policy?.CancellationCharge ?? "—"}${type}`;
};

const formatCountdown = (seconds) => {
  const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
  return `${String(Math.floor(safeSeconds / 60)).padStart(2, "0")}:${String(safeSeconds % 60).padStart(2, "0")}`;
};

const makeCustomerDetails = (paxRooms) => (paxRooms || []).map((room) => ({
  customer_names: [
    ...Array.from({ length: Number(room.Adults || 0) }, () => ({ title: "Mr", first_name: "", last_name: "", type: "Adult" })),
    ...Array.from({ length: Number(room.Children || 0) }, () => ({ title: "Mr", first_name: "", last_name: "", type: "Child" })),
  ],
}));

const startCountdown = (seconds) => {
  window.clearInterval(countdownTimer);
  remainingSeconds.value = Math.max(0, Math.ceil(Number(seconds) || 0));
  countdownTimer = window.setInterval(() => {
    remainingSeconds.value = Math.max(0, remainingSeconds.value - 1);
    if (remainingSeconds.value === 0) window.clearInterval(countdownTimer);
  }, 1000);
};

const loadPrebook = async () => {
  const prebookId = route.query.prebook_id;

  if (!prebookId || typeof prebookId !== "string") {
    errorMessage.value = "No confirmed hotel room was supplied. Please return to hotel search.";
    isLoading.value = false;
    return;
  }

  errorMessage.value = "";
  isLoading.value = true;

  try {
    const response = await hotelStore.fetchPrebook(prebookId);
    customerDetails.value = makeCustomerDetails(prebook.value?.stay?.pax_rooms);
    startCountdown(response.data?.remaining_seconds);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load the confirmed room.";
  } finally {
    isLoading.value = false;
  }
};

const submitReservation = async () => {
  if (!prebook.value || isExpired.value) return;

  errorMessage.value = "";
  try {
    const response = await hotelStore.reserveHotel({
      prebook_id: prebook.value.prebook_id,
      email: email.value,
      phone_number: phoneNumber.value,
      customer_details: customerDetails.value,
    });
    router.push({ name: "HotelBookingDetails", query: { booking_id: response.data?.booking_id } });
  } catch (error) {
    errorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Unable to save this reservation.";
  }
};

onMounted(loadPrebook);
onBeforeUnmount(() => window.clearInterval(countdownTimer));
</script>

<template>
  <main class="min-h-screen bg-slate-50 py-7 sm:py-10">
    <section class="mx-auto max-w-6xl px-4 sm:px-6">
      <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Hotel checkout</p>
          <h1 class="mt-1 text-2xl font-bold text-slate-950 sm:text-3xl">Guest information</h1>
          <p class="mt-2 text-sm text-slate-500">Your room has been checked with the provider. Add guest details to continue.</p>
        </div>
        <Button class="border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700" @click="router.push({ name: 'HotelSearch' })">Back to search</Button>
      </div>

      <div v-if="isLoading" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="h-[520px] animate-pulse border border-slate-200 bg-white" />
        <div class="h-72 animate-pulse border border-slate-200 bg-white" />
      </div>

      <div v-else-if="errorMessage" class="border border-red-200 bg-red-50 p-5 text-sm text-red-800">{{ errorMessage }}</div>

      <div v-else-if="prebook" class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-5">
          <div class="flex items-center justify-between gap-3 border-l-2 px-3 py-2 text-xs" :class="isExpired ? 'border-red-500 bg-red-50 text-red-800' : 'border-amber-500 bg-amber-50 text-amber-900'">
            <div class="flex items-center gap-1.5 font-medium"><Clock3 class="h-4 w-4" /><span>{{ isExpired ? 'Reservation window expired' : 'Complete details before expiry' }}</span></div>
            <strong class="border border-current/20 bg-white/60 px-2 py-0.5 font-mono text-sm font-bold tabular-nums">{{ formatCountdown(remainingSeconds) }}</strong>
          </div>

          <form class="overflow-hidden border border-slate-200 bg-white shadow-sm" @submit.prevent="submitReservation">
            <div class="border-b border-slate-100 px-5 py-5 sm:px-6">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center bg-primary/10 text-primary"><Users class="h-5 w-5" /></div>
                <div><h2 class="font-bold text-slate-950">Guest details</h2><p class="mt-0.5 text-sm text-slate-500">Enter names exactly as they appear on government-issued identification.</p></div>
              </div>
            </div>

            <div class="space-y-7 px-5 py-6 sm:px-6">
              <section v-for="(room, roomIndex) in customerDetails" :key="roomIndex" class="border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                <div class="mb-4 flex items-center justify-between gap-3"><h3 class="font-semibold text-slate-900">Room {{ roomIndex + 1 }}</h3><span class="text-xs font-medium text-slate-500">{{ room.customer_names.length }} guest{{ room.customer_names.length === 1 ? '' : 's' }}</span></div>
                <div v-for="(guest, guestIndex) in room.customer_names" :key="guestIndex" class="mt-4 first:mt-0">
                  <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Guest {{ guestIndex + 1 }} · {{ guest.type }}</p>
                  <div class="grid gap-3 sm:grid-cols-[104px_minmax(0,1fr)_minmax(0,1fr)]">
                    <label class="text-xs font-medium text-slate-600">Title<select v-model="guest.title" class="mt-1 h-11 w-full border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none focus:border-primary focus:ring-2 focus:ring-primary/15"><option value="Mr">Mr</option><option value="Mrs">Mrs</option><option value="Ms">Ms</option></select></label>
                    <label class="text-xs font-medium text-slate-600">First name<input v-model.trim="guest.first_name" required type="text" maxlength="100" placeholder="First name" class="mt-1 h-11 w-full border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/15"></label>
                    <label class="text-xs font-medium text-slate-600">Last name<input v-model.trim="guest.last_name" required type="text" maxlength="100" placeholder="Last name" class="mt-1 h-11 w-full border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/15"></label>
                  </div>
                </div>
              </section>

              <section class="border border-slate-200 bg-slate-50/60 p-4 sm:p-5">
                <h3 class="font-semibold text-slate-900">Lead guest contact</h3><p class="mt-1 text-sm text-slate-500">We will use these details for the hotel reservation.</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                  <label class="text-sm font-medium text-slate-700">Email address<input v-model.trim="email" required type="email" maxlength="255" placeholder="name@example.com" class="mt-1.5 h-11 w-full border border-slate-300 bg-white px-3 text-sm outline-none placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/15"></label>
                  <label class="text-sm font-medium text-slate-700">Phone number<input v-model.trim="phoneNumber" required type="tel" maxlength="64" placeholder="+92 ..." class="mt-1.5 h-11 w-full border border-slate-300 bg-white px-3 text-sm outline-none placeholder:text-slate-400 focus:border-primary focus:ring-2 focus:ring-primary/15"></label>
                </div>
              </section>

              <div class="flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-slate-500">Book now saves your details as a pending reservation. TBO booking happens only after payment.</p>
                <Button type="submit" class="h-11 bg-primary px-6 text-sm font-bold text-white hover:bg-primary/90" :disabled="isExpired" :is-loading="isSavingReservation">Book now</Button>
              </div>
            </div>
          </form>
        </div>

        <aside class="space-y-5 lg:sticky lg:top-5">
          <article class="overflow-hidden border border-slate-200 bg-white shadow-sm">
            <div class="bg-primary px-5 py-4 text-primary-foreground"><div class="flex items-center gap-2 text-sm font-semibold"><Hotel class="h-5 w-5" /> Your stay</div><h2 class="mt-2 text-lg font-bold">{{ prebook.hotel.name }}</h2><p class="mt-1 text-sm text-primary-foreground/80">{{ [prebook.hotel.city, prebook.hotel.country].filter(Boolean).join(', ') || 'Location details available at booking' }}</p></div>
            <div class="space-y-4 p-5">
              <div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Room</p><p class="mt-1 font-semibold text-slate-900">{{ roomName }}</p><p v-if="prebook.room.inclusion" class="mt-1 text-sm text-slate-500">{{ prebook.room.inclusion }}</p></div>
              <div class="grid grid-cols-2 gap-3 border-y border-slate-100 py-4 text-sm"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Check-in</p><p class="mt-1 font-medium text-slate-800">{{ prebook.stay.check_in }}</p></div><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Check-out</p><p class="mt-1 font-medium text-slate-800">{{ prebook.stay.check_out }}</p></div><div class="col-span-2 flex items-center gap-2 text-slate-600"><CalendarDays class="h-4 w-4 text-primary" />{{ stayNights }} night{{ stayNights === 1 ? '' : 's' }} · {{ prebook.room.meal_type || 'Meal details unavailable' }}</div></div>
              <div class="flex items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Total for stay</p><p class="mt-1 text-2xl font-bold text-slate-950">{{ formatMoney(finalFare || { amount: prebook.room.total_fare, currency: prebook.room.currency }) }}</p></div><span class="px-2.5 py-1 text-xs font-semibold" :class="prebook.room.is_refundable ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ prebook.room.is_refundable ? 'Refundable' : 'Non-refundable' }}</span></div>
            </div>
          </article>

          <article class="border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center gap-2"><ShieldCheck class="h-5 w-5 text-primary" /><h2 class="font-semibold text-slate-900">Cancellation policy</h2></div><ul v-if="prebook.room.cancel_policies?.length" class="mt-3 space-y-2 text-sm leading-5 text-slate-600"><li v-for="(policy, index) in prebook.room.cancel_policies" :key="index">{{ formatPolicy(policy) }}</li></ul><p v-else class="mt-3 text-sm text-slate-500">No cancellation policy was returned by the provider.</p></article>
        </aside>
      </div>
    </section>
  </main>
</template>
