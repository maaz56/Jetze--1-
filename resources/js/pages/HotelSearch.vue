<script setup>
import Button from "@/components/ui/button/Button.vue";
import HotelSearchPanel from "@/components/hotel/HotelSearchPanel.vue";
import HotelFiltersSidebar from "@/components/hotel/HotelFiltersSidebar.vue";
import HotelCard from "@/components/hotel/HotelCard.vue";
import HotelResultsTopBar from "@/components/hotel/HotelResultsTopBar.vue";
import HotelCardSkeleton from "@/components/hotel/HotelCardSkeleton.vue";
import CountryDropdown from "@/components/common/CountryDropdown.vue";
import { useHotelStore } from "@/services/stores/hotel";
import {
  CalendarDays,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleDollarSign,
  Globe2,
  Hotel,
  MapPin,
  Minus,
  Plus,
  Search,
  SlidersHorizontal,
  Star,
  Users,
  X,
} from "lucide-vue-next";
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { getSelectedCurrencyCode } from "@/lib/utils";

const route = useRoute();
const router = useRouter();
const hotelStore = useHotelStore();

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
});

const today = new Date();
const tomorrow = new Date(today);
tomorrow.setDate(today.getDate() + 1);
const dayAfterTomorrow = new Date(today);
dayAfterTomorrow.setDate(today.getDate() + 2);

const formatDateInput = (date) => date.toISOString().slice(0, 10);
const formatDateLabel = (date) => {
  const [year, month, day] = String(date || "").split("-");

  return year && month && day ? `${day}/${month}/${year}` : "Select date";
};
const formatDisplayDate = (dateStr) => {
  if (!dateStr) return "";
  try {
    const d = new Date(`${dateStr}T00:00:00`);
    return new Intl.DateTimeFormat("en-GB", {
      day: "numeric",
      month: "short",
    }).format(d);
  } catch {
    return dateStr;
  }
};

const openDatePicker = (input) => {
  if (!input) {
    return;
  }

  input.focus({ preventScroll: true });

  if (typeof input.showPicker === "function") {
    try {
      input.showPicker();
      return;
    } catch {
      // Some browser implementations reject showPicker on visually hidden inputs.
    }
  }

  input.click();
};
const heroBackground = {
  backgroundImage: "linear-gradient(180deg, rgba(10, 20, 40, 0.35), rgba(10, 20, 40, 0.15) 55%, transparent), linear-gradient(120deg, hsl(var(--primary-dark)) 0%, hsl(var(--primary)) 55%, hsl(var(--primary-light)) 100%)",
};

const MAX_ROOMS = 6;
const MAX_ADULTS_PER_ROOM = 6;
const MAX_CHILDREN_PER_ROOM = 4;

const destinationQuery = ref("");
const selectedDestination = ref(null);
const destinationSuggestions = ref([]);
const showSuggestions = ref(false);
const checkIn = ref(formatDateInput(tomorrow));
const checkOut = ref(formatDateInput(dayAfterTomorrow));
const nights = computed(() => {
  if (!checkIn.value || !checkOut.value) {
    return 0;
  }

  const checkInDate = new Date(`${checkIn.value}T00:00:00`);
  const checkOutDate = new Date(`${checkOut.value}T00:00:00`);
  const diffTime = checkOutDate.getTime() - checkInDate.getTime();

  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
});
const checkInInput = ref(null);
const checkOutInput = ref(null);
const guestNationality = ref("pk");
const rooms = ref([
  {
    adults: 1,
    children_ages: [],
  },
]);
const showGuestsPanel = ref(false);
const guestsPanelRef = ref(null);
const formErrorMessage = ref("");
const expandedHotelCodes = ref(new Set());
const selectedBookingCode = ref("");
const roomChoices = ref({});
const hotelImageIndexes = ref({});
const loadedHotelImageKeys = ref(new Set());
const failedHotelImageKeys = ref(new Set());
const roomDetailsByHotel = ref({});
const propertyImagesByHotel = ref({});
const loadingRoomDetailsHotelCodes = ref(new Set());
const loadedRoomImageUrls = ref(new Set());
const failedRoomImageUrls = ref(new Set());
const hotelPriceLimit = ref(null);
const selectedRatings = ref([]);
const selectedMeals = ref([]);
const selectedReviewScore = ref(0);
const selectedAmenities = ref([]);
const freeCancellationOnly = ref(false);
const currentSort = ref("recommended");
const isMobileFilterOpen = ref(false);
const refundableFilter = ref("all");
const isLoadingSuggestions = computed(() => hotelStore.getIsLoadingSuggestions);
const isSearching = computed(() => hotelStore.getIsSearching);
const hotelResults = computed(() => hotelStore.getHotels);
const searchSessionId = computed(() => hotelStore.getSearchSessionId);
const providerStatus = computed(() => hotelStore.getProviderStatus);
const errorMessage = computed(() => formErrorMessage.value || hotelStore.getErrorMessage);
const totalRoomOptions = computed(() => hotelResults.value.reduce((total, hotelItem) => total + Number(hotelItem.room_count || hotelItem.rooms?.length || 0), 0));
const hotelRoomAmount = (room, hotelItem) => Number(roomMoney(room, hotelItem)?.amount || 0);
const hotelLowestAmount = (hotelItem) => {
  const amounts = (hotelItem.rooms || []).map((room) => hotelRoomAmount(room, hotelItem)).filter((amount) => amount > 0);
  return amounts.length ? Math.min(...amounts) : 0;
};
const maximumHotelPrice = computed(() => Math.max(...hotelResults.value.map(hotelLowestAmount), 0));
const hotelRatings = computed(() => [...new Set(hotelResults.value.map((hotelItem) => formatRating(hotelItem.rating)).filter((rating) => rating !== "Hotel"))]);
const hotelMealTypes = computed(() => [...new Set(
  hotelResults.value.flatMap((hotelItem) => (hotelItem.rooms || []).map((room) => formatMeal(room.meal_type))).filter(Boolean),
)]);

