<script setup>
const props = defineProps({
    message: {
        type: String,
        required: true,
    },
    type: {
        type: String,
        default: "error",
    },
});
</script>

<template>
    <span
        class="mt-3 text-sm font-medium"
        :class="
            props.type == 'success'
                ? 'text-gray-500'
                : '' || props.type == 'error'
                ? 'text-red-500'
                : ''
        "
        >{{ message }}</span
    >
</template>
