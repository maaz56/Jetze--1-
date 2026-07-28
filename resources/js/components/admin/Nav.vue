<script setup>
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
    PencilRuler,
} from "lucide-vue-next";

import { computed, ref } from "vue";
import { useRoute } from "vue-router";
import NotificationsAlert from "../common/NotificationsAlert.vue";
import { Button } from "../ui/button";

const authStore = useAuthStore();
const route = useRoute();
const currentDate = ref(new Date().toLocaleDateString('en-US', {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: 'numeric',
}));

const pageTitle = computed(() => {
    if (typeof route.meta?.title === "string" && route.meta.title) {
        return route.meta.title;
    }

    return String(route.name || "Admin").replace(/([a-z])([A-Z])/g, "$1 $2");
});

const userName = computed(() => authStore.user?.name || "Admin User");
const userEmail = computed(() => authStore.user?.email || "admin@jetze.com");
const userInitials = computed(() => {
    return userName.value
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("") || "A";
});

function handleLogout() {
    authStore.logout();
}
</script>

<template>
    <header class="sticky top-0 z-30 w-full border-b border-gray-200 bg-white">
        <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Admin Console</p>
                <h1 class="truncate text-lg font-semibold text-gray-950 sm:text-xl">
                    {{ pageTitle }}
                </h1>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                <div class="hidden items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 sm:flex">
                    <Calendar class="h-4 w-4 text-gray-500" />
                    {{ currentDate }}
                </div>

                <NotificationsAlert
                    :isAdmin="true"
                    disable-animation
                    button-class="h-9 w-9 rounded-md border border-gray-200 bg-white text-gray-700 hover:bg-gray-50"
                />

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            class="h-10 gap-3 rounded-md border border-gray-200 bg-white px-2 text-gray-800 hover:bg-gray-50"
                        >
                            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-gray-900 text-xs font-semibold text-white">
                                {{ userInitials }}
                            </span>
                            <span class="hidden max-w-40 flex-col items-start text-left sm:flex">
                                <span class="w-full truncate text-sm font-semibold leading-4">{{ userName }}</span>
                                <span class="w-full truncate text-xs font-normal leading-4 text-gray-500">{{ userEmail }}</span>
                            </span>
                            <CircleUser class="h-4 w-4 text-gray-500 sm:hidden" />
                            <span class="sr-only">Open user menu</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56 border border-gray-200 bg-white p-1 text-gray-800 shadow-lg">
                        <DropdownMenuLabel class="px-3 py-2 font-normal">
                            <div class="flex flex-col space-y-1">
                                <p class="truncate text-sm font-semibold leading-none">{{ userName }}</p>
                                <p class="truncate text-xs leading-none text-gray-500">{{ userEmail }}</p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem class="cursor-pointer" @click="$router.push({ name: 'Dashboard' })">
                            <LayoutDashboard class="mr-2 h-4 w-4 text-gray-500" />
                            <span>Dashboard</span>
                        </DropdownMenuItem>
                        <DropdownMenuItem class="cursor-pointer" @click="$router.push({ name: 'Setting' })">
                            <PencilRuler class="mr-2 h-4 w-4 text-gray-500" />
                            <span>Settings</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="handleLogout()" class="cursor-pointer text-red-600 focus:text-red-600">
                            <ArrowUpFromDot class="mr-2 h-4 w-4" />
                            <span>Logout</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </header>
</template>
