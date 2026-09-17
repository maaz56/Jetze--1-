<script setup>
import { ref } from 'vue';
import { 
    ChevronLeft, 
    ChevronRight, 
    ArrowRight, 
    Sparkles 
} from 'lucide-vue-next';
import { RouterLink } from 'vue-router';

const collections = ref([
    {
        id: 1,
        title: 'Sacred Umrah & Ziyarat Journeys',
        subtitle: '5★ Walking-distance Haram hotels, express visa assist & VIP private transfers',
        badge: 'Spiritual',
        badgeColor: 'bg-emerald-600 text-white',
        count: '12 Packages',
        image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=800&h=1000&fit=crop',
        route: '/umra-packages'
    },
    {
        id: 2,
        title: 'Tropical Island & Coral Reef Retreats',
        subtitle: 'Overwater luxury villas in Maldives, Bali & Phuket with private excursions',
        badge: 'Island Luxe',
        badgeColor: 'bg-teal-600 text-white',
        count: '16 Packages',
        image: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&h=1000&fit=crop',
        route: '/holidays'
    },
    {
        id: 3,
        title: 'European Historic & Alpine Wonders',
        subtitle: 'Scenic rail journeys across Switzerland, historic Paris & London city discovery',
        badge: 'Heritage',
        badgeColor: 'bg-indigo-600 text-white',
        count: '14 Packages',
        image: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800&h=1000&fit=crop',
        route: '/holidays'
    },
    {
        id: 4,
        title: 'Metropolitan Luxury & Shopping Escapes',
        subtitle: 'Iconic skyscraper skyline suites, Dubai shopping festival & Michelin dining',
        badge: 'City Breaks',
        badgeColor: 'bg-blue-600 text-white',
        count: '11 Packages',
        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=800&h=1000&fit=crop',
        route: '/hotel/search'
    },
    {
        id: 5,
        title: 'High Altitude Valleys & Northern Expeditions',
        subtitle: 'Hunza, Skardu & Gilgit majestic peaks with premium alpine resort stays',
        badge: 'Adventure',
        badgeColor: 'bg-orange-500 text-white',
        count: '9 Packages',
        image: 'https://images.unsplash.com/photo-1546548970-71785318a17b?w=800&h=1000&fit=crop',
        route: '/holidays'
    }
]);

const scrollContainer = ref(null);

const scroll = (direction) => {
    if (scrollContainer.value) {
        const scrollAmount = 450;
        scrollContainer.value.scrollBy({
            left: direction === 'left' ? -scrollAmount : scrollAmount,
            behavior: 'smooth'
        });
    }
};
</script>

<template>
    <section class="w-full bg-white py-14 sm:py-18 px-4 sm:px-6 lg:px-8 border-b border-border/70">
        <div class="max-w-7xl mx-auto">
            
            <!-- Section Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 sm:mb-10 gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold uppercase tracking-wider mb-2">
                        <Sparkles class="w-3.5 h-3.5 text-orange-500" />
                        Curated Collections
                    </div>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">
                        Handcrafted <span class="text-blue-600">Travel Collections</span>
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-gray-600 font-normal">
                        Immersive vacation themes designed for family holidays, solo discovery, and spiritual pilgrimages.
                    </p>
                </div>

                <!-- Navigation Controls -->
                <div class="flex items-center gap-2 self-end md:self-auto">
                    <button 
                        @click="scroll('left')" 
                        class="w-10 h-10 rounded border border-gray-300 bg-white flex items-center justify-center text-gray-700 hover:bg-primary hover:text-white hover:border-primary shadow-xs transition-colors cursor-pointer"
                        title="Previous Collection"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </button>
                    <button 
                        @click="scroll('right')" 
                        class="w-10 h-10 rounded border border-gray-300 bg-white flex items-center justify-center text-gray-700 hover:bg-primary hover:text-white hover:border-primary shadow-xs transition-colors cursor-pointer"
                        title="Next Collection"
                    >
                        <ChevronRight class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Collections Carousel -->
            <div 
                ref="scrollContainer"
                class="flex gap-5 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-4 pt-1"
            >
                <div 
                    v-for="item in collections" 
                    :key="item.id"
                    class="min-w-[270px] sm:min-w-[320px] lg:min-w-[360px] snap-start"
                >
                    <RouterLink 
                        :to="item.route"
                        class="group relative block w-full h-[420px] sm:h-[450px] rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 border border-gray-200 cursor-pointer"
                    >
                        <!-- Background Image with Zoom -->
                        <img 
                            :src="item.image" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                            :alt="item.title"
                            loading="lazy"
                        >
                        
                        <!-- Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/30 to-transparent"></div>

                        <!-- Top Header Pill & Count -->
                        <div class="absolute top-4 left-4 right-4 flex items-center justify-between z-10">
                            <span 
                                class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded shadow-sm"
                                :class="item.badgeColor"
                            >
                                {{ item.badge }}
                            </span>

                            <span class="bg-white text-gray-900 text-xs font-bold px-2.5 py-0.5 rounded shadow-sm">
                                {{ item.count }}
                            </span>
                        </div>

                        <!-- Bottom Content -->
                        <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-6 flex flex-col justify-end z-10">
                            <h3 class="text-lg sm:text-xl font-black text-white leading-tight mb-2 group-hover:text-blue-300 transition-colors">
                                {{ item.title }}
                            </h3>
                            <p class="text-xs text-gray-300 font-normal leading-relaxed line-clamp-2 mb-3">
                                {{ item.subtitle }}
                            </p>

                            <!-- Explore CTA Pill -->
                            <div class="inline-flex items-center gap-1.5 text-xs font-bold text-white group-hover:text-orange-400 transition-colors">
                                <span>Explore Collection</span>
                                <ArrowRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </div>

        </div>
    </section>
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