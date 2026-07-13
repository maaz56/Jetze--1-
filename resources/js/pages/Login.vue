<template>
  <div class=" bg-gray-50">

    <!-- TopBar and Nav (keeping original) -->
    <!-- <TopBar class="z-10" />
    <Nav :isNavTransparent="true" class="z-10" /> -->

    <!-- Main Login Section -->
    <div class=" flex">
      <!-- Left Side - Login Form (40%) -->
      <div class="md:w-1/2 bg-white flex flex-1 items-center justify-center p-8">
        <div class="w-full max-w-md">
          <!-- Logo -->
          <div class="flex items-center mb-8 group justify-center">
            <!-- Logo Container -->
            <div
              class=" w-52 h-52 p-2 flex items-center justify-center transform transition-all duration-300 group-hover:scale-110 group-hover:rotate-3">
              <div class="flex items-center">
                <img src="/public/assets/logo.png"/>
              </div>
            </div>

            <!-- Brand Name Container -->
            <div class="ml-4 flex flex-col">
              <!-- Main Brand Name -->
              <!-- <div class="flex items-baseline">
                <span
                  class="text-2xl font-extrabold bg-gradient-to-r from-[#dbcaa4] to-[#a89666] bg-clip-text text-transparent animate-pulse">
                  Jetze
                </span>
                <span class="text-xs text-gray-500 ml-1 font-medium animate-bounce">.com</span>
              </div> -->

              <!-- Animated Underline -->
              <div
                class="h-0.5 bg-primary/80 transform scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left mt-1">
              </div>
            </div>
          </div>

          <!-- Form Type Toggle -->
          <div v-show="!showOtp" class="flex mb-6 bg-gray-100 rounded-lg p-1">
            <button type="button" @click="formType = 'login'"
              class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all duration-200"
              :class="formType === 'login' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-600 hover:text-gray-900'">
              Login
            </button>
            <button type="button" @click="formType = 'register'"
              class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all duration-200"
              :class="formType === 'register' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-600 hover:text-gray-900'">
              Register
            </button>
          </div>
          <div v-show="showOtp" @click="formType = 'login'; showOtp = false"
            class="flex items-center cursor-pointer text-sm gap-1 font-medium">
            <ArrowLeft :size="14"></ArrowLeft>
            Back
          </div>
          <template v-if="formType == 'login'">
            <Login @open-otp-card="(e) => { showOtp = true; formType = 'otp'; userDetail = e }"></Login>

          </template>
          <component v-else :is="currentComponent" :key="componentKey" v-bind="componentProps" />
        </div>
      </div>

      <!-- Right Side - Travel Images Carousel Section (60%) -->
      <div class="w-3/5 md:block hidden relative overflow-hidden ">
        <!-- Top Right Logo -->
        <!-- <a href="https://azractivities.com/" target="_blank" class="absolute top-8 right-8 z-20">
          <div class="bg-white/20 backdrop-blur-sm rounded-lg px-4 py-2 cursor-pointer">
            <div class="text-white font-bold text-lg">TRAVEL</div>
            <div class="text-white/90 text-sm">WORLD</div>
          </div>
        </a> -->

        <!-- Carousel Container -->
        <div class="relative w-full h-screen flex flex-col justify-center items-center overflow-hidden">
          <!-- Main Heading -->

          <!-- Travel Images Carousel -->
          <div class="absolute inset-0 w-full h-full z-10">
            <div class="w-full h-full">
              <div class="flex transition-transform duration-500 ease-in-out h-full"
                :style="{ transform: `translateX(-${currentSlide * 100}%)` }">
                <div v-for="(slide, index) in promoImages" :key="index" class="min-w-full h-full relative">
                  <img v-if="slide?.is_home" :src="slide.url" :alt="slide.title" class="w-full h-full object-cover" />
                  <!--  -->
                  
                </div>
              </div>
            </div>

            <!-- Carousel Navigation -->
            <button @click="previousSlide"
              class="absolute left-4 top-1/2 transform -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full p-3 transition-all duration-200 z-20">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>

            <button @click="nextSlide"
              class="absolute right-4 top-1/2 transform -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm rounded-full p-3 transition-all duration-200 z-20">
              <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <!-- Carousel Indicators -->
          <div class="absolute bottom-16 flex space-x-3 z-20">
            <button v-for="(slide, index) in totalSlides" :key="index" @click="currentSlide = index"
              class="w-3 h-3 rounded-full transition-all duration-200"
              :class="currentSlide === index ? 'bg-white' : 'bg-white/50 hover:bg-white/70'"></button>
          </div>

          <!-- Bottom Contact Info -->
          <!-- <div class="absolute bottom-8 left-8 text-white z-20">
            <div class="text-lg font-semibold mb-2">Contact us</div>
            <div class="text-3xl font-bold">+923111448111</div>
          </div> -->
        </div>

        <!-- Decorative Elements -->
        <div class="absolute top-20 left-20 w-32 h-32 rounded-full bg-white/10 blur-xl"></div>
        <div class="absolute bottom-32 right-32 w-24 h-24 rounded-full bg-white/10 blur-lg"></div>
        <div class="absolute top-1/2 left-10 w-16 h-16 rounded-full bg-white/5 blur-md"></div>
      </div>
    </div>

    <!-- Welcome Screen (when authenticated) -->

  </div>
</template>

<script setup>


import { useAuthStore } from '@/services/stores/auth'
import { ArrowLeft } from 'lucide-vue-next'
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue3-toastify'
import { FETCH_PROMO_IMAGES } from "@/services/store/actions.type";
import { useStore } from "vuex";

