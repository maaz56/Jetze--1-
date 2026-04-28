<script setup>
import { initFlowbite } from "flowbite";
import { onMounted } from "vue";
const props = defineProps({
    transparent: {
        type: Boolean,
        default: false,
    },
});

// initialize components based on data attribute selectors
onMounted(() => {
    initFlowbite();
});
</script>

<template>
    <nav class="py-2 items-center sticky top-0 z-50 bg-white " :class="{ 'bg-gradient': !transparent }">
        <div class="container grid grid-cols-1 md:grid-cols-2">
            <div class="flex gap-x-3 justify-center md:justify-start">
                <a href="#"><i class="fa-brands fa-instagram mr-1 text-white"></i></a>
                <div class="border-l border-white h-[20px] mx-2 md:block"></div>
                <a href="#"><i class="fa-brands fa-youtube mr-1 text-white"></i></a>
                <div class="border-l border-white h-[20px] mx-2 md:block"></div>
                <a href="#"><i class="fa-brands fa-facebook-f mr-1 text-white"></i></a>
                <div class="border-l border-white h-[20px] mx-2 md:block"></div>
            </div>

            <div class="flex gap-x-3 justify-center md:justify-end">
                <span class="text-sm md:block text-white">
                    UAN: <a href="tel:+923111448111">+923111448111</a>
                </span>
                <div class="border-l border-white h-[20px] mx-2 hidden md:block"></div>
                <span class="text-sm md:block text-white">
                    <a href="mailto:support@Jetze.pk">support@Jetze.pk</a>
                </span>
            </div>
        </div>
    </nav>
</template>

<style scoped>
.bg-gradient {
    background: linear-gradient(to right, #dbcaa4, #af9b64);
}
</style>