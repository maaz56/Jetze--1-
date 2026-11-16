<script setup>
import { useRoute, useRouter } from 'vue-router';

import { computed, watch } from 'vue';
import ATCustomerPaymentView from './AT/ATCustomerPaymentView.vue';
import HotelPaymentView from './HotelPaymentView.vue';

const router = useRouter();
const route = useRoute();
const provider = computed(() => route.query.flight_provider);
const flight_mode = computed(() => route.query.flight_mode);
const bookingId = computed(() => route.query.booking_id);
const isAtOrHakimi = computed(() => ['at', 'hakimi'].includes(String(provider.value || '').toLowerCase()));
watch(provider,()=>{
})
</script>
<template>
    <HotelPaymentView v-if="route.query.booking_type === 'hotel'" />
    <!-- Hakimi uses the same customer payment UI and gateways as AT. -->
    <ATCustomerPaymentView v-else-if="isAtOrHakimi && flight_mode === 'B2C'" />
</template>
