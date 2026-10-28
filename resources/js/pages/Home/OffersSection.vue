<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';
import { ChevronLeft, ChevronRight, Plane, ExternalLink } from 'lucide-vue-next';
import { usePopularRoutes } from '@/components/popular-routes/usePopularRoutes';
import { formatAmount } from '@/lib/utils';

const categories = ['All Offers', 'Flights', 'Hotels', 'Holidays'];
const activeTab = ref('All Offers');
const currentPage = ref(0);
const itemsPerPage = 4;
let autoplayTimer = null;

// Dynamic Popular Routes integration
const { routes, isLoading: routesLoading } = usePopularRoutes();

// Curated Hotel and Holiday Offers
const nonFlightOffers = ref([
    {
        id: 'hotel-1',
        category: 'HOTELS',
        title: 'Instant savings: Up to 25% OFF* on hotels',
        description: 'Grab this great value deal on your next luxury stay.',
        image: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: false,
    },
    {
        id: 'hotel-2',
        category: 'HOTELS',
        title: 'LUXURY RESORT ESCAPES: FLAT 20% OFF',
        description: 'Enjoy complimentary breakfast & spa credits on top stays.',
        image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: true,
    },
    {
        id: 'holiday-1',
        category: 'HOLIDAYS',
        title: 'Baku & Istanbul 7-Day All-Inclusive Vacation',
        description: 'Guided tours, 4-star boutique hotels, and seamless transfers.',
        image: 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=500&h=500&fit=crop',
        link: '/holidays',
        hasRedAccent: true,
    },
    {
        id: 'holiday-2',
        category: 'HOLIDAYS',
        title: 'Dubai Desert Safari & Luxury City Tour',
        description: 'Experience 5-day curated getaway package with private guide.',
        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=500&h=500&fit=crop',
        link: '/holidays',
        hasRedAccent: false,
    },
    {
        id: 'hotel-3',
        category: 'HOTELS',
        title: 'Beachfront Villa Discounts Up to 30%',
        description: 'Unwind at luxury beachfront properties worldwide.',
        image: 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: true,
    },
    {
        id: 'hotel-4',
        category: 'HOTELS',
        title: 'City Center Boutique Hotels Special',
        description: 'Stay right in the center with exclusive member pricing.',
        image: 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: false,
    },
]);

