<script setup>
import Nav from "@/components/shared/Nav.vue";
import { FETCH_VISAS } from "@/services/store/actions.type";
import { computed, onMounted, ref } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import Button from "@/components/ui/button/Button.vue";
import { MessageSquare } from 'lucide-vue-next';

const store = useStore();
const route = useRoute();
const name = ref('');
const email = ref('');

const visa = computed(() => {
    const visaId = route.query.visa_id;
    return store.getters["visa/visa"](visaId);
});

function fetchVisas() {
    store.dispatch("visa/" + FETCH_VISAS);
}

function openWhatsApp() {
  const message = `Hi, I'm interested in the ${visa.value?.title} visa. Please contact me.`;
  const whatsappUrl = `https://wa.me/+923111448111?text=${encodeURIComponent(message)}`;
  window.open(whatsappUrl, '_blank');
}

onMounted(() => {
    fetchVisas();
});
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <div class="w-full z-30 sticky top-0 shadow-sm">
  
    </div>
    
    <!-- Hero Section with Animation -->
    <div class="relative h-[300px] md:h-[400px] overflow-hidden">
      <img
        :src="visa?.header_image"
        alt=""
        class="w-full h-full object-cover brightness-[0.85] animate-slow-zoom"
      />
      <div class="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent flex items-center animate-fade-in">
        <div class="container mx-auto px-4 md:px-6">
          <div class="max-w-2xl text-white">
            <div class="flex items-center gap-3 mb-4 animate-slide-in-left">
              <img class="w-12 h-12 rounded-full shadow-lg border-2 border-white animate-pulse-slow" :src="visa?.country_flag" alt="" />
              <h1 class="text-3xl md:text-4xl font-bold">{{ visa?.title }}</h1>
            </div>
            <p class="text-lg text-white/90 animate-slide-in-bottom">Get your visa quickly and hassle-free</p>
            <p class="text-lg text-white/90 animate-slide-in-bottom">{{ visa?.currency }} {{ visa?.starting_price }} </p>
          </div>
        </div>
      </div>
      <div class="absolute bottom-0 left-0 right-0 h-16 bg-gradient-to-t from-black/40 to-transparent"></div>
    </div>
    
    <!-- Main Content -->
    <section class="container mx-auto py-12 px-4 md:px-6">
      <div class="flex flex-col lg:flex-row gap-8">
        <!-- Visa Information -->
        <div class="w-full lg:w-2/3">
          <div class="bg-white rounded-xl shadow-sm p-6 mb-6 transform transition-all duration-500 hover:shadow-md">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800 border-b pb-2 border-emerald-100">Visa Information</h2>
            <div class="description-content prose max-w-none" v-html="visa?.description"></div>
          </div>
          
          <!-- Thumbnail Gallery -->
          
        </div>
        
        <!-- Contact Form -->
        <div class="w-full lg:w-1/3">
          <div class="bg-white rounded-xl shadow-sm p-6 sticky top-24 animate-fade-in" style="animation-delay: 0.5s;">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800 animate-slide-in-left" style="animation-delay: 0.7s;">Contact us</h2>
            <p class="text-gray-600 mb-6 animate-slide-in-left" style="animation-delay: 0.8s;">Fill out the form below and our visa experts will get back to you shortly.</p>
            
            <div class="space-y-4">
              <div class="animate-slide-in-bottom" style="animation-delay: 0.9s;">
                <Label class="text-gray-700">Full Name</Label>
                <Input 
                  v-model="name"
                  placeholder="Enter your name" 
                  class="w-full mt-1 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring focus:ring-emerald-200 transition-all" 
                />
              </div>
              
              <div class="animate-slide-in-bottom" style="animation-delay: 1s;">
                <Label class="text-gray-700">Email Address</Label>
                <Input 
                  v-model="email"
                  placeholder="Enter your email" 
                  class="w-full mt-1 rounded-lg border-gray-300 focus:border-emerald-500 focus:ring focus:ring-emerald-200 transition-all" 
                />
              </div>
              
              <!-- <Button 
                class="w-full bg-emerald-600 hover:bg-emerald-700 text-white py-2.5 rounded-lg transition-all duration-200 hover:scale-[1.02] animate-slide-in-bottom"
                style="animation-delay: 1.1s;"
              >
                Contact me
              </Button> -->
              
              <!-- WhatsApp Button -->
              <button 
                @click="openWhatsApp"
                class="w-full mt-3 flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white py-2.5 px-4 rounded-lg transition-all duration-300 group animate-slide-in-bottom"
                style="animation-delay: 1.2s;"
              >
                <MessageSquare class="w-5 h-5 group-hover:animate-pulse" />
                <span>Contact via WhatsApp</span>
                <span class="absolute right-4 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all duration-300">→</span>
              </button>
            </div>
            
            <!-- Trust Badges -->
            <div class="mt-8 pt-6 border-t border-gray-200">
              <p class="text-sm text-gray-500 mb-3 font-medium">Trusted by thousands of applicants</p>
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
    <!-- <button 
      @click="openWhatsApp"
      class="fixed bottom-6 right-6 bg-green-500 hover:bg-green-600 text-white p-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 group z-50"
      :class="{'animate-bounce': true}"
    >
      <MessageSquare class="w-6 h-6" />
      <span class="absolute right-full mr-3 top-1/2 transform -translate-y-1/2 bg-white text-green-600 py-2 px-4 rounded-lg shadow-md whitespace-nowrap opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300">
        Chat with us on WhatsApp
      </span>
    </button> -->
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

/* Description content styling */
:deep(.description-content) {
  line-height: 1.7;
  color: #000000;
}

:deep(.description-content h1),
:deep(.description-content h2),
:deep(.description-content h3) {
  color: #000000;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  font-weight: 600;
}

:deep(.description-content h1) {
  font-size: 1.5rem;
  border-bottom: 1px solid #000000;
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
  color: #000000;
  font-weight: bold;
  display: inline-block;
  width: 1em;
  margin-left: -1em;
}

:deep(.description-content a) {
  color: #000000;
  text-decoration: underline;
  transition: all 0.2s;
}

:deep(.description-content a:hover) {
  color: #000000;
}

:deep(.description-content blockquote) {
  border-left: 4px solid #000000;
  padding-left: 1rem;
  font-style: italic;
  color: #000000;
  margin: 1.5rem 0;
  background-color: #f0fdf4;
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
  background-color: #f0fdf4;
  font-weight: 600;
}

:deep(.description-content tr:nth-child(even)) {
  background-color: #f9fafb;
}
</style>