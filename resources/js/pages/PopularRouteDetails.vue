<template>
  <div class="min-h-screen bg-slate-50 flex flex-col justify-between">
    <div class="flex-1">
      <!-- LOADING STATE -->
      <div v-if="isLoading" class="animate-pulse">
        <div class="h-[360px] sm:h-[440px] bg-slate-800 w-full relative">
          <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-24 space-y-4">
            <div class="h-4 w-40 bg-slate-700 rounded"></div>
            <div class="h-10 w-2/3 max-w-lg bg-slate-700 rounded"></div>
            <div class="h-6 w-1/3 bg-slate-700 rounded"></div>
          </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 sm:-mt-20">
          <div class="bg-white rounded-2xl shadow-lg border border-slate-200 p-6 h-36"></div>
          <div class="mt-12 space-y-6">
            <div class="h-6 w-48 bg-slate-200 rounded"></div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div v-for="i in 4" :key="i" class="h-28 bg-white border border-slate-200 rounded-xl"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ERROR / NOT FOUND STATE -->
      <div v-else-if="!routeDetails" class="py-24 text-center max-w-xl mx-auto px-4">
        <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4 text-slate-400">
          <Plane class="w-8 h-8 rotate-45" />
        </div>
        <h1 class="text-2xl font-bold text-slate-900 mb-2">Route Not Found</h1>
        <p class="text-slate-600 mb-6 text-sm">
          The requested flight route might have been moved or is no longer active.
        </p>
        <router-link
          to="/"
          class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-primary text-white font-semibold text-sm hover:bg-primary/90 transition shadow-sm"
        >
          Return to Home
        </router-link>
      </div>

      <!-- MAIN ROUTE CONTENT -->
      <div v-else>
        <!-- 1. HERO SECTION -->
        <section class="relative h-[380px] sm:h-[460px] overflow-hidden bg-slate-900 text-white">
          <!-- Background Image -->
          <img
            :src="heroImageUrl"
            :alt="`${destinationCity} skyline`"
            class="absolute inset-0 w-full h-full object-cover object-center scale-105 filter brightness-75 transition-transform duration-1000 ease-out"
          />

          <!-- Gradient overlay -->
          <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-900/40"></div>

          <!-- Hero Content Container -->
          <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex flex-col justify-start pt-10 sm:pt-14 z-10">
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-xs sm:text-sm text-slate-300 mb-4" aria-label="Breadcrumb">
              <router-link to="/" class="hover:text-white transition-colors">Home</router-link>
              <ChevronRight class="w-3.5 h-3.5 text-slate-400" />
              <router-link to="/flight/search" class="hover:text-white transition-colors">Flights</router-link>
              <ChevronRight class="w-3.5 h-3.5 text-slate-400" />
              <span class="text-white font-medium truncate">
                {{ originCity }} to {{ destinationCity }}
              </span>
            </nav>

            <!-- Badges -->
            <div class="flex items-center gap-2 mb-3 flex-wrap">
              <span class="px-3 py-1 bg-white/20 backdrop-blur-md rounded-full text-xs font-semibold uppercase tracking-wider text-white">
                {{ journeyTypeLabel }}
              </span>
              <span v-if="routeDetails.type" class="px-3 py-1 bg-primary text-white rounded-full text-xs font-semibold uppercase tracking-wider">
                {{ routeDetails.type }}
              </span>
              <span v-if="routeDetails.travel_class" class="px-3 py-1 bg-white/10 backdrop-blur-md rounded-full text-xs font-medium text-slate-200 capitalize">
                {{ routeDetails.travel_class }} Class
              </span>
            </div>

            <!-- Page H1 -->
            <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight text-white mb-3">
              Cheap Flights from {{ originCity }} to {{ destinationCity }}
            </h1>

            <!-- Subtitle with Live Price indicator -->
            <div class="flex items-center gap-3 text-sm sm:text-base text-slate-200 flex-wrap">
              <span class="font-mono bg-white/15 px-2 py-0.5 rounded text-xs font-semibold tracking-wider">
                {{ originIata }} ✈ {{ destinationIata }}
              </span>
              <span>·</span>
              <span v-if="hasValidPrice" class="font-semibold text-emerald-400">
                from {{ formattedPrice }} / person
              </span>
              <span v-else class="text-slate-300">
                Live flight fares on request
              </span>
              <span v-if="priceUpdatedAgo" class="text-xs text-slate-400">
                ({{ priceUpdatedAgo }})
              </span>
            </div>
          </div>
        </section>

        <!-- 2. COMPACT SEARCH PREFILL BOX -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 sm:-mt-24 relative z-20">
          <div class="bg-white rounded-2xl shadow-xl border border-slate-200/90 p-5 sm:p-7 backdrop-blur-md">
            <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
              <!-- Route Details Summary -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 flex-1">
                <!-- Origin -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 font-bold text-sm">
                    {{ originIata }}
                  </div>
                  <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">From</span>
                    <span class="text-sm font-bold text-slate-900 truncate block">{{ originCity }}</span>
                  </div>
                </div>

                <!-- Destination -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0 font-bold text-sm">
                    {{ destinationIata }}
                  </div>
                  <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">To</span>
                    <span class="text-sm font-bold text-slate-900 truncate block">{{ destinationCity }}</span>
                  </div>
                </div>

                <!-- Dates -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-slate-200/60 text-slate-600 flex items-center justify-center shrink-0">
                    <Calendar class="w-5 h-5" />
                  </div>
                  <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Departure</span>
                    <span class="text-sm font-bold text-slate-900 truncate block">{{ formattedDepartureDate }}</span>
                  </div>
                </div>

                <!-- Cabin & Journey -->
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-100 flex items-center gap-3">
                  <div class="w-10 h-10 rounded-lg bg-slate-200/60 text-slate-600 flex items-center justify-center shrink-0">
                    <Users class="w-5 h-5" />
                  </div>
                  <div class="min-w-0">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Travel Details</span>
                    <span class="text-sm font-bold text-slate-900 truncate block">1 Adult · {{ cabinLabel }}</span>
                  </div>
                </div>
              </div>

              <!-- Big CTA Search Button -->
              <div class="flex flex-col sm:flex-row items-center gap-3 shrink-0">
                <button
                  type="button"
                  @click="goToFlightSearch"
                  class="w-full sm:w-auto px-8 py-4 rounded-xl bg-primary hover:bg-primary/95 active:scale-[0.98] text-white font-bold text-sm sm:text-base shadow-lg hover:shadow-primary/25 transition-all duration-200 flex items-center justify-center gap-2 group"
                >
                  <span>Search Flights</span>
                  <ArrowRight class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. ROUTE FACTS STRIP -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
          <h2 class="text-xl font-bold text-slate-900 mb-4 flex items-center gap-2">
            <Info class="w-5 h-5 text-primary" />
            <span>Route Key Facts</span>
          </h2>

          <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Fact 1: Price -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
              <span class="text-xs font-medium text-slate-500 block mb-1">Cheapest Fare Found</span>
              <div class="mt-1">
                <RoutePrice :price="routeDetails.price" :journey-type="routeDetails.journey_type" />
              </div>
            </div>

            <!-- Fact 2: Airline -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
              <div>
                <span class="text-xs font-medium text-slate-500 block mb-1">Carrier</span>
                <div class="flex items-center gap-2 mt-2">
                  <div
                    v-if="routeDetails.airline?.logo_url"
                    class="w-6 h-6 rounded-full overflow-hidden bg-slate-100 shrink-0 border border-slate-200"
                  >
                    <img :src="routeDetails.airline.logo_url" :alt="routeDetails.airline.name" class="w-full h-full object-contain" />
                  </div>
                  <span class="text-base font-bold text-slate-900 truncate">
                    {{ routeDetails.airline?.name || 'Multiple Airlines' }}
                  </span>
                </div>
              </div>
              <span class="text-xs text-slate-400 mt-2 block">{{ routeDetails.airline?.iata ? `IATA: ${routeDetails.airline.iata}` : 'Direct & Connecting' }}</span>
            </div>

            <!-- Fact 3: Flight Duration -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
              <div>
                <span class="text-xs font-medium text-slate-500 block mb-1">Estimated Flight Time</span>
                <span class="text-xl font-bold text-slate-900 block mt-2">
                  {{ durationLabel }}
                </span>
              </div>
              <span class="text-xs text-slate-400 mt-2 block">Varies by schedule & stops</span>
            </div>

            <!-- Fact 4: Stops & Frequency -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col justify-between">
              <div>
                <span class="text-xs font-medium text-slate-500 block mb-1">Stops</span>
                <span class="text-xl font-bold text-slate-900 block mt-2">
                  {{ stopsLabel }}
                </span>
              </div>
              <span class="text-xs text-slate-400 mt-2 block">Daily departures available</span>
            </div>
          </div>
        </section>

        <!-- 4. BLOGS / ARTICLES SECTION -->
        <section v-if="sanitizedBlogs.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-14">
          <div class="space-y-10">
            <div
              v-for="(blog, index) in sanitizedBlogs"
              :key="index"
              class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden"
            >
              <div class="bg-slate-50/80 px-6 sm:px-8 py-5 border-b border-slate-100 flex items-center gap-3">
                <BookOpen class="w-5 h-5 text-primary shrink-0" />
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900">
                  {{ blog.title }}
                </h2>
              </div>
              <div class="p-6 sm:p-8">
                <div class="prose max-w-none text-slate-700 text-base sm:text-lg leading-relaxed quill-content" v-html="blog.content"></div>
              </div>
            </div>
          </div>
        </section>

        <!-- 5. FAQS SECTION WITH SCHEMA -->
        <section v-if="routeDetails.faqs && routeDetails.faqs.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-14">
          <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm">
            <div class="mb-6">
              <span class="text-xs font-bold text-primary uppercase tracking-wider block mb-1">Frequently Asked Questions</span>
              <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                Flights from {{ originCity }} to {{ destinationCity }} FAQ
              </h2>
            </div>

            <Accordion type="single" collapsible class="space-y-3">
              <AccordionItem
                v-for="(faq, index) in routeDetails.faqs"
                :key="`faq-${index}`"
                :value="`faq-${index}`"
                class="border border-slate-200 rounded-xl overflow-hidden bg-slate-50/50"
              >
                <AccordionTrigger class="px-5 py-4 text-left font-bold text-slate-900 hover:text-primary transition-colors text-base sm:text-lg">
                  {{ faq.question }}
                </AccordionTrigger>
                <AccordionContent class="px-5 pb-5 pt-1 text-slate-700 text-sm sm:text-base leading-relaxed bg-white">
                  <div class="quill-content" v-html="sanitizeHtml(faq.answer)"></div>
                </AccordionContent>
              </AccordionItem>
            </Accordion>
          </div>
        </section>

        <!-- 6. RELATED ROUTES SECTION -->
        <section v-if="routeDetails.related_routes && routeDetails.related_routes.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16 mb-20">
          <div class="mb-6">
            <span class="text-xs font-bold text-primary uppercase tracking-wider block mb-1">More Recommendations</span>
            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
              Related Flight Deals
            </h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <PopularRouteCard
              v-for="rel in routeDetails.related_routes"
              :key="rel.id"
              :route="rel"
            />
          </div>
        </section>

        <!-- 7. STICKY MOBILE CTA -->
        <div class="sm:hidden fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-md border-t border-slate-200 p-3 px-4 z-50 flex items-center justify-between shadow-2xl">
          <div>
            <span class="text-[11px] text-slate-500 font-medium block">Starting from</span>
            <span v-if="hasValidPrice" class="text-base font-bold text-primary">
              {{ formattedPrice }}
            </span>
            <span v-else class="text-sm font-bold text-primary">
              Check live fares
            </span>
          </div>

          <button
            type="button"
            @click="goToFlightSearch"
            class="px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold shadow-md hover:bg-primary/95 transition flex items-center gap-1.5"
          >
            <span>Search Flights</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import {
  Plane,
  ChevronRight,
  Calendar,
  Users,
  ArrowRight,
  Info,
  BookOpen,
} from 'lucide-vue-next';
import apiService from '@/services/store/apiService';
import { formatAmount, getSelectedCurrencyCode } from '@/lib/utils';
import RoutePrice from '@/components/popular-routes/RoutePrice.vue';
import PopularRouteCard from '@/components/popular-routes/PopularRouteCard.vue';
import {
  Accordion,
  AccordionItem,
  AccordionTrigger,
  AccordionContent,
} from '@/components/ui/accordion';

