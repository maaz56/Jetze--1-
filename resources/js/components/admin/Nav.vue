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
    Truck,
    Users
} from "lucide-vue-next";

import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import Calender from "../common/Calender.vue";
import NotificationsAlert from "../common/NotificationsAlert.vue";
import { Button } from "../ui/button";

const authStore = useAuthStore();
const route = useRoute();
const currentTime = ref('');
const currentDate = ref('');

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
        <div class="relative bg-primary overflow-hidden">
            <div class="container mx-auto px-2 sm:px-4 md:px-6 lg:px-8 relative z-10">
                <div class="flex h-14 sm:h-16 items-center justify-between">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex items-center gap-1 sm:gap-2">
                                <svg class="w-6 h-6 sm:w-8 sm:h-8 admin-icon-secondary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 12L2 12M2 12L10 18M2 12L10 6" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <span class="text-lg sm:text-xl md:text-2xl font-bold text-secondary tracking-tight">Jetze</span>
                            </div>
                        </div>
                    </div>

                    <!-- Flight dashboard-style clock and date -->
                    
                    <div class="text-xs sm:text-sm font-medium text-secondary mt-1 flex items-center gap-1">
                            <Calendar class="h-3 w-3 sm:h-4 sm:w-4 admin-icon-secondary" />
                            {{ currentDate }}
                        </div>
                    <!-- Right side items -->
                    <div class="flex items-center gap-2 sm:gap-4">
                        <!-- Decorative separator -->
                        <div class="hidden md:block">
                            <div class="h-6 sm:h-8 w-[1px] bg-white/30 mx-1 sm:mx-2"></div>
                        </div>

                        <!-- Radar-style notifications -->
                       <NotificationsAlert :isAdmin="true"></NotificationsAlert>

                        <!-- Control panel-style user menu -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="rounded-full h-10 w-10 sm:h-11 sm:w-11 border border-secondary/30 bg-primary hover:bg-primary/90 text-secondary"
                                >
                                    <CircleUser class="h-4 sm:h-5 w-4 sm:w-5" />
                                    <span class="sr-only">Toggle user menu</span>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-48 sm:w-56 p-2 bg-secondary text-secondary-foreground border border-primary/20 shadow-lg">
                                <DropdownMenuLabel class="font-normal">
                                    <div class="flex flex-col space-y-1">
                                        <p class="text-sm font-medium leading-none">Pilot Name</p>
                                        <p class="text-xs leading-none opacity-70">pilot@example.com</p>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator class="bg-primary/20" />
                                <DropdownMenuItem class="cursor-pointer hover:bg-primary/10">
                                    <LayoutDashboard class="mr-2 h-4 w-4 admin-icon-primary" />
                                    <span>Dashboard</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem class="cursor-pointer hover:bg-primary/10">
                                    <PencilRuler class="mr-2 h-4 w-4 admin-icon-primary" />
                                    <span>Settings</span>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator class="bg-primary/20" />
                                <DropdownMenuItem @click="handleLogout()" class="cursor-pointer text-red-400 hover:bg-red-400/20">
                                    <ArrowUpFromDot class="mr-2 h-4 w-4" />
                                    <span>Logout</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Cockpit-style mobile menu -->
                        <div class="flex md:hidden">
                            <Sheet>
                                <SheetTrigger as-child>
                                    <Button variant="ghost" size="icon" class="md:hidden h-10 w-10 sm:h-11 sm:w-11 text-secondary hover:bg-primary/90 rounded-full">
                                        <Menu class="h-5 sm:h-6 w-5 sm:w-6" />
                                        <span class="sr-only">Open menu</span>
                                    </Button>
                                </SheetTrigger>
                                <SheetContent side="right" class="w-64 sm:w-80 bg-primary border-l border-secondary/30">
                                    <div class="flex justify-center my-4 sm:my-6">
                                        <div class="text-center">
                                            <div class="text-xl sm:text-2xl font-mono font-bold bg-secondary text-primary px-3 sm:px-4 py-1 rounded border border-secondary/30">
                                                {{ currentTime }}
                                            </div>
                                            <div class="text-xs sm:text-sm font-medium text-secondary mt-2 flex items-center justify-center gap-1">
                                                <Calendar class="h-3 sm:h-4 w-3 sm:w-4 admin-icon-secondary" />
                                                {{ currentDate }}
                                            </div>
                                        </div>
                                    </div>
                                    <nav class="flex flex-col gap-2 mt-4 sm:mt-6">
                                        <Button variant="ghost" class="justify-start text-sm sm:text-base hover:bg-primary/90 text-secondary py-2">
                                            <LayoutDashboard class="mr-2 h-4 sm:h-5 w-4 sm:w-5 admin-icon-secondary" />
                                            Dashboard
                                        </Button>
                                        <Button variant="ghost" class="justify-start text-sm sm:text-base hover:bg-primary/90 text-secondary py-2">
                                            <Users class="mr-2 h-4 sm:h-5 w-4 sm:w-5 admin-icon-secondary" />
                                            Users
                                        </Button>
                                        <Button variant="ghost" class="justify-start text-sm sm:text-base hover:bg-primary/90 text-secondary py-2">
                                            <Package class="mr-2 h-4 sm:h-5 w-4 sm:w-5 admin-icon-secondary" />
                                            Flights
                                        </Button>
                                        <Button variant="ghost" class="justify-start text-sm sm:text-base hover:bg-primary/90 text-secondary py-2">
                                            <Truck class="mr-2 h-4 sm:h-5 w-4 sm:w-5 admin-icon-secondary" />
                                            Bookings
                                        </Button>
                                        <Button variant="ghost" class="justify-start text-sm sm:text-base hover:bg-primary/90 text-secondary py-2">
                                            <PencilRuler class="mr-2 h-4 sm:h-5 w-4 sm:h-5 admin-icon-secondary" />
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
/* Responsive adjustments */
@media (max-width: 640px) {
    .container {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}
</style>
