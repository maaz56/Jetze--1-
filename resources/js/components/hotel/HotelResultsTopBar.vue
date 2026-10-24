<script setup>
import { SlidersHorizontal, X } from "lucide-vue-next";

const props = defineProps({
  totalResults: { type: Number, default: 0 },
  filteredResults: { type: Number, default: 0 },
  destinationLabel: { type: String, default: "" },
  checkIn: { type: String, default: "" },
  checkOut: { type: String, default: "" },
  nights: { type: Number, default: 1 },
  guestsSummary: { type: String, default: "" },
  activeFiltersCount: { type: Number, default: 0 },
  // Active filter state
  priceLimit: { type: Number, default: null },
  maxPrice: { type: Number, default: 0 },
  currencyCode: { type: String, default: "PKR" },
  selectedRatings: { type: Array, default: () => [] },
  selectedReviewScore: { type: Number, default: 0 },
  selectedAmenities: { type: Array, default: () => [] },
  freeCancellationOnly: { type: Boolean, default: false },
  sort: { type: String, default: "recommended" },
});

const emits = defineEmits([
  "update:sort",
  "openMobileFilter",
  "removePriceLimit",
  "removeRating",
  "removeReviewScore",
  "removeAmenity",
  "removeFreeCancellation",
  "clearAll",
]);

const handleSortChange = (e) => {
  emits("update:sort", e.target.value);
};
</script>

<template>
  <div class="mb-5 space-y-3">
    <!-- TOP ROW: TITLE & RESULT COUNT + SORT + MOBILE FILTER BUTTON -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-3.5">
      <div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 flex items-center gap-2">
          <span>{{ destinationLabel || 'Available properties' }}</span>
          <span class="rounded-sm border border-slate-200 bg-white px-2 py-0.5 text-xs font-bold text-slate-700 shadow-2xs">
            {{ filteredResults }} {{ filteredResults === 1 ? 'property' : 'properties' }}
          </span>
        </h2>
        <p class="text-xs text-slate-600 font-medium mt-1">
          <span v-if="checkIn && checkOut">{{ checkIn }} – {{ checkOut }}</span>
          <span v-if="nights > 0"> · {{ nights }} {{ nights === 1 ? 'night' : 'nights' }}</span>
          <span v-if="guestsSummary"> · {{ guestsSummary }}</span>
        </p>
      </div>

      <div class="flex items-center gap-2.5 self-end sm:self-auto">
        <!-- Mobile Filter Button -->
        <button
          type="button"
          class="flex lg:hidden h-9 items-center gap-1.5 rounded border border-slate-200 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs hover:bg-slate-50"
          @click="emits('openMobileFilter')"
        >
          <SlidersHorizontal class="h-3.5 w-3.5 text-primary" />
          <span>Filters</span>
          <span
            v-if="activeFiltersCount > 0"
            class="flex h-4 w-4 items-center justify-center rounded bg-primary text-[10px] font-bold text-white"
          >
            {{ activeFiltersCount }}
          </span>
        </button>

        <!-- Sort Select -->
        <div class="flex items-center gap-2">
          <label for="hotel-sort" class="hidden sm:inline text-xs font-bold uppercase tracking-wider text-muted-foreground">
            Sort:
          </label>
          <select
            id="hotel-sort"
            :value="props.sort"
            class="h-10 rounded border border-slate-200 bg-white px-3 text-xs font-bold text-slate-900 shadow-xs outline-none transition-colors focus:border-primary cursor-pointer hover:border-slate-300"
            @change="handleSortChange"
          >
            <option value="recommended">Recommended</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="rating_desc">Stars: High to Low</option>
            <option value="score_desc">Review Score</option>
          </select>
        </div>
      </div>
    </div>

    <!-- BOTTOM ROW: ACTIVE FILTER CHIPS -->
    <div
      v-if="activeFiltersCount > 0"
      class="flex flex-wrap items-center gap-1.5 pt-1 text-xs"
    >
      <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mr-1">
        Active Filters:
      </span>

      <!-- Price Limit Chip -->
      <span
        v-if="priceLimit !== null && priceLimit < maxPrice"
        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 font-semibold shadow-2xs"
      >
        <span>Max {{ currencyCode }} {{ Math.round(priceLimit).toLocaleString() }}</span>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          aria-label="Remove price filter"
          @click="emits('removePriceLimit')"
        >
          <X class="h-3 w-3" />
        </button>
      </span>

      <!-- Star Rating Chips -->
      <span
        v-for="r in selectedRatings"
        :key="r"
        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 font-semibold shadow-2xs"
      >
        <span>{{ r }} Star{{ r > 1 ? 's' : '' }}</span>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          aria-label="Remove star rating filter"
          @click="emits('removeRating', r)"
        >
          <X class="h-3 w-3" />
        </button>
      </span>

      <!-- Review Score Chip -->
      <span
        v-if="selectedReviewScore > 0"
        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 font-semibold shadow-2xs"
      >
        <span>{{ selectedReviewScore }}+ Review Score</span>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          aria-label="Remove review score filter"
          @click="emits('removeReviewScore')"
        >
          <X class="h-3 w-3" />
        </button>
      </span>

      <!-- Free Cancellation Chip -->
      <span
        v-if="freeCancellationOnly"
        class="inline-flex items-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs text-emerald-800 font-bold shadow-2xs"
      >
        <span>Free cancellation</span>
        <button
          type="button"
          class="text-emerald-700 hover:opacity-75"
          aria-label="Remove free cancellation filter"
          @click="emits('removeFreeCancellation')"
        >
          <X class="h-3 w-3" />
        </button>
      </span>

      <!-- Amenity Chips -->
      <span
        v-for="a in selectedAmenities"
        :key="a"
        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-2.5 py-1 text-xs text-slate-800 font-semibold shadow-2xs"
      >
        <span>{{ a }}</span>
        <button
          type="button"
          class="text-slate-400 hover:text-slate-700"
          aria-label="Remove amenity filter"
          @click="emits('removeAmenity', a)"
        >
          <X class="h-3 w-3" />
        </button>
      </span>

      <!-- Clear All Link -->
      <button
        type="button"
        class="ml-1 text-xs font-bold text-primary hover:underline"
        @click="emits('clearAll')"
      >
        Clear all
      </button>
    </div>
  </div>
</template>