const route = useRoute();
const router = useRouter();

const isLoading = ref(true);
const routeDetails = ref(null);

// HTML sanitizer helper
const sanitizeHtml = (html) => {
  if (!html || typeof html !== 'string') return '';
  return html
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '')
    .replace(/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/gi, '')
    .replace(/on\w+="[^"]*"/gi, '')
    .replace(/on\w+='[^']*'/gi, '')
    .replace(/javascript:/gi, '');
};

const stripHtml = (html) => {
  if (!html || typeof html !== 'string') return '';
  return html.replace(/<[^>]*>?/gm, '').trim();
};

const originIata = computed(() => routeDetails.value?.from?.iata || routeDetails.value?.from_airport || 'LHE');
const destinationIata = computed(() => routeDetails.value?.to?.iata || routeDetails.value?.to_airport || 'DXB');

const originCity = computed(() => routeDetails.value?.from?.city || originIata.value);
const destinationCity = computed(() => {
  return (
    routeDetails.value?.destination_name ||
    routeDetails.value?.to?.city ||
    destinationIata.value
  );
});

const heroImageUrl = computed(() => {
  if (routeDetails.value?.image?.url) return routeDetails.value.image.url;
  if (routeDetails.value?.image_url) return routeDetails.value.image_url;
  if (routeDetails.value?.image) return `/storage/${routeDetails.value.image}`;
  return 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=1600&auto=format&fit=crop&q=80';
});

