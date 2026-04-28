<script setup>
import Nav from "@/components/shared/Nav.vue";
import { FETCH_HOLIDAYS } from "@/services/store/actions.type";
import { computed, onMounted, ref } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import Button from "@/components/ui/button/Button.vue";
import { MessageSquare, MapPin, Calendar, Users, Clock, Sun, Umbrella, Camera } from 'lucide-vue-next';

const store = useStore();
const route = useRoute();
const name = ref('');
const email = ref('');
const activeTab = ref('overview');

const holiday = computed(() => store.getters["holiday/holiday"](route.query.holiday_id));

// Sample gallery images - in a real app, these would come from your API
const galleryImages = ref([
  { id: 1, src: '/placeholder.svg?height=400&width=600&text=Beach', alt: 'Beach view' },
  { id: 2, src: '/placeholder.svg?height=400&width=600&text=Resort', alt: 'Resort' },
  { id: 3, src: '/placeholder.svg?height=400&width=600&text=Pool', alt: 'Swimming pool' },
  { id: 4, src: '/placeholder.svg?height=400&width=600&text=Restaurant', alt: 'Restaurant' },
  { id: 5, src: '/placeholder.svg?height=400&width=600&text=Activities', alt: 'Activities' },
  { id: 6, src: '/placeholder.svg?height=400&width=600&text=Room', alt: 'Room interior' },
]);

function fetchHolidays() {
    store.dispatch("holiday/" + FETCH_HOLIDAYS);
}

function openWhatsApp() {
  const message = `Hi, I'm interested in the ${holiday.value?.title} holiday package. Please contact me.`;
  const whatsappUrl = `https://wa.me/+923111448111?text=${encodeURIComponent(message)}`;
  window.open(whatsappUrl, '_blank');
}

