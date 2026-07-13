<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import Spinner from '@/components/common/Spinner.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Pencil } from 'lucide-vue-next';
import { toast } from 'vue3-toastify';
import { FETCH_HOT_DEAL } from "@/services/store/actions.type";

const route = useRoute();
const router = useRouter();
const store = useStore();

const isLoading = ref(false);
const data = ref(null);

const fetchHotDeal = async () => {
  isLoading.value = true;
  try {
    const response = await store.dispatch("hotDeals/" + FETCH_HOT_DEAL, route.params.id);
    data.value = response?.data?.data || response?.data || null;
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Failed to load hot deal');
    router.push({ name: 'HotDeals' });
  } finally {
    isLoading.value = false;
  }
};

onMounted(fetchHotDeal);
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <Button variant="outline" @click="router.push({ name: 'HotDeals' })">
          <ArrowLeft class="w-4 h-4 mr-1" />
          Back
        </Button>
        <h1 class="text-2xl font-semibold">Hot Deal Details</h1>
      </div>

      <Button v-if="data" @click="router.push({ name: 'HotDealEdit', params: { id: data.id } })">
        <Pencil class="w-4 h-4 mr-1" />
        Edit
      </Button>
    </div>

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <div v-else-if="data" class="bg-white border rounded-lg p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="space-y-3">
        <p><span class="font-medium">Title:</span> {{ data.title }}</p>
        <p><span class="font-medium">Route:</span> {{ data.from_airport }} → {{ data.to_airport }}</p>
        <p><span class="font-medium">From City:</span> {{ data.from_city }}</p>
        <p><span class="font-medium">To City:</span> {{ data.to_city }}</p>
        <p><span class="font-medium">Tag:</span> 
          <span v-if="data.tag" class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-semibold">
            {{ data.tag }}
          </span>
          <span v-else class="text-muted-foreground">No tag</span>
        </p>
      </div>

      <div class="space-y-3">
        <p><span class="font-medium">Original Price:</span> <span class="line-through text-gray-400">PKR {{ data.original_price?.toLocaleString() }}</span></p>
        <p><span class="font-medium">Discounted Price:</span> <span class="text-green-600 font-bold">PKR {{ data.discounted_price?.toLocaleString() }}</span></p>
        <p><span class="font-medium">Discount:</span> <span class="text-red-600">{{ data.discount_percentage }}% OFF</span></p>
        <p><span class="font-medium">Status:</span> 
          <span :class="data.is_active ? 'text-green-600' : 'text-red-600'">
            {{ data.is_active ? 'Active' : 'Inactive' }}
          </span>
        </p>
        <p><span class="font-medium">Display Order:</span> {{ data.display_order }}</p>
      </div>

      <div class="space-y-3">
        <p><span class="font-medium">Valid From:</span> {{ data.start_date ? new Date(data.start_date).toLocaleDateString() : 'No start date' }}</p>
        <p><span class="font-medium">Valid Until:</span> {{ data.end_date ? new Date(data.end_date).toLocaleDateString() : 'No end date' }}</p>
      </div>

      <div class="md:col-span-2 space-y-3">
        <div>
          <p class="font-medium mb-2">Deal Image</p>
          <img v-if="data.image_url" :src="data.image_url" :alt="data.title" class="w-64 h-40 object-cover rounded border" />
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
