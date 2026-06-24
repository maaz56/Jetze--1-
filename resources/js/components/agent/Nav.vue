<script setup>
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import router from "@/config/router";
import { formatAmount } from "@/lib/utils";
import {
    FETCH_AGENT_DATA,
    FETCH_AGENT_LEDGER
} from "@/services/store/actions.type";
import { useAuthStore } from "@/services/stores/auth";
import { useMagicKeys } from "@vueuse/core";

import {
    BadgeDollarSign,
    CircleUser,
    Mail,
    Menu,
    Package,
    Phone,
    Wallet
} from "lucide-vue-next";
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import NotificationsAlert from "../common/NotificationsAlert.vue";
import Button from "../ui/button/Button.vue";


const authStore = useAuthStore();
const user = computed(() => authStore.user);
const store = useStore();
const user_id = computed(() => user?.value?.id);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);

const mobileMenuOpen = ref(false);
const open = ref(false);
const error = ref();
const loading = ref(false);



const { Meta_J, Ctrl_J } = useMagicKeys({
    passive: false,
    onEventFired(e) {
        if (e.key === "j" && (e.metaKey || e.ctrlKey)) e.preventDefault();
    },
});
function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch("ledger/" + FETCH_AGENT_LEDGER, {
                userId: user_id.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}

function fetchAgent() {
    if (user_id.value) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: user_id.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}

watch([Meta_J, Ctrl_J], (v) => {
    if (v[0] || v[1]) handleOpenChange();
});
watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgentLedger();
        fetchAgent();
    }
});

function handleOpenChange() {
    open.value = !open.value;
}

function handleLogout() {
    router.push('/')
    authStore.logout();
}

function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value;
}
onMounted(() => {
     if (user?.value?.id) {
        fetchAgentLedger();
        fetchAgent();
    }
    // Listen for notifications on public channel only
   

    // Initialize Pusher
  
});

</script>

<template>
    <div class="flex flex-col w-full bg-white shadow-sm">
          
        <!-- Main Header -->
        <div class="flex items-center justify-between border-b px-4 py-3 lg:h-[60px] lg:px-6">
            <!-- Left Section: Logo and Mobile Menu Toggle -->
            <div class="flex items-center">
                <!-- Mobile Menu Toggle -->
                <button 
                    class="mr-3 p-1 rounded-md text-gray-500 hover:bg-gray-100 lg:hidden"
                    @click="toggleMobileMenu"
                    aria-label="Toggle mobile menu">
                    <Menu class="h-5 w-5" />
                </button>
                
                <!-- Logo -->
                <div>
                    <img src="../../../../public/assets/logo.png" alt="Company Logo"
                        class="h-7 w-auto sm:h-8" />
                </div>
            </div>

            <!-- Contact Information - Hidden on Mobile, Visible on Medium+ -->
            <div class="hidden md:flex items-center space-x-3 lg:space-x-6">
                <!-- Phone -->
                <div class="flex items-center group">
                    <Phone class="h-4 w-4 mr-1 text-primary" />
                    <a href="tel:03111448111" class="text-xs lg:text-sm text-primary-400 hover:text-gray-200">
                        (+92) 0000000000
                    </a>
                </div>

                <!-- WhatsApp -->
                <div class="flex items-center group">
                    <svg class="h-4 w-4 mr-1 text-primary" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.004 5.45-4.437 9.884-9.885 9.884"/>
                    </svg>
                    <a href="https://wa.me/923334419634?text=Hello%2C%20I%20am%20interested%20in%20your%20services" 
                       class="text-xs lg:text-sm text-primary-400 hover:text-gray-200" 
                       target="_blank"
                       aria-label="Chat on WhatsApp">
                        +92 333 4419634
                    </a>
                </div>

                <!-- Email -->
                <div class="flex items-center group">
                    <Mail class="h-4 w-4 mr-1 text-primary" />
                    <a href="mailto:support@Jetze.pk" class="text-xs lg:text-sm text-primary-400 hover:text-gray-200">
                        support@Jetze.pk
                    </a>
                </div>
            </div>

            <!-- Right Section: Wallet & User Menu -->
            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- Wallet Balance - Hidden on Mobile -->
                <div class="hidden sm:flex items-center bg-gray-50 text-gray-700 px-2 py-1 sm:px-3 sm:py-1.5 rounded-md">
                    <Wallet class="h-3 w-3 sm:h-4 sm:w-4 mr-1 sm:mr-2" />
                    <span class="text-xs sm:text-sm font-medium">{{ formatAmount(agentLedger?.balance) }}</span>
                </div>

                <!-- Notifications -->
            <NotificationsAlert />

                <!-- User Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="flex items-center gap-1 sm:gap-2 rounded-full p-1 sm:p-2">
                            <div class="flex items-center">
                                <div
                                    class="h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-700 font-medium overflow-hidden">
                                    <img v-if="user?.avatar" :src="user.avatar" alt="User Avatar"
                                        class="h-full w-full object-cover" />
                                    <span v-else>{{ user?.name?.charAt(0).toUpperCase() ||
                                        user?.email?.charAt(0).toUpperCase() || 'U' }}</span>
                                </div>
                            </div>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>My Account</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer" @click="router.push({ name: 'Settings' })">
                            <CircleUser class="mr-2 h-4 w-4" />
                            <span>Profile</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="router.push({ name: 'Deposits' })">
                            <Wallet class="mr-2 h-4 w-4" />
                            <span>Wallet</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="router.push({ name: 'ContactUs' })">
                            <BadgeDollarSign class="mr-2 h-4 w-4" />
                            <span href="mailto:support@Jetze.pk">Support</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer text-red-600" @click="handleLogout()">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 h-4 w-4">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Logout</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>

        <!-- Mobile Menu - Only visible when toggled -->
        <div v-if="mobileMenuOpen" class="lg:hidden border-b">
            <!-- Contact Information for Mobile -->
            <div class="flex flex-col space-y-3 p-4">
                <div class="text-sm font-medium text-gray-600 mb-1">Contact Us</div>
                
                <!-- Phone -->
                <div class="flex items-center group">
                    <Phone class="h-4 w-4 mr-2 text-primary" />
                    <a href="tel:+923111448111" class="text-sm text-primary-400 hover:text-gray-200">
                         (+92) 0000000000
                    </a>
                </div>

                <!-- WhatsApp -->
                <div class="flex items-center group">
                    <svg class="h-4 w-4 mr-2 text-primary" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.004 5.45-4.437 9.884-9.885 9.884"/>
                    </svg>
                    <a href="https://wa.me/923334419634?text=Hello%2C%20I%20am%20interested%20in%20your%20services" 
                       class="text-sm text-primary-400 hover:text-gray-200" 
                       target="_blank"
                       aria-label="Chat on WhatsApp">
                        +92 333 4419634
                    </a>
                </div>

                <!-- Email -->
                <div class="flex items-center group">
                    <Mail class="h-4 w-4 mr-2 text-primary" />
                    <a href="mailto:support@Jetze.pk" class="text-sm text-primary-400 hover:text-gray-200">
                        support@Jetze.pk
                    </a>
                </div>

                <!-- Wallet for Mobile -->
                <div class="flex items-center bg-gray-50 text-gray-700 px-3 py-2 rounded-md w-fit mt-2">
                    <Wallet class="h-4 w-4 mr-2" />
                    <span class="text-sm font-medium">{{ formatAmount(agentLedger?.balance) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>