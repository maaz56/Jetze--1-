<script setup>
import { computed } from "vue";
import { Check, RotateCcw, SlidersHorizontal, Star, X } from "lucide-vue-next";

const props = defineProps({
  hotelResults: { type: Array, default: () => [] },
  priceLimit: { type: Number, default: 0 },
  maxPrice: { type: Number, default: 0 },
  currencyCode: { type: String, default: "PKR" },
  selectedRatings: { type: Array, default: () => [] },
  selectedReviewScore: { type: Number, default: 0 },
  selectedAmenities: { type: Array, default: () => [] },
  freeCancellationOnly: { type: Boolean, default: false },
  isMobileOpen: { type: Boolean, default: false },
  totalCount: { type: Number, default: 0 },
});

const emits = defineEmits([
  "update:priceLimit",
  "update:selectedRatings",
  "update:selectedReviewScore",
  "update:selectedAmenities",
  "update:freeCancellationOnly",
  "clearAll",
  "closeMobile",
]);

// Available star ratings options
const starOptions = [
  { value: 5, label: "5 Stars", stars: 5 },
  { value: 4, label: "4 Stars", stars: 4 },
  { value: 3, label: "3 Stars", stars: 3 },
  { value: 2, label: "2 Stars or less", stars: 2 },
];

// Available review score options
const reviewScoreOptions = [
  { value: 0, label: "Any score" },
  { value: 9, label: "9+ Wonderful", badge: "9.0+" },
  { value: 8, label: "8+ Very Good", badge: "8.0+" },
  { value: 7, label: "7+ Good", badge: "7.0+" },
];

// Available common hotel amenities
const amenityOptions = [
  "Free WiFi",
  "Breakfast included",
  "Swimming pool",
  "Free parking",
  "Air conditioning",
  "Fitness center",
];

const toggleRating = (val) => {
  const next = [...props.selectedRatings];
  const idx = next.indexOf(val);
  if (idx > -1) {
    next.splice(idx, 1);
  } else {
    next.push(val);
  }
  emits("update:selectedRatings", next);
};

const toggleAmenity = (name) => {
  const next = [...props.selectedAmenities];
  const idx = next.indexOf(name);
  if (idx > -1) {
    next.splice(idx, 1);
  } else {
    next.push(name);
  }
  emits("update:selectedAmenities", next);
};

const setReviewScore = (val) => {
  emits("update:selectedReviewScore", val);
};

const toggleFreeCancellation = (e) => {
  emits("update:freeCancellationOnly", e.target.checked);
};

const handlePriceInput = (e) => {
  emits("update:priceLimit", Number(e.target.value));
};

const hasActiveFilters = computed(() => {
  return (
    (props.priceLimit !== null && props.priceLimit < props.maxPrice) ||
    props.selectedRatings.length > 0 ||
    props.selectedReviewScore > 0 ||
    props.selectedAmenities.length > 0 ||
    props.freeCancellationOnly
  );
});
</script>