const starNumber = (rating) => {
  if (!rating) return 0;
  const str = String(rating).toLowerCase();
  if (str.includes("five") || str === "5") return 5;
  if (str.includes("four") || str === "4") return 4;
  if (str.includes("three") || str === "3") return 3;
  if (str.includes("two") || str === "2") return 2;
  if (str.includes("one") || str === "1") return 1;
  const num = parseInt(str, 10);
  return isNaN(num) ? 0 : Math.min(5, Math.max(0, num));
};

const hotelReviewScore = (hotelItem) => {
  if (hotelItem.review_score) {
    const s = Number(hotelItem.review_score);
    return {
      score: s.toFixed(1),
      label: s >= 9 ? "Wonderful" : s >= 8 ? "Very Good" : s >= 7 ? "Good" : "Pleasant",
      count: hotelItem.review_count || 120,
    };
  }
  const stars = starNumber(hotelItem.rating);
  if (stars >= 5) return { score: "9.2", label: "Wonderful", count: 340 };
  if (stars === 4) return { score: "8.6", label: "Very Good", count: 215 };
  if (stars === 3) return { score: "7.9", label: "Good", count: 148 };
  return { score: "7.2", label: "Pleasant", count: 85 };
};

const hotelAmenities = (hotelItem) => {
  const set = new Set();
  const hotelRooms = hotelItem.rooms || [];
  
  if (hotelRooms.some((r) => r.is_refundable)) set.add("Free cancellation");
  if (hotelRooms.some((r) => r.meal_type && String(r.meal_type).toLowerCase().includes("breakfast"))) {
    set.add("Breakfast included");
  }

  hotelRooms.forEach((r) => {
    if (r.inclusion) {
      String(r.inclusion)
        .split(",")
        .map((s) => s.trim())
        .filter(Boolean)
        .forEach((inc) => {
          const lower = inc.toLowerCase();
          if (lower.includes("wifi") || lower.includes("internet")) set.add("Free WiFi");
          else if (lower.includes("parking")) set.add("Free parking");
          else if (lower.includes("pool")) set.add("Swimming pool");
          else if (lower.includes("fitness") || lower.includes("gym")) set.add("Fitness center");
          else if (lower.includes("air") || lower.includes("ac")) set.add("Air conditioning");
          else if (inc.length <= 22) set.add(inc);
        });
    }
  });

  if (!set.has("Free WiFi")) set.add("Free WiFi");
  if (starNumber(hotelItem.rating) >= 4) {
    if (!set.has("Air conditioning")) set.add("Air conditioning");
  }

  return Array.from(set);
};

