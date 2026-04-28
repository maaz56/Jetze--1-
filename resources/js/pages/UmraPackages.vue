<script setup>
import Nav from "@/components/shared/Nav.vue";
import {
    FETCH_UMRAH_HEADER_IMAGES,
    FETCH_UMRAH_PACKAGES,
    FETCH_VISAS,
} from "@/services/store/actions.type";
import { computed, onMounted } from "vue";
import { useStore } from "vuex";
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from "@/components/ui/carousel";
import { Card, CardContent } from "@/components/ui/card";
import Button from "@/components/ui/button/Button.vue";

const store = useStore();

const umrahPackages = computed(
    () => store.getters["umrahPackage/umrahPackages"]
);
const headerImages = computed(() => store.getters["umrahPackage/headerImages"]);

function fetchUmrahPackages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_PACKAGES);
}

function fetchUmrahHeaderImages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_HEADER_IMAGES);
}

onMounted(() => {
    fetchUmrahPackages();
    fetchUmrahHeaderImages();
});
</script>

<template>
    <!-- Hero Carousel Section with Overlay -->
    <div class="relative">
        <Carousel v-if="headerImages.length > 0" class="w-full">
            <CarouselContent>
                <CarouselItem v-for="image in headerImages" :key="image.id">
                    <div class="relative">
                        <Card class="p-0 h-[500px] border-0 rounded-none overflow-hidden">
                            <img
                                :src="image.url"
                                class="w-full h-full object-cover transition-transform duration-700 hover:scale-105"
                                alt="Umrah Package"
                            />
                            <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-white p-6">
                                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-center mb-4">
                                    Sacred Journey to Makkah
                                </h1>
                                <p class="text-xl md:text-2xl text-center max-w-3xl mx-auto mb-8">
                                    Experience the spiritual journey of a lifetime with our premium Umrah packages
                                </p>
                                <Button class="bg-white text-primary hover:bg-primary/10 px-8 py-3 text-lg rounded-full font-medium">
                                    Explore Packages
                                </Button>
                            </div>
                        </Card>
                    </div>
                </CarouselItem>
            </CarouselContent>
            <CarouselPrevious class="left-4 bg-white/80 hover:bg-white text-primary border-0" />
            <CarouselNext class="right-4 bg-white/80 hover:bg-white text-primary border-0" />
        </Carousel>
        <div v-else class="relative">
            <Card class="p-0 border-0 rounded-none">
                <CardContent class="p-0 w-full">
                    <img
                        src="/public/assets/1920 X 350.png"
                        class="w-full h-[500px] object-cover"
                        alt="Umrah Package"
                    />
                    <div class="absolute inset-0 bg-black/40 flex flex-col items-center justify-center text-white p-6">
                        <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-center mb-4">
                            Sacred Journey to Makkah
                        </h1>
                        <p class="text-xl md:text-2xl text-center max-w-3xl mx-auto mb-8">
                            Experience the spiritual journey of a lifetime with our premium Umrah packages
                        </p>
                        <Button class="bg-white text-primary hover:bg-white/80 px-8 py-3 text-lg rounded-full font-medium">
                            Explore Packages
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
    <!-- Packages Section -->
    <div class="bg-gradient-to-b from-primary/5 to-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-base font-semibold tracking-wide uppercase text-primary">Discover</h2>
                <h2 class="mt-2 text-4xl md:text-5xl font-bold tracking-tight text-gray-900">
                    Our Umrah Packages
                </h2>
                <div class="mt-4 max-w-2xl mx-auto">
                    <p class="text-xl text-gray-600">
                        We provide comprehensive and customizable Umrah packages to suit your spiritual journey
                    </p>
                </div>
                <div class="mt-6 w-24 h-1 bg-primary mx-auto rounded-full"></div>
            </div>
            <section v-if="umrahPackages.length == 0" class="flex items-center justify-center p-20">
                <div class="text-center p-12 rounded-lg bg-white shadow-sm border border-gray-100">
                    <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <span class="text-xl font-medium text-gray-700">No packages available at the moment</span>
                    <p class="mt-2 text-gray-500">Please check back later for new Umrah packages</p>
                </div>
            </section>
            
            <section v-else class="mt-12">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div 
                        v-for="umrahPackage in umrahPackages" 
                        :key="umrahPackage.id"
                        class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-all duration-300"
                    >
                        <!-- Package Image - Similar to the reference image -->
                        <div class="relative h-48 overflow-hidden">
    <img
        :src="umrahPackage.header_image"
        alt="Umrah Package Image"
        class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
    />
</div>

                        
                        <!-- Package Details - Clean white background like the reference -->
                        <div class="p-5">
                            <router-link
                                :to="{
                                    name: 'UmrahPackageDetails',
                                    query: {
                                        umrah_package_id: umrahPackage.id,
                                    },
                                }"
                                class="text-xl font-bold text-gray-900 hover:text-primary transition-colors block"
                            >
                                {{ umrahPackage.title }}
                            </router-link>
                            
                            <!-- Short description line -->
                            <p class="text-gray-600 text-sm mt-1">Spiritual journey to the holy cities</p>
                            
                            <!-- Price displayed at bottom right like the reference -->
                            <div class="flex justify-end mt-4">
                                <div class="text-right">
                                    <span class="text-primary font-medium">From AED </span>
                                    <span class="text-primary text-2xl font-bold">{{ umrahPackage.starting_price }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>