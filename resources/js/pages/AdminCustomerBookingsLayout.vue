<script setup>
import { computed, defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';

const bookingDetailsComponents = {
    'sabre:B2C': defineAsyncComponent(() => import('./Sabre/SabreAdminCustomerBookingDetails.vue')),
    'airsial:B2C': defineAsyncComponent(() => import('./Airsial/AirsialAdminCustomerBookingDetails.vue')),
    'travelport:B2C': defineAsyncComponent(() => import('./Travelport/TravelPortAdminCustomerBookingDetails.vue')),
    'OneApi:B2C': defineAsyncComponent(() => import('./OneApi/OneApiAdminCustomerBookingDetails.vue')),
    'at:B2C': defineAsyncComponent(() => import('./AT/ATAdminCustomerBookingDetailsOffline.vue')),
    'hakimi:B2C': defineAsyncComponent(() => import('./Hakimi/HakimiBookingDetails.vue')),
};

const route = useRoute();
const selectedComponentKey = computed(() => {
    const provider = String(route.query.flight_provider || '').toLowerCase();
    const mode = String(route.query.flight_mode || 'B2C').toUpperCase();
    return `${provider}:${mode}`;
});
const selectedComponent = computed(() => {
    return bookingDetailsComponents[selectedComponentKey.value] 
        || bookingDetailsComponents[`${route.query.flight_provider}:${route.query.flight_mode}`]
        || null;
});
</script>

<template>
    <component
        :is="selectedComponent"
        v-if="selectedComponent"
        :key="selectedComponentKey"
    />
</template>