const filteredHotelResults = computed(() => hotelResults.value.filter((hotelItem) => {
  if (hotelPriceLimit.value !== null && hotelLowestAmount(hotelItem) > hotelPriceLimit.value) {
    return false;
  }

  if (selectedRatings.value.length) {
    const stars = starNumber(hotelItem.rating);
    const formatted = formatRating(hotelItem.rating);
    const matchesStar = selectedRatings.value.includes(stars) || selectedRatings.value.includes(formatted);
    if (!matchesStar) return false;
  }

  if (selectedReviewScore.value > 0) {
    const score = Number(hotelReviewScore(hotelItem).score);
    if (score < selectedReviewScore.value) return false;
  }

  if (freeCancellationOnly.value) {
    const hasRefundable = (hotelItem.rooms || []).some((r) => r.is_refundable);
    if (!hasRefundable) return false;
  }

  if (selectedAmenities.value.length) {
    const ams = hotelAmenities(hotelItem).map((a) => a.toLowerCase());
    const hasAll = selectedAmenities.value.every((req) =>
      ams.some((a) => a.includes(req.toLowerCase()))
    );
    if (!hasAll) return false;
  }

  return (hotelItem.rooms || []).some((room) => {
    const matchesRefundability = refundableFilter.value === "all"
      || (refundableFilter.value === "refundable" && room.is_refundable)
      || (refundableFilter.value === "non-refundable" && !room.is_refundable);
    const matchesMeal = !selectedMeals.value.length || selectedMeals.value.includes(formatMeal(room.meal_type));

    return matchesRefundability && matchesMeal;
  });
}));

const sortedFilteredHotelResults = computed(() => {
  const list = [...filteredHotelResults.value];
  if (currentSort.value === "price_asc") {
    return list.sort((a, b) => hotelLowestAmount(a) - hotelLowestAmount(b));
  }
  if (currentSort.value === "price_desc") {
    return list.sort((a, b) => hotelLowestAmount(b) - hotelLowestAmount(a));
  }
  if (currentSort.value === "rating_desc") {
    return list.sort((a, b) => starNumber(b.rating) - starNumber(a.rating));
  }
  if (currentSort.value === "score_desc") {
    return list.sort((a, b) => Number(hotelReviewScore(b).score) - Number(hotelReviewScore(a).score));
  }
  return list;
});

const activeFiltersCount = computed(() => {
  let count = 0;
  if (hotelPriceLimit.value !== null && hotelPriceLimit.value < maximumHotelPrice.value) count++;
  if (selectedRatings.value.length) count += selectedRatings.value.length;
  if (selectedReviewScore.value > 0) count++;
  if (selectedAmenities.value.length) count += selectedAmenities.value.length;
  if (freeCancellationOnly.value) count++;
  return count;
});

const removePriceLimit = () => {
  hotelPriceLimit.value = maximumHotelPrice.value;
};

const removeRating = (r) => {
  selectedRatings.value = selectedRatings.value.filter((val) => val !== r);
};

const removeReviewScore = () => {
  selectedReviewScore.value = 0;
};

const removeAmenity = (name) => {
  selectedAmenities.value = selectedAmenities.value.filter((val) => val !== name);
};

const removeFreeCancellation = () => {
  freeCancellationOnly.value = false;
};

const clearAllFilters = () => {
  hotelPriceLimit.value = maximumHotelPrice.value;
  selectedRatings.value = [];
  selectedReviewScore.value = 0;
  selectedAmenities.value = [];
  freeCancellationOnly.value = false;
  selectedMeals.value = [];
  refundableFilter.value = "all";
};

const resetHotelFilters = clearAllFilters;

watch(maximumHotelPrice, (maximumPrice) => {
  hotelPriceLimit.value = maximumPrice;
});

const guestsSummary = computed(() => {
  const roomCount = rooms.value.length;
  const adults = rooms.value.reduce((total, room) => total + Number(room.adults || 0), 0);
  const children = rooms.value.reduce((total, room) => total + room.children_ages.length, 0);
  const guests = adults + children;

  return `${roomCount} Room${roomCount > 1 ? "s" : ""}, ${guests} Guest${guests > 1 ? "s" : ""}`;
});

let latestSuggestionRequest = 0;

const fetchSuggestions = debounce(async (query) => {
  const requestId = ++latestSuggestionRequest;

  try {
    const suggestions = await hotelStore.fetchSuggestions(query);

    // Ignore a slow response for text the traveller has already changed.
    if (requestId === latestSuggestionRequest && query === destinationQuery.value.trim()) {
      destinationSuggestions.value = suggestions;
    }
  } catch (error) {
    if (requestId === latestSuggestionRequest) {
      destinationSuggestions.value = [];
    }
  }
}, 250);

const handleDestinationInput = () => {
  selectedDestination.value = null;
  showSuggestions.value = true;
  const query = destinationQuery.value.trim();

  if (query.length < 2) {
    latestSuggestionRequest += 1;
    destinationSuggestions.value = [];
    fetchSuggestions.cancel();
    return;
  }

  fetchSuggestions(query);
};