const journeyTypeLabel = computed(() => {
  const jt = (routeDetails.value?.journey_type || '').toLowerCase();
  return jt === 'round' ? 'Round Trip' : 'One Way';
});

const cabinLabel = computed(() => {
  const c = (routeDetails.value?.travel_class || '').toLowerCase();
  if (c === 'business') return 'Business';
  if (c === 'first') return 'First';
  return 'Economy';
});

const stopsLabel = computed(() => {
  const stops = routeDetails.value?.meta?.stops;
  if (stops === 0) return 'Non-stop Direct';
  if (stops === 1) return '1 Stop';
  if (stops > 1) return `${stops} Stops`;
  return 'Direct / 1 Stop';
});

const durationLabel = computed(() => {
  const mins = routeDetails.value?.meta?.duration_minutes;
  if (!mins) return 'Approx. 3-5 Hours';
  const h = Math.floor(mins / 60);
  const m = mins % 60;
  return m > 0 ? `${h}h ${m}m` : `${h}h`;
});

const hasValidPrice = computed(() => {
  const p = routeDetails.value?.price;
  return p && Number(p.amount) > 0;
});

const formattedPrice = computed(() => {
  if (!hasValidPrice.value) return '';
  return formatAmount(routeDetails.value.price.amount, routeDetails.value.price.currency);
});

