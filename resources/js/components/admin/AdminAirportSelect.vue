<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Trigger Input Button -->
        <div
            role="combobox"
            :aria-expanded="isOpen"
            tabindex="0"
            @click="toggleOpen"
            @keydown.down.prevent="openAndFocus"
            @keydown.enter.prevent="toggleOpen"
            @keydown.esc="closeDropdown"
            class="h-10 w-full rounded border border-slate-200 bg-white px-3 flex items-center justify-between cursor-pointer hover:border-slate-300 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition-all select-none"
            :class="{
                'border-primary ring-1 ring-primary/20': isOpen,
                'border-rose-400 focus:border-rose-500 focus:ring-rose-200': hasError,
                'opacity-60 pointer-events-none': disabled
            }"
        >
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <component
                    :is="resolvedIcon"
                    class="w-4 h-4 shrink-0 transition-colors"
                    :class="selectedAirport ? 'text-primary' : 'text-slate-400'"
                />

                <template v-if="selectedAirport">
                    <span class="px-1.5 py-0.5 rounded text-[11px] font-mono font-bold bg-primary/10 text-primary border border-primary/20 shrink-0">
                        {{ selectedAirport.iata_code }}
                    </span>
                    <span class="text-xs font-semibold text-slate-900 truncate">
                        {{ selectedAirport.city_name || selectedAirport.name }}
                    </span>
                    <span class="text-[11px] text-slate-400 truncate hidden sm:inline">
                        ({{ selectedAirport.name }})
                    </span>
                </template>

                <template v-else>
                    <span class="text-xs text-slate-400 truncate">
                        {{ placeholder || 'Select airport...' }}
                    </span>
                </template>
            </div>

            <div class="flex items-center gap-1 shrink-0 ml-1.5">
                <button
                    v-if="selectedAirport && !disabled"
                    type="button"
                    @click.stop="clearSelection"
                    class="p-1 text-slate-400 hover:text-rose-500 rounded hover:bg-slate-100 transition"
                    title="Clear selection"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
                <ChevronDown
                    class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                    :class="{ 'rotate-180 text-primary': isOpen }"
                />
            </div>
        </div>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute top-full left-0 right-0 mt-1 z-50 rounded border border-slate-200 bg-white shadow-xl overflow-hidden animate-in fade-in-50 zoom-in-95 duration-100"
        >
            <!-- Search Input Box -->
            <div class="p-2 border-b border-slate-100 bg-slate-50/70">
                <div class="relative flex items-center">
                    <Search class="absolute left-2.5 w-3.5 h-3.5 text-slate-400 pointer-events-none" />
                    <input
                        ref="searchInputRef"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search city, airport, or IATA code..."
                        class="h-8 w-full pl-8 pr-7 text-xs rounded border border-slate-200 bg-white placeholder:text-slate-400 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20 transition"
                        @keydown.down.prevent="onArrowDown"
                        @keydown.up.prevent="onArrowUp"
                        @keydown.enter.prevent="selectHighlighted"
                        @keydown.esc="closeDropdown"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="searchQuery = ''"
                        class="absolute right-2 p-0.5 text-slate-400 hover:text-slate-600 rounded"
                    >
                        <X class="w-3 h-3" />
                    </button>
                </div>
            </div>

            <!-- Matched Country Banner -->
            <div
                v-if="matchedCountry"
                class="flex items-center gap-2 border-b border-slate-100 bg-slate-50 px-3 py-1.5 text-[11px] text-slate-600"
            >
                <img
                    v-if="matchedCountry.flag"
                    :src="matchedCountry.flag"
                    :alt="matchedCountry.name"
                    class="h-3 w-4 shrink-0 rounded-sm object-cover ring-1 ring-black/5"
                    loading="lazy"
                />
                <span>
                    Airports in <strong class="text-slate-900">{{ matchedCountry.name }}</strong>
                </span>
            </div>

            <!-- Airport Results List -->
            <ul
                ref="listRef"
                class="max-h-60 overflow-y-auto divide-y divide-slate-100 text-xs"
                tabindex="-1"
            >
                <li
                    v-for="(airport, idx) in searchResults"
                    :key="airport.id || airport.iata_code || idx"
                    @click="chooseAirport(airport)"
                    @mouseenter="highlightedIndex = idx"
                    class="group px-3 py-2 flex items-center justify-between gap-2.5 cursor-pointer transition-colors"
                    :class="[
                        highlightedIndex === idx ? 'bg-primary/5' : 'hover:bg-slate-50',
                        isSelected(airport) ? 'bg-primary/10' : ''
                    ]"
                >
                    <div class="flex items-center gap-2.5 min-w-0 flex-1">
                        <span
                            class="px-1.5 py-0.5 rounded text-[11px] font-mono font-bold transition-colors shrink-0"
                            :class="isSelected(airport)
                                ? 'bg-primary text-white'
                                : 'bg-slate-100 text-slate-700 group-hover:bg-primary group-hover:text-white'"
                        >
                            {{ airport.iata_code }}
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="font-semibold text-slate-900 truncate">
                                    {{ airport.city_name || airport.name }}
                                </span>
                                <span v-if="airport.iata_country_code" class="text-[10px] uppercase text-slate-400">
                                    {{ airport.iata_country_code }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 truncate leading-snug">
                                {{ airport.name }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <div v-if="getCountryMeta(airport)" class="flex items-center gap-1.5 text-[11px] text-slate-400">
                            <span class="hidden md:inline truncate max-w-[100px]">
                                {{ getCountryMeta(airport).name }}
                            </span>
                            <img
                                v-if="getCountryMeta(airport).flag"
                                :src="getCountryMeta(airport).flag"
                                :alt="getCountryMeta(airport).name"
                                class="h-3 w-4 rounded-sm object-cover ring-1 ring-black/5"
                                loading="lazy"
                            />
                        </div>
                        <Check
                            v-if="isSelected(airport)"
                            class="w-3.5 h-3.5 text-primary ml-1 shrink-0"
                        />
                    </div>
                </li>

                <!-- Empty State -->
                <li
                    v-if="searchResults.length === 0"
                    class="py-6 px-4 text-center text-xs text-slate-400"
                >
                    No airports found for "<span class="font-semibold text-slate-700">{{ searchQuery }}</span>"
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import {
    PlaneTakeoff,
    PlaneLanding,
    MapPin,
    Search,
    X,
    ChevronDown,
    Check
} from 'lucide-vue-next';
import { searchAirports, getAirportCountry } from '@/lib/airportSearch';

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    source: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Select airport...'
    },
    icon: {
        type: String,
        default: 'PlaneTakeoff'
    },
    hasError: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue', 'select']);

const containerRef = ref(null);
const searchInputRef = ref(null);
const listRef = ref(null);

const isOpen = ref(false);
const searchQuery = ref('');
const highlightedIndex = ref(0);

// Close on outside click
onClickOutside(containerRef, () => {
    closeDropdown();
});

// Resolve left icon
const resolvedIcon = computed(() => {
    if (props.icon === 'PlaneLanding') return PlaneLanding;
    if (props.icon === 'MapPin') return MapPin;
    return PlaneTakeoff;
});

// Find selected airport object
const selectedAirport = computed(() => {
    if (!props.modelValue) return null;
    const code = String(props.modelValue).trim().toUpperCase();
    return props.source.find((a) => String(a?.iata_code || '').toUpperCase() === code) || null;
});

// Major hubs to show first when query is empty
const POPULAR_HUBS = ['ISB', 'LHE', 'KHI', 'PEW', 'MUX', 'DXB', 'AUH', 'JED', 'RUH', 'DOH', 'LHR', 'IST', 'BKK', 'KUL', 'YYZ', 'JFK'];

// Compute search results
const matchedCountry = ref(null);
const searchResults = computed(() => {
    const list = Array.isArray(props.source) ? props.source : [];
    const query = searchQuery.value.trim();

    if (!query) {
        matchedCountry.value = null;
        // Prioritize popular hubs if available, else first 10
        const popular = [];
        const seen = new Set();
        for (const hub of POPULAR_HUBS) {
            const found = list.find((a) => String(a?.iata_code || '').toUpperCase() === hub);
            if (found && !seen.has(found.iata_code)) {
                popular.push(found);
                seen.add(found.iata_code);
            }
        }
        if (popular.length > 0) {
            return popular;
        }
        return list.slice(0, 10);
    }

    const { results, country } = searchAirports(list, query);
    matchedCountry.value = country;
    return results.slice(0, 25);
});

// Helpers
const getCountryMeta = (airport) => {
    try {
        return getAirportCountry(airport);
    } catch {
        return null;
    }
};

const isSelected = (airport) => {
    if (!props.modelValue || !airport?.iata_code) return false;
    return String(props.modelValue).toUpperCase() === String(airport.iata_code).toUpperCase();
};

const toggleOpen = () => {
    if (props.disabled) return;
    if (isOpen.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
};

const openDropdown = () => {
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
    nextTick(() => {
        searchInputRef.value?.focus();
    });
};

const openAndFocus = () => {
    if (!isOpen.value) {
        openDropdown();
    }
};

const closeDropdown = () => {
    isOpen.value = false;
    searchQuery.value = '';
    highlightedIndex.value = 0;
};

const chooseAirport = (airport) => {
    const code = airport?.iata_code || '';
    emit('update:modelValue', code);
    emit('select', airport);
    closeDropdown();
};

const clearSelection = () => {
    emit('update:modelValue', '');
    emit('select', null);
    searchQuery.value = '';
};

// Keyboard navigation
const onArrowDown = () => {
    if (searchResults.value.length === 0) return;
    highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length;
    scrollHighlightedIntoView();
};

const onArrowUp = () => {
    if (searchResults.value.length === 0) return;
    highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length;
    scrollHighlightedIntoView();
};

const selectHighlighted = () => {
    if (searchResults.value.length > 0 && highlightedIndex.value >= 0 && highlightedIndex.value < searchResults.value.length) {
        chooseAirport(searchResults.value[highlightedIndex.value]);
    }
};

const scrollHighlightedIntoView = () => {
    nextTick(() => {
        if (!listRef.value) return;
        const items = listRef.value.querySelectorAll('li');
        if (items[highlightedIndex.value]) {
            items[highlightedIndex.value].scrollIntoView({ block: 'nearest' });
        }
    });
};

watch(searchQuery, () => {
    highlightedIndex.value = 0;
});
</script>