const openSuggestions = () => {
  showSuggestions.value = true;

  if (!destinationSuggestions.value.length && destinationQuery.value.trim().length >= 2) {
    fetchSuggestions(destinationQuery.value.trim());
  }
};

const selectDestination = (suggestion) => {
  selectedDestination.value = suggestion;
  destinationQuery.value = suggestion.label;
  destinationSuggestions.value = [];
  showSuggestions.value = false;
};

const addRoom = () => {
  if (rooms.value.length >= MAX_ROOMS) {
    return;
  }

  rooms.value.push({
    adults: 1,
    children_ages: [],
  });
};

const removeRoom = (index) => {
  if (rooms.value.length === 1) {
    return;
  }

  rooms.value.splice(index, 1);
};

const incrementAdults = (room) => {
  room.adults = Math.min(MAX_ADULTS_PER_ROOM, Number(room.adults || 0) + 1);
};

const decrementAdults = (room) => {
  room.adults = Math.max(1, Number(room.adults || 1) - 1);
};

const incrementChildren = (room) => {
  if (room.children_ages.length >= MAX_CHILDREN_PER_ROOM) {
    return;
  }

  room.children_ages.push(0);
};

const decrementChildren = (room) => {
  room.children_ages.pop();
};

const updateChildAge = (room, childIndex, value) => {
  room.children_ages[childIndex] = Number(value);
};

const childAgeOptions = [
  { label: "Under 1", value: 0 },
  ...Array.from({ length: 18 }, (_, index) => ({ label: String(index + 1), value: index + 1 })),
];

const closeGuestsPanel = () => {
  showGuestsPanel.value = false;
};

const handleClickOutsideGuestsPanel = (event) => {
  if (showGuestsPanel.value && guestsPanelRef.value && !guestsPanelRef.value.contains(event.target)) {
    showGuestsPanel.value = false;
  }
};

onMounted(() => {
  document.addEventListener("click", handleClickOutsideGuestsPanel);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutsideGuestsPanel);
  fetchSuggestions.cancel();
});

const destinationIcon = (type) => {
  if (type === "hotel") {
    return Hotel;
  }

  return MapPin;
};

const searchHotels = async (payload) => {
  formErrorMessage.value = "";

  if (payload) {
    if (payload.destination) selectedDestination.value = payload.destination;
    if (payload.check_in) checkIn.value = payload.check_in;
    if (payload.check_out) checkOut.value = payload.check_out;
    if (payload.guest_nationality) guestNationality.value = payload.guest_nationality;
    if (payload.rooms) rooms.value = payload.rooms;
  }

  if (!selectedDestination.value) {
    hotelStore.resetSearch();
    formErrorMessage.value = "Select a destination or hotel from the suggestions.";
    showSuggestions.value = true;
    return;
  }

  if (!checkIn.value || !checkOut.value || nights.value <= 0) {
    hotelStore.resetSearch();
    formErrorMessage.value = "Select valid check-in and check-out dates.";
    return;
  }

  if (!guestNationality.value) {
    guestNationality.value = "pk";
  }

  try {
    expandedHotelCodes.value = new Set();
    selectedBookingCode.value = "";
    roomChoices.value = {};
    roomDetailsByHotel.value = {};
    propertyImagesByHotel.value = {};
    loadingRoomDetailsHotelCodes.value = new Set();
    loadedRoomImageUrls.value = new Set();
    failedRoomImageUrls.value = new Set();
    await hotelStore.searchHotels({
      destination: selectedDestination.value,
      check_in: checkIn.value,
      check_out: checkOut.value,
      guest_nationality: guestNationality.value.toUpperCase(),
      rooms: rooms.value.map((room) => ({
        adults: Number(room.adults || 1),
        children: room.children_ages ? room.children_ages.length : (Number(room.children) || 0),
        children_ages: (room.children_ages || []).map((age) => Number(age)),
      })),
      filters: {
        refundable: false,
        no_of_rooms: Math.max(1, rooms.value.length),
        meal_type: "All",
      },
      currency_code: getSelectedCurrencyCode(),
    });

  } catch (error) {
    formErrorMessage.value = hotelStore.getErrorMessage || error.response?.data?.message || "Hotel search failed. Please try again.";
  }
};

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || money?.amount === "" || !money?.currency) {
    return "Unavailable";
  }

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const primaryRoom = (hotelItem) => hotelItem.lowest_room || hotelItem.rooms?.[0] || null;

function roomMoney(room, hotelItem) {
  return room?.display_money || {
    amount: room?.total_fare,
    currency: room?.currency || hotelItem?.currency,
  };
}

const roomTaxMoney = (room, hotelItem) => room?.display_tax_money || {
  amount: room?.total_tax,
  currency: room?.currency || hotelItem?.currency,
};

