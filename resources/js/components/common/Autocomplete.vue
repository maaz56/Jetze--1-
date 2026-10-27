

<template>
    <div class="relative min-w-0 dropdown">
        <div class="relative h-[110px] min-w-0">
            <input
                type="text"
                :placeholder="isFocused ? 'Type to search...' : ''"
                v-model="search"
                autocomplete="off"
                autocorrect="off"
                autocapitalize="off"
                spellcheck="false"
                class="absolute inset-0 z-10 h-full w-full cursor-pointer truncate border-0 bg-transparent pl-14 pr-10 text-lg font-bold text-slate-900 outline-none ring-0 sm:text-2xl"
                :class="{ 'text-transparent caret-transparent': !isFocused }"
                @input="handleInput"
                @keydown="handleKeydown"
                @focus="handleFocus"
                @click="handleInputClick"
                @blur="handleBlur"
                ref="inputEl"
            />

            <div
                v-if="!isFocused"
                class="pointer-events-none absolute inset-0 flex min-w-0 items-center gap-3 px-4"
            >
                <MapPin class="h-7 w-7 shrink-0 text-gray-600" :stroke-width="2" />
                <div class="min-w-0">
                    <template v-if="selectedAirport">
                        <p class="truncate text-lg font-bold leading-tight text-gray-900 sm:text-2xl">
                            {{ selectedAirport.city_name }}
                        </p>
                        <p class="mt-1 mb-2 truncate text-sm text-gray-500">
                            {{ selectedAirport.iata_code }}, {{ selectedAirport.name }}
                        </p>
                    </template>
                    <template v-else>
                        <p class="text-lg font-bold leading-tight text-gray-900 sm:text-2xl">Select City</p>
                        <p class="mt-1 text-sm text-gray-500">Airport Name, Country</p>
                    </template>
                </div>
            </div>

            <button v-if="search && isFocused" @click.stop="clearSearch" type="button"
                class="absolute right-3 top-1/2 z-30 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                <CircleX class="h-4 w-4" />
            </button>
        </div>

        <!-- Use teleport to move the dropdown to body to avoid z-index issues -->
        <Teleport to="body">
            <ul v-if="isOpen" :style="dropdownStyle"
                class="fixed bg-white z-[9999] border border-gray-200 rounded-lg shadow-lg scrollbar-container">
                <div class="max-h-72 overflow-y-auto scrollbar">
                    <!-- Country / spelling hint -->
                    <li
                        v-if="matchedCountry || correctedTo"
                        class="sticky top-0 z-10 flex items-center gap-2 border-b border-gray-100 bg-slate-50 px-4 py-2 text-xs text-slate-600"
                    >
                        <img
                            v-if="matchedCountry"
                            :src="matchedCountry.flag"
                            :alt="matchedCountry.name"
                            class="h-3.5 w-5 shrink-0 rounded-sm object-cover ring-1 ring-black/5"
                            loading="lazy"
                        />
                        <span v-if="correctedTo">
                            Showing results for <span class="font-semibold text-slate-900">{{ correctedTo }}</span>
                        </span>
                        <span v-else>
                            Airports in <span class="font-semibold text-slate-900">{{ matchedCountry.name }}</span>
                        </span>
                    </li>

                    <li class="flex cursor-pointer items-center justify-between gap-3 border-b border-gray-100 px-4 py-2.5 last:border-b-0 hover:bg-gray-50"
                        :class="{ 'bg-gray-50': index === focusedIndex }" v-for="(item, index) in searchResults"
                        :key="item.id || index" @click.stop="setSelected(item)">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-semibold text-slate-900">{{ item.city_name || item.name }}</span>
                                <span class="shrink-0 rounded bg-primary px-1.5 py-0.5 text-[11px] font-bold leading-none text-white">
                                    {{ item.iata_code }}
                                </span>
                            </div>
                            <span class="block truncate text-sm text-gray-500">{{ item.name }}</span>
                        </div>
                        <div v-if="getAirportCountry(item)" class="flex shrink-0 items-center gap-2 text-sm text-gray-500">
                            <span
                                v-if="getAirportCountry(item).name !== item.city_name"
                                class="hidden max-w-[9rem] truncate sm:inline"
                            >{{ getAirportCountry(item).name }}</span>
                            <img
                                :src="getAirportCountry(item).flag"
                                :alt="getAirportCountry(item).name"
                                :title="getAirportCountry(item).name"
                                class="h-3.5 w-5 rounded-sm object-cover ring-1 ring-black/5"
                                loading="lazy"
                            />
                        </div>
                    </li>

                    <!-- Empty state -->
                    <li v-if="!isSearching && searchResults.length === 0"
                        class="text-gray-500 h-20 border flex items-center justify-center">
                        Nothing found.
                    </li>

                    <!-- Loading state -->
                    <li v-if="isSearching" class="h-20 border flex items-center justify-center">
                        <Spinner />
                    </li>
                </div>
            </ul>
        </Teleport>
    </div>
