<script setup>

import { useRoute, useRouter } from 'vue-router';
import SabreBookingDetailsOffline from './Sabre/SabreBookingDetailsOffline.vue';
import AirsialBookingDetailsOffline from './Airsial/AirsialBookingDetailsOffline.vue';
import AirblueAdminBookingDetails from './airblue/AirblueAdminBookingDetails.vue';
import { onMounted, ref } from 'vue';
// import SooperAgentBookingDetailsOffline from './Sooper/SooperAgentBookingDetailsOffline.vue';
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
    <SabreBookingDetailsOffline v-if="provider === 'sabre' && flight_mode === 'B2B'" />
    <AirsialBookingDetailsOffline v-if="provider === 'airsial' && flight_mode === 'B2B'" />
    <AirblueAdminBookingDetails v-if="provider === 'airblue' && flight_mode === 'B2B'" />
    <!-- <SooperAgentBookingDetailsOffline v-if="provider === 'sooper' && flight_mode === 'B2B'" /> -->
</template>