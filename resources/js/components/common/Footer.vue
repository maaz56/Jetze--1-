<script setup>
import { Building, Mail, MapPin, Phone, Plane } from "lucide-vue-next";

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

// Global offices — one card per branch, airport-code badge kept as a small
// nod to the travel business without breaking from the site's light/blue look.
const offices = [
  {
    code: 'LHE',
    tag: 'Head Office',
    city: 'Lahore, Pakistan',
    address: 'Office No. 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Main Boulevard, Gulberg III, Lahore 54660, Pakistan.',
    phone: '+92 300 7690691',
    tel: '+923007690691',
  },
  {
    code: 'DXB',
    tag: 'Regional Office',
    city: 'Dubai, UAE',
    address: 'Office 14, First Floor, Dubai National Insurance Building, Opposite Deira City Centre, Port Saeed, Deira, Dubai, United Arab Emirates.',
    phone: '+971 54 5299909',
    tel: '+971545299909',
  },
  {
    code: 'MNL',
    tag: 'Regional Office',
    city: 'Manila, Philippines',
    address: 'Corporate Plaza, High Street South, Makati City 1630, Metro Manila, Philippines.',
    phone: '+63 908 3986939',
    tel: '+639083986939',
  },
];

onMounted(() => {
  fetchPopularRoutes();
  fetchTopAirlines();
});
</script>

<template>
  <!-- Main Footer -->
  <footer class="bg-gray-100 text-gray-800">

    <!-- ============ Global Offices ============ -->
    <div class="py-12 bg-white border-b border-gray-100">
      <div class="container mx-auto px-4">
        <div class="flex items-center gap-2 mb-8">
          <Plane class="w-5 h-5 text-primary" />
          <h2 class="text-lg font-bold text-gray-900">Our Offices</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="office in offices" :key="office.code"
               class="border border-gray-200 rounded-lg p-6 hover:border-primary/40 hover:shadow-md transition-all duration-200">
            <div class="flex items-center mb-4">
              <div class="bg-primary p-3 text-white mr-4">
                <Building class="w-5 h-5" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-base font-bold text-gray-900">{{ office.code }}</span>
                  <span class="text-xs font-medium text-primary bg-primary/10 rounded px-2 py-0.5">{{ office.tag }}</span>
                </div>
                <p class="text-sm text-gray-500">{{ office.city }}</p>
              </div>
            </div>

            <p class="text-gray-600 text-sm flex gap-2 mb-3">
              <MapPin class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
              <span>{{ office.address }}</span>
            </p>

            <a :href="`tel:${office.tel}`"
               class="text-sm font-medium text-gray-800 hover:text-primary transition-colors flex items-center gap-2">
              <Phone class="w-4 h-4 text-gray-400" />
              <span>{{ office.phone }}</span>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Main Footer Content ============ -->
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

            <div class="space-y-2 mb-8">
              <a href="mailto:support@Jetze.pk" class="flex items-center gap-2 text-gray-600 hover:text-primary transition-colors text-sm">
                <Mail class="w-4 h-4 text-primary" />
                <span>support@Jetze.pk</span>
              </a>
              <a href="tel:+923007690691" class="flex items-center gap-2 text-gray-600 hover:text-primary transition-colors text-sm">
                <Phone class="w-4 h-4 text-primary" />
                <span>UAN (+92) 300 7690691</span>
              </a>
            </div>

            <!-- Social Media -->
            <div class="flex space-x-4 mb-8">
              <a href="https://www.tiktok.com/@user8107574618184?is_from_webapp=1&sender_device=pc" target="_blank" rel="noopener noreferrer" aria-label="TikTok"
                 class="bg-white border border-gray-200 hover:border-primary p-3 transition-all duration-200">
                <img src="/public/assets/tiktok.png" alt="TikTok" class="w-8 h-8 object-contain" />
              </a>
              <a href="https://www.facebook.com/share/1CDZXjBz3Y/" target="_blank"
                 rel="noopener noreferrer" aria-label="Facebook"
                 class="bg-white border border-gray-200 hover:border-primary p-1 transition-all duration-200">
                <img src="/public/assets/fb.png" alt="Facebook" class="w-12 h-12 object-contain" />
              </a>
              <a href="https://www.instagram.com/jetze.pk?igsh=MWllOTRpNTVwMm5peA==" target="_blank"
                 rel="noopener noreferrer" aria-label="Instagram"
                 class="bg-white border border-gray-200 hover:border-primary p-3 transition-all duration-200">
                <img src="/public/assets/instagram.png" alt="Instagram" class="w-8 h-8 object-contain" />
              </a>
              <a href="#" target="_blank"
                 rel="noopener noreferrer" aria-label="YouTube"
                 class="bg-white border border-gray-200 hover:border-primary p-1 transition-all duration-200">
                <img src="/public/assets/yt.png" alt="YouTube" class="w-12 h-12 object-contain" />
              </a>
            </div>
          </div>

          <!-- Quick Links -->
          <div class="lg:col-span-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">

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
                <ul class="space-y-1">
                  <li>
                    <a href="/about/us"
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      About Us
                    </a>
                  </li>
                  <li>
                    <a href="/contact/us"
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Contact Us
                    </a>
                  </li>
                  <li>
                    <a href="/blogs"
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      Blogs
                    </a>
                  </li>
                </ul>
              </div>

              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-2">Help</h3>
                <!-- <ul class="space-y-1">
                  <li>
                    <a href="/how-to-use-abhi-pay-bank-transfer"
                      class="text-gray-600 hover:text-primary hover:font-medium transition-all duration-200 inline-block">
                      How To Use AbhiPay&nbsp;Bank Transfer
                    </a>
                  </li>
                </ul> -->

                <h3 class="text-lg font-bold text-gray-900 mb-2 mt-8">Legal</h3>
                <ul class="space-y-1">
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

            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Bottom Copyright Bar ============ -->
    <div class="bg-gray-900 text-center py-8">
      <div class="container mx-auto px-4">
        <div class="flex flex-col md:flex-row justify-between items-center">
          <p class="text-gray-400 mb-4 md:mb-0">
            © {{ new Date().getFullYear() }} <span class="text-white font-medium">Jetze</span>. All Rights Reserved.
          </p>
          <p class="text-gray-500 text-sm tracking-wide">Lahore &middot; Dubai &middot; Manila</p>
        </div>
      </div>
    </div>
  </footer>
</template>
