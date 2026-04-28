<script setup>

import { useRoute, useRouter } from 'vue-router';
import { onMounted, ref } from 'vue';
import AirblueAdminCustomerBookingDetails from './airblue/AirblueAdminCustomerBookingDetails.vue';
import SabreAdminCustomerBookingDetails from './Sabre/SabreAdminCustomerBookingDetails.vue';
import AirsialAdminCustomerBookingDetails from './Airsial/AirsialAdminCustomerBookingDetails.vue';
import TravelPortAdminCustomerBookingDetails from './Travelport/TravelPortAdminCustomerBookingDetails.vue';
import OneApiAdminCustomerBookingDetails from './OneApi/OneApiAdminCustomerBookingDetails.vue';
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
    <SabreAdminCustomerBookingDetails v-if="provider === 'sabre' && flight_mode === 'B2C'" />
    <AirsialAdminCustomerBookingDetails v-if="provider === 'airsial' && flight_mode === 'B2C'" />
    <AirblueAdminCustomerBookingDetails v-if="provider === 'airblue' && flight_mode === 'B2C'" />
    <TravelPortAdminCustomerBookingDetails v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <OneApiAdminCustomerBookingDetails v-if="provider === 'OneApi' && flight_mode === 'B2C'" />
    <!-- <SooperAgentBookingDetailsOffline v-if="provider === 'sooper' && flight_mode === 'B2B'" /> -->
</template>