// Fallback Flight Offers if routes not yet loaded
const fallbackFlightOffers = [
    {
        id: 'fallback-fl-1',
        category: 'FLIGHTS',
        title: 'Savings Alert: Special International Fares',
        description: 'Book international flights with Jetze and enjoy exclusive discounts.',
        image: 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
    {
        id: 'fallback-fl-2',
        category: 'FLIGHTS',
        title: 'Domestic Flight Offers: Save Big Today',
        description: 'Lowest guaranteed fares on domestic and regional routes.',
        image: 'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
];

// Map dynamic popular routes into Flight Offers
const popularRouteFlightOffers = computed(() => {
    if (!routes.value || routes.value.length === 0) {
        return fallbackFlightOffers;
    }

    return routes.value.map((r, index) => {
        const origin = r.from?.city || r.from?.iata || 'Origin';
        const dest = r.destination_name || r.to?.city || r.to?.iata || 'Destination';
        const journey = (r.journey_type || '').toLowerCase() === 'round' ? 'Round trip' : 'One way';
        const travelClass = r.travel_class ? (r.travel_class.charAt(0).toUpperCase() + r.travel_class.slice(1)) : 'Economy';
        const airlineName = r.airline?.name ? ` · ${r.airline.name}` : '';

        let desc = '';
        if (r.price && Number(r.price.amount) > 0) {
            desc = `Starting from ${r.price.currency} ${formatAmount(r.price.amount, r.price.currency)} · ${journey} (${travelClass})${airlineName}`;
        } else {
            desc = `Check live fares · ${journey} (${travelClass})${airlineName}`;
        }

        const queryParams = new URLSearchParams({
            airline: 'AT',
            origin: r.search?.origin || r.from?.iata || '',
            destination: r.search?.destination || r.to?.iata || '',
            departure_date: r.search?.departure_date || '',
            return_date: r.search?.return_date || '',
            flightType: r.search?.flight_type || ((r.journey_type || '').toLowerCase() === 'round' ? 'return' : 'one-way'),
            cabin_class: r.search?.cabin_class || 'Y',
            adults: '1',
            children: '0',
            infants: '0',
        }).toString();

        return {
            id: `route-${r.id}`,
            category: 'FLIGHTS',
            title: `${origin} to ${dest} (${r.from?.iata || ''} ✈ ${r.to?.iata || ''})`,
            description: desc,
            image: r.image?.url || 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=500&h=500&fit=crop',
            link: `/flight/search?${queryParams}`,
            routeLink: r.slug ? `/flights/${r.slug}` : `/popular-routes/${r.id}`,
            hasRedAccent: index % 2 === 1,
            price: r.price && Number(r.price.amount) > 0 ? r.price : null,
            isPopularRoute: true,
            airlineName: r.airline?.name,
        };
    });
});

// All Offers: Combines popular flight routes with hotel and holiday offers
const allOffersCombined = computed(() => {
    const flightOffers = popularRouteFlightOffers.value;
    const others = nonFlightOffers.value;

    const merged = [];
    const maxLen = Math.max(flightOffers.length, others.length);

    // Interleave flights and other offers for a balanced presentation
    for (let i = 0; i < maxLen; i++) {
        if (i < flightOffers.length) {
            merged.push(flightOffers[i]);
        }
        if (i < others.length) {
            merged.push(others[i]);
        }
    }

    return merged;
});

const filteredOffers = computed(() => {
    if (activeTab.value === 'All Offers') {
        return allOffersCombined.value;
    }
    if (activeTab.value === 'Flights') {
        return popularRouteFlightOffers.value;
    }
    return nonFlightOffers.value.filter(
        offer => offer.category.toLowerCase() === activeTab.value.toLowerCase()
    );
});

const totalPages = computed(() => {
    return Math.ceil(filteredOffers.value.length / itemsPerPage);
});

const visibleOffers = computed(() => {
    const start = currentPage.value * itemsPerPage;
    return filteredOffers.value.slice(start, start + itemsPerPage);
});

const selectTab = (cat) => {
    activeTab.value = cat;
    currentPage.value = 0;
    resetAutoplay();
};

const nextSlide = () => {
    if (totalPages.value <= 1) return;
    currentPage.value = (currentPage.value + 1) % totalPages.value;
};

const prevSlide = () => {
    if (totalPages.value <= 1) return;
    currentPage.value = (currentPage.value - 1 + totalPages.value) % totalPages.value;
};

const goToPage = (index) => {
    currentPage.value = index;
    resetAutoplay();
};

const startAutoplay = () => {
    stopAutoplay();
    if (totalPages.value > 1) {
        autoplayTimer = setInterval(() => {
            nextSlide();
        }, 6000);
    }
};

const stopAutoplay = () => {
    if (autoplayTimer) {
        clearInterval(autoplayTimer);
        autoplayTimer = null;
    }
};

const resetAutoplay = () => {
    stopAutoplay();
    startAutoplay();
};

let touchStartX = 0;
let touchStartY = 0;

const handleTouchStart = (e) => {
    if (e.touches && e.touches.length === 1) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }
};

const handleTouchEnd = (e) => {
    if (!e.changedTouches || !e.changedTouches.length) return;
    const touchEndX = e.changedTouches[0].clientX;
    const touchEndY = e.changedTouches[0].clientY;
    const diffX = touchEndX - touchStartX;
    const diffY = touchEndY - touchStartY;
    
    // Only trigger horizontal swipe if horizontal movement is dominant and > 40px
    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 40) {
        if (diffX < 0) {
            nextSlide();
        } else {
            prevSlide();
        }
    }
};

onMounted(() => {
    startAutoplay();
});

onUnmounted(() => {
    stopAutoplay();
});
</script>

