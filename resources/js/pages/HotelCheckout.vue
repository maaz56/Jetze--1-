<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
hotelStore.resetCheckout();
const isLoading = ref(true);
const errorMessage = ref("");
const prebook = computed(() => hotelStore.getPrebook);
const booking = computed(() => hotelStore.getBooking);
const isBooking = computed(() => hotelStore.getIsBooking);
const email = ref("");
const phoneNumber = ref("");
const customerDetails = ref([]);
const finalFare = computed(() => prebook.value?.price_quote?.selling_money || null);

const formatMoney = (amount) => {
  if (amount === null || amount === undefined || amount === "") return "Unavailable";

  const money = typeof amount === "object" ? amount : {
    amount,
    currency: prebook.value?.room?.currency,
  };

  return `${money.currency || ""} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const formatPolicy = (policy) => {
  const charge = policy?.CancellationCharge;
  const type = policy?.ChargeType ? ` (${policy.ChargeType})` : "";
  return `From ${policy?.FromDate || "the stated date"}: ${charge ?? "—"}${type}`;
};

const makeCustomerDetails = (paxRooms) => (paxRooms || []).map((room) => ({
  customer_names: [
    ...Array.from({ length: Number(room.Adults || 0) }, () => ({
      title: "Mr",
      first_name: "",
      last_name: "",
      type: "Adult",
    })),
    ...Array.from({ length: Number(room.Children || 0) }, () => ({
      title: "Mr",
      first_name: "",
      last_name: "",
      type: "Child",
    })),
  ],
}));

const loadPrebook = async () => {
  const prebookId = route.query.prebook_id;

  if (!prebookId || typeof prebookId !== "string") {
    errorMessage.value = "No confirmed hotel room was supplied. Please return to hotel search.";
    isLoading.value = false;
    return;
  }

  hotelStore.resetCheckout();
  errorMessage.value = "";
  isLoading.value = true;

  try {
    await hotelStore.fetchPrebook(prebookId);
    customerDetails.value = makeCustomerDetails(prebook.value?.stay?.pax_rooms);
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load the confirmed room.";
  } finally {
    isLoading.value = false;
  }
};

const submitBooking = async () => {
  if (!prebook.value) return;

  errorMessage.value = "";

  try {
    await hotelStore.bookHotel({
      prebook_id: prebook.value.prebook_id,
      email: email.value,
      phone_number: phoneNumber.value,
      customer_details: customerDetails.value,
    });
  } catch (error) {
    errorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Unable to complete this hotel booking.";
  }
};

onMounted(loadPrebook);
</script>

<template>
  <section class="mx-auto max-w-4xl px-4 py-8 sm:py-12">
    <div class="mb-6 flex items-center justify-between gap-4">
      <div>
        <p class="text-xs font-bold uppercase text-primary">Hotel checkout</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-950">Review your confirmed room</h1>
      </div>
      <Button class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="router.push({ name: 'HotelSearch' })">
        Back to search
      </Button>
    </div>

    <div v-if="isLoading" class="rounded-lg border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm">
      Confirmed room details are loading...
    </div>

    <div v-else-if="errorMessage" class="rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-800">
      {{ errorMessage }}
    </div>

    <div v-else-if="booking" class="rounded-lg border border-emerald-200 bg-emerald-50 p-6">
      <p class="text-xs font-bold uppercase text-emerald-700">Booking confirmed</p>
      <h2 class="mt-1 text-2xl font-bold text-emerald-950">Your hotel booking is confirmed.</h2>
      <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="text-emerald-700">Confirmation number</dt><dd class="font-semibold text-emerald-950">{{ booking.confirmation_number || 'Pending provider confirmation' }}</dd></div>
        <div><dt class="text-emerald-700">Booking reference</dt><dd class="font-semibold text-emerald-950">{{ booking.booking_reference_id }}</dd></div>
        <div><dt class="text-emerald-700">Total fare</dt><dd class="font-semibold text-emerald-950">{{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</dd></div>
        <div><dt class="text-emerald-700">Hotel confirmation</dt><dd class="font-semibold text-emerald-950">{{ booking.hotel_confirmation_number || 'Will be updated by provider' }}</dd></div>
      </dl>
      <Button class="mt-5 rounded bg-primary px-5 py-2 text-sm font-bold text-white hover:bg-primary/90" @click="router.push({ name: 'HotelBookingDetails', query: { booking_id: booking.booking_id } })">
        View booking voucher
      </Button>
    </div>

    <div v-else-if="prebook" class="space-y-5">
      <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
        Room availability and the latest price have been confirmed by TBO. This confirmation expires at {{ new Date(prebook.expires_at).toLocaleString() }}.
      </div>

      <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase text-primary">{{ prebook.hotel.hotel_code }}</p>
        <h2 class="mt-1 text-xl font-bold text-gray-950">{{ prebook.hotel.name }}</h2>
        <p v-if="prebook.hotel.city || prebook.hotel.country" class="mt-1 text-sm text-gray-500">
          {{ [prebook.hotel.city, prebook.hotel.country].filter(Boolean).join(', ') }}
        </p>
        <p v-if="prebook.hotel.address" class="mt-1 text-sm text-gray-500">{{ prebook.hotel.address }}</p>

        <div class="mt-5 border-t border-gray-100 pt-5">
          <h3 class="font-semibold text-gray-950">{{ Array.isArray(prebook.room.name) ? prebook.room.name.join(', ') : prebook.room.name || 'Selected room' }}</h3>
          <p v-if="prebook.room.inclusion" class="mt-1 text-sm text-gray-600">{{ prebook.room.inclusion }}</p>
          <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div><span class="block text-xs font-semibold uppercase text-gray-500">Meal</span>{{ prebook.room.meal_type || 'Not specified' }}</div>
            <div><span class="block text-xs font-semibold uppercase text-gray-500">Refundable</span>{{ prebook.room.is_refundable ? 'Yes' : 'No' }}</div>
            <div><span class="block text-xs font-semibold uppercase text-gray-500">Final fare</span><strong class="text-base text-gray-950">{{ formatMoney(finalFare || prebook.room.total_fare) }}</strong></div>
          </div>
        </div>
      </article>

      <article class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-gray-950">Cancellation policy</h2>
        <ul v-if="prebook.room.cancel_policies?.length" class="mt-3 space-y-2 text-sm text-gray-600">
          <li v-for="(policy, index) in prebook.room.cancel_policies" :key="index">{{ formatPolicy(policy) }}</li>
        </ul>
        <p v-else class="mt-2 text-sm text-gray-500">No cancellation policy was returned by the provider.</p>
      </article>

      <article v-if="prebook.room.supplements?.flat?.(2)?.length" class="rounded-lg border border-amber-200 bg-amber-50 p-5">
        <h2 class="font-semibold text-amber-900">Payable at property</h2>
        <p class="mt-2 text-sm text-amber-800">Any mandatory supplements shown by TBO must be paid directly at the hotel.</p>
      </article>

      <form class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" @submit.prevent="submitBooking">
        <h2 class="text-lg font-semibold text-gray-950">Guest details</h2>
        <p class="mt-1 text-sm text-gray-500">Names must match each room's adults and children from the original search.</p>

        <div v-for="(room, roomIndex) in customerDetails" :key="roomIndex" class="mt-5 border-t border-gray-100 pt-5 first:border-t-0 first:pt-0">
          <h3 class="font-semibold text-gray-900">Room {{ roomIndex + 1 }}</h3>
          <div v-for="(guest, guestIndex) in room.customer_names" :key="guestIndex" class="mt-3 grid gap-3 rounded border border-gray-100 bg-gray-50 p-3 sm:grid-cols-[110px_1fr_1fr_110px]">
            <select v-model="guest.title" class="h-10 border border-gray-300 bg-white px-3 text-sm">
              <option value="Mr">Mr</option>
              <option value="Mrs">Mrs</option>
              <option value="Ms">Ms</option>
            </select>
            <input v-model.trim="guest.first_name" required type="text" maxlength="100" placeholder="First name" class="h-10 border border-gray-300 bg-white px-3 text-sm" />
            <input v-model.trim="guest.last_name" required type="text" maxlength="100" placeholder="Last name" class="h-10 border border-gray-300 bg-white px-3 text-sm" />
            <span class="flex h-10 items-center text-sm font-medium text-gray-600">{{ guest.type }}</span>
          </div>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-gray-700">
            Email
            <input v-model.trim="email" required type="email" maxlength="255" class="mt-1 h-10 w-full border border-gray-300 px-3 text-sm" />
          </label>
          <label class="text-sm font-medium text-gray-700">
            Phone number
            <input v-model.trim="phoneNumber" required type="tel" maxlength="64" class="mt-1 h-10 w-full border border-gray-300 px-3 text-sm" />
          </label>
        </div>

        <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-xs text-gray-500">Confirming creates a TBO booking and uses the agency's Limit balance.</p>
          <Button type="submit" class="h-11 rounded bg-primary px-5 text-sm font-bold text-white hover:bg-primary/90" :is-loading="isBooking">
            Confirm and book
          </Button>
        </div>
      </form>
    </div>
  </section>
</template>
