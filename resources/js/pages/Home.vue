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
        
        <!-- SCENIC HERO BANNER (Extends to top-0 underneath fixed transparent header) -->
        <div class="relative w-full h-[370px] sm:h-[410px] lg:h-[450px] bg-[url('/public/banner.jpeg')] bg-cover bg-center bg-no-repeat overflow-hidden z-10">
            <!-- Top Dark Gradient Overlay for optimal header text contrast + soft daylight vignette -->
            <div class="absolute inset-0 bg-gradient-to-b from-slate-950/70 via-slate-950/25 to-slate-900/10 pointer-events-none"></div>
        </div>

        <!-- OVERLAPPING SEARCH SECTION -->
        <div class="relative z-20 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-64 sm:-mt-72 lg:-mt-80 animate-fade-in-up">
            
            <!-- Headline & Tagline Above Search Header -->
            <div class="mb-3 sm:mb-4 text-white">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 mb-1.5 rounded bg-white/20 backdrop-blur-md border border-white/25 text-white text-[11px] sm:text-xs font-semibold shadow-xs">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400"></span>
                    <span>Exclusive Airline Deals & Instant E-Tickets</span>
                </div>
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight font-montserrat drop-shadow-md">
                    Book Cheap Flights & Travel Deals
                </h1>
                <p class="mt-1 text-xs sm:text-sm lg:text-base text-slate-100 font-medium drop-shadow-xs">
                    Search and compare 450+ airlines worldwide with guaranteed best fares
                </p>
            </div>

            <!-- Compact Search Card with rounded-lg -->
            <Header />

        </div>

        <!-- OFFERS & SEASONAL PROMOTIONS -->
        <div class="mt-4 sm:mt-6">
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
