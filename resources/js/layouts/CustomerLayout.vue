
<script setup>
import { AlertCircleIcon, ClockIcon, MailIcon, PhoneIcon } from 'lucide-vue-next';

import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";
import { computed, onMounted, provide, ref } from "vue";
import Nav from "@/components/customer/Nav.vue";
import Sidebar from '@/components/customer/Sidebar.vue';
const authStore = useAuthStore();
const flightStore=useFlightStore()
const isLoadingFlightStore = computed(() => flightStore.isLoading);
const user = computed(() => authStore.user);
const isLoading = ref(true);
const isSidebarExpanded = ref(true);

// Function to toggle sidebar state
const toggleSidebar = (expanded) => {
    isSidebarExpanded.value = expanded;
};

// Provide the sidebar state and toggle function to child components
provide('sidebarState', {
    isExpanded: isSidebarExpanded,
    toggle: toggleSidebar
});


const initializeAuth = async () => {
    try {
        isLoading.value = true

        // Check if we need to fetch the user data
        // This assumes your Pinia store has a method to check/fetch the current user
        if (!authStore.isAuthenticated) {
            await authStore.fetchUser() // Replace with your actual method name
        }

    } catch (error) {
        console.error('Error initializing auth:', error)
    } finally {
        // Set loading to false after a small delay to prevent flashing
        setTimeout(() => {
            isLoading.value = false
        }, 100)
    }
}

onMounted(() => {
    initializeAuth()
})
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar with dynamic width based on expanded state -->
        <!-- <aside  
            class="flex-shrink-0 overflow-y-auto bg-white border-r border-gray-200 shadow-sm transition-all duration-300 ease-in-out"
            :class="{
                'w-[280px] lg:w-[280px]': isSidebarExpanded,
                'w-[70px] lg:w-[70px]': !isSidebarExpanded,
                '!hidden':!user?.is_approved==1
            }">
            <Sidebar @toggle-sidebar="toggleSidebar" />
        </aside> -->

        <!-- Main Content Area that expands to fill available space -->
         
        <div  class="flex flex-col flex-1 overflow-hidden">
            <!-- Navbar -->
            <!-- <Nav  class="flex-shrink-0" /> -->

            <!-- Router View (Scrollable Area) -->
            <main class="flex-1 overflow-y-auto p-4 lg:p-8 bg-gray-50">
                
                <template v-if="isLoading">
                    <div class="flex items-center justify-center h-full">
                        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
                    </div>
                </template>
                <template v-else-if="user?.is_approved">
                    <div>
                        <router-view></router-view>
                    </div>
                </template>

                <div v-else>
                     <div v-if="user?.is_formFilled == 1" class="flex items-center justify-center p-4">
                        <div
                            class="max-w-md w-full bg-white shadow-2xl rounded-lg overflow-hidden transform hover:scale-105 transition-all duration-300">
                            <div class="p-6 sm:p-8">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="relative">
                                        <AlertCircleIcon class="h-8 w-8 text-yellow-500" />
                                        <span
                                            class="absolute top-0 right-0 h-3 w-3 bg-red-500 rounded-full animate-ping"></span>
                                    </div>
                                    <h2 class="text-2xl font-bold text-gray-800">Account Approval Required</h2>
                                </div>
                                <p class="text-gray-600 leading-relaxed mb-6">
                                    Please contact the admin to approve your account. We're here to assist you
                                    through the process. After approval please refresh the page.
                                </p>
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-3 text-gray-700">
                                        <MailIcon class="h-5 w-5 text-gray-500" />
                                        <a href="mailto:support@Jetze.pk"
                                            class="hover:text-gray-500 transition-colors duration-300">support@Jetze.pk</a>
                                    </div>
                                    <div class="flex items-center space-x-3 text-gray-700">
                                        <PhoneIcon class="h-5 w-5 text-gray-500" />
                                        <a href="tel:+923111448111"
                                            class="hover:text-gray-500 transition-colors duration-300">+923111448111</a>
                                    </div>
                                    <div class="flex items-center space-x-3 text-gray-700">
                                        <ClockIcon class="h-5 w-5 text-purple-500" />
                                        <span>Mon-Fri, 9:00 AM - 5:00 PM</span>
                                    </div>
                                </div>
                                <div class="mt-8" v-if="!user?.is_formFilled">
                                    <button @click="
                                        $router.push({
                                            name: 'AddAgentDetails'
                                        })
                                        "
                                        class="w-full bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Please add your company details.
                                    </button>
                                </div>


                            </div>
                            <div class="bg-gray-50 px-6 py-4 sm:px-8 sm:py-6 border-t border-gray-100">
                                <p class="text-sm text-gray-500 text-center">
                                    Thank you for your patience. We'll process your approval as quickly as possible.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style>
/* Ensure the app takes up the full height of the viewport */
html,
body,
#app {
    height: 100%;
}

/* Hide scrollbar for Chrome, Safari and Opera */


/* Hide scrollbar for IE, Edge and Firefox */

</style>

<style scoped>
@keyframes gradient {
    0% {
        background-position: 0% 50%;
    }

    50% {
        background-position: 100% 50%;
    }

    100% {
        background-position: 0% 50%;
    }
}

.bg-gradient-to-br {
    background-size: 200% 200%;
    animation: gradient 15s ease infinite;
}

.animate-ping {
    animation: ping 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping {

    75%,
    100% {
        transform: scale(2);
        opacity: 0;
    }
}
</style>