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
        <div class="relative w-full h-[390px] sm:h-[430px] lg:h-[470px] bg-[url('/public/banner.jpeg')] bg-cover bg-center bg-no-repeat overflow-hidden z-10">
            <!-- Soft banner overlay for readable content without darkening the image too much -->
            <div class="absolute inset-0 bg-black/25 pointer-events-none"></div>
        </div>

        <!-- OVERLAPPING SEARCH SECTION -->
        <div class="relative z-20 mx-auto -mt-[385px] w-full max-w-7xl px-4 sm:-mt-[425px] sm:px-6 lg:-mt-[460px] lg:px-8 animate-fade-in-up">
            
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