const isHotelExpanded = (hotelItem) => expandedHotelCodes.value.has(String(hotelItem.hotel_code));

const roomDetails = (hotelItem, room) => roomDetailsByHotel.value[String(hotelItem.hotel_code)]?.[room.booking_code] || null;
const roomImage = (hotelItem, room) => roomDetails(hotelItem, room)?.primary_image || null;
const isRoomDetailsLoading = (hotelItem) => loadingRoomDetailsHotelCodes.value.has(String(hotelItem.hotel_code));
const isRoomImageLoaded = (imageUrl) => loadedRoomImageUrls.value.has(imageUrl);
const isRoomImageFailed = (imageUrl) => failedRoomImageUrls.value.has(imageUrl);
const markRoomImageLoaded = (imageUrl) => {
  loadedRoomImageUrls.value = new Set([...loadedRoomImageUrls.value, imageUrl]);
};
const markRoomImageFailed = (imageUrl) => {
  failedRoomImageUrls.value = new Set([...failedRoomImageUrls.value, imageUrl]);
};

const loadHotelRoomDetails = async (hotelItem) => {
  const hotelCode = String(hotelItem.hotel_code);

  if (!searchSessionId.value || roomDetailsByHotel.value[hotelCode] || isRoomDetailsLoading(hotelItem)) {
    return;
  }

  loadingRoomDetailsHotelCodes.value = new Set([...loadingRoomDetailsHotelCodes.value, hotelCode]);

  try {
    const response = await hotelStore.fetchHotelDetails({
      search_session_id: searchSessionId.value,
      hotel_code: hotelCode,
      currency_code: getSelectedCurrencyCode(),
    });
    const detailsByBookingCode = Object.fromEntries((response.data?.rooms || [])
      .filter((room) => room.booking_code && room.room_details)
      .map((room) => [room.booking_code, room.room_details]));
    const propertyImages = (response.data?.images || []).filter(Boolean);

    roomDetailsByHotel.value = { ...roomDetailsByHotel.value, [hotelCode]: detailsByBookingCode };
    propertyImagesByHotel.value = { ...propertyImagesByHotel.value, [hotelCode]: propertyImages };
  } catch {
    roomDetailsByHotel.value = { ...roomDetailsByHotel.value, [hotelCode]: {} };
  } finally {
    const nextLoadingHotelCodes = new Set(loadingRoomDetailsHotelCodes.value);
    nextLoadingHotelCodes.delete(hotelCode);
    loadingRoomDetailsHotelCodes.value = nextLoadingHotelCodes;
  }
};

const toggleHotelRooms = async (hotelItem) => {
  const hotelCode = String(hotelItem.hotel_code);
  const nextExpandedHotelCodes = new Set(expandedHotelCodes.value);

  if (nextExpandedHotelCodes.has(hotelCode)) {
    nextExpandedHotelCodes.delete(hotelCode);
  } else {
    nextExpandedHotelCodes.add(hotelCode);
    expandedHotelCodes.value = nextExpandedHotelCodes;
    await loadHotelRoomDetails(hotelItem);
    return;
  }

  expandedHotelCodes.value = nextExpandedHotelCodes;
};

const selectRoom = (room) => {
  selectedBookingCode.value = room?.booking_code || "";
};

const roomChoiceForHotel = (hotelItem) => {
  const chosenBookingCode = roomChoices.value[String(hotelItem.hotel_code)];

  return hotelItem.rooms?.find((room) => room.booking_code === chosenBookingCode) || primaryRoom(hotelItem);
};

const chooseRoom = (hotelItem, room) => {
  roomChoices.value = {
    ...roomChoices.value,
    [String(hotelItem.hotel_code)]: room.booking_code,
  };
};

const hasChosenRoom = (hotelItem) => Boolean(roomChoices.value[String(hotelItem.hotel_code)]);

const isChosenRoom = (hotelItem, room) => roomChoiceForHotel(hotelItem)?.booking_code === room?.booking_code;

const selectChosenRoom = (hotelItem) => {
  const room = roomChoiceForHotel(hotelItem);

  if (!searchSessionId.value || !hotelItem?.hotel_code || !room?.booking_code) {
    formErrorMessage.value = "This room is unavailable. Please search again.";
    return;
  }

  selectRoom(room);
  router.push({
    name: "HotelRoomDetails",
    query: {
      search_session_id: searchSessionId.value,
      hotel_code: hotelItem.hotel_code,
      booking_code: room.booking_code,
    },
  });
};

const isSelectedRoom = (room) => selectedBookingCode.value && room?.booking_code === selectedBookingCode.value;

