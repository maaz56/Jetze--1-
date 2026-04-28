<template>
  <div class="max-w-4xl mx-auto bg-white rounded-lg border p-6 space-y-6 shadow-sm">
    <!-- Header -->
    <h2 class="text-2xl font-semibold text-gray-900 mb-4">New Popular Route</h2>

    <!-- Airports + Image -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- From Airport -->
      <div>
        <label class="form-label">From Airport *</label>
        <Autocomplete
          v-model="form.fromAirport"
          placeholder="Origin"
          :source="airports"
          :icon="'PlaneTakeoff'"
          class="border rounded-md"
        />
        <p v-if="errors.fromAirport" class="text-red-500 text-xs mt-1">
          {{ errors.fromAirport }}
        </p>
      </div>

      <!-- To Airport -->
      <div>
        <label class="form-label">To Airport *</label>
        <Autocomplete
          v-model="form.toAirport"
          placeholder="Destination"
          :source="airports"
          :icon="'PlaneLanding'"
          class="border rounded-md"
        />
        <p v-if="errors.toAirport" class="text-red-500 text-xs mt-1">
          {{ errors.toAirport }}
        </p>
      </div>

      <!-- Image -->
      <div>
        <label class="form-label">Destination Image *</label>
        <label
          class="flex items-center justify-between h-[42px] px-3 border border-dashed rounded-md cursor-pointer text-sm text-gray-700 hover:bg-gray-50"
        >
          <span>{{ form.image ? form.image.name : "+ Choose" }}</span>
          <input type="file" class="hidden" @change="onImageChange" />
        </label>
        <p v-if="errors.image" class="text-red-500 text-xs mt-1">
          {{ errors.image }}
        </p>
      </div>
    </div>

    <!-- Airline / Journey / Class -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Airline -->
      <div>
        <label class="form-label">Airline Code</label>
        <Popover v-model:open="isSupplierOpen">
  <PopoverTrigger class="form-input w-full justify-between">
    {{ selectedSupplier ? selectedSupplier.name + " (" + selectedSupplier.iata_code + ")" : "Select a supplier" }}
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
            class="px-3 py-2 cursor-pointer  hover:text-white rounded-md transition"
          >
            {{ airline.name }} ({{ airline.iata_code }})
          </CommandItem>
        </CommandGroup>
      </CommandList>
    </Command>
  </PopoverContent>
</Popover>

        <p v-if="errors.airline" class="text-red-500 text-xs mt-1">
          {{ errors.airline }}
        </p>
      </div>

      <!-- Journey Type -->
      <div>
        <label class="form-label">Journey Type *</label>
        <Select v-model="form.journeyType">
          <SelectTrigger class="form-input">
            <SelectValue placeholder="Select" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="round">Round Trip</SelectItem>
            <SelectItem value="one_way">One Way</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.journeyType" class="text-red-500 text-xs mt-1">
          {{ errors.journeyType }}
        </p>
      </div>

      <!-- Travel Class -->
      <div>
        <label class="form-label">Travel Class *</label>
        <Select v-model="form.travelClass">
          <SelectTrigger class="form-input">
            <SelectValue placeholder="Select" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="economy">Economy</SelectItem>
            <SelectItem value="business">Business</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.travelClass" class="text-red-500 text-xs mt-1">
          {{ errors.travelClass }}
        </p>
      </div>
    </div>

    <!-- Dates / Price -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="form-label">Departure Plus Days *</label>
        <input type="number" class="form-input" v-model="form.departurePlusDays" />
        <p v-if="errors.departurePlusDays" class="text-red-500 text-xs mt-1">
          {{ errors.departurePlusDays }}
        </p>
      </div>

      <div v-if="form.journeyType === 'round'">
        <label class="form-label">Duration Days *</label>
        <input type="number" class="form-input" v-model="form.stayDurationDays" />
        <p v-if="errors.stayDurationDays" class="text-red-500 text-xs mt-1">
          {{ errors.stayDurationDays }}
        </p>
      </div>

      <div>
        <label class="form-label">Price Type *</label>
        <Select v-model="form.priceType">
          <SelectTrigger class="form-input">
            <SelectValue placeholder="Select" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="dynamic">Dynamic</SelectItem>
            <SelectItem value="static">Static</SelectItem>
          </SelectContent>
        </Select>
        <p v-if="errors.priceType" class="text-red-500 text-xs mt-1">
          {{ errors.priceType }}
        </p>
      </div>
    </div>

    <!-- Dynamic / Static Inputs -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div v-if="form.priceType === 'dynamic'">
        <label class="form-label">Dynamic Fare Refresh (Hours) *</label>
        <input type="number" class="form-input" v-model="form.dynamicRefreshHours" />
        <p v-if="errors.dynamicRefreshHours" class="text-red-500 text-xs mt-1">
          {{ errors.dynamicRefreshHours }}
        </p>
        <p class="text-xs text-gray-500 mt-1">Recommended 4 hours or above</p>
      </div>

      <div v-if="form.priceType === 'static'">
        <label class="form-label">Static Price *</label>
        <input type="text" class="form-input" v-model="form.staticPrice" />
        <p v-if="errors.staticPrice" class="text-red-500 text-xs mt-1">
          {{ errors.staticPrice }}
        </p>
      </div>
    </div>

    <!-- Destination Names -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="form-label">Destination Name (English) *</label>
        <input class="form-input" v-model="form.destinationNameEn" />
        <p v-if="errors.destinationNameEn" class="text-red-500 text-xs mt-1">
          {{ errors.destinationNameEn }}
        </p>
      </div>
      <div>
        <label class="form-label">Destination Name (Arabic) *</label>
        <input class="form-input" v-model="form.destinationNameAr" />
        <p v-if="errors.destinationNameAr" class="text-red-500 text-xs mt-1">
          {{ errors.destinationNameAr }}
        </p>
      </div>
    </div>

    <!-- Submit Button -->
    <button
      @click="submit"
      class="w-full md:w-auto px-4 py-2 text-sm font-medium bg-primary text-white rounded-md hover:bg-primary-dark transition"
    >
      Save
    </button>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useStore } from "vuex";
