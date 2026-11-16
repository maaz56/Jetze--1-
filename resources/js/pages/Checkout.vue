<script setup>
import { useRoute, useRouter } from 'vue-router';
import { watch } from 'vue';
import ATCustomerFlightCheckout from './AT/ATCustomerFlightCheckout.vue';
const router = useRouter();
const route = useRoute();
const provider = route.query.flight_provider ;
const flight_mode = route.query.flight_mode ;
watch(provider,()=>{
})
</script>
<template>
    <!-- Hakimi currently shares the AT checkout form, but has no ancillary flow. -->
    <ATCustomerFlightCheckout v-if="(provider === 'at' || provider === 'hakimi') && flight_mode === 'B2C'" />
</template>
