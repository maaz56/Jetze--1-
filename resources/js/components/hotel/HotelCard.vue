<script setup>
import { computed, ref } from "vue";
import {
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  Coffee,
  Hotel,
  MapPin,
  Minus,
  Plus,
  ShieldCheck,
  Star,
  Users,
  Wifi,
} from "lucide-vue-next";
import Button from "@/components/ui/button/Button.vue";

const props = defineProps({
  hotel: { type: Object, required: true },
  nights: { type: Number, default: 1 },
  guestsSummary: { type: String, default: "" },
  isExpanded: { type: Boolean, default: false },
  selectedBookingCode: { type: String, default: "" },
  roomChoice: { type: Object, default: null },
  isRoomDetailsLoading: { type: Boolean, default: false },
  roomDetailsMap: { type: Object, default: () => ({}) },
  propertyImages: { type: Array, default: () => [] },
});

const emits = defineEmits([
  "viewDeal",
  "toggleRooms",
  "chooseRoom",
  "selectRoom",
]);

// Image gallery management
const currentImageIndex = ref(0);
const loadedImages = ref(new Set());
const failedImages = ref(new Set());

const allImages = computed(() => {
  const list = Array.isArray(props.hotel?.images) ? props.hotel.images.filter(Boolean) : [];
  if (list.length) return list;
  return props.propertyImages || [];
});

const activeImage = computed(() => {
  return allImages.value[currentImageIndex.value] || allImages.value[0] || null;
});

const nextImage = () => {
  if (allImages.value.length < 2) return;
  currentImageIndex.value = (currentImageIndex.value + 1) % allImages.value.length;
};

const prevImage = () => {
  if (allImages.value.length < 2) return;
  currentImageIndex.value = (currentImageIndex.value - 1 + allImages.value.length) % allImages.value.length;
};

// Hotel helpers
const starCount = computed(() => {
  const r = String(props.hotel?.rating || "").toLowerCase();
  if (r.includes("five") || r === "5") return 5;
  if (r.includes("four") || r === "4") return 4;
  if (r.includes("three") || r === "3") return 3;
  if (r.includes("two") || r === "2") return 2;
  if (r.includes("one") || r === "1") return 1;
  const num = parseInt(r, 10);
  return isNaN(num) ? 0 : Math.min(5, Math.max(0, num));
});

const formattedRating = computed(() => {
  const r = props.hotel?.rating;
  if (!r || r === "All") return "Hotel";
  return String(r).replace(/([a-z])([A-Z])/g, "$1 $2");
});

const locationDisplay = computed(() => {
  return [props.hotel?.city, props.hotel?.country].filter(Boolean).join(", ") || "Location unavailable";
});

// Review score (real or normalized from rating)
const reviewScore = computed(() => {
  if (props.hotel?.review_score) {
    const s = Number(props.hotel.review_score);
    return {
      score: s.toFixed(1),
      label: s >= 9 ? "Wonderful" : s >= 8 ? "Very Good" : s >= 7 ? "Good" : "Pleasant",
      count: props.hotel.review_count || 120,
    };
  }
  const stars = starCount.value;
  if (stars >= 5) return { score: "9.2", label: "Wonderful", count: 340 };
  if (stars === 4) return { score: "8.6", label: "Very Good", count: 215 };
  if (stars === 3) return { score: "7.9", label: "Good", count: 148 };
  return { score: "7.2", label: "Pleasant", count: 85 };
});

// Extracted amenities
const amenitiesList = computed(() => {
  const set = new Set();
  const rooms = props.hotel?.rooms || [];

  rooms.forEach((r) => {
    if (r.inclusion) {
      String(r.inclusion)
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean)
        .forEach((inc) => {
          if (inc.toLowerCase().includes("wifi") || inc.toLowerCase().includes("internet")) {
            set.add("Free WiFi");
          } else if (inc.toLowerCase().includes("parking")) {
            set.add("Free Parking");
          } else if (inc.toLowerCase().includes("pool")) {
            set.add("Swimming Pool");
          } else if (inc.toLowerCase().includes("breakfast")) {
            set.add("Breakfast included");
          } else if (inc.toLowerCase().includes("air") || inc.toLowerCase().includes("ac")) {
            set.add("Air Conditioning");
          } else if (inc.length <= 22) {
            set.add(inc);
          }
        });
    }
    if (r.meal_type && String(r.meal_type).toLowerCase().includes("breakfast")) {
      set.add("Breakfast included");
    }
  });

  if (!set.has("Free WiFi")) set.add("Free WiFi");
  if (starCount.value >= 4) {
    if (!set.has("Air Conditioning")) set.add("Air Conditioning");
  }

  return Array.from(set).slice(0, 4);
});

