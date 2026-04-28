<!-- <script setup>
import Nav from "@/components/admin/Nav.vue";
import Sidebar from "@/components/admin/Sidebar.vue";
import { Badge } from "@/components/ui/badge";

import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import { Settings } from "lucide-vue-next";
import { Users2 } from "lucide-vue-next";
import {
    Bell,
    CircleUser,
    Home,
    LineChart,
    Menu,
    Package,
    Package2,
    Search,
    ShoppingCart,
    Users,
} from "lucide-vue-next";
</script>

<template>
    <div
        class="grid min-h-screen w-full md:grid-cols-[220px_1fr] lg:grid-cols-[280px_1fr]"
    >
        <Sidebar />
        <div class="flex flex-col">
            <Nav />
            <main class="flex flex-1 flex-col gap-4 p-4 lg:gap-6 lg:p-8">
                <router-view></router-view>
            </main>
        </div>
    </div>
</template> -->

<script setup>
import Nav from "@/components/admin/Nav.vue";
import Sidebar from "@/components/admin/Sidebar.vue";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/components/ui/card";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
    Settings,
    Users2,
    Bell,
    CircleUser,
    Home,
    LineChart,
    Menu,
    Package,
    Package2,
    Search,
    ShoppingCart,
    Users,
} from "lucide-vue-next";
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <Sidebar
            class="hidden md:flex md:w-[220px] lg:w-[280px] flex-shrink-0"
        />
        <div class="flex flex-col flex-grow overflow-hidden">
            <Nav class="flex-shrink-0" />
            <div class="flex flex-grow overflow-hidden">
                <Sidebar class="md:hidden" />
                <main class="flex-grow overflow-y-auto p-4 lg:p-8 bg-gray-50">
                    <router-view></router-view>
                </main>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Add any component-specific styles here */
</style>
