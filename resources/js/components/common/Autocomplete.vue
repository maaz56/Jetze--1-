

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
                    <li class="px-4 py-3 cursor-pointer hover:bg-gray-50 border-b border-gray-100 last:border-b-0"
                        :class="{ 'bg-gray-50': index === focusedIndex }" v-for="(item, index) in searchResults"
                        :key="item.id || index" @click.stop="setSelected(item)">
                        <div class="font-medium">{{ item.name + " " + "(" + item.iata_code + ")" }}</div>
                        <span class="font-normal text-sm block text-gray-500">{{ item.city_name }}</span>
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
const isLoading = ref(false);
const isLoadingAirport = computed(() => store.getters["airport/isLoading"]);

const inputEl = ref(null);
const uniqueId = ref(`autocomplete-${Math.random().toString(36).substring(2, 9)}`);

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
    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + window.scrollY + 8}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${rect.width}px`,
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
        searchResults.value = [];
        isLoading.value = false;
    } else {
        const query = search.value.toLowerCase();
        const filteredResults = props.source.filter((item) => {
            const name = item.name ? item.name.toLowerCase() : "";
            const iataCode = item.iata_code ? item.iata_code.toLowerCase() : "";
            const cityName = item.city_name ? item.city_name.toLowerCase() : "";

            return (
                iataCode === query ||
                name.includes(query) ||
                cityName.includes(query)
            );
        });

        // Check if there's an exact match by iata_code
        const exactMatch = filteredResults.find((item) => {
            const iataCode = item.iata_code ? item.iata_code.toLowerCase() : "";
            return iataCode === query;
        });

        // If exact match found, set searchResults to it
        if (exactMatch) {
            searchResults.value = [exactMatch];
        } else {
            searchResults.value = filteredResults;
        }
        isLoading.value = false;
    }
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
    // When input gets focus, update position and show dropdown if there's content
    // //console.log(isLoadingAirport.value);
    if (search.value && search.value.length > 0) {
        isOpen.value = true;
        eventBus.value = {
            ...eventBus.value,
            dropdownOpen: true,
            dropdownId: uniqueId.value,
        };
        updateSearchResults();
        nextTick(() => {
            updatePosition();
        });
    }
}

function handleInputClick() {
    if (props.clearOnClick && search.value) {
        clearSearch();
        isFocused.value = true;
        inputEl.value?.focus();
    }
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