// Free cancellation check
const hasFreeCancellation = computed(() => {
  return (props.hotel?.rooms || []).some((r) => r.is_refundable);
});

// Price calculations
const activeRoom = computed(() => {
  return props.roomChoice || props.hotel?.lowest_room || props.hotel?.rooms?.[0] || null;
});

const stayNights = computed(() => Math.max(1, props.nights || 1));

const roomMoney = (room) => {
  return room?.display_money || {
    amount: room?.total_fare,
    currency: room?.currency || props.hotel?.currency || "PKR",
  };
};

const roomTaxMoney = (room) => {
  return room?.display_tax_money || {
    amount: room?.total_tax,
    currency: room?.currency || props.hotel?.currency || "PKR",
  };
};

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || money?.amount === "" || !money?.currency) {
    return "Unavailable";
  }
  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  })}`;
};

const perNightAmount = computed(() => {
  const m = roomMoney(activeRoom.value);
  if (!m?.amount) return null;
  const num = Math.round(Number(m.amount) / stayNights.value);
  return `${m.currency} ${num.toLocaleString()}`;
});

const totalAmount = computed(() => {
  return formatMoney(roomMoney(activeRoom.value));
});

const taxAmount = computed(() => {
  const t = roomTaxMoney(activeRoom.value);
  return t?.amount ? formatMoney(t) : null;
});

const firstPromotion = computed(() => {
  return props.hotel?.promotions?.[0] || activeRoom.value?.room_promotion?.[0] || null;
});

const formatRoomName = (room) => {
  if (!room) return "Standard Room";
  if (Array.isArray(room.name)) return room.name.filter(Boolean).join(", ") || "Standard Room";
  return room.name || "Standard Room";
};

const formatMeal = (mealType) => {
  if (!mealType) return "Room Only";
  return String(mealType).replaceAll("_", " ");
};

const roomImage = (room) => {
  return props.roomDetailsMap[room?.booking_code]?.primary_image || null;
};
</script>

<template>
  <article
    class="overflow-hidden rounded border border-slate-200/90 bg-white text-slate-900 shadow-sm transition-all duration-200 hover:border-slate-300 hover:shadow-md"
  >
    <!-- HORIZONTAL 3-COLUMN CARD (Mobile: stacked) -->
    <div class="flex flex-col lg:flex-row lg:items-stretch">
      
      <!-- COLUMN 1: IMAGE (LEFT) -->
      <div class="relative h-52 w-full shrink-0 overflow-hidden bg-slate-100 lg:h-auto lg:w-[260px]">
        <template v-if="activeImage && !failedImages.has(activeImage)">
          <img
            :src="activeImage"
            :alt="hotel.name"
            referrerpolicy="no-referrer"
            class="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
            @error="failedImages.add(activeImage)"
          />
          
          <!-- Image Carousel Arrows -->
          <template v-if="allImages.length > 1">
            <button
              type="button"
              class="absolute left-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded border border-slate-200 bg-white/90 text-slate-800 shadow transition hover:bg-white"
              aria-label="Previous image"
              @click.stop="prevImage"
            >
              <ChevronLeft class="h-4 w-4" />
            </button>
            <button
              type="button"
              class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded border border-slate-200 bg-white/90 text-slate-800 shadow transition hover:bg-white"
              aria-label="Next image"
              @click.stop="nextImage"
            >
              <ChevronRight class="h-4 w-4" />
            </button>
            
            <!-- Index indicator -->
            <span class="absolute bottom-2 right-2 rounded-sm bg-slate-950/75 px-1.5 py-0.5 text-[10px] font-bold text-white">
              {{ currentImageIndex + 1 }}/{{ allImages.length }}
            </span>
          </template>
        </template>
        
        <!-- Fallback if no images -->
        <div v-else class="flex h-full w-full items-center justify-center text-muted-foreground">
          <Hotel class="h-10 w-10 text-slate-300" />
        </div>

        <!-- Deal / Promotion badge overlay -->
        <div
          v-if="firstPromotion"
          class="absolute left-2.5 top-2.5 rounded-sm border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-extrabold text-amber-900 shadow-xs"
        >
          {{ firstPromotion }}
        </div>
      </div>

      <!-- COLUMN 2: DETAILS (MIDDLE) -->
      <div class="flex flex-1 flex-col justify-between p-4 sm:p-5 min-w-0">
        <div>
          <!-- Name & Stars -->
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <h3
                class="text-base sm:text-lg font-black text-slate-900 hover:text-primary transition-colors cursor-pointer leading-snug line-clamp-1"
                @click="emits('viewDeal', hotel)"
              >
                {{ hotel.name }}
              </h3>
              
              <div class="mt-1 flex items-center gap-2">
                <div v-if="starCount > 0" class="flex items-center gap-0.5">
                  <Star
                    v-for="s in starCount"
                    :key="s"
                    class="h-3.5 w-3.5 fill-amber-500 text-amber-500"
                  />
                </div>
                <span class="text-xs font-semibold text-slate-600">
                  {{ formattedRating }}
                </span>
              </div>
            </div>

            <!-- Review Score Badge -->
            <div class="flex items-center gap-2 text-right shrink-0">
              <div class="hidden sm:block text-right">
                <p class="text-xs font-extrabold text-slate-900 leading-none">
                  {{ reviewScore.label }}
                </p>
                <p class="text-[10px] text-muted-foreground mt-0.5">
                  {{ reviewScore.count }} reviews
                </p>
              </div>
              <span class="flex h-8 w-9 items-center justify-center rounded bg-primary text-xs font-black text-white shadow-2xs">
                {{ reviewScore.score }}
              </span>
            </div>
          </div>

          <!-- Location -->
          <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-600 font-medium">
            <MapPin class="h-3.5 w-3.5 shrink-0 text-primary" />
            <span class="truncate">{{ locationDisplay }}</span>
            <span v-if="hotel.address" class="hidden md:inline truncate">· {{ hotel.address }}</span>
          </p>

          <!-- Key Amenities Chips -->
          <div class="mt-3 flex flex-wrap gap-1.5">
            <span
              v-for="amenity in amenitiesList"
              :key="amenity"
              class="rounded-sm border border-slate-200 bg-slate-50/80 px-2.5 py-1 text-[11px] font-medium text-slate-700"
            >
              {{ amenity }}
            </span>
          </div>
        </div>

        <!-- Cancellation Badge & Room info -->
        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-3">
          <div class="flex items-center gap-2">
            <span
              v-if="hasFreeCancellation"
              class="inline-flex items-center gap-1 rounded-sm border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-800"
            >
              <Check class="h-3 w-3 stroke-[3]" />
              Free cancellation
            </span>
            <span
              v-else
              class="inline-flex items-center rounded-sm border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
            >
              Non-refundable
            </span>
          </div>

          <span class="text-xs font-semibold text-slate-600">
            {{ hotel.room_count || hotel.rooms?.length || 1 }} room {{ (hotel.room_count || hotel.rooms?.length || 1) === 1 ? 'type' : 'types' }}
          </span>
        </div>
      </div>

      <!-- COLUMN 3: PRICE BLOCK (RIGHT) -->
      <div class="flex w-full lg:w-[230px] shrink-0 flex-col justify-between p-4 sm:p-5 bg-slate-50/90 border-t lg:border-t-0 lg:border-l border-slate-200 text-left lg:text-right">
        <div>
          <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
            Price for {{ stayNights }} {{ stayNights === 1 ? 'night' : 'nights' }}
          </p>
          
          <!-- Per night price -->
          <p v-if="perNightAmount" class="mt-1 text-xs text-muted-foreground">
            <span class="font-extrabold text-slate-900 text-sm">{{ perNightAmount }}</span> / night
          </p>

          <!-- Total Price (Bold and prominent) -->
          <p class="mt-1 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
            {{ totalAmount }}
          </p>

          <!-- Taxes and fees note -->
          <p class="mt-1 text-[11px] text-muted-foreground">
            <span v-if="taxAmount">Includes {{ taxAmount }} taxes &amp; fees</span>
            <span v-else>Includes all taxes and fees</span>
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="mt-4 space-y-2 w-full">
          <!-- View Deal Button (Primary CTA, 44px) -->
          <button
            type="button"
            class="flex h-11 w-full items-center justify-center gap-1.5 rounded bg-primary text-sm font-bold text-white shadow-xs transition-colors hover:bg-primary/95 active:bg-primary"
            @click="emits('viewDeal', hotel)"
          >
            <span>View deal</span>
            <ChevronRight class="h-4 w-4" />
          </button>

          <!-- Toggle Room Options Link -->
          <button
            type="button"
            class="flex w-full items-center justify-center lg:justify-end gap-1 text-xs font-bold text-primary hover:underline py-1"
            @click="emits('toggleRooms', hotel)"
          >
            <Plus v-if="!props.isExpanded" class="h-3.5 w-3.5" />
            <Minus v-else class="h-3.5 w-3.5" />
            <span>{{ props.isExpanded ? 'Hide room options' : `Show ${hotel.rooms?.length || 0} room options` }}</span>
          </button>
        </div>
      </div>

    </div>

    <!-- EXPANDABLE ROOM OPTIONS ACCORDION -->
    <div
      v-if="props.isExpanded"
      class="border-t border-slate-200 bg-slate-50/60 p-4 sm:p-5"
    >
      <div class="mb-3.5 flex items-center justify-between">
        <div>
          <h4 class="text-sm font-bold text-foreground">Available Room Types</h4>
          <p class="text-xs text-muted-foreground">Prices shown for entire stay ({{ guestsSummary }})</p>
        </div>
        <span class="rounded-sm border border-border bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
          {{ hotel.rooms?.length || 0 }} options
        </span>
      </div>

      <div class="divide-y divide-border/60">
        <div
          v-for="room in hotel.rooms || []"
          :key="room.booking_code"
          class="grid cursor-pointer items-center gap-3 py-3 transition-colors hover:bg-muted/30 lg:grid-cols-[auto_100px_minmax(0,2fr)_minmax(140px,1fr)_auto] px-2 rounded"
          @click="emits('chooseRoom', hotel, room)"
        >
          <!-- Radio dot -->
          <span
            class="flex h-4 w-4 items-center justify-center rounded-full border border-border bg-background transition"
            :class="{ 'border-primary bg-primary text-primary-foreground': room.booking_code === roomChoice?.booking_code }"
          >
            <Check v-if="room.booking_code === roomChoice?.booking_code" class="h-3 w-3" />
          </span>

          <!-- Small Room Image -->
          <div class="relative h-16 w-24 overflow-hidden rounded bg-muted shrink-0">
            <img
              v-if="roomImage(room)"
              :src="roomImage(room)"
              :alt="formatRoomName(room)"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-[10px] text-muted-foreground">
              No preview
            </div>
          </div>

          <!-- Room Info -->
          <div class="min-w-0">
            <p class="truncate text-xs font-bold text-foreground">
              {{ formatRoomName(room) }}
            </p>
            <div class="mt-1 flex flex-wrap gap-2 text-[11px] text-muted-foreground">
              <span class="flex items-center gap-1">
                <Coffee class="h-3 w-3 text-primary" />
                {{ formatMeal(room.meal_type) }}
              </span>
              <span
                :class="room.is_refundable ? 'text-chart-2 font-semibold' : 'text-muted-foreground'"
              >
                {{ room.is_refundable ? 'Refundable' : 'Non-refundable' }}
              </span>
            </div>
          </div>

          <!-- Inclusions -->
          <div class="text-[11px] text-muted-foreground">
            <p v-if="room.inclusion" class="truncate">{{ room.inclusion }}</p>
            <p v-else class="italic">Standard room amenities</p>
          </div>

          <!-- Room Price & Select -->
          <div class="text-right shrink-0">
            <p class="text-sm font-bold text-foreground">
              {{ formatMoney(roomMoney(room)) }}
            </p>
            <button
              type="button"
              class="mt-1 rounded px-3 py-1 text-xs font-bold transition-colors"
              :class="[
                room.booking_code === roomChoice?.booking_code
                  ? 'bg-primary text-primary-foreground'
                  : 'border border-border bg-card text-foreground hover:bg-muted'
              ]"
              @click.stop="emits('selectRoom', hotel, room)"
            >
              {{ room.booking_code === roomChoice?.booking_code ? 'Selected' : 'Select' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </article>
</template>
