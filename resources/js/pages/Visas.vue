<script setup>
import Nav from "@/components/shared/Nav.vue";
import { useAuthStore } from "@/services/stores/auth";
import {
    FETCH_VISA_HEADER_IMAGES,
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
import visa from "@/services/store/visa";

const authStore = useAuthStore();
const store = useStore();

const visas = computed(() => store.getters["visa/visas"]);
const headerImages = computed(() => store.getters["visa/headerImages"]);

function fetchVisas() {
    store.dispatch("visa/" + FETCH_VISAS);
}

function fetchVisaHeaderImages() {
    store.dispatch("visa/" + FETCH_VISA_HEADER_IMAGES);
}

onMounted(() => {
    fetchVisas();
    fetchVisaHeaderImages();
});
</script>

<template>

         
    <!-- Hero Carousel Section -->
    <div class="relative">
        <Carousel v-if="headerImages.length > 0" class="w-full">
            <CarouselContent>
                <CarouselItem v-for="image in headerImages" :key="image.id">
                    <div class="relative">
                        <Card class="p-0 border-0 rounded-none overflow-hidden">
                            <img
                                :src="image.url"
                                class="w-full h-[450px] object-cover"
                                alt="Visa destination"
                            />
                        </Card>
                    </div>
                </CarouselItem>
            </CarouselContent>
            <CarouselPrevious class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white" />
            <CarouselNext class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white" />
        </Carousel>
        <div v-else>
            <Card class="p-0 border-0 rounded-none overflow-hidden">
                <CardContent class="p-0 w-full">
                    <img
                        src="/public/assets/1920 X 350.png"
                        class="w-full h-[450px] object-cover"
                        alt="Visa destination"
                    />
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Visa Title Section -->
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl font-bold text-gray-900 tracking-tight">
            All Visas
        </h2>
        <p class="mt-4 text-xl text-gray-600 max-w-3xl mx-auto">
            We Provide All Type of International Visas
        </p>
    </div>

    <!-- Visa Listings Section -->
    <section
        v-if="visas.length == 0"
        class="flex items-center justify-center p-20"
    >
        <span class="text-lg text-gray-500">Nothing found.</span>
    </section>
    <section v-else class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <p class="text-4xl font-bold text-gray-900 mb-10">Our Visas</p>
        
        <!-- Changed to 4-column grid to match the image -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div 
                v-for="visa in visas" 
                :key="visa.id"
                class="bg-white rounded-lg overflow-hidden shadow-sm hover:shadow-md transition-all duration-300 cursor-pointer"
                @click="
                    $router.push({
                        name: 'VisaDetails',
                        query: {
                            visa_id: visa.id,
                        },
                    })
                "
            >
                <!-- Visa Image -->
                <div class="h-48 overflow-hidden">
                    <img 
                        :src="visa.header_image || '/placeholder.svg?height=200&width=300'" 
                        class="w-full h-full object-cover transition-transform duration-700 hover:scale-105" 
                        alt="Visa destination" 
                    />
                </div>
               
                <!-- Visa Title -->
                <div class="p-4 pb-2">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ visa.title }}
                    </h3>
                </div>
                
                <!-- Flag and Price - exactly matching the image layout -->
                <div class="px-4 pb-4">
                    <div class="flex items-center justify-between">
                        <!-- Country Flag (replacing reviews) -->
                        <div class="flex items-center">
                            <img 
                                :src="visa.country_flag" 
                                class="w-10 h-10 object-cover rounded-full" 
                                alt="Country flag" 
                            />
                          
                        </div>
                        
                        <!-- Price -->
                        <div class="text-right">
                            <div class="text-xs text-gray-500">Per Person from</div>
                            <div class="text-lg font-bold text-gray-900">
                                 {{visa.currency}} {{ visa.starting_price }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>