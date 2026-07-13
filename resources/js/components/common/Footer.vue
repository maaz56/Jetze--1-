<script setup>
import { Building, Facebook, Instagram, Mail, MapPin, Phone, Youtube } from "lucide-vue-next";

import { ref, computed, onMounted } from "vue";
import { useStore } from "vuex";
import { debounce } from "lodash";
import { FETCH_POPULAR_ROUTES, FETCH_TOP_AIRLINES } from "@/services/store/actions.type";

const store = useStore();
const routesData = computed(() => store.getters['cms/popularRoutes']);


const popularRoutes = computed(() => routesData.value.data || []);
const topAirlines = computed(() => store.getters['cms/topAirlines']);

const domesticPopularRoutes = computed(() => popularRoutes.value.filter(r => r.type === 'domestic'));
const internationalPopularRoutes = computed(() => popularRoutes.value.filter(r => r.type === 'international'));

const domesticAirlines = computed(() => (topAirlines.value.data || []).filter(a => a.type === 'domestic' && a.is_active));
const internationalAirlines = computed(() => (topAirlines.value.data || []).filter(a => a.type === 'international' && a.is_active));

const fetchPopularRoutes = debounce(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, {
        per_page: 50,
    });
}, 350);

const fetchTopAirlines = debounce(() => {
    store.dispatch('cms/' + FETCH_TOP_AIRLINES, {
        per_page: 100,
    });
}, 350);

function formatDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getPopularRouteDepartureDate(routeItem) {
  if (routeItem?.departure_date) {
    return routeItem.departure_date;
  }

  const daysToAdd = routeItem?.departure_plus_days === null || routeItem?.departure_plus_days === undefined || routeItem?.departure_plus_days === ''
    ? 1
    : Number(routeItem.departure_plus_days);
  const date = new Date();
  date.setDate(date.getDate() + (Number.isFinite(daysToAdd) ? daysToAdd : 1));
  return formatDate(date);
}

function getPopularRouteReturnDate(routeItem) {
  if (routeItem?.journey_type !== 'round') {
    return undefined;
  }

  if (routeItem?.return_date) {
    return routeItem.return_date;
  }

  const stayDays = Number(routeItem?.stay_duration_days);
  if (!Number.isFinite(stayDays)) {
    return undefined;
  }

  const [year, month, day] = getPopularRouteDepartureDate(routeItem).split('-').map(Number);
  const date = new Date(year, month - 1, day);
  date.setDate(date.getDate() + stayDays);
  return formatDate(date);
}

const getPopularRouteSearchLink = (routeItem) => ({
  path: `/popular-routes/${routeItem.id}`,
  query: {
    origin: routeItem.from_airport,
    destination: routeItem.to_airport,
    departure_date: getPopularRouteDepartureDate(routeItem),
    return_date: getPopularRouteReturnDate(routeItem),
    flightType: routeItem.journey_type === 'round' ? 'return' : 'one-way',
    cabin_class: routeItem.travel_class === 'business' ? 'C' : 'Y',
    adults: 1,
    children: 0,
    infants: 0,
  },
});

const scrollToTop = () => {
  window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
};


onMounted(() => {
  fetchPopularRoutes();
  fetchTopAirlines();
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
           
              <div class="md:col-span-2">
                <h3 class="text-lg font-bold text-gray-900 mb-4 uppercase tracking-wider">Top Airlines</h3>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <h4 class="text-sm font-bold text-primary mb-2">Domestic</h4>
                    <ul class="space-y-1">
                      <li v-for="airline in domesticAirlines" :key="airline.id">
                        <span class="text-gray-600 hover:text-primary transition-colors cursor-default text-sm">
                          {{ airline.name }}
                        </span>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <h4 class="text-sm font-bold text-primary mb-2">International</h4>
                    <ul class="space-y-1 grid grid-cols-1 md:grid-cols-2 gap-x-4">
                      <li v-for="airline in internationalAirlines" :key="airline.id">
                        <span class="text-gray-600 hover:text-primary transition-colors cursor-default text-sm">
                          {{ airline.name }}
                        </span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="md:col-span-2">
                <h3 class="text-lg font-bold text-gray-900 mb-4 uppercase tracking-wider">Popular Flight Routes</h3>
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <h4 class="text-sm font-bold text-primary mb-2">Domestic</h4>
                    <ul class="space-y-1">
                      <li v-for="route in domesticPopularRoutes" :key="route.id">
                        <router-link :to="getPopularRouteSearchLink(route)" @click="scrollToTop"
                           class="text-gray-600 hover:text-primary transition-colors text-sm">
                          {{ route.from_city }} to {{ route.to_city }}
                        </router-link>
                      </li>
                    </ul>
                  </div>
                  <div>
                    <h4 class="text-sm font-bold text-primary mb-2">International</h4>
                    <ul class="space-y-1">
                      <li v-for="route in internationalPopularRoutes" :key="route.id">
                        <router-link :to="getPopularRouteSearchLink(route)" @click="scrollToTop"
                           class="text-gray-600 hover:text-primary transition-colors text-sm">
                          {{ route.from_city }} to {{ route.to_city }}
                        </router-link>
                      </li>
                    </ul>
                  </div>
                </div>
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
                   <li>
                    <a href="/blogs" 
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Blogs
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
