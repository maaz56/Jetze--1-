<script setup>
import SabreCustomerFlightCheckout from '@/pages/Sabre/SabreCustomerFlightCheckout.vue';
import { useRoute, useRouter } from 'vue-router';
import SabreFlightCheckout from './Sabre/SabreFlightCheckout.vue';
import AirsialFlightCheckout from './Airsial/AirsialFlightCheckout.vue';
import { watch } from 'vue';
import PIACustomerFlightCheckout from './PIA/PIACustomerFlightCheckout.vue';
import AirsialCustomerFlightCheckout from './Airsial/AirsialCustomerFlightCheckout.vue';
import FlydubaiCustomerFlightCheckout from './FlyDubai/FlydubaiCustomerFlightCheckout.vue';
import AirblueCustomerFlightCheckout from './airblue/AirblueCustomerFlightCheckout.vue';
import TravelPortCustomerFlightCheckout from './Travelport/TravelPortCustomerFlightCheckout.vue';
import OneApiCustomerFlightCheckout from './OneApi/OneApiCustomerFlightCheckout.vue';
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
    <SabreCustomerFlightCheckout v-if="provider === 'sabre' && flight_mode === 'B2C'" />
    <PIACustomerFlightCheckout v-if="provider === 'PIA' && flight_mode === 'B2C'" />
    <AirsialCustomerFlightCheckout v-if="provider === 'airsial' && flight_mode === 'B2C'" />
    <FlydubaiCustomerFlightCheckout v-if="provider === 'flydubai' && flight_mode === 'B2C'" />
    <AirblueCustomerFlightCheckout v-if="provider === 'airblue' && flight_mode === 'B2C'" />
    <TravelPortCustomerFlightCheckout v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <OneApiCustomerFlightCheckout v-if="provider === 'OneApi' && flight_mode === 'B2C'" />
</template>