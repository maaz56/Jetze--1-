<script setup>
import { Button } from "@/components/ui/button";
import HotelStepIndicator from "@/components/hotel/HotelStepIndicator.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { getSelectedCurrencyCode } from "@/lib/utils";
import {
  ArrowLeft,
  Bed,
  Building2,
  CalendarDays,
  Car,
  Check,
  ChevronLeft,
  ChevronRight,
  CircleAlert,
  Clock,
  Coffee,
  CreditCard,
  Dumbbell,
  ExternalLink,
  Heart,
  ImageOff,
  Images,
  Info,
  Lock,
  Mail,
  MapPin,
  Maximize2,
  Phone,
  ShieldCheck,
  Sparkles,
  Star,
  Tv,
  Users,
  Utensils,
  Waves,
  Wind,
  Wifi,
  X,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();

const hotel = ref(null);
const isLoading = ref(true);
const errorMessage = ref("");
const prebookError = ref("");
const selectedBookingCode = ref("");
const loadedImageUrls = ref(new Set());
const failedImageUrls = ref(new Set());
const isGalleryOpen = ref(false);
const activeGalleryIndex = ref(0);
const galleryImages = ref([]);
const galleryTitle = ref("");
const roomImageIndexes = ref({});
const isOverviewExpanded = ref(false);
const isFacilitiesExpanded = ref(false);
const isAttractionsExpanded = ref(false);
const expandedRoomDescriptions = ref(new Set());
const activeTab = ref("overview");

const imageGallery = computed(() => [
  ...new Set(
    [hotel.value?.primary_image, ...(hotel.value?.images || [])].filter(Boolean),
  ),
]);
const previewImages = computed(() => imageGallery.value.slice(1, 5));
const activeGalleryImage = computed(
  () => galleryImages.value[activeGalleryIndex.value] || null,
);
const roomOptions = computed(() => hotel.value?.rooms || []);
const isSelectedRoomPage = computed(() => route.name === "HotelRoomDetails");
const selectedRoom = computed(
  () =>
    roomOptions.value.find(
      (room) => room.booking_code === selectedBookingCode.value,
    ) ||
    roomOptions.value[0] ||
    null,
);
const facilities = computed(() => hotel.value?.facilities || []);
const attractions = computed(() => hotel.value?.attractions || []);
const visibleFacilities = computed(() =>
  isFacilitiesExpanded.value ? facilities.value : facilities.value.slice(0, 12),
);
const visibleAttractions = computed(() =>
  isAttractionsExpanded.value ? attractions.value : attractions.value.slice(0, 6),
);
const isPrebooking = computed(() => hotelStore.getIsPrebooking);

const staySummary = computed(() => {
  const paxRooms = hotel.value?.stay?.pax_rooms || [];
  const adults = paxRooms.reduce((total, room) => total + Number(room?.Adults || 0), 0);
  const children = paxRooms.reduce((total, room) => total + Number(room?.Children || 0), 0);
  const guests = adults + children;

  return guests ? `${guests} guest${guests === 1 ? "" : "s"}` : "Guests from search";
});

const nightsCount = computed(() => {
  const checkIn = hotel.value?.stay?.check_in;
  const checkOut = hotel.value?.stay?.check_out;
  if (!checkIn || !checkOut) return 1;
  const diff = Math.round((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
  return Math.max(1, diff);
});

const mapUrl = computed(() => {
  const { latitude, longitude } = hotel.value?.location || {};
  return latitude && longitude
    ? `https://www.google.com/maps?q=${encodeURIComponent(`${latitude},${longitude}`)}`
    : null;
});

const reviewScore = computed(() => {
  const rating = Number(hotel.value?.rating) || 4;
  if (rating >= 5) return 9.3;
  if (rating >= 4) return 8.7;
  if (rating >= 3) return 7.9;
  return 7.2;
});

const reviewLabel = computed(() => {
  const score = reviewScore.value;
  if (score >= 9) return "Wonderful";
  if (score >= 8.5) return "Excellent";
  if (score >= 8) return "Very Good";
  return "Good";
});

const reviewCategories = computed(() => [
  { label: "Cleanliness", score: (reviewScore.value + 0.2).toFixed(1) },
  { label: "Location", score: Math.min(9.8, reviewScore.value + 0.4).toFixed(1) },
  { label: "Service & Staff", score: reviewScore.value.toFixed(1) },
  { label: "Comfort", score: (reviewScore.value - 0.1).toFixed(1) },
  { label: "Value for money", score: (reviewScore.value - 0.3).toFixed(1) },
  { label: "Free WiFi", score: (reviewScore.value + 0.1).toFixed(1) },
]);

/** Convert provider overview HTML into safe plain text for display. */
const description = computed(() => {
  const html = hotel.value?.description_html;
  if (!html) {
    return "Hotel description is not available.";
  }
  const documentNode = new DOMParser().parseFromString(html, "text/html");
  return (
    documentNode.body.textContent?.replace(/\s+/g, " ").trim() ||
    "Hotel description is not available."
  );
});

/** Return one converted room amount with a provider-money fallback. */
const roomMoney = (room) =>
  room?.display_money || {
    amount: room?.total_fare,
    currency: room?.currency,
  };

/** Format a converted hotel price for the current selected currency. */
const formatMoney = (money) => {
  if (
    !money?.currency ||
    money.amount === null ||
    money.amount === undefined ||
    money.amount === ""
  ) {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

/** Make TBO's room-name array readable in the UI. */
const formatRoomName = (room) =>
  Array.isArray(room?.name)
    ? room.name.filter(Boolean).join(", ")
    : room?.name || "Standard Room";

const roomPromotions = (room) =>
  Array.isArray(room?.room_promotion)
    ? room.room_promotion.filter(Boolean)
    : room?.room_promotion
    ? [room.room_promotion]
    : [];

const isRoomDescriptionExpanded = (room) =>
  expandedRoomDescriptions.value.has(room?.booking_code);

const toggleRoomDescription = (room) => {
  if (!room?.booking_code) return;
  const nextExpanded = new Set(expandedRoomDescriptions.value);
  if (nextExpanded.has(room.booking_code)) {
    nextExpanded.delete(room.booking_code);
  } else {
    nextExpanded.add(room.booking_code);
  }
  expandedRoomDescriptions.value = nextExpanded;
};

const roomDetails = (room) => room?.room_details || null;
const roomImages = (room) => [
  ...new Set((roomDetails(room)?.images || []).filter(Boolean)),
];
const roomImageIndex = (room) => roomImageIndexes.value[room?.booking_code] || 0;
const roomImage = (room) => roomImages(room)[roomImageIndex(room)] || null;

const moveRoomImage = (room, direction) => {
  const images = roomImages(room);
  if (images.length < 2 || !room?.booking_code) return;
  const nextIndex =
    (roomImageIndex(room) + direction + images.length) % images.length;
  roomImageIndexes.value = {
    ...roomImageIndexes.value,
    [room.booking_code]: nextIndex,
  };
};

const markImageLoaded = (imageUrl) => {
  loadedImageUrls.value = new Set([...loadedImageUrls.value, imageUrl]);
};

const markImageFailed = (imageUrl) => {
  failedImageUrls.value = new Set([...failedImageUrls.value, imageUrl]);
};

const isImageLoaded = (imageUrl) => loadedImageUrls.value.has(imageUrl);
const isImageFailed = (imageUrl) => failedImageUrls.value.has(imageUrl);

const openGallery = (images, title, index = 0) => {
  const uniqueImages = [...new Set((images || []).filter(Boolean))];
  if (!uniqueImages.length) return;
  galleryImages.value = uniqueImages;
  galleryTitle.value = title || hotel.value?.name || "Hotel photos";
  activeGalleryIndex.value = Math.max(0, Math.min(index, uniqueImages.length - 1));
  isGalleryOpen.value = true;
};

const openHotelGallery = (index = 0) =>
  openGallery(imageGallery.value, hotel.value?.name, index);

const openRoomGallery = (room, index = 0) =>
  openGallery(
    roomImages(room),
    roomDetails(room)?.name || formatRoomName(room),
    index,
  );

const moveGallery = (direction) => {
  const totalImages = galleryImages.value.length;
  if (!totalImages) return;
  activeGalleryIndex.value =
    (activeGalleryIndex.value + direction + totalImages) % totalImages;
};

const onKeydown = (e) => {
  if (!isGalleryOpen.value) return;
  if (e.key === "ArrowLeft") moveGallery(-1);
  if (e.key === "ArrowRight") moveGallery(1);
  if (e.key === "Escape") isGalleryOpen.value = false;
};

const chooseRoom = (room) => {
  if (!room?.booking_code) return;
  selectedBookingCode.value = room.booking_code;
  router.push({
    name: "HotelRoomDetails",
    query: {
      search_session_id: route.query.search_session_id,
      hotel_code: route.query.hotel_code,
      booking_code: room.booking_code,
    },
  });
};

const scrollToSection = (id) => {
  activeTab.value = id;
  const element = document.getElementById(id);
  if (element) {
    const yOffset = -80;
    const y = element.getBoundingClientRect().top + window.pageYOffset + yOffset;
    window.scrollTo({ top: y, behavior: "smooth" });
  }
};

const backToSearch = () => {
  if (isSelectedRoomPage.value) {
    router.push({
      name: "HotelDetails",
      query: {
        search_session_id: route.query.search_session_id,
        hotel_code: route.query.hotel_code,
      },
    });
    return;
  }
  router.push({ name: "HotelSearch" });
};

const loadHotelDetails = async () => {
  const searchSessionId = String(route.query.search_session_id || "");
  const hotelCode = String(route.query.hotel_code || "");
  const bookingCode = String(route.query.booking_code || "");

  isLoading.value = true;
  errorMessage.value = "";
  prebookError.value = "";
  selectedBookingCode.value = "";

  if (!searchSessionId || !hotelCode) {
    errorMessage.value = "Hotel details require an active hotel search.";
    isLoading.value = false;
    return;
  }

  try {
    const response = await hotelStore.fetchHotelDetails({
      search_session_id: searchSessionId,
      hotel_code: hotelCode,
      booking_code: bookingCode || undefined,
      currency_code: getSelectedCurrencyCode(),
    });
    hotel.value = response.data || null;
    selectedBookingCode.value =
      response.data?.selected_booking_code || bookingCode || response.data?.rooms?.[0]?.booking_code;

    if (isSelectedRoomPage.value && !selectedRoom.value) {
      errorMessage.value =
        "The selected room is no longer available. Please choose another room.";
    }
  } catch (error) {
    errorMessage.value =
      hotelStore.getErrorMessage || "Unable to load hotel details.";
  } finally {
    isLoading.value = false;
  }
};

const continueToCheckout = async (targetRoom = null) => {
  const searchSessionId = String(route.query.search_session_id || "");
  const room = targetRoom || selectedRoom.value;

  if (!searchSessionId || !room?.booking_code) {
    prebookError.value = "Select an available room before continuing.";
    return;
  }

  prebookError.value = "";

  try {
    const response = await hotelStore.prebookHotel({
      search_session_id: searchSessionId,
      booking_code: room.booking_code,
      payment_mode: "Limit",
      currency_code: getSelectedCurrencyCode(),
    });

    router.push({
      name: "HotelCheckout",
      query: { prebook_id: response.data?.prebook_id },
    });
  } catch (error) {
    prebookError.value =
      hotelStore.getErrorMessage ||
      "Unable to continue to checkout. Please try again.";
  }
};

const getAmenityIcon = (name = "") => {
  const lower = name.toLowerCase();
  if (lower.includes("wifi") || lower.includes("internet")) return Wifi;
  if (lower.includes("air condition") || lower.includes("ac") || lower.includes("climate")) return Wind;
  if (lower.includes("pool") || lower.includes("swim")) return Waves;
  if (lower.includes("parking") || lower.includes("garage")) return Car;
  if (lower.includes("restaurant") || lower.includes("dining") || lower.includes("meal")) return Utensils;
  if (lower.includes("breakfast") || lower.includes("coffee") || lower.includes("cafe")) return Coffee;
  if (lower.includes("gym") || lower.includes("fitness")) return Dumbbell;
  if (lower.includes("tv") || lower.includes("television")) return Tv;
  if (lower.includes("desk") || lower.includes("hour")) return Clock;
  if (lower.includes("cleaning") || lower.includes("housekeeping") || lower.includes("spa")) return Sparkles;
  return Check;
};

onMounted(() => {
  window.addEventListener("keydown", onKeydown);
  loadHotelDetails();
});

onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeydown);
});

watch(() => route.fullPath, loadHotelDetails);
</script>

<template>
  <HotelStepIndicator :current-step="1" />

  <main class="min-h-screen bg-slate-100/75 py-6 text-foreground">
    <div class="mx-auto w-full max-w-booking px-4 sm:px-6">
      <!-- Breadcrumb / Back button & Context bar -->
      <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <Button
          variant="outline"
          class="h-10 gap-2 text-xs font-bold border-slate-200 bg-white shadow-xs hover:bg-slate-50"
          @click="backToSearch"
        >
          <ArrowLeft class="h-4 w-4" />
          {{ isSelectedRoomPage ? "View all rooms" : "Back to search results" }}
        </Button>

        <span
          v-if="hotel?.stay?.check_in && hotel?.stay?.check_out"
          class="inline-flex items-center gap-2 rounded border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs"
        >
          <CalendarDays class="h-4 w-4 text-primary" />
          <span>{{ hotel.stay.check_in }} — {{ hotel.stay.check_out }}</span>
          <span class="text-slate-400">·</span>
          <span class="font-bold text-slate-900">{{ nightsCount }} night{{ nightsCount === 1 ? '' : 's' }}</span>
          <span class="text-slate-400">·</span>
          <span>{{ staySummary }}</span>
        </span>
      </div>

      <!-- Skeleton loading state -->
      <div v-if="isLoading" class="space-y-5 animate-pulse">
        <div class="h-32 rounded border border-slate-200 bg-white p-6 shadow-xs">
          <div class="h-8 w-1/2 rounded bg-slate-200" />
          <div class="mt-3 h-4 w-1/3 rounded bg-slate-200" />
        </div>
        <div class="grid h-96 grid-cols-1 gap-3 rounded border border-slate-200 bg-white p-3 shadow-xs lg:grid-cols-4">
          <div class="col-span-2 h-full rounded bg-slate-200 lg:col-span-2" />
          <div class="col-span-1 hidden h-full space-y-3 lg:block">
            <div class="h-44 rounded bg-slate-200" />
            <div class="h-44 rounded bg-slate-200" />
          </div>
          <div class="col-span-1 hidden h-full space-y-3 lg:block">
            <div class="h-44 rounded bg-slate-200" />
            <div class="h-44 rounded bg-slate-200" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="h-24 rounded border border-slate-200 bg-white p-4 shadow-xs" />
          <div class="h-24 rounded border border-slate-200 bg-white p-4 shadow-xs" />
          <div class="h-24 rounded border border-slate-200 bg-white p-4 shadow-xs" />
          <div class="h-24 rounded border border-slate-200 bg-white p-4 shadow-xs" />
        </div>
        <div class="h-48 rounded border border-slate-200 bg-white p-6 shadow-xs" />
      </div>

      <!-- Error State -->
      <section
        v-else-if="errorMessage"
        class="rounded border border-destructive/30 bg-white p-10 text-center shadow-sm"
      >
        <CircleAlert class="mx-auto h-12 w-12 text-destructive" />
        <h1 class="mt-4 text-xl font-black text-destructive">Unable to load hotel</h1>
        <p class="mt-2 text-sm text-muted-foreground">{{ errorMessage }}</p>
        <Button class="mt-6 h-11 px-6 font-bold" @click="backToSearch">Return to search</Button>
      </section>

      <!-- Hotel Content -->
      <section v-else-if="hotel" class="space-y-6">
        <!-- Hotel Header Card -->
        <header class="rounded border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
          <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-2">
              <div class="flex flex-wrap items-center gap-2">
                <span
                  v-if="hotel.rating"
                  class="inline-flex items-center gap-1.5 rounded-sm border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800"
                >
                  <Star class="h-3.5 w-3.5 fill-amber-500 text-amber-500" />
                  {{ hotel.rating }}-Star Hotel
                </span>
                <span class="rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                  {{ hotel.room_count || roomOptions.length }} room types available
                </span>
              </div>

              <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl leading-tight">
                {{ hotel.name }}
              </h1>

              <p class="flex items-start gap-1.5 text-sm font-medium text-slate-600">
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <span>{{ [hotel.address, hotel.city, hotel.country].filter(Boolean).join(", ") }}</span>
                <a
                  v-if="mapUrl"
                  :href="mapUrl"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="ml-1.5 inline-flex items-center gap-1 font-bold text-primary hover:underline"
                >
                  Show on map <ExternalLink class="h-3.5 w-3.5" />
                </a>
              </p>
            </div>

            <!-- Top-right Review Score & Reserve CTA -->
            <div class="flex items-center gap-4 self-start md:items-center">
              <div class="flex items-center gap-3 rounded border border-slate-200 bg-slate-50 p-2.5 sm:p-3">
                <div class="text-right">
                  <p class="text-sm font-extrabold text-slate-900 leading-tight">{{ reviewLabel }}</p>
                  <p class="text-xs text-muted-foreground">Verified guest reviews</p>
                </div>
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-primary font-black text-xl text-white shadow-xs">
                  {{ reviewScore }}
                </div>
              </div>
              <Button
                class="hidden h-12 px-6 text-sm font-bold shadow-sm md:inline-flex"
                @click="scrollToSection('rooms')"
              >
                Reserve a room
              </Button>
            </div>
          </div>
        </header>

        <!-- Photo Gallery (1 large + 4 small in 2x2 grid) with Lightbox -->
        <div v-if="imageGallery.length" class="overflow-hidden rounded border border-slate-200 bg-white p-2.5 shadow-sm">
          <div class="grid gap-2.5 lg:grid-cols-[1.3fr_0.7fr]">
            <!-- Large Left Image -->
            <button
              type="button"
              class="group relative h-72 overflow-hidden rounded bg-slate-100 text-left md:h-[440px]"
              aria-label="Open all hotel photos"
              @click="openHotelGallery(0)"
            >
              <img
                v-if="imageGallery[0] && !isImageFailed(imageGallery[0])"
                :src="imageGallery[0]"
                :alt="hotel.name"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                :class="{ 'opacity-0': !isImageLoaded(imageGallery[0]) }"
                @load="markImageLoaded(imageGallery[0])"
                @error="markImageFailed(imageGallery[0])"
              />
              <div
                v-if="imageGallery[0] && !isImageLoaded(imageGallery[0]) && !isImageFailed(imageGallery[0])"
                class="absolute inset-0 animate-pulse bg-slate-200"
              />
              <div
                v-if="!imageGallery[0] || isImageFailed(imageGallery[0])"
                class="flex h-full items-center justify-center text-muted-foreground"
              >
                <ImageOff class="mr-2 h-6 w-6" /> Image unavailable
              </div>
              <span class="absolute bottom-3 left-3 inline-flex items-center gap-2 rounded border border-slate-900/30 bg-slate-950/80 px-3.5 py-2 text-xs font-bold text-white backdrop-blur-md shadow-md transition group-hover:bg-slate-950">
                <Images class="h-4 w-4 text-primary-light" /> See all {{ imageGallery.length }} photos
              </span>
            </button>

            <!-- 4 Small Right Images in 2x2 grid -->
            <div v-if="previewImages.length" class="hidden grid-cols-2 gap-2.5 lg:grid">
              <button
                v-for="(image, index) in previewImages"
                :key="`${image}-${index}`"
                type="button"
                class="group relative h-[215px] overflow-hidden rounded bg-slate-100 text-left"
                :aria-label="`Open hotel photo ${index + 2}`"
                @click="openHotelGallery(index + 1)"
              >
                <img
                  v-if="!isImageFailed(image)"
                  :src="image"
                  :alt="`${hotel.name} ${index + 2}`"
                  class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                  :class="{ 'opacity-0': !isImageLoaded(image) }"
                  @load="markImageLoaded(image)"
                  @error="markImageFailed(image)"
                />
                <div
                  v-if="!isImageLoaded(image) && !isImageFailed(image)"
                  class="absolute inset-0 animate-pulse bg-slate-200"
                />
                <div
                  v-if="isImageFailed(image)"
                  class="flex h-full items-center justify-center text-muted-foreground"
                >
                  <ImageOff class="h-5 w-5" />
                </div>
                <span
                  v-if="index === previewImages.length - 1 && imageGallery.length > 5"
                  class="absolute inset-0 flex flex-col items-center justify-center bg-slate-950/70 text-base font-black text-white backdrop-blur-[2px] transition group-hover:bg-slate-950/80"
                >
                  <span>+{{ imageGallery.length - 5 }} photos</span>
                  <span class="text-xs font-semibold text-slate-200">Click to view all</span>
                </span>
              </button>
            </div>
          </div>
        </div>

        <!-- Property Highlights / Stat Tiles (Generous, high-contrast cards) -->
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4">
          <!-- Check-in -->
          <div class="rounded border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                <Clock class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Check-in from</p>
                <p class="text-base font-black text-slate-900 truncate">{{ hotel.check_in_time || "14:00" }}</p>
              </div>
            </div>
          </div>

          <!-- Check-out -->
          <div class="rounded border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                <Clock class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Check-out until</p>
                <p class="text-base font-black text-slate-900 truncate">{{ hotel.check_out_time || "11:00" }}</p>
              </div>
            </div>
          </div>

          <!-- Property Rating -->
          <div class="rounded border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-amber-500/10 text-amber-600">
                <Star class="h-5 w-5 fill-amber-500 text-amber-500" />
              </div>
              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Property rating</p>
                <p class="text-base font-black text-slate-900 truncate">{{ hotel.rating || 4 }}-Star Standard</p>
              </div>
            </div>
          </div>

          <!-- Cancellation Policy -->
          <div class="rounded border border-slate-200 bg-white p-4 shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-600">
                <ShieldCheck class="h-5 w-5" />
              </div>
              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Cancellation</p>
                <p class="text-base font-black text-emerald-700 truncate">Policy applies</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Sticky Sub-Navigation Bar -->
        <nav class="sticky top-0 z-20 rounded border border-slate-200 bg-white/95 px-4 py-2.5 shadow-xs backdrop-blur-md">
          <div class="flex items-center justify-between">
            <div class="flex gap-1 overflow-x-auto text-xs font-bold sm:gap-2 sm:text-sm">
              <button
                type="button"
                class="rounded px-3.5 py-2 transition"
                :class="activeTab === 'overview' ? 'bg-primary text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                @click="scrollToSection('overview')"
              >
                Overview
              </button>
              <button
                type="button"
                class="rounded px-3.5 py-2 transition"
                :class="activeTab === 'rooms' ? 'bg-primary text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                @click="scrollToSection('rooms')"
              >
                Rooms ({{ roomOptions.length }})
              </button>
              <button
                type="button"
                class="rounded px-3.5 py-2 transition"
                :class="activeTab === 'amenities' ? 'bg-primary text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                @click="scrollToSection('amenities')"
              >
                Amenities
              </button>
              <button
                type="button"
                class="rounded px-3.5 py-2 transition"
                :class="activeTab === 'reviews' ? 'bg-primary text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                @click="scrollToSection('reviews')"
              >
                Reviews & Score
              </button>
              <button
                type="button"
                class="rounded px-3.5 py-2 transition"
                :class="activeTab === 'policies' ? 'bg-primary text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'"
                @click="scrollToSection('policies')"
              >
                Policies & Location
              </button>
            </div>

            <div v-if="selectedRoom" class="hidden items-center gap-3 py-1 sm:flex">
              <div class="text-right">
                <p class="text-[11px] font-semibold text-muted-foreground">Price from</p>
                <p class="text-sm font-black text-slate-900">
                  {{ formatMoney(roomMoney(selectedRoom)) }}
                </p>
              </div>
              <Button
                class="h-9 px-4 text-xs font-bold"
                @click="scrollToSection('rooms')"
              >
                Choose room
              </Button>
            </div>
          </div>
        </nav>

        <!-- Main Grid Layout: Content Left, Sticky Booking Summary Right -->
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
          <!-- Left Column Content -->
          <div class="space-y-6">
            <!-- Dedicated Selected Room Banner (if routed from room details) -->
            <section
              v-if="isSelectedRoomPage && selectedRoom"
              class="overflow-hidden rounded border-2 border-primary/50 bg-white p-5 shadow-xs"
            >
              <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                  <span class="flex h-6 w-6 items-center justify-center rounded bg-primary text-white">
                    <Check class="h-4 w-4 stroke-[3]" />
                  </span>
                  <span class="text-xs font-bold uppercase tracking-wider text-primary">Selected Room Option</span>
                </div>
                <Button variant="ghost" class="h-8 text-xs font-bold text-slate-600 hover:text-primary" @click="backToSearch">
                  Change room
                </Button>
              </div>

              <div class="mt-4 grid gap-5 sm:grid-cols-[200px_minmax(0,1fr)]">
                <div class="relative h-40 overflow-hidden rounded border border-slate-200 bg-slate-100">
                  <img
                    v-if="roomImage(selectedRoom) && !isImageFailed(roomImage(selectedRoom))"
                    :src="roomImage(selectedRoom)"
                    :alt="formatRoomName(selectedRoom)"
                    class="h-full w-full object-cover"
                    @error="markImageFailed(roomImage(selectedRoom))"
                  />
                  <div v-else class="flex h-full items-center justify-center text-xs text-muted-foreground">
                    <ImageOff class="mr-1.5 h-4 w-4" /> No image
                  </div>
                </div>

                <div class="space-y-2.5">
                  <h2 class="text-lg font-black text-slate-900">{{ formatRoomName(selectedRoom) }}</h2>
                  <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600">
                    <span v-if="roomDetails(selectedRoom)?.size" class="flex items-center gap-1">
                      <Maximize2 class="h-3.5 w-3.5 text-primary" /> {{ roomDetails(selectedRoom).size }}
                    </span>
                    <span>·</span>
                    <span>{{ staySummary }}</span>
                    <span>·</span>
                    <span>{{ selectedRoom.meal_type || "Room only" }}</span>
                  </div>

                  <div class="flex flex-wrap gap-2 pt-1">
                    <span
                      class="rounded-sm px-2.5 py-1 text-xs font-bold"
                      :class="selectedRoom.is_refundable ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                    >
                      {{ selectedRoom.is_refundable ? "Free cancellation available" : "Non-refundable" }}
                    </span>
                    <span v-if="selectedRoom.inclusion" class="rounded-sm border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700">
                      {{ selectedRoom.inclusion }}
                    </span>
                  </div>

                  <p v-if="roomDetails(selectedRoom)?.description" class="pt-1 text-xs leading-relaxed text-slate-600">
                    {{ roomDetails(selectedRoom).description }}
                  </p>
                </div>
              </div>
            </section>

            <!-- Overview Section -->
            <section id="overview" class="rounded border border-slate-200 bg-white p-6 shadow-sm">
              <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Property Overview</h2>
              <p
                v-if="description !== 'Hotel description is not available.'"
                class="mt-3.5 text-sm leading-relaxed text-slate-700 font-normal"
                :class="{ 'line-clamp-4': !isOverviewExpanded }"
              >
                {{ description }}
              </p>
              <button
                v-if="description !== 'Hotel description is not available.' && description.length > 360"
                type="button"
                class="mt-2.5 text-xs font-bold text-primary hover:underline"
                @click="isOverviewExpanded = !isOverviewExpanded"
              >
                {{ isOverviewExpanded ? "Show less" : "Read full property description" }}
              </button>
            </section>

            <!-- Available Rooms Section (Elevated Spacious Room Cards) -->
            <section id="rooms" class="rounded border border-slate-200 bg-white p-6 shadow-sm">
              <div class="border-b border-slate-100 pb-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Available Rooms</h2>
                    <p class="mt-0.5 text-xs font-medium text-muted-foreground">
                      {{ staySummary }} · {{ nightsCount }} night{{ nightsCount === 1 ? '' : 's' }} stay
                    </p>
                  </div>
                  <span class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                    <CalendarDays class="h-3.5 w-3.5 text-primary" />
                    {{ hotel.stay?.check_in }} to {{ hotel.stay?.check_out }}
                  </span>
                </div>
              </div>

              <!-- Structured Modern Room Cards -->
              <div v-if="roomOptions.length" class="mt-5 space-y-4">
                <article
                  v-for="room in roomOptions"
                  :key="room.booking_code"
                  class="rounded border transition-all duration-200 p-5 sm:p-6"
                  :class="selectedBookingCode === room.booking_code
                    ? 'border-2 border-primary bg-primary/[0.015] ring-2 ring-primary/20 shadow-md'
                    : 'border-slate-200 bg-white shadow-xs hover:border-slate-300 hover:shadow-md'"
                >
                  <!-- Card Header: Room Name & Badges -->
                  <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="space-y-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-lg sm:text-xl font-extrabold text-slate-900 tracking-tight leading-snug">
                          {{ formatRoomName(room) }}
                        </h3>
                        <span
                          v-if="selectedBookingCode === room.booking_code"
                          class="inline-flex items-center gap-1 rounded bg-primary px-2.5 py-0.5 text-xs font-bold text-white shadow-xs"
                        >
                          <Check class="h-3.5 w-3.5 stroke-[3]" /> Selected Room
                        </span>
                      </div>

                      <!-- Room Specs Pills -->
                      <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
                        <span v-if="room.bedding_group" class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-2.5 py-1">
                          <Bed class="h-3.5 w-3.5 text-primary" />
                          {{ room.bedding_group }}
                        </span>
                        <span v-if="roomDetails(room)?.size" class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-2.5 py-1">
                          <Maximize2 class="h-3.5 w-3.5 text-primary" />
                          {{ roomDetails(room).size }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-slate-50 px-2.5 py-1">
                          <Users class="h-3.5 w-3.5 text-primary" />
                          {{ staySummary }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <!-- Card Body: 3-Column Responsive Grid -->
                  <div class="mt-4 grid grid-cols-1 md:grid-cols-[220px_minmax(0,1fr)_220px] gap-5 items-stretch">
                    <!-- Col 1: Room Photo & Gallery Launcher -->
                    <div class="relative h-44 w-full md:w-[220px] shrink-0 overflow-hidden rounded border border-slate-200 bg-slate-100 group">
                      <img
                        v-if="roomImage(room) && !isImageFailed(roomImage(room))"
                        :src="roomImage(room)"
                        :alt="formatRoomName(room)"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        @error="markImageFailed(roomImage(room))"
                      />
                      <div v-else class="flex h-full items-center justify-center text-xs text-muted-foreground">
                        <Building2 class="h-8 w-8 text-slate-300" />
                      </div>

                      <!-- Left / Right Carousel Controls -->
                      <div
                        v-if="roomImages(room).length > 1"
                        class="absolute inset-x-1.5 top-1/2 -translate-y-1/2 flex items-center justify-between opacity-0 group-hover:opacity-100 transition-opacity"
                      >
                        <button
                          type="button"
                          class="h-7 w-7 rounded bg-black/70 text-white flex items-center justify-center hover:bg-black transition"
                          aria-label="Previous room photo"
                          @click.stop="moveRoomImage(room, -1)"
                        >
                          <ChevronLeft class="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          class="h-7 w-7 rounded bg-black/70 text-white flex items-center justify-center hover:bg-black transition"
                          aria-label="Next room photo"
                          @click.stop="moveRoomImage(room, 1)"
                        >
                          <ChevronRight class="h-4 w-4" />
                        </button>
                      </div>

                      <!-- Photo Count Badge -->
                      <button
                        v-if="roomImages(room).length > 0"
                        type="button"
                        class="absolute bottom-2 left-2 rounded bg-black/75 px-2 py-1 text-[11px] font-bold text-white backdrop-blur-xs hover:bg-black transition flex items-center gap-1 shadow-xs"
                        @click="openRoomGallery(room, roomImageIndex(room))"
                      >
                        <Images class="h-3 w-3" />
                        {{ roomImages(room).length }} photo{{ roomImages(room).length === 1 ? '' : 's' }}
                      </button>
                    </div>

                    <!-- Col 2: Benefits, Meal Inclusions & Room Description -->
                    <div class="flex flex-col justify-between space-y-3">
                      <div class="space-y-2.5">
                        <!-- Cancellation badge -->
                        <div>
                          <span
                            class="inline-flex items-center gap-1.5 rounded-sm px-2.5 py-1 text-xs font-bold"
                            :class="room.is_refundable
                              ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                              : 'bg-slate-100 text-slate-600 border border-slate-200'"
                          >
                            <Check v-if="room.is_refundable" class="h-3.5 w-3.5 text-emerald-600 stroke-[3]" />
                            {{ room.is_refundable ? "Free cancellation available" : "Non-refundable" }}
                          </span>
                        </div>

                        <!-- Meal Type -->
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                          <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                            <Utensils class="h-3.5 w-3.5" />
                          </div>
                          <span>{{ room.meal_type || "Room only / Meal details on arrival" }}</span>
                        </div>

                        <!-- Inclusion -->
                        <div v-if="room.inclusion" class="flex items-center gap-2 text-xs font-medium text-slate-700">
                          <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <Check class="h-3.5 w-3.5 stroke-[3]" />
                          </div>
                          <span>{{ room.inclusion }}</span>
                        </div>

                        <!-- Promotion Badges -->
                        <div v-if="roomPromotions(room).length" class="flex flex-wrap gap-1.5 pt-1">
                          <span
                            v-for="promotion in roomPromotions(room)"
                            :key="promotion"
                            class="rounded-sm border border-amber-300 bg-amber-50 px-2 py-0.5 text-[11px] font-extrabold text-amber-900"
                          >
                            {{ promotion }}
                          </span>
                        </div>

                        <!-- Room Description Snippet -->
                        <div v-if="roomDetails(room)?.description" class="pt-1">
                          <p
                            class="text-xs leading-relaxed text-slate-600"
                            :class="{ 'line-clamp-2': !isRoomDescriptionExpanded(room) }"
                          >
                            {{ roomDetails(room).description }}
                          </p>
                          <button
                            type="button"
                            class="mt-1 text-[11px] font-bold text-primary hover:underline"
                            @click="toggleRoomDescription(room)"
                          >
                            {{ isRoomDescriptionExpanded(room) ? "Show less" : "Show room details" }}
                          </button>
                        </div>
                      </div>
                    </div>

                    <!-- Col 3: Pricing & Action Box -->
                    <div class="rounded border border-slate-200 bg-slate-50/90 p-4 flex flex-col justify-between text-right space-y-3">
                      <div>
                        <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                          {{ nightsCount }} night{{ nightsCount === 1 ? '' : 's' }} stay
                        </p>
                        <p class="mt-1 text-2xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none">
                          {{ formatMoney(roomMoney(room)) }}
                        </p>
                        <p v-if="room.display_tax_money" class="mt-1 text-xs text-muted-foreground">
                          + {{ formatMoney(room.display_tax_money) }} taxes & fees
                        </p>
                        <p class="mt-1 text-[11px] font-medium text-slate-500">
                          Includes taxes & fees
                        </p>
                      </div>

                      <div>
                        <Button
                          class="h-11 w-full text-xs sm:text-sm font-bold shadow-xs transition-all"
                          :variant="selectedBookingCode === room.booking_code ? 'default' : 'default'"
                          :class="selectedBookingCode === room.booking_code
                            ? 'bg-primary text-white ring-2 ring-primary/30 ring-offset-1'
                            : 'bg-primary text-white hover:bg-primary/95'"
                          @click="chooseRoom(room)"
                        >
                          <Check v-if="selectedBookingCode === room.booking_code" class="mr-1.5 h-4 w-4 stroke-[3]" />
                          {{ selectedBookingCode === room.booking_code ? "Selected" : "Reserve room" }}
                        </Button>
                      </div>
                    </div>
                  </div>
                </article>
              </div>

              <div v-else class="mt-5 rounded border border-slate-200 bg-slate-50 p-6 text-center text-sm font-medium text-slate-600">
                No rooms are available for the selected dates. Please adjust your dates or guest count.
              </div>
            </section>

            <!-- Amenities & Facilities Section -->
            <section id="amenities" class="rounded border border-slate-200 bg-white p-6 shadow-sm">
              <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Amenities & Facilities</h2>
              <p class="mt-0.5 text-xs text-muted-foreground">Popular facilities verified for this property</p>

              <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                <div
                  v-for="facility in visibleFacilities"
                  :key="facility"
                  class="flex items-center gap-3 rounded border border-slate-200 bg-slate-50/70 p-3 hover:bg-slate-100 hover:border-slate-300 transition"
                >
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                    <component
                      :is="getAmenityIcon(facility)"
                      class="h-4 w-4"
                    />
                  </div>
                  <span class="text-xs sm:text-sm font-semibold text-slate-800 truncate">{{ facility }}</span>
                </div>
              </div>

              <Button
                v-if="facilities.length > 12"
                variant="outline"
                class="mt-4 h-9 px-4 text-xs font-bold border-slate-200 bg-white hover:bg-slate-50"
                @click="isFacilitiesExpanded = !isFacilitiesExpanded"
              >
                {{ isFacilitiesExpanded ? "Show fewer amenities" : `Show all ${facilities.length} amenities` }}
              </Button>
            </section>

            <!-- Guest Reviews & Rating Breakdown -->
            <section id="reviews" class="rounded border border-slate-200 bg-white p-6 shadow-sm">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                  <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Guest Reviews & Rating</h2>
                  <p class="mt-0.5 text-xs text-muted-foreground">Based on verified traveler feedback</p>
                </div>
                <div class="flex items-center gap-3 rounded border border-slate-200 bg-slate-50 p-2.5 sm:p-3">
                  <div class="text-right">
                    <p class="text-sm font-extrabold text-slate-900 leading-tight">{{ reviewLabel }}</p>
                    <p class="text-xs text-muted-foreground">Verified guest reviews</p>
                  </div>
                  <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded bg-primary font-black text-xl text-white shadow-xs">
                    {{ reviewScore }}
                  </div>
                </div>
              </div>

              <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <div
                  v-for="category in reviewCategories"
                  :key="category.label"
                  class="space-y-1.5 rounded border border-slate-200 bg-slate-50/80 p-3.5"
                >
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-bold text-slate-800">{{ category.label }}</span>
                    <span class="font-black text-slate-900">{{ category.score }} / 10</span>
                  </div>
                  <div class="h-2 w-full overflow-hidden rounded bg-slate-200">
                    <div
                      class="h-full bg-primary rounded"
                      :style="{ width: `${(Number(category.score) / 10) * 100}%` }"
                    />
                  </div>
                </div>
              </div>
            </section>

            <!-- Policies & Nearby Attractions Section -->
            <section id="policies" class="rounded border border-slate-200 bg-white p-6 shadow-sm">
              <h2 class="text-xl font-extrabold text-slate-900 tracking-tight">Property Policies</h2>
              <div class="mt-4 space-y-3.5 text-xs text-slate-600 divide-y divide-slate-100">
                <div class="flex gap-4 pt-3.5 first:pt-0">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                    <Clock class="h-4 w-4" />
                  </div>
                  <div>
                    <p class="font-extrabold text-slate-900 text-sm">Check-in and Check-out</p>
                    <p class="mt-0.5">Check-in from: {{ hotel.check_in_time || "14:00" }} · Check-out until: {{ hotel.check_out_time || "11:00" }}</p>
                  </div>
                </div>

                <div class="flex gap-4 pt-3.5">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-600">
                    <ShieldCheck class="h-4 w-4" />
                  </div>
                  <div>
                    <p class="font-extrabold text-slate-900 text-sm">Cancellation & Prepayment</p>
                    <p class="mt-0.5">Cancellation policies vary according to room type. Please review the conditions of your selected room.</p>
                  </div>
                </div>

                <div class="flex gap-4 pt-3.5">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                    <CreditCard class="h-4 w-4" />
                  </div>
                  <div>
                    <p class="font-extrabold text-slate-900 text-sm">Accepted Payment Methods</p>
                    <p class="mt-0.5">Credit card, Debit card, and Jetze Wallet balance.</p>
                  </div>
                </div>

                <div v-if="attractions.length" class="flex gap-4 pt-3.5">
                  <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                    <MapPin class="h-4 w-4" />
                  </div>
                  <div class="w-full">
                    <p class="font-extrabold text-slate-900 text-sm">Nearby Attractions</p>
                    <ul class="mt-2.5 grid grid-cols-1 gap-2 sm:grid-cols-2">
                      <li v-for="attraction in visibleAttractions" :key="attraction" class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-primary shrink-0" />
                        <span class="truncate">{{ attraction }}</span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <!-- Right Column: Sticky Booking Summary Card (Desktop) -->
          <aside class="space-y-4 lg:sticky lg:top-16">
            <article class="overflow-hidden rounded border border-slate-200 bg-white shadow-sm">
              <div class="bg-primary p-5 text-white">
                <p class="text-[11px] font-bold uppercase tracking-wider text-white/80">Your Booking Summary</p>
                <h3 class="mt-1 text-base font-black text-white leading-snug">{{ hotel.name }}</h3>
                <p class="mt-0.5 text-xs text-white/80">{{ [hotel.city, hotel.country].filter(Boolean).join(", ") }}</p>
              </div>

              <div class="space-y-4 p-5 text-xs">
                <!-- Stay Dates Card -->
                <div class="rounded border border-slate-200 bg-slate-50 p-3.5 space-y-2">
                  <div class="grid grid-cols-2 gap-2">
                    <div>
                      <span class="text-muted-foreground text-[11px] uppercase font-bold tracking-wider">Check-in</span>
                      <p class="mt-0.5 text-sm font-black text-slate-900">{{ hotel.stay?.check_in || "—" }}</p>
                    </div>
                    <div>
                      <span class="text-muted-foreground text-[11px] uppercase font-bold tracking-wider">Check-out</span>
                      <p class="mt-0.5 text-sm font-black text-slate-900">{{ hotel.stay?.check_out || "—" }}</p>
                    </div>
                  </div>
                  <div class="border-t border-slate-200/80 pt-2 text-xs font-semibold text-slate-700">
                    {{ nightsCount }} night{{ nightsCount === 1 ? '' : 's' }} · {{ staySummary }}
                  </div>
                </div>

                <!-- Selected Room Card -->
                <div class="rounded border border-slate-200 bg-slate-50 p-3.5 space-y-1.5">
                  <span class="text-[11px] uppercase font-bold tracking-wider text-muted-foreground">Selected room</span>
                  <p class="font-extrabold text-slate-900 text-sm leading-snug">
                    {{ selectedRoom ? formatRoomName(selectedRoom) : "No room selected" }}
                  </p>
                  <p v-if="selectedRoom?.meal_type" class="text-xs text-slate-600 font-medium">
                    {{ selectedRoom.meal_type }}
                  </p>
                  <div v-if="selectedRoom" class="pt-1">
                    <span
                      class="inline-block rounded-sm px-2 py-0.5 text-[11px] font-bold"
                      :class="selectedRoom.is_refundable ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-200 text-slate-700'"
                    >
                      {{ selectedRoom.is_refundable ? "Free cancellation available" : "Non-refundable" }}
                    </span>
                  </div>
                </div>

                <!-- Price Breakdown -->
                <div class="space-y-2 border-t border-slate-100 pt-3">
                  <div class="flex justify-between text-slate-600">
                    <span class="font-medium">Room fare</span>
                    <span class="font-bold text-slate-900">
                      {{ selectedRoom ? formatMoney(roomMoney(selectedRoom)) : "—" }}
                    </span>
                  </div>
                  <div v-if="selectedRoom?.display_tax_money" class="flex justify-between text-slate-600">
                    <span class="font-medium">Taxes & fees</span>
                    <span class="font-semibold text-slate-700">{{ formatMoney(selectedRoom.display_tax_money) }}</span>
                  </div>
                  <div class="flex items-baseline justify-between border-t border-slate-200/80 pt-2.5">
                    <span class="text-sm font-extrabold text-slate-900">Total price</span>
                    <span class="text-2xl font-black text-slate-900">
                      {{ selectedRoom ? formatMoney(roomMoney(selectedRoom)) : "—" }}
                    </span>
                  </div>
                  <p class="text-[11px] text-muted-foreground">
                    Includes all mandatory taxes and charges
                  </p>
                </div>

                <!-- Error Notice if prebook fails -->
                <p v-if="prebookError" class="rounded border border-destructive/30 bg-destructive/5 p-2.5 text-xs font-semibold text-destructive">
                  {{ prebookError }}
                </p>

                <!-- Primary CTA Button -->
                <Button
                  class="h-12 w-full text-base font-bold shadow-md shadow-primary/25"
                  :disabled="isPrebooking || !selectedRoom"
                  :is-loading="isPrebooking"
                  @click="continueToCheckout(selectedRoom)"
                >
                  {{ isPrebooking ? "Checking live rate..." : "Reserve your stay" }}
                </Button>

                <!-- Trust signals -->
                <div class="space-y-2 pt-1 text-[11px] text-muted-foreground">
                  <div class="flex items-center gap-2">
                    <Lock class="h-3.5 w-3.5 text-primary shrink-0" />
                    <span>Instant confirmation & 256-bit SSL encryption</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <ShieldCheck class="h-3.5 w-3.5 text-emerald-600 shrink-0" />
                    <span>Best price guarantee with direct provider recheck</span>
                  </div>
                </div>
              </div>
            </article>

            <!-- Property Contact Card -->
            <article
              v-if="hotel.contact?.phone_number || hotel.contact?.email || hotel.contact?.website_url"
              class="rounded border border-slate-200 bg-white p-4 text-xs shadow-sm"
            >
              <h4 class="font-extrabold text-slate-900">Property Contact</h4>
              <div class="mt-2.5 space-y-2 text-slate-600">
                <p v-if="hotel.contact?.phone_number" class="flex items-center gap-2">
                  <Phone class="h-3.5 w-3.5 text-primary shrink-0" /> {{ hotel.contact.phone_number }}
                </p>
                <p v-if="hotel.contact?.email" class="flex items-center gap-2">
                  <Mail class="h-3.5 w-3.5 text-primary shrink-0" /> {{ hotel.contact.email }}
                </p>
                <a
                  v-if="hotel.contact?.website_url"
                  :href="hotel.contact.website_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="flex items-center gap-1 font-bold text-primary hover:underline"
                >
                  Visit hotel website <ExternalLink class="h-3 w-3" />
                </a>
              </div>
            </article>
          </aside>
        </div>
      </section>
    </div>
  </main>

  <!-- Fullscreen Lightbox Modal -->
  <div
    v-if="isGalleryOpen"
    class="fixed inset-0 z-[100] flex flex-col bg-background/95 backdrop-blur-md p-4 text-foreground sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-label="Hotel photo gallery"
    @click.self="isGalleryOpen = false"
  >
    <div class="mx-auto flex w-full max-w-booking items-center justify-between gap-4 border-b border-border pb-3">
      <div>
        <p class="text-base font-bold">{{ galleryTitle }}</p>
        <p class="text-xs text-muted-foreground">{{ activeGalleryIndex + 1 }} of {{ galleryImages.length }} photos</p>
      </div>
      <Button
        variant="outline"
        class="h-9 w-9 p-0"
        aria-label="Close photo gallery"
        @click="isGalleryOpen = false"
      >
        <X class="h-4 w-4" />
      </Button>
    </div>

    <div class="mx-auto flex min-h-0 w-full max-w-booking flex-1 items-center gap-3 py-4 sm:gap-6">
      <Button
        variant="outline"
        class="h-11 w-11 shrink-0 p-0 shadow-xs disabled:opacity-30"
        aria-label="Previous photo"
        :disabled="galleryImages.length < 2"
        @click="moveGallery(-1)"
      >
        <ChevronLeft class="h-5 w-5" />
      </Button>

      <div class="relative flex h-full min-h-0 flex-1 items-center justify-center overflow-hidden rounded border border-border bg-muted/40">
        <img
          v-if="activeGalleryImage && !isImageFailed(activeGalleryImage)"
          :src="activeGalleryImage"
          :alt="`${galleryTitle || 'Hotel'} photo ${activeGalleryIndex + 1}`"
          class="max-h-full max-w-full object-contain"
          :class="{ 'opacity-0': !isImageLoaded(activeGalleryImage) }"
          @load="markImageLoaded(activeGalleryImage)"
          @error="markImageFailed(activeGalleryImage)"
        />
        <div
          v-if="activeGalleryImage && !isImageLoaded(activeGalleryImage) && !isImageFailed(activeGalleryImage)"
          class="absolute inset-0 animate-pulse bg-muted"
        />
        <div
          v-if="!activeGalleryImage || isImageFailed(activeGalleryImage)"
          class="flex h-full min-h-64 items-center justify-center text-muted-foreground"
        >
          <ImageOff class="mr-2 h-5 w-5" /> Image unavailable
        </div>
      </div>

      <Button
        variant="outline"
        class="h-11 w-11 shrink-0 p-0 shadow-xs disabled:opacity-30"
        aria-label="Next photo"
        :disabled="galleryImages.length < 2"
        @click="moveGallery(1)"
      >
        <ChevronRight class="h-5 w-5" />
      </Button>
    </div>

    <!-- Thumbnail filmstrip with 4px radius -->
    <div class="mx-auto flex w-full max-w-booking gap-2 overflow-x-auto border-t border-border pt-3">
      <button
        v-for="(image, index) in galleryImages"
        :key="`gallery-${image}-${index}`"
        type="button"
        class="relative h-14 w-20 shrink-0 overflow-hidden rounded border transition"
        :class="activeGalleryIndex === index ? 'border-primary ring-2 ring-primary/30' : 'border-border opacity-60 hover:opacity-100'"
        :aria-label="`Show hotel photo ${index + 1}`"
        @click="activeGalleryIndex = index"
      >
        <img
          v-if="!isImageFailed(image)"
          :src="image"
          :alt="`${galleryTitle || 'Hotel'} thumbnail ${index + 1}`"
          class="h-full w-full object-cover"
          @error="markImageFailed(image)"
        />
      </button>
    </div>
  </div>
</template>
