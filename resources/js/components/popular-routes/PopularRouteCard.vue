<template>
  <router-link
    :to="routeLink"
    class="group relative flex flex-col justify-between h-full bg-white rounded-2xl border border-slate-200/90 overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 select-none"
    :aria-label="accessibleLabel"
  >
    <!-- TOP: IMAGE + BADGES -->
    <div class="relative w-full aspect-[4/3] overflow-hidden bg-slate-100">
      <!-- Loading shimmer before image loads -->
      <div
        v-if="!imageLoaded && !imageFailed"
        class="absolute inset-0 bg-slate-200 animate-pulse"
      ></div>

      <!-- Main Image -->
      <img
        v-if="!imageFailed"
        :src="imageUrl"
        :srcset="imageSrcSet"
        sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
        :alt="route.image?.alt || `${destinationCity} city view`"
        loading="lazy"
        decoding="async"
        class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
        @load="imageLoaded = true"
        @error="imageFailed = true"
      />

      <!-- Fallback branded gradient on error -->
      <div
        v-else
        class="w-full h-full bg-gradient-to-br from-primary via-blue-800 to-indigo-950 flex flex-col items-center justify-center p-4 text-white text-center"
      >
        <Plane class="w-8 h-8 opacity-80 mb-2" />
        <span class="text-lg font-bold">{{ destinationCity }}</span>
        <span class="text-xs uppercase tracking-widest opacity-75 mt-1">{{ destinationIata }}</span>
      </div>

      <!-- Soft gradient overlay over image bottom -->
      <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/10 to-transparent pointer-events-none"></div>

      <!-- Badges on image -->
      <div class="absolute top-3 left-3 right-3 flex items-center justify-between pointer-events-none gap-2">
        <div class="flex items-center gap-1.5 flex-wrap">
          <span class="px-2.5 py-1 bg-white/95 backdrop-blur-sm text-slate-800 text-[11px] font-semibold rounded-full shadow-sm capitalize">
            {{ journeyTypeLabel }}
          </span>
          <span
            v-if="route.type"
            class="px-2.5 py-1 bg-black/60 backdrop-blur-sm text-white text-[11px] font-medium rounded-full shadow-sm capitalize"
          >
            {{ route.type }}
          </span>
        </div>

        <span
          v-if="isBestDeal"
          class="px-2.5 py-1 bg-amber-500 text-white text-[11px] font-bold rounded-full shadow-md uppercase tracking-wider flex items-center gap-1"
        >
          <Sparkles class="w-3 h-3" /> Best Deal
        </span>
      </div>
    </div>

    <!-- MIDDLE: ROUTE INFORMATION -->
    <div class="p-4 sm:p-5 flex flex-col justify-between flex-1 space-y-4">
      <div class="space-y-2.5">
        <!-- Origin -> Destination -->
        <div class="flex items-start justify-between gap-2">
          <h3 class="text-lg font-bold text-slate-900 group-hover:text-primary transition-colors line-clamp-1">
            {{ originCity }}
            <span class="text-primary font-normal mx-1">→</span>
            {{ destinationCity }}
          </h3>
        </div>

        <!-- IATA Codes -->
        <div class="flex items-center text-xs font-semibold text-slate-500 tracking-wider">
          <span>{{ originIata }}</span>
          <Plane class="w-3.5 h-3.5 mx-1.5 text-primary rotate-45" />
          <span>{{ destinationIata }}</span>
          <span v-if="destinationAirportName" class="text-slate-400 font-normal ml-2 truncate max-w-[160px]">
            · {{ destinationAirportName }}
          </span>
        </div>

        <!-- Airline & Stops -->
        <div class="flex items-center gap-2 pt-1 text-xs text-slate-600">
          <div
            v-if="route.airline?.logo_url"
            class="w-5 h-5 rounded-full overflow-hidden bg-slate-100 flex items-center justify-center shrink-0 border border-slate-200"
          >
            <img
              :src="route.airline.logo_url"
              :alt="route.airline.name || 'Airline'"
              class="w-full h-full object-contain"
              loading="lazy"
            />
          </div>
          <span class="font-medium text-slate-800 truncate">
            {{ route.airline?.name || 'Multiple airlines' }}
          </span>
          <span class="text-slate-400">·</span>
          <span class="text-slate-500 shrink-0">{{ stopsLabel }}</span>
        </div>

        <!-- Dates & Cabin Class -->
        <div class="flex items-center text-xs text-slate-500 pt-0.5">
          <Calendar class="w-3.5 h-3.5 mr-1.5 text-slate-400 shrink-0" />
          <span class="truncate">{{ datesLabel }} · {{ cabinLabel }}</span>
        </div>
      </div>

      <!-- BOTTOM: PRICE & SEARCH CTA -->
      <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-3">
        <RoutePrice
          :price="route.price"
          :journey-type="route.journey_type"
        />

        <button
          type="button"
          @click.prevent.stop="goToSearch"
          class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary hover:bg-primary/95 active:scale-[0.98] text-white text-xs sm:text-sm font-semibold shadow-sm hover:shadow-md transition-all duration-200 shrink-0 group/btn"
          aria-label="Search flights for this route"
        >
          <span>Search</span>
          <ArrowRight class="w-3.5 h-3.5 transition-transform group-hover/btn:translate-x-0.5" />
        </button>
      </div>
    </div>
  </router-link>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useRouter } from 'vue-router';
