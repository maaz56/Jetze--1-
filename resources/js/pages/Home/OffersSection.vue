<script setup>
import { ref, computed } from 'vue';
import { ChevronLeft, ChevronRight, MoveRight } from 'lucide-vue-next';

const categories = [
    'All Offers', 'Flights', 'Hotels', 'Holidays', 'Trains', 'Cabs', 'Bus', 'Forex', 'Activities'
];

const activeTab = ref('All Offers');

const offers = ref([
   // FLIGHTS
    {
        id: 1,
        category: 'Flights',
        subCategory: 'INTL FLIGHTS',
        title: 'Grab Up to 40% OFF* on International Flights',
        description: 'Valid on Stays, Flights, Buses, Cabs, Trains, Packages & More.',
        image: 'https://images.unsplash.com/photo-1436491865332-7a61a109c0f2?w=300&h=300&fit=crop',
        link: '#'
    },
    {
        id: 2,
        category: 'Flights',
        subCategory: 'DOM FLIGHTS',
        title: 'For Your Char Dham Journey: Up to 40% OFF*',
        description: 'Book domestic flights at the lowest prices for your pilgrimage.',
        image: 'https://images.unsplash.com/photo-1530789253388-582c481c54b0?w=300&h=300&fit=crop',
        link: '#'
    },
    // HOTELS
    {
        id: 3,
        category: 'Hotels',
        subCategory: 'DOM HOTELS',
        title: 'CRICKET SEASON SPECIAL: Up to 35% OFF*',
        description: 'Stay near stadiums or in nearby cities for live screenings.',
        image: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=300&h=300&fit=crop',
        link: '#'
    },
    {
        id: 4,
        category: 'Hotels',
        subCategory: 'INTL HOTELS',
        title: 'Luxury Stays in Dubai: Flat 25% Off',
        description: 'Experience world-class hospitality with our curated hotel list.',
        image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=300&h=300&fit=crop',
        link: '#'
    },
    // CABS
    {
        id: 5,
        category: 'Cabs',
        subCategory: 'OUTSTATION CABS',
        title: 'Explore Top Routes starting @ ₹10/km*',
        description: 'Safe, reliable journeys with professional drivers.',
        image: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=300&h=300&fit=crop',
        link: '#'
    },
    // HOLIDAYS
    {
        id: 6,
        category: 'Holidays',
        subCategory: 'SUMMER SPECIAL',
        title: 'European Escapade: Save up to $500',
        description: 'Full holiday packages including flights, hotels, and tours.',
        image: 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=300&h=300&fit=crop',
        link: '#'
    },
    // TRAINS
    {
        id: 7,
        category: 'Trains',
        subCategory: 'RAILWAYS',
        title: 'Zero Convenience Fee on First Train Booking',
        description: 'Use code: FIRSTTRAIN to avail this offer today.',
        image: 'https://images.unsplash.com/photo-1474487543417-981ceee1c818?w=300&h=300&fit=crop',
        link: '#'
    }
]);

const filteredOffers = computed(() => {
    if (activeTab.value === 'All Offers') return offers.value;
    return offers.value.filter(offer => offer.category === activeTab.value);
});

const scrollContainer = ref(null);

const scroll = (direction) => {
    if (scrollContainer.value) {
        const scrollAmount = 500;
        scrollContainer.value.scrollBy({
            left: direction === 'left' ? -scrollAmount : scrollAmount,
            behavior: 'smooth'
        });
    }
};
</script>

<template>
    <section class="w-full bg-gray-50 py-12 px-4">
        <div class="max-w-7xl mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 p-8 lg:p-10">
            
            <div class="flex flex-col lg:flex-row lg:items-center justify-between mb-10 gap-8">
                <div class="flex items-center gap-12">
                    <h2 class="text-4xl lg:text-5xl font-black text-[#1a1a1a]">Offers</h2>
                    
                    <nav class="flex items-center gap-8 overflow-x-auto no-scrollbar pb-1">
                        <button 
                            v-for="cat in categories" 
                            :key="cat"
                            @click="activeTab = cat"
                            :class="[
                                /* INCREASED: Tab Font (text-lg) */
                                'whitespace-nowrap text-lg font-bold transition-all relative pb-3',
                                activeTab === cat ? 'text-[#008cff]' : 'text-gray-500 hover:text-gray-800'
                            ]"
                        >
                            {{ cat }}
                            <div v-if="activeTab === cat" class="absolute bottom-0 left-0 w-full h-[4px] bg-[#008cff] rounded-full"></div>
                        </button>
                    </nav>
                </div>

                <div class="flex items-center justify-between lg:justify-end gap-8 border-t lg:border-t-0 pt-6 lg:pt-0">
                    <a href="#" class="flex items-center gap-2 text-[#008cff] font-black text-sm uppercase tracking-widest hover:underline">
                        View All <MoveRight class="w-5 h-5" />
                    </a>
                    
                    <div class="flex items-center gap-4">
                        <button @click="scroll('left')" class="group p-3 rounded-full border border-gray-200 hover:bg-[#008cff] hover:border-[#008cff] shadow-md transition-all">
                            <ChevronLeft class="w-6 h-6 text-[#008cff] group-hover:text-white" />
                        </button>
                        <button @click="scroll('right')" class="group p-3 rounded-full border border-gray-200 hover:bg-[#008cff] hover:border-[#008cff] shadow-md transition-all">
                            <ChevronRight class="w-6 h-6 text-[#008cff] group-hover:text-white" />
                        </button>
                    </div>
                </div>
            </div>

            <div 
                ref="scrollContainer"
                class="flex gap-6 overflow-x-auto no-scrollbar snap-x snap-mandatory pb-6"
            >
                <div 
                    v-for="offer in filteredOffers" 
                    :key="offer.id"
                    class="min-w-full md:min-w-[500px] snap-start"
                >
                    <div class="flex gap-6 p-6 border border-gray-100 rounded-2xl hover:shadow-xl transition-all duration-300 bg-white h-full">
                        <div class="w-40 h-40 shrink-0 rounded-xl overflow-hidden shadow-sm">
                            <img :src="offer.image" class="w-full h-full object-cover" :alt="offer.title">
                        </div>

                        <div class="flex flex-col flex-1">
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ offer.subCategory }}</span>
                                <span class="text-[10px] text-gray-400 font-bold">T&C'S APPLY</span>
                            </div>
                            
                            <h3 class="text-xl lg:text-2xl font-black text-gray-900 leading-tight mb-3 line-clamp-2">
                                {{ offer.title }}
                            </h3>
                            
                            <p class="text-sm text-gray-500 font-medium leading-relaxed line-clamp-2">
                                {{ offer.description }}
                            </p>

                            <div class="mt-auto flex justify-end">
                                <a :href="offer.link" class="text-[#008cff] text-base font-black uppercase tracking-wider hover:opacity-70 transition-opacity">
                                    Book Now
                                </a>
                            </div>
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

/* Line clamping for larger text */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>