<script setup>
import Button from "@/components/ui/button/Button.vue";
import apiService from "@/services/store/apiService";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Search, Building2, Eye, Calendar, ArrowRight } from "lucide-vue-next";

const props = defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
});

const router = useRouter();
const bookings = ref([]);
const meta = ref(null);
const search = ref("");
const status = ref("all");
const isLoading = ref(true);
const errorMessage = ref("");

const statuses = ["all", "processing", "confirmed", "cancellation_pending", "cancelled", "failed", "unknown"];

const loadBookings = async (page = 1) => {
  isLoading.value = true;
  errorMessage.value = "";

  try {
    const response = await apiService.getAdminHotelBookings({
      page,
      q: search.value.trim() || undefined,
      status: status.value,
    });
    bookings.value = response.data.data || [];
    meta.value = response.data.meta || null;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load hotel bookings.";
  } finally {
    isLoading.value = false;
  }
};

const totalLabel = computed(() => meta.value?.total ?? 0);

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || !money?.currency) return "—";

  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  })}`;
};

const statusClass = (value) => ({
  confirmed: "bg-emerald-50 text-emerald-700 border border-emerald-200",
  cancelled: "bg-slate-100 text-slate-700 border border-slate-200",
  failed: "bg-rose-50 text-rose-700 border border-rose-200",
  cancellation_pending: "bg-amber-50 text-amber-700 border border-amber-200",
  processing: "bg-primary/10 text-primary border border-primary/20",
}[value] || "bg-slate-100 text-slate-700 border border-slate-200");

const isUnseen = (booking) => !booking?.admin_seen_at;

function openBookingDetails(booking) {
  if (isUnseen(booking)) {
    booking.admin_seen_at = new Date().toISOString();
    apiService.markAdminSeen("hotel_bookings", booking.admin_booking_id).catch(() => {});
  }

  router.push({ name: "AdminHotelBookingDetails", params: { bookingId: booking.booking_id } });
}

onMounted(loadBookings);
</script>

<template>
  <section :class="props.embedded ? 'space-y-4' : 'mx-auto max-w-7xl px-4 py-6 sm:px-6 space-y-6'">
    <!-- Header / Filter bar -->
    <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-bold text-slate-900 sm:text-base">Hotel Bookings</h2>
            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200">
              {{ totalLabel }} total
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5">Manage hotel reservations, stays, and confirmation numbers</p>
        </div>

        <form class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center" @submit.prevent="loadBookings()">
          <div class="relative w-full sm:w-64">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
            <input
              v-model.trim="search"
              type="search"
              placeholder="Ref, confirmation, hotel or customer..."
              class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div>

          <select
            v-model="status"
            class="rounded border border-slate-200 bg-white py-1.5 px-3 text-xs font-medium text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            @change="loadBookings()"
          >
            <option v-for="item in statuses" :key="item" :value="item">
              {{ item === "all" ? "All statuses" : item.replaceAll("_", " ").toUpperCase() }}
            </option>
          </select>

          <button
            type="submit"
            class="inline-flex items-center justify-center rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90 transition cursor-pointer shadow-2xs"
          >
            Search
          </button>
        </form>
      </div>
    </div>

    <!-- Error message -->
    <div v-if="errorMessage" class="rounded border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-800">
      {{ errorMessage }}
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="rounded border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
      Loading hotel bookings...
    </div>

    <!-- Table Container -->
    <div v-else class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden">
      <div v-if="!bookings.length" class="p-12 text-center text-xs text-slate-500">
        <Building2 class="mx-auto h-8 w-8 text-slate-300 mb-2" />
        <p class="text-sm font-semibold text-slate-800">No hotel bookings found</p>
        <p class="text-slate-400 mt-1">No bookings match the selected filters.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left">
          <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
            <tr>
              <th scope="col" class="px-4 py-3">Booking Reference</th>
              <th scope="col" class="px-4 py-3">Customer</th>
              <th scope="col" class="px-4 py-3">Hotel / Stay</th>
              <th scope="col" class="px-4 py-3">Total Amount</th>
              <th scope="col" class="px-4 py-3">Status</th>
              <th scope="col" class="px-4 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white text-xs">
            <tr
              v-for="booking in bookings"
              :key="booking.booking_id"
              class="transition-colors hover:bg-slate-50/80"
              :class="{ 'row-unseen': isUnseen(booking) }"
            >
              <!-- Booking Reference -->
              <td class="whitespace-nowrap px-4 py-3">
                <p class="font-bold text-slate-900 font-mono">{{ booking.booking_reference_id }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">TBO: {{ booking.confirmation_number || "Pending" }}</p>
              </td>

              <!-- Customer -->
              <td class="whitespace-nowrap px-4 py-3">
                <p class="font-semibold text-slate-900">{{ booking.customer?.name || "Guest booking" }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ booking.customer?.email || booking.email || "—" }}</p>
              </td>

              <!-- Hotel / Stay -->
              <td class="whitespace-nowrap px-4 py-3">
                <p class="font-semibold text-slate-900">{{ booking.hotel?.name || "Hotel booking" }}</p>
                <p class="mt-0.5 text-[11px] text-slate-500">{{ booking.check_in || "—" }} → {{ booking.check_out || "—" }}</p>
              </td>

              <!-- Total Amount -->
              <td class="whitespace-nowrap px-4 py-3 font-bold text-slate-900">
                {{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}
              </td>

              <!-- Status -->
              <td class="whitespace-nowrap px-4 py-3">
                <span class="inline-flex rounded px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wider" :class="statusClass(booking.status)">
                  {{ booking.status?.replaceAll("_", " ") }}
                </span>
              </td>

              <!-- Action -->
              <td class="whitespace-nowrap px-4 py-3 text-right">
                <button
                  class="rounded bg-primary px-3 py-1 text-xs font-semibold text-white shadow-2xs hover:bg-primary/90 transition cursor-pointer"
                  @click="openBookingDetails(booking)"
                >
                  View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta?.last_page > 1" class="border-t border-slate-200 bg-slate-50/60 px-4 py-3 flex items-center justify-between text-xs">
        <button
          class="rounded border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 cursor-pointer"
          :disabled="isLoading || meta.current_page <= 1"
          @click="loadBookings(meta.current_page - 1)"
        >
          Previous
        </button>
        <span class="text-slate-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button
          class="rounded border border-slate-200 bg-white px-3 py-1 font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40 cursor-pointer"
          :disabled="isLoading || meta.current_page >= meta.last_page"
          @click="loadBookings(meta.current_page + 1)"
        >
          Next
        </button>
      </div>
    </div>
  </section>
</template>