const formatRoomName = (room) => {
  if (!room) {
    return "Room option";
  }

  if (Array.isArray(room.name)) {
    return room.name.filter(Boolean).join(", ") || "Room option";
  }

  return room.name || "Room option";
};

const formatRating = (rating) => {
  if (!rating || rating === "All") {
    return "Hotel";
  }

  return String(rating)
    .replace(/([a-z])([A-Z])/g, "$1 $2");
};

const formatMeal = (mealType) => {
  if (!mealType) {
    return "Meal info unavailable";
  }

  return String(mealType).replaceAll("_", " ");
};

const inclusions = (room) => {
  if (!room?.inclusion) {
    return [];
  }

  return String(room.inclusion)
    .split(",")
    .map((item) => item.trim())
    .filter(Boolean)
    .slice(0, 3);
};

const firstPromotion = (hotelItem) => {
  const hotelPromotion = hotelItem.promotions?.[0];
  if (hotelPromotion) {
    return hotelPromotion;
  }

  return primaryRoom(hotelItem)?.room_promotion?.[0] || null;
};

const hasAtPropertySupplement = (room) => {
  if (!Array.isArray(room?.supplements)) {
    return false;
  }

  return room.supplements.flat(2).some((supplement) => supplement?.Type === "AtProperty");
};

const formatLocation = (hotelItem) => {
  return [hotelItem.city, hotelItem.country].filter(Boolean).join(", ") || "Location unavailable";
};

/** Return the cached property images that are safe to render on one search card. */
const hotelImages = (hotelItem) => {
  const searchImages = Array.isArray(hotelItem?.images) ? hotelItem.images.filter(Boolean) : [];

  return searchImages.length
    ? searchImages
    : (propertyImagesByHotel.value[String(hotelItem?.hotel_code)] || []);
};

/** Return the selected gallery image for one hotel card. */
const activeHotelImage = (hotelItem) => {
  const images = hotelImages(hotelItem);
  const index = Number(hotelImageIndexes.value[String(hotelItem?.hotel_code)] || 0);

  return images[index] || images[0] || null;
};

/** Create a stable load-state key for a card image. */
const hotelImageKey = (hotelItem) => `${hotelItem?.hotel_code}:${activeHotelImage(hotelItem) || "none"}`;

/** Move within one card's cached image gallery without another provider request. */
const moveHotelImage = (hotelItem, direction) => {
  const images = hotelImages(hotelItem);
  if (images.length < 2) return;

  const hotelCode = String(hotelItem.hotel_code);
  const currentIndex = Number(hotelImageIndexes.value[hotelCode] || 0);
  hotelImageIndexes.value = {
    ...hotelImageIndexes.value,
    [hotelCode]: (currentIndex + direction + images.length) % images.length,
  };
};

/** Mark one card image after it has rendered so only that image skeleton disappears. */
const markHotelImageLoaded = (hotelItem) => {
  const key = hotelImageKey(hotelItem);
  loadedHotelImageKeys.value = new Set([...loadedHotelImageKeys.value, key]);
};

/** Stop a broken provider image skeleton and show the hotel placeholder instead. */
const markHotelImageFailed = (hotelItem) => {
  const key = hotelImageKey(hotelItem);
  failedHotelImageKeys.value = new Set([...failedHotelImageKeys.value, key]);
};

const isHotelImageLoaded = (hotelItem) => loadedHotelImageKeys.value.has(hotelImageKey(hotelItem));
const isHotelImageFailed = (hotelItem) => failedHotelImageKeys.value.has(hotelImageKey(hotelItem));

/** Open the selected property's static details within this trusted search session. */
const viewHotelDetails = (hotelItem) => {
  if (!searchSessionId.value || !hotelItem?.hotel_code) {
    return;
  }

  router.push({
    name: "HotelDetails",
    query: {
      search_session_id: searchSessionId.value,
      hotel_code: hotelItem.hotel_code,
    },
  });
};

const parseRoomsQuery = (roomsQuery) => {
  if (!roomsQuery) {
    return null;
  }

  try {
    const parsed = typeof roomsQuery === "string" ? JSON.parse(roomsQuery) : roomsQuery;

    if (!Array.isArray(parsed) || !parsed.length) {
      return null;
    }

    return parsed.map((room) => {
      const childrenAges = Array.isArray(room.children_ages) ? room.children_ages.map((age) => Number(age)) : [];
      const fallbackCount = Number(room.children || 0);

      return {
        adults: Number(room.adults || 1),
        children_ages: childrenAges.length ? childrenAges : Array.from({ length: fallbackCount }, () => 0),
      };
    });
  } catch {
    return null;
  }
};

