<script setup>
import ATFlowLoader from "@/components/common/ATFlowLoader.vue";
import apiService from "@/services/store/apiService";
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const paymentAttempt = computed(() => String(route.query.payment_attempt || ""));
const outcome = computed(() => String(route.params.outcome || "success"));
const payment = ref(null);
const error = ref("");
const isLoading = ref(true);
let pollTimer = null;
let redirectTimer = null;
let isUnmounted = false;

const isTicketIssued = computed(() => payment.value?.fulfilment_status === "completed");
const needsAttention = computed(() => [
    "failed",
    "reconciliation_required",
    "duplicate_payment_review",
].includes(payment.value?.fulfilment_status));
const isPaymentUnsuccessful = computed(() => ["failed", "cancelled"].includes(payment.value?.status));
const isFinished = computed(() => isTicketIssued.value
    || needsAttention.value
    || isPaymentUnsuccessful.value);
const showProgress = computed(() => !error.value && !isFinished.value);

const progressTitle = computed(() => {
    if (payment.value?.status === "paid") return "Issuing your ticket";
    return "Confirming your payment";
});

const progressMessage = computed(() => {
    if (payment.value?.status === "paid") {
        return "Your payment is verified. Please wait while we confirm your booking and issue the ticket.";
    }

    return "Please wait while we securely verify your payment with Nomod.";
});

const stateHeading = computed(() => {
    if (isTicketIssued.value) return "Ticket issued";
    if (needsAttention.value) return "Booking confirmation needs attention";
    if (payment.value?.status === "cancelled" || outcome.value === "cancelled") return "Payment was cancelled";
    return "Payment was not completed";
});

const stateMessage = computed(() => {
    if (isTicketIssued.value) {
        return "Your payment has been confirmed and your booking ticket has been issued.";
    }

    if (needsAttention.value) {
        return "Your payment is safely recorded. Our team is checking the booking confirmation; please do not pay again.";
    }

    if (payment.value?.status === "cancelled" || outcome.value === "cancelled") {
        return "Checkout was cancelled. No successful payment has been confirmed for this booking.";
    }

    return "No successful payment has been confirmed. You can return to the booking and try again.";
});

const loadStatus = async () => {
    if (!paymentAttempt.value) {
        error.value = "This payment return link is missing its payment reference.";
        isLoading.value = false;
        return;
    }

    try {
        const response = await apiService.getNomodPaymentStatus(paymentAttempt.value);
        payment.value = response.data;
        error.value = "";

        if (response.data?.booking_id && (
            response.data?.status === "failed"
            || response.data?.status === "cancelled"
            || ["completed", "failed", "reconciliation_required", "duplicate_payment_review"]
                .includes(response.data?.fulfilment_status)
        )) {
            redirectTimer = window.setTimeout(() => {
                if (!isUnmounted) {
                    router.replace({
                        name: "BookingsDetails",
                        query: {
                            booking_id: response.data.booking_id,
                            booking_source: "1",
                            flight_provider: "at",
                            flight_mode: "B2C",
                        },
                    });
                }
            }, 1200);
        }
    } catch (requestError) {
        error.value = requestError?.response?.data?.message
            || "We could not load the payment confirmation status.";
    } finally {
        isLoading.value = false;
    }
};

const stopPolling = () => {
    if (pollTimer) window.clearTimeout(pollTimer);
    pollTimer = null;
};

const pollUntilFinished = () => {
    stopPolling();

    if (isUnmounted || isFinished.value || error.value) return;

    pollTimer = window.setTimeout(async () => {
        await loadStatus();
        pollUntilFinished();
    }, 3000);
};

const retryStatus = async () => {
    isLoading.value = true;
    await loadStatus();
    pollUntilFinished();
};

const returnHome = () => {
    // The prior history entry is Nomod's checkout, so browser-back could reopen
    // it and invite a duplicate payment.
    router.push({ name: "Home" });
};

onMounted(async () => {
    await loadStatus();
    pollUntilFinished();
});

onBeforeUnmount(() => {
    isUnmounted = true;
    stopPolling();
    if (redirectTimer) window.clearTimeout(redirectTimer);
});
</script>

<template>
    <main class="min-h-[60vh] bg-slate-50 px-4 py-12">
        <section v-if="isLoading || showProgress" class="mx-auto flex min-h-[48vh] max-w-xl flex-col items-center justify-center text-center">
            <ATFlowLoader :fullscreen="false" />
            <h1 class="mt-3 text-2xl font-bold text-slate-900">{{ progressTitle }}</h1>
            <p class="mt-3 max-w-md text-slate-600">{{ progressMessage }}</p>
            <p class="mt-5 text-sm text-slate-500">Do not close this page or make another payment.</p>
        </section>

        <section v-else class="mx-auto max-w-xl rounded-xl border border-slate-200 bg-white p-7 text-center shadow-sm">
            <div
                :class="[
                    'mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full text-xl',
                    isTicketIssued ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700',
                ]"
            >
                {{ isTicketIssued ? '✓' : '!' }}
            </div>
            <h1 class="text-2xl font-bold text-slate-900">{{ error ? 'Unable to check payment status' : stateHeading }}</h1>
            <p class="mt-3 text-slate-600">{{ error || stateMessage }}</p>

            <div class="mt-6 flex justify-center gap-3">
                <button
                    v-if="error"
                    type="button"
                    class="rounded bg-primary px-4 py-2 font-medium text-white transition hover:bg-primary/90"
                    @click="retryStatus"
                >
                    Try again
                </button>
                <button
                    type="button"
                    class="rounded border border-slate-300 px-4 py-2 font-medium text-slate-700 transition hover:bg-slate-50"
                    @click="returnHome"
                >
                    Return home
                </button>
            </div>
        </section>
    </main>
</template>
