<script setup>
import { ref } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

// Data exactly as seen in the provided image
const collections = ref([
    {
        id: 1,
        title: 'Stays in & Around Delhi for a Weekend Getaway',
        count: 8,
        image: 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=400&h=600&fit=crop'
    },
    {
        id: 2,
        title: 'Stays in & Around Mumbai for a Weekend Getaway',
        count: 8,
        image: 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&h=600&fit=crop'
    },
    {
        id: 3,
        title: 'Stays in & Around Bangalore for a Weekend Getaway',
        count: 9,
        image: 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=400&h=600&fit=crop'
    },
    {
        id: 4,
        title: 'Beach Destinations',
        count: 11,
        image: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=400&h=600&fit=crop'
    },
    {
        id: 5,
        title: 'Weekend Getaways',
        count: 11,
        image: 'https://images.unsplash.com/photo-1476514525535-07fb3b4ae5f1?w=400&h=600&fit=crop'
    },
    {
        id: 6,
        title: 'Hill Stations',
        count: 11,
        image: 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=400&h=600&fit=crop'
    }
]);

const scrollContainer = ref(null);

const scroll = (direction) => {
    if (scrollContainer.value) {
        const scrollAmount = 350;
        scrollContainer.value.scrollBy({
            left: direction === 'left' ? -scrollAmount : scrollAmount,
            behavior: 'smooth'
        });
    }
};
</script>

<template>
    <section class="w-full bg-[#f2f2f2] py-12 px-4">
        <div class="max-w-7xl mx-auto bg-white rounded-xl p-6 md:p-10 shadow-sm">
            
            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl md:text-3xl font-black text-[#1a1a1a]">
                    Handpicked Collections for You
                </h2>
                
                <div class="flex items-center gap-2">
                    <button 
                        @click="scroll('left')" 
                        class="p-1 rounded-full border border-gray-200 text-[#008cff] hover:bg-gray-50 transition-colors"
                    >
                        <ChevronLeft class="w-5 h-5 opacity-40" />
                    </button>
                    <button 
                        @click="scroll('right')" 
                        class="p-1 rounded-full border border-gray-200 text-[#008cff] hover:bg-gray-50 transition-colors"
                    >
                        <ChevronRight class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <div 
                ref="scrollContainer"
                class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory pt-4 pb-2"
            >
                <div 
                    v-for="item in collections" 
                    :key="item.id"
                    class="min-w-[180px] md:min-w-[210px] snap-start"
                >
                    <div class="relative group cursor-pointer pt-3">
                        
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[70%] h-4 bg-gray-100 rounded-t-lg z-0 transition-transform group-hover:-translate-y-1"></div>
                        <div class="absolute top-1.5 left-1/2 -translate-x-1/2 w-[85%] h-4 bg-gray-200 rounded-t-lg z-10 transition-transform group-hover:-translate-y-0.5"></div>
                        
                        <div class="relative w-full h-[280px] rounded-lg overflow-hidden z-20 shadow-md">
                            <img 
                                :src="item.image" 
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" 
                                :alt="item.title"
                            >
                            
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>

                            <div class="absolute inset-0 p-4 flex flex-col justify-end">
                                <div class="bg-white text-black text-[10px] font-black px-2 py-0.5 rounded shadow-sm w-fit mb-2 uppercase tracking-tight">
                                    TOP {{ item.count }}
                                </div>
                                <h3 class="text-white text-[13px] md:text-sm font-bold leading-tight">
                                    {{ item.title }}
                                </h3>
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
</style>