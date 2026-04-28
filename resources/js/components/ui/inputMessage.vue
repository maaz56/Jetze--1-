<script setup>
import { cn } from "@/lib/utils";

const props = defineProps({
    class: { type: null, required: false },
});
</script>

<template>
    <p :class="cn('font-medium text-sm text-destructive select-none', props.class)">
        <slot />
    </p>
</template>