onMounted(() => {
    fetchHolidays();
});
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <div class="w-full z-30 sticky top-0 shadow-md bg-white">
      <Nav />
    </div>
    
    <!-- Hero Section with Animation -->
    <div class="relative h-[500px] md:h-[600px] overflow-hidden">
      <img
        :src="holiday?.header_image"
        alt=""
        class="w-full h-full object-cover brightness-[0.9] animate-slow-zoom"
      />
      
      <!-- Gradient Overlay -->
      <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>
      
      <!-- Hero Content -->
      <div class="absolute inset-0 flex items-end pb-16 md:pb-24">
        <div class="container mx-auto px-4 md:px-6">
          <!-- Animated Title Bar -->
          <div class="relative animate-slide-in-right">
            <div class="absolute left-0 top-0 h-full w-2 bg-yellow-400"></div>
            <div class="bg-white/90 backdrop-blur-sm shadow-lg p-6 md:p-8 rounded-r-lg max-w-2xl">
              <div class="flex items-center gap-4 mb-2">
                <img 
                  class="w-12 h-12 rounded-full border-2 border-yellow-400 shadow-lg animate-pulse-slow" 
                  :src="holiday?.country_flag" 
                  alt=""
                />
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">{{ holiday?.title }}</h1>
              </div>
              
              <!-- Quick Info Pills -->
              <div class="flex flex-wrap gap-2 mt-4">
                <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                  <Clock class="w-3 h-3" />
                  <span>7 Days</span>
                </div>
                <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                  <Users class="w-3 h-3" />
                  <span>Family Friendly</span>
                </div>
                <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                  <Sun class="w-3 h-3" />
                  <span>All Inclusive</span>
                </div>
                <div class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm flex items-center gap-1">
                  <MapPin class="w-3 h-3" />
                  <span>Popular Destination</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Decorative Elements -->
      <div class="absolute top-10 right-10 animate-float">
        <div class="text-white opacity-50">
          <Umbrella class="w-12 h-12" />
        </div>
      </div>
      <div class="absolute bottom-40 right-20 animate-float-delayed">
        <div class="text-white opacity-50">
          <Camera class="w-8 h-8" />
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <section class="container mx-auto py-12 px-4 md:px-6">
      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Holiday Information -->
        <div class="w-full lg:w-2/3">
          <!-- Tabs Navigation -->
          <div class="mb-6 border-b border-gray-200">
            <div class="flex overflow-x-auto space-x-4">
              <button 
                @click="activeTab = 'overview'" 
                class="py-2 px-4 font-medium transition-colors duration-200 whitespace-nowrap"
                :class="activeTab === 'overview' ? 'text-yellow-600 border-b-2 border-yellow-400' : 'text-gray-600 hover:text-yellow-600'"
              >
                Overview
              </button>
              <button 
                @click="activeTab = 'itinerary'" 
                class="py-2 px-4 font-medium transition-colors duration-200 whitespace-nowrap"
                :class="activeTab === 'itinerary' ? 'text-yellow-600 border-b-2 border-yellow-400' : 'text-gray-600 hover:text-yellow-600'"
              >
                Itinerary
              </button>
              <button 
                @click="activeTab = 'gallery'" 
                class="py-2 px-4 font-medium transition-colors duration-200 whitespace-nowrap"
                :class="activeTab === 'gallery' ? 'text-yellow-600 border-b-2 border-yellow-400' : 'text-gray-600 hover:text-yellow-600'"
              >
                Gallery
              </button>
              <button 
                @click="activeTab = 'reviews'" 
                class="py-2 px-4 font-medium transition-colors duration-200 whitespace-nowrap"
                :class="activeTab === 'reviews' ? 'text-yellow-600 border-b-2 border-yellow-400' : 'text-gray-600 hover:text-yellow-600'"
              >
                Reviews
              </button>
            </div>
          </div>
          
          <!-- Tab Content -->
          <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'" class="animate-fade-in">
              <!-- Title Bar -->
              <div class="relative mb-6">
                <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
                <h2 class="text-2xl font-semibold pl-4 text-gray-800">Holiday Overview</h2>
              </div>
              
              <div class="description-content prose max-w-none" v-html="holiday?.description"></div>
            </div>
            
            <!-- Itinerary Tab -->
            <div v-if="activeTab === 'itinerary'" class="animate-fade-in">
              <!-- Title Bar -->
              <div class="relative mb-6">
                <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
                <h2 class="text-2xl font-semibold pl-4 text-gray-800">Day-by-Day Itinerary</h2>
              </div>
              
              <div class="space-y-6">
                <div v-for="day in 5" :key="day" class="border-l-2 border-yellow-400 pl-4 pb-6 relative">
                  <div class="absolute -left-2 top-0 w-4 h-4 rounded-full bg-yellow-400"></div>
                  <h3 class="text-xl font-medium text-gray-800">Day {{ day }}</h3>
                  <p class="text-gray-600 mt-2">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum vestibulum.</p>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Breakfast</span>
                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Guided Tour</span>
                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs">Free Time</span>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Gallery Tab -->
            <div v-if="activeTab === 'gallery'" class="animate-fade-in">
              <!-- Title Bar -->
              <div class="relative mb-6">
                <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
                <h2 class="text-2xl font-semibold pl-4 text-gray-800">Photo Gallery</h2>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div 
                  v-for="(image, index) in galleryImages" 
                  :key="image.id" 
                  class="group relative overflow-hidden rounded-lg bg-gray-100 h-48 transition-all duration-300 hover:shadow-md hover:-translate-y-1"
                  :style="{ animationDelay: (index * 0.1) + 's' }"
                  :class="'animate-fade-in'"
                >
                  <img 
                    :src="image.src" 
                    :alt="image.alt" 
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end">
                    <p class="text-white p-3 font-medium">{{ image.alt }}</p>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Reviews Tab -->
            <div v-if="activeTab === 'reviews'" class="animate-fade-in">
              <!-- Title Bar -->
              <div class="relative mb-6">
                <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
                <h2 class="text-2xl font-semibold pl-4 text-gray-800">Customer Reviews</h2>
              </div>
              
              <div class="space-y-6">
                <div v-for="review in 3" :key="review" class="bg-gray-50 p-4 rounded-lg">
                  <div class="flex items-center gap-3 mb-2">
                    <div class="w-10 h-10 rounded-full bg-yellow-200 flex items-center justify-center text-yellow-800 font-bold">
                      {{ String.fromCharCode(64 + review) }}
                    </div>
                    <div>
                      <h4 class="font-medium">Happy Traveler {{ review }}</h4>
                      <div class="flex text-yellow-400">
                        <span v-for="star in 5" :key="star" class="text-lg">★</span>
                      </div>
                    </div>
                  </div>
                  <p class="text-gray-600">Amazing experience! The holiday package exceeded our expectations. The accommodations were excellent and the activities were well organized.</p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Highlights Section -->
          <div class="bg-white rounded-xl shadow-sm p-6">
            <!-- Title Bar -->
            <div class="relative mb-6">
              <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
              <h2 class="text-2xl font-semibold pl-4 text-gray-800">Holiday Highlights</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div v-for="i in 4" :key="i" class="flex gap-3 p-4 rounded-lg border border-gray-100 hover:border-yellow-200 transition-colors duration-200">
                <div class="text-yellow-500 mt-1">
                  <component :is="[Sun, Umbrella, MapPin, Calendar][i-1]" class="w-6 h-6" />
                </div>
                <div>
                  <h3 class="font-medium text-gray-800">Holiday Feature {{ i }}</h3>
                  <p class="text-gray-600 text-sm mt-1">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus lacinia odio vitae vestibulum.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Contact Form -->
        <div class="w-full lg:w-1/3">
          <div class="bg-white rounded-xl shadow-sm p-6 sticky top-24 animate-fade-in" style="animation-delay: 0.5s;">
            <!-- Title Bar -->
            <div class="relative mb-6">
              <div class="absolute left-0 top-0 h-full w-1 bg-yellow-400"></div>
              <h2 class="text-2xl font-semibold pl-4 text-gray-800">Book This Holiday</h2>
            </div>
            
            <p class="text-gray-600 mb-6">Fill out the form below and our travel experts will get back to you shortly.</p>
            
            <div class="space-y-4">
              <div class="animate-slide-in-bottom" style="animation-delay: 0.9s;">
                <Label class="text-gray-700">Full Name</Label>
                <Input 
                  v-model="name"
                  placeholder="Enter your name" 
                  class="w-full mt-1 rounded-lg border-gray-300 focus:border-yellow-500 focus:ring focus:ring-yellow-200 transition-all" 
                />
              </div>
              
              <div class="animate-slide-in-bottom" style="animation-delay: 1s;">
                <Label class="text-gray-700">Email Address</Label>
                <Input 
                  v-model="email"
                  placeholder="Enter your email" 
                  class="w-full mt-1 rounded-lg border-gray-300 focus:border-yellow-500 focus:ring focus:ring-yellow-200 transition-all" 
                />
              </div>
              
              <Button 
                class="w-full bg-yellow-500 hover:bg-yellow-600 text-white py-2.5 rounded-lg transition-all duration-200 hover:scale-[1.02] animate-slide-in-bottom"
                style="animation-delay: 1.1s;"
              >
                Contact me
              </Button>
              
              <!-- WhatsApp Button -->
              <button 
                @click="openWhatsApp"
                class="w-full mt-3 flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white py-2.5 px-4 rounded-lg transition-all duration-300 group animate-slide-in-bottom relative overflow-hidden"
                style="animation-delay: 1.2s;"
              >
                <span class="absolute inset-0 w-full h-full bg-white/20 skew-x-[-20deg] translate-x-[-100%] group-hover:translate-x-[100%] transition-transform duration-1000"></span>
                <MessageSquare class="w-5 h-5 group-hover:animate-pulse" />
                <span>Contact via WhatsApp</span>
                <span class="absolute right-4 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-300">→</span>
              </button>
            </div>
            
            <!-- Price Card -->
            <div class="mt-8 bg-yellow-50 p-4 rounded-lg border border-yellow-100">
              <div class="flex justify-between items-center mb-2">
                <span class="text-gray-700 font-medium">Starting from</span>
                <span class="text-2xl font-bold text-yellow-600">AED {{ holiday.starting_price }}</span>
              </div>
              <p class="text-sm text-gray-600">per person, all inclusive</p>
              <div class="mt-3 pt-3 border-t border-yellow-200">
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                  <span class="text-green-500">✓</span>
                  <span>Accommodation included</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600 mb-1">
                  <span class="text-green-500">✓</span>
                  <span>Breakfast included</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                  <span class="text-green-500">✓</span>
                  <span>Guided tours included</span>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-600">
                  <span class="text-green-500">✓</span>
                  <span>Travel Insurance included</span>
                </div>
              </div>
            </div>
            
            <!-- Trust Badges -->
            <div class="mt-8 pt-6 border-t border-gray-200">
              <p class="text-sm text-gray-500 mb-3 font-medium">Trusted by thousands of travelers</p>
              <div class="flex flex-wrap gap-3 justify-between">
                <div v-for="i in 3" :key="i" class="bg-gray-100 rounded-md p-2 flex items-center justify-center w-[30%]">
                  <div class="w-full h-6 bg-gray-200 rounded animate-pulse"></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- Floating WhatsApp Button -->
    
  </div>
