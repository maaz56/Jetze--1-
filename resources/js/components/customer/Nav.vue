<script setup>
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuthStore } from "@/services/stores/auth";
import {
    ArrowUpFromDot,
    Calendar,
    CircleUser,
    LayoutDashboard,
    Menu,
    Package,
    PencilRuler,
    PlaneTakeoff,
    Truck,
    Users
} from "lucide-vue-next";

import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import Calender from "../common/Calender.vue";
import NotificationsAlert from "../common/NotificationsAlert.vue";
import { Button } from "../ui/button";
import { computed } from "vue";

const authStore = useAuthStore();
const route = useRoute();
const currentTime = ref('');
const currentDate = ref('');
const user = computed(() => authStore.user);
function fetchUser() {
    authStore.fetchUser();
}
function updateDateTime() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    currentTime.value = `${hours}:${minutes}:${seconds}`;
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentDate.value = now.toLocaleDateString('en-US', options);
}

let clockInterval;

onMounted(() => {
    fetchUser();
    updateDateTime();
    clockInterval = setInterval(updateDateTime, 1000);
});

onBeforeUnmount(() => {
    if (clockInterval) clearInterval(clockInterval);
});

function handleLogout() {
    authStore.logout();
}
const testDate = ref(undefined);
</script>

<template>
    <header class="sticky top-0 z-50 w-full shadow-lg">
        <div class="relative bg-gradient-to-r from-primary to-primary/50 overflow-hidden">
            <!-- Animated clouds -->
            <div class="absolute inset-0 clouds"></div>

            <div class="container mx-auto px-2 sm:px-4 md:px-6 lg:px-8 relative z-10">
                <div class="flex h-14 sm:h-16 items-center justify-between">
                    <!-- Logo with airplane icon -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center gap-1 sm:gap-2">

                                <span
                                    class="text-lg sm:text-xl md:text-2xl font-bold text-white tracking-tight">Jetze</span>
                            </div>
                        </div>
                    </div>

                    <!-- Flight dashboard-style clock and date -->

                    <div class="text-xs sm:text-sm font-medium text-white mt-1 flex items-center gap-1">
                        <Calendar class="h-3 w-3 sm:h-4 sm:w-4 text-green-400" />
                        {{ currentDate }}
                    </div>
                    <!-- Right side items -->
                    <div class="flex items-center gap-2 sm:gap-4">
                        <!-- Decorative separator -->
                        <div class="hidden md:block">
                            <div class="h-6 sm:h-8 w-[1px] bg-white/30 mx-1 sm:mx-2"></div>
                        </div>
                        <!-- Radar-style notifications -->
                        <NotificationsAlert class="text-white" :isAdmin="true"></NotificationsAlert>
                        <router-link :to="{ name: 'Home' }" class="flex items-center">
                            <Button class=" bg-transparent shadow-none">
                                <PlaneTakeoff class=" text-white" />
                            </Button>
                        </router-link>
                        <!-- Control panel-style user menu -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="icon"
                                    class="rounded-full h-10 w-10 sm:h-11 sm:w-11 border border-white/30 bg-black/50 hover:bg-white/20 text-white">
                                    <CircleUser class="h-4 sm:h-5 w-4 sm:w-5" />
                                    <span class="sr-only">Toggle user menu</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end"
                                class="w-48 sm:w-56 p-2 bg-black/80 text-white border border-white/30 shadow-lg">
                                <DropdownMenuLabel class="font-normal">
                                    <div class="flex flex-col space-y-1">
                                        <p class="text-xs leading-none opacity-70">{{ user?.email }}</p>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator class="bg-white/30" />
                                <DropdownMenuItem class="cursor-pointer hover:bg-white/20">
                                    <LayoutDashboard class="mr-2 h-4 w-4 text-green-400" />
                                    <span>Dashboard</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem class="cursor-pointer hover:bg-white/20">
                                    <PencilRuler class="mr-2 h-4 w-4 text-green-400" />
                                    <span>Settings</span>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator class="bg-white/30" />
                                <DropdownMenuItem @click="handleLogout()"
                                    class="cursor-pointer text-red-400 hover:bg-red-400/20">
                                    <ArrowUpFromDot class="mr-2 h-4 w-4" />
                                    <span>Logout</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Cockpit-style mobile menu -->
                        <div class="flex md:hidden">
                            <Sheet>
                                <SheetTrigger as-child>
                                    <Button variant="ghost" size="icon"
                                        class="md:hidden h-10 w-10 sm:h-11 sm:w-11 text-white hover:bg-white/20 rounded-full">
                                        <Menu class="h-5 sm:h-6 w-5 sm:w-6" />
                                        <span class="sr-only">Open menu</span>
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="right" class="w-64 sm:w-80 bg-black/90 border-l border-white/30">
                                    <div class="flex justify-center my-4 sm:my-6">
                                        <div class="text-center">
                                            <div
                                                class="text-xl sm:text-2xl font-mono font-bold bg-black/50 text-green-400 px-3 sm:px-4 py-1 rounded border border-green-400/30">
                                                {{ currentTime }}
                                            </div>
                                            <div
                                                class="text-xs sm:text-sm font-medium text-white mt-2 flex items-center justify-center gap-1">
                                                <Calendar class="h-3 sm:h-4 w-3 sm:w-4 text-green-400" />
                                                {{ currentDate }}
                                            </div>
                                        </div>
                                    </div>
                                    <nav class="flex flex-col gap-2 mt-4 sm:mt-6">
                                        <Button variant="ghost"
                                            class="justify-start text-sm sm:text-base hover:bg-white/20 text-white py-2">
                                            <LayoutDashboard class="mr-2 h-4 sm:h-5 w-4 sm:w-5 text-green-400" />
                                            Dashboard
                                        </Button>
                                        <Button variant="ghost"
                                            class="justify-start text-sm sm:text-base hover:bg-white/20 text-white py-2">
                                            <Users class="mr-2 h-4 sm:h-5 w-4 sm:w-5 text-green-400" />
                                            Users
                                        </Button>
                                        <Button variant="ghost"
                                            class="justify-start text-sm sm:text-base hover:bg-white/20 text-white py-2">
                                            <Package class="mr-2 h-4 sm:h-5 w-4 sm:w-5 text-green-400" />
                                            Flights
                                        </Button>
                                        <Button variant="ghost"
                                            class="justify-start text-sm sm:text-base hover:bg-white/20 text-white py-2">
                                            <Truck class="mr-2 h-4 sm:h-5 w-4 sm:w-5 text-green-400" />
                                            Bookings
                                        </Button>
                                        <Button variant="ghost"
                                            class="justify-start text-sm sm:text-base hover:bg-white/20 text-white py-2">
                                            <PencilRuler class="mr-2 h-4 sm:h-5 w-4 sm:h-5 text-green-400" />
                                            Settings
                                        </Button>
                                    </nav>
                                </SheetContent>
                            </Sheet>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
