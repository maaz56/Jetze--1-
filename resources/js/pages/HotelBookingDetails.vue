<script setup>
import Button from "@/components/ui/button/Button.vue";
import HotelStepIndicator from "@/components/hotel/HotelStepIndicator.vue";
import { useHotelStore } from "@/services/stores/hotel";
import {
  AlertCircle,
  ArrowLeft,
  CalendarDays,
  Check,
  CheckCircle2,
  Copy,
  CreditCard,
  Home,
  Hotel,
  MapPin,
  Printer,
  ShieldAlert,
  ShieldCheck,
  User,
  Users,
} from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();
const errorMessage = ref("");
const showCancellationConfirmation = ref(false);
const isCopied = ref(false);

const booking = computed(() => hotelStore.getBooking);
const isLoading = computed(() => hotelStore.getIsLoadingBookingDetails);
const isCancelling = computed(() => hotelStore.getIsCancelling);
const isPaymentPending = computed(
  () => booking.value?.status === "payment_pending",
);
const guestsByRoom = computed(() =>
  (booking.value?.guests || []).reduce((rooms, guest) => {
    const roomIndex = Number(guest.room_index || 0);
    rooms[roomIndex] = rooms[roomIndex] || [];
    rooms[roomIndex].push(guest);
    return rooms;
  }, {}),
);

const formatMoney = (money) => {
  if (
    money?.amount === null ||
    money?.amount === undefined ||
    !money?.currency
  ) {
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
    errorMessage.value =
      error.response?.data?.message || "Unable to load the hotel booking.";
  }
};

const cancelBooking = async () => {
  if (!booking.value?.booking_id) return;

  errorMessage.value = "";

  try {
    await hotelStore.cancelHotelBooking(booking.value.booking_id);
    showCancellationConfirmation.value = false;
  } catch (error) {
    errorMessage.value =
      hotelStore.getErrorMessage ||
      error.response?.data?.message ||
      "Unable to cancel this hotel booking.";
  }
};

const copyReference = () => {
  if (!booking.value?.booking_reference_id) return;
  navigator.clipboard.writeText(booking.value.booking_reference_id);
  isCopied.value = true;
  setTimeout(() => {
    isCopied.value = false;
  }, 2000);
};

const printBooking = () => window.print();

const payNow = () => {
  if (!booking.value?.booking_id) return;
  router.push({
    name: "CustomerPaymentView",
    query: {
      booking_type: "hotel",
      hotel_booking_id: booking.value.booking_id,
    },
  });
};

onMounted(loadBooking);
</script>

