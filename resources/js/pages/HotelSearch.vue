<script setup>
import apiService from "@/config/axios";
import Button from "@/components/ui/button/Button.vue";
import {
  BedDouble,
  CalendarDays,
  ChevronDown,
  CircleDollarSign,
  Hotel,
  MapPin,
  Search,
  Star,
  Users,
  X,
} from "lucide-vue-next";
import { computed, ref } from "vue";
import { debounce } from "lodash";

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
});

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const dayAfterTomorrow = new Date(today);
dayAfterTomorrow.setDate(today.getDate() + 2);

const formatDateInput = (date) => date.toISOString().slice(0, 10);

const destinationQuery = ref("");
const selectedDestination = ref(null);
const destinationSuggestions = ref([]);
const isLoadingSuggestions = ref(false);
const showSuggestions = ref(false);
const checkIn = ref(formatDateInput(tomorrow));
const checkOut = ref(formatDateInput(dayAfterTomorrow));
const guestNationality = ref("PK");
const rooms = ref([
  {
    adults: 1,
    children: 0,
    children_ages: [],
  },
]);
const showGuestsPanel = ref(false);
const isSearching = ref(false);
const hotelResults = ref([]);
const searchSessionId = ref(null);
const providerStatus = ref(null);
const errorMessage = ref("");

const nights = computed(() => {
  const start = new Date(checkIn.value);
  const end = new Date(checkOut.value);
  const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));

  return diff > 0 ? diff : 0;
});

const guestsSummary = computed(() => {
  const roomCount = rooms.value.length;
  const adults = rooms.value.reduce((total, room) => total + Number(room.adults || 0), 0);
  const children = rooms.value.reduce((total, room) => total + Number(room.children || 0), 0);
  const guests = adults + children;

  return `${roomCount} Room${roomCount > 1 ? "s" : ""}, ${guests} Guest${guests > 1 ? "s" : ""}`;
});

const fetchSuggestions = debounce(async () => {
  isLoadingSuggestions.value = true;

  try {
    const response = await apiService.get("/hotels/suggestions", {
      params: { q: destinationQuery.value },
    });
    destinationSuggestions.value = response.data.data || [];
  } catch (error) {
    destinationSuggestions.value = [];
  } finally {
    isLoadingSuggestions.value = false;
  }
}, 300);

const handleDestinationInput = () => {
  selectedDestination.value = null;
  showSuggestions.value = true;
  fetchSuggestions();
};

const openSuggestions = () => {
  showSuggestions.value = true;

  if (!destinationSuggestions.value.length) {
    fetchSuggestions();
  }
};

const selectDestination = (suggestion) => {
  selectedDestination.value = suggestion;
  destinationQuery.value = suggestion.label;
  destinationSuggestions.value = [];
  showSuggestions.value = false;
};

const addRoom = () => {
  rooms.value.push({
    adults: 1,
    children: 0,
    children_ages: [],
  });
};

const removeRoom = (index) => {
  if (rooms.value.length === 1) {
    return;
  }

  rooms.value.splice(index, 1);
};

const updateChildren = (room, value) => {
  const children = Math.max(0, Math.min(4, Number(value || 0)));
  room.children = children;
  room.children_ages = Array.from({ length: children }, (_, index) => room.children_ages[index] ?? 5);
};

const destinationIcon = (type) => {
  if (type === "hotel") {
    return Hotel;
  }

  return MapPin;
};

const searchHotels = async () => {
  errorMessage.value = "";
  providerStatus.value = null;

  if (!selectedDestination.value) {
    errorMessage.value = "Select a destination or hotel from the suggestions.";
    showSuggestions.value = true;
    return;
  }

  if (!checkIn.value || !checkOut.value || nights.value <= 0) {
    errorMessage.value = "Select valid check-in and check-out dates.";
    return;
  }

  isSearching.value = true;
  hotelResults.value = [];

  try {
    const response = await apiService.post("/hotels/search", {
      destination: selectedDestination.value,
      check_in: checkIn.value,
      check_out: checkOut.value,
      guest_nationality: guestNationality.value.toUpperCase(),
      rooms: rooms.value.map((room) => ({
        adults: Number(room.adults),
        children: Number(room.children),
        children_ages: room.children_ages.map((age) => Number(age)),
      })),
      filters: {
        refundable: false,
        no_of_rooms: 0,
        meal_type: "All",
      },
    });

    hotelResults.value = response.data.data?.hotels || [];
    searchSessionId.value = response.data.data?.search_session_id || null;
    providerStatus.value = response.data.provider_status || null;
    errorMessage.value = hotelResults.value.length ? "" : response.data.message || "No hotels available.";
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Hotel search failed. Please try again.";
    providerStatus.value = error.response?.data?.provider_status || null;
  } finally {
    isSearching.value = false;
  }
};

