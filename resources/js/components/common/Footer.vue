<script setup>
import { 
    Building, 
    Mail, 
    MapPin, 
    Phone, 
    Plane, 
    ShieldCheck, 
    Zap, 
    Headphones, 
    Globe, 
    CheckCircle2, 
    ArrowRight,
    Award
} from "lucide-vue-next";

import { ref, computed, onMounted } from "vue";
import { useStore } from "vuex";
import { debounce } from "lodash";
import { FETCH_POPULAR_ROUTES, FETCH_TOP_AIRLINES } from "@/services/store/actions.type";

const store = useStore();
const routesData = computed(() => store.getters['cms/popularRoutes']);

const popularRoutes = computed(() => routesData.value.data || []);
const topAirlines = computed(() => store.getters['cms/topAirlines']);

const domesticPopularRoutes = computed(() => popularRoutes.value.filter(r => r.type === 'domestic').slice(0, 6));
const internationalPopularRoutes = computed(() => popularRoutes.value.filter(r => r.type === 'international').slice(0, 6));

const domesticAirlines = computed(() => (topAirlines.value.data || []).filter(a => a.type === 'domestic' && a.is_active).slice(0, 5));
const internationalAirlines = computed(() => (topAirlines.value.data || []).filter(a => a.type === 'international' && a.is_active).slice(0, 5));

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

