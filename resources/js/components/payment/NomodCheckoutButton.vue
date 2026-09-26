<script setup>
import apiService from "@/services/store/apiService";
import { computed, ref } from "vue";

const props = defineProps({
    bookingId: {
        type: [Number, String],
        required: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const isStarting = ref(false);
const error = ref("");
const canStart = computed(() => Boolean(props.bookingId) && !props.disabled && !isStarting.value);

const startCheckout = async () => {
    if (!canStart.value) return;

    error.value = "";
    isStarting.value = true;

    try {
        const response = await apiService.createNomodCheckout(props.bookingId);
        const checkoutUrl = response?.data?.checkout_url;

        if (typeof checkoutUrl !== "string" || !checkoutUrl) {
            throw new Error("The payment gateway did not return a checkout URL.");
        }

        window.location.assign(checkoutUrl);
    } catch (requestError) {
        error.value = requestError?.response?.data?.message
            || requestError?.message
            || "Unable to start secure checkout. Please try again.";
        isStarting.value = false;
    }
};
</script>

<template>
    <section class="mt-6 rounded-md border border-primary/20 bg-primary/[0.04] p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded border border-primary/20 bg-primary/10 text-primary" aria-hidden="true">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M3 9h18M7 15h3" />
                    </svg>
                </span>
                <div>
                    <h2 class="font-semibold text-slate-900">Secure Card Payment</h2>
                    <p class="mt-1 text-sm leading-5 text-slate-600">
                        Pay securely using your debit or credit card. A 2.5% processing fee is applied to the booking total; your wallet balance will not be used.
                    </p>
                </div>
            </div>
            <button
                type="button"
                :disabled="!canStart"
                class="inline-flex min-h-11 shrink-0 items-center justify-center rounded bg-primary px-5 py-2.5 font-semibold text-white transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
                @click="startCheckout"
            >
                {{ isStarting ? "Processing…" : "Make Payment" }}
            </button>
        </div>
        <p v-if="error" class="mt-3 text-sm text-destructive" role="alert">{{ error }}</p>
    </section>
</template>
