<script setup>
import { Building, Facebook, Instagram, Mail, MapPin, Phone, Youtube } from "lucide-vue-next";

import { ref, computed, onMounted } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import { debounce } from "lodash";
import { FETCH_POPULAR_ROUTES } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const routesData = computed(() => store.getters['cms/popularRoutes']);


const popularRoutes = computed(() => routesData.value.data || []);
const fetchPopularRoutes = debounce(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

const date = new Date();
const formattedDate = new Intl.DateTimeFormat('en-CA', { 
    year: 'numeric', 
    month: '2-digit', 
    day: '2-digit' 
}).format(date);


onMounted(() => {
  fetchPopularRoutes();
});





</script>

<template>
  <!-- Main Footer - Clean Professional Design -->
  <footer class="bg-gray-100 text-gray-800">
    <!-- Top Contact Section -->
    <div class="py-12 bg-white">
      <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
          <!-- Phone -->
          <div class="text-center md:text-left">
            <div class="flex justify-center md:justify-start items-center mb-4">
              <div class="bg-primary p-3 text-white mr-4">
                <Phone class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-gray-900">Phone</h3>
            </div>
            <div class="space-y-2">
              <p class="text-gray-600">
                <span class="font-medium">UAN:</span> (+92) 0000000000
              </p>
              <p class="text-gray-600">
                <span class="font-medium">Complaints:</span> (+92) 0000000000
              </p>
            </div>
          </div>
          
          <!-- Address -->
          <div class="text-center md:text-left">
            <div class="flex justify-center md:justify-start items-center mb-4">
              <div class="bg-primary p-3 text-white mr-4">
                <MapPin class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-gray-900">Address</h3>
            </div>
            <p class="text-gray-600">
              Address1234<br>
              Address1234
            </p>
          </div>
          
          <!-- Company Info -->
          <div class="text-center md:text-left">
            <div class="flex justify-center md:justify-start items-center mb-4">
              <div class="bg-primary p-3 text-white mr-4">
                <Building class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-gray-900">Company Info</h3>
            </div>
            <p class="text-gray-600 text-sm">
              Established in 2012, ISO & IATA certified travel management company.
            </p>
          </div>
          
          <!-- Email -->
          <div class="text-center md:text-left">
            <div class="flex justify-center md:justify-start items-center mb-4">
              <div class="bg-primary p-3 text-white mr-4">
                <Mail class="w-5 h-5" />
              </div>
              <h3 class="text-lg font-bold text-gray-900">Our Emails</h3>
            </div>
            <div class="space-y-2">
              <p class="text-gray-600">
                <span class="font-medium">Support:</span> support@Jetze.pk
              </p>
              <p class="text-gray-600">
                <span class="font-medium">Finance:</span> 
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Footer Content -->
    <div class="py-16">
      <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
          <!-- Logo & Company Info -->
          <div class="lg:col-span-4">
            <div class="mb-8">
              <img src="/public/assets/logo.png" class="h-10 mb-6" alt="Jetze Logo" />
              <p class="text-gray-600 mb-8">
                We Provide All Type of Domestic and International Air Tickets. 
                Your trusted travel partner since 2012.
              </p>
            </div>
            
            <!-- Social Media -->
            <div class="flex space-x-4 mb-8">
              <a href="https://www.facebook.com/Jetzet/" target="_blank" 
                 class="bg-gray-100 hover:bg-primary hover:text-white p-3 text-gray-600 transition-all duration-200">
                <Facebook class="w-5 h-5" />
              </a>
              <a href="https://www.instagram.com/Jetze.pk/" target="_blank" 
                 class="bg-gray-100 hover:bg-primary hover:text-white p-3 text-gray-600 transition-all duration-200">
                <Instagram class="w-5 h-5" />
              </a>
              <a href="https://www.youtube.com/@Jetze" target="_blank" 
                 class="bg-gray-100 hover:bg-primary hover:text-white p-3 text-gray-600 transition-all duration-200">
                <Youtube class="w-5 h-5" />
              </a>
            </div>
            
            <!-- Partner Logos -->
            <!-- <div class="grid grid-cols-3 gap-4">
              <div class="p-4 bg-gray-50 flex items-center justify-center">
                <img src="/public/assets/logo_img_2.png" class="h-8 object-contain" alt="toursview" />
              </div>
              <div class="p-4 bg-gray-50 flex items-center justify-center">
                <img src="/public/assets/logo_img_3.png" class="h-8 object-contain" alt="uniquetravels" />
              </div>
              <div class="p-4 bg-gray-50 flex items-center justify-center">
                <img src="/public/assets/logo_img_7.png" class="h-8 object-contain" alt="minsa" />
              </div>
            </div> -->
          </div>
          
          <!-- Quick Links & Newsletter -->
          <div class="lg:col-span-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
              <!-- Company Links -->
           
              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Popular Flight Routes</h3>
                <ul>
                 <li  v-for="popularRoute in popularRoutes" :key="popularRoute.id">
                   <a :href="`/flight/search?origin=${popularRoute.from_airport}&destination=${popularRoute.to_airport}&departure_date=${formattedDate}`" 
                     target="_blank" 
                     class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                     {{ popularRoute.from_city }} To {{ popularRoute.to_city }}
                   </a>
                 </li>
                </ul>
              </div>

              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Company</h3>
                <ul class="">
                  <li>
                    <a href="/about/us" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      About Us
                    </a>
                    <!-- <router-link :to="{ name: 'AboutUs' }" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      About Us
                    </router-link> -->
                  </li>
                  <li>
                    <a href="/contact/us" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Contact Us
                    </a>
                    <!-- <router-link :to="{ name: 'ClientContactUs' }" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Contact Us
                    </router-link> -->
                  </li>
                </ul>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Help</h3>
                <ul class="">
                  <li>
                    <a href="/how-to-use-abhi-pay-bank-transfer" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      How To Use AbhiPay&nbsp;Bank Transfer
                    </a>
                    <!-- <router-link :to="{ name: 'HowToPay' }" 
                      class="text-gray-600 text-sm hover:text-primary hover:font-medium transition-all duration-200 inline-block">
How To Use AbhiPay&nbsp;Bank Transfer
                    </router-link> -->
                  </li>
                 
                </ul>
              </div>
              
              <!-- Legal Links -->
              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Legal</h3>
                <ul class="">
                  <li>
                    <a href="/privacy-policy" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Privacy Policy
                    </a>
                  </li>
                  <li>
                    <a href="#" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Licensing
                    </a>
                  </li>
                  <li>
                    <a href="/terms-condition" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Terms & Conditions
                    </a>
                  </li>
                </ul>
              </div>
              
              <!-- Newsletter -->
              
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Copyright Bar -->
    <div class="bg-gray-900 text-center py-8">
      <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <!-- Copyright -->
          <div class="mb-4 md:mb-0 text-center md:text-left">
            <p class="text-gray-400">
              © {{ new Date().getFullYear() }} <span class="text-white font-medium">Jetze</span>. All Rights Reserved.
            </p>
          </div>
          
         
        </div>
      </div>
    </div>
  </footer>
</template>