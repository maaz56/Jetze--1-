<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { 
    ShieldCheck, 
    Zap, 
    Plane, 
    Headphones, 
    BadgePercent, 
    CreditCard
} from 'lucide-vue-next';

const items = [
    {
        id: 1,
        title: '100% SECURED PAYMENTS',
        subtitle: 'Visa, Mastercard & Encrypted Gateway',
        icon: CreditCard,
        bgGradient: 'from-blue-500/15 to-indigo-500/15 text-primary border-blue-200/80',
        badge: 'Guaranteed'
    },
    {
        id: 2,
        title: 'INSTANT E-TICKETING',
        subtitle: 'Fast PNR confirmation & E-tickets',
        icon: Zap,
        bgGradient: 'from-amber-500/15 to-orange-500/15 text-orange-600 border-amber-200/80',
        badge: 'Instant'
    },
    {
        id: 3,
        title: '450+ AIRLINE PARTNERS',
        subtitle: 'Direct global wholesale rates & cheap fares',
        icon: Plane,
        bgGradient: 'from-purple-500/15 to-violet-500/15 text-purple-600 border-purple-200/80',
        badge: 'Wholesale'
    },
    {
        id: 4,
        title: 'JETZE EXCLUSIVE HOTELS',
        subtitle: 'Avail lowest price guarantee on top stays',
        icon: BadgePercent,
        bgGradient: 'from-rose-500/15 to-pink-500/15 text-rose-600 border-rose-200/80',
        badge: 'Exclusive'
    },
    {
        id: 5,
        title: '24/7 DEDICATED SUPPORT',
        subtitle: 'Real travel agents always on standby',
        icon: Headphones,
        bgGradient: 'from-emerald-500/15 to-teal-500/15 text-emerald-600 border-emerald-200/80',
        badge: '24/7 Live'
    }
];

const currentPage = ref(0);
const itemsPerPage = 3;
let timer = null;

const visibleItems = computed(() => {
    const list = [];
    for (let i = 0; i < itemsPerPage; i++) {
        const index = (currentPage.value + i) % items.length;
        list.push(items[index]);
    }
    return list;
});

const nextSlide = () => {
    currentPage.value = (currentPage.value + 1) % items.length;
};

const startAutoplay = () => {
    stopAutoplay();
    timer = setInterval(() => {
        nextSlide();
    }, 3500);
};

const stopAutoplay = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
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
    <div class="w-full py-4 px-4 sm:px-6 lg:px-8">
        <div 
            class="max-w-7xl mx-auto relative overflow-hidden"
            @mouseenter="stopAutoplay"
            @mouseleave="startAutoplay"
        >
            <!-- Auto Carousel Container (Horizontal sliding individual cards) -->
            <Transition name="horizontal-slide" mode="out-in">
                <div 
                    :key="currentPage"
                    class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 items-stretch"
                >
                    <div 
                        v-for="item in visibleItems" 
                        :key="item.id"
                        class="bg-white rounded-xl border border-gray-200/90 shadow-2xs hover:shadow-md transition-all duration-300 p-4 sm:p-5 flex items-center gap-4 group"
                    >
                        <!-- Icon Badge -->
                        <div 
                            class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-gradient-to-br border flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform duration-300"
                            :class="item.bgGradient"
                        >
                            <component :is="item.icon" class="w-6 h-6 sm:w-7 sm:h-7" />
                        </div>

                        <!-- Card Content -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 mb-0.5">
                                <h3 class="text-xs sm:text-sm font-extrabold text-gray-900 tracking-wide uppercase truncate">
                                    {{ item.title }}
                                </h3>
                            </div>
                            <p class="text-xs text-gray-500 font-medium truncate">
                                {{ item.subtitle }}
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Bottom Carousel Indicators -->
            <div class="flex items-center justify-center gap-1.5 mt-4">
                <button 
                    v-for="(item, index) in items" 
                    :key="index"
                    @click="currentPage = index"
                    :class="[
                        'h-1.5 rounded-full transition-all cursor-pointer',
                        currentPage === index 
                            ? 'w-6 bg-primary' 
                            : 'w-2 bg-gray-300/80 hover:bg-gray-400'
                    ]"
                    :title="`Slide ${index + 1}`"
                ></button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.horizontal-slide-enter-active,
.horizontal-slide-leave-active {
    transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.horizontal-slide-enter-from {
    opacity: 0;
    transform: translateX(30px);
}

.horizontal-slide-leave-to {
    opacity: 0;
    transform: translateX(-30px);
}
</style>

