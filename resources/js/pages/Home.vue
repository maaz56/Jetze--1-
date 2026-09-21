<script setup>
import { onMounted } from 'vue';
import { useStore } from 'vuex';
import { 
    ShieldCheck, 
    Zap, 
    Plane, 
    Headphones 
} from 'lucide-vue-next';

import Header from '@/components/shared/Header.vue';
import QuickServices from './Home/QuickServices.vue';
import OffersSection from './Home/OffersSection.vue';
import TrustFeaturesCarousel from './Home/TrustFeaturesCarousel.vue';
import TopFlightRoutesSection from './Home/TopFlightRoutesSection.vue';
import WhyChooseUs from './Home/WhyChooseUs.vue';
import TrendingRoutes from './Home/TrendingRoutes.vue';
import Collections from './Home/Collections.vue';
import NewsletterSection from './Home/NewsletterSection.vue';
import Footer from '@/components/common/Footer.vue';
import { FETCH_POPULAR_ROUTES } from '@/services/store/actions.type';

const store = useStore();

onMounted(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, { per_page: 20 });
});
</script>

<template>
    <div class="flex flex-col min-h-screen bg-slate-50/60 font-roboto antialiased selection:bg-primary/20 selection:text-primary">
        
        <!-- SCENIC HERO BANNER (extends behind the transparent navigation) -->
        <div class="relative z-10 h-[510px] w-full overflow-hidden bg-[url('/public/banner.jpeg')] bg-cover bg-center bg-no-repeat sm:h-[560px] lg:h-[620px]">
            <!-- Keep the image vivid while retaining enough contrast for the white hero copy. -->
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/38 via-slate-950/12 to-slate-900/5"></div>
        </div>

        <!-- OVERLAPPING SEARCH SECTION -->
        <div class="relative z-20 mx-auto -mt-[385px] w-full max-w-7xl px-4 sm:-mt-[425px] sm:px-6 lg:-mt-[460px] lg:px-8 animate-fade-in-up">
            
            <!-- Centered hero copy -->
            <div class="mb-5 text-center text-white sm:mb-7 lg:mb-8">
                <h1 class="font-montserrat text-[2.05rem] font-black tracking-tight drop-shadow-md sm:text-[2.5rem] lg:text-[3.3rem]">
                    Discover the World with <span class="text-amber-300">Jetze</span>
                </h1>
                <p class="mt-2 text-[15px] font-bold text-slate-100 drop-shadow-sm sm:text-[1.1rem] lg:text-[1.25rem]">
                    Better Fares <span class="mx-2 text-amber-300">•</span>
                    More Choices <span class="mx-2 text-amber-300">•</span>
                    Unforgettable Journeys
                </p>
            </div>

            <!-- Service selector and search form -->
            <Header />

        </div>

        <!-- OFFERS & SEASONAL PROMOTIONS -->
        <div class="mt-8 sm:mt-10">
            <OffersSection />
        </div>

        <!-- TRUST FEATURES AUTO CAROUSEL -->
        <div class="mt-2 sm:mt-4">
            <TrustFeaturesCarousel />
        </div>

        <!-- TOP FLIGHT ROUTES & AIRLINES FOOTER DIRECTORY -->
        <div class="mt-4 sm:mt-6">
            <TopFlightRoutesSection />
        </div>

        <!-- GLOBAL FOOTER -->
        <Footer />

    </div>
</template>

<style scoped>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
