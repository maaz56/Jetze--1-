<script setup>
import { useRoute, useRouter } from 'vue-router';

import { computed, watch } from 'vue';
import TravelPortCustomerPaymentView from './Travelport/TravelPortCustomerPaymentView.vue';
import OneApiCustomerPaymentView from './OneApi/OneApiCustomerPaymentView.vue';
import ATCustomerPaymentView from './AT/ATCustomerPaymentView.vue';

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
    <TravelPortCustomerPaymentView v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <OneApiCustomerPaymentView v-else-if="provider === 'OneApi' && flight_mode === 'B2C'" />
    <!-- Hakimi uses the same customer payment UI and gateways as AT. -->
    <ATCustomerPaymentView v-else-if="isAtOrHakimi && flight_mode === 'B2C'" />
</template>
