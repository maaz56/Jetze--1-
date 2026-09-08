<script setup>
import Button from "@/components/ui/button/Button.vue";
import apiService from "@/services/store/apiService";
import html2pdf from "html2pdf.js";
import { Download, Printer, RefreshCw, X } from "lucide-vue-next";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

const props = defineProps({
  bookingId: { type: String, required: true },
});

const router = useRouter();
const booking = ref(null);
const isLoading = ref(true);
const isRefreshing = ref(false);
const isCancelling = ref(false);
const showCancelDialog = ref(false);
const errorMessage = ref("");
const printArea = ref(null);

const guestsByRoom = computed(() => (booking.value?.guests || []).reduce((rooms, guest) => {
  const room = Number(guest.room_index || 0);
  rooms[room] = rooms[room] || [];
  rooms[room].push(guest);
  return rooms;
}, {}));
const canCancel = computed(() => booking.value?.status === "confirmed" && Boolean(booking.value?.confirmation_number));

const loadBooking = async () => {
  isLoading.value = true;
  errorMessage.value = "";

  try {
    const response = await apiService.getAdminHotelBooking(props.bookingId);
    booking.value = response.data.data || null;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to load this hotel booking.";
  } finally {
    isLoading.value = false;
  }
};

const refreshDetails = async () => {
  isRefreshing.value = true;
  errorMessage.value = "";
  try {
    await apiService.refreshAdminHotelBookingDetails(props.bookingId);
    await loadBooking();
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to refresh provider details.";
  } finally {
    isRefreshing.value = false;
  }
};

const cancelBooking = async () => {
  isCancelling.value = true;
  errorMessage.value = "";
  try {
    await apiService.cancelAdminHotelBooking(props.bookingId);
    await loadBooking();
    showCancelDialog.value = false;
  } catch (error) {
    errorMessage.value = error.response?.data?.message || "Unable to cancel this booking.";
  } finally {
    isCancelling.value = false;
  }
};