<template>
  <div class="min-h-screen bg-slate-100/75 text-foreground">
    <!-- Step Indicator Banner -->
    <div class="border-b border-slate-200/90 bg-white shadow-2xs print:hidden">
      <HotelStepIndicator :current-step="4" />
    </div>

    <main class="py-6 sm:py-8">
      <div class="mx-auto w-full max-w-booking px-4 sm:px-6">
        <!-- Loading Skeleton -->
        <div v-if="isLoading" class="space-y-4">
          <div class="h-24 rounded border border-slate-200/90 bg-white p-6 shadow-sm animate-pulse" />
          <div class="h-64 rounded border border-slate-200/90 bg-white p-6 shadow-sm animate-pulse" />
        </div>

        <!-- Error State -->
        <div
          v-else-if="errorMessage"
          class="rounded border border-destructive/30 bg-white p-6 shadow-sm text-sm text-destructive"
        >
          <div class="flex items-center gap-2 font-bold text-base">
            <AlertCircle class="h-5 w-5" />
            <span>Notice</span>
          </div>
          <p class="mt-2 text-slate-600">{{ errorMessage }}</p>
          <Button class="mt-4 h-10 px-5 text-xs font-bold rounded" @click="router.push({ name: 'HotelSearch' })">
            Return to search
          </Button>
        </div>

        <!-- Booking Content -->
        <div v-else-if="booking" class="space-y-6">
          <!-- Top Action Bar (Print / Back to home / Pay now) -->
          <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
            <div>
              <p class="text-xs font-bold uppercase tracking-wider text-primary">
                {{ isPaymentPending ? "Reservation Summary" : "Official Hotel Voucher" }}
              </p>
              <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                {{ isPaymentPending ? "Payment Pending" : "Booking Confirmed" }}
              </h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Button
                variant="outline"
                class="h-10 px-4 gap-2 text-xs font-semibold rounded border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900"
                @click="printBooking"
              >
                <Printer class="h-4 w-4" />
                Print Voucher
              </Button>

              <Button
                v-if="isPaymentPending"
                class="h-10 px-6 text-xs font-bold rounded bg-primary text-white shadow-xs hover:bg-primary/95"
                @click="payNow"
              >
                Pay Now
              </Button>

              <Button
                variant="outline"
                class="h-10 px-4 gap-2 text-xs font-semibold rounded border-slate-200 bg-white text-slate-700 shadow-2xs hover:bg-slate-50 hover:text-slate-900"
                @click="router.push({ name: 'Home' })"
              >
                <Home class="h-4 w-4" />
                Back to Home
              </Button>

              <Button
                class="h-10 px-5 text-xs font-bold rounded bg-primary text-white shadow-xs hover:bg-primary/95"
                @click="router.push({ name: 'HotelSearch' })"
              >
                Search Hotels
              </Button>
            </div>
          </div>

          <!-- Status Confirmation Banner -->
          <div
            class="rounded border p-5 sm:p-6 shadow-sm transition"
            :class="
              isPaymentPending
                ? 'border-amber-300/80 bg-amber-50/90 text-amber-950'
                : booking.status === 'cancelled'
                ? 'border-rose-300/80 bg-rose-50/90 text-rose-950'
                : 'border-emerald-300/80 bg-emerald-50/90 text-emerald-950'
            "
          >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex items-start gap-3.5">
                <span
                  class="flex h-11 w-11 shrink-0 items-center justify-center rounded text-white shadow-xs"
                  :class="
                    isPaymentPending
                      ? 'bg-amber-600 text-white'
                      : booking.status === 'cancelled'
                      ? 'bg-rose-600 text-white'
                      : 'bg-emerald-600 text-white'
                  "
                >
                  <AlertCircle v-if="isPaymentPending" class="h-6 w-6" />
                  <ShieldAlert v-else-if="booking.status === 'cancelled'" class="h-6 w-6" />
                  <CheckCircle2 v-else class="h-6 w-6" />
                </span>

                <div>
                  <h2 class="text-base sm:text-lg font-bold">
                    {{
                      isPaymentPending
                        ? "Your room reservation is saved and waiting for payment"
                        : booking.status === "cancelled"
                        ? "This hotel booking has been cancelled"
                        : "Your hotel booking is confirmed & guaranteed!"
                    }}
                  </h2>
                  <p class="mt-1 text-xs opacity-85 leading-relaxed max-w-2xl">
                    {{
                      isPaymentPending
                        ? "Complete payment before the hold period expires to secure your confirmed voucher."
                        : booking.status === "cancelled"
                        ? `Cancelled at ${booking.cancelled_at ? new Date(booking.cancelled_at).toLocaleString() : 'requested time'}.`
                        : "A confirmation email with your official hotel voucher has been generated and your room is reserved."
                    }}
                  </p>
                </div>
              </div>

              <!-- Reference Number Badge with Copy -->
              <div class="flex items-center gap-3 self-start rounded border border-slate-200/90 bg-white p-3 sm:self-center shadow-xs">
                <div class="text-left">
                  <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-500">Booking Reference</span>
                  <span class="font-mono text-sm sm:text-base font-black text-slate-900">{{ booking.booking_reference_id }}</span>
                </div>
                <button
                  type="button"
                  class="rounded p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900 transition"
                  title="Copy reference to clipboard"
                  @click="copyReference"
                >
                  <Check v-if="isCopied" class="h-4 w-4 text-emerald-600" />
                  <Copy v-else class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Detailed Reservation Cards -->
          <div class="grid gap-6 lg:grid-cols-2">
            <!-- Stay Information -->
            <article class="overflow-hidden rounded border border-slate-200/90 bg-white shadow-sm">
              <header class="border-b border-slate-100 bg-slate-50/80 p-5">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                  <Hotel class="h-4 w-4" />
                  <span>Property & Dates</span>
                </div>
                <h3 class="mt-2 text-lg font-black text-slate-900 leading-snug">
                  {{ booking.hotel?.name || "Hotel Reservation" }}
                </h3>
                <p
                  v-if="booking.hotel?.city || booking.hotel?.country"
                  class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"
                >
                  <MapPin class="h-3.5 w-3.5 text-primary shrink-0" />
                  <span>{{ [booking.hotel.city, booking.hotel.country].filter(Boolean).join(", ") }}</span>
                </p>
              </header>

              <div class="p-5 space-y-4 text-xs">
                <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-4">
                  <div class="rounded border border-slate-200/80 bg-slate-50/90 p-3.5">
                    <span class="text-[11px] font-medium text-slate-500">Check-in</span>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ booking.check_in || "—" }}</p>
                  </div>
                  <div class="rounded border border-slate-200/80 bg-slate-50/90 p-3.5">
                    <span class="text-[11px] font-medium text-slate-500">Check-out</span>
                    <p class="mt-1 text-sm font-bold text-slate-900">{{ booking.check_out || "—" }}</p>
                  </div>
                </div>

                <!-- Confirmation IDs -->
                <div class="grid gap-4 sm:grid-cols-2 pt-1">
                  <div>
                    <span class="text-[11px] font-medium text-slate-500">TBO Confirmation ID</span>
                    <p class="mt-0.5 font-mono text-xs font-bold text-slate-900">
                      {{ booking.confirmation_number || "Pending" }}
                    </p>
                  </div>
                  <div>
                    <span class="text-[11px] font-medium text-slate-500">Hotel Provider ID</span>
                    <p class="mt-0.5 font-mono text-xs font-bold text-slate-900">
                      {{ booking.hotel_confirmation_number || "Pending provider update" }}
                    </p>
                  </div>
                  <div>
                    <span class="text-[11px] font-medium text-slate-500">Voucher Status</span>
                    <p class="mt-0.5 text-xs font-bold text-slate-900">
                      {{ booking.voucher_status || "Pending confirmation" }}
                    </p>
                  </div>
                  <div>
                    <span class="text-[11px] font-medium text-slate-500">Status Checked</span>
                    <p class="mt-0.5 text-xs text-slate-500">
                      {{ booking.booking_details_checked_at ? new Date(booking.booking_details_checked_at).toLocaleString() : "Live" }}
                    </p>
                  </div>
                </div>
              </div>
            </article>

            <!-- Guest Details & Payment Total -->
            <article class="overflow-hidden rounded border border-slate-200/90 bg-white shadow-sm">
              <header class="border-b border-slate-100 bg-slate-50/80 p-5">
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-primary">
                  <Users class="h-4 w-4" />
                  <span>Guests & Total Fare</span>
                </div>
                <div class="mt-2 flex items-baseline justify-between">
                  <span class="text-xs font-medium text-slate-500">Total Fare Paid / Due</span>
                  <span class="text-xl sm:text-2xl font-black text-slate-900">
                    {{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}
                  </span>
                </div>
              </header>

              <div class="p-5 space-y-4 text-xs">
                <div>
                  <h4 class="font-bold text-slate-900 mb-3 text-xs uppercase tracking-wider text-slate-500">Registered Guests</h4>
                  <div v-for="(guests, roomIndex) in guestsByRoom" :key="roomIndex" class="space-y-2.5 mb-4 last:mb-0">
                    <p class="text-[11px] font-bold text-primary">
                      Room {{ Number(roomIndex) + 1 }}
                    </p>
                    <div class="space-y-2">
                      <div
                        v-for="guest in guests"
                        :key="`${guest.room_index}-${guest.guest_index}`"
                        class="flex items-center justify-between rounded border border-slate-200/80 bg-slate-50/80 p-3"
                      >
                        <div class="flex items-center gap-2.5 font-bold text-slate-900">
                          <User class="h-4 w-4 text-slate-400" />
                          <span>{{ guest.title }} {{ guest.first_name }} {{ guest.last_name }}</span>
                        </div>
                        <span class="rounded bg-white px-2 py-0.5 text-[10px] font-bold uppercase text-slate-600 border border-slate-200/80 shadow-2xs">
                          {{ guest.type }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </article>
          </div>

          <!-- Cancellation Policy & Actions -->
          <article class="rounded border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 pb-3.5">
              <ShieldCheck class="h-4 w-4 text-emerald-600" />
              <h3 class="text-sm font-bold text-slate-900">Cancellation Terms & Status</h3>
            </div>

            <div class="mt-4 text-xs text-slate-600 space-y-3">
              <ul v-if="booking.cancellation_policies?.length" class="space-y-2">
                <li v-for="(policy, index) in booking.cancellation_policies" :key="index" class="leading-relaxed flex items-start gap-2">
                  <span class="h-1.5 w-1.5 rounded-full bg-slate-400 mt-1.5 shrink-0" />
                  <span>{{ formatPolicy(policy) }}</span>
                </li>
              </ul>
              <p v-else>
                Standard hotel cancellation policy applies. Refer to property check-in rules.
              </p>

              <!-- Cancellation Trigger -->
              <div
                v-if="booking.status === 'confirmed' && booking.confirmation_number"
                class="border-t border-slate-100 pt-4 print:hidden"
              >
                <div v-if="showCancellationConfirmation" class="rounded border border-rose-300/80 bg-rose-50/90 p-4 text-rose-950">
                  <p class="font-bold">Confirm Booking Cancellation?</p>
                  <p class="mt-1 text-xs text-rose-800">
                    The cancellation policy above will apply. This action cannot be reversed.
                  </p>
                  <div class="mt-4 flex gap-2">
                    <Button
                      class="h-9 bg-destructive text-destructive-foreground hover:bg-destructive/90 text-xs font-bold rounded"
                      :is-loading="isCancelling"
                      @click="cancelBooking"
                    >
                      Confirm Cancellation
                    </Button>
                    <Button
                      variant="outline"
                      class="h-9 text-xs font-semibold rounded border-slate-200 bg-white text-slate-700"
                      :disabled="isCancelling"
                      @click="showCancellationConfirmation = false"
                    >
                      Keep Booking
                    </Button>
                  </div>
                </div>

                <Button
                  v-else
                  variant="outline"
                  class="h-9 text-xs font-semibold text-rose-600 border-rose-200 bg-white hover:bg-rose-50 rounded"
                  @click="showCancellationConfirmation = true"
                >
                  Request Booking Cancellation
                </Button>
              </div>
            </div>
          </article>
        </div>
      </div>
    </main>
  </div>
</template>