</template>

<script setup>
import eventBus from "@/services/eventBus";
import { debounce } from "lodash";
import { CircleX, MapPin, PlaneLanding, PlaneTakeoff } from "lucide-vue-next";

import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import Spinner from "../common/Spinner.vue";
import { getAirportCountry, searchAirports } from "@/lib/airportSearch";

const store = useStore();
const props = defineProps({
    source: {
        type: Array,
        required: true,
        default: () => [],
    },
    modelValue: {
        type: String,
        default: null,
    },
    placeholder: {
        type: String,
        default: "Origin",
    },
    label: {
        type: String,
    },
    tabId: {
        type: String,
        default: "default"
    },
    icon:{
        type: String,
        default: "PlaneTakeoff"
    },
    routeDisplay: {
        type: Boolean,
        default: false,
    },
    clearOnClick: {
        type: Boolean,
        default: false,
    }

});

const emit = defineEmits(["update:modelValue"]);

const isSearching = computed(() => {
    return (isLoading.value || isLoadingAirport.value) && searchResults.value.length === 0;
});

const icons = {
    PlaneTakeoff,
    PlaneLanding
}
const search = ref(props.modelValue || null);
const isOpen = ref(false);
const isFocused = ref(false);
const focusedIndex = ref(-1);
const searchResults = ref([]);
const matchedCountry = ref(null);
const correctedTo = ref(null);
const isLoading = ref(false);
const isLoadingAirport = computed(() => store.getters["airport/isLoading"]);

const inputEl = ref(null);
const uniqueId = ref(`autocomplete-${Math.random().toString(36).substring(2, 9)}`);
const DEFAULT_AIRPORT_LIMIT = 6;
const defaultAirportResults = () => props.source.slice(0, DEFAULT_AIRPORT_LIMIT);

// Use a reactive object for dropdownStyle instead of computed
const dropdownStyle = ref({ display: 'none' });
const selectedAirport = computed(() => {
    const value = String(search.value || "").toUpperCase();

    return props.source.find((item) => String(item?.iata_code || "").toUpperCase() === value) || null;
});
const regionNames = typeof Intl !== "undefined" && Intl.DisplayNames
    ? new Intl.DisplayNames(["en"], { type: "region" })
    : null;
const selectedAirportCountry = computed(() => {
    const code = selectedAirport.value?.iata_country_code;

    if (!code) return "";

    return regionNames?.of(String(code).toUpperCase()) || code;
});
const showRouteDisplay = computed(() => props.routeDisplay && selectedAirport.value && !isFocused.value);

const updateDropdownStyle = () => {
    if (!inputEl.value) {
        dropdownStyle.value = { display: 'none' };
        return;
    }
    const rect = inputEl.value.getBoundingClientRect();
    // The input can be narrower than a result row, so give the list room and keep it on screen.
    const gutter = 8;
    const width = Math.min(Math.max(rect.width, 380), window.innerWidth - gutter * 2);
    const left = Math.max(gutter, Math.min(rect.left, window.innerWidth - width - gutter));
    dropdownStyle.value = {
        position: 'fixed',
        // The dropdown uses fixed positioning, so use viewport coordinates.
        // Adding scroll offsets here makes it drift away from its input.
        top: `${rect.bottom + 8}px`,
        left: `${left}px`,
        width: `${width}px`,
        maxHeight: '300px',
        zIndex: 9999
    };
};

const updatePosition = () => {
    nextTick(() => {
        updateDropdownStyle();
    });
};