/* Animated clouds */
.clouds {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 50"><path d="M30 30a10 10 0 0 1 0-20h40a15 15 0 0 1 0 30H30zM100 35a12 12 0 0 1 0-24h50a10 10 0 0 1 0 20H100zM170 30a8 8 0 0 1 0-16h20a10 10 0 0 1 0 20H170z" fill="rgba(255,255,255,0.3)"/></svg>') repeat-x;
    background-size: 600px 100px;
    animation: moveClouds 60s linear infinite;
    opacity: 0.3;
}

@keyframes moveClouds {
    0% {
        background-position: 0 0;
    }

    100% {
        background-position: -600px 0;
    }
}

/* Animated airplane */
.airplane {
    animation: flyAirplane 10s linear forwards;
}

@keyframes flyAirplane {
    0% {
        transform: translateX(-100px);
        opacity: 0;
    }

    10% {
        opacity: 1;
    }

    90% {
        opacity: 1;
    }

    100% {
        transform: translateX(100vw);
        opacity: 0;
    }
}

/* Radar animation for notifications */
.animate-radar {
    animation: radarPulse 2s infinite;
}

@keyframes radarPulse {
    0% {
        transform: scale(1);
        opacity: 0.75;
    }

    50% {
        transform: scale(2);
        opacity: 0;
    }

    100% {
        transform: scale(1);
        opacity: 0.75;
    }
}

/* Responsive adjustments */
@media (max-width: 640px) {
    .container {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}
</style>