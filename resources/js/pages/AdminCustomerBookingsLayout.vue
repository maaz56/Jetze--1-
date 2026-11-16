<script setup>
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiService from '@/config/axios';

const bookingDetailsComponents = {
    'sabre:B2C': defineAsyncComponent(() => import('./Sabre/SabreAdminCustomerBookingDetails.vue')),
    'airsial:B2C': defineAsyncComponent(() => import('./Airsial/AirsialAdminCustomerBookingDetails.vue')),
    'travelport:B2C': defineAsyncComponent(() => import('./Travelport/TravelPortAdminCustomerBookingDetails.vue')),
    'OneApi:B2C': defineAsyncComponent(() => import('./OneApi/OneApiAdminCustomerBookingDetails.vue')),
    'at:B2C': defineAsyncComponent(() => import('./AT/ATAdminCustomerBookingDetailsOffline.vue')),
    'hakimi:B2C': defineAsyncComponent(() => import('./Hakimi/HakimiBookingDetails.vue')),
};

const route = useRoute();
const router = useRouter();
const resolving = ref(false);

// Links that only carry booking_id (e.g. older notifications) are completed
// from the booking itself, then sent to the right page for its mode.
async function completeQueryFromBooking() {
    if (route.query.flight_provider || !route.query.booking_id) return;
    resolving.value = true;
    try {
        const response = await apiService.get('/get-customer-flight-booking-details', {
            params: { bookingId: route.query.booking_id },
        });
        const booking = Array.isArray(response.data) ? response.data[0] : response.data;
        if (!booking) return;
        const mode = String(booking.booking_mode || 'B2C').toUpperCase();
        let provider = booking.flight_provider;
        if (mode === 'B2B') {
            try { provider = JSON.parse(booking.flight_data)?.provider?.name || provider; } catch (e) { /* keep column value */ }
        }
        const query = {
            ...route.query,
            pnr: booking.itinerary_ref || booking.pnr,
            agent_id: booking.agent_id,
            booking_source: booking.booking_source,
            flight_provider: provider,
            flight_mode: mode,
        };
        await router.replace({ name: mode === 'B2B' ? 'AdminBookingsDetails' : 'AdminCustomerBookingsLayout', query });
    } catch (e) {
        // leave the page as is
    } finally {
        resolving.value = false;
    }
}
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

// Show which customer this booking belongs to (their CUS_ ID).
const customerUid = ref('');
onMounted(async () => {
    await completeQueryFromBooking();
    const userId = route.query.agent_id;
    if (!userId) return;
    try {
        const response = await apiService.get(`/users/${userId}`);
        customerUid.value = response.data?.data?.customer?.customer_uid || '';
    } catch (e) {
        customerUid.value = '';
    }
});
</script>

<template>
    <div v-if="customerUid" class="mb-3 inline-flex items-center gap-2 rounded-md border bg-white px-3 py-2 text-sm">
        <span class="text-gray-500">Customer ID</span>
        <span class="font-semibold text-gray-900">{{ customerUid }}</span>
    </div>
    <component
        :is="selectedComponent"
        v-if="selectedComponent"
        :key="selectedComponentKey"
    />
</template>
