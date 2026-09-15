<script setup>
import { Button } from "@/components/ui/button";
import Nav from "@/components/shared/Nav.vue";
import { useHotelStore } from "@/services/stores/hotel";
import { getSelectedCurrencyCode } from "@/lib/utils";
import {
  ArrowLeft,
  Building2,
  CalendarDays,
  Check,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  ExternalLink,
  ImageOff,
  Images,
  Mail,
  MapPin,
  Phone,
  Star,
  X,
} from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
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

const imageGallery = computed(() => [...new Set([
  hotel.value?.primary_image,
  ...(hotel.value?.images || []),
].filter(Boolean))]);
const previewImages = computed(() => imageGallery.value.slice(1, 5));
const activeGalleryImage = computed(() => galleryImages.value[activeGalleryIndex.value] || null);
const roomOptions = computed(() => hotel.value?.rooms || []);
const selectedRoom = computed(() => roomOptions.value.find((room) => room.booking_code === selectedBookingCode.value) || roomOptions.value[0] || null);
const facilities = computed(() => hotel.value?.facilities || []);
const attractions = computed(() => hotel.value?.attractions || []);
const visibleFacilities = computed(() => isFacilitiesExpanded.value ? facilities.value : facilities.value.slice(0, 8));
const visibleAttractions = computed(() => isAttractionsExpanded.value ? attractions.value : attractions.value.slice(0, 6));
const isPrebooking = computed(() => hotelStore.getIsPrebooking);
const staySummary = computed(() => {
  const paxRooms = hotel.value?.stay?.pax_rooms || [];
  const adults = paxRooms.reduce((total, room) => total + Number(room?.Adults || 0), 0);
  const children = paxRooms.reduce((total, room) => total + Number(room?.Children || 0), 0);
  const guests = adults + children;

  return guests ? `${guests} guest${guests === 1 ? "" : "s"}` : "Guests from current search";
});
const mapUrl = computed(() => {
  const { latitude, longitude } = hotel.value?.location || {};

  return latitude && longitude
    ? `https://www.google.com/maps?q=${encodeURIComponent(`${latitude},${longitude}`)}`
    : null;
});

/** Convert provider overview HTML into safe plain text for display. */
const description = computed(() => {
  const html = hotel.value?.description_html;

  if (!html) {
    return "Hotel description is not available.";
  }

  const documentNode = new DOMParser().parseFromString(html, "text/html");
  return documentNode.body.textContent?.replace(/\s+/g, " ").trim() || "Hotel description is not available.";
});

/** Return one converted room amount with a provider-money fallback. */
const roomMoney = (room) => room?.display_money || {
  amount: room?.total_fare,
  currency: room?.currency,
};

