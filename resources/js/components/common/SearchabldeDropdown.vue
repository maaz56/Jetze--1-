<script setup>
import { computed, ref, nextTick, watch } from "vue";
import { useStore } from "vuex";
import Skeleton from "primevue/skeleton";
import _ from "lodash";
import { FETCH_AIRPORTS } from "@/services/store/actions.type";

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
  },
  searchType: {
    type: String,
    required: true,
  },
  searchQuery: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    default: "Please select",
  },
});

const emits = defineEmits(["update:modelValue"]);

const store = useStore();

const query = ref(props.searchQuery);  
const isOpen = ref(false);
const focusedIndex = ref(-1);
const searchInput = ref(null);

const airports = computed(() => {
  return props.searchType === "origin"
    ? store.getters["airport/origins"]
    : store.getters["airport/destinations"];
});
const isLoading = computed(() => store.getters["airport/isLoading"]);

const toggleDropdown = () => {
  isOpen.value = !isOpen.value;
  if (isOpen.value) {
    nextTick(() => {
      searchInput.value.focus();
    });
  } else {
    query.value = "";
    focusedIndex.value = -1;
  }
};

const searchAirports = _.debounce(async () => {
  const searchQuery = query.value;  // Use the query value
  //console.log(`Search ${props.searchType}: ${searchQuery}`);
  await store.dispatch("airport/" + FETCH_AIRPORTS, {
    is_origin: props.searchType === "origin",
    is_destination: props.searchType === "destination",
    search_query: searchQuery,
  });
}, 200);

watch(() => props.searchQuery, (newQuery) => {
  query.value = newQuery;
  searchAirports();
}, { immediate: true });  // Immediate to handle initial state

const selectAirport = (item) => {
  emits("update:modelValue", item);
  isOpen.value = false;
  query.value = "";
  focusedIndex.value = -1;
};

const handleKeydown = (e) => {
  if (e.key === "ArrowDown") {
    focusedIndex.value = (focusedIndex.value + 1) % airports.value.length;
  } else if (e.key === "ArrowUp") {
    focusedIndex.value =
      (focusedIndex.value - 1 + airports.value.length) % airports.value.length;
  } else if (e.key === "Enter") {
    if (focusedIndex.value >= 0 && focusedIndex.value < airports.value.length) {
      selectAirport(airports.value[focusedIndex.value]);
    }
  }
};
</script>

<template>
  <div class="relative inline-block">
    <button @click="toggleDropdown" class="bg-white text-start border p-4 w-full">
      <div class="flex items-center justify-between">
        <span class="text-gray-500">{{ searchType === 'origin' ? 'From' : 'To' }}</span>
        <i class="fa-solid fa-chevron-right rotate-90"></i>
      </div>
      <div class="py-4">
        <h1 v-if="modelValue" class="md:text-2xl font-semibold">{{ modelValue.city_name }}</h1>
        <h1 v-else class="md:text-2xl font-semibold">{{ placeholder }}</h1>
        <span v-if="modelValue" class="line-clamp-1 text:sm md:text-base">{{ modelValue.name }}</span>
        <span v-else class="line-clamp-1 text:sm md:text-base">{{ placeholder }}</span>
      </div>
    </button>
    <div v-if="isOpen" class="border z-30 absolute bg-white rounded-b-lg shadow-2xl overflow-hidden w-full">
      <div class="p-3">
        <div class="relative">
          <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
              <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
            </svg>
          </div>
          <input ref="searchInput" type="text" v-model="query" @input="searchAirports" @keydown="handleKeydown" autocomplete="off" class="block w-full p-4 ps-10 text-sm text-gray-900 border border-gray-300 rounded-md bg-gray-50 focus:ring-gray-500 focus:border-fb-primary" placeholder="Search City" />
        </div>
      </div>
      <ul class="h-80 px-4 pb-3 overflow-y-auto text-sm text-gray-700">
        <div v-if="isLoading" class="space-y-4">
          <div class="flex-col items-center justify-center h-full">
            <Skeleton width="50%" height="20px" class="bg-gray-300 mb-2 rounded-none" />
            <Skeleton width="100%" height="20px" class="bg-gray-300 rounded-none" />
          </div>
        </div>
        <div v-if="!isLoading && airports.length > 0">
          <li v-for="(item, index) in airports" :key="item.id" :class="{ 'bg-gray-100': index === focusedIndex }" class="cursor-pointer" @click="selectAirport(item)">
            <label class="flex items-center cursor-pointer hover:bg-gray-100 ps-4 py-3">
              <a>
                <p class="text-base font-semibold text-gray-900">{{ item.city_name }}</p>
                <p>{{ item.name }}</p>
              </a>
            </label>
          </li>
        </div>
        <div v-if="!isLoading && airports.length == 0" class="flex items-center justify-center h-full">
          Nothing found.
        </div>
      </ul>
    </div>
  </div>
</template>
