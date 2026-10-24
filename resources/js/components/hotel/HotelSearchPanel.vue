<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useHotelStore } from "@/services/stores/hotel";
import CountryDropdown from "@/components/common/CountryDropdown.vue";
import {
  CalendarDays,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Clock,
  Globe2,
  Hotel,
  Loader2,
  MapPin,
  Minus,
  Plus,
  Search,
  Sparkles,
  X,
} from "lucide-vue-next";

const props = defineProps({
  destination: { type: Object, default: null },
  checkIn: { type: String, default: "" },
  checkOut: { type: String, default: "" },
  rooms: { type: Array, default: null },
  nationality: { type: String, default: "" },
  isLoading: { type: Boolean, default: false },
  embedded: { type: Boolean, default: false },
  standaloneRedirect: { type: Boolean, default: false },
});

const emits = defineEmits([
  "update:destination",
  "update:checkIn",
  "update:checkOut",
  "update:rooms",
  "update:nationality",
  "search",
]);

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();

const HOTEL_RECENT_SEARCHES_KEY = "hotel_recent_search_history";
const MAX_RECENT_HOTEL_SEARCHES = 4;
const MAX_ROOMS = 6;
const MAX_ADULTS_PER_ROOM = 6;
const MAX_CHILDREN_PER_ROOM = 4;

// --- Date helpers ---
const padZero = (num) => String(num).padStart(2, "0");
const toDateString = (date) => `${date.getFullYear()}-${padZero(date.getMonth() + 1)}-${padZero(date.getDate())}`;

const todayDate = new Date();
todayDate.setHours(0, 0, 0, 0);
const todayString = toDateString(todayDate);

const tomorrowDate = new Date(todayDate);
tomorrowDate.setDate(tomorrowDate.getDate() + 1);
const tomorrowString = toDateString(tomorrowDate);

const dayAfterTomorrowDate = new Date(todayDate);
dayAfterTomorrowDate.setDate(dayAfterTomorrowDate.getDate() + 2);
const dayAfterTomorrowString = toDateString(dayAfterTomorrowDate);

// --- Form State ---
const destinationQuery = ref(props.destination?.label || "");
const internalDestination = ref(props.destination || null);
const internalCheckIn = ref(props.checkIn || tomorrowString);
const internalCheckOut = ref(props.checkOut || dayAfterTomorrowString);
const internalNationality = ref(props.nationality || "PK");
const internalRooms = ref(
  props.rooms && props.rooms.length
    ? JSON.parse(JSON.stringify(props.rooms))
    : [{ adults: 2, children_ages: [] }]
);

// Sync with incoming props
watch(
  () => props.destination,
  (val) => {
    internalDestination.value = val;
    if (val?.label) {
      destinationQuery.value = val.label;
    }
  },
  { deep: true }
);
watch(
  () => props.checkIn,
  (val) => {
    if (val) internalCheckIn.value = val;
  }
);
watch(
  () => props.checkOut,
  (val) => {
    if (val) internalCheckOut.value = val;
  }
);
watch(
  () => props.nationality,
  (val) => {
    if (val) internalNationality.value = val;
  }
);
watch(
  () => props.rooms,
  (val) => {
    if (val && val.length) {
      internalRooms.value = JSON.parse(JSON.stringify(val));
    }
  },
  { deep: true }
);

// --- Dropdown & Panel visibility ---
const showSuggestions = ref(false);
const showDatePicker = ref(false);
const showGuestsPanel = ref(false);

const destinationFieldRef = ref(null);
const dateFieldRef = ref(null);
const guestsFieldRef = ref(null);

// Validation error states
const destinationError = ref(false);
const datesError = ref(false);
const validationMessage = ref("");

// Autocomplete suggestions
const destinationSuggestions = ref([]);
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
let suggestionRequestId = 0;

const fetchSuggestions = debounce(async (query) => {
  const reqId = ++suggestionRequestId;
  try {
    const list = await hotelStore.fetchSuggestions(query);
    if (reqId === suggestionRequestId && query === destinationQuery.value.trim()) {
      destinationSuggestions.value = Array.isArray(list) ? list : [];
    }
  } catch {
    if (reqId === suggestionRequestId) {
      destinationSuggestions.value = [];
    }
  }
}, 250);

const handleDestinationInput = () => {
  destinationError.value = false;
  validationMessage.value = "";
  internalDestination.value = null;
  emits("update:destination", null);
  showSuggestions.value = true;

  const query = destinationQuery.value.trim();
  if (query.length < 2) {
    suggestionRequestId++;
    destinationSuggestions.value = [];
    fetchSuggestions.cancel();
    return;
  }
  fetchSuggestions(query);
};

