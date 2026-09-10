<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import {
  CalendarDays,
  Check,
  ChevronDown,
  CircleDollarSign,
  Hotel,
  MapPin,
  Minus,
  Plus,
  Search,
  SlidersHorizontal,
  Star,
  Users,
  X,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { debounce } from "lodash";
import { getSelectedCurrencyCode } from "@/lib/utils";

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
const formatDateLabel = (date) => {
  const [year, month, day] = String(date || "").split("-");

  return year && month && day ? `${day}/${month}/${year}` : "Select date";
};

const openDatePicker = (input) => {
  if (!input) {
    return;
  }

  input.focus({ preventScroll: true });

  if (typeof input.showPicker === "function") {
    try {
      input.showPicker();
      return;
    } catch {
      // Some browser implementations reject showPicker on visually hidden inputs.
    }
  }

  input.click();
};
const heroBackground = {
  backgroundImage: "linear-gradient(180deg, rgba(10, 20, 40, 0.35), rgba(10, 20, 40, 0.15) 55%, transparent), linear-gradient(120deg, hsl(var(--primary-dark)) 0%, hsl(var(--primary)) 55%, hsl(var(--primary-light)) 100%)",
};

const MAX_ADULTS_PER_ROOM = 10;
const MAX_CHILDREN_PER_ROOM = 6;

const destinationQuery = ref("");
const selectedDestination = ref(null);
const destinationSuggestions = ref([]);
const showSuggestions = ref(false);
const checkIn = ref(formatDateInput(tomorrow));
const checkOut = ref(formatDateInput(dayAfterTomorrow));
const checkInInput = ref(null);
const checkOutInput = ref(null);
// Nationality is no longer editable from the search bar; kept as a fixed
// default so the existing search payload contract is unchanged.
const guestNationality = ref("PK");
const rooms = ref([
  {
    adults: 1,
    children_ages: [],
  },
]);
const showGuestsPanel = ref(false);
const guestsPanelRef = ref(null);
const formErrorMessage = ref("");
const expandedHotelCodes = ref(new Set());
const selectedBookingCode = ref("");
const roomChoices = ref({});
const hotelPriceLimit = ref(null);
const selectedRatings = ref([]);
const selectedMeals = ref([]);
const refundableFilter = ref("all");
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
const isSearching = computed(() => hotelStore.getIsSearching);
const hotelResults = computed(() => hotelStore.getHotels);
const searchSessionId = computed(() => hotelStore.getSearchSessionId);
const providerStatus = computed(() => hotelStore.getProviderStatus);
const errorMessage = computed(() => formErrorMessage.value || hotelStore.getErrorMessage);
const totalRoomOptions = computed(() => hotelResults.value.reduce((total, hotelItem) => total + Number(hotelItem.room_count || hotelItem.rooms?.length || 0), 0));
const hotelRoomAmount = (room, hotelItem) => Number(roomMoney(room, hotelItem)?.amount || 0);
const hotelLowestAmount = (hotelItem) => {
  const amounts = (hotelItem.rooms || []).map((room) => hotelRoomAmount(room, hotelItem)).filter((amount) => amount > 0);
  return amounts.length ? Math.min(...amounts) : 0;
};
const maximumHotelPrice = computed(() => Math.max(...hotelResults.value.map(hotelLowestAmount), 0));
const hotelRatings = computed(() => [...new Set(hotelResults.value.map((hotelItem) => formatRating(hotelItem.rating)).filter((rating) => rating !== "Hotel"))]);
const hotelMealTypes = computed(() => [...new Set(
  hotelResults.value.flatMap((hotelItem) => (hotelItem.rooms || []).map((room) => formatMeal(room.meal_type))).filter(Boolean),
)]);
const filteredHotelResults = computed(() => hotelResults.value.filter((hotelItem) => {
  if (hotelPriceLimit.value !== null && hotelLowestAmount(hotelItem) > hotelPriceLimit.value) {
    return false;
  }

  if (selectedRatings.value.length && !selectedRatings.value.includes(formatRating(hotelItem.rating))) {
    return false;
  }

  return (hotelItem.rooms || []).some((room) => {
    const matchesRefundability = refundableFilter.value === "all"
      || (refundableFilter.value === "refundable" && room.is_refundable)
      || (refundableFilter.value === "non-refundable" && !room.is_refundable);
    const matchesMeal = !selectedMeals.value.length || selectedMeals.value.includes(formatMeal(room.meal_type));

    return matchesRefundability && matchesMeal;
  });
}));

const nights = computed(() => {
  const start = new Date(checkIn.value);
  const end = new Date(checkOut.value);
  const diff = Math.ceil((end - start) / (1000 * 60 * 60 * 24));

  return diff > 0 ? diff : 0;
});

const resetHotelFilters = () => {
  hotelPriceLimit.value = maximumHotelPrice.value;
  selectedRatings.value = [];
  selectedMeals.value = [];
  refundableFilter.value = "all";
};

watch(maximumHotelPrice, (maximumPrice) => {
  hotelPriceLimit.value = maximumPrice;
});

const guestsSummary = computed(() => {
  const roomCount = rooms.value.length;
  const adults = rooms.value.reduce((total, room) => total + Number(room.adults || 0), 0);
  const children = rooms.value.reduce((total, room) => total + room.children_ages.length, 0);
  const guests = adults + children;

  return `${roomCount} Room${roomCount > 1 ? "s" : ""}, ${guests} Guest${guests > 1 ? "s" : ""}`;
});

let latestSuggestionRequest = 0;

const fetchSuggestions = debounce(async (query) => {
  const requestId = ++latestSuggestionRequest;

  try {
    const suggestions = await hotelStore.fetchSuggestions(query);

    // Ignore a slow response for text the traveller has already changed.
    if (requestId === latestSuggestionRequest && query === destinationQuery.value.trim()) {
      destinationSuggestions.value = suggestions;
    }
  } catch (error) {
    if (requestId === latestSuggestionRequest) {
      destinationSuggestions.value = [];
    }
  }
}, 250);

const handleDestinationInput = () => {
  selectedDestination.value = null;
  showSuggestions.value = true;
  const query = destinationQuery.value.trim();

  if (query.length < 2) {
    latestSuggestionRequest += 1;
    destinationSuggestions.value = [];
    fetchSuggestions.cancel();
    return;
  }

  fetchSuggestions(query);
};

const openSuggestions = () => {
  showSuggestions.value = true;

  if (!destinationSuggestions.value.length && destinationQuery.value.trim().length >= 2) {
    fetchSuggestions(destinationQuery.value.trim());
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
    children_ages: [],
  });
};

