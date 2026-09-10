<script setup>
import Button from "@/components/ui/button/Button.vue";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { useHotelStore } from "@/services/stores/hotel";
import { CalendarDays, ChevronDown, Clock3, Hotel, MapPin, Search, Users, X } from "lucide-vue-next";
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { debounce } from "lodash";

const router = useRouter();
const hotelStore = useHotelStore();
const HOTEL_RECENT_SEARCHES_KEY = "hotel_recent_search_history";
const MAX_RECENT_HOTEL_SEARCHES = 4;

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const dayAfterTomorrow = new Date(today);
dayAfterTomorrow.setDate(today.getDate() + 2);

const formatDateInput = (date) => date.toISOString().slice(0, 10);
const formatDateLabel = (date) =>
    date
        ? new Intl.DateTimeFormat("en-GB", {
              day: "2-digit",
              month: "2-digit",
              year: "numeric",
          }).format(new Date(`${date}T00:00:00`))
        : "Select date";

const destinationQuery = ref("");
const selectedDestination = ref(null);
const destinationSuggestions = ref([]);
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
const showSuggestions = ref(false);
const checkIn = ref(formatDateInput(tomorrow));
const checkOut = ref(formatDateInput(dayAfterTomorrow));
const checkInInput = ref(null);
const checkOutInput = ref(null);
const guestNationality = ref("PK");
const rooms = ref([{ adults: 1, children: 0, children_ages: [] }]);
const showGuestsPanel = ref(false);
const errorMessage = ref("");
const destinationFieldRef = ref(null);
const recentHotelSearches = ref([]);

