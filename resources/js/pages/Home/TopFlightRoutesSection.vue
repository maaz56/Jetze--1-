<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import { RouterLink } from 'vue-router';
import { ChevronDown, ChevronUp } from 'lucide-vue-next';
import { FETCH_POPULAR_ROUTES, FETCH_TOP_AIRLINES } from '@/services/store/actions.type';

const store = useStore();

const dynamicRoutes = computed(() => {
    const data = store.getters['cms/popularRoutes'];
    const routes = data?.data?.data ?? data?.data;
    return Array.isArray(routes) ? routes : [];
});

const dynamicAirlines = computed(() => {
    const data = store.getters['cms/topAirlines'];
    const airlines = data?.data?.data ?? data?.data;
    return Array.isArray(airlines) ? airlines : [];
});

onMounted(() => {
    if (!dynamicRoutes.value.length) {
        store.dispatch('cms/' + FETCH_POPULAR_ROUTES, { per_page: 50 });
    }
    if (!dynamicAirlines.value.length) {
        store.dispatch('cms/' + FETCH_TOP_AIRLINES, { per_page: 50 });
    }
});

const dummyRoutes = [
    { from_city: 'Lahore', to_city: 'Karachi', from_airport: 'LHE', to_airport: 'KHI', title: 'lahore to karachi flight' },
    { from_city: 'Islamabad', to_city: 'Jeddah', from_airport: 'ISB', to_airport: 'JED', title: 'islamabad to jeddah flight' },
    { from_city: 'Karachi', to_city: 'Dubai', from_airport: 'KHI', to_airport: 'DXB', title: 'karachi to dubai flight' },
    { from_city: 'Lahore', to_city: 'Dubai', from_airport: 'LHE', to_airport: 'DXB', title: 'lahore to dubai flight' },
    { from_city: 'Islamabad', to_city: 'Karachi', from_airport: 'ISB', to_airport: 'KHI', title: 'islamabad to karachi flight' },
    { from_city: 'Peshawar', to_city: 'Dubai', from_airport: 'PEW', to_airport: 'DXB', title: 'peshawar to dubai flight' },
    { from_city: 'Islamabad', to_city: 'London', from_airport: 'ISB', to_airport: 'LHR', title: 'islamabad to london flight' },
    { from_city: 'Lahore', to_city: 'Manchester', from_airport: 'LHE', to_airport: 'MAN', title: 'lahore to manchester flight' },
    { from_city: 'Karachi', to_city: 'Riyadh', from_airport: 'KHI', to_airport: 'RUH', title: 'karachi to riyadh flight' },
    { from_city: 'Islamabad', to_city: 'Medinah', from_airport: 'ISB', to_airport: 'MED', title: 'islamabad to medinah flight' },
    { from_city: 'Lahore', to_city: 'Istanbul', from_airport: 'LHE', to_airport: 'IST', title: 'lahore to istanbul flight' },
    { from_city: 'Sialkot', to_city: 'Sharjah', from_airport: 'SKT', to_airport: 'SHJ', title: 'sialkot to sharjah flight' },
    { from_city: 'Multan', to_city: 'Dubai', from_airport: 'MUX', to_airport: 'DXB', title: 'multan to dubai flight' },
    { from_city: 'Karachi', to_city: 'Islamabad', from_airport: 'KHI', to_airport: 'ISB', title: 'karachi to islamabad flight' },
    { from_city: 'Lahore', to_city: 'Jeddah', from_airport: 'LHE', to_airport: 'JED', title: 'lahore to jeddah flight' },
    { from_city: 'Islamabad', to_city: 'Toronto', from_airport: 'ISB', to_airport: 'YYZ', title: 'islamabad to toronto flight' },
    { from_city: 'Karachi', to_city: 'London', from_airport: 'KHI', to_airport: 'LHR', title: 'karachi to london flight' },
    { from_city: 'Lahore', to_city: 'Riyadh', from_airport: 'LHE', to_airport: 'RUH', title: 'lahore to riyadh flight' },
    { from_city: 'Islamabad', to_city: 'Abu Dhabi', from_airport: 'ISB', to_airport: 'AUH', title: 'islamabad to abu dhabi flight' },
    { from_city: 'Peshawar', to_city: 'Riyadh', from_airport: 'PEW', to_airport: 'RUH', title: 'peshawar to riyadh flight' }
];

const mergedRoutes = computed(() => {
    const adminRoutesFormatted = dynamicRoutes.value.map(r => ({
        id: r.id,
        from_city: r.from_city_name || r.from_airport || 'Origin',
        to_city: r.to_city_name || r.to_airport || 'Destination',
        from_airport: r.from_airport || 'LHE',
        to_airport: r.to_airport || 'KHI',
        journey_type: r.journey_type,
        travel_class: r.travel_class,
        departure_date: r.departure_date,
        departure_plus_days: r.departure_plus_days,
        title: `${(r.from_city_name || r.from_airport || '').toLowerCase()} to ${(r.to_city_name || r.to_airport || '').toLowerCase()} flight`
    }));

    const combined = [...adminRoutesFormatted];
    dummyRoutes.forEach(dummy => {
        const exists = combined.some(r => 
            (r.from_airport === dummy.from_airport && r.to_airport === dummy.to_airport) ||
            (r.title.toLowerCase() === dummy.title.toLowerCase())
        );
        if (!exists) {
            combined.push(dummy);
        }
    });
    return combined;
});

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getDepartureDate(routeItem) {
    if (routeItem.departure_date) return routeItem.departure_date;
    const daysToAdd = Number(routeItem.departure_plus_days) || 3;
    const date = new Date();
    date.setDate(date.getDate() + daysToAdd);
    return formatDate(date);
}