const priceUpdatedAgo = computed(() => {
  if (!routeDetails.value?.price?.updated_at) return '';
  const d = new Date(routeDetails.value.price.updated_at);
  if (isNaN(d.getTime())) return '';
  const mins = Math.floor((new Date().getTime() - d.getTime()) / (1000 * 60));
  if (mins < 1) return 'updated just now';
  if (mins < 60) return `updated ${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `updated ${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  return `updated ${days}d ago`;
});

const formattedDepartureDate = computed(() => {
  const dep = routeDetails.value?.search?.departure_date;
  if (!dep) return 'Flexible';
  const d = new Date(dep);
  if (isNaN(d.getTime())) return dep;
  return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
});

const sanitizedBlogs = computed(() => {
  const list = routeDetails.value?.blogs || [];
  return list.map((b) => ({
    title: b.title,
    content: sanitizeHtml(b.content),
  }));
});

// JSON-LD injection and SEO
let scriptElement = null;

const applySeoAndStructuredData = (data) => {
  if (!data) return;
  const seo = data.seo || {};

  const title =
    seo.meta_title ||
    `Cheap Flights from ${originCity.value} to ${destinationCity.value} | Jetze`;
  const desc =
    seo.meta_description ||
    `Compare and book cheap flight tickets from ${originCity.value} (${originIata.value}) to ${destinationCity.value} (${destinationIata.value}). Best fare deals updated daily on Jetze.`;

  document.title = title;

  const setMeta = (name, content) => {
    if (!content) return;
    let el = document.querySelector(`meta[name="${name}"]`);
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute('name', name);
      document.head.appendChild(el);
    }
    el.setAttribute('content', content);
  };

  const setOg = (property, content) => {
    if (!content) return;
    let el = document.querySelector(`meta[property="${property}"]`);
    if (!el) {
      el = document.createElement('meta');
      el.setAttribute('property', property);
      document.head.appendChild(el);
    }
    el.setAttribute('content', content);
  };

  setMeta('description', desc);
  setMeta('keywords', seo.meta_keywords || `${originCity.value} to ${destinationCity.value} flights, cheap air tickets, Jetze`);
  setOg('og:title', seo.og_title || title);
  setOg('og:description', seo.og_description || desc);
  if (seo.og_image_url || data.image?.url) {
    setOg('og:image', seo.og_image_url || data.image.url);
  }

  // Canonical link
  const canonicalUrl = seo.canonical_url || (typeof window !== 'undefined' ? window.location.href.split('?')[0] : '');
  let linkEl = document.querySelector('link[rel="canonical"]');
  if (!linkEl) {
    linkEl = document.createElement('link');
    linkEl.setAttribute('rel', 'canonical');
    document.head.appendChild(linkEl);
  }
  linkEl.setAttribute('href', canonicalUrl);

  // Structured Data (JSON-LD)
  const currentUrl = typeof window !== 'undefined' ? window.location.href : '';
  const breadcrumbSchema = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      {
        '@type': 'ListItem',
        position: 1,
        name: 'Home',
        item: typeof window !== 'undefined' ? window.location.origin : '',
      },
      {
        '@type': 'ListItem',
        position: 2,
        name: 'Flights',
        item: typeof window !== 'undefined' ? `${window.location.origin}/flight/search` : '',
      },
      {
        '@type': 'ListItem',
        position: 3,
        name: `${originCity.value} to ${destinationCity.value}`,
        item: currentUrl,
      },
    ],
  };

  const schemas = [breadcrumbSchema];

  if (data.faqs && data.faqs.length > 0) {
    const faqSchema = {
      '@context': 'https://schema.org',
      '@type': 'FAQPage',
      mainEntity: data.faqs.map((f) => ({
        '@type': 'Question',
        name: f.question,
        acceptedAnswer: {
          '@type': 'Answer',
          text: stripHtml(f.answer),
        },
      })),
    };
    schemas.push(faqSchema);
  }

  if (scriptElement) {
    scriptElement.remove();
  }
  scriptElement = document.createElement('script');
  scriptElement.id = 'route-json-ld';
  scriptElement.type = 'application/ld+json';
  scriptElement.text = JSON.stringify(schemas);
  document.head.appendChild(scriptElement);
};

