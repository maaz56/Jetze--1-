<!-- <script setup>
import Nav from "@/components/agent/Nav.vue";
import Sidebar from "@/components/agent/Sidebar.vue";
import ApprovelNotice from "@/components/common/ApprovelNotice.vue";
import { AlertCircleIcon, MailIcon, PhoneIcon, ClockIcon } from 'lucide-vue-next'

import { useAuthStore } from "@/services/stores/auth";
import { computed, onMounted, ref } from "vue";

const authStore = useAuthStore();

const user = computed(() => authStore.user);
const isLoading = ref(true);
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
    <div class="flex h-screen overflow-hidden" >
        <aside class="w-[220px] lg:w-[280px] flex-shrink-0 overflow-y-auto bg-gray-100 dark:bg-gray-800">
            <Sidebar />
        </aside>

       
        <div class="flex flex-col flex-1 overflow-hidden">
            <Nav class="flex-shrink-0" />

          
            <main class="flex-1 overflow-y-auto p-4 lg:p-8">
                <template v-if="isLoading">
                    <div class="flex items-center justify-center h-full">
                        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary"></div>
                    </div>
                </template>
                <template v-if="user?.is_approved">
                    <div>
                        <router-view></router-view>
                    </div>

                </template>
                <div v-else>
       

        <div class="flex items-center justify-center p-4 ">
            <div
                class="max-w-md w-full bg-white shadow-2xl rounded-lg overflow-hidden transform hover:scale-105 transition-all duration-300">
                <div class="p-6 sm:p-8">
                    <div class="flex items-center space-x-3 mb-6">
                        <div class="relative">
                            <AlertCircleIcon class="h-8 w-8 text-yellow-500" />
                            <span class="absolute top-0 right-0 h-3 w-3 bg-red-500 rounded-full animate-ping"></span>
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
                            <a href="mailto:info@Jetze.com"
                                class="hover:text-gray-500 transition-colors duration-300">info@Jetze.com</a>
                        </div>
                        <div class="flex items-center space-x-3 text-gray-700">
                            <PhoneIcon class="h-5 w-5 text-gray-500" />
                            <a href="tel:+923000063111" class="hover:text-gray-500 transition-colors duration-300">+92
                                300
                                0063111</a>
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
html,
body,
#app {
    height: 100%;
}

.overflow-y-auto::-webkit-scrollbar {
    display: none;
}

.overflow-y-auto {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
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
</style> -->
<script setup>
import Nav from "@/components/agent/Nav.vue";
import Sidebar from "@/components/agent/Sidebar.vue";
import { AlertCircleIcon, ClockIcon, MailIcon, PhoneIcon } from 'lucide-vue-next';

import { useAuthStore } from "@/services/stores/auth";
import { useFlightStore } from "@/services/stores/flight";
import { computed, onMounted, provide, ref } from "vue";
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
        <aside  
            class="flex-shrink-0 overflow-y-auto bg-white border-r border-gray-200 shadow-sm transition-all duration-300 ease-in-out"
            :class="{
                'w-[280px] lg:w-[280px]': isSidebarExpanded,
                'w-[70px] lg:w-[70px]': !isSidebarExpanded,
                '!hidden':!user?.is_approved==1
            }">
            <Sidebar @toggle-sidebar="toggleSidebar" />
        </aside>

        <!-- Main Content Area that expands to fill available space -->
         
        <div  class="flex flex-col flex-1 overflow-hidden">
            <!-- Navbar -->
            <Nav  class="flex-shrink-0" />

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
                    <!-- <div v-if="user?.email_verified_at == null">
                        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                            <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4 overflow-hidden text-center">
                                <div class="bg-amber-50 p-4 border-b border-amber-100">
                                    <div class="flex flex-col items-center">
                                        <div class="bg-amber-400 rounded-full p-8 mr-3">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round" class="text-white">
                                                <path
                                                    d="M21.73 18l-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z">
                                                </path>
                                                <path d="M12 9v4"></path>
                                                <path d="M12 17h.01"></path>
                                            </svg>
                                        </div>
                                        <h3 class="mt-4 text-lg font-semibold text-amber-800">Email Verification Required
                                        </h3>
                                    </div>
                                </div>

                                <div class="p-6">
                                    <p class="text-gray-700 mb-4">
                                        Thank you for registering! Before you can access all features of our portal,
                                        please
                                        verify your
                                        email address.
                                    </p>
                                    <p class="text-gray-700 mb-6">
                                        We've sent a verification link to your email <span class="text-amber-800">{{ user?.email }}</span>. 
                                        <br>Please check your inbox and click
                                        on
                                        the link to
                                        complete the verification process.
                                    </p>

                                    <div class="flex flex-col sm:flex-row gap-3 justify-end">
                                        <button @click="resendVerificationEmail"
                                            class="px-4 py-2 border border-amber-500 text-amber-600 rounded-md hover:bg-amber-50 transition-colors">
                                            Resend Verification Email
                                        </button>
                                        <button @click="closeDialog"
                                            class="px-4 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 transition-colors">
                                            I'll Verify Later
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> -->
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