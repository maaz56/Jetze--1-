<script setup>
import Button from "@/components/ui/button/Button.vue";
import HotelStepIndicator from "@/components/hotel/HotelStepIndicator.vue";
import { useHotelStore } from "@/services/stores/hotel";
import {
  AlertCircle,
  ArrowLeft,
  CalendarDays,
  Check,
  Clock3,
  CreditCard,
  Hotel,
  Info,
  Lock,
  Mail,
  Phone,
  ShieldCheck,
  User,
  Users,
} from "lucide-vue-next";
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
const supplements = computed(() =>
  (prebook.value?.room?.supplements || [])
    .flat(2)
    .filter((supplement) => supplement && typeof supplement === "object"),
);
const roomName = computed(() => {
  const name = prebook.value?.room?.name;
  return Array.isArray(name)
    ? name.filter(Boolean).join(", ")
    : name || "Selected room";
});
const stayNights = computed(() => {
  const checkIn = prebook.value?.stay?.check_in;
  const checkOut = prebook.value?.stay?.check_out;
  return checkIn && checkOut
    ? Math.max(0, Math.round((new Date(checkOut) - new Date(checkIn)) / 86400000))
    : 1;
});

const formatMoney = (money) => {
  if (!money || money.amount === null || money.amount === undefined)
    return "Unavailable";
  return `${money.currency || ""} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const formatPolicy = (policy) => {
  const type = policy?.ChargeType ? ` (${policy.ChargeType})` : "";
  return `From ${policy?.FromDate || "the stated date"}: ${policy?.CancellationCharge ?? "—"}${type}`;
};

const formatSupplement = (supplement) => {
  const amount =
    supplement?.Price !== undefined && supplement?.Price !== null
      ? `${supplement.Currency || ""} ${Number(supplement.Price).toLocaleString()}`.trim()
      : "Amount as advised by the hotel";

  return `${supplement.Description || "Supplement"}: ${amount}${
    supplement.Type === "AtProperty" ? " (payable at property)" : ""
  }`;
};

const formatCountdown = (seconds) => {
  const safeSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
  return `${String(Math.floor(safeSeconds / 60)).padStart(2, "0")}:${String(
    safeSeconds % 60,
  ).padStart(2, "0")}`;
};

const makeCustomerDetails = (paxRooms) =>
  (paxRooms || []).map((room) => ({
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
    errorMessage.value =
      "No confirmed hotel room was supplied. Please return to hotel search.";
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
    errorMessage.value =
      error.response?.data?.message || "Unable to load the confirmed room.";
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
    router.push({
      name: "HotelBookingDetails",
      query: { booking_id: response.data?.booking_id },
    });
  } catch (error) {
    errorMessage.value =
      hotelStore.getErrorMessage ||
      error.response?.data?.message ||
      "Unable to save this reservation.";
  }
};

onMounted(loadPrebook);
onBeforeUnmount(() => window.clearInterval(countdownTimer));
</script>

<template>
  <HotelStepIndicator :current-step="2" />

  <main class="min-h-screen bg-slate-100/75 py-6 sm:py-8">
    <div class="mx-auto w-full max-w-booking px-4 sm:px-6">
      <!-- Top Bar -->
      <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Guest Information
          </h1>
          <p class="mt-1 text-xs text-muted-foreground">
            Please enter guest details exactly as shown on government IDs or passports.
          </p>
        </div>
        <Button
          variant="outline"
          class="h-9 gap-1.5 text-xs font-semibold"
          @click="router.push({ name: 'HotelSearch' })"
        >
          <ArrowLeft class="h-3.5 w-3.5" />
          Back to search
        </Button>
      </div>

      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-4">
          <div class="h-14 rounded bg-muted animate-pulse" />
          <div class="h-64 rounded bg-muted animate-pulse" />
          <div class="h-44 rounded bg-muted animate-pulse" />
        </div>
        <div class="h-96 rounded bg-muted animate-pulse" />
      </div>

      <!-- Error Message -->
      <div
        v-else-if="errorMessage"
        class="rounded border border-destructive/30 bg-destructive/5 p-5 text-sm text-destructive"
      >
        <div class="flex items-center gap-2 font-bold">
          <AlertCircle class="h-4 w-4" />
          <span>Notice</span>
        </div>
        <p class="mt-1">{{ errorMessage }}</p>
        <Button class="mt-4 h-9 text-xs" @click="router.push({ name: 'HotelSearch' })">
          Return to hotel search
        </Button>
      </div>

      <!-- Main Two-Column Layout -->
      <div v-else-if="prebook" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <!-- Left Column: Forms -->
        <div class="space-y-6">
          <!-- Countdown Warning Banner -->
          <div
            class="flex items-center justify-between gap-3 rounded border p-3.5 text-xs"
            :class="
              isExpired
                ? 'border-destructive/40 bg-destructive/10 text-destructive'
                : 'border-chart-4/40 bg-chart-4/10 text-foreground'
            "
          >
            <div class="flex items-center gap-2 font-medium">
              <Clock3 class="h-4 w-4 shrink-0 text-primary" />
              <span>
                {{
                  isExpired
                    ? "Reservation window expired. Please reselect your room."
                    : "Room held at live price. Complete details within:"
                }}
              </span>
            </div>
            <span class="rounded bg-background px-2.5 py-1 font-mono text-xs font-bold shadow-xs tabular-nums border border-border">
              {{ formatCountdown(remainingSeconds) }}
            </span>
          </div>

          <!-- Guest Details Form -->
          <form class="space-y-6" @submit.prevent="submitReservation">
            <!-- Rooms & Guests Input Cards -->
            <div
              v-for="(room, roomIndex) in customerDetails"
              :key="roomIndex"
              class="rounded border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm"
            >
              <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                <div class="flex items-center gap-2.5">
                  <span class="flex h-6 w-6 items-center justify-center rounded bg-primary text-xs font-black text-white shadow-xs">
                    {{ roomIndex + 1 }}
                  </span>
                  <h2 class="text-base font-extrabold text-slate-900">Room {{ roomIndex + 1 }} Guests</h2>
                </div>
                <span class="rounded bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                  {{ room.customer_names.length }} guest{{ room.customer_names.length === 1 ? '' : 's' }}
                </span>
              </div>

              <div class="mt-4 space-y-4">
                <div
                  v-for="(guest, guestIndex) in room.customer_names"
                  :key="guestIndex"
                  class="rounded border border-slate-200 bg-slate-50/90 p-4"
                >
                  <p class="mb-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
                    Guest {{ guestIndex + 1 }} · {{ guest.type }}
                  </p>

                  <div class="grid gap-3 sm:grid-cols-[100px_minmax(0,1fr)_minmax(0,1fr)]">
                    <!-- Title Selection -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1">
                        Title
                      </label>
                      <select
                        v-model="guest.title"
                        class="h-11 w-full rounded border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-900 outline-none transition focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs"
                      >
                        <option value="Mr">Mr</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Ms">Ms</option>
                      </select>
                    </div>

                    <!-- First Name -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1">
                        First Name <span class="text-destructive">*</span>
                      </label>
                      <input
                        v-model.trim="guest.first_name"
                        required
                        type="text"
                        minlength="2"
                        maxlength="25"
                        pattern="[A-Za-z]+"
                        title="Use 2 to 25 English letters only."
                        placeholder="e.g. John"
                        class="h-11 w-full rounded border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-muted-foreground/60 transition focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs"
                      />
                    </div>

                    <!-- Last Name -->
                    <div>
                      <label class="block text-xs font-bold text-slate-700 mb-1">
                        Last Name <span class="text-destructive">*</span>
                      </label>
                      <input
                        v-model.trim="guest.last_name"
                        required
                        type="text"
                        minlength="2"
                        maxlength="25"
                        pattern="[A-Za-z]+"
                        title="Use 2 to 25 English letters only."
                        placeholder="e.g. Smith"
                        class="h-11 w-full rounded border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none placeholder:text-muted-foreground/60 transition focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Lead Guest Contact Section -->
            <div class="rounded border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
              <div class="border-b border-slate-100 pb-3.5">
                <h2 class="text-base font-extrabold text-slate-900">Lead Guest Contact Details</h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  Your booking confirmation voucher and updates will be sent to this email.
                </p>
              </div>

              <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <!-- Email -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">
                    Email Address <span class="text-destructive">*</span>
                  </label>
                  <div class="relative">
                    <Mail class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                    <input
                      v-model.trim="email"
                      required
                      type="email"
                      maxlength="255"
                      placeholder="name@example.com"
                      class="h-11 w-full rounded border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-900 outline-none placeholder:text-muted-foreground/60 transition focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs"
                    />
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">We'll send your booking confirmation voucher here.</p>
                </div>

                <!-- Phone -->
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">
                    Phone Number <span class="text-destructive">*</span>
                  </label>
                  <div class="relative">
                    <Phone class="pointer-events-none absolute left-3 top-3.5 h-4 w-4 text-muted-foreground" />
                    <input
                      v-model.trim="phoneNumber"
                      required
                      type="tel"
                      maxlength="64"
                      placeholder="+92 300 1234567"
                      class="h-11 w-full rounded border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-900 outline-none placeholder:text-muted-foreground/60 transition focus:border-primary focus:ring-1 focus:ring-primary shadow-2xs"
                    />
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">For critical updates and property check-in coordination.</p>
                </div>
              </div>
            </div>

            <!-- Trust Signals & Guarantee -->
            <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
              <div class="flex items-start gap-3">
                <Lock class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                <div class="text-xs">
                  <p class="font-extrabold text-slate-900">Secure 256-Bit SSL Encrypted Checkout</p>
                  <p class="mt-0.5 text-slate-600">
                    Your personal information is encrypted and transmitted securely. We adhere to PCI-DSS payment compliance standards.
                  </p>
                </div>
              </div>

              <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[11px] text-muted-foreground">
                <span class="font-bold text-slate-800">Accepted payment options:</span>
                <span class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 font-semibold text-slate-700">Visa</span>
                <span class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 font-semibold text-slate-700">Mastercard</span>
                <span class="rounded border border-slate-200 bg-slate-50 px-2 py-0.5 font-semibold text-slate-700">Jetze Wallet</span>
              </div>
            </div>

            <!-- Submit CTA -->
            <div class="flex flex-col gap-3 rounded border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs text-muted-foreground">
                By continuing, you agree to the property cancellation policy and terms of service.
              </p>
              <Button
                type="submit"
                class="h-12 px-8 text-sm font-bold shrink-0 shadow-md shadow-primary/20"
                :disabled="isExpired || isSavingReservation"
                :is-loading="isSavingReservation"
              >
                {{ isSavingReservation ? "Saving reservation..." : "Proceed to payment" }}
              </Button>
            </div>
          </form>
        </div>

        <!-- Right Column: Sticky Booking Summary -->
        <aside class="space-y-4 lg:sticky lg:top-16">
          <article class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
            <!-- Property Header -->
            <div class="bg-primary p-5 text-white">
              <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-white/80">
                <Hotel class="h-4 w-4" />
                <span>Your Stay</span>
              </div>
              <h2 class="mt-1 text-base font-black text-white leading-snug">
                {{ prebook.hotel.name }}
              </h2>
              <p class="mt-0.5 text-xs text-white/80">
                {{ [prebook.hotel.city, prebook.hotel.country].filter(Boolean).join(", ") }}
              </p>
            </div>

            <!-- Stay Specs -->
            <div class="space-y-4 p-4 text-xs">
              <!-- Dates & Nights -->
              <div class="grid grid-cols-2 gap-2 border-b border-border pb-3">
                <div>
                  <span class="text-muted-foreground">Check-in</span>
                  <p class="mt-0.5 font-bold text-foreground">{{ prebook.stay.check_in }}</p>
                </div>
                <div>
                  <span class="text-muted-foreground">Check-out</span>
                  <p class="mt-0.5 font-bold text-foreground">{{ prebook.stay.check_out }}</p>
                </div>
                <div class="col-span-2 pt-1 flex items-center gap-1.5 text-muted-foreground">
                  <CalendarDays class="h-3.5 w-3.5 text-primary" />
                  <span>{{ stayNights }} night{{ stayNights === 1 ? '' : 's' }} stay</span>
                </div>
              </div>

              <!-- Room Details -->
              <div class="border-b border-border pb-3">
                <span class="text-muted-foreground">Reserved room</span>
                <p class="mt-0.5 font-bold text-foreground">{{ roomName }}</p>
                <p v-if="prebook.room.meal_type" class="mt-0.5 text-muted-foreground">
                  {{ prebook.room.meal_type }}
                </p>
                <div class="mt-2 flex flex-wrap gap-1.5">
                  <span
                    class="rounded-sm px-2 py-0.5 text-[11px] font-semibold"
                    :class="
                      prebook.room.is_refundable
                        ? 'bg-chart-2/10 text-chart-2 border border-chart-2/30'
                        : 'bg-muted text-muted-foreground border border-border'
                    "
                  >
                    {{ prebook.room.is_refundable ? "Free cancellation available" : "Non-refundable" }}
                  </span>
                  <span
                    v-if="prebook.room.inclusion"
                    class="rounded-sm border border-border bg-muted/60 px-2 py-0.5 text-[11px] text-foreground"
                  >
                    {{ prebook.room.inclusion }}
                  </span>
                </div>
              </div>

              <!-- Price Breakdown -->
              <div class="space-y-2 border-b border-border pb-3">
                <div class="flex justify-between text-muted-foreground">
                  <span>Room rate ({{ stayNights }} night{{ stayNights === 1 ? '' : 's' }})</span>
                  <span class="font-medium text-foreground">
                    {{ formatMoney(finalFare || { amount: prebook.room.total_fare, currency: prebook.room.currency }) }}
                  </span>
                </div>
                <div class="flex items-baseline justify-between pt-1">
                  <span class="text-sm font-bold text-foreground">Total fare to pay</span>
                  <span class="text-xl font-bold text-foreground">
                    {{ formatMoney(finalFare || { amount: prebook.room.total_fare, currency: prebook.room.currency }) }}
                  </span>
                </div>
                <p class="text-[11px] text-muted-foreground">
                  Includes all mandatory taxes and carrier charges
                </p>
              </div>

              <!-- Cancellation policy note -->
              <div>
                <div class="flex items-center gap-1.5 font-bold text-foreground">
                  <ShieldCheck class="h-4 w-4 text-chart-2" />
                  <span>Cancellation terms</span>
                </div>
                <ul
                  v-if="prebook.room.cancel_policies?.length"
                  class="mt-2 space-y-1.5 text-muted-foreground"
                >
                  <li
                    v-for="(policy, index) in prebook.room.cancel_policies"
                    :key="index"
                    class="leading-relaxed"
                  >
                    {{ formatPolicy(policy) }}
                  </li>
                </ul>
                <p v-else class="mt-2 text-muted-foreground">
                  Refer to voucher conditions for cancellation charges.
                </p>
              </div>

              <!-- Mandatory or Optional Supplements -->
              <div v-if="supplements.length" class="rounded border border-chart-4/30 bg-chart-4/10 p-3">
                <p class="font-bold text-foreground">Property charges / Supplements</p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">Payable directly at the hotel:</p>
                <ul class="mt-1.5 space-y-1 text-muted-foreground">
                  <li v-for="(supplement, index) in supplements" :key="index">
                    {{ formatSupplement(supplement) }}
                  </li>
                </ul>
              </div>
            </div>
          </article>
        </aside>
      </div>
    </div>
  </main>
</template>