const fetchRouteDetails = async () => {
  isLoading.value = true;
  const identifier = route.params.slug || route.params.id;

  try {
    const currency = getSelectedCurrencyCode() || 'AED';
    const response = await apiService.getPublicPopularRoute(identifier, { currency });

    if (response?.data?.data) {
      routeDetails.value = response.data.data;
      applySeoAndStructuredData(routeDetails.value);

      // Backwards compatible clean URL canonical redirect if accessed via id
      if (route.params.id && routeDetails.value.slug) {
        router.replace({
          name: 'PopularRouteBySlug',
          params: { slug: routeDetails.value.slug },
        });
      }
    } else {
      routeDetails.value = null;
    }
  } catch (err) {
    console.error('Failed to load route details:', err);
    routeDetails.value = null;
  } finally {
    isLoading.value = false;
  }
};

const goToFlightSearch = () => {
  const search = routeDetails.value?.search || {};
  router.push({
    path: '/flight/search',
    query: {
      airline: 'AT',
      origin: search.origin || originIata.value,
      destination: search.destination || destinationIata.value,
      departure_date: search.departure_date || '',
      return_date: search.return_date || '',
      flightType: search.flight_type || (routeDetails.value?.journey_type === 'round' ? 'return' : 'one-way'),
      cabin_class: search.cabin_class || (routeDetails.value?.travel_class === 'business' ? 'C' : 'Y'),
      adults: 1,
      children: 0,
      infants: 0,
    },
  });
};

const onCurrencyChange = () => {
  fetchRouteDetails();
};

onMounted(() => {
  fetchRouteDetails();
  if (typeof window !== 'undefined') {
    window.addEventListener('currency-changed', onCurrencyChange);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('currency-changed', onCurrencyChange);
  }
  if (scriptElement) {
    scriptElement.remove();
  }
});

watch(
  () => route.params.slug || route.params.id,
  (newVal, oldVal) => {
    if (newVal && newVal !== oldVal) {
      fetchRouteDetails();
    }
  }
);
</script>

<style scoped>
.quill-content :deep(p) {
  margin-bottom: 1rem;
}
.quill-content :deep(h1),
.quill-content :deep(h2),
.quill-content :deep(h3) {
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  font-weight: 700;
  color: #0f172a;
}
.quill-content :deep(ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}
.quill-content :deep(ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}
.quill-content :deep(a) {
  color: hsl(var(--primary));
  text-decoration: underline;
}
</style>
