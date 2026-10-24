<script setup>
import Button from "@/components/ui/button/Button.vue";
import HotelStepIndicator from "@/components/hotel/HotelStepIndicator.vue";
import { FETCH_AGENT_LEDGER } from "@/services/store/actions.type";
import apiService from "@/services/store/apiService";
import { useAuthStore } from "@/services/stores/auth";
import { useHotelStore } from "@/services/stores/hotel";
import {
  AlertCircle,
  CalendarDays,
  Check,
  Clock3,
  CreditCard,
  Hotel,
  Lock,
  MapPin,
  ShieldCheck,
  Wallet,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useStore } from "vuex";

const route = useRoute();
const router = useRouter();
const store = useStore();
const authStore = useAuthStore();
const hotelStore = useHotelStore();
const errorMessage = ref("");
const isLoading = ref(true);
const paymentMethod = ref("wallet");
const isLowBalanceDialogOpen = ref(false);
const isStartingCardPayment = ref(false);
const remainingSeconds = ref(0);
let timer;

const booking = computed(() => hotelStore.getBooking);
const isProcessing = computed(() => hotelStore.getIsBooking);
const isExpired = computed(
  () => remainingSeconds.value <= 0 || booking.value?.status === "expired",
);
const total = computed(
  () =>
    booking.value?.price_snapshot?.selling_money || {
      amount: booking.value?.total_fare,
      currency: booking.value?.currency,
    },
);
const walletLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const walletBalanceAed = computed(() => Number(walletLedger.value?.balance));
const walletRequiredAed = computed(() =>
  Number(booking.value?.price_snapshot?.base_money?.amount),
);
const hasKnownWalletAmounts = computed(
  () =>
    booking.value?.price_snapshot?.base_money?.amount !== null &&
    booking.value?.price_snapshot?.base_money?.amount !== undefined &&
    walletLedger.value?.balance !== null &&
    walletLedger.value?.balance !== undefined &&
    Number.isFinite(walletBalanceAed.value) &&
    Number.isFinite(walletRequiredAed.value),
);
const hasSufficientWalletBalance = computed(
  () =>
    hasKnownWalletAmounts.value &&
    walletBalanceAed.value >= walletRequiredAed.value,
);

const formatMoney = (money) =>
  money?.amount === null || money?.amount === undefined
    ? "Unavailable"
    : `${money.currency || ""} ${Number(money.amount).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      })}`;

const timerLabel = computed(() => {
  const seconds = Math.max(0, Math.floor(Number(remainingSeconds.value) || 0));
  const minutesPart = String(Math.floor(seconds / 60)).padStart(2, "0");
  const secondsPart = String(seconds % 60).padStart(2, "0");

  return `${minutesPart}:${secondsPart}`;
});

const startTimer = (seconds) => {
  window.clearInterval(timer);
  remainingSeconds.value = Math.max(0, Math.floor(Number(seconds) || 0));
  timer = window.setInterval(() => {
    remainingSeconds.value = Math.max(0, remainingSeconds.value - 1);
    if (!remainingSeconds.value) window.clearInterval(timer);
  }, 1000);
};

const loadBooking = async () => {
  const bookingId = route.query.hotel_booking_id;
  if (!bookingId || typeof bookingId !== "string") {
    errorMessage.value = "No hotel reservation was supplied.";
    isLoading.value = false;
    return;
  }
  try {
    const response = await hotelStore.fetchHotelBooking(bookingId);
    startTimer(response.data?.remaining_seconds);
  } catch (error) {
    errorMessage.value =
      error.response?.data?.message || "Unable to load the hotel reservation.";
  } finally {
    isLoading.value = false;
  }
};

const fetchWalletBalance = async () => {
  if (!authStore.user) await authStore.fetchUser();
  if (authStore.user?.id) {
    await store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
      userId: authStore.user.id,
    });
  }
};

const completePayment = async () => {
  if (!booking.value?.booking_id || isExpired.value) return;
  errorMessage.value = "";

  if (paymentMethod.value === "card") {
    isStartingCardPayment.value = true;
    try {
      const response = await apiService.createHotelNomodCheckout(
        booking.value.booking_id,
      );
      const checkoutUrl = response?.data?.checkout_url;
      const parsedUrl = new URL(checkoutUrl);
      if (parsedUrl.protocol !== "https:")
        throw new Error("The payment gateway returned an invalid checkout URL.");
      window.location.assign(parsedUrl.toString());
    } catch (error) {
      errorMessage.value =
        error.response?.data?.message ||
        error.message ||
        "Unable to start secure hotel checkout.";
      isStartingCardPayment.value = false;
    }
    return;
  }

  try {
    await fetchWalletBalance();
    if (hasKnownWalletAmounts.value && !hasSufficientWalletBalance.value) {
      isLowBalanceDialogOpen.value = true;
      return;
    }

    await hotelStore.confirmHotelBooking(booking.value.booking_id, "wallet");
    router.replace({
      name: "HotelBookingDetails",
      query: { booking_id: booking.value.booking_id },
    });
  } catch (error) {
    if (error.response?.data?.code === "INSUFFICIENT_WALLET_BALANCE") {
      isLowBalanceDialogOpen.value = true;
      return;
    }
    errorMessage.value =
      hotelStore.getErrorMessage ||
      error.response?.data?.message ||
      "Unable to confirm the hotel booking.";
  }
};