const openSuggestions = () => {
  showSuggestions.value = true;
  showDatePicker.value = false;
  showGuestsPanel.value = false;
  if (!destinationSuggestions.value.length && destinationQuery.value.trim().length >= 2) {
    fetchSuggestions(destinationQuery.value.trim());
  }
};

const selectDestination = (item) => {
  internalDestination.value = item;
  destinationQuery.value = item.label;
  emits("update:destination", item);
  destinationSuggestions.value = [];
  showSuggestions.value = false;
  destinationError.value = false;
  validationMessage.value = "";
  // Auto-advance to date picker if dates aren't set
  if (!internalCheckIn.value || !internalCheckOut.value) {
    showDatePicker.value = true;
  }
};

const clearDestination = () => {
  destinationQuery.value = "";
  internalDestination.value = null;
  emits("update:destination", null);
  destinationSuggestions.value = [];
  fetchSuggestions.cancel();
};

// --- Nights computation ---
const nights = computed(() => {
  if (!internalCheckIn.value || !internalCheckOut.value) return 0;
  const inDate = new Date(`${internalCheckIn.value}T00:00:00`);
  const outDate = new Date(`${internalCheckOut.value}T00:00:00`);
  const diff = Math.ceil((outDate - inDate) / (1000 * 60 * 60 * 24));
  return diff > 0 ? diff : 0;
});

const guestsSummary = computed(() => {
  const roomCount = internalRooms.value.length;
  const adults = internalRooms.value.reduce((acc, r) => acc + Number(r.adults || 0), 0);
  const children = internalRooms.value.reduce(
    (acc, r) => acc + Number(r.children_ages?.length || r.children || 0),
    0
  );
  const totalGuests = adults + children;
  return {
    roomCount,
    totalGuests,
    label: `${roomCount} ${roomCount === 1 ? "Room" : "Rooms"}, ${totalGuests} ${
      totalGuests === 1 ? "Guest" : "Guests"
    }`,
  };
});

// --- Date Formats ---
const formatDisplayDate = (dateStr) => {
  if (!dateStr) return "";
  try {
    const d = new Date(`${dateStr}T00:00:00`);
    return new Intl.DateTimeFormat("en-GB", {
      day: "numeric",
      month: "short",
    }).format(d);
  } catch {
    return dateStr;
  }
};

const formatFullDisplayDate = (dateStr) => {
  if (!dateStr) return "";
  try {
    const d = new Date(`${dateStr}T00:00:00`);
    return new Intl.DateTimeFormat("en-GB", {
      weekday: "short",
      day: "numeric",
      month: "short",
    }).format(d);
  } catch {
    return dateStr;
  }
};

// --- Dual-Month Date Picker Logic ---
const calendarBaseMonth = ref(new Date(todayDate.getFullYear(), todayDate.getMonth(), 1));
const hoverDateString = ref(null);
const isSelectingEndDate = ref(false);

const month1 = computed(() => {
  return new Date(calendarBaseMonth.value.getFullYear(), calendarBaseMonth.value.getMonth(), 1);
});

const month2 = computed(() => {
  return new Date(calendarBaseMonth.value.getFullYear(), calendarBaseMonth.value.getMonth() + 1, 1);
});

const formatMonthHeader = (date) => {
  return new Intl.DateTimeFormat("en-GB", {
    month: "long",
    year: "numeric",
  }).format(date);
};

const canGoPrevMonth = computed(() => {
  const currentFirstOfThisMonth = new Date(todayDate.getFullYear(), todayDate.getMonth(), 1);
  return calendarBaseMonth.value > currentFirstOfThisMonth;
});

const prevMonth = () => {
  if (!canGoPrevMonth.value) return;
  calendarBaseMonth.value = new Date(
    calendarBaseMonth.value.getFullYear(),
    calendarBaseMonth.value.getMonth() - 1,
    1
  );
};

const nextMonth = () => {
  calendarBaseMonth.value = new Date(
    calendarBaseMonth.value.getFullYear(),
    calendarBaseMonth.value.getMonth() + 1,
    1
  );
};

const getDaysMatrix = (monthDate) => {
  const year = monthDate.getFullYear();
  const month = monthDate.getMonth();
  const firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const days = [];
  // Empty leading slots
  for (let i = 0; i < firstDayIndex; i++) {
    days.push(null);
  }
  // Days of the month
  for (let d = 1; d <= daysInMonth; d++) {
    const date = new Date(year, month, d);
    days.push(toDateString(date));
  }
  return days;
};

const month1Days = computed(() => getDaysMatrix(month1.value));
const month2Days = computed(() => getDaysMatrix(month2.value));

const isPastDate = (dateStr) => {
  if (!dateStr) return false;
  return dateStr < todayString;
};

