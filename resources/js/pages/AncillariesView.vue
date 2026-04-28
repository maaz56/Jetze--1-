<script setup>
import { useRoute, useRouter } from 'vue-router';

import { watch } from 'vue';
import AirblueAncillariesView from './airblue/AirblueAncillariesView.vue';

const router = useRouter();
const route = useRoute();
const provider = route.query.flight_provider ;
const flight_mode = route.query.flight_mode ;

</script>
<template>
    <AirblueAncillariesView v-if="provider === 'airblue' && flight_mode === 'B2C'" />
    
</template>