<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from 'vuex';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';
import { FETCH_AIRPORT, UPDATE_AIRPORT } from '@/services/store/actions.type';
import apiService from '@/services/store/apiService';
import Spinner from '@/components/common/Spinner.vue';

const route = useRoute();
const router = useRouter();
const store = useStore();

const isLoading = ref(true);
const isSaving = ref(false);
const errors = reactive({});

const form = reactive({
  iata_code: '',
  name: '',
  iata_city_code: '',
  iata_country_code: '',
  city_name: '',
  latitude: '',
  longitude: '',
  time_zone: '',
});

const resetErrors = () => {
  Object.keys(errors).forEach(key => errors[key] = null);
};

const setServerErrors = (error) => {
  if (error.response?.data?.errors) {
    const serverErrors = error.response.data.errors;
    Object.keys(serverErrors).forEach(key => {
      errors[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key];
    });
  }
};

const fetchAirport = async () => {
  try {
    const response = await apiService.getAirport(route.params.id);
    const airport = response.data.data;
    Object.keys(form).forEach(key => {
      form[key] = airport[key] || '';
    });
  } catch (error) {
    console.error('Failed to fetch airport', error);
    router.push({ name: 'Airports' });
  } finally {
    isLoading.value = false;
  }
};

const submit = async () => {
  isSaving.value = true;
  resetErrors();

  try {
    await store.dispatch('airport/' + UPDATE_AIRPORT, {
      id: route.params.id,
      data: form,
    });
    router.push({ name: 'Airports' });
  } catch (error) {
    setServerErrors(error);
  } finally {
    isSaving.value = false;
  }
};

onMounted(fetchAirport);
</script>

<template>
  <div class="max-w-4xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-semibold text-gray-900">Edit Airport</h2>
      <Button variant="outline" @click="router.push({ name: 'Airports' })">
        <ArrowLeft class="w-4 h-4 mr-1" />
        Back
      </Button>
    </div>

    <div v-if="isLoading" class="py-20 flex justify-center">
      <Spinner />
    </div>

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-2">
          <label class="form-label">Airport Name *</label>
          <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Jinnah International Airport" />
          <p v-if="errors.name" class="text-red-500 text-xs">{{ errors.name }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">IATA Code *</label>
          <input v-model="form.iata_code" type="text" class="form-input" placeholder="e.g. KHI" />
          <p v-if="errors.iata_code" class="text-red-500 text-xs">{{ errors.iata_code }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">City Name</label>
          <input v-model="form.city_name" type="text" class="form-input" placeholder="e.g. Karachi" />
          <p v-if="errors.city_name" class="text-red-500 text-xs">{{ errors.city_name }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">IATA City Code</label>
          <input v-model="form.iata_city_code" type="text" class="form-input" placeholder="e.g. KHI" />
          <p v-if="errors.iata_city_code" class="text-red-500 text-xs">{{ errors.iata_city_code }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">IATA Country Code</label>
          <input v-model="form.iata_country_code" type="text" class="form-input" placeholder="e.g. PK" />
          <p v-if="errors.iata_country_code" class="text-red-500 text-xs">{{ errors.iata_country_code }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">Time Zone</label>
          <input v-model="form.time_zone" type="text" class="form-input" placeholder="e.g. Asia/Karachi" />
          <p v-if="errors.time_zone" class="text-red-500 text-xs">{{ errors.time_zone }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">Latitude</label>
          <input v-model="form.latitude" type="number" step="0.000001" class="form-input" placeholder="e.g. 24.9065" />
          <p v-if="errors.latitude" class="text-red-500 text-xs">{{ errors.latitude }}</p>
        </div>

        <div class="space-y-2">
          <label class="form-label">Longitude</label>
          <input v-model="form.longitude" type="number" step="0.000001" class="form-input" placeholder="e.g. 67.1608" />
          <p v-if="errors.longitude" class="text-red-500 text-xs">{{ errors.longitude }}</p>
        </div>
      </div>

      <div class="flex justify-end pt-4">
        <Button @click="submit" :disabled="isSaving" class="w-full md:w-auto">
          {{ isSaving ? 'Updating...' : 'Update Airport' }}
        </Button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.form-label {
  @apply block text-sm font-medium text-gray-700;
}
.form-input {
  @apply w-full h-10 px-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm;
}
</style>
