<template>
    <button
        :class="'py-2 px-4 rounded-full border-2 border-fb-primary text-fb-primary font-medium'"
    >
        {{ props.title }}
    </button>
</template>

<script setup>
import { ref, computed } from "vue";

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>
