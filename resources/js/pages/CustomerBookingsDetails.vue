<script setup>

import { useRoute, useRouter } from 'vue-router';
import SabrePublicBookingDetails from './Sabre/SabrePublicBookingDetails.vue';
import ArisialPublicBookingDetails from './Airsial/ArisialPublicBookingDetails.vue';
import { FETCH_PNR_DATA } from '@/services/store/actions.type';
import { useStore } from 'vuex';
import { onMounted } from 'vue';
import { ref } from 'vue';
import AirbluePublicBookingDetails from './airblue/AirbluePublicBookingDetails.vue';
import TravelPortPublicBookingDetails from './Travelport/TravelPortPublicBookingDetails.vue';
import OneApiPublicBookingDetails from './OneApi/OneApiPublicBookingDetails.vue';
const router = useRouter();
const route = useRoute();
const provider = ref();
const flight_mode = ref();

onMounted(() => {
    // fetchPnrData();
    provider.value = route.query.flight_provider;
    flight_mode.value = route.query.flight_mode;
});
</script>
<template>
    <SabrePublicBookingDetails v-if="provider === 'sabre' && flight_mode === 'B2C'" />
    <ArisialPublicBookingDetails v-if="provider === 'airsial' && flight_mode === 'B2C'" />
    <AirbluePublicBookingDetails v-if="provider === 'airblue' && flight_mode === 'B2C'" />
    <TravelPortPublicBookingDetails v-if="provider === 'travelport' && flight_mode === 'B2C'" />
    <OneApiPublicBookingDetails v-if="provider === 'OneApi' && flight_mode === 'B2C'" />
</template>