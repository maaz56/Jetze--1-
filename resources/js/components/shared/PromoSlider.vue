<template>
    <div class="relative w-full overflow-hidden mt-4" @mouseenter="pauseScroll" @mouseleave="resumeScroll">
       

        <div class="relative w-full rounded-lg h-80 flex flex-col justify-center items-center overflow-hidden">
          <!-- Main Heading -->

          <!-- Travel Images Carousel -->
          <div class="absolute inset-0 w-full h-full z-10 ">
            <div class="w-full h-full">
              <div class="flex transition-transform duration-500 ease-in-out h-full"
                :style="{ transform: `translateX(-${currentSlide * 100}%)` }">
                <div v-for="(slide, index) in extendedSlides" :key="index" class="min-w-full h-full relative">
                  <img :src="slide.url" :alt="slide.title" class="w-full h-full object-cover" />
                  <!--  -->
                </div>
              </div>
            </div>

            <!-- Carousel Navigation -->
            <button @click="previousSlide"
              class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full p-3 transition-all duration-200 z-20">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <button @click="nextSlide"
              class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full p-3 transition-all duration-200 z-20">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <!-- Carousel Indicators -->
          <div class="absolute bottom-16 flex space-x-3 z-20">
            <button v-for="(slide, index) in totalSlides" :key="index" @click="currentSlide = index"
              class="w-3 h-3 rounded-full transition-all duration-200"
              :class="currentSlide === index ? 'bg-white' : 'bg-white/50 hover:bg-white/70'"></button>
          </div>

          <!-- Bottom Contact Info -->
         
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from 'vue';
import { FETCH_PROMO_IMAGES } from "@/services/store/actions.type";
import { useStore } from "vuex";

const store = useStore();

const promoImages = computed(() => {
    const images = store.getters["promoImage/promoImageData"];
    if (!images || !Array.isArray(images) || images.length === 0) return [];
    return images.filter(img => img.is_home === false || img.is_home === 0);
});
function fetchPromoImages() {
    store.dispatch("promoImage/" + FETCH_PROMO_IMAGES);
}
const currentSlide = ref(0)
const totalSlides = computed(() => promoImages.value.filter(img => img.is_home === false || img.is_home === 0).length)
let carouselInterval = null
function nextSlide() {
  currentSlide.value = (currentSlide.value + 1) % totalSlides.value
}

function previousSlide() {
  currentSlide.value = currentSlide.value === 0 ? totalSlides.value - 1 : currentSlide.value - 1
}


// Default fallback slides (in case promo images are empty)
const defaultSlides = [
    {
        image: 'https://picsum.photos/id/1018/800/400',
        title: 'Mountain View',
        description: 'Beautiful mountain landscape with a clear gray sky.'
    },
    {
        image: 'https://picsum.photos/id/1015/800/400',
        title: 'River Stream',
        description: 'Peaceful river flowing through a lush gray forest.'
    },
    {
        image: 'https://picsum.photos/id/1019/800/400',
        title: 'Ocean Sunset',
        description: 'Stunning sunset over the calm ocean waters.'
    },
    {
        image: 'https://picsum.photos/id/1016/800/400',
        title: 'Forest Path',
        description: 'Serene path through a misty forest in the morning.'
    },
    {
        image: 'https://picsum.photos/id/1039/800/400',
        title: 'Desert Landscape',
        description: 'Vast desert landscape with rolling sand dunes.'
    }
];

// Use promoImages if available, otherwise fallback to default slides
const slides = computed(() => promoImages.value.length ? promoImages.value : defaultSlides);

// Create extended slides array for infinite looping
const extendedSlides = computed(() => {
    const slideCount = slides.value.length;
    return [...slides.value, ...slides.value];
});

// Reactive state
const currentIndex = ref(0);
const currentDotIndex = ref(0);
const sliderContainer = ref(null);
const currentOffset = ref(0);
const autoScrollInterval = ref(null);
const isPaused = ref(false);
const isTransitioning = ref(true);

// Calculate the number of visible slides based on screen size
const visibleSlides = computed(() => {
    if (typeof window === 'undefined') return 1;
    if (window.innerWidth >= 1024) return 2; // Show 2 slides on large screens
    if (window.innerWidth >= 768) return 2; // Show 2 slides on medium screens
    return 1;
});

// Calculate slide width
const slideWidth = computed(() => {
    if (!sliderContainer.value) return 0;
    return sliderContainer.value.clientWidth / visibleSlides.value;
});

// Methods
const updateOffset = () => {
    if (!sliderContainer.value) return;
    currentOffset.value = currentIndex.value * slideWidth.value;
    currentDotIndex.value = currentIndex.value % slides.value.length;
};



const goToSlide = (index) => {
    currentIndex.value = index;
    isTransitioning.value = true;
    updateOffset();
};

const startAutoScroll = () => {
    if (autoScrollInterval.value) clearInterval(autoScrollInterval.value);
    autoScrollInterval.value = setInterval(() => {
        if (!isPaused.value) {
            nextSlide();
        }
    }, 3000);
};

const pauseScroll = () => {
    isPaused.value = true;
};

const resumeScroll = () => {
    isPaused.value = false;
};

const handleResize = () => {
    nextTick(() => {
        updateOffset();
    });
};

onMounted(() => {
    fetchPromoImages();
    updateOffset();
    startAutoScroll();
    window.addEventListener('resize', handleResize);
});

onBeforeUnmount(() => {
    if (autoScrollInterval.value) {
        clearInterval(autoScrollInterval.value);
    }
    window.removeEventListener('resize', handleResize);
});
</script>

<style>
/* Ensure smooth transitions */
.transition-transform {
    transition-property: transform;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}

/* Card styling */
.h-64 {
    height: 16rem; /* Match the height of the cards in the image (approximately 256px) */
}

.rounded-lg {
    border-radius: 1rem; /* Match the rounded corners */
}

.shadow-lg {
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Navigation arrows */
.bg-orange-500 {
    background-color: #a89666;
}

.text-white {
    color: #ffffff;
}

.rounded-full {
    border-radius: 9999px;
}

.p-2 {
    padding: 0.5rem;
}

.shadow-md {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.hover\:bg-orange-600:hover {
    background-color: #a89666;
}

.transition-colors {
    transition-property: background-color, border-color, color, fill, stroke;
    transition-duration: 300ms;
}
</style>