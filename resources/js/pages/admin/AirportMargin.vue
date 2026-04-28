<template>
  <div class="min-h-screen bg-gray-50 py-10">
    <div class="max-w-2xl mx-auto">
      <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">
        Airport Margins
      </h2>
      <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8">
        <div class="space-y-10">

          <!-- Domestic Flights Margin (Fixed Amount) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">
              Domestic Flights Margin (Fixed Amount)
            </label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-600 font-medium">
                PKR
              </span>
              <input
                v-model.number="margin.domestic"
                type="number"
                min="0"
                max="10000"
                step="50"
                class="w-full pl-12 pr-4 py-4 text-xl font-medium border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-shadow shadow-sm"
                placeholder="e.g. 500"
              />
            </div>
            <p class="mt-3 text-sm text-gray-600">
              Current B2C Domestic Margin: 
              <span class="font-bold text-primary text-lg">PKR{{ formatAmount(margin.domestic) }}</span>
            </p>
          </div>

          <!-- International Flights Margin (Fixed Amount) -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">
              International Flights Margin (Fixed Amount)
            </label>
            <div class="relative">
              <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-600 font-medium">
                PKR
              </span>
              <input
                v-model.number="margin.international"
                type="number"
                min="0"
                max="25000"
                step="100"
                class="w-full pl-12 pr-4 py-4 text-xl font-medium border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-shadow shadow-sm"
                placeholder="e.g. 1500"
              />
            </div>
            <p class="mt-3 text-sm text-gray-600">
              Current B2C International Margin: 
              <span class="font-bold text-primary text-lg">PKR{{ formatAmount(margin.international) }}</span>
            </p>
          </div>

        </div>

        <!-- Summary Box -->
        <div class="mt-10 p-6 bg-gray-50 rounded-lg border border-gray-200">
          <h4 class="font-semibold text-gray-700 mb-3">Summary </h4>
          <div class="grid grid-cols-2 gap-4 text-lg">
            <div>
              <span class="text-gray-600">Domestic:</span>
              <span class="font-bold text-primary ml-2">PKR{{ formatAmount(margin.domestic) }}</span>
            </div>
            <div>
              <span class="text-gray-600">International:</span>
              <span class="font-bold text-primary ml-2">PKR{{ formatAmount(margin.international) }}</span>
            </div>
          </div>
        </div>

        <!-- Save Button -->
        <div class="mt-10 text-right">
          <button
            @click="saveMargins"
            class="px-10 py-4 bg-primary text-white font-semibold text-lg rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-4 focus:ring-primary/30 transition-all shadow-lg"
          >
            Save Margin
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive } from 'vue'
import { useStore } from 'vuex'
import { FETCH_AIRPORT_MARGINS, SAVE_AIRPORT_MARGINS } from '@/services/store/actions.type'
import { computed } from 'vue'

const store = useStore()
const margin = computed(() => store.getters['airport/airportMargin'] || {})



// Format number with Indian comma style (optional)
const formatAmount = (amount) => {
  if (!amount) return '0'
  return amount.toLocaleString('en-IN')
}
const fetchMargins = async () => {
 store.dispatch('airport/' + FETCH_AIRPORT_MARGINS)
}
const saveMargins = () => {
  const payload = {
    domestic: margin.value?.domestic,
    international: margin.value?.international,
  }

  console.log('Saving B2C Fixed Margins:', payload)

  store.dispatch('airport/' + SAVE_AIRPORT_MARGINS, payload)
}

onMounted(() => {
  fetchMargins()
});
</script>

<style scoped>
/* Ensure your Tailwind config has `primary` color defined */
/* Example: primary: #4f46e5 (indigo) or your brand color */
</style>