const getRouteSearchLink = (route) => {
    if (route.id) {
        return {
            path: `/popular-routes/${route.id}`,
            query: {
                origin: route.from_airport,
                destination: route.to_airport,
                departure_date: getDepartureDate(route),
                flightType: route.journey_type === 'round' ? 'return' : 'one-way',
                cabin_class: route.travel_class === 'business' ? 'C' : 'Y',
                adults: 1,
            }
        };
    }
    return {
        path: '/flight/search',
        query: {
            origin: route.from_airport,
            destination: route.to_airport,
            departure_date: getDepartureDate(route),
            flightType: 'one-way',
            cabin_class: 'Y',
            adults: 1,
        }
    };
};

const dummyAirlines = [
    { name: 'Emirates', code: 'EK' },
    { name: 'Qatar Airways', code: 'QR' },
    { name: 'PIA', code: 'PK' },
    { name: 'Saudia', code: 'SV' },
    { name: 'Airblue', code: 'PA' },
    { name: 'Flydubai', code: 'FZ' },
    { name: 'Etihad Airways', code: 'EY' },
    { name: 'Turkish Airlines', code: 'TK' },
    { name: 'Gulf Air', code: 'GF' },
    { name: 'Kuwait Airways', code: 'KU' },
    { name: 'Oman Air', code: 'WY' },
    { name: 'Air Arabia', code: 'G9' },
    { name: 'SriLankan Airlines', code: 'UL' },
    { name: 'Thai Airways', code: 'TG' },
    { name: 'AirSial', code: 'PF' },
    { name: 'Serene Air', code: 'ER' }
];

const mergedAirlines = computed(() => {
    const adminAirlines = (dynamicAirlines.value || [])
        .filter(a => a.is_active !== false)
        .map(a => ({ name: a.name || a.airline_name || a.airline?.name, code: a.code || a.airline_code || a.airline?.iata_code }))
        .filter(a => a.name && a.code);

    const combined = [...adminAirlines];
    dummyAirlines.forEach(d => {
        const exists = combined.some(a => a.name.toLowerCase() === d.name.toLowerCase());
        if (!exists) {
            combined.push(d);
        }
    });
    return combined;
});

const isRoutesExpanded = ref(false);
const isAirlinesExpanded = ref(false);

const displayedRoutes = computed(() => {
    if (isRoutesExpanded.value) return mergedRoutes.value;
    return mergedRoutes.value.slice(0, 8);
});

const displayedAirlines = computed(() => {
    if (isAirlinesExpanded.value) return mergedAirlines.value;
    return mergedAirlines.value.slice(0, 10);
});
</script>

