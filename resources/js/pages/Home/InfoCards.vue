<script setup>
import { ref } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const infoCards = [
    {
        id: 1,
        icon: 'https://cdn-icons-png.flaticon.com/512/2991/2991108.png', // Placeholder Report Icon
        text: 'Check out our Indian Travel Trends Report. Our trends report 2023-24 is out now. Read on for top travel insights.'
    },
    {
        id: 2,
        icon: 'https://cdn-icons-png.flaticon.com/512/2276/2276931.png', // Placeholder Food Icon
        text: 'Finding Indian Food just got easier! Use newly launched filters to find Indian food during International travel'
    },
    {
        id: 3,
        icon: 'https://cdn-icons-png.flaticon.com/512/2276/2276931.png', // Placeholder Food Icon
        text: 'Finding Indian Food just got easier! Use newly launched filters to find Indian food during International travel'
    },
    {
        id: 4,
        icon: 'https://cdn-icons-png.flaticon.com/512/2276/2276931.png', // Placeholder Food Icon
        text: 'Finding Indian Food just got easier! Use newly launched filters to find Indian food during International travel'
    },
    {
        id: 5,
        icon: 'https://cdn-icons-png.flaticon.com/512/2276/2276931.png', // Placeholder Food Icon
        text: 'Finding Indian Food just got easier! Use newly launched filters to find Indian food during International travel'
    },
    {
        id: 6,
        icon: 'https://cdn-icons-png.flaticon.com/512/2276/2276931.png', // Placeholder Food Icon
        text: 'Finding Indian Food just got easier! Use newly launched filters to find Indian food during International travel'
    },
    {
        id: 7,
        icon: 'https://cdn-icons-png.flaticon.com/512/723/723955.png', // Placeholder Plane Icon
        text: 'Planning to book an international flight?',
        link: 'Check Travel Guidelines',
        href: '#'
    }
];

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
    <div class="w-full bg-[#f2f2f2] py-8">
        <div class="max-w-7xl mx-auto px-4 relative group">
            
            <button 
                @click="scroll('left')" 
                class="absolute left-0 top-1/2 -translate-y-1/2 z-10 bg-white p-2 rounded-full shadow-md border border-gray-100 hover:bg-gray-50 transition-all opacity-0 group-hover:opacity-100 hidden md:block"
            >
                <ChevronLeft class="w-6 h-6 text-[#008cff] opacity-40" />
            </button>
            
            <button 
                @click="scroll('right')" 
                class="absolute right-0 top-1/2 -translate-y-1/2 z-10 bg-white p-2 rounded-full shadow-md border border-gray-100 hover:bg-gray-50 transition-all opacity-0 group-hover:opacity-100 hidden md:block"
            >
                <ChevronRight class="w-6 h-6 text-[#008cff]" />
            </button>

            <div 
                ref="scrollContainer"
                class="flex gap-4 overflow-x-auto no-scrollbar snap-x snap-mandatory px-4 md:px-12"
            >
                <div 
                    v-for="card in infoCards" 
                    :key="card.id"
                    class="min-w-[280px] md:min-w-[380px] bg-white rounded-lg p-5 shadow-sm border border-gray-100 snap-start flex items-center gap-5"
                >
                    <div class="w-16 h-16 shrink-0 flex items-center justify-center">
                        <img :src="card.icon" class="w-full h-full object-contain" alt="info icon">
                    </div>

                    <div class="flex-1">
                        <p class="text-[13px] leading-tight text-gray-700 font-medium">
                            {{ card.text }}
                        </p>
                        <a v-if="card.link" :href="card.href" class="text-[#008cff] text-[13px] font-bold block mt-1 hover:underline">
                            {{ card.link }}
                        </a>
                    </div>
                </div>
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