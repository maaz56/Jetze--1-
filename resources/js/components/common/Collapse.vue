<script setup>
import { ref } from "vue";

defineProps({
    buttonTitle: {
        type: String,
        required: true,
    },
    buttonClasses: {
        type: String,
        required: false,
    },
    accordionClasses: {
        type: String,
        required: false,
    },
});

const isExpanded = ref(false);

const toggleAccordion = () => {
    isExpanded.value = !isExpanded.value;
};
</script>

<template>
    <div>
        <button @click="toggleAccordion" :class="buttonClasses">
            <slot name="button"></slot>
        </button>
        <div v-if="isExpanded" :class="accordionClasses">
            <slot name="body"></slot>
        </div>
    </div>
</template>
