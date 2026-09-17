<script setup>
import { ref, computed } from 'vue';
import { 
    ChevronLeft, 
    ChevronRight, 
    ArrowRight, 
    Copy, 
    Check, 
    Tag, 
    Sparkles, 
    Clock,
    Percent
} from 'lucide-vue-next';
import { RouterLink } from 'vue-router';
import { toast } from 'vue3-toastify';

const categories = [
    'All Offers', 
    'Flights', 
    'Hotels', 
    'Umrah', 
    'Holidays',
    'Visas'
];

const activeTab = ref('All Offers');
const copiedCode = ref(null);

const offers = ref([
    {
        id: 1,
        category: 'Flights',
        subCategory: 'Global Routes',
        tag: 'Flash Sale',
        tagColor: 'bg-rose-500 text-white',
        title: 'Save Up to 25% on International Airfare',
        description: 'Exclusive discounted fares across Emirates, Qatar Airways, Turkish, and Saudia for early birds.',
        code: 'FLYJETZE26',
        discount: '25% OFF',
        image: 'https://images.unsplash.com/photo-1436491865332-7a61a109c0f2?w=600&h=450&fit=crop',
        link: '/flight/search',
        expiry: 'Expires in 4 days'
    },
    {
        id: 2,
        category: 'Umrah',
        subCategory: 'Pilgrimage Special',
        tag: 'Spiritual Journey',
        tagColor: 'bg-emerald-600 text-white',
        title: 'Complete Umrah Packages with 5★ Haram Hotels',
        description: 'Full board packages including visa processing, luxury private transfers, and daily Ziyarat tours.',
        code: 'UMRAHVIP',
        discount: 'SPECIAL FARE',
        image: 'https://images.unsplash.com/photo-1591604129939-f1efa4d9f7fa?w=600&h=450&fit=crop',
        link: '/umra-packages',
        expiry: 'Limited Slots Available'
    },
    {
        id: 3,
        category: 'Hotels',
        subCategory: 'Luxury Stays',
        tag: 'Members Only',
        tagColor: 'bg-indigo-600 text-white',
        title: 'Dubai & Maldives Luxury Beach Resort Escapes',
        description: 'Book 3 nights and receive complimentary breakfast, airport lounge access, and spa credits.',
        code: 'LUXESTAY',
        discount: '30% OFF',
        image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=600&h=450&fit=crop',
        link: '/hotel/search',
        expiry: 'Valid this month'
    },
    {
        id: 4,
        category: 'Holidays',
        subCategory: 'Curated Escapes',
        tag: 'Best Seller',
        tagColor: 'bg-orange-500 text-white',
        title: 'Baku & Istanbul 7-Day All-Inclusive Vacation',
        description: 'Experience guided historical tours, 4-star boutique hotels, and seamless local transport.',
        code: 'ESCAPE2026',
        discount: 'FLAT $150 OFF',
        image: 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=600&h=450&fit=crop',
        link: '/holidays',
        expiry: 'Ends Sunday'
    },
    {
        id: 5,
        category: 'Visas',
        subCategory: 'Consular Services',
        tag: 'Fast Track',
        tagColor: 'bg-purple-600 text-white',
        title: 'Express Tourist Visa Processing for UAE & Schengen',
        description: 'Dedicated visa specialists reviewing your documentation to ensure swift turnaround.',
        code: 'VISAPRO',
        discount: 'ZERO FEE ON ASSIST',
        image: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=600&h=450&fit=crop',
        link: '/visa',
        expiry: 'Instant assistance'
    },
    {
        id: 6,
        category: 'Flights',
        subCategory: 'Domestic Routes',
        tag: 'Popular',
        tagColor: 'bg-blue-600 text-white',
        title: 'Domestic Flights: Lahore, Karachi, Islamabad & Peshawar',
        description: 'Zero convenience fees and instant confirmation on all major domestic carriers.',
        code: 'DOMESTICFLY',
        discount: 'LOWEST FARE',
        image: 'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=600&h=450&fit=crop',
        link: '/flight/search',
        expiry: 'Ongoing promotion'
    }
]);

const filteredOffers = computed(() => {
    if (activeTab.value === 'All Offers') return offers.value;
    return offers.value.filter(offer => offer.category === activeTab.value);
});

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

const copyCode = (code) => {
    navigator.clipboard.writeText(code);
    copiedCode.value = code;
    toast.success(`Coupon code ${code} copied!`);
    setTimeout(() => {
        if (copiedCode.value === code) {
            copiedCode.value = null;
        }
    }, 2500);
};
</script>