const removeRoom = (index) => {
  if (rooms.value.length === 1) {
    return;
  }

  rooms.value.splice(index, 1);
};

const incrementAdults = (room) => {
  room.adults = Math.min(MAX_ADULTS_PER_ROOM, Number(room.adults || 0) + 1);
};

const decrementAdults = (room) => {
  room.adults = Math.max(1, Number(room.adults || 1) - 1);
};

const incrementChildren = (room) => {
  if (room.children_ages.length >= MAX_CHILDREN_PER_ROOM) {
    return;
  }

  room.children_ages.push(0);
};

const decrementChildren = (room) => {
  room.children_ages.pop();
};

const updateChildAge = (room, childIndex, value) => {
  room.children_ages[childIndex] = Number(value);
};

const childAgeOptions = [
  { label: "Under 1", value: 0 },
  ...Array.from({ length: 12 }, (_, index) => ({ label: String(index + 1), value: index + 1 })),
];

const closeGuestsPanel = () => {
  showGuestsPanel.value = false;
};

const handleClickOutsideGuestsPanel = (event) => {
  if (showGuestsPanel.value && guestsPanelRef.value && !guestsPanelRef.value.contains(event.target)) {
    showGuestsPanel.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutsideGuestsPanel);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutsideGuestsPanel);
  fetchSuggestions.cancel();
});

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
    roomChoices.value = {};
    await hotelStore.searchHotels({
      destination: selectedDestination.value,
      check_in: checkIn.value,
      check_out: checkOut.value,
      guest_nationality: guestNationality.value.toUpperCase(),
      rooms: rooms.value.map((room) => ({
        adults: Number(room.adults),
        children: room.children_ages.length,
        children_ages: room.children_ages.map((age) => Number(age)),
      })),
      filters: {
        refundable: false,
        no_of_rooms: 0,
        meal_type: "All",
      },
      currency_code: getSelectedCurrencyCode(),
    });
  } catch (error) {
    formErrorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Hotel search failed. Please try again.";
  }
};

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || money?.amount === "" || !money?.currency) {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const primaryRoom = (hotelItem) => hotelItem.lowest_room || hotelItem.rooms?.[0] || null;

function roomMoney(room, hotelItem) {
  return room?.display_money || {
    amount: room?.total_fare,
    currency: room?.currency || hotelItem?.currency,
  };
}

const roomTaxMoney = (room, hotelItem) => room?.display_tax_money || {
  amount: room?.total_tax,
  currency: room?.currency || hotelItem?.currency,
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

const roomChoiceForHotel = (hotelItem) => {
  const chosenBookingCode = roomChoices.value[String(hotelItem.hotel_code)];

  return hotelItem.rooms?.find((room) => room.booking_code === chosenBookingCode) || primaryRoom(hotelItem);
};

const chooseRoom = (hotelItem, room) => {
  roomChoices.value = {
    ...roomChoices.value,
    [String(hotelItem.hotel_code)]: room.booking_code,
  };
};

const hasChosenRoom = (hotelItem) => Boolean(roomChoices.value[String(hotelItem.hotel_code)]);

const isChosenRoom = (hotelItem, room) => roomChoiceForHotel(hotelItem)?.booking_code === room?.booking_code;

const selectChosenRoom = (hotelItem) => {
  selectRoom(roomChoiceForHotel(hotelItem));
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

    return parsed.map((room) => {
      const childrenAges = Array.isArray(room.children_ages) ? room.children_ages.map((age) => Number(age)) : [];
      const fallbackCount = Number(room.children || 0);

      return {
        adults: Number(room.adults || 1),
        children_ages: childrenAges.length ? childrenAges : Array.from({ length: fallbackCount }, () => 0),
      };
    });
  } catch {
    return null;
  }
};

const loadSearchFromRoute = async (query = route.query) => {
  const destinationType = query.destination_type;
  const destinationValue = query.destination_value;

  if (!destinationType || !destinationValue) {
    return;
  }

  const destinationLabel = query.destination_label || String(destinationValue);
  selectedDestination.value = {
    type: String(destinationType),
    value: String(destinationValue),
    label: String(destinationLabel),
  };
  destinationQuery.value = String(destinationLabel);
  checkIn.value = query.check_in ? String(query.check_in) : checkIn.value;
  checkOut.value = query.check_out ? String(query.check_out) : checkOut.value;
  guestNationality.value = query.guest_nationality ? String(query.guest_nationality).toUpperCase() : guestNationality.value;

  const parsedRooms = parseRoomsQuery(query.rooms);
  if (parsedRooms) {
    rooms.value = parsedRooms;
  }

  await searchHotels();
};

watch(
  () => route.fullPath,
  (fullPath, previousFullPath) => {
    if (fullPath === previousFullPath) {
      return;
    }

    loadSearchFromRoute({ ...route.query });
  },
  { immediate: true },
);
</script>

<template>
  <div :class="props.embedded ? 'bg-transparent' : 'bg-primary/[0.04]'">
    <section
      :class="[
        'relative isolate overflow-visible bg-slate-950 bg-cover bg-center',
        props.embedded
          ? 'bg-transparent'
          : 'hotel-results-search sticky top-0 z-30 h-[144px] sm:h-[158px]',
      ]"
      :style="!props.embedded ? heroBackground : undefined"
    >
      <div :class="props.embedded ? 'relative mx-auto max-w-7xl px-0 py-0' : 'hotel-results-search__content relative mx-auto h-full max-w-7xl px-4 pt-8 sm:pt-10'">
        <form :class="props.embedded ? 'bg-white shadow-none' : 'hotel-results-search__form z-10 overflow-visible rounded border border-white/30 bg-white/95 shadow-[0_20px_45px_-20px_rgba(15,23,42,0.55)] backdrop-blur-sm'" @submit.prevent="searchHotels">
          <div class="grid grid-cols-1 divide-y divide-slate-200 lg:grid-cols-12 lg:divide-x lg:divide-y-0">
            <div class="relative p-4 sm:p-5 lg:col-span-3 lg:py-4">
              <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                <MapPin class="h-3.5 w-3.5" />
                Destination or property
              </label>
              <div class="mt-2 flex items-center gap-3 rounded border border-transparent bg-slate-50 px-3 transition focus-within:border-primary/35 focus-within:bg-white focus-within:ring-4 focus-within:ring-primary/10">
                <Search class="h-5 w-5 shrink-0 text-primary" />
                <input
                  v-model="destinationQuery"
                  type="text"
                  autocomplete="off"
                  class="h-11 w-full border-0 bg-transparent text-base font-medium text-slate-900 outline-none placeholder:font-normal placeholder:text-slate-400"
                  placeholder="City, hotel or area"
                  @focus="openSuggestions"
                  @input="handleDestinationInput"
                />
                <button
                  v-if="destinationQuery"
                  type="button"
                  class="text-slate-400 transition hover:text-slate-700"
                  @click="destinationQuery = ''; selectedDestination = null; destinationSuggestions = []; fetchSuggestions.cancel()"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>

              <div
                v-if="showSuggestions"
                class="absolute left-4 right-4 top-[92px] z-30 max-h-80 overflow-y-auto rounded border border-slate-200 bg-white py-1 shadow-2xl sm:left-5 sm:right-5"
              >
                <div v-if="isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-500">
                  Loading destinations...
                </div>
                <button
                  v-for="suggestion in destinationSuggestions"
                  :key="`${suggestion.type}-${suggestion.value}`"
                  type="button"
                  class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-primary/5"
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

            <div class="p-4 sm:p-5 lg:col-span-2 lg:py-4">
              <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                <CalendarDays class="h-3.5 w-3.5" />
                Check in
              </label>
              <button type="button" class="relative mt-2 flex h-11 w-full items-center justify-between rounded border border-transparent bg-slate-50 px-3 text-left text-base font-bold text-slate-900 transition hover:bg-white focus:border-primary/35 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10" @click="openDatePicker(checkInInput)">
                <span>{{ formatDateLabel(checkIn) }}</span>
                <CalendarDays class="h-4 w-4 text-slate-500" />
              </button>
              <input ref="checkInInput" v-model="checkIn" type="date" :min="formatDateInput(today)" class="sr-only" aria-label="Check in date" />
            </div>

            <div class="relative p-4 sm:p-5 lg:col-span-2 lg:py-4">
              <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                <CalendarDays class="h-3.5 w-3.5" />
                Check out
              </label>
              <button type="button" class="relative mt-2 flex h-11 w-full items-center justify-between rounded border border-transparent bg-slate-50 px-3 text-left text-base font-bold text-slate-900 transition hover:bg-white focus:border-primary/35 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10" @click="openDatePicker(checkOutInput)">
                <span>{{ formatDateLabel(checkOut) }}</span>
                <CalendarDays class="h-4 w-4 text-slate-500" />
              </button>
              <input ref="checkOutInput" v-model="checkOut" type="date" :min="checkIn" class="sr-only" aria-label="Check out date" />
              <p class="mt-1.5 text-xs font-semibold text-slate-500 lg:hidden">{{ nights }} Night{{ nights === 1 ? "" : "s" }}</p>
              <div class="absolute left-0 top-1/2 z-10 hidden -translate-x-1/2 -translate-y-1/2 flex-col items-center lg:flex" aria-label="Stay duration">
                <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-sm font-bold text-slate-900 shadow-sm">
                  {{ nights }}
                </span>
                <span class="mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                  Night{{ nights === 1 ? "" : "s" }}
                </span>
              </div>
            </div>

            <div ref="guestsPanelRef" class="relative p-4 sm:p-5 lg:col-span-3 lg:py-4">
              <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                <Users class="h-3.5 w-3.5" />
                Rooms &amp; guests
              </label>
              <button type="button" class="mt-2 flex h-11 w-full items-center justify-between rounded border border-transparent bg-slate-50 px-3 text-left text-base font-bold text-slate-900 transition hover:bg-primary/5 focus:outline-none focus:ring-4 focus:ring-primary/10" @click="showGuestsPanel = !showGuestsPanel">
                <span>{{ guestsSummary }}</span>
                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': showGuestsPanel }" />
              </button>

              <div
                v-if="showGuestsPanel"
                class="absolute right-0 top-[96px] z-20 w-[380px] max-w-[calc(100vw-2rem)] rounded border border-slate-200 bg-white p-5 shadow-2xl"
              >
                <div v-for="(room, index) in rooms" :key="index" class="border-b border-gray-100 pb-4 pt-1 first:pt-0 last:border-b-0 last:pb-0">
                  <div class="mb-3 flex items-center justify-between">
                    <p class="text-base font-bold text-gray-900">Room {{ index + 1 }}</p>
                    <button v-if="rooms.length > 1" type="button" class="text-xs font-semibold text-red-600 hover:text-red-700" @click="removeRoom(index)">
                      Remove
                    </button>
                  </div>

                  <div class="flex items-center justify-between py-2">
                    <span class="text-sm text-gray-900">Adults</span>
                    <div class="flex items-center gap-3">
                      <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-800 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="room.adults <= 1"
                        @click="decrementAdults(room)"
                      >
                        <Minus class="h-3.5 w-3.5" />
                      </button>
                      <span class="w-4 text-center text-sm font-semibold text-gray-900">{{ room.adults }}</span>
                      <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-900 bg-gray-900 text-white disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="room.adults >= MAX_ADULTS_PER_ROOM"
                        @click="incrementAdults(room)"
                      >
                        <Plus class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>

                  <div class="flex items-center justify-between py-2">
                    <div>
                      <p class="text-sm text-gray-900">Children</p>
                      <p class="text-xs text-gray-500">0 - 12 Years</p>
                    </div>
                    <div class="flex items-center gap-3">
                      <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-200 bg-gray-50 text-gray-800 disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="room.children_ages.length <= 0"
                        @click="decrementChildren(room)"
                      >
                        <Minus class="h-3.5 w-3.5" />
                      </button>
                      <span class="w-4 text-center text-sm font-semibold text-gray-900">{{ room.children_ages.length }}</span>
                      <button
                        type="button"
                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-900 bg-gray-900 text-white disabled:cursor-not-allowed disabled:opacity-30"
                        :disabled="room.children_ages.length >= MAX_CHILDREN_PER_ROOM"
                        @click="incrementChildren(room)"
                      >
                        <Plus class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>

                  <div v-if="room.children_ages.length" class="mt-2">
                    <p class="mb-2 text-xs font-bold uppercase text-gray-900">Children's age</p>
                    <div class="grid grid-cols-2 gap-3">
                      <label v-for="(age, childIndex) in room.children_ages" :key="childIndex" class="text-xs text-gray-500">
                        Child {{ childIndex + 1 }}
                        <select
                          :value="age"
                          class="mt-1 h-9 w-full rounded border border-gray-300 px-2 text-sm text-gray-900 outline-none focus:border-primary"
                          @change="updateChildAge(room, childIndex, $event.target.value)"
                        >
                          <option v-for="option in childAgeOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                          </option>
                        </select>
                      </label>
                    </div>
                  </div>
                </div>

                <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-4">
                  <button type="button" class="flex items-center gap-1.5 text-sm font-bold text-red-600 hover:text-red-700" @click="addRoom">
                    <Plus class="h-4 w-4" />
                    Add Another Room
                  </button>
                  <button
                    type="button"
                    class="rounded bg-red-600 px-6 py-2.5 text-xs font-bold uppercase tracking-wide text-white hover:bg-red-700"
                    @click="closeGuestsPanel"
                  >
                    Done
                  </button>
                </div>
              </div>
            </div>

            <div class="flex items-center p-4 sm:p-5 lg:col-span-2 lg:p-3">
              <Button type="submit" class="h-12 w-full rounded border-0 bg-[linear-gradient(180deg,hsl(var(--primary-button-start)),hsl(var(--primary-button-end)))] px-6 text-base font-bold text-white shadow-lg shadow-primary/30 transition duration-200 hover:-translate-y-0.5 hover:brightness-110 disabled:translate-y-0 disabled:opacity-70 lg:h-14" :is-loading="isSearching">
                <Search class="h-5 w-5" />
                Search
              </Button>
            </div>
          </div>
        </form>
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
        <div class="rounded border border-primary/15 bg-white p-4 shadow-sm">
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
            </div>
          </div>
        </div>

        <div v-if="searchSessionId" class="text-right text-xs text-gray-500">Session: {{ searchSessionId }}</div>

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
          <aside class="h-fit rounded border border-gray-200 bg-white p-4 shadow-sm lg:sticky lg:top-4">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2 text-lg font-semibold text-gray-950">
                <SlidersHorizontal class="h-5 w-5 text-primary" />
                Filters
              </div>
              <button type="button" class="text-xs font-semibold text-primary hover:text-primary/80" @click="resetHotelFilters">Reset</button>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-4">
              <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-gray-700">Price per stay</p>
                <span class="text-xs font-bold text-gray-700">{{ getSelectedCurrencyCode() }} {{ Math.round(hotelPriceLimit || 0).toLocaleString() }}</span>
              </div>
              <input v-model.number="hotelPriceLimit" type="range" min="0" :max="maximumHotelPrice || 1" step="1" class="mt-3 w-full accent-primary" />
              <div class="mt-1 flex justify-between text-xs text-gray-500">
                <span>0</span>
                <span>{{ getSelectedCurrencyCode() }} {{ Math.round(maximumHotelPrice).toLocaleString() }}</span>
              </div>
            </div>

            <div v-if="hotelRatings.length" class="mt-5 border-t border-gray-100 pt-4">
              <p class="text-sm font-semibold text-gray-700">Hotel rating</p>
              <label v-for="rating in hotelRatings" :key="rating" class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                <input v-model="selectedRatings" type="checkbox" :value="rating" class="h-4 w-4 accent-primary" />
                <span>{{ rating }}</span>
              </label>
            </div>

            <div class="mt-5 border-t border-gray-100 pt-4">
              <p class="text-sm font-semibold text-gray-700">Cancellation</p>
              <label v-for="option in [{ value: 'all', label: 'Any policy' }, { value: 'refundable', label: 'Refundable' }, { value: 'non-refundable', label: 'Non-refundable' }]" :key="option.value" class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                <input v-model="refundableFilter" type="radio" name="hotel-refundability" :value="option.value" class="h-4 w-4 accent-primary" />
                <span>{{ option.label }}</span>
              </label>
            </div>

            <div v-if="hotelMealTypes.length" class="mt-5 border-t border-gray-100 pt-4">
              <p class="text-sm font-semibold text-gray-700">Meal plan</p>
              <label v-for="mealType in hotelMealTypes" :key="mealType" class="mt-3 flex cursor-pointer items-center gap-2 text-sm text-gray-600">
                <input v-model="selectedMeals" type="checkbox" :value="mealType" class="h-4 w-4 accent-primary" />
                <span class="capitalize">{{ mealType }}</span>
              </label>
            </div>
          </aside>

          <div class="min-w-0">
            <p class="mb-3 text-sm text-gray-600">{{ filteredHotelResults.length }} of {{ hotelResults.length }} hotel{{ hotelResults.length === 1 ? "" : "s" }} shown</p>
            <div v-if="filteredHotelResults.length" class="space-y-4">
          <article
            v-for="hotelItem in filteredHotelResults"
            :key="hotelItem.hotel_code"
            class="overflow-hidden rounded border border-gray-300 bg-card text-card-foreground shadow-md shadow-primary/5 transition hover:shadow-lg hover:shadow-primary/10"
          >
            <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
              <div class="min-w-0">
                <div class="flex gap-4">
                  <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                    <Hotel class="h-7 w-7" />
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                      <h4 class="min-w-0 text-xl font-semibold leading-snug">{{ hotelItem.name }}</h4>
                      <span class="inline-flex items-center gap-1 rounded border border-primary/30 bg-primary/10 px-2 py-1 text-xs font-semibold text-primary">
                        <Star class="h-3.5 w-3.5 fill-current" />
                        {{ formatRating(hotelItem.rating) }}
                      </span>
                    </div>
                    <p class="mt-2 flex items-center gap-1.5 text-sm font-medium text-gray-600">
                      <MapPin class="h-4 w-4 shrink-0 text-primary" />
                      <span class="truncate">{{ formatLocation(hotelItem) }}</span>
                    </p>
                    <p v-if="hotelItem.address" class="mt-1 line-clamp-1 text-sm text-gray-600">{{ hotelItem.address }}</p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                      <!-- <span class="rounded bg-gray-50 px-2.5 py-1 text-gray-600">Code {{ hotelItem.hotel_code }}</span> -->
                      <span class="rounded bg-gray-50 px-2.5 py-1 text-gray-700">{{ hotelItem.room_count }} room option{{ hotelItem.room_count === 1 ? "" : "s" }}</span>
                      <span v-if="firstPromotion(hotelItem)" class="rounded bg-gray-50 px-2.5 py-1 text-gray-700">{{ firstPromotion(hotelItem) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="min-w-[210px] pl-0 lg:pl-6 lg:text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">{{ hasChosenRoom(hotelItem) ? "Selected room price" : "From" }}</p>
                <p class="mt-1 flex items-center gap-1 text-2xl font-bold lg:justify-end">
                  <CircleDollarSign class="h-5 w-5 text-primary" />
                  {{ formatMoney(roomMoney(roomChoiceForHotel(hotelItem), hotelItem)) }}
                </p>
                <p class="mt-1 text-xs text-gray-600">{{ nights }} night{{ nights === 1 ? "" : "s" }} · {{ guestsSummary }}</p>
                <Button
                  class="mt-3 h-12 rounded bg-primary px-8 text-base font-bold text-primary-foreground hover:bg-primary/90"
                  :class="{ 'bg-gray-100 text-gray-700 hover:bg-gray-100': isSelectedRoom(roomChoiceForHotel(hotelItem)) }"
                  @click="selectChosenRoom(hotelItem)"
                >
                  {{ isSelectedRoom(roomChoiceForHotel(hotelItem)) ? "Selected" : "Select" }}
                </Button>
              </div>
            </div>

            <div class="mt-1 bg-gray-200/50">
              <button type="button" class="flex w-full items-center justify-center gap-2 px-5 py-3 text-sm font-bold text-primary transition hover:bg-primary/10" @click="toggleHotelRooms(hotelItem)">
                <Plus v-if="!isHotelExpanded(hotelItem)" class="h-4 w-4" />
                <Minus v-else class="h-4 w-4" />
                {{ isHotelExpanded(hotelItem) ? "Hide room options" : `+ ${hotelItem.rooms?.length || 0} room option${(hotelItem.rooms?.length || 0) === 1 ? "" : "s"}` }}
              </button>
            </div>

            <div v-if="isHotelExpanded(hotelItem)" class="bg-card p-4 sm:p-5">
              <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                  <p class="text-sm font-bold">Choose a room</p>
                  <p class="text-xs text-gray-600">Prices shown are for {{ guestsSummary.toLowerCase() }}.</p>
                </div>
                <span class="rounded bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">{{ hotelItem.rooms?.length || 0 }} available</span>
              </div>

              <div class="divide-y divide-border/60">
                <div
                    v-for="room in hotelItem.rooms || []"
                    :key="room.booking_code"
                    role="radio"
                    :aria-checked="isChosenRoom(hotelItem, room)"
                    tabindex="0"
                    class="grid cursor-pointer items-center gap-3 px-1 py-4 outline-none transition hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary/30 lg:grid-cols-[auto_minmax(0,2fr)_minmax(150px,1fr)_auto]"
                    @click="chooseRoom(hotelItem, room)"
                    @keydown.enter.prevent="chooseRoom(hotelItem, room)"
                    @keydown.space.prevent="chooseRoom(hotelItem, room)"
                  >
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full border border-border bg-background text-primary transition hover:border-primary"
                    :class="{ 'border-primary bg-primary text-primary-foreground': isChosenRoom(hotelItem, room) }"
                    aria-hidden="true"
                  >
                    <Check v-if="isChosenRoom(hotelItem, room)" class="h-3.5 w-3.5" />
                  </span>
                  <div class="min-w-auto">
                    <p class="truncate w-64 text-sm font-bold">{{ formatRoomName(room) }}</p>
                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 w-64 text-xs text-gray-600">
                      <span>{{ formatMeal(room.meal_type) }}</span>
                      <span>{{ room.is_refundable ? "Refundable" : "Non-refundable" }}</span>
                      <span v-if="hasAtPropertySupplement(room)">Payable at property</span>
                    </div>
                  </div>
                  <div class="flex flex-wrap gap-1.5">
                    <span v-for="item in inclusions(room)" :key="`${room.booking_code}-${item}`" class="rounded border border-border bg-background px-2 py-1 text-xs text-gray-600">
                      {{ item }}
                    </span>
                  </div>
                  <div class="lg:text-right">
                    <p class="text-lg font-bold">{{ formatMoney(roomMoney(room, hotelItem)) }}</p>
                    <p v-if="room.total_tax" class="mt-1 text-xs text-gray-600">Tax {{ formatMoney(roomTaxMoney(room, hotelItem)) }}</p>
                  </div>
                </div>
              </div>
            </div>
          </article>
            </div>
            <div v-else class="rounded border border-gray-200 bg-white px-6 py-10 text-center shadow-sm">
              <Hotel class="mx-auto mb-3 h-9 w-9 text-primary" />
              <p class="text-base font-semibold text-gray-950">No hotels match these filters</p>
              <button type="button" class="mt-3 text-sm font-semibold text-primary hover:text-primary/80" @click="resetHotelFilters">Clear filters</button>
            </div>
          </div>
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

<style scoped>
@media (min-width: 1024px) {
  .hotel-results-search__form {
    position: absolute;
    top: 50%;
    right: 1rem;
    left: 1rem;
    transform: translateY(-50%);
  }
}
</style>