<template>
  <div>
    <!-- DESKTOP SIDEBAR -->
    <aside class="hidden lg:block w-72 shrink-0">
      <div class="sticky top-24 rounded border border-slate-200/90 bg-white p-5 text-slate-900 shadow-sm">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
          <div class="flex items-center gap-2">
            <SlidersHorizontal class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-900">
              Filter by
            </h3>
          </div>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="flex items-center gap-1 text-xs font-bold text-primary hover:underline"
            @click="emits('clearAll')"
          >
            <RotateCcw class="h-3 w-3" />
            Clear all
          </button>
        </div>

        <div class="divide-y divide-slate-100 text-xs">
          
          <!-- 1. FREE CANCELLATION TOGGLE -->
          <div class="py-4">
            <label class="flex cursor-pointer items-start justify-between gap-3">
              <div>
                <p class="font-extrabold text-slate-900 text-xs">Free Cancellation</p>
                <p class="mt-0.5 text-[11px] text-muted-foreground">Only show refundable rooms</p>
              </div>
              <input
                type="checkbox"
                :checked="props.freeCancellationOnly"
                class="mt-0.5 h-4 w-4 rounded-sm border-slate-300 text-primary accent-primary cursor-pointer"
                @change="toggleFreeCancellation"
              />
            </label>
          </div>

          <!-- 2. PRICE RANGE -->
          <div class="py-4">
            <div class="flex items-center justify-between">
              <span class="font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
                Budget (per stay)
              </span>
              <span class="font-bold text-foreground">
                Up to {{ currencyCode }} {{ Math.round(priceLimit).toLocaleString() }}
              </span>
            </div>
            <div class="mt-2.5">
              <input
                type="range"
                min="0"
                :max="maxPrice || 1"
                step="1"
                :value="priceLimit"
                class="w-full accent-primary cursor-pointer"
                @input="handlePriceInput"
              />
              <div class="mt-1 flex items-center justify-between text-[11px] text-muted-foreground">
                <span>0</span>
                <span>{{ currencyCode }} {{ Math.round(maxPrice).toLocaleString() }}</span>
              </div>
            </div>
          </div>

          <!-- 3. STAR RATING -->
          <div class="py-4">
            <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
              Property Star Rating
            </p>
            <div class="space-y-2">
              <label
                v-for="opt in starOptions"
                :key="opt.value"
                class="flex cursor-pointer items-center justify-between gap-2 rounded px-1.5 py-1 transition-colors hover:bg-muted"
              >
                <div class="flex items-center gap-2">
                  <input
                    type="checkbox"
                    :checked="props.selectedRatings.includes(opt.value)"
                    class="h-4 w-4 rounded-sm border-border text-primary accent-primary cursor-pointer"
                    @change="toggleRating(opt.value)"
                  />
                  <div class="flex items-center gap-0.5">
                    <Star
                      v-for="s in opt.stars"
                      :key="s"
                      class="h-3.5 w-3.5 fill-chart-4 text-chart-4"
                    />
                  </div>
                </div>
                <span class="text-[11px] text-muted-foreground font-medium">
                  {{ opt.label }}
                </span>
              </label>
            </div>
          </div>

          <!-- 4. REVIEW SCORE -->
          <div class="py-4">
            <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
              Guest Review Score
            </p>
            <div class="space-y-1.5">
              <button
                v-for="opt in reviewScoreOptions"
                :key="opt.value"
                type="button"
                class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left transition-colors"
                :class="[
                  props.selectedReviewScore === opt.value
                    ? 'bg-primary/10 font-bold text-primary border border-primary/20'
                    : 'text-foreground hover:bg-muted font-medium'
                ]"
                @click="setReviewScore(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span
                  v-if="opt.badge"
                  class="rounded-sm bg-primary/20 px-1.5 py-0.5 text-[10px] font-bold text-primary"
                >
                  {{ opt.badge }}
                </span>
              </button>
            </div>
          </div>

          <!-- 5. AMENITIES -->
          <div class="py-4">
            <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
              Popular Amenities
            </p>
            <div class="space-y-2">
              <label
                v-for="amenity in amenityOptions"
                :key="amenity"
                class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1 transition-colors hover:bg-muted"
              >
                <input
                  type="checkbox"
                  :checked="props.selectedAmenities.includes(amenity)"
                  class="h-4 w-4 rounded-sm border-border text-primary accent-primary cursor-pointer"
                  @change="toggleAmenity(amenity)"
                />
                <span class="text-xs text-foreground font-medium">
                  {{ amenity }}
                </span>
              </label>
            </div>
          </div>

        </div>
      </div>
    </aside>

    <!-- MOBILE DRAWER MODAL -->
    <Teleport to="body">
      <div
        v-if="props.isMobileOpen"
        class="fixed inset-0 z-50 flex lg:hidden"
      >
        <!-- Overlay -->
        <div
          class="fixed inset-0 bg-black/60 backdrop-blur-[2px] transition-opacity"
          @click="emits('closeMobile')"
        />

        <!-- Slide-out Drawer Panel -->
        <div class="relative z-10 flex h-full w-full max-w-xs flex-col border-r border-border bg-card text-card-foreground shadow-2xl">
          <!-- Drawer Header -->
          <div class="flex items-center justify-between border-b border-border p-4">
            <div class="flex items-center gap-2">
              <SlidersHorizontal class="h-4 w-4 text-primary" />
              <h3 class="text-sm font-bold uppercase tracking-wide text-foreground">
                Filters
              </h3>
            </div>
            <div class="flex items-center gap-3">
              <button
                v-if="hasActiveFilters"
                type="button"
                class="text-xs font-semibold text-primary hover:underline"
                @click="emits('clearAll')"
              >
                Clear all
              </button>
              <button
                type="button"
                class="rounded-sm p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Close filters"
                @click="emits('closeMobile')"
              >
                <X class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Drawer Content -->
          <div class="flex-1 overflow-y-auto p-4 divide-y divide-border text-xs">
            <!-- Free Cancellation -->
            <div class="pb-4">
              <label class="flex cursor-pointer items-start justify-between gap-3">
                <div>
                  <p class="font-bold text-foreground">Free Cancellation</p>
                  <p class="mt-0.5 text-[11px] text-muted-foreground">Only show refundable rooms</p>
                </div>
                <input
                  type="checkbox"
                  :checked="props.freeCancellationOnly"
                  class="mt-0.5 h-4 w-4 rounded-sm border-border text-primary accent-primary cursor-pointer"
                  @change="toggleFreeCancellation"
                />
              </label>
            </div>

            <!-- Price Range -->
            <div class="py-4">
              <div class="flex items-center justify-between">
                <span class="font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
                  Budget (per stay)
                </span>
                <span class="font-bold text-foreground">
                  Up to {{ currencyCode }} {{ Math.round(priceLimit).toLocaleString() }}
                </span>
              </div>
              <div class="mt-2.5">
                <input
                  type="range"
                  min="0"
                  :max="maxPrice || 1"
                  step="1"
                  :value="priceLimit"
                  class="w-full accent-primary cursor-pointer"
                  @input="handlePriceInput"
                />
              </div>
            </div>

            <!-- Star Rating -->
            <div class="py-4">
              <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
                Property Star Rating
              </p>
              <div class="space-y-2">
                <label
                  v-for="opt in starOptions"
                  :key="opt.value"
                  class="flex cursor-pointer items-center justify-between gap-2 rounded px-1.5 py-1"
                >
                  <div class="flex items-center gap-2">
                    <input
                      type="checkbox"
                      :checked="props.selectedRatings.includes(opt.value)"
                      class="h-4 w-4 rounded-sm border-border text-primary accent-primary cursor-pointer"
                      @change="toggleRating(opt.value)"
                    />
                    <div class="flex items-center gap-0.5">
                      <Star
                        v-for="s in opt.stars"
                        :key="s"
                        class="h-3.5 w-3.5 fill-chart-4 text-chart-4"
                      />
                    </div>
                  </div>
                  <span class="text-[11px] text-muted-foreground font-medium">
                    {{ opt.label }}
                  </span>
                </label>
              </div>
            </div>

            <!-- Review Score -->
            <div class="py-4">
              <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
                Guest Review Score
              </p>
              <div class="space-y-1.5">
                <button
                  v-for="opt in reviewScoreOptions"
                  :key="opt.value"
                  type="button"
                  class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left"
                  :class="[
                    props.selectedReviewScore === opt.value
                      ? 'bg-primary/10 font-bold text-primary border border-primary/20'
                      : 'text-foreground hover:bg-muted font-medium'
                  ]"
                  @click="setReviewScore(opt.value)"
                >
                  <span>{{ opt.label }}</span>
                  <span
                    v-if="opt.badge"
                    class="rounded-sm bg-primary/20 px-1.5 py-0.5 text-[10px] font-bold text-primary"
                  >
                    {{ opt.badge }}
                  </span>
                </button>
              </div>
            </div>

            <!-- Amenities -->
            <div class="py-4">
              <p class="mb-2.5 font-bold uppercase tracking-wider text-muted-foreground text-[11px]">
                Popular Amenities
              </p>
              <div class="space-y-2">
                <label
                  v-for="amenity in amenityOptions"
                  :key="amenity"
                  class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1"
                >
                  <input
                    type="checkbox"
                    :checked="props.selectedAmenities.includes(amenity)"
                    class="h-4 w-4 rounded-sm border-border text-primary accent-primary cursor-pointer"
                    @change="toggleAmenity(amenity)"
                  />
                  <span class="text-xs text-foreground font-medium">
                    {{ amenity }}
                  </span>
                </label>
              </div>
            </div>
          </div>

          <!-- Drawer Footer -->
          <div class="border-t border-border p-4 bg-card">
            <button
              type="button"
              class="w-full h-11 rounded bg-primary text-primary-foreground font-bold text-sm hover:bg-primary/90 flex items-center justify-center"
              @click="emits('closeMobile')"
            >
              Show {{ props.totalCount }} properties
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
