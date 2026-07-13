<template>
  <div class="max-w-4xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <!-- Header -->
    <h2 class="text-2xl font-semibold text-gray-900 mb-4">New Hot Deal</h2>

    <!-- Airports + Image -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- From Airport -->
      <div>
        <label class="form-label">From Airport *</label>
        <Autocomplete
          v-model="form.from_airport"
          placeholder="Origin"
          :source="airports"
          :icon="'PlaneTakeoff'"
          text_color="black"
          class="border rounded-md"
        />
        <p v-if="errors.from_airport" class="text-red-500 text-xs mt-1">
          {{ errors.from_airport }}
        </p>
      </div>

      <!-- To Airport -->
      <div>
        <label class="form-label">To Airport *</label>
        <Autocomplete
          v-model="form.to_airport"
          placeholder="Destination"
          :source="airports"
          :icon="'PlaneLanding'"
          text_color="black"
          class="border rounded-md"
        />
        <p v-if="errors.to_airport" class="text-red-500 text-xs mt-1">
          {{ errors.to_airport }}
        </p>
      </div>

      <!-- Image -->
      
    </div>

    <!-- Title & Tag -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Deal Image *</label>
        <label
          class="flex items-center justify-between h-[42px] px-3 border border-dashed rounded-md cursor-pointer text-sm text-gray-700 hover:bg-gray-50"
        >
          <span>{{ form.image ? form.image.name : "+ Choose Image" }}</span>
          <input type="file" class="hidden" @change="onImageChange" accept="image/*" />
        </label>
        <p v-if="errors.image" class="text-red-500 text-xs mt-1">
          {{ errors.image }}
        </p>
      </div>
      <div>
        <label class="form-label">Deal Title *</label>
        <input type="text" class="form-input" v-model="form.title" placeholder="e.g., Dubai Luxury Escape" />
        <p v-if="errors.title" class="text-red-500 text-xs mt-1">
          {{ errors.title }}
        </p>
      </div>

      <div>
        <label class="form-label">Tag (Optional)</label>
        <input type="text" class="form-input" v-model="form.tag" placeholder="e.g., Top Seller, Flash Sale" />
        <p v-if="errors.tag" class="text-red-500 text-xs mt-1">
          {{ errors.tag }}
        </p>
      </div>
    </div>

    <!-- Prices -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Original Price (PKR) *</label>
        <input type="number" class="form-input" v-model="form.original_price" placeholder="e.g., 95000" />
        <p v-if="errors.original_price" class="text-red-500 text-xs mt-1">
          {{ errors.original_price }}
        </p>
      </div>

      <div>
        <label class="form-label">Discounted Price (PKR) *</label>
        <input type="number" class="form-input" v-model="form.discounted_price" placeholder="e.g., 74999" />
        <p v-if="errors.discounted_price" class="text-red-500 text-xs mt-1">
          {{ errors.discounted_price }}
        </p>
      </div>
    </div>

    <!-- Status & Display Order -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Status</label>
        <Select v-model="form.is_active">
          <SelectTrigger class="form-input">
            <SelectValue placeholder="Select status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="true">Active</SelectItem>
            <SelectItem :value="false">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div>
        <label class="form-label">Display Order</label>
        <input type="number" class="form-input" v-model="form.display_order" placeholder="0, 1, 2..." />
        <p v-if="errors.display_order" class="text-red-500 text-xs mt-1">
          {{ errors.display_order }}
        </p>
      </div>
    </div>

    <!-- Date Range -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Start Date (Optional)</label>
        <input type="date" class="form-input" v-model="form.start_date" />
        <p v-if="errors.start_date" class="text-red-500 text-xs mt-1">
          {{ errors.start_date }}
        </p>
      </div>

      <div>
        <label class="form-label">End Date (Optional)</label>
        <input type="date" class="form-input" v-model="form.end_date" />
        <p v-if="errors.end_date" class="text-red-500 text-xs mt-1">
          {{ errors.end_date }}
        </p>
      </div>
    </div>

    <!-- SEO Section -->
    <div class="space-y-4 border-t pt-6">
      <h3 class="text-lg font-semibold text-gray-900">SEO Settings</h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Focus Keyword</label>
          <input class="form-input" v-model="form.focus_keyword" placeholder="cheap flight deal" />
          <p v-if="errors.focus_keyword" class="text-red-500 text-xs mt-1">{{ errors.focus_keyword }}</p>
        </div>

        <div>
          <div class="flex justify-between mb-1">
            <label class="form-label mb-0">Meta Title</label>
            <span :class="metaTitleLengthClass">{{ metaTitleLength }} / 60</span>
          </div>
          <input class="form-input" v-model="form.meta_title" placeholder="Limited Time Flight Deal" />
          <p v-if="errors.meta_title" class="text-red-500 text-xs mt-1">{{ errors.meta_title }}</p>
        </div>

        <div class="md:col-span-2">
          <div class="flex justify-between mb-1">
            <label class="form-label mb-0">Meta Description</label>
            <span :class="metaDescLengthClass">{{ metaDescLength }} / 160</span>
          </div>
          <textarea class="form-textarea" v-model="form.meta_description" rows="3" placeholder="Book this limited-time discounted flight deal before it expires."></textarea>
          <p v-if="errors.meta_description" class="text-red-500 text-xs mt-1">{{ errors.meta_description }}</p>
        </div>

        <div>
          <label class="form-label">OG Title</label>
          <input class="form-input" v-model="form.og_title" placeholder="Limited Time Flight Deal" />
          <p v-if="errors.og_title" class="text-red-500 text-xs mt-1">{{ errors.og_title }}</p>
        </div>

        <div>
          <label class="form-label">Canonical URL</label>
          <input class="form-input" v-model="form.canonical_url" type="url" placeholder="https://Jetze.com/hot-deals/deal-name" />
          <p v-if="errors.canonical_url" class="text-red-500 text-xs mt-1">{{ errors.canonical_url }}</p>
        </div>

        <div class="md:col-span-2">
          <label class="form-label">OG Description</label>
          <textarea class="form-textarea" v-model="form.og_description" rows="3" placeholder="Social sharing description for this hot deal."></textarea>
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

    <!-- Submit Button -->
    <div class="flex gap-3">
      <button
        @click="submit"
        class="px-4 py-2 text-sm font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition"
      >
        Save Hot Deal
      </button>
      <button
        @click="router.push({ name: 'HotDeals' })"
        class="px-4 py-2 text-sm font-medium bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition"
      >
        Cancel
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import Autocomplete from "@/components/common/Autocomplete.vue";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import { FETCH_AIRPORTS } from "@/services/store/actions.type";
import { SAVE_HOT_DEAL } from "@/services/store/actions.type";
import { toast } from "vue3-toastify";