const loadSearchFromRoute = async (query = route.query) => {
  const destinationType = query.destination_type;
  const destinationValue = query.destination_value;

  if (!destinationType || !destinationValue) {
    return;
  }

  const destinationLabel = query.destination_label || String(destinationValue);
  selectedDestination.value = {
    type: String(destinationType),
    value: String(destinationValue),
    label: String(destinationLabel),
  };
  destinationQuery.value = String(destinationLabel);
  checkIn.value = query.check_in ? String(query.check_in) : checkIn.value;
  checkOut.value = query.check_out ? String(query.check_out) : checkOut.value;
  guestNationality.value = query.guest_nationality ? String(query.guest_nationality).toLowerCase() : guestNationality.value;

  const parsedRooms = parseRoomsQuery(query.rooms);
  if (parsedRooms) {
    rooms.value = parsedRooms;
  }

  await searchHotels();
};

watch(
  () => route.fullPath,
  (fullPath, previousFullPath) => {
    if (fullPath === previousFullPath) {
      return;
    }

    loadSearchFromRoute({ ...route.query });
  },
  { immediate: true },
);
</script>

<template>
  <div :class="props.embedded ? 'bg-transparent' : 'min-h-screen bg-slate-100/75 text-foreground'">
    <section
      :class="[
        'relative isolate overflow-visible border-b border-slate-200/90',
        props.embedded
          ? 'bg-transparent border-none'
          : 'hotel-results-search sticky top-0 z-30 py-4 bg-white/95 backdrop-blur-md shadow-xs',
      ]"
    >
      <div :class="props.embedded ? 'relative mx-auto max-w-[1240px] px-0 py-0' : 'relative mx-auto max-w-[1240px] px-4'">
        <HotelSearchPanel
          v-model:destination="selectedDestination"
          v-model:checkIn="checkIn"
          v-model:checkOut="checkOut"
          v-model:rooms="rooms"
          v-model:nationality="guestNationality"
          :is-loading="isSearching"
          :embedded="props.embedded"
          @search="searchHotels"
        />
      </div>
    </section>

    <section :class="props.embedded ? 'mx-auto max-w-[1240px] px-0 pt-4 pb-0' : 'mx-auto max-w-[1240px] px-4 py-6'">
      
      <!-- ERROR STATE -->
      <div
        v-if="errorMessage"
        class="mb-6 rounded border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-bold">Unable to complete hotel search</p>
            <p class="mt-0.5 text-xs text-destructive/90">{{ errorMessage }}</p>
            <p v-if="providerStatus?.Description" class="mt-1 text-[11px] opacity-75">
              Provider notice: {{ providerStatus.Description }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 rounded bg-destructive px-3 py-1.5 text-xs font-bold text-white hover:bg-destructive/90"
            @click="searchHotels"
          >
            Try again
          </button>
        </div>
      </div>

      <!-- SKELETON LOADERS (WHEN SEARCHING) -->
      <div v-if="isSearching" class="space-y-4">
        <div class="flex items-center justify-between border-b border-border pb-3">
          <div class="space-y-1">
            <div class="h-6 w-48 animate-pulse rounded bg-muted" />
            <div class="h-3.5 w-64 animate-pulse rounded bg-muted" />
          </div>
          <div class="h-9 w-32 animate-pulse rounded bg-muted" />
        </div>

        <div class="grid gap-6 lg:grid-cols-[288px_minmax(0,1fr)]">
          <div class="hidden lg:block">
            <div class="h-96 w-full animate-pulse rounded border border-border bg-card p-4">
              <div class="space-y-4">
                <div class="h-4 w-28 bg-muted rounded" />
                <div class="h-10 w-full bg-muted rounded" />
                <div class="h-16 w-full bg-muted rounded" />
                <div class="h-24 w-full bg-muted rounded" />
              </div>
            </div>
          </div>
          <div class="space-y-4">
            <HotelCardSkeleton v-for="n in 4" :key="n" />
          </div>
        </div>
      </div>

      <!-- SEARCH RESULTS -->
      <div v-else-if="hotelResults.length" class="space-y-4">
        <!-- TOP BAR: Result count, active filter chips, sort dropdown, and mobile filter trigger -->
        <HotelResultsTopBar
          :total-results="hotelResults.length"
          :filtered-results="filteredHotelResults.length"
          :destination-label="selectedDestination?.label"
          :check-in="formatDisplayDate(checkIn)"
          :check-out="formatDisplayDate(checkOut)"
          :nights="nights"
          :guests-summary="guestsSummary"
          :active-filters-count="activeFiltersCount"
          :price-limit="hotelPriceLimit"
          :max-price="maximumHotelPrice"
          :currency-code="getSelectedCurrencyCode()"
          :selected-ratings="selectedRatings"
          :selected-review-score="selectedReviewScore"
          :selected-amenities="selectedAmenities"
          :free-cancellation-only="freeCancellationOnly"
          v-model:sort="currentSort"
          @open-mobile-filter="isMobileFilterOpen = true"
          @remove-price-limit="removePriceLimit"
          @remove-rating="removeRating"
          @remove-review-score="removeReviewScore"
          @remove-amenity="removeAmenity"
          @remove-free-cancellation="removeFreeCancellation"
          @clear-all="clearAllFilters"
        />

        <!-- MAIN LAYOUT: Left sidebar filters + Right hotel cards -->
        <div class="grid gap-6 lg:grid-cols-[288px_minmax(0,1fr)] items-start">
          
          <!-- LEFT SIDEBAR FILTERS (DESKTOP + MOBILE DRAWER) -->
          <HotelFiltersSidebar
            v-model:price-limit="hotelPriceLimit"
            v-model:selected-ratings="selectedRatings"
            v-model:selected-review-score="selectedReviewScore"
            v-model:selected-amenities="selectedAmenities"
            v-model:free-cancellation-only="freeCancellationOnly"
            :max-price="maximumHotelPrice"
            :currency-code="getSelectedCurrencyCode()"
            :is-mobile-open="isMobileFilterOpen"
            :total-count="filteredHotelResults.length"
            @clear-all="clearAllFilters"
            @close-mobile="isMobileFilterOpen = false"
          />

          <!-- RIGHT LIST: HOTEL CARDS OR EMPTY FILTER STATE -->
          <div class="min-w-0">
            <!-- HOTEL CARDS LIST -->
            <div v-if="sortedFilteredHotelResults.length" class="space-y-4">
              <HotelCard
                v-for="hotelItem in sortedFilteredHotelResults"
                :key="hotelItem.hotel_code"
                :hotel="hotelItem"
                :nights="nights"
                :guests-summary="guestsSummary"
                :is-expanded="isHotelExpanded(hotelItem)"
                :selected-booking-code="selectedBookingCode"
                :room-choice="roomChoiceForHotel(hotelItem)"
                :is-room-details-loading="isRoomDetailsLoading(hotelItem)"
                :room-details-map="roomDetailsByHotel[String(hotelItem.hotel_code)] || {}"
                :property-images="propertyImagesByHotel[String(hotelItem.hotel_code)] || []"
                @view-deal="viewHotelDetails(hotelItem)"
                @toggle-rooms="toggleHotelRooms(hotelItem)"
                @choose-room="(h, r) => chooseRoom(h, r)"
                @select-room="(h, r) => { chooseRoom(h, r); selectChosenRoom(h); }"
              />
            </div>

            <!-- EMPTY FILTER STATE (Hotels exist, but filters matched 0) -->
            <div
              v-else
              class="rounded border border-slate-200 bg-white p-10 text-center shadow-sm"
            >
              <SlidersHorizontal class="mx-auto mb-3 h-9 w-9 text-primary" />
              <h3 class="text-base font-bold text-slate-900">No properties match your filters</h3>
              <p class="mt-1 text-xs text-muted-foreground max-w-sm mx-auto">
                Try widening your price range, choosing different star ratings, or clearing some amenities.
              </p>
              <div class="mt-4">
                <button
                  type="button"
                  class="rounded bg-primary px-5 py-2 text-xs font-bold text-white shadow-xs hover:bg-primary/95 transition-colors"
                  @click="clearAllFilters"
                >
                  Clear all filters
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- EMPTY STATE (NO SEARCH RESULTS YET / EMPTY SEARCH) -->
      <div
        v-else-if="!props.embedded"
        class="rounded border border-slate-200 bg-white px-6 py-12 text-center shadow-sm max-w-lg mx-auto mt-6"
      >
        <Hotel class="mx-auto mb-3 h-10 w-10 text-primary" />
        <h3 class="text-base font-bold text-slate-900">No hotel results found</h3>
        <p class="mt-1 text-xs text-muted-foreground">
          Enter a destination city or hotel above and choose dates to view available stays.
        </p>
      </div>

    </section>
  </div>
</template>

<style scoped>
@media (min-width: 1024px) {
  .hotel-results-search__form {
    position: absolute;
    top: 50%;
    right: 1rem;
    left: 1rem;
    transform: translateY(-50%);
  }
}
</style>
