<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import { toast } from 'vue3-toastify';
import Autocomplete from '@/components/common/Autocomplete.vue';
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from '@/components/ui/select';
import { Popover, PopoverTrigger, PopoverContent } from '@/components/ui/popover';
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem } from '@/components/ui/command';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import Spinner from '@/components/common/Spinner.vue';
import apiService from '@/services/store/apiService';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { FETCH_AIRPORTS, FETCH_AIRLINES, UPDATE_POPULAR_ROUTE } from '@/services/store/actions.type';

const route = useRoute();
const router = useRouter();
const store = useStore();

const isLoading = ref(false);
const isSaving = ref(false);

const form = reactive({
  fromAirport: null,
  toAirport: null,
  image: null,
  airline: null,
  type: 'domestic',
  journeyType: null,
  travelClass: null,
  departurePlusDays: null,
  stayDurationDays: null,
  priceType: null,
  dynamicRefreshHours: null,
  staticPrice: null,
  destinationNameEn: '',
  destinationNameAr: '',
  focus_keyword: '',
  meta_title: '',
  meta_description: '',
  og_title: '',
  og_description: '',
  canonical_url: '',
  no_index: false,
  no_follow: false,
  blogs: [],
  faqs: [],
});

const currentImageUrl = ref('');
const errors = reactive({});

const airports = computed(() => store.getters['airport/airports']);
const airlines = computed(() => store.getters['airline/airlines']);
const isSupplierOpen = ref(false);
const selectedSupplier = ref(null);
const supplierSearchQuery = ref('');

const filteredAirlines = computed(() => {
  if (!supplierSearchQuery.value) return airlines.value;
  return airlines.value.filter(a =>
    a.name.toLowerCase().includes(supplierSearchQuery.value.toLowerCase()) ||
    a.iata_code?.toLowerCase().includes(supplierSearchQuery.value.toLowerCase())
  );
});

const metaTitleLength = computed(() => form.meta_title?.length || 0);
const metaDescLength = computed(() => form.meta_description?.length || 0);

const metaTitleLengthClass = computed(() => {
  if (metaTitleLength.value > 60) return 'text-red-600 text-xs';
  if (metaTitleLength.value > 50) return 'text-amber-600 text-xs';
  return 'text-green-600 text-xs';
});

const metaDescLengthClass = computed(() => {
  if (metaDescLength.value > 160) return 'text-red-600 text-xs';
  if (metaDescLength.value > 120) return 'text-amber-600 text-xs';
  return 'text-green-600 text-xs';
});

const isValidUrl = (url) => {
  try {
    new URL(url);
    return true;
  } catch (_) {
    return false;
  }
};

const selectSupplier = (airline) => {
  selectedSupplier.value = airline;
  form.airline = airline.id;
  isSupplierOpen.value = false;
};

const onImageChange = (e) => {
  form.image = e.target.files[0] || null;
};

const addBlogSection = () => {
  form.blogs.push({ title: '', content: '' });
};

const removeBlogSection = (index) => {
  form.blogs.splice(index, 1);
};

const addFaqItem = () => {
  form.faqs.push({ question: '', answer: '' });
};

const removeFaqItem = (index) => {
  form.faqs.splice(index, 1);
};

const resetErrors = () => Object.keys(errors).forEach((k) => { errors[k] = null; });