const formatFare = (hotelItem) => {
  if (!hotelItem.lowest_total_fare) {
    return "Price unavailable";
  }

  return `${hotelItem.currency || ""} ${Number(hotelItem.lowest_total_fare).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};
</script>

<template>
  <div :class="props.embedded ? 'bg-transparent' : 'bg-[#f5f8fb]'">
    <section
      :class="[
        'relative overflow-visible',
        props.embedded
          ? 'bg-transparent'
          : 'bg-[url(https://cdn.pixabay.com/photo/2015/12/28/10/19/hotel-1111199_960_720.jpg)] bg-cover bg-center',
      ]"
    >
      <div v-if="!props.embedded" class="absolute inset-0 bg-sky-900/35"></div>
      <div :class="props.embedded ? 'relative mx-auto max-w-7xl px-0 py-0' : 'relative mx-auto max-w-7xl px-4 py-8 sm:py-10'">
        <h2 v-if="!props.embedded" class="mb-4 text-center text-xl font-semibold text-white sm:text-2xl">
          Book Domestic and International Hotels
        </h2>

        <div :class="props.embedded ? 'bg-white shadow-none' : 'bg-white shadow-xl'">
          <div class="grid grid-cols-1 divide-y divide-gray-200 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto] lg:divide-x lg:divide-y-0">
            <div class="relative p-4">
              <label class="text-xs font-bold uppercase text-[#0054a6]">Enter your destination or property</label>
              <div class="mt-2 flex items-center gap-3">
                <Search class="h-5 w-5 text-gray-800" />
                <input
                  v-model="destinationQuery"
                  type="text"
                  autocomplete="off"
                  class="h-10 w-full border-0 text-sm outline-none placeholder:text-gray-400"
                  placeholder="Enter City/Hotel/Area/building"
                  @focus="openSuggestions"
                  @input="handleDestinationInput"
                />
                <button
                  v-if="destinationQuery"
                  type="button"
                  class="text-gray-400 hover:text-gray-700"
                  @click="destinationQuery = ''; selectedDestination = null; destinationSuggestions = []"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>

              <div
                v-if="showSuggestions"
                class="absolute left-4 right-4 top-[78px] z-30 max-h-80 overflow-y-auto border border-gray-200 bg-white shadow-2xl"
              >
                <div v-if="isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-500">
                  Loading destinations...
                </div>
                <button
                  v-for="suggestion in destinationSuggestions"
                  :key="`${suggestion.type}-${suggestion.value}`"
                  type="button"
                  class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-sky-50"
                  @click="selectDestination(suggestion)"
                >
                  <component :is="destinationIcon(suggestion.type)" class="h-5 w-5 text-emerald-600" />
                  <span>
                    <span class="block text-sm font-semibold text-gray-900">{{ suggestion.label }}</span>
                    <span class="block text-xs capitalize text-gray-500">{{ suggestion.type }}</span>
                  </span>
                </button>
                <div v-if="!isLoadingSuggestions && !destinationSuggestions.length" class="px-4 py-3 text-sm text-gray-500">
                  No synced destinations found.
                </div>
              </div>
            </div>

            <div class="p-4">
              <label class="flex items-center gap-1 text-xs font-bold uppercase text-gray-800">
                <CalendarDays class="h-4 w-4" />
                Check in
              </label>
              <input v-model="checkIn" type="date" :min="formatDateInput(today)" class="mt-2 h-10 w-full border-0 text-xl font-semibold outline-none" />
            </div>

            <div class="p-4">
              <label class="flex items-center gap-1 text-xs font-bold uppercase text-gray-800">
                <CalendarDays class="h-4 w-4" />
                Check out
              </label>
              <input v-model="checkOut" type="date" :min="checkIn" class="mt-2 h-10 w-full border-0 text-xl font-semibold outline-none" />
              <p class="mt-1 text-xs font-semibold text-gray-500">{{ nights }} Night{{ nights === 1 ? "" : "s" }}</p>
            </div>

            <div class="relative p-4">
              <label class="text-xs font-bold uppercase text-gray-800">Rooms & Guests</label>
              <button type="button" class="mt-2 flex h-10 w-full items-center justify-between text-left text-lg font-semibold" @click="showGuestsPanel = !showGuestsPanel">
                <span>{{ guestsSummary }}</span>
                <ChevronDown class="h-4 w-4" />
              </button>

              <div v-if="showGuestsPanel" class="absolute left-0 right-0 top-[88px] z-20 border border-gray-200 bg-white p-4 shadow-2xl lg:min-w-80">
                <div v-for="(room, index) in rooms" :key="index" class="border-b border-gray-100 py-3 last:border-b-0">
                  <div class="mb-3 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-900">Room {{ index + 1 }}</p>
                    <button v-if="rooms.length > 1" type="button" class="text-xs font-semibold text-red-600" @click="removeRoom(index)">Remove</button>
                  </div>
                  <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs font-medium text-gray-600">
                      Adults
                      <input v-model.number="room.adults" type="number" min="1" max="8" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-sky-600" />
                    </label>
                    <label class="text-xs font-medium text-gray-600">
                      Children
                      <input :value="room.children" type="number" min="0" max="4" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-sky-600" @input="updateChildren(room, $event.target.value)" />
                    </label>
                  </div>
                  <div v-if="room.children > 0" class="mt-3 grid grid-cols-2 gap-3">
                    <label v-for="childIndex in room.children" :key="childIndex" class="text-xs font-medium text-gray-600">
                      Child {{ childIndex }} age
                      <input v-model.number="room.children_ages[childIndex - 1]" type="number" min="0" max="18" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-sky-600" />
                    </label>
                  </div>
                </div>
                <button type="button" class="mt-3 text-sm font-semibold text-[#0054a6]" @click="addRoom">Add room</button>
              </div>
            </div>

            <div class="flex flex-col gap-3 p-4 lg:min-w-40">
              <label class="text-xs font-bold uppercase text-gray-800">Nationality</label>
              <input v-model="guestNationality" maxlength="2" type="text" class="h-10 border border-gray-200 px-3 text-sm font-semibold uppercase outline-none focus:border-sky-600" />
              <Button class="h-12 rounded bg-red-500 px-6 text-base font-bold text-white hover:bg-red-600" :is-loading="isSearching" @click="searchHotels">
                <Search class="h-5 w-5" />
                Search
              </Button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section :class="props.embedded ? 'mx-auto max-w-7xl px-0 pt-5 pb-0' : 'mx-auto max-w-7xl px-4 py-8'">
      <div v-if="errorMessage" class="mb-5 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ errorMessage }}
        <span v-if="providerStatus?.Description" class="block text-xs opacity-80">{{ providerStatus.Description }}</span>
      </div>

      <div v-if="isSearching" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="index in 6" :key="index" class="h-64 animate-pulse bg-white shadow"></div>
      </div>

      <div v-else-if="hotelResults.length" class="space-y-4">
        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-end">
          <div>
            <p class="text-sm font-semibold uppercase text-[#0054a6]">Available hotels</p>
            <h3 class="text-2xl font-semibold text-gray-950">{{ selectedDestination?.label }}</h3>
          </div>
          <p v-if="searchSessionId" class="text-xs text-gray-500">Session: {{ searchSessionId }}</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="hotelItem in hotelResults" :key="hotelItem.hotel_code" class="overflow-hidden bg-white shadow transition hover:shadow-lg">
            <div class="h-44 bg-gray-100">
              <img v-if="hotelItem.image" :src="hotelItem.image" :alt="hotelItem.name" class="h-full w-full object-cover" />
              <div v-else class="flex h-full items-center justify-center text-gray-400">
                <BedDouble class="h-10 w-10" />
              </div>
            </div>
            <div class="space-y-3 p-4">
              <div>
                <div class="mb-1 flex items-start justify-between gap-3">
                  <h4 class="line-clamp-2 text-lg font-semibold text-gray-950">{{ hotelItem.name }}</h4>
                  <span v-if="hotelItem.rating" class="flex shrink-0 items-center gap-1 text-xs font-semibold text-amber-600">
                    <Star class="h-4 w-4 fill-current" />
                    {{ hotelItem.rating }}
                  </span>
                </div>
                <p class="flex items-center gap-1 text-sm text-gray-500">
                  <MapPin class="h-4 w-4" />
                  <span class="truncate">{{ hotelItem.city || hotelItem.address || "Location unavailable" }}</span>
                </p>
              </div>

              <div class="flex items-center justify-between border-t border-gray-100 pt-3">
                <div class="text-sm text-gray-500">
                  <p class="flex items-center gap-1">
                    <Users class="h-4 w-4" />
                    {{ guestsSummary }}
                  </p>
                  <p>{{ nights }} Night{{ nights === 1 ? "" : "s" }}</p>
                </div>
                <div class="text-right">
                  <p class="flex items-center justify-end gap-1 text-lg font-bold text-gray-950">
                    <CircleDollarSign class="h-5 w-5 text-emerald-600" />
                    {{ formatFare(hotelItem) }}
                  </p>
                  <p class="text-xs text-gray-500">total from TBO</p>
                </div>
              </div>

              <div class="space-y-2">
                <div v-for="room in hotelItem.rooms.slice(0, 2)" :key="room.booking_code" class="border border-gray-100 px-3 py-2 text-sm">
                  <p class="font-medium text-gray-900">{{ room.name?.join(", ") || "Room option" }}</p>
                  <p class="text-xs text-gray-500">
                    {{ room.inclusion || room.meal_type || "Inclusions unavailable" }}
                    <span v-if="room.is_refundable" class="font-semibold text-emerald-600"> / Refundable</span>
                    <span v-else class="font-semibold text-red-500"> / Non-refundable</span>
                  </p>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>

      <div v-else-if="!props.embedded" class="bg-white px-6 py-10 text-center shadow">
        <Hotel class="mx-auto mb-3 h-10 w-10 text-[#0054a6]" />
        <h3 class="text-lg font-semibold text-gray-950">Start with a destination</h3>
        <p class="mt-1 text-sm text-gray-500">Search synced TBO countries, cities, or hotel names to find live availability.</p>
      </div>
    </section>
  </div>
</template>
