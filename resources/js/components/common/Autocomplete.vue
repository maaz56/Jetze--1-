<template>
    <div class="relative dropdown group">
        <label v-if="label" class="mb-1 flex items-start justify-start text-sm font-semibold" :style="{ color: text_color }">
            {{ label }}
        </label>
        <div class="relative">
            <div :class="[
                'relative min-h-[110px] w-full cursor-pointer rounded-xl border bg-white p-4 transition-all duration-200',
                isFocused ? 'border-blue-500 ring-1 ring-blue-500 shadow-lg' : 'border-gray-200 hover:bg-gray-50',
            ]">
                <span class="mb-1 block text-sm font-medium uppercase tracking-wide text-gray-500">
                    {{ placeholder || 'From' }}
                </span>

                <input type="text" :placeholder="isFocused ? 'Type to search...' : ''" v-model="search"
                    autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                    :name="`${uniqueId}-search`" data-lpignore="true"
                    :class="[
                        'absolute inset-0 z-10 h-full w-full cursor-pointer border-none bg-transparent px-4 pt-7 text-3xl font-black text-gray-900 outline-none ring-0',
                        !isFocused ? 'text-transparent caret-transparent' : '',
                    ]"
                    @input="handleInput" @keydown="handleKeydown" @focus="handleFocus" @click="handleInputClick"
                    @blur="handleBlur" ref="inputEl" />

                <div v-if="!isFocused" class="relative pointer-events-none">
                    <template v-if="displayValue">
                        <h2 class="truncate text-3xl font-black leading-tight text-gray-900">{{ displayValue.city }}</h2>
                        <p class="mt-1 truncate text-sm font-medium text-gray-600">
                            {{ displayValue.iata }}, {{ selectedItem.name }}{{ displayValue.country ? ' ' + displayValue.country : '' }}
                        </p>
                    </template>
                    <template v-else>
                        <h2 class="text-3xl font-black leading-tight text-gray-400">Select City</h2>
                        <p class="mt-1 text-sm text-gray-400">Airport Name, Country</p>
                    </template>
                </div>

                <button v-if="search && isFocused" @click.stop="clearSearch" type="button"
                    class="absolute right-4 top-4 z-30 rounded-full p-1 text-gray-400 hover:bg-blue-50 hover:text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>

        <Teleport to="body">
            <ul v-if="isOpen" :style="dropdownStyle"
                class="z-[9999] mt-1 min-w-[400px] overflow-hidden rounded-xl border-none bg-white shadow-2xl">
                <div class="custom-scrollbar max-h-80 overflow-y-auto">
                    <li v-for="(item, index) in searchResults" :key="item.id || index"
                        @click.stop="setSelected(item)" :class="[
                            'flex cursor-pointer items-center justify-between border-b border-gray-50 px-4 py-3 transition-colors',
                            index === focusedIndex ? 'bg-blue-50' : 'hover:bg-gray-50',
                        ]">
                        <div class="flex items-center gap-4">
                            <div class="rounded-lg bg-gray-100 p-2">
                                <Plane class="h-5 w-5 text-gray-500" />
                            </div>
                            <div class="flex flex-col">
                                <span class="text-lg font-bold text-gray-900">
                                    {{ item.city_name }}
                                    <span v-if="item.iata_code" class="ml-1 font-medium text-gray-400">({{ item.iata_code }})</span>
                                </span>
                                <span class="max-w-[250px] truncate text-sm text-gray-500">{{ item.name }}</span>
                            </div>
                        </div>
                        <div v-if="getCountryName(item)" class="text-xs font-bold uppercase tracking-tighter text-gray-400">
                            {{ getCountryName(item) }}
                        </div>
                    </li>

                    <li v-if="!isSearching && searchResults.length === 0" class="p-8 text-center font-medium text-gray-400">
                        <div class="mb-2">📍</div>
                        No airports found for "{{ search }}"
                    </li>
                    <li v-if="isSearching" class="flex flex-col items-center justify-center gap-2 p-8">
                        <Spinner color="#008cff" />
                        <span class="animate-pulse text-xs text-gray-400">Searching...</span>
                    </li>
                </div>
            </ul>
        </Teleport>
    </div>
</template>

<script setup>
import eventBus from "@/services/eventBus";
import CountryList from "@/components/common/CountryList.json";
import { debounce } from "lodash";
import { PlaneLanding, PlaneTakeoff } from "lucide-vue-next";
import { Plane } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import Spinner from "../common/Spinner.vue";
import { Badge } from "../ui/badge";

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
    icon: {
        type: String,
        default: "PlaneTakeoff"
    },
    defaultSuggestionsLimit: {
        type: Number,
        default: 8,
    },
    defaultSuggestions: {
        type: Array,
        default: () => [],
    },
    defaultSuggestionsResolver: {
        type: Function,
        default: null,
    },
    text_color: {
        type: String,
        default: "white"
    },
    border_color: {
        type: String,
        default: "transparent",
    },
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
const selectedItem = ref(null);
const isOpen = ref(false);
const focusedIndex = ref(-1);
const searchResults = ref([]);
const isLoading = ref(false);
const isLoadingAirport = computed(() => store.getters["airport/isLoading"]);
const isFocused = ref(false);

const inputEl = ref(null);
const uniqueId = ref(`autocomplete-${Math.random().toString(36).substring(2, 9)}`);

// Use a reactive object for dropdownStyle instead of computed
const dropdownStyle = ref({ display: 'none' });

