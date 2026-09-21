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
watch(provider,()=>{
    console.log(provider);
    console.log(flight_mode);
})
</script>
<template>
    <TravelPortCustomerPaymentView v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <OneApiCustomerPaymentView v-else-if="provider === 'OneApi' && flight_mode === 'B2C'" />
    <ATCustomerPaymentView v-else-if="provider === 'at' && flight_mode === 'B2C'" />
</template>
