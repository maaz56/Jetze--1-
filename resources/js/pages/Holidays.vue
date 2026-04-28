<script setup>
import Nav from "@/components/shared/Nav.vue";
import {
    FETCH_HOLIDAY_HEADER_IMAGES,
    FETCH_HOLIDAYS,
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

const holidays = computed(() => store.getters["holiday/holidays"]);
const headerImages = computed(() => store.getters["holiday/headerImages"]);

function fetchHolidays() {
    store.dispatch("holiday/" + FETCH_HOLIDAYS);
}

function fetchHolidayHeaderImages() {
    store.dispatch("holiday/" + FETCH_HOLIDAY_HEADER_IMAGES);
}

onMounted(() => {
    fetchHolidays();
    fetchHolidayHeaderImages();
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
                                alt="Holiday destination"
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
                        src="https://cdn.pixabay.com/photo/2020/08/09/12/07/man-5475371_960_720.jpg"
                        class="w-full h-[450px] object-cover"
                        alt="Holiday destination"
                    />
                </CardContent>
            </Card>
        </div>
    </div>

    <!-- Holiday Packages Section -->
    <section v-if="holidays.length == 0" class="flex items-center justify-center p-20">
        <span class="text-lg text-gray-500">Nothing found.</span>
    </section>
    <section v-else class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
        <p class="text-4xl font-bold text-gray-900 mb-10">Our Holidays</p>
        
        <!-- Changed from 4 columns to 3 columns for wider cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <div 
                v-for="holiday in holidays" 
                :key="holiday.id" 
                class="relative bg-white rounded-xl overflow-hidden shadow-md hover:shadow-lg transition-all duration-300 cursor-pointer border border-gray-100 hover:border-gray-200 hover:-translate-y-1"
                @click="$router.push({
                    name: 'HolidayDetails',
                    query: {
                        holiday_id: holiday.id,
                    },
                })"
            >
                <!-- Make entire card clickable -->
                <div class="h-full flex flex-col">
                    <!-- Holiday Image - increased height -->
                    <div class="h-56 overflow-hidden">
                        <img 
                            :src="holiday?.header_image" 
                            class="w-full h-full object-cover transition-transform duration-700 hover:scale-105" 
                            alt="Holiday destination" 
                        />
                    </div>
                    
                    <!-- Holiday Title - improved spacing and typography -->
                    <div class="p-5 pb-3">
                        <h3 class="text-xl font-semibold text-gray-900 line-clamp-2">
                            {{ holiday.title }}
                        </h3>
                    </div>
                    
                    <!-- Flag and Price - improved layout -->
                    <div class="px-5 pb-5 mt-auto">
                        <div class="flex items-center justify-between">
                            <!-- Country Flag with label -->
                            <div class="flex items-center space-x-2">
                                <img 
                                    :src="holiday.country_flag" 
                                    class="w-7 h-7 object-cover rounded-full ring-1 ring-gray-200" 
                                    alt="Country flag" 
                                />
                                <span class="text-sm text-gray-600">{{ holiday.country || 'Destination' }}</span>
                            </div>
                            
                            <!-- Price - enhanced styling -->
                            <div class="text-right">
                                <div class="text-xs text-gray-500 font-medium">Per Person from</div>
                                <div class="text-xl font-bold text-emerald-600">
                                    AED {{ holiday.starting_price }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>