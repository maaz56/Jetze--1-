<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import apiService from '@/services/store/apiService';
import Spinner from '@/components/common/Spinner.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';

const route = useRoute();
const router = useRouter();

const isLoading = ref(false);
const data = ref(null);

const fetchRoute = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.getPopularRoute(route.params.id);
    data.value = response.data?.data || null;
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Failed to load popular route');
    router.push({ name: 'PopularRoutes' });
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchRoute);
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Button variant="outline" @click="router.push({ name: 'PopularRoutes' })">
          <ArrowLeft class="w-4 h-4 mr-1" />
          Back
        </Button>
        <h1 class="text-2xl font-semibold">Popular Route Details</h1>
      </div>

      <Button v-if="data" @click="router.push({ name: 'PopularRouteEdit', params: { id: data.id } })">
        <Pencil class="w-4 h-4 mr-1" />
        Edit
      </Button>
    </div>

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <div v-else-if="data" class="bg-white border rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="space-y-3">
        <p><span class="font-medium">From:</span> {{ data.from_airport }} ({{ data.from_city }})</p>
        <p><span class="font-medium">To:</span> {{ data.to_airport }} ({{ data.to_city }})</p>
        <p><span class="font-medium">Journey Type:</span> {{ data.journey_type }}</p>
        <p><span class="font-medium">Travel Class:</span> {{ data.travel_class }}</p>
        <p><span class="font-medium">Airline:</span> {{ data.airline?.name || 'Any' }}</p>
      </div>

      <div class="space-y-3">
        <p><span class="font-medium">Price Type:</span> {{ data.price_type }}</p>
        <p><span class="font-medium">Static Price:</span> {{ data.static_price ?? 'N/A' }}</p>
        <p><span class="font-medium">Dynamic Refresh Hours:</span> {{ data.dynamic_refresh_hours ?? 'N/A' }}</p>
        <p><span class="font-medium">Departure Date:</span> {{ data.departure_date }}</p>
        <p><span class="font-medium">Return Date:</span> {{ data.return_date ?? 'N/A' }}</p>
        <p><span class="font-medium">Departure Plus Days:</span> {{ data.departure_plus_days }}</p>
        <p><span class="font-medium">Stay Duration:</span> {{ data.stay_duration_days ?? 'N/A' }}</p>
      </div>

      <div class="md:col-span-2 space-y-3">
        <p><span class="font-medium">Destination Name (EN):</span> {{ data.destination_name_en }}</p>
        <p><span class="font-medium">Destination Name (AR):</span> {{ data.destination_name_ar }}</p>
        <div>
          <p class="font-medium mb-2">Image</p>
          <img v-if="data.image_url" :src="data.image_url" alt="Destination" class="w-64 h-40 object-cover rounded border" />
          <p v-else class="text-sm text-muted-foreground">No image uploaded.</p>
        </div>
      </div>

      <div class="md:col-span-2 border-t pt-5 space-y-3">
        <h2 class="text-lg font-semibold text-gray-900">SEO Settings</h2>
        <p><span class="font-medium">Focus Keyword:</span> {{ data.seo?.meta_keywords || 'N/A' }}</p>
        <p><span class="font-medium">Meta Title:</span> {{ data.seo?.meta_title || 'N/A' }}</p>
        <p><span class="font-medium">Meta Description:</span> {{ data.seo?.meta_description || 'N/A' }}</p>
        <p><span class="font-medium">OG Title:</span> {{ data.seo?.og_title || 'N/A' }}</p>
        <p><span class="font-medium">OG Description:</span> {{ data.seo?.og_description || 'N/A' }}</p>
        <p><span class="font-medium">Canonical URL:</span> {{ data.seo?.canonical_url || 'N/A' }}</p>
        <p><span class="font-medium">No Index:</span> {{ data.seo?.no_index ? 'Yes' : 'No' }}</p>
        <p><span class="font-medium">No Follow:</span> {{ data.seo?.no_follow ? 'Yes' : 'No' }}</p>
      </div>
    </div>
  </div>
</template>
