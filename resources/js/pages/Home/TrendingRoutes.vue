<script setup>
import { ref, computed } from 'vue';
import { 
    MapPin, 
    Plane, 
    Star, 
    ArrowRight, 
    Clock, 
    Compass, 
    Sparkles 
} from 'lucide-vue-next';
import { RouterLink } from 'vue-router';
import { getSelectedCurrencyCode } from '@/lib/utils';

const activeTab = ref('international');
const currency = computed(() => getSelectedCurrencyCode() || 'AED');

const internationalDestinations = [
    {
        id: 1,
        city: 'Dubai',
        country: 'United Arab Emirates',
        code: 'DXB',
        rating: 4.9,
        flightDuration: '3h 20m',
        flightType: 'Direct Flight',
        priceAED: '950',
        pricePKR: '78,000',
        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600&h=450&fit=crop',
        tag: 'Best Seller',
        tagColor: 'bg-blue-600 text-white'
    },
    {
        id: 2,
        city: 'Jeddah & Makkah',
        country: 'Saudi Arabia',
        code: 'JED',
        rating: 5.0,
        flightDuration: '4h 45m',
        flightType: 'Direct Flight',
        priceAED: '1,250',
        pricePKR: '98,000',
        image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=600&h=450&fit=crop',
        tag: 'Umrah Special',
        tagColor: 'bg-emerald-600 text-white'
    },
    {
        id: 3,
        city: 'Istanbul',
        country: 'Turkey',
        code: 'IST',
        rating: 4.8,
        flightDuration: '5h 30m',
        flightType: 'Direct Flight',
        priceAED: '1,420',
        pricePKR: '115,000',
        image: 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=600&h=450&fit=crop',
        tag: 'Historic',
        tagColor: 'bg-orange-500 text-white'
    },
    {
        id: 4,
        city: 'London',
        country: 'United Kingdom',
        code: 'LHR',
        rating: 4.9,
        flightDuration: '8h 15m',
        flightType: '1-Stop / Direct',
        priceAED: '2,100',
        pricePKR: '168,000',
        image: 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?w=600&h=450&fit=crop',
        tag: 'Popular',
        tagColor: 'bg-indigo-600 text-white'
    },
    {
        id: 5,
        city: 'Baku',
        country: 'Azerbaijan',
        code: 'GYD',
        rating: 4.7,
        flightDuration: '3h 50m',
        flightType: 'Direct Flight',
        priceAED: '890',
        pricePKR: '69,000',
        image: 'https://images.unsplash.com/photo-1578895101408-1a36b834405b?w=600&h=450&fit=crop',
        tag: 'Trending Now',
        tagColor: 'bg-purple-600 text-white'
    },
    {
        id: 6,
        city: 'Bangkok',
        country: 'Thailand',
        code: 'BKK',
        rating: 4.8,
        flightDuration: '4h 50m',
        flightType: 'Direct Flight',
        priceAED: '1,380',
        pricePKR: '109,000',
        image: 'https://images.unsplash.com/photo-1508009603885-50cf7c579365?w=600&h=450&fit=crop',
        tag: 'Holiday Escape',
        tagColor: 'bg-teal-600 text-white'
    }
];

const domesticDestinations = [
    {
        id: 7,
        city: 'Karachi',
        country: 'Pakistan',
        code: 'KHI',
        rating: 4.7,
        flightDuration: '1h 45m',
        flightType: 'Direct Flight',
        priceAED: '310',
        pricePKR: '24,500',
        image: 'https://images.unsplash.com/photo-1588668214407-6ea9a6d8c272?w=600&h=450&fit=crop',
        tag: 'Business Hub',
        tagColor: 'bg-blue-600 text-white'
    },
    {
        id: 8,
        city: 'Islamabad',
        country: 'Pakistan',
        code: 'ISB',
        rating: 4.8,
        flightDuration: '1h 50m',
        flightType: 'Direct Flight',
        priceAED: '320',
        pricePKR: '25,200',
        image: 'https://images.unsplash.com/photo-1627894483216-2138af692e32?w=600&h=450&fit=crop',
        tag: 'Capital City',
        tagColor: 'bg-emerald-600 text-white'
    },
    {
        id: 9,
        city: 'Lahore',
        country: 'Pakistan',
        code: 'LHE',
        rating: 4.9,
        flightDuration: '1h 45m',
        flightType: 'Direct Flight',
        priceAED: '310',
        pricePKR: '24,500',
        image: 'https://images.unsplash.com/photo-1622546758596-f1f06ba11f58?w=600&h=450&fit=crop',
        tag: 'Cultural Heart',
        tagColor: 'bg-orange-600 text-white'
    },
    {
        id: 10,
        city: 'Skardu',
        country: 'Pakistan',
        code: 'KDU',
        rating: 4.9,
        flightDuration: '1h 00m',
        flightType: 'Scenic Flight',
        priceAED: '450',
        pricePKR: '36,000',
        image: 'https://images.unsplash.com/photo-1546548970-71785318a17b?w=600&h=450&fit=crop',
        tag: 'Alpine Wonder',
        tagColor: 'bg-purple-600 text-white'
    }
];