const nights = computed(() => {
    const diff = Math.ceil((new Date(checkOut.value) - new Date(checkIn.value)) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
});

const guestsSummary = computed(() => {
    const roomCount = rooms.value.length;
    const guests = rooms.value.reduce((total, room) => total + Number(room.adults || 0) + Number(room.children || 0), 0);
    return `${roomCount} Room${roomCount > 1 ? "s" : ""}, ${guests} Guest${guests > 1 ? "s" : ""}`;
});

const openDatePicker = (input) => {
    if (!input) return;

    input.focus({ preventScroll: true });

    if (typeof input.showPicker === "function") {
        try {
            input.showPicker();
            return;
        } catch {
            // Fall through for browsers that do not allow showPicker here.
        }
    }

    input.click();
};

const readRecentHotelSearches = () => {
    try {
        const searches = JSON.parse(localStorage.getItem(HOTEL_RECENT_SEARCHES_KEY));
        return Array.isArray(searches) ? searches.slice(0, MAX_RECENT_HOTEL_SEARCHES) : [];
    } catch {
        return [];
    }
};

const saveRecentHotelSearch = (search) => {
    const signature = JSON.stringify({
        destination: search.destination,
        check_in: search.check_in,
        check_out: search.check_out,
        rooms: search.rooms,
    });
    const entry = { ...search, signature, savedAt: Date.now() };
    const searches = readRecentHotelSearches().filter((item) => item.signature !== signature);
    recentHotelSearches.value = [entry, ...searches].slice(0, MAX_RECENT_HOTEL_SEARCHES);
    localStorage.setItem(HOTEL_RECENT_SEARCHES_KEY, JSON.stringify(recentHotelSearches.value));
};

const formatRecentHotelDate = (date) => {
    if (!date) return "";

    return new Intl.DateTimeFormat("en", { month: "short", day: "numeric" }).format(new Date(`${date}T00:00:00`));
};

const applyRecentHotelSearch = async (search) => {
    selectedDestination.value = search.destination || null;
    destinationQuery.value = search.destination?.label || "";
    checkIn.value = search.check_in || checkIn.value;
    checkOut.value = search.check_out || checkOut.value;
    rooms.value = Array.isArray(search.rooms) && search.rooms.length ? search.rooms : rooms.value;
    showSuggestions.value = false;
    await submitHotelSearch();
};

const fetchSuggestions = debounce(async () => {
    try {
        destinationSuggestions.value = await hotelStore.fetchSuggestions(destinationQuery.value);
    } catch {
        destinationSuggestions.value = [];
    }
}, 250);

const loadSuggestionsNow = async () => {
    destinationSuggestions.value = await hotelStore.fetchSuggestions(destinationQuery.value);
    return destinationSuggestions.value;
};

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

const closeFloatingPanels = (event) => {
    if (destinationFieldRef.value && !destinationFieldRef.value.contains(event.target)) {
        showSuggestions.value = false;
    }

};

onMounted(() => {
    recentHotelSearches.value = readRecentHotelSearches();
    document.addEventListener("click", closeFloatingPanels);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", closeFloatingPanels);
    fetchSuggestions.cancel();
});

const selectDestination = (suggestion) => {
    selectedDestination.value = suggestion;
    destinationQuery.value = suggestion.label;
    destinationSuggestions.value = [];
    showSuggestions.value = false;
};

const updateChildren = (room, value) => {
    const children = Math.max(0, Math.min(4, Number(value || 0)));
    room.children = children;
    room.children_ages = Array.from({ length: children }, (_, index) => room.children_ages[index] ?? 5);
};

const addRoom = () => {
    rooms.value.push({ adults: 1, children: 0, children_ages: [] });
};

const removeRoom = (index) => {
    if (rooms.value.length > 1) {
        rooms.value.splice(index, 1);
    }
};

const destinationIcon = (type) => (type === "hotel" ? Hotel : MapPin);

const submitHotelSearch = async () => {
    errorMessage.value = "";

    if (!selectedDestination.value && destinationQuery.value.trim().length >= 2) {
        try {
            const suggestions = await loadSuggestionsNow();
            if (suggestions.length) {
                selectDestination(suggestions[0]);
            }
        } catch {
            selectedDestination.value = null;
        }
    }

    if (!selectedDestination.value) {
        errorMessage.value = "Select a city or hotel from the suggestions.";
        showSuggestions.value = true;
        return;
    }

    if (!checkIn.value || !checkOut.value || nights.value <= 0) {
        errorMessage.value = "Select valid check-in and check-out dates.";
        return;
    }

    const hotelSearch = {
        destination: selectedDestination.value,
        check_in: checkIn.value,
        check_out: checkOut.value,
        rooms: rooms.value,
    };
    saveRecentHotelSearch(hotelSearch);

    await router.push({
        name: "HotelSearch",
        query: {
            destination_type: selectedDestination.value.type,
            destination_value: selectedDestination.value.value,
            destination_label: selectedDestination.value.label,
            check_in: checkIn.value,
            check_out: checkOut.value,
            guest_nationality: guestNationality.value.toUpperCase(),
            rooms: JSON.stringify(hotelSearch.rooms),
            // A route update must occur even when the traveller submits the
            // exact same search again from recent searches.
            search_key: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        },
    });

    await nextTick();
    requestAnimationFrame(() => window.scrollTo({ top: 0, left: 0, behavior: "auto" }));
};
</script>

<template>
    <form class="space-y-5" @submit.prevent="submitHotelSearch">
        <div class="overflow-visible rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="grid grid-cols-1 divide-y divide-slate-200 lg:grid-cols-12 lg:divide-x lg:divide-y-0">
                <div ref="destinationFieldRef" class="relative p-4 sm:p-5 lg:col-span-3 lg:py-4">
                    <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                        <MapPin class="h-3.5 w-3.5" />
                        Destination or property
                    </label>
                    <div class="mt-2 flex h-11 items-center gap-3 rounded border border-transparent bg-slate-50 px-3 transition focus-within:border-primary/35 focus-within:bg-white focus-within:ring-4 focus-within:ring-primary/10">
                        <Search class="h-5 w-5 shrink-0 text-primary" />
                        <input
                            v-model="destinationQuery"
                            type="text"
                            autocomplete="off"
                            class="h-full w-full border-0 bg-transparent text-left text-base font-medium text-slate-900 outline-none placeholder:font-normal placeholder:text-slate-400"
                            placeholder="City, hotel or area"
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

                    <div v-if="showSuggestions" class="absolute left-4 right-4 top-[92px] z-40 max-h-80 overflow-y-auto rounded border border-slate-200 bg-white py-1 shadow-2xl sm:left-5 sm:right-5">
                        <div v-if="isLoadingSuggestions" class="px-4 py-3 text-sm text-gray-500">Loading destinations...</div>
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
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 bg-white text-sm font-bold text-slate-900 shadow-sm">{{ nights }}</span>
                        <span class="mt-1 text-[10px] font-bold uppercase tracking-wide text-slate-500">Night{{ nights === 1 ? "" : "s" }}</span>
                    </div>
                </div>

                <div class="p-4 sm:p-5 lg:col-span-3 lg:py-4">
                    <label class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-primary">
                        <Users class="h-3.5 w-3.5" />
                        Rooms &amp; guests
                    </label>
                    <Popover v-model:open="showGuestsPanel">
                        <PopoverTrigger as-child>
                            <button type="button" class="mt-2 flex h-11 w-full items-center justify-between gap-3 rounded border border-transparent bg-slate-50 px-3 text-left text-base font-bold text-slate-900 transition hover:bg-primary/5 focus:outline-none focus:ring-4 focus:ring-primary/10">
                                <div class="text-left">
                                    <p>{{ guestsSummary }}</p>
                                    <p class="mt-1 text-sm font-medium text-gray-500">Choose rooms and guests</p>
                                </div>
                                <ChevronDown class="h-4 w-4 shrink-0 transition-transform" :class="{ 'rotate-180': showGuestsPanel }" />
                            </button>
                        </PopoverTrigger>

                        <PopoverContent side="bottom" align="end" :side-offset="8" class="z-[100] w-[380px] max-w-[calc(100vw-2rem)] rounded border border-slate-200 bg-white p-5 shadow-2xl">
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
                        <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                            <button type="button" class="text-sm font-semibold text-primary hover:text-primary/80" @click="addRoom">Add room</button>
                            <button type="button" class="rounded bg-primary px-5 py-2 text-xs font-bold uppercase tracking-wide text-primary-foreground hover:bg-primary/90" @click="showGuestsPanel = false">Done</button>
                        </div>
                        </PopoverContent>
                    </Popover>
                </div>

                <div class="flex items-center p-4 sm:p-5 lg:col-span-2 lg:p-3">
                    <Button type="submit" class="h-12 w-full rounded bg-[linear-gradient(180deg,hsl(var(--primary-button-start)),hsl(var(--primary-button-end)))] px-6 text-base font-bold text-primary-foreground shadow-lg shadow-primary/30 transition hover:-translate-y-0.5 hover:brightness-110 lg:h-14">
                        <Search class="h-5 w-5" />
                        Search
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="recentHotelSearches.length" class="flex flex-wrap items-center gap-2">
            <button
                v-for="search in recentHotelSearches"
                :key="search.signature"
                type="button"
                class="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-white hover:text-primary"
                @click="applyRecentHotelSearch(search)"
            >
                <Clock3 class="h-3.5 w-3.5 text-primary" />
                <span>{{ search.destination?.label || "Hotel search" }}</span>
                <span class="text-gray-500">{{ formatRecentHotelDate(search.check_in) }} - {{ formatRecentHotelDate(search.check_out) }}</span>
            </button>
        </div>

        <div v-if="errorMessage" class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ errorMessage }}
        </div>
    </form>
</template>

<style scoped>
.hotel-date-input::-webkit-calendar-picker-indicator {
    display: none;
}
</style>