const updateSearchResults = debounce(() => {
    if (!search.value || search.value === "") {
        searchResults.value = defaultAirportResults();
        matchedCountry.value = null;
        correctedTo.value = null;
    } else {
        const { results, country, correctedTo: corrected } = searchAirports(props.source, search.value);
        searchResults.value = results;
        matchedCountry.value = country;
        correctedTo.value = corrected;
    }
    focusedIndex.value = searchResults.value.length ? 0 : -1;
    isLoading.value = false;
}, 300);

watch(search, () => {
    isLoading.value = true;
    updateSearchResults();
});

function handleInput(event) {
    isOpen.value = true;
    eventBus.value = {
        ...eventBus.value,
        dropdownOpen: true,
        dropdownId: uniqueId.value,
    };
    search.value = event.target.value;
    emit("update:modelValue", search.value);
    updateSearchResults();
    nextTick(() => {
        updatePosition(); // Update dropdown position when opening
    });
}

function handleFocus() {
    isFocused.value = true;
    // A click should show the default airports in the dropdown. Filtering only
    // starts after the user edits the IATA code or search text.
    searchResults.value = defaultAirportResults();
    matchedCountry.value = null;
    correctedTo.value = null;
    isLoading.value = false;
    isOpen.value = true;
    eventBus.value = {
        ...eventBus.value,
        dropdownOpen: true,
        dropdownId: uniqueId.value,
    };
    nextTick(() => {
        updatePosition();
    });
}

function handleInputClick() {
    // Keep the selected IATA code in place when editing. The user can change
    // it directly or use the clear button, instead of losing the route on click.
    inputEl.value?.focus();
}

function handleBlur() {
    window.setTimeout(() => {
        isFocused.value = false;
    }, 120);
}

function setSelected(item) {
    isOpen.value = false;
    isFocused.value = false;
    eventBus.value = {
        ...eventBus.value,
        dropdownOpen: false,
        dropdownId: null,
    };
    search.value = item.iata_code;
    emit("update:modelValue", search.value);
}

function clearSearch() {
    search.value = "";
    emit("update:modelValue", "");
    isOpen.value = false;
    isFocused.value = false;
}

const handleKeydown = (e) => {
    if (!searchResults.value.length) return;

    if (e.key === "ArrowDown") {
        focusedIndex.value =
            (focusedIndex.value + 1) % searchResults.value.length;
    } else if (e.key === "ArrowUp") {
        focusedIndex.value =
            (focusedIndex.value - 1 + searchResults.value.length) %
            searchResults.value.length;
    } else if (e.key === "Enter") {
        if (
            focusedIndex.value >= 0 &&
            focusedIndex.value < searchResults.value.length
        ) {
            setSelected(searchResults.value[focusedIndex.value]);
        }
    }
};

// Close the dropdown when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest(".dropdown") && !event.target.closest(`[data-dropdown-id="${uniqueId.value}"]`)) {
        isOpen.value = false;
    }
};

// Listen for global event bus updates to close other dropdowns
watch(eventBus, (newVal) => {
    if (newVal.dropdownOpen && newVal.dropdownId !== uniqueId.value) {
        isOpen.value = false;
    }
});

// Watch for tab changes
watch(() => props.tabId, () => {
    // When tab changes, we need to update the position after the DOM has updated
    nextTick(() => {
        updatePosition();
    });
});

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);

    // Initialize with default value if provided
    if (props.modelValue) {
        search.value = props.modelValue;
    }

    // Update position after component is mounted
    nextTick(() => {
        updateDropdownStyle();
    });
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});

// Watch for changes to modelValue
watch(() => props.modelValue, (newValue) => {
    if (newValue !== search.value) {
        search.value = newValue;
    }
});

watch(() => props.source, () => {
    if (search.value) {
        updateSearchResults(); // Filter again using the current search term
    }
});
</script>

<style>
/* Ensure the dropdown container is above other elements */
.dropdown {
    isolation: isolate;
}

/* Add some transition effects */
.fixed {
    transition: opacity 0.15s ease-in-out;
}

/* Custom scrollbar styling */
.scrollbar-container {
    overflow: hidden;
}

.scrollbar::-webkit-scrollbar {
    width: 8px;
}

.scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 0 4px 4px 0;
}

.scrollbar::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* For Firefox */
.scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}

.route-display-input:not(:focus) {
    color: transparent;
}
</style>