import Autocomplete from "@/components/common/Autocomplete.vue";
import {
  Select,
  SelectTrigger,
  SelectValue,
  SelectContent,
  SelectItem,
} from "@/components/ui/select";
import { Popover, PopoverTrigger, PopoverContent } from "@/components/ui/popover";
import { Command, CommandInput, CommandList, CommandEmpty, CommandGroup, CommandItem } from "@/components/ui/command";
import { FETCH_AIRPORTS, FETCH_AIRLINES, SAVE_POPULAR_ROUTE } from "@/services/store/actions.type";
import { useRouter } from "vue-router";

const router = useRouter();
const store = useStore();

// Form state
const form = reactive({
  fromAirport: null,
  toAirport: null,
  image: null,
  airline: null,
  journeyType: null,
  travelClass: null,
  departurePlusDays: null,
  stayDurationDays: null,
  priceType: null,
  dynamicRefreshHours: null,
  staticPrice: null,
  destinationNameEn: "",
  destinationNameAr: "",
});

// Errors
const errors = reactive({});
const clearErrors = () => Object.keys(errors).forEach(k => errors[k] = null);

// Airports / Airlines
const airports = computed(() => store.getters["airport/airports"]);
const airlines = computed(() => store.getters["airline/airlines"]);
const isSupplierOpen = ref(false);
const selectedSupplier = ref(null);
const supplierSearchQuery = ref("");

const filteredAirlines = computed(() => {
  if (!supplierSearchQuery.value) return airlines.value;
  return airlines.value.filter(a =>
    a.name.toLowerCase().includes(supplierSearchQuery.value.toLowerCase()) ||
    a.iata_code?.toLowerCase().includes(supplierSearchQuery.value.toLowerCase())
  );
});

const selectSupplier = (airline) => {
  selectedSupplier.value = airline;
  form.airline = airline.id;
  isSupplierOpen.value = false;
};

// File change
const onImageChange = (e) => {
  form.image = e.target.files[0];
};

// Validation
const validate = () => {
  clearErrors();
  let valid = true;
  if (!form.fromAirport) { errors.fromAirport = "From airport is required"; valid = false; }
  if (!form.toAirport) { errors.toAirport = "To airport is required"; valid = false; }
  if (!form.journeyType) { errors.journeyType = "Journey type is required"; valid = false; }
  if (!form.travelClass) { errors.travelClass = "Travel class is required"; valid = false; }
  if (!form.departurePlusDays) { errors.departurePlusDays = "Departure days required"; valid = false; }
  if (form.journeyType === "round" && !form.stayDurationDays) { errors.stayDurationDays = "Stay duration required"; valid = false; }
  if (!form.priceType) { errors.priceType = "Price type is required"; valid = false; }
  if (form.priceType === "dynamic" && !form.dynamicRefreshHours) { errors.dynamicRefreshHours = "Refresh hours required"; valid = false; }
  if (form.priceType === "static" && !form.staticPrice) { errors.staticPrice = "Static price required"; valid = false; }
  if (!form.destinationNameEn) { errors.destinationNameEn = "Destination name (EN) required"; valid = false; }
  if (!form.destinationNameAr) { errors.destinationNameAr = "Destination name (AR) required"; valid = false; }
  return valid;
};

// Submit
const submit = () => {
  if (!validate()) return;
  const payload = new FormData();
  for (const key in form) {
    if (form[key] !== null) payload.append(key, form[key]);
  }
  store.dispatch("cms/" + SAVE_POPULAR_ROUTE, payload).then(() => {
  router.push({ name: 'PopularRoutes' });
});

};

// Fetch airports and airlines
onMounted(() => {
  store.dispatch("airport/" + FETCH_AIRPORTS);
  store.dispatch("airline/" + FETCH_AIRLINES);
});
</script>

<style>
.form-label {
  @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
  @apply w-full h-[42px] px-3 border border-gray-300 rounded-md
         focus:outline-none focus:ring-1 focus:ring-primary
         focus:border-primary text-sm;
}
</style>