const formatMoney = (money) => {
  if (money?.amount === null || money?.amount === undefined || !money?.currency) return "—";
  return `${money.currency} ${Number(money.amount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
};

const formatPolicy = (policy) => `From ${policy?.FromDate || "the stated date"}: ${policy?.CancellationCharge ?? "—"}${policy?.ChargeType ? ` (${policy.ChargeType})` : ""}`;
const printBooking = () => window.print();
const downloadPdf = () => {
  if (!printArea.value || !booking.value) return;
  html2pdf().set({ margin: 8, filename: `hotel-booking-${booking.value.booking_reference_id}.pdf`, image: { type: "jpeg", quality: 0.98 }, html2canvas: { scale: 2 }, jsPDF: { unit: "mm", format: "a4", orientation: "portrait" } }).from(printArea.value).save();
};

onMounted(loadBooking);
</script>

<template>
  <section class="mx-auto max-w-6xl px-4 py-7 sm:px-6">
    <div class="print:hidden mb-5 flex flex-wrap items-center justify-between gap-3">
      <Button class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" @click="router.push({ name: 'AdminHotelBookings' })">Back to hotel bookings</Button>
      <div class="flex flex-wrap gap-2">
        <Button class="rounded bg-gray-700 px-4 py-2 text-sm font-semibold text-white" @click="printBooking"><Printer class="mr-2 h-4 w-4" />Print</Button>
        <Button class="rounded bg-primary px-4 py-2 text-sm font-semibold text-white" @click="downloadPdf"><Download class="mr-2 h-4 w-4" />Download PDF</Button>
        <Button class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canCancel" @click="showCancelDialog = true"><X class="mr-2 h-4 w-4" />Cancel booking</Button>
      </div>
    </div>

    <div v-if="errorMessage" class="print:hidden mb-5 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ errorMessage }}</div>
    <div v-if="isLoading" class="rounded border border-gray-200 bg-white p-7 text-sm text-gray-500">Loading booking details...</div>

    <div v-else-if="booking" ref="printArea" class="space-y-5 bg-white text-gray-900">
      <header class="rounded-lg border border-gray-200 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wide text-primary">Hotel booking voucher</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ booking.hotel?.name || "Hotel booking" }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ [booking.hotel?.city, booking.hotel?.country].filter(Boolean).join(", ") || "Location unavailable" }}</p>
          </div>
          <span class="inline-flex w-fit rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold capitalize text-gray-700">{{ booking.status?.replaceAll("_", " ") }}</span>
        </div>
      </header>

      <div class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-lg border border-gray-200 p-5">
          <h2 class="font-semibold">Booking and customer</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Booking reference</dt><dd class="mt-1 font-medium">{{ booking.booking_reference_id }}</dd></div>
            <div><dt class="text-gray-500">TBO confirmation</dt><dd class="mt-1 font-medium">{{ booking.confirmation_number || "Pending" }}</dd></div>
            <div><dt class="text-gray-500">Hotel confirmation</dt><dd class="mt-1 font-medium">{{ booking.hotel_confirmation_number || "Pending" }}</dd></div>
            <div><dt class="text-gray-500">Booked on</dt><dd class="mt-1 font-medium">{{ booking.created_at ? new Date(booking.created_at).toLocaleString() : "—" }}</dd></div>
            <div><dt class="text-gray-500">Customer</dt><dd class="mt-1 font-medium">{{ booking.customer?.name || "Guest booking" }}</dd></div>
            <div><dt class="text-gray-500">Contact</dt><dd class="mt-1 break-all font-medium">{{ booking.email || booking.customer?.email || "—" }}<br />{{ booking.phone_number || "" }}</dd></div>
          </dl>
        </article>

        <article class="rounded-lg border border-gray-200 p-5">
          <h2 class="font-semibold">Stay and price</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Check-in</dt><dd class="mt-1 font-medium">{{ booking.check_in || "—" }}</dd></div>
            <div><dt class="text-gray-500">Check-out</dt><dd class="mt-1 font-medium">{{ booking.check_out || "—" }}</dd></div>
            <div><dt class="text-gray-500">Final total</dt><dd class="mt-1 text-base font-semibold">{{ formatMoney(booking.price_snapshot?.selling_money || { amount: booking.total_fare, currency: booking.currency }) }}</dd></div>
            <div><dt class="text-gray-500">Provider fare</dt><dd class="mt-1 font-medium">{{ formatMoney(booking.price_snapshot?.provider_money || { amount: booking.total_fare, currency: booking.currency }) }}</dd></div>
          </dl>
        </article>
      </div>

      <article v-if="booking.guests?.length" class="rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold">Guests</h2>
        <div v-for="(guests, roomIndex) in guestsByRoom" :key="roomIndex" class="mt-4 border-t border-gray-100 pt-4 first:mt-3 first:border-t-0 first:pt-0">
          <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Room {{ Number(roomIndex) + 1 }}</p>
          <ul class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
            <li v-for="guest in guests" :key="`${guest.room_index}-${guest.guest_index}`" class="rounded bg-gray-50 px-3 py-2">{{ guest.title }} {{ guest.first_name }} {{ guest.last_name }} <span class="text-gray-500">· {{ guest.type }}</span></li>
          </ul>
        </div>
      </article>

      <article class="rounded-lg border border-gray-200 p-5">
        <div class="print:hidden flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">Provider details</h2><Button class="rounded bg-primary px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary/90" :is-loading="isRefreshing" @click="refreshDetails"><RefreshCw class="mr-2 h-4 w-4" />Refresh provider details</Button></div>
        <h2 class="hidden print:block font-semibold">Provider details</h2>
        <p class="mt-3 text-sm text-gray-600">Voucher status: <strong>{{ booking.voucher_status || "Pending" }}</strong>. Last checked: {{ booking.booking_details_checked_at ? new Date(booking.booking_details_checked_at).toLocaleString() : "Not yet checked" }}.</p>
        <div class="mt-4"><h3 class="text-sm font-semibold">Cancellation policy</h3><ul v-if="booking.cancellation_policies?.length" class="mt-2 space-y-1 text-sm text-gray-600"><li v-for="(policy, index) in booking.cancellation_policies" :key="index">{{ formatPolicy(policy) }}</li></ul><p v-else class="mt-2 text-sm text-gray-500">No cancellation policy is available from TBO yet.</p></div>
      </article>

      <article v-if="booking.events?.length" class="rounded-lg border border-gray-200 p-5">
        <h2 class="font-semibold">Activity timeline</h2>
        <ol class="mt-3 space-y-2 text-sm"><li v-for="(event, index) in booking.events" :key="index" class="flex flex-wrap justify-between gap-2 rounded bg-gray-50 px-3 py-2"><span class="font-medium capitalize">{{ event.stage.replaceAll("_", " ") }}</span><span class="text-gray-500">{{ event.provider_reference || "—" }} · {{ event.occurred_at ? new Date(event.occurred_at).toLocaleString() : "—" }}</span></li></ol>
      </article>
    </div>

    <div v-if="showCancelDialog" class="print:hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCancelDialog = false">
      <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"><h2 class="text-lg font-semibold">Cancel hotel booking?</h2><p class="mt-2 text-sm text-gray-600">TBO cancellation policy will apply. This action cannot be undone.</p><div class="mt-6 flex justify-end gap-3"><Button class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" :disabled="isCancelling" @click="showCancelDialog = false">Keep booking</Button><Button class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white" :is-loading="isCancelling" @click="cancelBooking">Confirm cancellation</Button></div></div>
    </div>
  </section>
</template>

<style scoped>
@media print {
  @page { margin: 12mm; }
  section { max-width: none; padding: 0; }
}
</style>
