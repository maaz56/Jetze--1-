<template>
    <div class=" mt-4 rounded-md border border-primary/20 bg-primary/10 text-primary h-8 flex items-center overflow-hidden">
        <div class="flex items-center  px-3 py-1">
            <svg class="w-5 h-5 text-primary mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="font-semibold text-sm">Announcements:</span>
        </div>
        <div class="marquee">
            <span>{{ message }}</span>
        </div>
    </div>
</template>

<script setup>
import { defineProps } from 'vue';

const props = defineProps({
    message: {
        type: String,
        default: "Welcome to Jetze! Stay tuned for updates."
    }
});
</script>

<style scoped>


.marquee {
    white-space: nowrap;
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.marquee span {
    display: inline-block;
    padding-left: 100%;
    animation: marquee 15s linear infinite;
}

@keyframes marquee {
    0% {
        transform: translateX(0%);
    }
    100% {
        transform: translateX(-100%);
    }
}
</style>