<template>
    <section class="w-full bg-slate-200/60 border-t border-gray-300/60 py-8 sm:py-12 px-3 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto space-y-6 sm:space-y-8 text-gray-700">
            
            <!-- TOP FLIGHT ROUTES -->
            <div>
                <h3 class="text-xs sm:text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-2.5">
                    TOP FLIGHT ROUTES
                </h3>
                
                <!-- Mobile Chips Layout -->
                <div class="sm:hidden">
                    <div class="flex flex-wrap gap-1.5">
                        <RouterLink 
                            v-for="(route, index) in displayedRoutes" 
                            :key="index"
                            :to="getRouteSearchLink(route)" 
                            class="inline-flex items-center gap-1 rounded-lg border border-slate-300/80 bg-white/90 px-2.5 py-1 text-xs text-slate-700 shadow-2xs hover:border-primary hover:bg-white hover:text-primary transition-all capitalize active:scale-95"
                        >
                            <span>{{ route.from_city }}</span>
                            <span class="text-slate-400 text-[10px]">✈</span>
                            <span>{{ route.to_city }}</span>
                        </RouterLink>
                    </div>
                    <button 
                        v-if="mergedRoutes.length > 8"
                        @click="isRoutesExpanded = !isRoutesExpanded" 
                        class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline focus:outline-none"
                    >
                        <span>{{ isRoutesExpanded ? 'Show fewer routes' : `Show all ${mergedRoutes.length} routes` }}</span>
                        <component :is="isRoutesExpanded ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Desktop Text List -->
                <p class="hidden sm:block text-xs sm:text-sm text-gray-600 leading-relaxed font-normal">
                    <template v-for="(route, index) in mergedRoutes" :key="index">
                        <RouterLink 
                            :to="getRouteSearchLink(route)" 
                            class="hover:text-primary hover:underline transition-colors lowercase font-normal"
                        >
                            {{ route.title }}
                        </RouterLink>
                        <span v-if="index < mergedRoutes.length - 1" class="text-gray-400 mr-1.5">, </span>
                    </template>
                </p>
            </div>

            <!-- AIRLINES -->
            <div>
                <h3 class="text-xs sm:text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-2.5">
                    AIRLINES
                </h3>

                <!-- Mobile Chips Layout -->
                <div class="sm:hidden">
                    <div class="flex flex-wrap gap-1.5">
                        <RouterLink 
                            v-for="(airline, index) in displayedAirlines" 
                            :key="index"
                            :to="{ path: '/flight/search', query: { airline: airline.code } }" 
                            class="inline-flex items-center rounded-lg border border-slate-300/80 bg-white/90 px-2.5 py-1 text-xs font-medium text-slate-700 shadow-2xs hover:border-primary hover:bg-white hover:text-primary transition-all active:scale-95"
                        >
                            {{ airline.name }}
                        </RouterLink>
                    </div>
                    <button 
                        v-if="mergedAirlines.length > 10"
                        @click="isAirlinesExpanded = !isAirlinesExpanded" 
                        class="mt-2.5 inline-flex items-center gap-1 text-xs font-bold text-primary hover:underline focus:outline-none"
                    >
                        <span>{{ isAirlinesExpanded ? 'Show fewer airlines' : `Show all ${mergedAirlines.length} airlines` }}</span>
                        <component :is="isAirlinesExpanded ? ChevronUp : ChevronDown" class="w-3.5 h-3.5" />
                    </button>
                </div>

                <!-- Desktop Text List -->
                <p class="hidden sm:block text-xs sm:text-sm text-gray-600 leading-relaxed font-normal">
                    <template v-for="(airline, index) in mergedAirlines" :key="index">
                        <RouterLink 
                            :to="{ path: '/flight/search', query: { airline: airline.code } }" 
                            class="hover:text-primary hover:underline transition-colors font-normal"
                        >
                            {{ airline.name }}
                        </RouterLink>
                        <span v-if="index < mergedAirlines.length - 1" class="text-gray-400 mr-1.5">, </span>
                    </template>
                </p>
            </div>

            <!-- ABOUT THE SITE -->
            <div>
                <h3 class="text-xs sm:text-sm font-extrabold text-gray-900 uppercase tracking-wider mb-2.5">
                    ABOUT THE SITE
                </h3>
                <div class="flex flex-wrap gap-x-3 gap-y-1.5 text-xs sm:text-sm text-gray-600 font-normal">
                    <RouterLink to="/about/us" class="hover:text-primary hover:underline transition-colors">About Us</RouterLink>
                    <span class="text-gray-400">•</span>
                    <RouterLink to="/contact/us" class="hover:text-primary hover:underline transition-colors">Contact Us</RouterLink>
                    <span class="text-gray-400">•</span>
                    <RouterLink to="/our/services" class="hover:text-primary hover:underline transition-colors">Our Services</RouterLink>
                    <span class="text-gray-400">•</span>
                    <RouterLink to="/how-to-use-abhi-pay-bank-transfer" class="hover:text-primary hover:underline transition-colors">How to Pay</RouterLink>
                    <span class="text-gray-400">•</span>
                    <RouterLink to="/privacy-policy" class="hover:text-primary hover:underline transition-colors">Privacy Policy</RouterLink>
                    <span class="text-gray-400">•</span>
                    <RouterLink to="/terms-condition" class="hover:text-primary hover:underline transition-colors">Terms &amp; Conditions</RouterLink>
                </div>
            </div>

            <!-- WHY JETZE & BOOKING FLIGHTS WITH JETZE -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-8 pt-2 pb-2">
                <div class="bg-white/70 rounded-xl p-4 sm:p-5 border border-slate-300/60 shadow-2xs">
                    <h3 class="text-sm sm:text-base font-extrabold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-primary shrink-0"></span>
                        Why Jetze?
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 leading-relaxed font-normal">
                        Established as Pakistan's premier online travel platform, Jetze has positioned itself as the go-to destination for modern travelers by providing great offers, competitive airfares, exclusive discounts, and a seamless online booking experience. Booking flight tickets, luxury hotel stays, Umrah packages, and vacation tours through our web or mobile app can be done with complete ease and zero hassle. We deliver amazing value with low fare alerts, instant discounts, transparent pricing, and flexible payment solutions.
                    </p>
                </div>

                <div class="bg-white/70 rounded-xl p-4 sm:p-5 border border-slate-300/60 shadow-2xs">
                    <h3 class="text-sm sm:text-base font-extrabold text-gray-900 mb-2 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
                        Booking Flights with Jetze
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 leading-relaxed font-normal">
                        At Jetze, you can find the best travel deals and cheap air tickets to any destination worldwide by booking on our web or mobile platform. Comparing fares across 450+ leading domestic and international airlines, Jetze helps you secure affordable flights customized to your travel schedule. With customer satisfaction as our priority, our 24/7 dedicated support team is always on standby to assist with queries, ticket reissues, and travel assistance.
                    </p>
                </div>
            </div>
        </div>
        
    </section>
</template>