<template>
    <section class="w-full py-4 sm:py-6 px-3 sm:px-6 lg:px-8">
        <div 
            class="max-w-7xl mx-auto bg-white rounded-2xl border border-gray-200/80 p-3.5 sm:p-7 shadow-xs"
            @mouseenter="stopAutoplay"
            @mouseleave="startAutoplay"
            @touchstart="handleTouchStart"
            @touchend="handleTouchEnd"
        >
            
            <!-- Section Header: Title & Navigation Controls -->
            <div class="flex items-center justify-between mb-3 sm:mb-4">
                <div>
                    <h2 class="text-xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">
                        Jetze Offers
                    </h2>
                    <p class="text-[11px] sm:text-sm text-gray-500 mt-0.5">Curated travel discounts & limited-time deals</p>
                </div>

                <!-- Carousel Navigation Arrows -->
                <div v-if="totalPages > 1" class="flex items-center gap-1.5 sm:gap-2">
                    <button 
                        @click="prevSlide" 
                        class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary shadow-2xs transition-colors cursor-pointer active:scale-95"
                        title="Previous Offers"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button 
                        @click="nextSlide" 
                        class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary shadow-2xs transition-colors cursor-pointer active:scale-95"
                        title="Next Offers"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Scrollable Category Tabs Bar -->
            <div class="flex items-center gap-2 sm:gap-6 border-b border-gray-200 pb-3 mb-4 sm:mb-6 overflow-x-auto no-scrollbar">
                <button 
                    v-for="cat in categories" 
                    :key="cat"
                    @click="selectTab(cat)"
                    :class="[
                        'px-3 py-1.5 sm:px-0 sm:pb-2 sm:pt-0 rounded-full sm:rounded-none transition-all cursor-pointer font-bold text-xs sm:text-sm whitespace-nowrap flex items-center gap-1.5 shrink-0',
                        activeTab === cat 
                            ? 'bg-primary text-white sm:bg-transparent sm:text-primary sm:border-b-2 sm:border-primary sm:-mb-px shadow-xs sm:shadow-none' 
                            : 'bg-slate-100 text-gray-600 hover:bg-slate-200 sm:bg-transparent sm:text-gray-500 sm:hover:text-gray-800 sm:border-b-2 sm:border-transparent sm:-mb-px'
                    ]"
                >
                    <span>{{ cat }}</span>
                    <span 
                        v-if="cat === 'Flights' && routes.length > 0" 
                        :class="[
                            'text-[10px] px-1.5 py-0.5 rounded-full font-extrabold',
                            activeTab === cat 
                                ? 'bg-white/20 text-white sm:bg-primary/10 sm:text-primary' 
                                : 'bg-slate-200 text-slate-700 sm:bg-primary/10 sm:text-primary'
                        ]"
                    >
                        {{ routes.length }}
                    </span>
                </button>
            </div>

            <!-- Offers Carousel Grid (2 rows x 2 cols = 4 items visible) -->
            <Transition name="fade-slide" mode="out-in">
                <div 
                    :key="`${activeTab}-${currentPage}`"
                    class="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-5 lg:gap-6 content-start min-h-[290px] sm:min-h-[380px]"
                >
                    <div 
                        v-for="offer in visibleOffers" 
                        :key="offer.id"
                        class="bg-white rounded-xl border border-gray-200/90 p-3 sm:p-4 shadow-xs hover:shadow-md transition-all duration-200 flex gap-3 sm:gap-4 items-stretch group relative"
                    >
                        <!-- Left Image Thumbnail -->
                        <div class="w-24 h-24 xs:w-28 xs:h-28 sm:w-36 sm:h-36 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100 relative">
                            <img 
                                :src="offer.image" 
                                :alt="offer.title"
                                class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                            <div 
                                v-if="offer.price" 
                                class="absolute bottom-1 left-1 right-1 bg-black/80 backdrop-blur-xs text-white rounded px-1 py-0.5 text-[9px] sm:text-[10px] font-bold text-center truncate"
                            >
                                from {{ offer.price.currency }} {{ formatAmount(offer.price.amount, offer.price.currency) }}
                            </div>
                        </div>

                        <!-- Right Content -->
                        <div class="flex-1 flex flex-col justify-between min-w-0 py-0.5">
                            <div>
                                <!-- Header Row: Category & Details Link / T&C -->
                                <div class="flex items-center justify-between gap-2 text-xs mb-1">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider text-[10px] sm:text-[11px] flex items-center gap-1">
                                        <Plane v-if="offer.category === 'FLIGHTS'" class="w-3 h-3 text-primary" />
                                        {{ offer.category }}
                                    </span>
                                    <RouterLink 
                                        v-if="offer.routeLink"
                                        :to="offer.routeLink"
                                        class="text-[10px] sm:text-[11px] font-semibold text-gray-400 hover:text-primary transition-colors flex items-center gap-0.5"
                                        title="View route guide"
                                    >
                                        <span>Route Guide</span>
                                        <ExternalLink class="w-3 h-3" />
                                    </RouterLink>
                                    <span v-else class="text-[9px] sm:text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                        T&C'S APPLY
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3 class="text-xs sm:text-base font-extrabold text-gray-900 leading-snug line-clamp-2">
                                    {{ offer.title }}
                                </h3>

                                <!-- Red Accent Line -->
                                <div v-if="offer.hasRedAccent" class="w-6 h-[2px] bg-rose-500 my-1 sm:my-2"></div>

                                <!-- Description / Fares info -->
                                <p class="text-[11px] sm:text-sm text-gray-500 leading-normal line-clamp-2 mt-0.5 sm:mt-1">
                                    {{ offer.description }}
                                </p>
                            </div>

                            <!-- CTA Link using Primary Color -->
                            <div class="flex items-center justify-between pt-1.5 sm:pt-2">
                                <RouterLink 
                                    v-if="offer.routeLink" 
                                    :to="offer.routeLink"
                                    class="text-[11px] sm:text-xs text-gray-500 hover:text-primary font-medium"
                                >
                                    Details
                                </RouterLink>
                                <span v-else></span>

                                <RouterLink 
                                    :to="offer.link" 
                                    class="text-primary hover:opacity-85 font-black text-xs sm:text-sm uppercase tracking-wider hover:underline flex items-center gap-1 active:translate-x-0.5 transition-transform"
                                >
                                    <span>BOOK NOW</span>
                                    <span class="text-xs">→</span>
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Carousel Pagination Dots -->
            <div class="flex items-center justify-center gap-2 mt-4 sm:mt-6 h-4">
                <template v-if="totalPages > 1">
                    <button 
                        v-for="pageIndex in totalPages" 
                        :key="pageIndex"
                        @click="goToPage(pageIndex - 1)"
                        :class="[
                            'h-2 rounded-full transition-all cursor-pointer',
                            currentPage === (pageIndex - 1) 
                                ? 'w-6 bg-primary' 
                                : 'w-2 bg-gray-300 hover:bg-gray-400'
                        ]"
                        :title="`Go to page ${pageIndex}`"
                    ></button>
                </template>
            </div>

        </div>
    </section>
</template>

<style scoped>
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateX(12px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>