/** Format a converted hotel price for the current selected currency. */
const formatMoney = (money) => {
  if (!money?.currency || money.amount === null || money.amount === undefined || money.amount === "") {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

/** Make TBO's room-name array readable in the UI. */
const formatRoomName = (room) => Array.isArray(room?.name)
  ? room.name.filter(Boolean).join(", ")
  : room?.name || "Room option";

const isRoomDescriptionExpanded = (room) => expandedRoomDescriptions.value.has(room?.booking_code);

const toggleRoomDescription = (room) => {
  if (!room?.booking_code) {
    return;
  }

  const nextExpandedDescriptions = new Set(expandedRoomDescriptions.value);
  if (nextExpandedDescriptions.has(room.booking_code)) {
    nextExpandedDescriptions.delete(room.booking_code);
  } else {
    nextExpandedDescriptions.add(room.booking_code);
  }
  expandedRoomDescriptions.value = nextExpandedDescriptions;
};

/** Return safe static metadata matched by TBO RoomID or a unique room-name fallback. */
const roomDetails = (room) => room?.room_details || null;
const roomImages = (room) => [...new Set((roomDetails(room)?.images || []).filter(Boolean))];
const roomImageIndex = (room) => roomImageIndexes.value[room?.booking_code] || 0;
const roomImage = (room) => roomImages(room)[roomImageIndex(room)] || null;

/** Move through the static images of one live room option. */
const moveRoomImage = (room, direction) => {
  const images = roomImages(room);
  if (images.length < 2 || !room?.booking_code) {
    return;
  }

  const nextIndex = (roomImageIndex(room) + direction + images.length) % images.length;
  roomImageIndexes.value = { ...roomImageIndexes.value, [room.booking_code]: nextIndex };
};

/** Mark one provider image independently, so content never waits for image rendering. */
const markImageLoaded = (imageUrl) => {
  loadedImageUrls.value = new Set([...loadedImageUrls.value, imageUrl]);
};

/** Replace a broken provider image with its local placeholder. */
const markImageFailed = (imageUrl) => {
  failedImageUrls.value = new Set([...failedImageUrls.value, imageUrl]);
};

const isImageLoaded = (imageUrl) => loadedImageUrls.value.has(imageUrl);
const isImageFailed = (imageUrl) => failedImageUrls.value.has(imageUrl);

/** Open a provider image set in the full gallery at a selected image. */
const openGallery = (images, title, index = 0) => {
  const uniqueImages = [...new Set((images || []).filter(Boolean))];
  if (!uniqueImages.length) {
    return;
  }

  galleryImages.value = uniqueImages;
  galleryTitle.value = title || hotel.value?.name || "Hotel photos";
  activeGalleryIndex.value = Math.max(0, Math.min(index, uniqueImages.length - 1));
  isGalleryOpen.value = true;
};

/** Open all property images from the mosaic preview. */
const openHotelGallery = (index = 0) => openGallery(imageGallery.value, hotel.value?.name, index);

/** Open only the verified images associated with this room. */
const openRoomGallery = (room, index = 0) => openGallery(
  roomImages(room),
  roomDetails(room)?.name || formatRoomName(room),
  index,
);

/** Move through all provider photos without leaving the gallery. */
const moveGallery = (direction) => {
  const totalImages = galleryImages.value.length;

  if (!totalImages) {
    return;
  }

  activeGalleryIndex.value = (activeGalleryIndex.value + direction + totalImages) % totalImages;
};

/** Choose a room locally; the provider price is still rechecked before checkout. */
const chooseRoom = (room) => {
  selectedBookingCode.value = room?.booking_code || "";
};

/** Return to the hotel search page without retaining this property's static response. */
const backToSearch = () => {
  router.push({ name: "HotelSearch" });
};

/** Load the property content only through its current trusted search session. */
const loadHotelDetails = async () => {
  const searchSessionId = String(route.query.search_session_id || "");
  const hotelCode = String(route.query.hotel_code || "");

  if (!searchSessionId || !hotelCode) {
    errorMessage.value = "Hotel details require an active hotel search.";
    isLoading.value = false;
    return;
  }

  try {
    const response = await hotelStore.fetchHotelDetails({
      search_session_id: searchSessionId,
      hotel_code: hotelCode,
      currency_code: getSelectedCurrencyCode(),
    });
    hotel.value = response.data || null;
    selectedBookingCode.value = hotel.value?.rooms?.[0]?.booking_code || "";
  } catch (error) {
    errorMessage.value = hotelStore.getErrorMessage || "Unable to load hotel details.";
  } finally {
    isLoading.value = false;
  }
};

/** Revalidate the selected provider room, then continue only with its locked PreBook quote. */
const continueToCheckout = async () => {
  const searchSessionId = String(route.query.search_session_id || "");
  const room = selectedRoom.value;

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
    prebookError.value = hotelStore.getErrorMessage || "Unable to confirm this room. Please try again.";
  }
};

onMounted(loadHotelDetails);
</script>

<template>
  <Nav />

  <main class="min-h-screen bg-slate-50 py-7">
    <div class="mx-auto w-full max-w-7xl px-4">
      <Button variant="outline" class="mb-6 gap-2" @click="backToSearch">
        <ArrowLeft class="h-4 w-4" />
        Back to hotel search
      </Button>

      <div v-if="isLoading" class="space-y-5 animate-pulse">
        <div class="h-72 rounded-2xl bg-slate-200" />
        <div class="h-10 w-1/2 rounded bg-slate-200" />
        <div class="h-24 rounded-xl bg-slate-200" />
      </div>

      <section v-else-if="errorMessage" class="rounded-xl border border-red-200 bg-red-50 p-7 text-center">
        <h1 class="text-xl font-bold text-red-800">Unable to load hotel details</h1>
        <p class="mt-2 text-sm text-red-700">{{ errorMessage }}</p>
        <Button class="mt-5" @click="backToSearch">Return to search</Button>
      </section>

      <section v-else-if="hotel" class="space-y-7">
        <div v-if="imageGallery.length" class="grid gap-3 overflow-hidden rounded-2xl lg:grid-cols-[1.1fr_0.9fr]">
          <button
            type="button"
            class="group relative h-72 overflow-hidden rounded-2xl bg-slate-200 text-left md:h-[430px]"
            aria-label="Open all hotel photos"
            @click="openHotelGallery(0)"
          >
            <img
              v-if="imageGallery[0] && !isImageFailed(imageGallery[0])"
              :src="imageGallery[0]"
              :alt="hotel.name"
              class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
              :class="{ 'opacity-0': !isImageLoaded(imageGallery[0]) }"
              @load="markImageLoaded(imageGallery[0])"
              @error="markImageFailed(imageGallery[0])"
            >
            <div v-if="imageGallery[0] && !isImageLoaded(imageGallery[0]) && !isImageFailed(imageGallery[0])" class="absolute inset-0 animate-pulse bg-slate-200" />
            <div v-if="!imageGallery[0] || isImageFailed(imageGallery[0])" class="flex h-full items-center justify-center text-slate-500">
              <ImageOff class="mr-2 h-5 w-5" /> Image unavailable
            </div>
            <span class="absolute bottom-4 left-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-slate-900 shadow-sm">
              <Images class="h-4 w-4 text-primary" /> See all {{ imageGallery.length }} photos
            </span>
          </button>

          <div v-if="previewImages.length" class="hidden grid-cols-2 gap-3 lg:grid">
            <button
              v-for="(image, index) in previewImages"
              :key="`${image}-${index}`"
              type="button"
              class="group relative h-[208px] overflow-hidden rounded-2xl bg-slate-200 text-left"
              :aria-label="`Open hotel photo ${index + 2}`"
              @click="openHotelGallery(index + 1)"
            >
              <img
                v-if="!isImageFailed(image)"
                :src="image"
                :alt="`${hotel.name} ${index + 2}`"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                :class="{ 'opacity-0': !isImageLoaded(image) }"
                @load="markImageLoaded(image)"
                @error="markImageFailed(image)"
              >
              <div v-if="!isImageLoaded(image) && !isImageFailed(image)" class="absolute inset-0 animate-pulse bg-slate-200" />
              <div v-if="isImageFailed(image)" class="flex h-full items-center justify-center text-slate-500"><ImageOff class="h-5 w-5" /></div>
              <span v-if="index === previewImages.length - 1 && imageGallery.length > 5" class="absolute inset-0 flex items-center justify-center bg-slate-950/50 text-lg font-bold text-white">
                +{{ imageGallery.length - 5 }} photos
              </span>
            </button>
          </div>
        </div>

        <div v-else class="flex h-72 items-center justify-center rounded-2xl bg-slate-200 text-slate-500">
          <ImageOff class="mr-2 h-5 w-5" /> Images unavailable
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
          <div class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
              <div class="flex flex-wrap items-center gap-2">
                <span v-if="hotel.rating" class="inline-flex items-center gap-1 rounded border border-primary/30 bg-primary/5 px-2 py-1 text-xs font-semibold text-primary">
                  <Star class="h-3.5 w-3.5 fill-current" /> {{ hotel.rating }} Star property
                </span>
                <span class="rounded bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ hotel.room_count || roomOptions.length }} room option{{ (hotel.room_count || roomOptions.length) === 1 ? "" : "s" }}</span>
              </div>
              <h1 class="mt-3 text-2xl font-bold text-slate-900 md:text-3xl">{{ hotel.name }}</h1>
              <p class="mt-2 flex items-start gap-2 text-sm text-slate-600">
                <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                {{ [hotel.address, hotel.city, hotel.country].filter(Boolean).join(", ") }}
              </p>
              <a v-if="mapUrl" :href="mapUrl" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                View on map <ExternalLink class="h-3.5 w-3.5" />
              </a>
            </section>

            <section v-if="description !== 'Hotel description is not available.' || facilities.length" class="rounded-xl border border-slate-200 bg-white p-5">
              <h2 class="text-xl font-bold text-slate-900">Overview</h2>
              <p
                v-if="description !== 'Hotel description is not available.'"
                class="mt-3 text-sm leading-6 text-slate-600"
                :class="{ 'line-clamp-4': !isOverviewExpanded }"
              >
                {{ description }}
              </p>
              <button
                v-if="description !== 'Hotel description is not available.' && description.length > 360"
                type="button"
                class="mt-2 text-sm font-semibold text-primary hover:underline"
                @click="isOverviewExpanded = !isOverviewExpanded"
              >
                {{ isOverviewExpanded ? "Show less" : "See more" }}
              </button>
              <div v-if="visibleFacilities.length" class="mt-4 grid gap-x-5 gap-y-2 text-sm text-slate-700 sm:grid-cols-2">
                <span v-for="facility in visibleFacilities" :key="facility" class="flex items-start gap-2"><Check class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> {{ facility }}</span>
              </div>
              <button
                v-if="facilities.length > 8"
                type="button"
                class="mt-3 text-sm font-semibold text-primary hover:underline"
                @click="isFacilitiesExpanded = !isFacilitiesExpanded"
              >
                {{ isFacilitiesExpanded ? "Show fewer facilities" : `See all ${facilities.length} facilities` }}
              </button>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5">
              <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <h2 class="text-xl font-bold text-slate-900">Select your room</h2>
                  <p class="mt-1 text-sm text-slate-600">{{ staySummary }} · Prices are for your current search.</p>
                </div>
                <span v-if="hotel.stay?.check_in && hotel.stay?.check_out" class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><CalendarDays class="h-4 w-4 text-primary" /> {{ hotel.stay.check_in }} to {{ hotel.stay.check_out }}</span>
              </div>

              <div v-if="roomOptions.length" class="mt-5 space-y-4">
                <article
                  v-for="room in roomOptions"
                  :key="room.booking_code"
                  class="overflow-hidden rounded-xl border transition"
                  :class="selectedRoom?.booking_code === room.booking_code ? 'border-primary ring-1 ring-primary/20' : 'border-slate-200'"
                >
                  <div class="grid lg:grid-cols-[260px_minmax(0,1fr)_190px]">
                    <div class="border-b border-slate-200 p-4 lg:border-b-0 lg:border-r">
                      <div class="relative h-44 overflow-hidden rounded-lg bg-slate-100">
                        <img
                          v-if="roomImage(room) && !isImageFailed(roomImage(room))"
                          :src="roomImage(room)"
                          :alt="roomDetails(room)?.name || formatRoomName(room)"
                          class="h-full w-full object-cover"
                          :class="{ 'opacity-0': !isImageLoaded(roomImage(room)) }"
                          @load="markImageLoaded(roomImage(room))"
                          @error="markImageFailed(roomImage(room))"
                        >
                        <div v-if="roomImage(room) && !isImageLoaded(roomImage(room)) && !isImageFailed(roomImage(room))" class="absolute inset-0 animate-pulse bg-slate-200" />
                        <div v-if="!roomImage(room) || isImageFailed(roomImage(room))" class="flex h-full items-center justify-center text-slate-400"><Building2 class="mr-2 h-5 w-5" /> Image unavailable</div>
                        <button v-if="roomImages(room).length > 1" type="button" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/95 p-1.5 text-slate-900 shadow" aria-label="Previous room image" @click="moveRoomImage(room, -1)"><ChevronLeft class="h-4 w-4" /></button>
                        <button v-if="roomImages(room).length > 1" type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/95 p-1.5 text-slate-900 shadow" aria-label="Next room image" @click="moveRoomImage(room, 1)"><ChevronRight class="h-4 w-4" /></button>
                        <span v-if="roomImages(room).length" class="absolute bottom-2 left-2 rounded bg-slate-950/75 px-2 py-1 text-xs font-bold text-white">{{ roomImageIndex(room) + 1 }}/{{ roomImages(room).length }}</span>
                      </div>
                      <button v-if="roomImages(room).length" type="button" class="mt-2 text-sm font-semibold text-primary hover:underline" @click="openRoomGallery(room, roomImageIndex(room))">Room photos and details</button>
                    </div>

                    <div class="min-w-0 p-5">
                      <h3 class="text-lg font-bold text-slate-900">{{ formatRoomName(room) }}</h3>
                      <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm font-medium text-slate-700">
                        <span v-if="roomDetails(room)?.size">{{ roomDetails(room).size }}</span>
                        <span>{{ staySummary }}</span>
                        <span>{{ room.meal_type || "Meal details unavailable" }}</span>
                      </p>
                      <p
                        v-if="roomDetails(room)?.description"
                        class="mt-3 text-sm leading-6 text-slate-600"
                        :class="{ 'line-clamp-3': !isRoomDescriptionExpanded(room) }"
                      >
                        {{ roomDetails(room).description }}
                      </p>
                      <button
                        v-if="roomDetails(room)?.description?.length > 270"
                        type="button"
                        class="mt-2 text-sm font-semibold text-primary hover:underline"
                        @click="toggleRoomDescription(room)"
                      >
                        {{ isRoomDescriptionExpanded(room) ? "Show less" : "See more" }}
                      </button>
                      <div class="mt-4 space-y-2 text-sm text-slate-700">
                        <p class="flex gap-2"><Check class="mt-0.5 h-4 w-4 shrink-0" :class="room.is_refundable ? 'text-emerald-600' : 'text-slate-500'" /> {{ room.is_refundable ? "Refundable room option" : "Non-refundable room option" }}</p>
                        <p v-if="room.inclusion" class="flex gap-2"><Check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> {{ room.inclusion }}</p>
                      </div>
                    </div>

                    <div class="flex flex-col justify-center border-t border-slate-200 p-5 lg:border-l lg:border-t-0">
                      <p class="text-right text-xl font-bold text-slate-900"><CircleDollarSign class="mr-1 inline h-4 w-4 text-primary" />{{ formatMoney(roomMoney(room)) }}</p>
                      <p v-if="room.display_tax_money" class="mt-1 text-right text-xs text-slate-500">Tax {{ formatMoney(room.display_tax_money) }}</p>
                      <p class="mt-4 text-right text-xs text-slate-500">Total for this stay</p>
                      <Button class="mt-3 h-10 w-full text-sm font-bold" :variant="selectedRoom?.booking_code === room.booking_code ? 'outline' : 'default'" @click="chooseRoom(room)">
                        {{ selectedRoom?.booking_code === room.booking_code ? "Selected" : "Select room" }}
                      </Button>
                    </div>
                  </div>
                </article>
              </div>
              <p v-else class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No rooms are available in this search anymore. Please return to search.</p>

              <p v-if="prebookError" class="mt-4 text-sm font-medium text-red-700">{{ prebookError }}</p>
              <div class="mt-5 flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500">TBO will recheck the selected room's final availability and price before checkout.</p>
                <Button class="h-11 gap-2 px-5 text-sm font-bold" :disabled="!selectedRoom || isPrebooking" :is-loading="isPrebooking" @click="continueToCheckout">Confirm room price</Button>
              </div>
            </section>

            <section v-if="attractions.length" class="rounded-xl border border-slate-200 bg-white p-5">
              <h2 class="text-xl font-bold text-slate-900">Nearby attractions</h2>
              <ul class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                <li v-for="attraction in visibleAttractions" :key="attraction" class="flex gap-2"><MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> {{ attraction }}</li>
              </ul>
              <button
                v-if="attractions.length > 6"
                type="button"
                class="mt-3 text-sm font-semibold text-primary hover:underline"
                @click="isAttractionsExpanded = !isAttractionsExpanded"
              >
                {{ isAttractionsExpanded ? "Show fewer attractions" : `See all ${attractions.length} attractions` }}
              </button>
            </section>
          </div>

          <aside class="space-y-5 lg:sticky lg:top-5">
            <section class="rounded-xl border border-slate-200 bg-white p-5">
              <h2 class="text-lg font-bold text-slate-900">Property information</h2>
              <div class="mt-4 grid grid-cols-2 gap-4 border-b border-slate-200 pb-4 text-sm">
                <p><span class="block font-bold text-slate-900">Check-in</span><span class="text-slate-600">{{ hotel.check_in_time || "Contact property" }}</span></p>
                <p><span class="block font-bold text-slate-900">Check-out</span><span class="text-slate-600">{{ hotel.check_out_time || "Contact property" }}</span></p>
              </div>
              <p v-if="hotel.pin_code" class="mt-4 flex items-center gap-2 text-sm text-slate-600"><Building2 class="h-4 w-4 text-primary" /> Postal code: {{ hotel.pin_code }}</p>
              <a v-if="mapUrl" :href="mapUrl" target="_blank" rel="noopener noreferrer" class="mt-4 flex items-center justify-center gap-2 rounded-lg bg-primary/5 px-3 py-3 text-sm font-semibold text-primary hover:bg-primary/10"><MapPin class="h-4 w-4" /> View property on map</a>
            </section>

            <section v-if="hotel.contact?.phone_number || hotel.contact?.email || hotel.contact?.website_url" class="rounded-xl border border-slate-200 bg-white p-5">
              <h2 class="text-lg font-bold text-slate-900">Contact</h2>
              <div class="mt-4 space-y-3 break-words text-sm text-slate-600">
                <p v-if="hotel.contact?.phone_number" class="flex gap-2"><Phone class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> {{ hotel.contact.phone_number }}</p>
                <p v-if="hotel.contact?.email" class="flex gap-2"><Mail class="mt-0.5 h-4 w-4 shrink-0 text-primary" /> {{ hotel.contact.email }}</p>
                <a v-if="hotel.contact?.website_url" :href="hotel.contact.website_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">Hotel website <ExternalLink class="h-3.5 w-3.5" /></a>
              </div>
            </section>

            <section v-if="hotel.fees?.mandatory?.length || hotel.fees?.optional?.length" class="rounded-xl border border-amber-200 bg-amber-50 p-5">
              <h2 class="text-lg font-bold text-amber-900">Property fees</h2>
              <p v-if="hotel.fees.mandatory?.length" class="mt-3 text-sm font-semibold text-amber-800">Mandatory fees may apply.</p>
              <p v-if="hotel.fees.optional?.length" class="mt-2 text-sm text-amber-800">Optional charges are available at the property.</p>
            </section>
          </aside>
        </div>
      </section>
    </div>
  </main>

  <div
    v-if="isGalleryOpen"
    class="fixed inset-0 z-[100] flex flex-col bg-slate-950/95 p-4 text-white sm:p-6"
    role="dialog"
    aria-modal="true"
    aria-label="Hotel photo gallery"
    @click.self="isGalleryOpen = false"
  >
    <div class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4">
      <div>
        <p class="text-lg font-bold">{{ galleryTitle }}</p>
        <p class="text-sm text-slate-300">{{ activeGalleryIndex + 1 }} of {{ galleryImages.length }} photos</p>
      </div>
      <button
        type="button"
        class="rounded-full p-2 text-white transition hover:bg-white/10"
        aria-label="Close photo gallery"
        @click="isGalleryOpen = false"
      >
        <X class="h-6 w-6" />
      </button>
    </div>

    <div class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 items-center gap-3 py-5 sm:gap-6">
      <button
        type="button"
        class="shrink-0 rounded-full bg-white p-3 text-slate-900 shadow transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Previous photo"
        :disabled="galleryImages.length < 2"
        @click="moveGallery(-1)"
      >
        <ChevronLeft class="h-6 w-6" />
      </button>

      <div class="relative flex h-full min-h-0 flex-1 items-center justify-center overflow-hidden rounded-xl bg-slate-900">
        <img
          v-if="activeGalleryImage && !isImageFailed(activeGalleryImage)"
          :src="activeGalleryImage"
        :alt="`${galleryTitle || 'Hotel'} photo ${activeGalleryIndex + 1}`"
          class="max-h-full max-w-full object-contain"
          :class="{ 'opacity-0': !isImageLoaded(activeGalleryImage) }"
          @load="markImageLoaded(activeGalleryImage)"
          @error="markImageFailed(activeGalleryImage)"
        >
        <div v-if="activeGalleryImage && !isImageLoaded(activeGalleryImage) && !isImageFailed(activeGalleryImage)" class="absolute inset-0 animate-pulse bg-slate-800" />
        <div v-if="!activeGalleryImage || isImageFailed(activeGalleryImage)" class="flex h-full min-h-64 items-center justify-center text-slate-300">
          <ImageOff class="mr-2 h-5 w-5" /> Image unavailable
        </div>
      </div>

      <button
        type="button"
        class="shrink-0 rounded-full bg-white p-3 text-slate-900 shadow transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
        aria-label="Next photo"
        :disabled="galleryImages.length < 2"
        @click="moveGallery(1)"
      >
        <ChevronRight class="h-6 w-6" />
      </button>
    </div>

    <div class="mx-auto flex w-full max-w-7xl gap-2 overflow-x-auto pb-2">
      <button
        v-for="(image, index) in galleryImages"
        :key="`gallery-${image}-${index}`"
        type="button"
        class="relative h-16 w-24 shrink-0 overflow-hidden rounded-md border-2 bg-slate-800"
        :class="activeGalleryIndex === index ? 'border-white' : 'border-transparent opacity-70 hover:opacity-100'"
        :aria-label="`Show hotel photo ${index + 1}`"
        @click="activeGalleryIndex = index"
      >
        <img
          v-if="!isImageFailed(image)"
          :src="image"
          :alt="`${galleryTitle || 'Hotel'} thumbnail ${index + 1}`"
          class="h-full w-full object-cover"
          @load="markImageLoaded(image)"
          @error="markImageFailed(image)"
        >
      </button>
    </div>
  </div>
</template>
