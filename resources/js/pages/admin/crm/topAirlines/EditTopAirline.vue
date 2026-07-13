<template>
  <div class="max-w-2xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-semibold text-gray-900">Edit Top Airline</h2>
      <Button variant="outline" @click="router.push({ name: 'TopAirlines' })">
        <ArrowLeft class="w-4 h-4 mr-1" />
        Back
      </Button>
    </div>

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <template v-else>
      <div class="space-y-4">
        <!-- Name -->
        <div>
          <label class="form-label">Airline Name *</label>
          <input class="form-input" v-model="form.name" placeholder="Enter airline name" />
          <p v-if="errors.name" class="text-red-500 text-xs mt-1">
            {{ errors.name }}
          </p>
        </div>

        <!-- Type -->
        <div>
          <label class="form-label">Airline Type *</label>
          <Select v-model="form.type">
            <SelectTrigger class="form-input">
              <SelectValue placeholder="Select airline type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="domestic">Domestic</SelectItem>
              <SelectItem value="international">International</SelectItem>
            </SelectContent>
          </Select>
          <p v-if="errors.type" class="text-red-500 text-xs mt-1">
            {{ errors.type }}
          </p>
        </div>

        <!-- Image -->
        <div>
          <label class="form-label">Airline Logo</label>
          <label
            class="flex items-center justify-between h-[42px] px-3 border border-dashed rounded-md cursor-pointer text-sm text-gray-700 hover:bg-gray-50"
          >
            <span>{{ form.image ? form.image.name : "+ Change Logo" }}</span>
            <input type="file" class="hidden" @change="onImageChange" />
          </label>
          <img v-if="!form.image && currentImageUrl" :src="currentImageUrl" class="w-24 h-16 object-contain rounded border mt-2" alt="Current logo" />
          <p v-if="errors.image" class="text-red-500 text-xs mt-1">
            {{ errors.image }}
          </p>
        </div>

        <!-- Is Active -->
        <div class="flex items-center gap-2">
            <input type="checkbox" id="is_active" v-model="form.is_active" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary" />
            <label for="is_active" class="text-sm font-medium text-gray-700">Is Active</label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex gap-4 pt-4">
          <Button
            @click="submit"
            :disabled="isSaving"
            class="px-6 py-2"
          >
            {{ isSaving ? 'Saving...' : 'Update' }}
          </Button>
          <Button
            variant="outline"
            @click="router.push({ name: 'TopAirlines' })"
            class="px-6 py-2"
          >
            Cancel
          </Button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useStore } from "vuex";
import { useRouter, useRoute } from "vue-router";
import { toast } from "vue3-toastify";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import { Button } from "@/components/ui/button";
import { ArrowLeft } from "lucide-vue-next";
import Spinner from "@/components/common/Spinner.vue";
import apiService from "@/services/store/apiService";
import { UPDATE_TOP_AIRLINE } from "@/services/store/actions.type";

const router = useRouter();
const route = useRoute();
const store = useStore();

const isLoading = ref(false);
const isSaving = ref(false);
const currentImageUrl = ref("");

const form = reactive({
  name: "",
  type: null,
  image: null,
  is_active: true,
});

const errors = reactive({});

const clearErrors = () => Object.keys(errors).forEach(k => errors[k] = null);

const applyServerErrors = (error) => {
  const serverErrors = error?.response?.data?.errors || {};
  Object.keys(serverErrors).forEach((key) => {
    errors[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
  });
};

const onImageChange = (e) => {
  form.image = e.target.files[0] || null;
};

const fetchAirline = async () => {
  isLoading.value = true;
  try {
    const response = await apiService.getTopAirline(route.params.id);
    const item = response.data?.data;
    if (!item) throw new Error('Invalid top airline response');

    form.name = item.name;
    form.type = item.type;
    form.is_active = !!item.is_active;
    currentImageUrl.value = item.image_url || '';
  } catch (error) {
    toast.error(error?.response?.data?.message || 'Failed to load airline');
    router.push({ name: 'TopAirlines' });
  } finally {
    isLoading.value = false;
  }
};

const validate = () => {
  clearErrors();
  let valid = true;
  if (!form.name) { errors.name = "Name is required"; valid = false; }
  if (!form.type) { errors.type = "Type is required"; valid = false; }
  return valid;
};

const submit = async () => {
  if (!validate()) return;
  
  isSaving.value = true;
  const payload = new FormData();
  payload.append('name', form.name);
  payload.append('type', form.type);
  payload.append('is_active', form.is_active ? 1 : 0);
  if (form.image) {
    payload.append('image', form.image);
  }

  try {
    await store.dispatch("cms/" + UPDATE_TOP_AIRLINE, {
      id: route.params.id,
      data: payload,
    });
    toast.success("Top airline updated successfully");
    router.push({ name: 'TopAirlines' });
  } catch (error) {
    applyServerErrors(error);
    toast.error(error?.response?.data?.message || "Failed to update top airline");
  } finally {
    isSaving.value = false;
  }
};

onMounted(() => {
  fetchAirline();
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
</style>
