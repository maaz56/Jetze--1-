<script setup>
defineProps({
    modelValue: {
        type: String,
        default: "",
    },
    id: {
        type: String,
        default: "input",
    },
});
</script>

<template>
    <input
        type="text"
        :id="id"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
        v-bind="$attrs"
        :class="'bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-2xl focus:ring-fb-primary focus:border-fb-primary block w-full p-2.5'"
    />
</template>