const isStartDate = (dateStr) => dateStr === internalCheckIn.value;
const isEndDate = (dateStr) => dateStr === internalCheckOut.value;

const isInSelectedRange = (dateStr) => {
  if (!dateStr || !internalCheckIn.value || !internalCheckOut.value) return false;
  return dateStr > internalCheckIn.value && dateStr < internalCheckOut.value;
};

const isInHoverRange = (dateStr) => {
  if (!dateStr || !isSelectingEndDate.value || !internalCheckIn.value || !hoverDateString.value) {
    return false;
  }
  return dateStr > internalCheckIn.value && dateStr <= hoverDateString.value;
};

const handleDateClick = (dateStr) => {
  if (!dateStr || isPastDate(dateStr)) return;
  datesError.value = false;
  validationMessage.value = "";

  if (!isSelectingEndDate.value) {
    // Picking start date
    internalCheckIn.value = dateStr;
    internalCheckOut.value = "";
    emits("update:checkIn", dateStr);
    emits("update:checkOut", "");
    isSelectingEndDate.value = true;
  } else {
    // Picking end date
    if (dateStr > internalCheckIn.value) {
      internalCheckOut.value = dateStr;
      emits("update:checkOut", dateStr);
      isSelectingEndDate.value = false;
      showDatePicker.value = false;
    } else {
      // Clicked before or on the start date -> reset to new start date
      internalCheckIn.value = dateStr;
      internalCheckOut.value = "";
      emits("update:checkIn", dateStr);
      emits("update:checkOut", "");
      isSelectingEndDate.value = true;
    }
  }
};

const handleDateHover = (dateStr) => {
  if (isSelectingEndDate.value && dateStr && dateStr > internalCheckIn.value) {
    hoverDateString.value = dateStr;
  } else {
    hoverDateString.value = null;
  }
};

const applyPreset = (durationNights, offsetDays = 0) => {
  const start = new Date(todayDate);
  start.setDate(start.getDate() + offsetDays);
  const end = new Date(start);
  end.setDate(end.getDate() + durationNights);

  internalCheckIn.value = toDateString(start);
  internalCheckOut.value = toDateString(end);
  emits("update:checkIn", internalCheckIn.value);
  emits("update:checkOut", internalCheckOut.value);
  isSelectingEndDate.value = false;
  datesError.value = false;
  showDatePicker.value = false;
};

// --- Guests & Rooms Steppers ---
const addRoom = () => {
  if (internalRooms.value.length >= MAX_ROOMS) return;
  internalRooms.value.push({
    adults: 2,
    children_ages: [],
  });
  emits("update:rooms", internalRooms.value);
};

const removeRoom = (index) => {
  if (internalRooms.value.length <= 1) return;
  internalRooms.value.splice(index, 1);
  emits("update:rooms", internalRooms.value);
};

const incrementAdults = (room) => {
  if (room.adults < MAX_ADULTS_PER_ROOM) {
    room.adults++;
    emits("update:rooms", internalRooms.value);
  }
};

const decrementAdults = (room) => {
  if (room.adults > 1) {
    room.adults--;
    emits("update:rooms", internalRooms.value);
  }
};

const incrementChildren = (room) => {
  if (!room.children_ages) room.children_ages = [];
  if (room.children_ages.length < MAX_CHILDREN_PER_ROOM) {
    room.children_ages.push(6);
    emits("update:rooms", internalRooms.value);
  }
};

const decrementChildren = (room) => {
  if (room.children_ages && room.children_ages.length > 0) {
    room.children_ages.pop();
    emits("update:rooms", internalRooms.value);
  }
};

const updateChildAge = (room, idx, val) => {
  room.children_ages[idx] = Number(val);
  emits("update:rooms", internalRooms.value);
};

const updateNationality = (code) => {
  internalNationality.value = code;
  emits("update:nationality", code);
};

// --- Recent Searches ---
const recentSearches = ref([]);

const loadRecentSearches = () => {
  try {
    const data = JSON.parse(localStorage.getItem(HOTEL_RECENT_SEARCHES_KEY));
    recentSearches.value = Array.isArray(data) ? data.slice(0, MAX_RECENT_HOTEL_SEARCHES) : [];
  } catch {
    recentSearches.value = [];
  }
};

const saveRecentSearch = (searchData) => {
  try {
    const signature = JSON.stringify({
      destVal: searchData.destination?.value,
      checkIn: searchData.check_in,
      checkOut: searchData.check_out,
      roomsCount: searchData.rooms?.length,
    });
    const entry = {
      ...searchData,
      signature,
      savedAt: Date.now(),
    };
    const existing = (recentSearches.value || []).filter((s) => s.signature !== signature);
    recentSearches.value = [entry, ...existing].slice(0, MAX_RECENT_HOTEL_SEARCHES);
    localStorage.setItem(HOTEL_RECENT_SEARCHES_KEY, JSON.stringify(recentSearches.value));
  } catch {
    // Ignore storage errors
  }
};

