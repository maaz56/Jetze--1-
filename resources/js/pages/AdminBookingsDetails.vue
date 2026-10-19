<script setup>

import { useRoute, useRouter } from 'vue-router';
import SabreBookingDetailsOffline from './Sabre/SabreBookingDetailsOffline.vue';
import AirsialBookingDetailsOffline from './Airsial/AirsialBookingDetailsOffline.vue';
import HakimiBookingDetails from './Hakimi/HakimiBookingDetails.vue';
import { onMounted, ref } from 'vue';
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
    <HakimiBookingDetails v-if="String(provider || '').toLowerCase() === 'hakimi'" />
</template>
