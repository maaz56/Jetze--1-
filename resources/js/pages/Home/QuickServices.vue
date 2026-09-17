<script setup>
import { RouterLink } from 'vue-router';
import { 
    School, 
    FileCheck2, 
    Users2, 
    ShieldCheck, 
    Building2, 
    Sun, 
    Briefcase,
    Zap,
    Plane,
    Headphones
} from 'lucide-vue-next';

const trustItems = [
    { label: '100% Secure Checkout', icon: ShieldCheck, color: 'text-blue-600' },
    { label: 'Instant E-Tickets', icon: Zap, color: 'text-orange-500' },
    { label: '450+ Verified Airlines', icon: Plane, color: 'text-blue-600' },
    { label: '24/7 Dedicated Support', icon: Headphones, color: 'text-orange-500' },
];

const subServices = [
    {
        id: 'umrah',
        title: 'Umrah Packages',
        icon: School,
        iconColor: 'text-emerald-600',
        route: '/umra-packages'
    },
    {
        id: 'visas',
        title: 'Visa Services',
        icon: FileCheck2,
        iconColor: 'text-orange-500',
        route: '/visa'
    },
    {
        id: 'groups',
        title: 'Group Charters',
        icon: Users2,
        iconColor: 'text-blue-600',
        route: '/group-tickets-main'
    },
    {
        id: 'insurance',
        title: 'Travel Insurance',
        icon: ShieldCheck,
        iconColor: 'text-purple-600',
        route: '/travel-insurance'
    },
    {
        id: 'hotels',
        title: 'Luxury Stays',
        icon: Building2,
        iconColor: 'text-indigo-600',
        route: '/hotel/search'
    },
    {
        id: 'holidays',
        title: 'Holiday Escapes',
        icon: Sun,
        iconColor: 'text-amber-500',
        route: '/holidays'
    },
    {
        id: 'corporate',
        title: 'Corporate Travel',
        icon: Briefcase,
        iconColor: 'text-slate-700',
        route: '/our/services'
    }
];
</script>

<template>
    <!-- Single Unified Bar (clean 'rounded', solid background, no color split) -->
    <div class="w-full bg-white border border-gray-200/90 rounded shadow-xs overflow-hidden">
        
        <!-- Trust Guarantees Top Strip -->
        <div class="bg-slate-50/90 border-b border-gray-100 px-4 sm:px-6 py-2 flex flex-wrap items-center justify-around gap-3 text-xs text-gray-600 font-medium">
            <div 
                v-for="(item, idx) in trustItems" 
                :key="idx" 
                class="flex items-center gap-1.5"
            >
                <component :is="item.icon" class="w-3.5 h-3.5 shrink-0" :class="item.color" />
                <span class="text-[11px] sm:text-xs text-slate-700 font-medium">{{ item.label }}</span>
            </div>
        </div>

        <!-- Quick Travel Services Main Strip -->
        <div class="px-3 sm:px-6 py-2.5 overflow-x-auto no-scrollbar">
            <div class="flex items-center justify-between min-w-max gap-3 sm:gap-6 divide-x divide-gray-100">
                <RouterLink 
                    v-for="(service, idx) in subServices" 
                    :key="service.id"
                    :to="service.route"
                    :class="[
                        'flex items-center gap-2 text-xs sm:text-sm font-semibold text-gray-700 hover:text-primary transition-colors cursor-pointer group py-1',
                        idx > 0 ? 'pl-3 sm:pl-6' : ''
                    ]"
                >
                    <component 
                        :is="service.icon" 
                        class="w-4 h-4 transition-transform group-hover:scale-110 shrink-0" 
                        :class="service.iconColor" 
                    />
                    <span class="whitespace-nowrap">{{ service.title }}</span>
                </RouterLink>
            </div>
        </div>

    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