const applyRecentSearch = async (item) => {
  if (item.destination) {
    internalDestination.value = item.destination;
    destinationQuery.value = item.destination.label;
    emits("update:destination", item.destination);
  }
  if (item.check_in) {
    internalCheckIn.value = item.check_in;
    emits("update:checkIn", item.check_in);
  }
  if (item.check_out) {
    internalCheckOut.value = item.check_out;
    emits("update:checkOut", item.check_out);
  }
  if (item.rooms) {
    internalRooms.value = JSON.parse(JSON.stringify(item.rooms));
    emits("update:rooms", internalRooms.value);
  }
  if (item.guest_nationality) {
    internalNationality.value = item.guest_nationality;
    emits("update:nationality", item.guest_nationality);
  }
  await nextTick();
  submitSearch();
};

// --- Click Outside Handler ---
const handleClickOutside = (e) => {
  if (destinationFieldRef.value && !destinationFieldRef.value.contains(e.target)) {
    showSuggestions.value = false;
  }
  if (dateFieldRef.value && !dateFieldRef.value.contains(e.target)) {
    showDatePicker.value = false;
  }
  if (guestsFieldRef.value && !guestsFieldRef.value.contains(e.target)) {
    showGuestsPanel.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutside);
  loadRecentSearches();

  // If initial checkIn is in a different month, point calendar to it
  if (internalCheckIn.value) {
    const d = new Date(`${internalCheckIn.value}T00:00:00`);
    if (!isNaN(d.getTime())) {
      calendarBaseMonth.value = new Date(d.getFullYear(), d.getMonth(), 1);
    }
  }
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside);
  fetchSuggestions.cancel();
});

// --- Search Submission ---
const submitSearch = async () => {
  destinationError.value = false;
  datesError.value = false;
  validationMessage.value = "";

  // Auto-resolve destination if user typed a query but didn't click dropdown item
  if (!internalDestination.value && destinationQuery.value.trim().length >= 2) {
    try {
      let list = destinationSuggestions.value;
      if (!list || !list.length) {
        list = await hotelStore.fetchSuggestions(destinationQuery.value.trim());
      }
      if (list && list.length) {
        const queryLower = destinationQuery.value.trim().toLowerCase();
        const matched =
          list.find((item) => item.label?.toLowerCase() === queryLower) ||
          list.find((item) => item.label?.toLowerCase().includes(queryLower)) ||
          list[0];
        if (matched) {
          internalDestination.value = matched;
          destinationQuery.value = matched.label;
          emits("update:destination", matched);
          showSuggestions.value = false;
        }
      }
    } catch {
      // fallback
    }
  }

  // Validate destination
  if (!internalDestination.value) {
    destinationError.value = true;
    validationMessage.value = "Please select a destination or hotel from the list.";
    showSuggestions.value = true;
    return;
  }

  // Ensure dates exist
  if (!internalCheckIn.value) internalCheckIn.value = tomorrowString;
  if (!internalCheckOut.value) internalCheckOut.value = dayAfterTomorrowString;
  emits("update:checkIn", internalCheckIn.value);
  emits("update:checkOut", internalCheckOut.value);

  // Validate dates
  if (nights.value <= 0) {
    datesError.value = true;
    validationMessage.value = "Please select valid check-in and check-out dates.";
    showDatePicker.value = true;
    return;
  }

  // Ensure nationality exists
  const nationality = (internalNationality.value || "PK").toUpperCase();

  const formattedRooms = internalRooms.value.map((room) => ({
    adults: Number(room.adults || 1),
    children: room.children_ages?.length || 0,
    children_ages: (room.children_ages || []).map((a) => Number(a)),
  }));

  const payload = {
    destination: internalDestination.value,
    check_in: internalCheckIn.value,
    check_out: internalCheckOut.value,
    guest_nationality: nationality,
    rooms: formattedRooms,
  };

  saveRecentSearch(payload);

  if (props.standaloneRedirect) {
    router.push({
      name: "HotelSearch",
      query: {
        destination_type: internalDestination.value.type,
        destination_value: internalDestination.value.value,
        destination_label: internalDestination.value.label,
        check_in: internalCheckIn.value,
        check_out: internalCheckOut.value,
        guest_nationality: nationality,
        rooms: JSON.stringify(formattedRooms),
        search_key: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
      },
    });
  } else {
    emits("search", payload);
  }
};
</script>

