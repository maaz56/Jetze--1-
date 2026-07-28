<script setup>
import { computed, defineAsyncComponent } from 'vue';
import { useRoute } from 'vue-router';

const bookingDetailsComponents = {
    'sabre:B2C': defineAsyncComponent(() => import('./Sabre/SabreAdminCustomerBookingDetails.vue')),
    'airsial:B2C': defineAsyncComponent(() => import('./Airsial/AirsialAdminCustomerBookingDetails.vue')),
    'travelport:B2C': defineAsyncComponent(() => import('./Travelport/TravelPortAdminCustomerBookingDetails.vue')),
    'OneApi:B2C': defineAsyncComponent(() => import('./OneApi/OneApiAdminCustomerBookingDetails.vue')),
    'at:B2C': defineAsyncComponent(() => import('./AT/ATAdminCustomerBookingDetailsOffline.vue')),
};

const route = useRoute();
const selectedComponentKey = computed(() => `${route.query.flight_provider}:${route.query.flight_mode}`);
const selectedComponent = computed(() => bookingDetailsComponents[selectedComponentKey.value] || null);
</script>

<template>
    <component
        :is="selectedComponent"
        v-if="selectedComponent"
        :key="selectedComponentKey"
    />
</template>
