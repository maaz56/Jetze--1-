<script setup>
import Nav from "../components/shared/Nav.vue";
import Sidebar from "../components/agent/Sidebar.vue";
import Footer from "../components/common/Footer.vue";
</script>

<template>
    <section>
        <Nav />
        <main class="py-4 bg-gray-100 min-h-screen">
            <div class="container flex flex-col md:flex-row gap-4 p-4">
                <Sidebar />
                <div class="py-4 w-full">
                    <!-- extending pages here -->
                    <router-view></router-view>
                </div>
            </div>
        </main>
    </section>
    <Footer />
</template>