const setServerErrors = (error) => {
  const serverErrors = error?.response?.data?.errors || {};
  Object.keys(serverErrors).forEach((key) => {
    errors[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
  });
};

const validate = () => {
  resetErrors();
  let valid = true;

  if (!form.fromAirport) { errors.fromAirport = 'From airport is required'; valid = false; }
  if (!form.toAirport) { errors.toAirport = 'To airport is required'; valid = false; }
  if (form.fromAirport && form.toAirport && form.fromAirport === form.toAirport) {
    errors.toAirport = 'Destination airport must be different';
    valid = false;
  }
  if (!form.journeyType) { errors.journeyType = 'Journey type is required'; valid = false; }
  if (!form.travelClass) { errors.travelClass = 'Travel class is required'; valid = false; }
  if (!form.type) { errors.type = 'Route type is required'; valid = false; }
  if (form.departurePlusDays === null || form.departurePlusDays === '') { errors.departurePlusDays = 'Departure plus days is required'; valid = false; }
  if (form.journeyType === 'round' && (form.stayDurationDays === null || form.stayDurationDays === '')) {
    errors.stayDurationDays = 'Stay duration is required for round trip';
    valid = false;
  }
  if (!form.priceType) { errors.priceType = 'Price type is required'; valid = false; }
  if (form.priceType === 'dynamic' && (form.dynamicRefreshHours === null || form.dynamicRefreshHours === '')) {
    errors.dynamicRefreshHours = 'Dynamic refresh hours is required';
    valid = false;
  }
  if (form.priceType === 'static' && (form.staticPrice === null || form.staticPrice === '')) {
    errors.staticPrice = 'Static price is required';
    valid = false;
  }
  if (!form.destinationNameEn?.trim()) { errors.destinationNameEn = 'Destination name in English is required'; valid = false; }
  if (!form.destinationNameAr?.trim()) { errors.destinationNameAr = 'Destination name in Arabic is required'; valid = false; }
  if (form.focus_keyword && form.focus_keyword.length > 255) { errors.focus_keyword = 'Focus keyword must not exceed 255 characters'; valid = false; }
  if (form.meta_title && form.meta_title.length > 255) { errors.meta_title = 'Meta title must not exceed 255 characters'; valid = false; }
  if (form.meta_description && form.meta_description.length > 500) { errors.meta_description = 'Meta description must not exceed 500 characters'; valid = false; }
  if (form.og_title && form.og_title.length > 255) { errors.og_title = 'OG title must not exceed 255 characters'; valid = false; }
  if (form.og_description && form.og_description.length > 500) { errors.og_description = 'OG description must not exceed 500 characters'; valid = false; }
  if (form.canonical_url && !isValidUrl(form.canonical_url)) { errors.canonical_url = 'Canonical URL must be a valid URL'; valid = false; }
  if (form.canonical_url && form.canonical_url.length > 500) { errors.canonical_url = 'Canonical URL must not exceed 500 characters'; valid = false; }

  return valid;
};

const fetchRoute = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.getPopularRoute(route.params.id);
    const item = response.data?.data;
    if (!item) throw new Error('Invalid popular route response');

    form.fromAirport = item.from_airport;
    form.toAirport = item.to_airport;
    form.airline = item.airline_id;
    form.type = item.type;
    form.journeyType = item.journey_type;
    form.travelClass = item.travel_class;
    form.departurePlusDays = item.departure_plus_days;
    form.stayDurationDays = item.stay_duration_days;
    form.priceType = item.price_type;
    form.dynamicRefreshHours = item.dynamic_refresh_hours;
    form.staticPrice = item.static_price;
    form.destinationNameEn = item.destination_name_en;
    form.destinationNameAr = item.destination_name_ar;
    form.focus_keyword = item.seo?.meta_keywords || '';
    form.meta_title = item.seo?.meta_title || '';
    form.meta_description = item.seo?.meta_description || '';
    form.og_title = item.seo?.og_title || '';
    form.og_description = item.seo?.og_description || '';
    form.canonical_url = item.seo?.canonical_url || '';
    form.no_index = item.seo?.no_index || false;
    form.no_follow = item.seo?.no_follow || false;
    form.blogs = item.blogs || [];
    form.faqs = item.faqs || [];
    currentImageUrl.value = item.image_url || '';

    if (item.airline_id) {
      const airline = airlines.value.find((a) => a.id === item.airline_id);
      if (airline) selectedSupplier.value = airline;
    }
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Failed to load route');
    router.push({ name: 'PopularRoutes' });
  } finally {
    isLoading.value = false;
  }
};

