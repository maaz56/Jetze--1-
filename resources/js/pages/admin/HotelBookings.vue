<script setup>
import Button from "@/components/ui/button/Button.vue";
import apiService from "@/services/store/apiService";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

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
  confirmed: "bg-emerald-100 text-emerald-700",
  cancelled: "bg-slate-100 text-slate-700",
  failed: "bg-red-100 text-red-700",
  cancellation_pending: "bg-amber-100 text-amber-700",
  processing: "bg-blue-100 text-blue-700",
}[value] || "bg-gray-100 text-gray-700");

onMounted(loadBookings);
</script>

<template>
  <section class="mx-auto max-w-7xl px-4 py-7 sm:px-6">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Hotel inventory</p>
        <h1 class="mt-1 text-2xl font-semibold text-gray-950">Hotel bookings</h1>
        <p class="mt-1 text-sm text-gray-500">{{ totalLabel }} booking{{ totalLabel === 1 ? "" : "s" }} found.</p>
      </div>

      <form class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row" @submit.prevent="loadBookings()">
        <input v-model.trim="search" type="search" placeholder="Ref, confirmation, hotel or customer" class="h-10 min-w-64 rounded border border-gray-300 bg-white px-3 text-sm outline-none focus:border-primary" />
        <select v-model="status" class="h-10 rounded border border-gray-300 bg-white px-3 text-sm" @change="loadBookings()">
          <option v-for="item in statuses" :key="item" :value="item">{{ item === "all" ? "All statuses" : item.replaceAll("_", " ") }}</option>
        </select>
        <Button type="submit" class="h-10 rounded bg-primary px-4 text-sm font-semibold text-white">Search</Button>
      </form>
    </div>

    <div v-if="errorMessage" class="mb-5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ errorMessage }}</div>
    <div v-if="isLoading" class="rounded border border-gray-200 bg-white p-7 text-sm text-gray-500">Loading hotel bookings...</div>

    <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
      <div v-if="!bookings.length" class="p-10 text-center text-sm text-gray-500">No hotel bookings match these filters.</div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-5 py-3">Booking</th>
              <th class="px-5 py-3">Customer</th>
              <th class="px-5 py-3">Hotel / stay</th>
              <th class="px-5 py-3">Total</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="booking in bookings" :key="booking.booking_id" class="hover:bg-gray-50">
              <td class="px-5 py-4">
                <p class="font-semibold text-gray-900">{{ booking.booking_reference_id }}</p>
                <p class="mt-1 text-xs text-gray-500">TBO: {{ booking.confirmation_number || "Pending" }}</p>
              </td>
              <td class="px-5 py-4">
                <p class="font-medium text-gray-900">{{ booking.customer?.name || "Guest booking" }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ booking.customer?.email || booking.email || "—" }}</p>
              </td>
              <td class="px-5 py-4">
                <p class="font-medium text-gray-900">{{ booking.hotel?.name || "Hotel booking" }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ booking.check_in || "—" }} → {{ booking.check_out || "—" }}</p>
              </td>
              <td class="whitespace-nowrap px-5 py-4 font-medium text-gray-900">{{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</td>
              <td class="px-5 py-4"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusClass(booking.status)">{{ booking.status?.replaceAll("_", " ") }}</span></td>
              <td class="px-5 py-4 text-right">
                <Button class="rounded bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary/90" @click="router.push({ name: 'AdminHotelBookingDetails', params: { bookingId: booking.booking_id } })">View details</Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="meta?.last_page > 1" class="mt-5 flex items-center justify-between text-sm">
      <Button class="rounded border border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700" :disabled="isLoading || meta.current_page <= 1" @click="loadBookings(meta.current_page - 1)">Previous</Button>
      <span class="text-gray-500">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <Button class="rounded border border-gray-300 bg-white px-4 py-2 font-semibold text-gray-700" :disabled="isLoading || meta.current_page >= meta.last_page" @click="loadBookings(meta.current_page + 1)">Next</Button>
    </div>
  </section>
</template>
