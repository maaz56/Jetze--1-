<script setup>
import apiService from "@/services/store/apiService";
import { computed, ref } from "vue";

const props = defineProps({
    bookingId: {
        type: [Number, String],
        required: true,
    },
    /** "flight" or "hotel" — selects the checkout endpoint. */
    paymentType: {
        type: String,
        default: "flight",
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
        const response = props.paymentType === "hotel"
            ? await apiService.createHotelTabbyCheckout(props.bookingId)
            : await apiService.createTabbyCheckout(props.bookingId);
        const checkoutUrl = response?.data?.checkout_url;

        if (typeof checkoutUrl !== "string" || !checkoutUrl) {
            throw new Error("Tabby did not return a checkout URL.");
        }

        window.location.assign(checkoutUrl);
    } catch (requestError) {
        error.value = requestError?.response?.data?.message
            || requestError?.message
            || "Unable to start Tabby checkout. Please try again.";
        isStarting.value = false;
    }
};
</script>

<template>
    <div>
        <button
            type="button"
            :disabled="!canStart"
            class="inline-flex min-h-12 items-center justify-center rounded bg-primary px-7 py-3 font-semibold uppercase tracking-wide text-white transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500"
            @click="startCheckout"
        >
            {{ isStarting ? "Processing…" : "Pay with Tabby" }}
        </button>
        <p v-if="error" class="mt-3 text-sm text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