const store = useStore();
const Login = defineAsyncComponent(() => import('@/components/agent/auth/Login.vue'))
const Register = defineAsyncComponent(() => import('@/components/agent/auth/Register.vue'))
const OTPValidation = defineAsyncComponent(() => import('@/components/agent/auth/OTPValidation.vue'))
// Store and router setup
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const promoImages = computed(() => {
  const images = store.getters["promoImage/promoImageData"];
  if (!images || !Array.isArray(images) || images.length === 0) return [];
  return images.filter(img => img.is_home === true || img.is_home === 1);
});
// Form state
const formType = ref('login')
const showOtp = ref(false);
const userDetail = ref({})
const user = computed(() => authStore.user)
const isAuthenticated = computed(() => authStore.isAuthenticated)
watch(() => isAuthenticated, (newValue) => {
  if (isAuthenticated.value) {
    handleUserDashboard()
  }
}, { immediate: true })
// Dynamic component handling
const currentComponent = computed(() => {
  if (showOtp.value) {
    formType.value === 'otp'
    return OTPValidation

  }
  return formType.value === 'otp' ? OTPValidation : Register
})

const componentKey = computed(() => {
  if (showOtp.value) return 'otp' + formType.value
  return formType.value + Date.now() // Adding timestamp to force re-render
})

const componentProps = computed(() => {
  if (showOtp.value) {
    return {
      showOtpInput: showOtp.value,
      userDetails: userDetail.value
    }
  }
  return {}
})

// Carousel state
const slides = ref([
  {
    image: 'https://images.unsplash.com/photo-1509233725247-49e657c54213?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
    title: 'Island Escape Packages',
    description: 'Unwind in tropical bliss with our curated island tours',
    icon: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064'
  },
  {
    image: 'https://images.unsplash.com/photo-1519681393784-d120267933ba?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
    title: 'Alpine Journey Expeditions',
    description: 'Explore stunning mountain ranges with expert guides',
    icon: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'
  },
  {
    image: 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
    title: 'Urban Adventure Tours',
    description: 'Discover the pulse of iconic cities with our guided tours',
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'
  },
  {
    image: 'https://images.unsplash.com/photo-1516026672322-bc52d61a55d5?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80',
    title: 'Safari Quest Adventures',
    description: 'Embark on thrilling wildlife tours in exotic deserts',
    icon: 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z'
  }
])

// Fetch PromoImages from API

function fetchPromoImages() {
  store.dispatch("promoImage/" + FETCH_PROMO_IMAGES);
}
// const fetchPromoImages = async () => {
//   try {
//     const response = await store.dispatch("promoImage/" + FETCH_PROMO_IMAGES)
//     const data = await response.json()


//   } catch (error) {
//     console.error('Error fetching promo images:', error)
//   }
// }

const currentSlide = ref(0)
const totalSlides = computed(() => promoImages.value.filter(img => img.is_home === true || img.is_home === 1).length)
let carouselInterval = null


function handleLogout() {
  authStore.logout()
}

function handleUserDashboard() {
  if (!user?.value) return;

  const role = user.value.role;

  if (role === 'admin') {
    router.push({ name: 'Dashboard' });
  } else if (role === 'agent') {
    if (user.value.is_formFilled === 0) {
      router.push({ name: 'AddAgentDetails' });
    } else {
      router.push({ name: 'DashboardFlights' });
    }
  } else if (role === 'customer' || role === 'user') {
    router.push({ name: 'CustomerProfile' });
  } else {
    // This covers reservation, accounts, salesman and any custom roles created in the database
    router.push({ name: 'AdminCustomerBookings' });
  }
}

// Carousel functions
function nextSlide() {
  currentSlide.value = (currentSlide.value + 1) % totalSlides.value
}

function previousSlide() {
  currentSlide.value = currentSlide.value === 0 ? totalSlides.value - 1 : currentSlide.value - 1
}

// Airlines rotation animation (keeping original)
const rotation = ref(0)
const airlines = [
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/asdasdasdad-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/asdddd1-copy-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/chi33-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/dasd1-copy-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/qweqw-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/sssss-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/Untitasdled-1-copy-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/Untitlasded-1-copy-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/asdasd1-copy-min.png",
  "https://www.ahmadworldtravel.com/wp-content/uploads/2023/01/111-min.png",
]


let rotationInterval = null

onMounted(() => {
  // Fetch promo images when component mounts
  fetchPromoImages()

  if (route.query.verified) {
    toast("Your email has been verified successfully.", {
      type: "success",
    })
    const query = { ...route.query }
    delete query.verified
    router.replace({ query })
  }

  rotationInterval = setInterval(() => {
    rotation.value = (rotation.value + 0.2) % 360
  }, 50)

  // Auto-advance carousel
  carouselInterval = setInterval(() => {
    nextSlide()
  }, 5000) // Change slide every 5 seconds
})

onUnmounted(() => {
  if (rotationInterval) {
    clearInterval(rotationInterval)
  }
  if (carouselInterval) {
    clearInterval(carouselInterval)
  }
})


</script>

<style scoped>
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #a89666;
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: #8b7a5a;
}

/* Smooth transitions */
* {
  transition: all 0.2s ease;
}

/* Focus states */
input:focus {
  box-shadow: 0 0 0 3px rgba(168, 150, 102, 0.1);
}



/* Hover effects */
.group:hover {
  transform: translateY(-2px);
}

/* Animation for carousel */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(100px);
  }

  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.carousel-slide {
  animation: slideIn 0.5s ease-out;
}

/* Animation styles */
@keyframes fadeIn {
  from {
    opacity: 0;
  }

  to {
    opacity: 1;
  }
}

@keyframes slideDown {
  from {
    transform: translateY(-20px);
    opacity: 0;
  }

  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.group:hover {
  transform: translateY(-5px);
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
</style>