import { Plane, Calendar, ArrowRight, Sparkles } from 'lucide-vue-next';
import RoutePrice from './RoutePrice.vue';

const props = defineProps({
  route: {
    type: Object,
    required: true,
  },
  isBestDeal: {
    type: Boolean,
    default: false,
  },
});

const router = useRouter();

const imageLoaded = ref(false);
const imageFailed = ref(false);

const originIata = computed(() => props.route.from?.iata || props.route.from_airport || 'LHE');
const destinationIata = computed(() => props.route.to?.iata || props.route.to_airport || 'DXB');

const originCity = computed(() => props.route.from?.city || props.route.from_city || originIata.value);
const destinationCity = computed(() => {
  return (
    props.route.destination_name ||
    props.route.to?.city ||
    props.route.to_city ||
    destinationIata.value
  );
});

const destinationAirportName = computed(() => {
  return props.route.to?.airport || null;
});

const imageUrl = computed(() => {
  const url = props.route.image?.url || props.route.image_url;
  if (url) {
    if (url.includes('/storage/http://') || url.includes('/storage/https://')) {
      return url.replace(/^.*\/storage\/(https?:\/\/)/, '$1');
    }
    return url;
  }
  if (props.route.image) {
    if (props.route.image.startsWith('http://') || props.route.image.startsWith('https://')) {
      return props.route.image;
    }
    return `/storage/${props.route.image}`;
  }
  return '/assets/no-data.webp';
});

const imageSrcSet = computed(() => {
  const thumb = props.route.image?.thumb_url;
  const full = imageUrl.value;
  if (thumb && full && !thumb.includes('/storage/http')) {
    return `${thumb} 640w, ${full} 1280w`;
  }
  return undefined;
});

const journeyTypeLabel = computed(() => {
  const jt = (props.route.journey_type || '').toLowerCase();
  return jt === 'round' ? 'Round trip' : 'One way';
});

const stopsLabel = computed(() => {
  const stops = props.route.meta?.stops;
  if (stops === 0) return 'Non-stop';
  if (stops === 1) return '1 stop';
  if (stops > 1) return `${stops} stops`;
  return 'Direct / 1 stop';
});

const cabinLabel = computed(() => {
  const c = (props.route.travel_class || '').toLowerCase();
  if (c === 'business') return 'Business';
  if (c === 'first') return 'First';
  return 'Economy';
});

const formatShortDate = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short' }).format(d);
};

const datesLabel = computed(() => {
  const dep = props.route.search?.departure_date;
  const ret = props.route.search?.return_date;
  if (dep && ret && (props.route.journey_type || '').toLowerCase() === 'round') {
    return `${formatShortDate(dep)} – ${formatShortDate(ret)}`;
  }
  if (dep) {
    return formatShortDate(dep);
  }
  return 'Flexible dates';
});

const routeLink = computed(() => {
  if (props.route.slug) {
    return `/flights/${props.route.slug}`;
  }
  return `/popular-routes/${props.route.id}`;
});

const accessibleLabel = computed(() => {
  return `Flights from ${originCity.value} to ${destinationCity.value}`;
});

const goToSearch = () => {
  const search = props.route.search || {};
  router.push({
    path: '/flight/search',
    query: {
      airline: 'AT',
      origin: search.origin || originIata.value,
      destination: search.destination || destinationIata.value,
      departure_date: search.departure_date || '',
      return_date: search.return_date || '',
      flightType: search.flight_type || (props.route.journey_type === 'round' ? 'return' : 'one-way'),
      cabin_class: search.cabin_class || (props.route.travel_class === 'business' ? 'C' : 'Y'),
      adults: 1,
      children: 0,
      infants: 0,
    },
  });
};
</script>