const countryByCode = new Map(
    CountryList.map((country) => [String(country.code).toLowerCase(), country.name]),
);

const getCountryName = (item) => {
    if (!item) return "";
    if (item.country_name) return item.country_name;
    if (item.country && item.country.name) return item.country.name;
    const code = item.iata_country_code || item.country_code || item.country;
    if (!code) return "";
    const normalized = String(code).toLowerCase();
    return countryByCode.get(normalized) || String(code);
};

const updateDropdownStyle = () => {
    if (!inputEl.value) {
        dropdownStyle.value = { display: 'none' };
        return;
    }
    const rect = inputEl.value.getBoundingClientRect();
    dropdownStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
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

const formatSelection = (item) => {
    if (!item) return "";
    const iata = item.iata_code || "";
    const city = item.city_name || "";
    const country = getCountryName(item) || "";
    return [iata, city, country].filter(Boolean).join("-");
};

const displayValue = computed(() => {
    if (!selectedItem.value) return null;
    return {
        iata: selectedItem.value.iata_code || "",
        city: selectedItem.value.city_name || "",
        country: getCountryName(selectedItem.value) || "",
    };
});

const syncSelectionFromModelValue = (value) => {
    if (!value) {
        search.value = "";
        selectedItem.value = null;
        return;
    }

    const normalizedValue = String(value).trim().toLowerCase();
    const found = props.source.find((i) => String(i.iata_code || "").trim().toLowerCase() === normalizedValue);
    if (found) {
        selectedItem.value = found;
        search.value = formatSelection(found);
        return;
    }

    search.value = value;
    selectedItem.value = null;
};

const setDefaultSuggestions = () => {
    const limit = Math.max(0, props.defaultSuggestionsLimit);
    let suggestions = [];

    if (typeof props.defaultSuggestionsResolver === "function") {
        const resolved = props.defaultSuggestionsResolver(props.source);
        suggestions = Array.isArray(resolved) ? resolved : [];
    } else if (props.defaultSuggestions.length > 0) {
        const hasStringValues = props.defaultSuggestions.every(
            (item) => typeof item === "string",
        );

        if (hasStringValues) {
            const codes = props.defaultSuggestions.map((item) =>
                item.toLowerCase(),
            );
            suggestions = props.source.filter((item) =>
                codes.includes((item.iata_code || "").toLowerCase()),
            );
        } else {
            suggestions = props.defaultSuggestions;
        }
    } else {
        suggestions = props.source;
    }

    searchResults.value = suggestions.slice(0, limit);
    isLoading.value = false;
};

const updateSearchResults = debounce(() => {
    if (!search.value || search.value === "") {
        setDefaultSuggestions();
    } else {
        // normalize the query; if the user typed or a selection filled the field with "Name (CODE)" strip the parentheses
        let query = search.value.toLowerCase();
        const codeMatch = query.match(/\(([^)]+)\)$/);
        if (codeMatch) {
            query = codeMatch[1].trim();
        }
        // If input is in formatted style like "LHE-Lahore-Pakistan", use IATA code part.
        if (query.includes("-")) {
            const [firstPart] = query.split("-");
            if (firstPart && firstPart.trim().length >= 2) {
                query = firstPart.trim();
            }
        }

        // Keep selected airport visible in popover when the input already holds formatted text.
        if (selectedItem.value) {
            const selectedCode = (selectedItem.value.iata_code || "").toLowerCase();
            if (selectedCode && selectedCode === query) {
                searchResults.value = [selectedItem.value];
                isLoading.value = false;
                return;
            }
        }

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
    // clear any previous selection when user types manually
    selectedItem.value = null;

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
    // Show dropdown on focus, including default suggestions when input is empty.
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

function handleInputClick() {
    // If an airport is already selected, clear it fully on click.
    if (selectedItem.value) {
        search.value = "";
        selectedItem.value = null;
        emit("update:modelValue", "");
    }

    isOpen.value = true;
    eventBus.value = {
        ...eventBus.value,
        dropdownOpen: true,
        dropdownId: uniqueId.value,
    };
    setDefaultSuggestions();
    nextTick(() => {
        updatePosition();
    });
}

function handleBlur() {
    isFocused.value = false;
}

function setSelected(item) {
    isOpen.value = false;
    eventBus.value = {
        ...eventBus.value,
        dropdownOpen: false,
        dropdownId: null,
    };

    selectedItem.value = item;
    const formatted = formatSelection(item);
    search.value = formatted;
    emit("update:modelValue", item.iata_code); // store only the IATA code externally
}

function clearSearch() {
    search.value = "";
    selectedItem.value = null;
    emit("update:modelValue", "");
    isOpen.value = true;
    setDefaultSuggestions();
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
        syncSelectionFromModelValue(props.modelValue);
        updatePosition();
    });
});

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);

    // Initialize with default value if provided
    syncSelectionFromModelValue(props.modelValue);

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
    if (newValue !== search.value || !selectedItem.value) {
        syncSelectionFromModelValue(newValue);
    }
});

watch(() => props.source, () => {
    if (search.value) {
        updateSearchResults(); // Filter again using the current search term
    } else if (isOpen.value) {
        setDefaultSuggestions();
    }

    // if the component already has a modelValue but we haven't yet resolved
    // the matching item (likely because source was empty earlier), try again
    if (props.modelValue && !selectedItem.value) {
        syncSelectionFromModelValue(props.modelValue);
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
.custom-scrollbar {
    overflow: hidden;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 0 4px 4px 0;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #a1a1a1;
}

/* For Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}
</style>
