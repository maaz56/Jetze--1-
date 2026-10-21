<script setup>
import Button from "@/components/ui/button/Button.vue";
import { FETCH_AGENT_LEDGER } from "@/services/store/actions.type";
import apiService from "@/services/store/apiService";
import { useAuthStore } from "@/services/stores/auth";
import { useHotelStore } from "@/services/stores/hotel";
import { Clock3, CreditCard, Hotel, Wallet } from "lucide-vue-next";
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
        : `${money.currency || ""} ${Number(money.amount).toLocaleString(undefined, { maximumFractionDigits: 2 })}`;
const timerLabel = computed(() => {
    const seconds = Math.max(
        0,
        Math.floor(Number(remainingSeconds.value) || 0),
    );
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
            error.response?.data?.message ||
            "Unable to load the hotel reservation.";
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
                throw new Error(
                    "The payment gateway returned an invalid checkout URL.",
                );
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

        await hotelStore.confirmHotelBooking(
            booking.value.booking_id,
            "wallet",
        );
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
    <main class="min-h-screen bg-slate-50 p-4 sm:p-6">
        <section class="mx-auto max-w-5xl">
            <div
                v-if="isLoading"
                class="h-80 animate-pulse rounded border border-slate-200 bg-white"
            />
            <div
                v-else-if="errorMessage"
                class="rounded border border-red-200 bg-red-50 p-5 text-sm text-red-800"
            >
                {{ errorMessage }}
            </div>
            <div
                v-else-if="booking"
                class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]"
            >
                <article
                    class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4"
                    >
                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-wide text-primary"
                            >
                                Hotel payment
                            </p>
                            <h1 class="mt-1 text-xl font-bold text-slate-950">
                                Choose payment method
                            </h1>
                        </div>
                        <div
                            class="flex items-center gap-2 font-mono text-sm font-bold"
                            :class="
                                isExpired ? 'text-red-700' : 'text-amber-700'
                            "
                        >
                            <Clock3 class="h-4 w-4" />{{ timerLabel }}
                        </div>
                    </header>
                    <div class="grid sm:grid-cols-[190px_minmax(0,1fr)]">
                        <aside
                            class="border-b border-slate-200 bg-slate-50 p-3 sm:border-b-0 sm:border-r"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded px-3 py-3 text-left text-sm font-semibold"
                                :class="
                                    paymentMethod === 'wallet'
                                        ? 'bg-white text-primary shadow-sm'
                                        : 'text-slate-700'
                                "
                                @click="paymentMethod = 'wallet'"
                            >
                                <Wallet class="h-5 w-5" />Wallet balance</button
                            >
                            <button
                                type="button"
                                class="mt-2 flex w-full items-center gap-3 rounded px-3 py-3 text-left text-sm font-semibold"
                                :class="
                                    paymentMethod === 'card'
                                        ? 'bg-white text-primary shadow-sm'
                                        : 'text-slate-700'
                                "
                                @click="paymentMethod = 'card'"
                            >
                                <CreditCard class="h-5 w-5" />Debit / credit
                                card
                            </button>
                        </aside>
                        <div class="p-5 sm:p-6">
                            <div class="flex items-center gap-3">
                                <component
                                    :is="
                                        paymentMethod === 'wallet'
                                            ? Wallet
                                            : CreditCard
                                    "
                                    class="h-7 w-7 text-primary"
                                />
                                <div>
                                    <h2 class="font-semibold text-slate-950">
                                        {{
                                            paymentMethod === "wallet"
                                                ? "Wallet Balance"
                                                : "Card payment"
                                        }}
                                    </h2>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            paymentMethod === "wallet"
                                                ? "Pay from your Jetze wallet."
                                                : "Continue to secure Nomod checkout."
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-8 border-t border-slate-100 pt-5">
                                <p class="text-sm text-slate-500">
                                    Amount payable
                                </p>
                                <p
                                    class="mt-1 text-3xl font-bold text-slate-950"
                                >
                                    {{ formatMoney(total) }}
                                </p>
                                <p
                                    v-if="
                                        paymentMethod === 'wallet' &&
                                        Number.isFinite(walletBalanceAed)
                                    "
                                    class="mt-2 text-xs text-slate-500"
                                >
                                    Available wallet balance: AED
                                    {{
                                        walletBalanceAed.toLocaleString(
                                            undefined,
                                            { maximumFractionDigits: 2 },
                                        )
                                    }}
                                </p>
                            </div>
                            <Button
                                class="mt-8 h-12 w-full rounded bg-primary text-sm font-bold text-white hover:bg-primary/90"
                                :disabled="
                                    isExpired ||
                                    booking.status !== 'payment_pending'
                                "
                                :is-loading="
                                    isProcessing || isStartingCardPayment
                                "
                                @click="completePayment"
                                >{{
                                    isExpired
                                        ? "Reservation expired"
                                        : paymentMethod === "wallet"
                                          ? "Pay with wallet and confirm booking"
                                          : "Continue to secure checkout"
                                }}</Button
                            >
                            <p class="mt-3 text-xs leading-5 text-slate-500">
                                {{
                                    paymentMethod === "wallet"
                                        ? "After wallet verification succeeds, your saved reservation is sent to TBO for final confirmation."
                                        : "Card payment will only be confirmed by a verified Nomod webhook."
                                }}
                            </p>
                        </div>
                    </div>
                </article>
                <aside
                    class="rounded border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-primary">
                        <Hotel class="h-5 w-5" />
                        <p class="text-sm font-bold uppercase tracking-wide">
                            Booking summary
                        </p>
                    </div>
                    <h2 class="mt-3 text-lg font-bold text-slate-950">
                        {{ booking.hotel?.name || "Hotel reservation" }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ booking.check_in }} to {{ booking.check_out }}
                    </p>
                    <div class="mt-5 border-t border-slate-100 pt-4 text-sm">
                        <p class="text-slate-500">Reference</p>
                        <p class="mt-1 font-semibold text-slate-900">
                            {{ booking.booking_reference_id }}
                        </p>
                    </div>
                </aside>
            </div>
        </section>
        <div
            v-if="isLowBalanceDialogOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            @click.self="isLowBalanceDialogOpen = false"
        >
            <div class="w-full max-w-md rounded bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-950">
                    Low balance
                </h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Your wallet balance is insufficient to confirm this hotel
                    booking. Please add funds to your account.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <Button
                        variant="outline"
                        @click="isLowBalanceDialogOpen = false"
                        >Cancel</Button
                    >
                    <Button
                        class="bg-primary text-white hover:bg-primary/90"
                        @click="goToWalletDeposit"
                        >Go to deposit</Button
                    >
                </div>
            </div>
        </div>
    </main>
</template>