const submit = async () => {
  if (!validate()) return;

  isSaving.value = true;
  resetErrors();

  const payload = new FormData();
  payload.append('fromAirport', form.fromAirport);
  payload.append('toAirport', form.toAirport);
  payload.append('journeyType', form.journeyType);
  payload.append('travelClass', form.travelClass);
  payload.append('type', form.type);
  payload.append('departurePlusDays', form.departurePlusDays);
  payload.append('priceType', form.priceType);
  payload.append('destinationNameEn', form.destinationNameEn);
  payload.append('destinationNameAr', form.destinationNameAr);
  payload.append('focus_keyword', form.focus_keyword || '');
  payload.append('meta_title', form.meta_title || '');
  payload.append('meta_description', form.meta_description || '');
  payload.append('og_title', form.og_title || '');
  payload.append('og_description', form.og_description || '');
  payload.append('canonical_url', form.canonical_url || '');
  payload.append('no_index', form.no_index ? '1' : '0');
  payload.append('no_follow', form.no_follow ? '1' : '0');

  if (form.airline) payload.append('airline', form.airline);
  if (form.journeyType === 'round' && form.stayDurationDays !== null && form.stayDurationDays !== '') payload.append('stayDurationDays', form.stayDurationDays);
  if (form.priceType === 'dynamic' && form.dynamicRefreshHours !== null && form.dynamicRefreshHours !== '') payload.append('dynamicRefreshHours', form.dynamicRefreshHours);
  if (form.priceType === 'static' && form.staticPrice !== null && form.staticPrice !== '') payload.append('staticPrice', form.staticPrice);
  if (form.image) payload.append('image', form.image);
  if (form.blogs && form.blogs.length > 0) payload.append('blogs', JSON.stringify(form.blogs));
  if (form.faqs && form.faqs.length > 0) payload.append('faqs', JSON.stringify(form.faqs));

  try {
    await store.dispatch('cms/' + UPDATE_POPULAR_ROUTE, {
      id: route.params.id,
      data: payload,
    });
    toast.success('Popular route updated successfully');
    router.push({ name: 'PopularRouteView', params: { id: route.params.id } });
  } catch (error) {
    setServerErrors(error);
    toast.error(error?.response?.data?.message || 'Failed to update popular route');
  } finally {
    isSaving.value = false;
  }
};

onMounted(async () => {
  await Promise.all([
    store.dispatch('airport/' + FETCH_AIRPORTS),
    store.dispatch('airline/' + FETCH_AIRLINES),
  ]);
  await fetchRoute();
});
</script>