<template>
  <div class="w-full">
    <!-- Validation alert if any -->
    <div
      v-if="validationMessage"
      class="mb-3 flex items-center justify-between rounded border border-destructive/40 bg-destructive/10 px-3.5 py-2 text-xs font-semibold text-destructive animate-fade-in"
    >
      <div class="flex items-center gap-2">
        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-sm bg-destructive text-white text-[10px] font-bold">!</span>
        <span>{{ validationMessage }}</span>
      </div>
      <button
        type="button"
        class="text-destructive/70 hover:text-destructive p-0.5"
        @click="validationMessage = ''"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </div>

    <!-- MAIN SEARCH BAR (Desktop: 1 horizontal bar with 1px dividers, Mobile: stacked) -->
    <form
      class="w-full rounded border-2 border-primary/25 bg-card shadow-[0_4px_24px_-4px_rgba(0,0,0,0.08)] transition-all hover:border-primary/45 focus-within:border-primary focus-within:ring-4 focus-within:ring-primary/10"
      @submit.prevent="submitSearch"
    >
      <div class="flex flex-col divide-y divide-border lg:flex-row lg:items-stretch lg:divide-x lg:divide-y-0">
        
        <!-- FIELD 1: DESTINATION -->
        <div
          ref="destinationFieldRef"
          class="relative flex-[1.2] p-3 sm:px-4 sm:py-3.5 transition-colors hover:bg-muted/30 focus-within:bg-background/90"
          :class="[
            destinationError ? 'border-destructive ring-1 ring-destructive rounded-t lg:rounded-l lg:rounded-tr-none' : '',
          ]"
        >
          <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
              <MapPin class="h-4.5 w-4.5" />
            </div>
            <div class="min-w-0 flex-1">
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">
                Destination or property
              </label>
              <div class="flex items-center">
                <input
                  v-model="destinationQuery"
                  type="text"
                  autocomplete="off"
                  spellcheck="false"
                  class="h-6 w-full border-0 bg-transparent p-0 text-sm font-bold text-foreground outline-none placeholder:font-normal placeholder:text-muted-foreground focus:ring-0"
                  placeholder="Where are you going?"
                  @focus="openSuggestions"
                  @input="handleDestinationInput"
                  @keydown.enter.prevent="submitSearch"
                />
                <button
                  v-if="destinationQuery"
                  type="button"
                  class="shrink-0 p-1 text-muted-foreground hover:text-foreground"
                  aria-label="Clear destination"
                  @click="clearDestination"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <!-- Destination Autocomplete Dropdown -->
          <div
            v-if="showSuggestions"
            class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 max-h-80 overflow-y-auto rounded border border-border bg-popover py-1 shadow-lg sm:min-w-[340px]"
          >
            <div v-if="isLoadingSuggestions" class="flex items-center gap-2 px-4 py-3 text-xs text-muted-foreground">
              <Loader2 class="h-3.5 w-3.5 animate-spin text-primary" />
              Searching destinations...
            </div>
            <div
              v-else-if="!destinationSuggestions.length"
              class="px-4 py-3 text-xs text-muted-foreground"
            >
              {{ destinationQuery.length < 2 ? 'Type at least 2 characters to search...' : 'No destinations or properties found.' }}
            </div>
            <div v-else>
              <button
                v-for="suggestion in destinationSuggestions"
                :key="`${suggestion.type}-${suggestion.value}`"
                type="button"
                class="flex w-full items-center gap-3 border-b border-border/40 px-3.5 py-2.5 text-left text-xs transition-colors hover:bg-muted last:border-b-0"
                @click="selectDestination(suggestion)"
              >
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded border border-border bg-background text-primary">
                  <Hotel v-if="suggestion.type === 'hotel'" class="h-3.5 w-3.5" />
                  <MapPin v-else class="h-3.5 w-3.5" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate font-semibold text-foreground">
                    {{ suggestion.label }}
                  </p>
                  <p class="text-[11px] capitalize text-muted-foreground">
                    {{ suggestion.type }}
                  </p>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- FIELD 2: DATES (CHECK-IN & CHECK-OUT) -->
        <div
          ref="dateFieldRef"
          class="relative flex-1 p-3 sm:px-4 sm:py-3.5 transition-colors hover:bg-muted/30 focus-within:bg-background/90 cursor-pointer"
          :class="[
            datesError ? 'border-destructive ring-1 ring-destructive' : '',
          ]"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left outline-none"
            @click="showDatePicker = !showDatePicker; showSuggestions = false; showGuestsPanel = false"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                <CalendarDays class="h-4.5 w-4.5" />
              </div>
              <div class="min-w-0 flex-1">
                <span class="block text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">
                  Check-in — Check-out
                </span>
                <div class="truncate text-sm font-bold text-foreground">
                  <span v-if="internalCheckIn && internalCheckOut">
                    {{ formatDisplayDate(internalCheckIn) }} – {{ formatDisplayDate(internalCheckOut) }}
                  </span>
                  <span v-else class="font-normal text-muted-foreground">
                    Select stay dates
                  </span>
                </div>
              </div>
            </div>
            <span
              v-if="nights > 0"
              class="shrink-0 rounded bg-primary/10 border border-primary/20 px-2 py-0.5 text-xs font-bold text-primary"
            >
              {{ nights }} {{ nights === 1 ? 'night' : 'nights' }}
            </span>
          </button>

          <!-- DUAL-MONTH DATE PICKER POPOVER -->
          <div
            v-if="showDatePicker"
            class="absolute left-0 top-[calc(100%+4px)] z-50 w-full min-w-[320px] rounded border border-border bg-popover p-4 shadow-xl sm:w-[620px] lg:-left-20"
          >
            <!-- Navigation Header -->
            <div class="mb-3 flex items-center justify-between border-b border-border pb-3">
              <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                :disabled="!canGoPrevMonth"
                @click="prevMonth"
              >
                <ChevronLeft class="h-4 w-4" />
              </button>
              <div class="flex items-center gap-2 text-xs font-semibold text-foreground">
                <span class="hidden sm:inline text-muted-foreground">Selecting:</span>
                <span v-if="isSelectingEndDate" class="text-primary font-bold">Select Check-out Date</span>
                <span v-else class="text-foreground">Select Check-in Date</span>
              </div>
              <button
                type="button"
                class="flex h-7 w-7 items-center justify-center rounded border border-border bg-background text-foreground hover:bg-muted"
                @click="nextMonth"
              >
                <ChevronRight class="h-4 w-4" />
              </button>
            </div>

            <!-- Two Months Side-by-Side on Desktop -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
              
              <!-- Month 1 -->
              <div>
                <p class="mb-2 text-center text-xs font-bold text-foreground">
                  {{ formatMonthHeader(month1) }}
                </p>
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-muted-foreground mb-1">
                  <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                  <div
                    v-for="(dayStr, idx) in month1Days"
                    :key="`m1-${idx}`"
                    class="aspect-square p-0.5"
                  >
                    <button
                      v-if="dayStr"
                      type="button"
                      :disabled="isPastDate(dayStr)"
                      class="flex h-full w-full items-center justify-center text-xs transition-colors rounded-sm"
                      :class="[
                        isStartDate(dayStr)
                          ? 'bg-primary text-primary-foreground font-bold'
                          : '',
                        isEndDate(dayStr)
                          ? 'bg-primary text-primary-foreground font-bold'
                          : '',
                        isInSelectedRange(dayStr)
                          ? 'bg-primary/10 text-primary font-medium'
                          : '',
                        isInHoverRange(dayStr) && !isInSelectedRange(dayStr)
                          ? 'bg-primary/10 text-primary font-medium'
                          : '',
                        !isStartDate(dayStr) && !isEndDate(dayStr) && !isInSelectedRange(dayStr) && !isPastDate(dayStr)
                          ? 'text-foreground hover:bg-muted'
                          : '',
                        isPastDate(dayStr)
                          ? 'text-muted-foreground/40 cursor-not-allowed line-through text-[11px]'
                          : '',
                      ]"
                      @click="handleDateClick(dayStr)"
                      @mouseenter="handleDateHover(dayStr)"
                    >
                      {{ Number(dayStr.split('-')[2]) }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Month 2 -->
              <div class="hidden sm:block border-l border-border pl-6">
                <p class="mb-2 text-center text-xs font-bold text-foreground">
                  {{ formatMonthHeader(month2) }}
                </p>
                <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold uppercase text-muted-foreground mb-1">
                  <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                </div>
                <div class="grid grid-cols-7 gap-0.5">
                  <div
                    v-for="(dayStr, idx) in month2Days"
                    :key="`m2-${idx}`"
                    class="aspect-square p-0.5"
                  >
                    <button
                      v-if="dayStr"
                      type="button"
                      :disabled="isPastDate(dayStr)"
                      class="flex h-full w-full items-center justify-center text-xs transition-colors rounded-sm"
                      :class="[
                        isStartDate(dayStr)
                          ? 'bg-primary text-primary-foreground font-bold'
                          : '',
                        isEndDate(dayStr)
                          ? 'bg-primary text-primary-foreground font-bold'
                          : '',
                        isInSelectedRange(dayStr)
                          ? 'bg-primary/10 text-primary font-medium'
                          : '',
                        isInHoverRange(dayStr) && !isInSelectedRange(dayStr)
                          ? 'bg-primary/10 text-primary font-medium'
                          : '',
                        !isStartDate(dayStr) && !isEndDate(dayStr) && !isInSelectedRange(dayStr) && !isPastDate(dayStr)
                          ? 'text-foreground hover:bg-muted'
                          : '',
                        isPastDate(dayStr)
                          ? 'text-muted-foreground/40 cursor-not-allowed line-through text-[11px]'
                          : '',
                      ]"
                      @click="handleDateClick(dayStr)"
                      @mouseenter="handleDateHover(dayStr)"
                    >
                      {{ Number(dayStr.split('-')[2]) }}
                    </button>
                  </div>
                </div>
              </div>

            </div>

            <!-- Quick Presets + Apply Bar -->
            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
              <div class="flex flex-wrap items-center gap-1.5">
                <button
                  type="button"
                  class="rounded-sm border border-border bg-background px-2 py-1 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                  @click="applyPreset(1, 0)"
                >
                  Tonight
                </button>
                <button
                  type="button"
                  class="rounded-sm border border-border bg-background px-2 py-1 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                  @click="applyPreset(2, 1)"
                >
                  2 nights
                </button>
                <button
                  type="button"
                  class="rounded-sm border border-border bg-background px-2 py-1 text-[11px] font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
                  @click="applyPreset(7, 1)"
                >
                  1 week
                </button>
              </div>

              <div class="flex items-center gap-2">
                <span v-if="internalCheckIn && internalCheckOut" class="text-xs text-muted-foreground">
                  {{ formatFullDisplayDate(internalCheckIn) }} – {{ formatFullDisplayDate(internalCheckOut) }}
                </span>
                <button
                  type="button"
                  class="rounded bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground hover:bg-primary/90"
                  @click="showDatePicker = false"
                >
                  Apply
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- FIELD 3: GUESTS & ROOMS POPOVER -->
        <div
          ref="guestsFieldRef"
          class="relative flex-1 p-3 sm:px-4 sm:py-3.5 transition-colors hover:bg-muted/30 focus-within:bg-background/90 cursor-pointer"
        >
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 text-left outline-none"
            @click="showGuestsPanel = !showGuestsPanel; showSuggestions = false; showDatePicker = false"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                <Users class="h-4.5 w-4.5" />
              </div>
              <div class="min-w-0 flex-1">
                <span class="block text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground">
                  Guests &amp; Rooms
                </span>
                <div class="truncate text-sm font-bold text-foreground">
                  {{ guestsSummary.label }}
                </div>
              </div>
            </div>
            <ChevronDown
              class="h-4 w-4 shrink-0 text-muted-foreground transition-transform"
              :class="{ 'rotate-180': showGuestsPanel }"
            />
          </button>

          <!-- GUESTS & ROOMS POPOVER -->
          <div
            v-if="showGuestsPanel"
            class="absolute right-0 top-[calc(100%+4px)] z-50 w-full min-w-[300px] rounded border border-border bg-popover p-4 shadow-xl sm:w-[360px]"
          >
            <!-- Nationality selector (ensures API payload is valid) -->
            <div class="mb-3.5 border-b border-border pb-3">
              <label class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-foreground">
                <Globe2 class="h-3.5 w-3.5 text-primary" />
                Guest Nationality
              </label>
              <CountryDropdown
                v-model="internalNationality"
                key-value="code"
                placeholder="Select nationality"
                trigger-class="!h-9 !rounded !border-border !bg-background !px-3 !text-xs !font-semibold !text-foreground"
                @update:model-value="updateNationality"
              />
            </div>

            <!-- Room List -->
            <div class="max-h-72 space-y-3.5 overflow-y-auto pr-1">
              <div
                v-for="(room, index) in internalRooms"
                :key="index"
                class="rounded border border-border bg-background/50 p-3"
              >
                <div class="mb-2 flex items-center justify-between">
                  <p class="text-xs font-bold uppercase tracking-wide text-foreground">
                    Room {{ index + 1 }}
                  </p>
                  <button
                    v-if="internalRooms.length > 1"
                    type="button"
                    class="text-[11px] font-semibold text-destructive hover:underline"
                    @click="removeRoom(index)"
                  >
                    Remove
                  </button>
                </div>

                <!-- Adults Stepper -->
                <div class="flex items-center justify-between py-1.5">
                  <div>
                    <p class="text-xs font-semibold text-foreground">Adults</p>
                    <p class="text-[10px] text-muted-foreground">Age 18+</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="flex h-7 w-7 items-center justify-center rounded border border-border bg-card text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                      :disabled="room.adults <= 1"
                      @click="decrementAdults(room)"
                    >
                      <Minus class="h-3.5 w-3.5" />
                    </button>
                    <span class="w-5 text-center text-xs font-bold text-foreground">
                      {{ room.adults }}
                    </span>
                    <button
                      type="button"
                      class="flex h-7 w-7 items-center justify-center rounded border border-border bg-card text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                      :disabled="room.adults >= MAX_ADULTS_PER_ROOM"
                      @click="incrementAdults(room)"
                    >
                      <Plus class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Children Stepper -->
                <div class="flex items-center justify-between py-1.5 border-t border-border/50">
                  <div>
                    <p class="text-xs font-semibold text-foreground">Children</p>
                    <p class="text-[10px] text-muted-foreground">Ages 0 – 17</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      class="flex h-7 w-7 items-center justify-center rounded border border-border bg-card text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                      :disabled="!room.children_ages || room.children_ages.length <= 0"
                      @click="decrementChildren(room)"
                    >
                      <Minus class="h-3.5 w-3.5" />
                    </button>
                    <span class="w-5 text-center text-xs font-bold text-foreground">
                      {{ room.children_ages ? room.children_ages.length : 0 }}
                    </span>
                    <button
                      type="button"
                      class="flex h-7 w-7 items-center justify-center rounded border border-border bg-card text-foreground hover:bg-muted disabled:cursor-not-allowed disabled:opacity-30"
                      :disabled="room.children_ages && room.children_ages.length >= MAX_CHILDREN_PER_ROOM"
                      @click="incrementChildren(room)"
                    >
                      <Plus class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Children Age Selects -->
                <div v-if="room.children_ages && room.children_ages.length" class="mt-2 pt-2 border-t border-border/50">
                  <p class="mb-1 text-[10px] font-bold uppercase text-muted-foreground">
                    Children's Age at Check-out
                  </p>
                  <div class="grid grid-cols-2 gap-2">
                    <div
                      v-for="(age, childIdx) in room.children_ages"
                      :key="childIdx"
                      class="text-[11px] text-muted-foreground"
                    >
                      <span>Child {{ childIdx + 1 }}</span>
                      <select
                        :value="age"
                        class="mt-0.5 h-8 w-full rounded border border-border bg-background px-2 text-xs font-medium text-foreground outline-none focus:border-primary"
                        @change="updateChildAge(room, childIdx, $event.target.value)"
                      >
                        <option :value="0">Under 1</option>
                        <option v-for="y in 17" :key="y" :value="y">{{ y }} yr{{ y > 1 ? 's' : '' }}</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Footer: Add room + Done -->
            <div class="mt-4 flex items-center justify-between border-t border-border pt-3">
              <button
                type="button"
                class="flex items-center gap-1 text-xs font-bold text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="internalRooms.length >= MAX_ROOMS"
                @click="addRoom"
              >
                <Plus class="h-3.5 w-3.5" />
                Add Another Room
              </button>
              <button
                type="button"
                class="rounded bg-primary px-4 py-1.5 text-xs font-bold text-primary-foreground hover:bg-primary/90"
                @click="showGuestsPanel = false"
              >
                Done
              </button>
            </div>
          </div>
        </div>

        <!-- FIELD 4: SEARCH ACTION BUTTON -->
        <div class="flex items-center p-2.5 sm:p-3">
          <button
            type="submit"
            :disabled="props.isLoading"
            class="flex h-12 lg:h-[50px] w-full items-center justify-center gap-2 rounded bg-primary px-8 text-sm font-bold uppercase tracking-wider text-primary-foreground shadow-md shadow-primary/25 transition-all hover:bg-primary/95 hover:shadow-lg hover:shadow-primary/35 active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
            @click.prevent="submitSearch"
          >
            <Loader2 v-if="props.isLoading" class="h-4.5 w-4.5 animate-spin shrink-0" />
            <Search v-else class="h-4.5 w-4.5 shrink-0 stroke-[2.5]" />
            <span>Search</span>
          </button>
        </div>

      </div>
    </form>

    <!-- RECENT SEARCHES CHIPS -->
    <div
      v-if="recentSearches.length"
      class="mt-3 flex flex-wrap items-center gap-2"
    >
      <span class="flex items-center gap-1.5 text-xs font-bold text-muted-foreground mr-0.5">
        <Clock class="h-3.5 w-3.5 text-primary" />
        Recent:
      </span>
      <button
        v-for="s in recentSearches"
        :key="s.signature"
        type="button"
        class="inline-flex items-center gap-2 rounded border border-border bg-card px-2.5 py-1 text-xs font-medium text-foreground transition-all hover:border-primary/50 hover:bg-muted/40 shadow-xs"
        @click="applyRecentSearch(s)"
      >
        <span class="font-bold text-primary truncate max-w-[130px]">
          {{ s.destination?.label || 'Hotel stay' }}
        </span>
        <span class="text-muted-foreground text-[11px]">
          {{ formatDisplayDate(s.check_in) }}–{{ formatDisplayDate(s.check_out) }}
        </span>
      </button>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-2px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in {
  animation: fadeIn 0.15s ease-out forwards;
}
</style>