<template>
    <section class="w-full bg-slate-50/80 py-14 sm:py-18 px-4 sm:px-6 lg:px-8 border-b border-border/70">
        <div class="max-w-7xl mx-auto">
            
            <!-- Section Header -->
            <div class="flex flex-col lg:flex-row lg:items-end justify-between mb-8 sm:mb-10 gap-6">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold uppercase tracking-wider mb-2">
                        <Sparkles class="w-3.5 h-3.5 text-orange-500" />
                        Exclusive Deals
                    </div>
                    <h2 class="text-2xl sm:text-3xl lg:text-4xl font-black text-gray-900 tracking-tight">
                        Offers & <span class="text-blue-600">Seasonal Savings</span>
                    </h2>
                    <p class="mt-2 text-sm sm:text-base text-gray-600 font-normal">
                        Unlock handpicked discounts, promo codes, and special member fares across our network.
                    </p>
                </div>

                <!-- Navigation Arrows -->
                <div class="flex items-center gap-2 self-end lg:self-auto">
                    <button 
                        @click="scroll('left')" 
                        class="w-10 h-10 rounded border border-gray-300 bg-white flex items-center justify-center text-gray-700 hover:bg-primary hover:text-white hover:border-primary shadow-xs transition-colors cursor-pointer"
                        title="Scroll Left"
                    >
                        <ChevronLeft class="w-5 h-5" />
                    </button>
                    <button 
                        @click="scroll('right')" 
                        class="w-10 h-10 rounded border border-gray-300 bg-white flex items-center justify-center text-gray-700 hover:bg-primary hover:text-white hover:border-primary shadow-xs transition-colors cursor-pointer"
                        title="Scroll Right"
                    >
                        <ChevronRight class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex items-center gap-2 sm:gap-2.5 overflow-x-auto no-scrollbar pb-3 mb-6 border-b border-gray-200/80">
                <button 
                    v-for="cat in categories" 
                    :key="cat"
                    @click="activeTab = cat"
                    :class="[
                        'px-4 py-2 rounded text-xs sm:text-sm font-bold whitespace-nowrap transition-all cursor-pointer border',
                        activeTab === cat 
                            ? 'bg-primary text-white border-primary shadow-xs' 
                            : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100 hover:text-gray-900'
                    ]"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Offers Scrollable Grid -->
            <div 
                ref="scrollContainer"
                class="flex gap-5 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-4 pt-1"
            >
                <div 
                    v-for="offer in filteredOffers" 
                    :key="offer.id"
                    class="min-w-[290px] sm:min-w-[390px] lg:min-w-[410px] snap-start"
                >
                    <div class="group relative bg-white rounded-lg border border-gray-200 hover:border-primary/60 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col h-full overflow-hidden">
                        
                        <!-- Card Image Banner -->
                        <div class="relative h-44 sm:h-48 w-full overflow-hidden bg-gray-100">
                            <img 
                                :src="offer.image" 
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" 
                                :alt="offer.title"
                                loading="lazy"
                            >
                            <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>

                            <!-- Top Badges -->
                            <div class="absolute top-3 left-3 right-3 flex items-center justify-between">
                                <span 
                                    class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-1 rounded shadow-sm"
                                    :class="offer.tagColor"
                                >
                                    {{ offer.tag }}
                                </span>

                                <span class="bg-white text-gray-900 text-xs font-black px-2 py-0.5 rounded shadow-sm">
                                    {{ offer.discount }}
                                </span>
                            </div>

                            <!-- Bottom Title inside Image Banner -->
                            <div class="absolute bottom-3 left-3 right-3">
                                <span class="text-[10px] font-bold text-orange-400 uppercase tracking-wider block">
                                    {{ offer.subCategory }}
                                </span>
                                <h3 class="text-sm sm:text-base font-bold text-white leading-snug line-clamp-1 group-hover:text-blue-300 transition-colors">
                                    {{ offer.title }}
                                </h3>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 sm:p-5 flex flex-col flex-1 justify-between bg-white">
                            <p class="text-xs text-gray-600 line-clamp-2 leading-relaxed mb-4">
                                {{ offer.description }}
                            </p>

                            <!-- Promo Code & Expiry Strip -->
                            <div class="flex items-center justify-between gap-3 pt-3 border-t border-gray-100 mb-4">
                                <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium">
                                    <Clock class="w-3.5 h-3.5 text-gray-400" />
                                    <span>{{ offer.expiry }}</span>
                                </div>

                                <!-- Promo Code Pill -->
                                <button 
                                    @click="copyCode(offer.code)"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded border border-dashed border-primary/60 bg-blue-50/70 hover:bg-blue-100/70 text-primary text-xs font-bold tracking-wider transition-colors cursor-pointer group/code"
                                    title="Click to copy code"
                                >
                                    <Tag class="w-3 h-3 text-orange-500" />
                                    <span>{{ offer.code }}</span>
                                    <component 
                                        :is="copiedCode === offer.code ? Check : Copy" 
                                        class="w-3 h-3 text-primary group-hover/code:scale-110 transition-transform" 
                                    />
                                </button>
                            </div>

                            <!-- Action CTA -->
                            <RouterLink 
                                :to="offer.link" 
                                class="w-full py-2.5 px-4 rounded bg-primary text-white hover:bg-primary-dark font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-xs transition-colors"
                            >
                                <span>Claim Offer & Book</span>
                                <ArrowRight class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                            </RouterLink>

                        </div>

                    </div>
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