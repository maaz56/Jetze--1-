<script setup>
import Nav from "@/components/admin/Nav.vue";
import Sidebar from "@/components/admin/Sidebar.vue";
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-slate-50">
        <Sidebar class="flex-shrink-0" />
        <div class="flex min-w-0 flex-grow flex-col overflow-hidden">
            <Nav />
            <main class="flex-grow overflow-y-auto bg-slate-50/80 p-4 lg:p-6">
                <router-view></router-view>
            </main>
        </div>
    </div>
</template>

<style scoped>
</style>
