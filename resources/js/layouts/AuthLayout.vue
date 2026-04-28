<script setup>
import WhatsappButton from "@/components/common/WhatsappButton.vue";
</script>

<template>
    <section>
        <main>
            <!-- extending pages here -->
            <router-view></router-view>
            <WhatsappButton />
        </main>
    </section>
</template>
