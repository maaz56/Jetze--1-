<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const categories = ['All Offers', 'Flights', 'Hotels', 'Holidays'];
const activeTab = ref('All Offers');
const currentPage = ref(0);
const itemsPerPage = 4;
let autoplayTimer = null;

const offers = ref([
    {
        id: 1,
        category: 'HOTELS',
        title: 'Instant savings: Up to 25% OFF* on hotels',
        description: 'Grab this great value deal on your next trip.',
        image: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: false,
    },
    {
        id: 2,
        category: 'FLIGHTS',
        title: 'Savings Alert: Get FLAT 8% OFF*',
        description: 'when you book your International Flights with us.',
        image: 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
    {
        id: 3,
        category: 'FLIGHTS',
        title: 'BIG SAVINGS ON YOUR FIRST BOOKING',
        description: 'Up to 35% OFF* on Flights & Hotels',
        image: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
    {
        id: 4,
        category: 'HOTELS',
        title: 'LUXURY RESORT ESCAPES: FLAT 20% OFF',
        description: 'Enjoy complimentary breakfast & spa credits on top stays.',
        image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: true,
    },
    {
        id: 5,
        category: 'HOLIDAYS',
        title: 'Baku & Istanbul 7-Day All-Inclusive Vacation',
        description: 'Guided tours, 4-star boutique hotels, and seamless transfers.',
        image: 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=500&h=500&fit=crop',
        link: '/holidays',
        hasRedAccent: true,
    },
    {
        id: 6,
        category: 'HOLIDAYS',
        title: 'Dubai Desert Safari & Luxury City Tour',
        description: 'Experience 5-day curated getaway package with private guide.',
        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=500&h=500&fit=crop',
        link: '/holidays',
        hasRedAccent: false,
    },
    {
        id: 7,
        category: 'FLIGHTS',
        title: 'Special Fares on Middle East Routes',
        description: 'Discounted flights across Saudia, Emirates & Qatar Airways.',
        image: 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: false,
    },
    {
        id: 8,
        category: 'HOTELS',
        title: 'Beachfront Villa Discounts Up to 30%',
        description: 'Unwind at luxury beachfront properties worldwide.',
        image: 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: true,
    },
    {
        id: 9,
        category: 'FLIGHTS',
        title: 'Domestic Flight Offers: Save Big Today',
        description: 'Lowest guaranteed fares on domestic routes.',
        image: 'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
    {
        id: 10,
        category: 'HOTELS',
        title: 'City Center Boutique Hotels Special',
        description: 'Stay right in the center with exclusive member pricing.',
        image: 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: false,
    },
     {
        id: 11,
        category: 'FLIGHTS',
        title: 'Domestic Flight Offers: Save Big Today',
        description: 'Lowest guaranteed fares on domestic routes.',
        image: 'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=500&h=500&fit=crop',
        link: '/flight/search',
        hasRedAccent: true,
    },
    {
        id: 12,
        category: 'HOTELS',
        title: 'City Center Boutique Hotels Special',
        description: 'Stay right in the center with exclusive member pricing.',
        image: 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=500&h=500&fit=crop',
        link: '/hotel/search',
        hasRedAccent: false,
    }

]);

const filteredOffers = computed(() => {
    if (activeTab.value === 'All Offers') return offers.value;
    return offers.value.filter(offer => offer.category.toLowerCase() === activeTab.value.toLowerCase());
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
        }, 5000);
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

onMounted(() => {
    startAutoplay();
});

onUnmounted(() => {
    stopAutoplay();
});
</script>

<template>
    <section class="w-full py-6 px-4 sm:px-6 lg:px-8">
        <div 
            class="max-w-7xl mx-auto bg-white rounded-2xl border border-gray-200/80 p-5 sm:p-7 shadow-xs"
            @mouseenter="stopAutoplay"
            @mouseleave="startAutoplay"
        >
            
            <!-- Section Header & Tabs & Controls -->
            <div class="flex items-center justify-between border-b border-gray-200 mb-6 pb-0">
                <div class="flex items-center gap-6 sm:gap-10 overflow-x-auto no-scrollbar">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight whitespace-nowrap pb-3">
                        Jetze Offers
                    </h2>

                    <div class="flex items-center gap-6 text-sm sm:text-base font-semibold whitespace-nowrap">
                        <button 
                            v-for="cat in categories" 
                            :key="cat"
                            @click="selectTab(cat)"
                            :class="[
                                'pb-3 transition-all cursor-pointer font-bold border-b-2 -mb-px',
                                activeTab === cat 
                                    ? 'text-primary border-primary' 
                                    : 'text-gray-500 border-transparent hover:text-gray-800'
                            ]"
                        >
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <!-- Carousel Navigation Arrows -->
                <div v-if="totalPages > 1" class="flex items-center gap-2 pb-3 pl-4 shrink-0">
                    <button 
                        @click="prevSlide" 
                        class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary shadow-2xs transition-colors cursor-pointer"
                        title="Previous Offers"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                    <button 
                        @click="nextSlide" 
                        class="w-8 h-8 rounded-lg border border-gray-200 bg-white flex items-center justify-center text-gray-600 hover:bg-primary hover:text-white hover:border-primary shadow-2xs transition-colors cursor-pointer"
                        title="Next Offers"
                    >
                        <ChevronRight class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Offers Carousel Grid (2 rows x 2 cols = 4 items visible, with min-height to prevent layout jump) -->
            <Transition name="fade-slide" mode="out-in">
                <div 
                    :key="`${activeTab}-${currentPage}`"
                    class="grid grid-cols-1 md:grid-cols-2 gap-5 lg:gap-6 content-start min-h-[360px] sm:min-h-[380px]"
                >
                    <div 
                        v-for="offer in visibleOffers" 
                        :key="offer.id"
                        class="bg-white rounded-xl border border-gray-200/90 p-3.5 sm:p-4 shadow-xs hover:shadow-md transition-all duration-200 flex gap-4 items-stretch group"
                    >
                        <!-- Left Image Thumbnail -->
                        <div class="w-28 h-28 sm:w-36 sm:h-36 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                            <img 
                                :src="offer.image" 
                                :alt="offer.title"
                                class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                        </div>

                        <!-- Right Content -->
                        <div class="flex-1 flex flex-col justify-between min-w-0 py-0.5">
                            <div>
                                <!-- Header Row: Category & T&C -->
                                <div class="flex items-center justify-between gap-2 text-xs mb-1">
                                    <span class="font-bold text-gray-500 uppercase tracking-wider text-[11px]">
                                        {{ offer.category }}
                                    </span>
                                    <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">
                                        T&C'S APPLY
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3 class="text-sm sm:text-base font-extrabold text-gray-900 leading-snug line-clamp-2">
                                    {{ offer.title }}
                                </h3>

                                <!-- Red Accent Line -->
                                <div v-if="offer.hasRedAccent" class="w-7 h-[2px] bg-rose-500 my-2"></div>

                                <!-- Description -->
                                <p class="text-xs sm:text-sm text-gray-500 leading-normal line-clamp-2 mt-1">
                                    {{ offer.description }}
                                </p>
                            </div>

                            <!-- CTA Link using Primary Color -->
                            <div class="flex justify-end pt-2">
                                <RouterLink 
                                    :to="offer.link" 
                                    class="text-primary hover:opacity-85 font-black text-xs sm:text-sm uppercase tracking-wider hover:underline"
                                >
                                    BOOK NOW
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Carousel Pagination Dots (Fixed height area to prevent layout jump) -->
            <div class="flex items-center justify-center gap-2 mt-6 h-4">
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