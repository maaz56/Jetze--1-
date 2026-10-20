<script setup>
import Header from '@/components/shared/Header.vue';
import OffersSection from './Home/OffersSection.vue';
import TrustFeaturesCarousel from './Home/TrustFeaturesCarousel.vue';
import TopFlightRoutesSection from './Home/TopFlightRoutesSection.vue';
import Footer from '@/components/common/Footer.vue';
</script>

<template>
    <div class="flex flex-col min-h-screen bg-slate-50/60 font-roboto antialiased">
        
        <!-- SCENIC HERO BANNER (extends behind the transparent navigation) -->
        <div class="relative z-10 w-full bg-[url('/public/banner.jpeg')] bg-cover bg-center bg-no-repeat">
            <!-- Keep the image vivid while retaining enough contrast for the white hero copy. -->
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-slate-950/38 via-slate-950/12 to-slate-900/5"></div>

        <!-- HERO SEARCH SECTION -->
        <div class="relative z-20 mx-auto w-full max-w-7xl px-4 pb-12 pt-[130px] sm:px-6 sm:pb-14 sm:pt-[140px] lg:px-8 lg:pb-20 lg:pt-[155px] animate-fade-in-up">
            
            <!-- Centered hero copy -->
            <div class="mb-5 text-center text-white sm:mb-7 lg:mb-8">
                <h1 class="hero-title font-montserrat text-[2.05rem] font-black tracking-tight sm:text-[2.5rem] lg:text-[3.3rem]">
                    Discover the World with <span class="text-amber-300">Jetze</span>
                </h1>
                <p class="hero-subtitle mt-2 text-[15px] font-bold text-slate-100 sm:text-[1.1rem] lg:text-[1.25rem]">
                    Better Fares <span class="mx-2 text-amber-300">•</span>
                    More Choices <span class="mx-2 text-amber-300">•</span>
                    Unforgettable Journeys
                </p>
            </div>

            <!-- Service selector and search form -->
            <Header />

        </div>
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
    }
    to {
        opacity: 1;
    }
}

.hero-title, .hero-subtitle {
    text-shadow: 0 2px 4px rgba(15, 23, 42, 0.35);
}

.animate-fade-in-up {
    animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