const displayedDestinations = computed(() => {
    return activeTab.value === 'international' ? internationalDestinations : domesticDestinations;
});

const getPrice = (item) => {
    if (currency.value === 'PKR') {
        return `PKR ${item.pricePKR}`;
    }
    return `AED ${item.priceAED}`;
};
</script>

<template>
    <section class="w-full bg-slate-50/80 py-14 sm:py-18 px-4 sm:px-6 lg:px-8 border-b border-border/70">
        <div class="max-w-7xl mx-auto">
            
            <!-- Section Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-8 sm:mb-10 gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold uppercase tracking-wider mb-2">
                        <Compass class="w-3.5 h-3.5 text-orange-500" />
                        Top Traveled Routes
                    </div>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">
                        Trending <span class="text-blue-600">Destinations</span>
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-gray-600 font-normal">
                        Compare verified fares, non-stop flight routes, and special promotional rates this season.
                    </p>
                </div>

                <!-- Tabs -->
                <div class="flex items-center bg-gray-200/80 p-1 rounded self-start md:self-auto border border-gray-300">
                    <button 
                        @click="activeTab = 'international'"
                        :class="[
                            'px-4 py-1.5 rounded text-xs sm:text-sm font-bold transition-all cursor-pointer',
                            activeTab === 'international' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        International Hotspots
                    </button>
                    <button 
                        @click="activeTab = 'domestic'"
                        :class="[
                            'px-4 py-1.5 rounded text-xs sm:text-sm font-bold transition-all cursor-pointer',
                            activeTab === 'domestic' ? 'bg-white text-gray-900 shadow-xs' : 'text-gray-600 hover:text-gray-900'
                        ]"
                    >
                        Domestic Routes
                    </button>
                </div>
            </div>

            <!-- Destinations Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
                <div 
                    v-for="place in displayedDestinations" 
                    :key="place.id"
                    class="group relative bg-white rounded-lg overflow-hidden border border-gray-200 hover:border-primary/60 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col"
                >
                    <!-- Image Container -->
                    <div class="relative h-50 sm:h-54 w-full overflow-hidden bg-gray-100">
                        <img 
                            :src="place.image" 
                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                            :alt="place.city"
                            loading="lazy"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/25 to-transparent"></div>

                        <!-- Top Badges -->
                        <div class="absolute top-3 left-3 right-3 flex items-center justify-between">
                            <span 
                                class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded shadow-sm"
                                :class="place.tagColor"
                            >
                                {{ place.tag }}
                            </span>
                            <div class="flex items-center gap-1 bg-white text-gray-900 text-xs font-black px-2 py-0.5 rounded shadow-sm">
                                <Star class="w-3.5 h-3.5 fill-amber-400 text-amber-400" />
                                <span>{{ place.rating }}</span>
                            </div>
                        </div>

                        <!-- Bottom Location & Code -->
                        <div class="absolute bottom-3 left-3 right-3 flex items-end justify-between">
                            <div>
                                <h3 class="text-lg sm:text-xl font-black text-white leading-tight">
                                    {{ place.city }}
                                </h3>
                                <div class="flex items-center gap-1 text-xs text-gray-200 font-medium mt-0.5">
                                    <MapPin class="w-3.5 h-3.5 text-orange-400" />
                                    <span>{{ place.country }}</span>
                                </div>
                            </div>

                            <span class="text-xs font-mono font-bold bg-white/20 text-white px-2 py-0.5 rounded border border-white/30 uppercase">
                                {{ place.code }}
                            </span>
                        </div>
                    </div>

                    <!-- Details Body -->
                    <div class="p-4 sm:p-5 flex flex-col flex-1 justify-between bg-white">
                        
                        <!-- Flight Metrics Strip -->
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 mb-3 text-xs text-gray-600 font-medium">
                            <div class="flex items-center gap-1.5">
                                <Plane class="w-3.5 h-3.5 text-primary" />
                                <span>{{ place.flightType }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5 text-gray-400" />
                                <span>{{ place.flightDuration }}</span>
                            </div>
                        </div>

                        <!-- Price and CTA Row -->
                        <div class="flex items-center justify-between gap-4 pt-1">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">Fares starting from</span>
                                <span class="text-base sm:text-lg font-black text-gray-900">
                                    {{ getPrice(place) }}
                                </span>
                            </div>

                            <RouterLink 
                                to="/flight/search"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded bg-primary text-white hover:bg-primary-dark font-bold text-xs shadow-xs transition-colors"
                            >
                                <span>Search</span>
                                <ArrowRight class="w-3.5 h-3.5 transition-transform group-hover:translate-x-1" />
                            </RouterLink>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </section>
</template>