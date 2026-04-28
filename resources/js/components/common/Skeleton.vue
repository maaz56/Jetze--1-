<template>
    <div
        class="bg-gray-200 animate-pulse duration-75"
        :style="{ width: width, height: height, borderRadius: borderRadius }"
    ></div>
</template>

<script setup>
import { defineProps } from "vue";

const props = defineProps({
    width: {
        type: String,
        default: "100%",
    },
    height: {
        type: String,
        default: "100%",
    },
    borderRadius: {
        type: String,
        default: "1rem",
    },
});
</script>
