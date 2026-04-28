<script setup>
import { useRoute, useRouter } from 'vue-router';

import { watch } from 'vue';
import TravelPortCustomerPaymentView from './Travelport/TravelPortCustomerPaymentView.vue';
import AirblueCustomerPaymentView from './airblue/AirblueCustomerPaymentView.vue';
import OneApiCustomerPaymentView from './OneApi/OneApiCustomerPaymentView.vue';

const router = useRouter();
const route = useRoute();
const provider = route.query.flight_provider ;
const flight_mode = route.query.flight_mode ;
watch(provider,()=>{
    console.log(provider);
    console.log(flight_mode);
})
</script>
<template>
    <TravelPortCustomerPaymentView v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <AirblueCustomerPaymentView v-else-if="provider === 'airblue' && flight_mode === 'B2C'" />
    <OneApiCustomerPaymentView v-else-if="provider === 'OneApi' && flight_mode === 'B2C'" />
</template>