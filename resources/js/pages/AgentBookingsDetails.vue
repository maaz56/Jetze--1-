<script setup>

import { useRoute, useRouter } from 'vue-router';
import SabreAgentBookingDetailsOffline from './Sabre/SabreAgentBookingDetailsOffline.vue';
import SooperAgentBookingDetailsOffline from './Sooper/SooperAgentBookingDetailsOffline.vue';
import AirsialAgentBookingDetailsOffline from './Airsial/AirsialAgentBookingDetailsOffline.vue';
import { ref } from 'vue';
import { onMounted } from 'vue';
import AirblueAgentBookingDetails from './airblue/AirblueAgentBookingDetails.vue';
import TravelPortAgentBookingDetailsOffline from './Travelport/TravelPortAgentBookingDetailsOffline.vue';
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
    <SooperAgentBookingDetailsOffline v-if="provider === 'sooper' && flight_mode === 'B2B'" />
    <TravelPortAgentBookingDetailsOffline v-if="provider === 'travelport' && flight_mode === 'B2B'" />
</template>