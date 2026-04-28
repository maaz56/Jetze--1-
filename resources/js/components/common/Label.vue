<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <label
        :for="$attrs.for"
        class="block mb-2 text-sm font-medium text-gray-900 dark:text-white"
    >
        {{ title }}
    </label>
</template>
