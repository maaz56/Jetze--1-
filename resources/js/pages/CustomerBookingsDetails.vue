<script setup>

import { useRoute, useRouter } from 'vue-router';
import { FETCH_PNR_DATA } from '@/services/store/actions.type';
import { useStore } from 'vuex';
import { onMounted } from 'vue';
import { ref } from 'vue';
import ATPublicBookingDetails from './AT/ATPublicBookingDetails.vue';
import HakimiPublicBookingDetails from './Hakimi/HakimiPublicBookingDetails.vue';
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
    <ATPublicBookingDetails v-if="provider === 'at' && flight_mode === 'B2C'" />
    <HakimiPublicBookingDetails v-if="String(provider || '').toLowerCase() === 'hakimi'" />
</template>