</template>

<style scoped>
@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.animate-pulse {
  animation: pulse 2s infinite;
}

.animate-pulse-slow {
  animation: pulse 3s infinite;
}

@keyframes bounce {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-bounce {
  animation: bounce 2s infinite;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.animate-fade-in {
  animation: fadeIn 1.5s ease-out;
}

@keyframes slideInLeft {
  from {
    transform: translateX(-30px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-slide-in-left {
  animation: slideInLeft 1s ease-out;
}

@keyframes slideInRight {
  from {
    transform: translateX(30px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-slide-in-right {
  animation: slideInRight 1s ease-out;
}

@keyframes slideInBottom {
  from {
    transform: translateY(20px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slide-in-bottom {
  animation: slideInBottom 1s ease-out 0.3s forwards;
  opacity: 0;
}

@keyframes slowZoom {
  from {
    transform: scale(1.05);
  }
  to {
    transform: scale(1);
  }
}

.animate-slow-zoom {
  animation: slowZoom 10s ease-out forwards;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-float {
  animation: float 6s ease-in-out infinite;
}

.animate-float-delayed {
  animation: float 8s ease-in-out 1s infinite;
}

/* Description content styling */
:deep(.description-content) {
  line-height: 1.7;
  color: #374151;
}

:deep(.description-content h1),
:deep(.description-content h2),
:deep(.description-content h3) {
  color: #b45309;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  font-weight: 600;
}

:deep(.description-content h1) {
  font-size: 1.5rem;
  border-bottom: 1px solid #fef3c7;
  padding-bottom: 0.5rem;
}

:deep(.description-content h2) {
  font-size: 1.25rem;
}

:deep(.description-content h3) {
  font-size: 1.125rem;
}

:deep(.description-content p) {
  margin-bottom: 1rem;
}

:deep(.description-content ul),
:deep(.description-content ol) {
  margin-left: 1.5rem;
  margin-bottom: 1rem;
}

:deep(.description-content li) {
  margin-bottom: 0.5rem;
  position: relative;
}

:deep(.description-content ul li::before) {
  content: "•";
  color: #f59e0b;
  font-weight: bold;
  display: inline-block;
  width: 1em;
  margin-left: -1em;
}

:deep(.description-content a) {
  color: #d97706;
  text-decoration: underline;
  transition: all 0.2s;
}

:deep(.description-content a:hover) {
  color: #b45309;
}

:deep(.description-content blockquote) {
  border-left: 4px solid #f59e0b;
  padding-left: 1rem;
  font-style: italic;
  color: #4b5563;
  margin: 1.5rem 0;
  background-color: #fffbeb;
  padding: 1rem;
  border-radius: 0.25rem;
}

:deep(.description-content table) {
  width: 100%;
  border-collapse: collapse;
  margin: 1.5rem 0;
}

:deep(.description-content th),
:deep(.description-content td) {
  border: 1px solid #d1d5db;
  padding: 0.5rem;
}

:deep(.description-content th) {
  background-color: #fffbeb;
  font-weight: 600;
}

:deep(.description-content tr:nth-child(even)) {
  background-color: #f9fafb;
}
</style>