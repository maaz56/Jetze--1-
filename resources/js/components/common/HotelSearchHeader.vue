<script setup>
import Button from "@/components/ui/button/Button.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { CalendarDays, ChevronDown, Hotel, MapPin, Search, X } from "lucide-vue-next";
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { debounce } from "lodash";

const router = useRouter();
const hotelStore = useHotelStore();

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const dayAfterTomorrow = new Date(today);
dayAfterTomorrow.setDate(today.getDate() + 2);

const formatDateInput = (date) => date.toISOString().slice(0, 10);

const destinationQuery = ref("");
const selectedDestination = ref(null);
const destinationSuggestions = ref([]);
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
const showSuggestions = ref(false);
const checkIn = ref(formatDateInput(tomorrow));
const checkOut = ref(formatDateInput(dayAfterTomorrow));
const guestNationality = ref("PK");
const rooms = ref([{ adults: 1, children: 0, children_ages: [] }]);
const showGuestsPanel = ref(false);
const errorMessage = ref("");

const nights = computed(() => {
    const diff = Math.ceil((new Date(checkOut.value) - new Date(checkIn.value)) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
});

const guestsSummary = computed(() => {
    const roomCount = rooms.value.length;
    const guests = rooms.value.reduce((total, room) => total + Number(room.adults || 0) + Number(room.children || 0), 0);
    return `${roomCount} Room${roomCount > 1 ? "s" : ""}, ${guests} Guest${guests > 1 ? "s" : ""}`;
});

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

    router.push({
        name: "HotelSearch",
        query: {
            destination_type: selectedDestination.value.type,
            destination_value: selectedDestination.value.value,
            destination_label: selectedDestination.value.label,
            check_in: checkIn.value,
            check_out: checkOut.value,
            guest_nationality: guestNationality.value.toUpperCase(),
            rooms: JSON.stringify(rooms.value),
        },
    });
};
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-visible bg-white">
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

                    <div v-if="showSuggestions" class="absolute left-4 right-4 top-[78px] z-40 max-h-80 overflow-y-auto border border-gray-200 bg-white shadow-2xl">
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

                    <div v-if="showGuestsPanel" class="absolute left-0 right-0 top-[88px] z-30 border border-gray-200 bg-white p-4 shadow-2xl lg:min-w-80">
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
                    <Button class="h-12 rounded bg-primary px-6 text-base font-bold text-white hover:bg-primary/90" @click="submitHotelSearch">
                        <Search class="h-5 w-5" />
                        Search
                    </Button>
                </div>
            </div>
        </div>

        <div v-if="errorMessage" class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ errorMessage }}
        </div>
    </div>
</template>
