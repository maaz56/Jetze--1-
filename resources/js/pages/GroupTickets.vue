<!-- <script setup>
import Nav from "@/components/shared/Nav.vue";
import {
    FETCH_GROUP_TICKETS,
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

const tickets = computed(() => store.getters["groupTicket/tickets"]);

function fetchGroupTickets() {
    store.dispatch("groupTicket/" + FETCH_GROUP_TICKETS);
}

onMounted(() => {
    fetchGroupTickets();
});
</script>

<template>

    <div class="flex flex-col items-center justify-center mx-auto">
        <h2
            class="mt-8 text-center text-4xl tracking-tight font-extrabold text-gray-900 dark:text-white"
        >
            Group Tickets
        </h2>
        <p
            class="mt-4 text-2xl tracking-tight font-light text-gray-900 dark:text-white"
        >
            We Provide All Type of Group Tickets
        </p>
    </div>

    <section
       
        class="flex items-center justify-center p-20"
    >
        <span class="text-lg text-muted-foreground">Nothing found.</span>
    </section>
    <section  class="md:container mx-auto py-12">
        <p class="text-4xl p-4">Group tickets</p>
        <div class="grid grid-cols-4 gap-4">
            <div v-for="visa in visas" :key="visa.id">
                <div class="flex-col gap-4 shadow-lg p-4 w-100 border">
                    <div class="flex items-center gap-4">
                        <span class="text-5xl rounded">{{
                            visa.country_flag
                        }}</span>
                        <router-link
                            :to="{
                                name: 'VisaDetails',
                                query: {
                                    visa_id: visa.id,
                                },
                            }"
                            class="text-xl"
                            >{{ visa.title }}</router-link
                        >
                    </div>
                    <div class="flex items-center justify-end">
                        <Button
                            @click="
                                $router.push({
                                    name: 'VisaDetails',
                                    query: {
                                        visa_id: visa.id,
                                    },
                                })
                            "
                            variant="outline"
                            class="py-2 px-4 rounded-full mt-4"
                            >Starting from {{ visa.starting_price }}</Button
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template> -->
<!-- new code static page  -->
<script setup>
import Nav from "@/components/shared/Nav.vue";
</script>

<template>

    <div class="bg-white text-gray-800 p-4 rounded-2xl">
        <!-- Background overlay with travel pattern -->
        <div class="absolute bg-cover bg-center opacity-10"
            style="background-image: url('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?ixlib=rb-1.2.1&auto=format&fit=crop&w=1950&q=80');">
        </div>

        <!-- Main content container -->
        <div class="relative z-10 container mx-auto px-4 py-12 md:py-20">
            <!-- Header section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-6xl font-bold mb-4">Group Flight Bookings</h1>
                <p class="text-xl md:text-2xl max-w-3xl mx-auto">Simplify your travel planning with our tailored group ticket solutions</p>
            </div>
        </div>
        <!-- Main card -->
        <div class="bg-white/10 backdrop-blur-md rounded-3xl p-6 md:p-10 shadow-xl max-w-5xl mx-auto">
            <!-- Group booking highlights -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                <div class="flex items-start space-x-4">
                    <div class="bg-primary p-3 rounded-full text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-2">Dedicated Support Team</h3>
                        <p>Our experienced travel consultants assist you through every step of your group booking process.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="bg-primary p-3 rounded-full text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-2">Flexible Payment Options</h3>
                        <p>Convenient payment plans for groups, ensuring flexibility and affordability.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="bg-primary p-3 rounded-full text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-2">Competitive Group Discounts</h3>
                        <p>Exclusive fares for groups of 10 or more travelers to destinations worldwide.</p>
                    </div>
                </div>

                <div class="flex items-start space-x-4">
                    <div class="bg-primary p-3 rounded-full text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-2">Flexible Name Submission</h3>
                        <p>Book your seats now and submit passenger names closer to your departure date.</p>
                    </div>
                </div>
            </div>

            <!-- Details section -->
            <div class="mb-10">
                <h2 class="text-2xl md:text-3xl font-bold mb-6">Tailored Group Booking Services</h2>
                <div class="space-y-4">
                    <p>Our group booking service is perfect for corporate trips, school tours, family gatherings, weddings, and sports teams — ensuring hassle-free arrangements at the best prices.</p>
                    <p>Whether you're planning international or domestic travel, we offer guaranteed fare quotes, flexible ticketing deadlines, and expert support for smooth coordination.</p>
                    <p>Contact us for a personalized consultation and let us handle the complexities of your group's journey.</p>
                </div>
            </div>

            <!-- Why choose us -->
            <div class="mb-10">
                <h2 class="text-2xl md:text-3xl font-bold mb-6">Why Choose Our Group Booking Service</h2>
                <ul class="list-disc pl-5 space-y-2">
                    <li>Custom fare quotes for groups of 10 or more</li>
                    <li>Flexible deposit and payment terms</li>
                    <li>Dedicated agent for group coordination</li>
                    <li>Extended deadlines for passenger details</li>
                    <li>Special assistance for group check-in and seating</li>
                    <li>Seamless booking experience and reliable service</li>
                </ul>
            </div>

            <!-- Contact section -->
            <div class="text-center">
                <h2 class="text-2xl md:text-3xl font-bold mb-6">Ready to Book for Your Group?</h2>
                <p class="text-lg mb-8">Let our team simplify your group travel planning — get in touch today!</p>

                <!-- WhatsApp button -->
                <a href="https://wa.me/+923334419634" target="_blank" rel="noopener noreferrer"
                    class="inline-flex items-center justify-center bg-primary text-white font-bold py-4 px-8 rounded-full text-lg transition-all duration-300 transform hover:scale-105 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" viewBox="0 0 24 24"
                        fill="currentColor">
                        <path
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                    Contact Us on WhatsApp
                </a>
            </div>
        </div>
    </div>

</template>

<style>
/* Reusing your custom animations */
@keyframes float-slow {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-20px); }
}
@keyframes float-medium {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-15px); }
}
@keyframes float-fast {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
.animate-float-slow { animation: float-slow 8s ease-in-out infinite; }
.animate-float-medium { animation: float-medium 6s ease-in-out infinite; }
.animate-float-fast { animation: float-fast 4s ease-in-out infinite; }
</style>
