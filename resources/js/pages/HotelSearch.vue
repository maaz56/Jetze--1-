<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import {
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
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { debounce } from "lodash";

const route = useRoute();
const hotelStore = useHotelStore();

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
const formErrorMessage = ref("");
const expandedHotelCodes = ref(new Set());
const selectedBookingCode = ref("");
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
const isSearching = computed(() => hotelStore.getIsSearching);
const hotelResults = computed(() => hotelStore.getHotels);
const searchSessionId = computed(() => hotelStore.getSearchSessionId);
const providerStatus = computed(() => hotelStore.getProviderStatus);
const errorMessage = computed(() => formErrorMessage.value || hotelStore.getErrorMessage);
const totalRoomOptions = computed(() => hotelResults.value.reduce((total, hotelItem) => total + Number(hotelItem.room_count || hotelItem.rooms?.length || 0), 0));

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
  try {
    destinationSuggestions.value = await hotelStore.fetchSuggestions(destinationQuery.value);
  } catch (error) {
    destinationSuggestions.value = [];
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
  formErrorMessage.value = "";

  if (!selectedDestination.value) {
    hotelStore.resetSearch();
    formErrorMessage.value = "Select a destination or hotel from the suggestions.";
    showSuggestions.value = true;
    return;
  }

  if (!checkIn.value || !checkOut.value || nights.value <= 0) {
    hotelStore.resetSearch();
    formErrorMessage.value = "Select valid check-in and check-out dates.";
    return;
  }

  try {
    expandedHotelCodes.value = new Set();
    selectedBookingCode.value = "";
    await hotelStore.searchHotels({
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
  } catch (error) {
    formErrorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Hotel search failed. Please try again.";
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

const formatMoney = (amount, currency) => {
  if (amount === null || amount === undefined || amount === "") {
    return "Unavailable";
  }

  return `${currency || ""} ${Number(amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const primaryRoom = (hotelItem) => hotelItem.lowest_room || hotelItem.rooms?.[0] || null;

const additionalRooms = (hotelItem) => {
  const selectedPrimaryRoom = primaryRoom(hotelItem);
  return (hotelItem.rooms || []).filter((room) => room?.booking_code !== selectedPrimaryRoom?.booking_code);
};

const isHotelExpanded = (hotelItem) => expandedHotelCodes.value.has(String(hotelItem.hotel_code));

const toggleHotelRooms = (hotelItem) => {
  const hotelCode = String(hotelItem.hotel_code);
  const nextExpandedHotelCodes = new Set(expandedHotelCodes.value);

  if (nextExpandedHotelCodes.has(hotelCode)) {
    nextExpandedHotelCodes.delete(hotelCode);
  } else {
    nextExpandedHotelCodes.add(hotelCode);
  }

  expandedHotelCodes.value = nextExpandedHotelCodes;
};

const selectRoom = (room) => {
  selectedBookingCode.value = room?.booking_code || "";
};

const isSelectedRoom = (room) => selectedBookingCode.value && room?.booking_code === selectedBookingCode.value;

const formatRoomName = (room) => {
  if (!room) {
    return "Room option";
  }

  if (Array.isArray(room.name)) {
    return room.name.filter(Boolean).join(", ") || "Room option";
  }

  return room.name || "Room option";
};

const formatRating = (rating) => {
  if (!rating || rating === "All") {
    return "Hotel";
  }

  return String(rating)
    .replace(/([a-z])([A-Z])/g, "$1 $2");
};

const formatMeal = (mealType) => {
  if (!mealType) {
    return "Meal info unavailable";
  }

  return String(mealType).replaceAll("_", " ");
};

const inclusions = (room) => {
  if (!room?.inclusion) {
    return [];
  }

  return String(room.inclusion)
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean)
    .slice(0, 3);
};

const firstPromotion = (hotelItem) => {
  const hotelPromotion = hotelItem.promotions?.[0];
  if (hotelPromotion) {
    return hotelPromotion;
  }

  return primaryRoom(hotelItem)?.room_promotion?.[0] || null;
};

const hasAtPropertySupplement = (room) => {
  if (!Array.isArray(room?.supplements)) {
    return false;
  }

  return room.supplements.flat(2).some((supplement) => supplement?.Type === "AtProperty");
};

const formatLocation = (hotelItem) => {
  return [hotelItem.city, hotelItem.country].filter(Boolean).join(", ") || "Location unavailable";
};

const parseRoomsQuery = (roomsQuery) => {
  if (!roomsQuery) {
    return null;
  }

  try {
    const parsed = typeof roomsQuery === "string" ? JSON.parse(roomsQuery) : roomsQuery;

    if (!Array.isArray(parsed) || !parsed.length) {
      return null;
    }

    return parsed.map((room) => ({
      adults: Number(room.adults || 1),
      children: Number(room.children || 0),
      children_ages: Array.isArray(room.children_ages) ? room.children_ages.map((age) => Number(age)) : [],
    }));
  } catch {
    return null;
  }
};

const loadSearchFromRoute = async () => {
  const destinationType = route.query.destination_type;
  const destinationValue = route.query.destination_value;

  if (!destinationType || !destinationValue) {
    return;
  }

  const destinationLabel = route.query.destination_label || String(destinationValue);
  selectedDestination.value = {
    type: String(destinationType),
    value: String(destinationValue),
    label: String(destinationLabel),
  };
  destinationQuery.value = String(destinationLabel);
  checkIn.value = route.query.check_in ? String(route.query.check_in) : checkIn.value;
  checkOut.value = route.query.check_out ? String(route.query.check_out) : checkOut.value;
  guestNationality.value = route.query.guest_nationality ? String(route.query.guest_nationality).toUpperCase() : guestNationality.value;

  const parsedRooms = parseRoomsQuery(route.query.rooms);
  if (parsedRooms) {
    rooms.value = parsedRooms;
  }

  await searchHotels();
};

onMounted(() => {
  loadSearchFromRoute();
});

watch(
  () => route.fullPath,
  () => {
    loadSearchFromRoute();
  },
);
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
              <label class="text-xs font-bold uppercase text-primary">Enter your destination or property</label>
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
                  class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-primary/5"
                  @click="selectDestination(suggestion)"
                >
                  <component :is="destinationIcon(suggestion.type)" class="h-5 w-5 text-primary" />
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
                      <input v-model.number="room.adults" type="number" min="1" max="8" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-primary" />
                    </label>
                    <label class="text-xs font-medium text-gray-600">
                      Children
                      <input :value="room.children" type="number" min="0" max="4" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-primary" @input="updateChildren(room, $event.target.value)" />
                    </label>
                  </div>
                  <div v-if="room.children > 0" class="mt-3 grid grid-cols-2 gap-3">
                    <label v-for="childIndex in room.children" :key="childIndex" class="text-xs font-medium text-gray-600">
                      Child {{ childIndex }} age
                      <input v-model.number="room.children_ages[childIndex - 1]" type="number" min="0" max="18" class="mt-1 h-10 w-full border border-gray-300 px-3 outline-none focus:border-primary" />
                    </label>
                  </div>
                </div>
                <button type="button" class="mt-3 text-sm font-semibold text-primary" @click="addRoom">Add room</button>
              </div>
            </div>

            <div class="flex flex-col gap-3 p-4 lg:min-w-40">
              <label class="text-xs font-bold uppercase text-gray-800">Nationality</label>
              <input v-model="guestNationality" maxlength="2" type="text" class="h-10 border border-gray-200 px-3 text-sm font-semibold uppercase outline-none focus:border-primary" />
              <Button class="h-12 rounded bg-primary px-6 text-base font-bold text-white hover:bg-primary/90" :is-loading="isSearching" @click="searchHotels">
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

      <div v-if="isSearching" class="space-y-4">
        <div v-for="index in 5" :key="index" class="animate-pulse rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex flex-col gap-5 lg:flex-row lg:items-stretch">
            <div class="flex flex-1 gap-4">
              <div class="h-14 w-14 rounded-lg bg-primary/10"></div>
              <div class="flex-1 space-y-3">
                <div class="h-5 w-2/5 rounded bg-gray-200"></div>
                <div class="h-4 w-3/5 rounded bg-gray-100"></div>
                <div class="h-4 w-4/5 rounded bg-gray-100"></div>
              </div>
            </div>
            <div class="h-24 rounded-lg bg-gray-100 lg:w-72"></div>
            <div class="h-24 rounded-lg bg-primary/10 lg:w-56"></div>
          </div>
        </div>
      </div>

      <div v-else-if="hotelResults.length" class="space-y-4">
        <div class="rounded-lg border border-primary/15 bg-white p-4 shadow-sm">
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <p class="text-xs font-bold uppercase text-primary">Available hotels</p>
              <h3 class="mt-1 text-xl font-semibold text-gray-950">{{ selectedDestination?.label }}</h3>
              <p class="mt-1 text-sm text-gray-500">
                {{ hotelResults.length }} hotel{{ hotelResults.length === 1 ? "" : "s" }} and {{ totalRoomOptions }} room option{{ totalRoomOptions === 1 ? "" : "s" }} found for {{ nights }} night{{ nights === 1 ? "" : "s" }} and {{ guestsSummary.toLowerCase() }}.
              </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-semibold text-gray-600">
              <span class="rounded bg-primary/10 px-3 py-2 text-primary">{{ checkIn }} to {{ checkOut }}</span>
              <span class="rounded bg-gray-100 px-3 py-2">Nationality {{ guestNationality }}</span>
            </div>
          </div>
        </div>

        <div v-if="searchSessionId" class="text-right text-xs text-gray-500">Session: {{ searchSessionId }}</div>

        <div class="space-y-4">
          <article
            v-for="hotelItem in hotelResults"
            :key="hotelItem.hotel_code"
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary/40 hover:shadow-md sm:p-5"
          >
            <div class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,1fr)_230px]">
              <div class="min-w-0">
                <div class="flex gap-4">
                  <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Hotel class="h-7 w-7" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <h4 class="min-w-0 text-lg font-semibold leading-snug text-gray-950 sm:text-xl">{{ hotelItem.name }}</h4>
                      <span class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                        <Star class="h-3.5 w-3.5 fill-current" />
                        {{ formatRating(hotelItem.rating) }}
                      </span>
                    </div>

                    <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-gray-700">
                      <MapPin class="h-4 w-4 shrink-0 text-primary" />
                      <span class="truncate">{{ formatLocation(hotelItem) }}</span>
                    </p>
                    <p v-if="hotelItem.address" class="mt-1 line-clamp-2 text-sm leading-6 text-gray-500">
                      {{ hotelItem.address }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                      <span class="rounded bg-gray-100 px-2.5 py-1 text-gray-600">Code {{ hotelItem.hotel_code }}</span>
                      <span class="rounded bg-primary/10 px-2.5 py-1 text-primary">{{ hotelItem.room_count }} room option{{ hotelItem.room_count === 1 ? "" : "s" }}</span>
                      <span v-if="firstPromotion(hotelItem)" class="rounded bg-emerald-50 px-2.5 py-1 text-emerald-700">{{ firstPromotion(hotelItem) }}</span>
                      <span v-if="hotelItem.has_at_property_supplements" class="rounded bg-orange-50 px-2.5 py-1 text-orange-700">At-property charges</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="rounded-lg border border-gray-100 bg-gray-50 p-4">
                <p class="text-xs font-bold uppercase text-gray-500">Lowest available room</p>
                <div class="mt-1 flex items-start justify-between gap-3">
                  <h5 class="line-clamp-2 text-sm font-semibold text-gray-950">{{ formatRoomName(primaryRoom(hotelItem)) }}</h5>
                  <span class="shrink-0 text-sm font-bold text-gray-950">{{ formatMoney(primaryRoom(hotelItem)?.total_fare, hotelItem.currency) }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                  <span
                    v-for="item in inclusions(primaryRoom(hotelItem))"
                    :key="item"
                    class="rounded bg-white px-2.5 py-1 text-xs font-medium text-gray-700 ring-1 ring-gray-200"
                  >
                    {{ item }}
                  </span>
                </div>

                <div class="mt-3 grid gap-2 text-xs text-gray-600 sm:grid-cols-2">
                  <span class="flex items-center gap-1.5">
                    <Users class="h-3.5 w-3.5 text-primary" />
                    {{ guestsSummary }}
                  </span>
                  <span>{{ formatMeal(primaryRoom(hotelItem)?.meal_type) }}</span>
                  <span :class="primaryRoom(hotelItem)?.is_refundable ? 'text-emerald-700' : 'text-red-600'">
                    {{ primaryRoom(hotelItem)?.is_refundable ? "Refundable" : "Non-refundable" }}
                  </span>
                  <span v-if="hasAtPropertySupplement(primaryRoom(hotelItem))" class="text-orange-700">Payable at property</span>
                </div>

                <div class="mt-4 border-t border-gray-200 pt-3">
                  <button
                    v-if="additionalRooms(hotelItem).length"
                    type="button"
                    class="text-xs font-bold text-primary hover:text-primary/80"
                    @click="toggleHotelRooms(hotelItem)"
                  >
                    {{ isHotelExpanded(hotelItem) ? "Hide" : "View" }}
                    {{ additionalRooms(hotelItem).length }} more room option{{ additionalRooms(hotelItem).length === 1 ? "" : "s" }}
                  </button>
                  <span v-else class="text-xs text-gray-500">Only one room option</span>
                </div>

                <div v-if="isHotelExpanded(hotelItem)" class="mt-3 divide-y divide-gray-200 rounded border border-gray-200 bg-white">
                  <div
                    v-for="room in additionalRooms(hotelItem)"
                    :key="room.booking_code"
                    class="grid gap-3 px-3 py-3 text-xs sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                  >
                    <div class="min-w-0">
                      <p class="truncate font-semibold text-gray-900">{{ formatRoomName(room) }}</p>
                      <div class="mt-1 flex flex-wrap gap-2 text-gray-500">
                        <span>{{ formatMeal(room.meal_type) }}</span>
                        <span :class="room.is_refundable ? 'text-emerald-700' : 'text-red-600'">
                          {{ room.is_refundable ? "Refundable" : "Non-refundable" }}
                        </span>
                        <span v-if="room.total_tax">Tax {{ formatMoney(room.total_tax, hotelItem.currency) }}</span>
                        <span v-if="hasAtPropertySupplement(room)" class="text-orange-700">At-property charges</span>
                      </div>
                      <div v-if="inclusions(room).length" class="mt-2 flex flex-wrap gap-1.5">
                        <span v-for="item in inclusions(room)" :key="`${room.booking_code}-${item}`" class="rounded bg-gray-50 px-2 py-1 text-[11px] font-medium text-gray-600 ring-1 ring-gray-200">
                          {{ item }}
                        </span>
                      </div>
                    </div>
                    <span class="font-bold text-gray-950 sm:text-right">{{ formatMoney(room.total_fare, hotelItem.currency) }}</span>
                    <button
                      type="button"
                      class="rounded border border-primary px-4 py-2 text-xs font-bold text-primary hover:bg-primary hover:text-white"
                      :class="{ 'border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-600': isSelectedRoom(room) }"
                      @click="selectRoom(room)"
                    >
                      {{ isSelectedRoom(room) ? "Selected" : "Select" }}
                    </button>
                  </div>
                </div>
              </div>

              <div class="flex flex-col justify-between rounded-lg border border-primary/20 bg-primary/5 p-4 xl:text-right">
                <div>
                  <p class="text-xs font-semibold uppercase text-gray-500">Total fare</p>
                  <p class="mt-1 flex items-center gap-1 text-2xl font-bold text-gray-950 xl:justify-end">
                    <CircleDollarSign class="h-5 w-5 text-primary" />
                    {{ formatFare(hotelItem) }}
                  </p>
                  <p v-if="primaryRoom(hotelItem)?.total_tax" class="mt-1 text-xs text-gray-500">
                    Includes {{ formatMoney(primaryRoom(hotelItem).total_tax, hotelItem.currency) }} tax
                  </p>
                  <p class="mt-2 text-xs text-gray-500">{{ nights }} night{{ nights === 1 ? "" : "s" }} from TBO</p>
                </div>
                <Button
                  class="mt-4 h-11 rounded bg-primary px-5 text-sm font-bold text-white hover:bg-primary/90"
                  :class="{ 'bg-emerald-600 hover:bg-emerald-600': isSelectedRoom(primaryRoom(hotelItem)) }"
                  @click="selectRoom(primaryRoom(hotelItem))"
                >
                  {{ isSelectedRoom(primaryRoom(hotelItem)) ? "Selected" : "Select room" }}
                </Button>
              </div>
            </div>
          </article>
        </div>
      </div>

      <div v-else-if="!props.embedded" class="bg-white px-6 py-10 text-center shadow">
        <Hotel class="mx-auto mb-3 h-10 w-10 text-primary" />
        <h3 class="text-lg font-semibold text-gray-950">Start with a destination</h3>
        <p class="mt-1 text-sm text-gray-500">Search synced TBO countries, cities, or hotel names to find live availability.</p>
      </div>
    </section>
  </div>
</template>
