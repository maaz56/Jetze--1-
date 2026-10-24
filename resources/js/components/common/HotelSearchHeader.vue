<script setup>
import HotelSearchPanel from "@/components/hotel/HotelSearchPanel.vue";

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="w-full">
        <HotelSearchPanel :standalone-redirect="true" :embedded="props.embedded" />
    </div>
</template>