const goToWalletDeposit = () => {
  isLowBalanceDialogOpen.value = false;
  router.push({ name: "CustomerProfile", params: { section: "deposits" } });
};

onMounted(async () => {
  await Promise.all([loadBooking(), fetchWalletBalance()]);
});
onBeforeUnmount(() => window.clearInterval(timer));
</script>

<template>
  <HotelStepIndicator :current-step="3" />

  <main class="min-h-screen bg-slate-100/75 py-6 sm:py-8">
    <div class="mx-auto w-full max-w-booking px-4 sm:px-6">
      <!-- Loading Skeleton -->
      <div
        v-if="isLoading"
        class="h-80 animate-pulse rounded border border-border bg-muted"
      />

      <!-- Error State -->
      <div
        v-else-if="errorMessage"
        class="rounded border border-destructive/30 bg-destructive/5 p-5 text-sm text-destructive"
      >
        <div class="flex items-center gap-2 font-bold">
          <AlertCircle class="h-4 w-4" />
          <span>Payment Error</span>
        </div>
        <p class="mt-1">{{ errorMessage }}</p>
      </div>

      <!-- Main Payment View -->
      <div
        v-else-if="booking"
        class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]"
      >
        <!-- Left: Payment Options -->
        <article class="space-y-6">
          <!-- Expiry Notice -->
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
                    ? "Reservation payment window expired."
                    : "Complete payment to lock in this reservation:"
                }}
              </span>
            </div>
            <span
              class="rounded bg-background px-2.5 py-1 font-mono text-xs font-bold shadow-xs tabular-nums border border-border"
            >
              {{ timerLabel }}
            </span>
          </div>

          <!-- Payment Card Container -->
          <div class="rounded border border-slate-200/90 bg-white shadow-sm overflow-hidden">
            <div class="border-b border-slate-100 p-5">
              <h1 class="text-xl font-black tracking-tight text-slate-900">
                Select Payment Method
              </h1>
              <p class="mt-1 text-xs text-muted-foreground">
                All transactions are encrypted and processed through verified payment channels.
              </p>
            </div>

            <!-- Method Selection Tabs -->
            <div class="grid grid-cols-2 border-b border-slate-200 p-2 gap-2 bg-slate-100/70">
              <button
                type="button"
                class="flex items-center justify-center gap-2 rounded border px-4 py-3 text-xs font-bold transition shadow-xs"
                :class="
                  paymentMethod === 'wallet'
                    ? 'border-slate-200 bg-white text-primary'
                    : 'border-transparent text-slate-600 hover:bg-white/50 hover:text-slate-900'
                "
                @click="paymentMethod = 'wallet'"
              >
                <Wallet class="h-4 w-4" />
                <span>Jetze Wallet</span>
              </button>

              <button
                type="button"
                class="flex items-center justify-center gap-2 rounded border px-4 py-3 text-xs font-bold transition shadow-xs"
                :class="
                  paymentMethod === 'card'
                    ? 'border-slate-200 bg-white text-primary'
                    : 'border-transparent text-slate-600 hover:bg-white/50 hover:text-slate-900'
                "
                @click="paymentMethod = 'card'"
              >
                <CreditCard class="h-4 w-4" />
                <span>Debit / Credit Card</span>
              </button>
            </div>

            <!-- Method Content Body -->
            <div class="p-6">
              <!-- Wallet View -->
              <div v-if="paymentMethod === 'wallet'" class="space-y-5">
                <div class="flex items-start justify-between rounded border border-slate-200 bg-slate-50/90 p-4">
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Available Wallet Balance</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">
                      AED {{ Number.isFinite(walletBalanceAed) ? walletBalanceAed.toLocaleString(undefined, { maximumFractionDigits: 2 }) : '0.00' }}
                    </p>
                  </div>
                  <span
                    class="rounded-sm px-2.5 py-1 text-xs font-bold"
                    :class="
                      hasSufficientWalletBalance
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-rose-50 text-rose-800 border border-rose-200'
                    "
                  >
                    {{ hasSufficientWalletBalance ? "Sufficient Balance" : "Insufficient Balance" }}
                  </span>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50/60 p-4 space-y-1">
                  <p class="text-xs text-muted-foreground font-medium">Total to deduct for this stay:</p>
                  <p class="text-2xl font-black text-slate-900">{{ formatMoney(total) }}</p>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                  Upon clicking confirm, the booking will be verified and submitted directly to the provider for instant voucher issuance.
                </p>
              </div>

              <!-- Card View -->
              <div v-else class="space-y-5">
                <div class="rounded border border-slate-200 bg-slate-50/90 p-4 space-y-3">
                  <div class="flex items-center justify-between">
                    <p class="text-xs font-extrabold text-slate-900">Debit / Credit Card (Nomod Gateway)</p>
                    <div class="flex gap-1.5 text-xs">
                      <span class="rounded border border-slate-200 bg-white px-2 py-0.5 font-bold text-slate-700">Visa</span>
                      <span class="rounded border border-slate-200 bg-white px-2 py-0.5 font-bold text-slate-700">Mastercard</span>
                    </div>
                  </div>
                  <p class="text-xs text-slate-600 leading-relaxed">
                    You will be redirected to Nomod's secure 3D-Secure hosted payment gateway to enter your card details.
                  </p>
                </div>

                <div class="rounded border border-slate-200 bg-slate-50/60 p-4 space-y-1">
                  <p class="text-xs text-muted-foreground font-medium">Total payable:</p>
                  <p class="text-2xl font-black text-slate-900">{{ formatMoney(total) }}</p>
                </div>
              </div>

              <!-- Trust & Security Signals -->
              <div class="mt-6 flex items-center gap-2 rounded border border-slate-200 bg-slate-50 p-3.5 text-xs text-slate-600">
                <Lock class="h-4 w-4 shrink-0 text-primary" />
                <span>256-bit SSL encrypted connection · PCI-DSS compliant checkout</span>
              </div>

              <!-- CTA Button -->
              <Button
                class="mt-6 h-12 w-full text-base font-bold shadow-md shadow-primary/20"
                :disabled="isExpired || booking.status !== 'payment_pending'"
                :is-loading="isProcessing || isStartingCardPayment"
                @click="completePayment"
              >
                {{
                  isExpired
                    ? "Reservation Expired"
                    : paymentMethod === "wallet"
                    ? "Pay with Wallet & Confirm Booking"
                    : "Proceed to Nomod Secure Checkout"
                }}
              </Button>
            </div>
          </div>
        </article>

        <!-- Right: Sticky Booking Summary -->
        <aside class="space-y-4 lg:sticky lg:top-24">
          <article class="overflow-hidden rounded border border-slate-200/90 bg-white shadow-sm">
            <div class="bg-primary p-5 text-white">
              <div class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-white/85">
                <Hotel class="h-4 w-4" />
                <span>Booking Summary</span>
              </div>
              <h2 class="mt-1.5 text-base sm:text-lg font-bold text-white leading-snug">
                {{ booking.hotel?.name || "Hotel Reservation" }}
              </h2>
              <p class="mt-1 flex items-center gap-1 text-xs text-white/80">
                <MapPin class="h-3.5 w-3.5 shrink-0" />
                <span>{{ [booking.hotel?.city, booking.hotel?.country].filter(Boolean).join(", ") || "Confirmed Location" }}</span>
              </p>
            </div>

            <div class="space-y-4 p-5 text-xs">
              <div class="grid grid-cols-2 gap-3 border-b border-slate-100 pb-3.5">
                <div>
                  <span class="text-[11px] font-medium text-slate-500">Check-in</span>
                  <p class="mt-0.5 text-xs font-bold text-slate-900">{{ booking.check_in || "—" }}</p>
                </div>
                <div>
                  <span class="text-[11px] font-medium text-slate-500">Check-out</span>
                  <p class="mt-0.5 text-xs font-bold text-slate-900">{{ booking.check_out || "—" }}</p>
                </div>
              </div>

              <div class="border-b border-slate-100 pb-3.5">
                <span class="text-[11px] font-medium text-slate-500">Booking Reference</span>
                <p class="mt-0.5 font-mono text-xs font-bold text-slate-900">
                  {{ booking.booking_reference_id }}
                </p>
              </div>

              <div class="flex items-baseline justify-between rounded bg-slate-50/90 p-3.5 border border-slate-200/80">
                <span class="text-xs font-bold text-slate-700">Total Due</span>
                <span class="text-xl font-black text-slate-900">{{ formatMoney(total) }}</span>
              </div>

              <div class="pt-2 text-[11px] text-slate-500 space-y-2">
                <p class="flex items-center gap-2">
                  <ShieldCheck class="h-4 w-4 text-emerald-600 shrink-0" />
                  <span>Instant confirmation upon payment</span>
                </p>
                <p class="flex items-center gap-2">
                  <Check class="h-4 w-4 text-primary shrink-0" />
                  <span>Official hotel voucher issued immediately</span>
                </p>
              </div>
            </div>
          </article>
        </aside>
      </div>
    </div>

    <!-- Low Balance Modal Dialog -->
    <div
      v-if="isLowBalanceDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-xs p-4"
      @click.self="isLowBalanceDialogOpen = false"
    >
      <div class="w-full max-w-md rounded border border-border bg-card p-6 shadow-lg">
        <h3 class="text-base font-bold text-foreground">Insufficient Wallet Balance</h3>
        <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
          Your wallet balance is insufficient to confirm this hotel booking. Please deposit funds or switch to debit/credit card payment.
        </p>
        <div class="mt-6 flex justify-end gap-2">
          <Button
            variant="outline"
            class="h-9 text-xs"
            @click="isLowBalanceDialogOpen = false"
          >
            Cancel
          </Button>
          <Button
            class="h-9 text-xs font-bold"
            @click="goToWalletDeposit"
          >
            Deposit Funds
          </Button>
        </div>
      </div>
    </div>
  </main>
</template>
