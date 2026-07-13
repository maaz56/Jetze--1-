<template>
  <div>
    <FlightSearch />

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <div v-else-if="routeDetails" class="bg-gray-50 py-12">
      <div class="container mx-auto px-4">
        <div v-if="routeDetails.blogs && routeDetails.blogs.length > 0" class="space-y-8 mb-10">
          <div 
            v-for="(blog, index) in routeDetails.blogs" 
            :key="index"
            class="bg-white border border-gray-200 rounded-sm shadow-sm overflow-hidden"
          >
            <div class="bg-gray-50/80 px-6 py-4 border-b border-gray-200">
              <h2 class="text-xl font-semibold font-medium text-primary">{{ blog.title }}</h2>
            </div>
            <div class="p-6">
              <!-- Render the Quill HTML content safely -->
              <div class="prose max-w-none text-gray-700 text-lg leading-relaxed quill-content" v-html="blog.content"></div>
            </div>
          </div>
        </div>

        <div v-if="routeDetails.faqs && routeDetails.faqs.length > 0" class="space-y-4">
          <h2 class="text-3xl font-semibold text-primary">
            FAQs of Cheap Flight {{ routeDetails.from_city || routeDetails.from_airport }} to {{ routeDetails.to_city || routeDetails.to_airport }}
          </h2>

          <Accordion type="single" collapsible class="space-y-3">
            <AccordionItem
              v-for="(faq, index) in routeDetails.faqs"
              :key="`faq-${index}`"
              :value="`faq-${index}`"
              class="bg-white border border-gray-300"
            >
              <AccordionTrigger class="px-4 py-4 text-lg bg-gray-50/80 text-left font-medium">
                {{ faq.question }}
              </AccordionTrigger>
              <AccordionContent class="p-4 pb-4">
                <div class="quill-content text-gray-700 text-lg" v-html="faq.answer"></div>
              </AccordionContent>
            </AccordionItem>
          </Accordion>
        </div>

        <div v-if="(!routeDetails.blogs || routeDetails.blogs.length === 0) && (!routeDetails.faqs || routeDetails.faqs.length === 0)" class="text-center text-gray-500 py-12">
          No detailed information available for this route yet.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import Spinner from "@/components/common/Spinner.vue";
import apiService from '@/services/store/apiService';
import { toast } from 'vue3-toastify';
import FlightSearch from './FlightSearch.vue';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';

const route = useRoute();
const isLoading = ref(true);
const routeDetails = ref(null);

const setMetaTag = (selector, attributes) => {
  let element = document.head.querySelector(selector);
  if (!element) {
    element = document.createElement('meta');
    document.head.appendChild(element);
  }
  Object.entries(attributes).forEach(([key, value]) => element.setAttribute(key, value));
};

const setCanonicalLink = (href) => {
  let element = document.head.querySelector('link[rel="canonical"]');
  if (!href) {
    element?.remove();
    return;
  }
  if (!element) {
    element = document.createElement('link');
    element.setAttribute('rel', 'canonical');
    document.head.appendChild(element);
  }
  element.setAttribute('href', href);
};

const applyRouteSeo = (routeData) => {
  const seo = routeData?.seo || {};
  const title = seo.meta_title || `Cheap Flights ${routeData.from_city || routeData.from_airport} to ${routeData.to_city || routeData.to_airport}`;
  const description = seo.meta_description || seo.og_description || '';
  const robots = [
    seo.no_index ? 'noindex' : 'index',
    seo.no_follow ? 'nofollow' : 'follow',
  ].join(', ');

  document.title = title;
  setMetaTag('meta[name="description"]', { name: 'description', content: description });
  setMetaTag('meta[name="keywords"]', { name: 'keywords', content: seo.meta_keywords || '' });
  setMetaTag('meta[name="robots"]', { name: 'robots', content: robots });
  setMetaTag('meta[property="og:title"]', { property: 'og:title', content: seo.og_title || title });
  setMetaTag('meta[property="og:description"]', { property: 'og:description', content: seo.og_description || description });
  if (seo.og_image_url || routeData.image_url) {
    setMetaTag('meta[property="og:image"]', { property: 'og:image', content: seo.og_image_url || routeData.image_url });
  }
  setCanonicalLink(seo.canonical_url);
};

const fetchRouteDetails = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.getPopularRoute(route.params.id);
    if (response.data && response.data.data) {
      routeDetails.value = response.data.data;
      applyRouteSeo(routeDetails.value);
    } else {
      throw new Error('Invalid response');
    }
  } catch (error) {
    toast.error('Failed to load popular route details.');
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchRouteDetails();
});
</script>

<style>
/* Add any specific styling for quill content if needed */
.quill-content p {
  margin-bottom: 1rem;
}
.quill-content h1, .quill-content h2, .quill-content h3 {
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
  font-weight: 600;
  color: #1f2937;
}
.quill-content ul {
  list-style-type: disc;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}
.quill-content ol {
  list-style-type: decimal;
  padding-left: 1.5rem;
  margin-bottom: 1rem;
}
.quill-content a {
  color: #2563eb;
  text-decoration: underline;
}
</style>