<template>
  <div class="max-w-4xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-semibold text-gray-900">Edit Popular Route</h2>
      <Button variant="outline" @click="router.push({ name: 'PopularRoutes' })">
        <ArrowLeft class="w-4 h-4 mr-1" />
        Back
      </Button>
    </div>

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">From Airport *</label>
          <Autocomplete
            v-model="form.fromAirport"
            placeholder="Origin"
            :source="airports"
            :icon="'PlaneTakeoff'"
            text_color="black"
            class="border rounded-md"
          />
          <p v-if="errors.fromAirport" class="text-red-500 text-xs mt-1">{{ errors.fromAirport }}</p>
        </div>

        <div>
          <label class="form-label">To Airport *</label>
          <Autocomplete
            v-model="form.toAirport"
            placeholder="Destination"
            :source="airports"
            :icon="'PlaneLanding'"
            text_color="black"
            class="border rounded-md"
          />
          <p v-if="errors.toAirport" class="text-red-500 text-xs mt-1">{{ errors.toAirport }}</p>
        </div>

       
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
         <div>
          <label class="form-label">Airline Code</label>
          <Popover v-model:open="isSupplierOpen">
            <PopoverTrigger class="form-input w-full justify-between">
              {{ selectedSupplier ? selectedSupplier.name + ' (' + selectedSupplier.iata_code + ')' : 'Select a supplier' }}
            </PopoverTrigger>
            <PopoverContent class="p-0">
              <Command>
                <CommandInput placeholder="Search airlines..." v-model="supplierSearchQuery" class="h-9" />
                <CommandList>
                  <CommandEmpty>No airlines found.</CommandEmpty>
                  <CommandGroup>
                    <CommandItem
                      v-for="airline in filteredAirlines"
                      :key="airline.id"
                      :value="airline.name"
                      @select="() => selectSupplier(airline)"
                      class="px-3 py-2 cursor-pointer hover:text-white rounded-md transition"
                    >
                      {{ airline.name }} ({{ airline.iata_code }})
                    </CommandItem>
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>
        <div>
          <label class="form-label">Destination Image</label>
          <label class="flex items-center justify-between h-[42px] px-3 border border-dashed rounded-md cursor-pointer text-sm text-gray-700 hover:bg-gray-50">
            <span>{{ form.image ? form.image.name : '+ Choose' }}</span>
            <input type="file" class="hidden" accept="image/*" @change="onImageChange" />
          </label>
          <img v-if="!form.image && currentImageUrl" :src="currentImageUrl" class="w-24 h-16 object-cover rounded border mt-2" alt="Current image" />
          <p v-if="errors.image" class="text-red-500 text-xs mt-1">{{ errors.image }}</p>
        </div>

        <div>
          <label class="form-label">Journey Type *</label>
          <Select v-model="form.journeyType">
            <SelectTrigger class="form-input"><SelectValue placeholder="Select" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="round">Round Trip</SelectItem>
              <SelectItem value="one_way">One Way</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.journeyType" class="text-red-500 text-xs mt-1">{{ errors.journeyType }}</p>
        </div>

        <div>
          <label class="form-label">Travel Class *</label>
          <Select v-model="form.travelClass">
            <SelectTrigger class="form-input"><SelectValue placeholder="Select" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="economy">Economy</SelectItem>
              <SelectItem value="business">Business</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.travelClass" class="text-red-500 text-xs mt-1">{{ errors.travelClass }}</p>
        </div>

        <div>
          <label class="form-label">Route Type *</label>
          <Select v-model="form.type">
            <SelectTrigger class="form-input"><SelectValue placeholder="Select" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="domestic">Domestic</SelectItem>
              <SelectItem value="international">International</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.type" class="text-red-500 text-xs mt-1">{{ errors.type }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="form-label">Departure Plus Days *</label>
          <input type="number" class="form-input" v-model="form.departurePlusDays" />
          <p v-if="errors.departurePlusDays" class="text-red-500 text-xs mt-1">{{ errors.departurePlusDays }}</p>
        </div>

        <div v-if="form.journeyType === 'round'">
          <label class="form-label">Duration Days *</label>
          <input type="number" class="form-input" v-model="form.stayDurationDays" />
          <p v-if="errors.stayDurationDays" class="text-red-500 text-xs mt-1">{{ errors.stayDurationDays }}</p>
        </div>

        <div>
          <label class="form-label">Price Type *</label>
          <Select v-model="form.priceType">
            <SelectTrigger class="form-input"><SelectValue placeholder="Select" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="dynamic">Dynamic</SelectItem>
              <SelectItem value="static">Static</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.priceType" class="text-red-500 text-xs mt-1">{{ errors.priceType }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-if="form.priceType === 'dynamic'">
          <label class="form-label">Dynamic Fare Refresh (Hours) *</label>
          <input type="number" class="form-input" v-model="form.dynamicRefreshHours" />
          <p v-if="errors.dynamicRefreshHours" class="text-red-500 text-xs mt-1">{{ errors.dynamicRefreshHours }}</p>
        </div>

        <div v-if="form.priceType === 'static'">
          <label class="form-label">Static Price *</label>
          <input type="number" class="form-input" v-model="form.staticPrice" />
          <p v-if="errors.staticPrice" class="text-red-500 text-xs mt-1">{{ errors.staticPrice }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Destination Name (English) *</label>
          <input class="form-input" v-model="form.destinationNameEn" />
          <p v-if="errors.destinationNameEn" class="text-red-500 text-xs mt-1">{{ errors.destinationNameEn }}</p>
        </div>
        <div>
          <label class="form-label">Destination Name (Arabic) *</label>
          <input class="form-input" v-model="form.destinationNameAr" />
          <p v-if="errors.destinationNameAr" class="text-red-500 text-xs mt-1">{{ errors.destinationNameAr }}</p>
        </div>
      </div>

      <!-- SEO Section -->
      <div class="space-y-4 border-t pt-6">
        <h3 class="text-lg font-semibold text-gray-900">SEO Settings</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Focus Keyword</label>
            <input class="form-input" v-model="form.focus_keyword" placeholder="cheap flights to Dubai" />
            <p v-if="errors.focus_keyword" class="text-red-500 text-xs mt-1">{{ errors.focus_keyword }}</p>
          </div>

          <div>
            <div class="flex justify-between mb-1">
              <label class="form-label mb-0">Meta Title</label>
              <span :class="metaTitleLengthClass">{{ metaTitleLength }} / 60</span>
            </div>
            <input class="form-input" v-model="form.meta_title" placeholder="Cheap Flights from Lahore to Dubai" />
            <p v-if="errors.meta_title" class="text-red-500 text-xs mt-1">{{ errors.meta_title }}</p>
          </div>

          <div class="md:col-span-2">
            <div class="flex justify-between mb-1">
              <label class="form-label mb-0">Meta Description</label>
              <span :class="metaDescLengthClass">{{ metaDescLength }} / 160</span>
            </div>
            <textarea class="form-textarea" v-model="form.meta_description" rows="3" placeholder="Find affordable flights, fares, and travel options for this route."></textarea>
            <p v-if="errors.meta_description" class="text-red-500 text-xs mt-1">{{ errors.meta_description }}</p>
          </div>

          <div>
            <label class="form-label">OG Title</label>
            <input class="form-input" v-model="form.og_title" placeholder="Cheap Flights from Lahore to Dubai" />
            <p v-if="errors.og_title" class="text-red-500 text-xs mt-1">{{ errors.og_title }}</p>
          </div>

          <div>
            <label class="form-label">Canonical URL</label>
            <input class="form-input" v-model="form.canonical_url" type="url" placeholder="https://Jetze.com/popular-routes/route-slug" />
            <p v-if="errors.canonical_url" class="text-red-500 text-xs mt-1">{{ errors.canonical_url }}</p>
          </div>

          <div class="md:col-span-2">
            <label class="form-label">OG Description</label>
            <textarea class="form-textarea" v-model="form.og_description" rows="3" placeholder="Social sharing description for this route."></textarea>
            <p v-if="errors.og_description" class="text-red-500 text-xs mt-1">{{ errors.og_description }}</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-6">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.no_index" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
            No Index
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.no_follow" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary" />
            No Follow
          </label>
        </div>
      </div>

      <!-- Blogs Section -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Blog Sections</h3>
          <button
            @click="addBlogSection"
            type="button"
            class="px-3 py-1.5 text-sm font-medium bg-secondary text-white rounded-md hover:bg-secondary-dark transition flex items-center gap-1"
          >
            <span class="text-lg leading-none">+</span> Add Section
          </button>
        </div>

        <div v-for="(blog, index) in form.blogs" :key="index" class="p-4 border rounded-md relative bg-gray-50">
          <button
            @click="removeBlogSection(index)"
            type="button"
            class="absolute top-2 right-2 text-red-500 hover:text-red-700 font-bold px-2"
          >
            &times;
          </button>
          
          <div class="mb-4">
            <label class="form-label">Section Title *</label>
            <input class="form-input bg-white" v-model="blog.title" placeholder="e.g. Things to do..." />
          </div>
          
          <div class="mb-2">
            <label class="form-label">Content *</label>
            <div class="bg-white">
              <QuillEditor theme="snow" v-model:content="blog.content" contentType="html" class="min-h-[150px]" />
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">FAQs</h3>
          <button
            @click="addFaqItem"
            type="button"
            class="px-3 py-1.5 text-sm font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition flex items-center gap-1"
          >
            <span class="text-lg leading-none">+</span> Add item
          </button>
        </div>

        <div v-for="(faq, index) in form.faqs" :key="index" class="p-4 border rounded-md relative bg-gray-50 space-y-4">
          <button
            @click="removeFaqItem(index)"
            type="button"
            class="absolute top-4 right-4 bg-red-600 hover:bg-red-700 text-white rounded px-4 py-1.5 text-sm"
          >
            Delete
          </button>

          <div>
            <label class="form-label">Question</label>
            <input class="form-input bg-white pr-28" v-model="faq.question" placeholder="Eg: When and where does the tour end?" />
          </div>

          <div>
            <label class="form-label">Answer</label>
            <div class="bg-white">
              <QuillEditor theme="snow" v-model:content="faq.answer" contentType="html" class="min-h-[150px]" />
            </div>
          </div>
        </div>
      </div>

      <Button @click="submit" :disabled="isSaving">
        {{ isSaving ? 'Updating...' : 'Update' }}
      </Button>
    </template>
  </div>
</template>

<style>
.form-label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
  @apply w-full h-[42px] px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary text-sm;
}

.form-textarea {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary text-sm;
}
</style>
