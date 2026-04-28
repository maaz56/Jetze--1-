<script setup>
import { useRoute, useRouter } from 'vue-router';

import { watch } from 'vue';
import TravelPortPaymentView from './Travelport/TravelPortCustomerPaymentView.vue';

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
    <TravelPortPaymentView v-if="provider === 'travelport' && flight_mode === 'B2B'" />
    
</template>