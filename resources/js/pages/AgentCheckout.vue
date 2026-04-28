<script setup>
import SabreCustomerFlightCheckout from '@/pages/Sabre/SabreCustomerFlightCheckout.vue';
import { useRoute, useRouter } from 'vue-router';
import SabreFlightCheckout from './Sabre/SabreFlightCheckout.vue';
import AirsialFlightCheckout from './Airsial/AirsialFlightCheckout.vue';
import PIAFlightCheckout from './PIA/PIAFlightCheckout.vue';
import { onMounted, ref } from 'vue';
import FlydubaiFlightCheckout from './FlyDubai/FlydubaiFlightCheckout.vue';
import AirblueFlightCheckout from './airblue/AirblueFlightCheckout.vue';
import TravelPortFlightCheckout from './Travelport/TravelPortFlightCheckout.vue';
const router = useRouter();
const route = useRoute();
const provider = ref(route.query.flight_provider);
const flight_mode = ref(route.query.flight_mode);
onMounted(()=>{
    provider.value = route.query.flight_provider;
    flight_mode.value = route.query.flight_mode;
})
</script>
<template>
    <AirsialFlightCheckout v-if="provider === 'airsial' && flight_mode === 'B2B'" />
    <PIAFlightCheckout v-if="provider === 'PIA' && flight_mode === 'B2B'" />
    <FlydubaiFlightCheckout v-if="provider === 'flydubai' && flight_mode === 'B2B'" />
    <SabreFlightCheckout v-if="provider === 'sabre' && flight_mode === 'B2B'" />
    <AirblueFlightCheckout v-if="provider === 'airblue' && flight_mode === 'B2B'" />
    <TravelPortFlightCheckout v-if="provider === 'travelport' && flight_mode === 'B2B'" />
</template>