const offices = [
  {
    code: 'LHE',
    tag: 'Head Office',
    city: 'Lahore, Pakistan',
    address: 'Office No. 305, 3rd Floor, Big City Plaza, Liberty Roundabout, Main Boulevard, Gulberg III, Lahore.',
    phone: '+92 300 7690691',
    tel: '+923007690691',
  },
  {
    code: 'DXB',
    tag: 'Regional Office',
    city: 'Dubai, UAE',
    address: 'Office 14, 1st Floor, Dubai National Insurance Bldg, Opp Deira City Centre, Port Saeed, Deira, Dubai.',
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
  <footer class="bg-[#070e1c] text-slate-200 font-roboto border-t border-slate-800/90 antialiased">

    <!-- ============ Top Trust Highlights Strip ============ -->
    <div class="border-b border-slate-800/90 bg-[#040812] py-8 sm:py-10 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded bg-blue-500/15 border border-blue-500/30 flex items-center justify-center text-blue-400 shrink-0">
            <ShieldCheck class="w-6 h-6" />
          </div>
          <div>
            <h4 class="text-sm sm:text-base font-bold text-white">100% Safe & Secure</h4>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Encrypted payment gateway</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded bg-orange-500/15 border border-orange-500/30 flex items-center justify-center text-orange-400 shrink-0">
            <Zap class="w-6 h-6" />
          </div>
          <div>
            <h4 class="text-sm sm:text-base font-bold text-white">Instant E-Ticketing</h4>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Fast PNR confirmation</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded bg-purple-500/15 border border-purple-500/30 flex items-center justify-center text-purple-400 shrink-0">
            <Plane class="w-6 h-6" />
          </div>
          <div>
            <h4 class="text-sm sm:text-base font-bold text-white">450+ Airline Partners</h4>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Direct global wholesale rates</p>
          </div>
        </div>

        <div class="flex items-center gap-3.5">
          <div class="w-12 h-12 rounded bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shrink-0">
            <Headphones class="w-6 h-6" />
          </div>
          <div>
            <h4 class="text-sm sm:text-base font-bold text-white">24/7 Dedicated Support</h4>
            <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Real agents always on standby</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Main Footer Content ============ -->
    <div class="py-14 sm:py-18 px-4 sm:px-6 lg:px-8">
      <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8 lg:gap-10">
          
          <!-- Column 1: Brand & Direct Contact (4 Cols) -->
          <div class="lg:col-span-4">
            <div class="mb-6">
              <div class="inline-block bg-white p-2.5 rounded shadow-xs mb-4">
                <img src="/public/assets/logo.png" class="h-10 w-auto object-contain" alt="Jetze Logo" />
              </div>
              <p class="text-sm sm:text-[15px] text-slate-300 leading-relaxed max-w-sm">
                Your Journey, Simplified. Pakistan's trusted online travel platform for domestic & international flights, luxury stays, Umrah packages, and fast-track visas.
              </p>
            </div>

            <!-- Direct Contact Details -->
            <div class="space-y-2.5 mb-6 text-sm sm:text-[15px]">
              <a href="mailto:support@jetze.pk" class="flex items-center gap-3 text-slate-300 hover:text-white transition-colors">
                <Mail class="w-4 h-4 text-orange-400 shrink-0" />
                <span>support@jetze.pk</span>
              </a>
              <a href="tel:+923007690691" class="flex items-center gap-3 text-slate-300 hover:text-white transition-colors">
                <Phone class="w-4 h-4 text-blue-400 shrink-0" />
                <span>UAN: (+92) 300 7690691</span>
              </a>
              <div class="pt-1">
                <a href="/contact/us" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-400 hover:text-blue-300 transition-colors">
                  <span>View All Contact & Support Options</span>
                  <ArrowRight class="w-3.5 h-3.5" />
                </a>
              </div>
            </div>

            <!-- Social Media Icons -->
            <div class="flex items-center space-x-2.5">
              <a 
                href="https://www.tiktok.com/@user8107574618184" 
                target="_blank" 
                rel="noopener noreferrer" 
                aria-label="TikTok"
                class="w-10 h-10 rounded bg-slate-800/90 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center transition-colors shadow-2xs"
              >
                <img src="/public/assets/tiktok.png" alt="TikTok" class="w-4 h-4 object-contain" />
              </a>
              <a 
                href="https://www.facebook.com/share/1CDZXjBz3Y/" 
                target="_blank" 
                rel="noopener noreferrer" 
                aria-label="Facebook"
                class="w-10 h-10 rounded bg-slate-800/90 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center transition-colors shadow-2xs"
              >
                <img src="/public/assets/fb.png" alt="Facebook" class="w-4 h-4 object-contain" />
              </a>
              <a 
                href="https://www.instagram.com/jetze.pk" 
                target="_blank" 
                rel="noopener noreferrer" 
                aria-label="Instagram"
                class="w-10 h-10 rounded bg-slate-800/90 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center transition-colors shadow-2xs"
              >
                <img src="/public/assets/instagram.png" alt="Instagram" class="w-4 h-4 object-contain" />
              </a>
              <a 
                href="#" 
                target="_blank" 
                rel="noopener noreferrer" 
                aria-label="YouTube"
                class="w-10 h-10 rounded bg-slate-800/90 hover:bg-slate-700 border border-slate-700/80 flex items-center justify-center transition-colors shadow-2xs"
              >
                <img src="/public/assets/yt.png" alt="YouTube" class="w-4 h-4 object-contain" />
              </a>
            </div>
          </div>

          <!-- Column 2: Company & About Links (2 Cols) -->
          <div class="lg:col-span-2">
            <h3 class="text-sm sm:text-base font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2.5">
              Company
            </h3>
            <ul class="space-y-2.5 text-sm sm:text-[15px]">
              <li>
                <a href="/about/us" class="text-slate-300 hover:text-white transition-colors flex items-center gap-1.5 font-medium">
                  About Us
                </a>
              </li>
              <li>
                <a href="/contact/us" class="text-slate-300 hover:text-white transition-colors flex items-center gap-1.5 font-medium">
                  Contact Us
                </a>
              </li>
              <li>
                <router-link to="/our/services" class="text-slate-300 hover:text-white transition-colors">
                  Our Services
                </router-link>
              </li>
              <li>
                <router-link to="/how-to-use-abhi-pay-bank-transfer" class="text-slate-300 hover:text-white transition-colors">
                  How to Pay (AbhiPay)
                </router-link>
              </li>
              <li>
                <a href="/blogs" class="text-slate-300 hover:text-white transition-colors">
                  Travel Blogs
                </a>
              </li>
              <li>
                <router-link to="/privacy-policy" class="text-slate-300 hover:text-white transition-colors">
                  Privacy Policy
                </router-link>
              </li>
              <li>
                <router-link to="/terms-condition" class="text-slate-300 hover:text-white transition-colors">
                  Terms & Conditions
                </router-link>
              </li>
            </ul>
          </div>

          <!-- Column 3: Travel Services & Airlines (3 Cols) -->
          <div class="lg:col-span-3">
            <h3 class="text-sm sm:text-base font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2.5">
              Travel Services
            </h3>
            <ul class="space-y-2.5 text-sm sm:text-[15px] mb-6">
              <li>
                <router-link to="/flight/search" class="text-slate-300 hover:text-white transition-colors">
                  Flight Bookings
                </router-link>
              </li>
              <li>
                <router-link to="/hotel/search" class="text-slate-300 hover:text-white transition-colors">
                  Hotels & Resorts
                </router-link>
              </li>
              <li>
                <router-link to="/holidays" class="text-slate-300 hover:text-white transition-colors">
                  Holiday Packages
                </router-link>
              </li>
              <li>
                <router-link to="/umra-packages" class="text-slate-300 hover:text-white transition-colors">
                  Umrah Packages
                </router-link>
              </li>
              <li>
                <router-link to="/visa" class="text-slate-300 hover:text-white transition-colors">
                  Visa Assistance
                </router-link>
              </li>
              <li>
                <router-link to="/group-tickets-main" class="text-slate-300 hover:text-white transition-colors">
                  Group Charters
                </router-link>
              </li>
              <li>
                <router-link to="/travel-insurance" class="text-slate-300 hover:text-white transition-colors">
                  Travel Insurance
                </router-link>
              </li>
            </ul>

            <h4 class="text-xs sm:text-sm font-bold text-white uppercase tracking-wider mb-2">
              Featured Airlines
            </h4>
            <div class="flex flex-wrap gap-2">
              <span class="text-xs bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded text-slate-300">Emirates</span>
              <span class="text-xs bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded text-slate-300">Qatar Airways</span>
              <span class="text-xs bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded text-slate-300">PIA</span>
              <span class="text-xs bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded text-slate-300">Saudia</span>
              <span class="text-xs bg-slate-800/90 border border-slate-700/80 px-2.5 py-1 rounded text-slate-300">Airblue</span>
            </div>
          </div>

          <!-- Column 4: Global Offices (3 Cols) -->
          <div class="lg:col-span-3">
            <h3 class="text-sm sm:text-base font-bold text-white uppercase tracking-wider mb-4 border-b border-slate-800 pb-2.5">
              Global Offices
            </h3>
            <div class="space-y-3.5">
              <div 
                v-for="office in offices" 
                :key="office.code"
                class="bg-slate-900/95 rounded border border-slate-800 p-3 text-xs sm:text-sm"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-white text-sm">{{ office.city }}</span>
                    <span class="text-[10px] font-mono font-bold bg-primary/20 text-blue-300 px-1.5 py-0.5 rounded">{{ office.code }}</span>
                  </div>
                  <span class="text-xs text-slate-400">{{ office.tag }}</span>
                </div>
                <p class="text-xs sm:text-sm text-slate-300 leading-relaxed mb-2">
                  {{ office.address }}
                </p>
                <a :href="`tel:${office.tel}`" class="text-xs sm:text-sm font-semibold text-blue-400 hover:text-blue-300 flex items-center gap-1.5">
                  <Phone class="w-3.5 h-3.5" />
                  <span>{{ office.phone }}</span>
                </a>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- ============ Bottom Copyright & Accreditation Bar ============ -->
    <div class="bg-[#030710] py-6 px-4 sm:px-6 lg:px-8 border-t border-slate-800/90">
      <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-xs sm:text-sm text-slate-400">
        <div class="flex items-center gap-2.5 flex-wrap justify-center md:justify-start">
          <span>© {{ new Date().getFullYear() }} <strong class="text-white">Jetze</strong>. All Rights Reserved.</span>
          <span class="hidden sm:inline text-slate-700">|</span>
          <span class="hidden sm:inline text-slate-400">IATA Accredited • DTS Licensed</span>
        </div>

        <div class="flex items-center gap-4 sm:gap-6 text-xs sm:text-sm flex-wrap justify-center">
          <a href="/about/us" class="hover:text-white transition-colors font-medium">About Us</a>
          <a href="/contact/us" class="hover:text-white transition-colors font-medium">Contact Us</a>
          <a href="/blogs" class="hover:text-white transition-colors font-medium">Blogs</a>
          <router-link to="/privacy-policy" class="hover:text-white transition-colors">Privacy Policy</router-link>
          <router-link to="/terms-condition" class="hover:text-white transition-colors">Terms & Conditions</router-link>
        </div>
      </div>
    </div>

  </footer>
</template>