const router = useRouter();
const store = useStore();

// Form state
const form = reactive({
  from_airport: null,
  to_airport: null,
  title: "",
  tag: "",
  original_price: null,
  discounted_price: null,
  image: null,
  is_active: true,
  display_order: 0,
  start_date: null,
  end_date: null,
  focus_keyword: "",
  meta_title: "",
  meta_description: "",
  og_title: "",
  og_description: "",
  canonical_url: "",
  no_index: false,
  no_follow: false,
});

// Errors
const errors = reactive({});

const clearErrors = () => Object.keys(errors).forEach(k => errors[k] = null);

const applyServerErrors = (error) => {
  const serverErrors = error?.response?.data?.errors || {};
  Object.keys(serverErrors).forEach((key) => {
    errors[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
  });
};

const appendPayloadValue = (payload, key, value) => {
  if (value === null || value === undefined || value === "") return;

  if (["is_active", "no_index", "no_follow"].includes(key)) {
    payload.append(key, value ? 1 : 0);
    return;
  }

  payload.append(key, value);
};

// Airports
const airports = computed(() => store.getters["airport/airports"]);

const metaTitleLength = computed(() => form.meta_title?.length || 0);
const metaDescLength = computed(() => form.meta_description?.length || 0);

const metaTitleLengthClass = computed(() => {
  if (metaTitleLength.value > 60) return "text-red-600 text-xs";
  if (metaTitleLength.value > 50) return "text-amber-600 text-xs";
  return "text-green-600 text-xs";
});

const metaDescLengthClass = computed(() => {
  if (metaDescLength.value > 160) return "text-red-600 text-xs";
  if (metaDescLength.value > 120) return "text-amber-600 text-xs";
  return "text-green-600 text-xs";
});

const isValidUrl = (url) => {
  try {
    new URL(url);
    return true;
  } catch (_) {
    return false;
  }
};

// File change
const onImageChange = (e) => {
  form.image = e.target.files[0];
};

// Validation
const validate = () => {
  clearErrors();
  let valid = true;
  
  if (!form.from_airport) { errors.from_airport = "From airport is required"; valid = false; }
  if (!form.to_airport) { errors.to_airport = "To airport is required"; valid = false; }
  if (!form.title) { errors.title = "Deal title is required"; valid = false; }
  if (!form.original_price) { errors.original_price = "Original price is required"; valid = false; }
  if (!form.discounted_price) { errors.discounted_price = "Discounted price is required"; valid = false; }
  if (form.discounted_price > form.original_price) { 
    errors.discounted_price = "Discounted price must be less than or equal to original price"; 
    valid = false; 
  }
  if (!form.image) { errors.image = "Deal image is required"; valid = false; }
  if (form.focus_keyword && form.focus_keyword.length > 255) { errors.focus_keyword = "Focus keyword must not exceed 255 characters"; valid = false; }
  if (form.meta_title && form.meta_title.length > 255) { errors.meta_title = "Meta title must not exceed 255 characters"; valid = false; }
  if (form.meta_description && form.meta_description.length > 500) { errors.meta_description = "Meta description must not exceed 500 characters"; valid = false; }
  if (form.og_title && form.og_title.length > 255) { errors.og_title = "OG title must not exceed 255 characters"; valid = false; }
  if (form.og_description && form.og_description.length > 500) { errors.og_description = "OG description must not exceed 500 characters"; valid = false; }
  if (form.canonical_url && !isValidUrl(form.canonical_url)) { errors.canonical_url = "Canonical URL must be a valid URL"; valid = false; }
  if (form.canonical_url && form.canonical_url.length > 500) { errors.canonical_url = "Canonical URL must not exceed 500 characters"; valid = false; }
  
  return valid;
};

// Submit
const submit = () => {
  if (!validate()) return;
  
  clearErrors();
  const payload = new FormData();
  
  for (const key in form) {
    appendPayloadValue(payload, key, form[key]);
  }
  
  store.dispatch("hotDeals/" + SAVE_HOT_DEAL, payload)
    .then(() => {
      router.push({ name: 'HotDeals' });
    })
    .catch((error) => {
      applyServerErrors(error);
      toast.error(error?.response?.data?.message || "Failed to create hot deal");
    });
};

// Fetch airports
onMounted(() => {
  store.dispatch("airport/" + FETCH_AIRPORTS);
});
</script>

<style scoped>
.form-label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
  @apply w-full h-[42px] px-3 border border-gray-300 rounded-md
         focus:outline-none focus:ring-1 focus:ring-primary
         focus:border-primary text-sm;
}

.form-textarea {
  @apply w-full px-3 py-2 border border-gray-300 rounded-md
         focus:outline-none focus:ring-1 focus:ring-primary
         focus:border-primary text-sm;
}
</style>
