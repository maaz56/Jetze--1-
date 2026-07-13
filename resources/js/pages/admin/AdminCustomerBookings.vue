<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import {
    CalendarIcon,
    CheckCircleIcon,
    Receipt,
    MoveRight,
    CircleChevronRight,
    Ban,
    CirclePause,
    CircleX,
} from "lucide-vue-next";

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";

import { calculateLayover, formatDuration } from "@/lib/utils";

import { useStore } from "vuex";
import { computed, onMounted, onUnmounted, ref } from "vue";
import {
    FETCH_BOOKINGS,
    FETCH_TRANSACTIONS,
    FETCH_BOOKING_DATA,
    CUSTOMER_BOOKINGS,
    APPROVE_BOOKING,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { useAuthStore } from "@/services/stores/auth";
import { useRoute } from "vue-router";

const store = useStore();
const route = useRoute();
const authStore = useAuthStore();

const bookings = computed(() => store.getters["flight/customerBookings"]);

const transactions = computed(() => store.getters["transaction/transactions"]);

const user = computed(() => authStore.user);
const emit = defineEmits(['filter-change'])
const activeFilter = ref('all');
const now = ref(Date.now());
let countdownInterval;
const isStatusDialogOpen = ref(false);
const statusForm = ref({
    booking_id: null,
    status: "booked",
});
const statusOptions = [
    "booked",
    // "pending",
    // "approved",
    // "confirmed",
    "issued",
    "ticketed",
    "requested",
    "voided",
    "canceled",
    // "rejected",
];

function filterBookings(type) {
    activeFilter.value = type
    //console.log("Filter changed to:", type);
    fetchBookings();


}

// function fetchTransactions() {
//     store.dispatch("transaction/" + FETCH_TRANSACTIONS, {
//         user_id: user.id,
//     });
// }
const formatAmount = (amount) => {
    return new Intl.NumberFormat("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount || 0);
};
const parseFlightData = (flightDataString) => {
    try {
        return JSON.parse(flightDataString);
    } catch (error) {
        console.error("Error parsing flight data:", error);
        return null;
    }
};
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

const getRemainingTime = (expiry) => {
    if (!expiry) return 'N/A';

    const expiryTime = new Date(String(expiry).replace(' ', 'T')).getTime();
    const diff = expiryTime - now.value;
    if (Number.isNaN(expiryTime) || diff <= 0) return 'Expired';

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    let result = '';
    if (days > 0) result += `${days}d `;
    if (hours > 0 || days > 0) result += `${hours}h `;
    return `${result}${minutes}m ${seconds.toString().padStart(2, '0')}s`;
};
function fetchBookings() {
    const selectedUserId = route.query.user_id || route.query.userId;

    store.dispatch("flight/" + CUSTOMER_BOOKINGS, {
        user_id: selectedUserId,
        userId: selectedUserId,
        userRole: user.value.role,
        bookingFilter: activeFilter.value,
    });
}

function openStatusDialog(booking) {
    statusForm.value.booking_id = booking.id;
    statusForm.value.status = booking.status || "booked";
    isStatusDialogOpen.value = true;
}

function updateBookingStatus() {
    if (!statusForm.value.booking_id || !statusForm.value.status) {
        return;
    }
    store.dispatch("flight/" + APPROVE_BOOKING, {
        booking_id: statusForm.value.booking_id,
        status: statusForm.value.status,
    }).then(() => {
        isStatusDialogOpen.value = false;
        fetchBookings();
    });
}

onMounted(() => {
    countdownInterval = setInterval(() => {
        now.value = Date.now();
    }, 1000);
    fetchBookings();
});

onUnmounted(() => clearInterval(countdownInterval));
</script>

<template>
    <section>
        <div>
            <!-- <div class="flex items-center justify-between mb-4">
                <span class="text-3xl font-medium">Bookings</span>
                <span class="text-3xl font-medium">Total Approved Amount: {{ offlineBookings?.approved_amount}}</span>
                <span class="text-3xl font-medium">Total Approved: {{ offlineBookings?.approved_count }}</span>
            </div> -->

            <div class="bg-gradient-to-br from-gray-100 to-gray-200 mb-4 ">
                <div class="max-w-full mx-auto">
                    <div class="bg-white rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                                Bookings Overview
                            </h1>
                            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                                <!-- Total Bookings -->
                                <div class="bg-blue-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-blue-500': activeFilter === 'all' }"
                                    @click="filterBookings('all')">
                                    <CalendarIcon class="h-8 w-8 text-blue-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-blue-600">
                                            All Bookings
                                        </p>
                                        <p class="text-2xl font-bold text-blue-800">
                                            {{ bookings?.total_count || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Ticketed -->
                                <div class="bg-green-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-green-500': activeFilter === 'ticketed' }"
                                    @click="filterBookings('ticketed')">
                                    <CheckCircleIcon class="h-8 w-8 text-green-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-green-600">
                                            Ticketed
                                        </p>
                                        <p class="text-2xl font-bold text-green-800">
                                            {{ bookings?.total_ticketed || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Total Canceled -->
                                <div class="bg-red-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-red-500': activeFilter === 'canceled' }"
                                    @click="filterBookings('canceled')">
                                    <Ban class="h-8 w-8 text-red-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-red-600">
                                            Total Canceled
                                        </p>
                                        <p class="text-2xl font-bold text-red-800">
                                            {{ bookings?.total_canceled || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- On Hold -->
                                <div class="bg-yellow-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-yellow-500': activeFilter === 'booked' }"
                                    @click="filterBookings('booked')">
                                    <CirclePause class="h-8 w-8 text-yellow-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-yellow-600">
                                            On Hold
                                        </p>
                                        <p class="text-2xl font-bold text-yellow-800">
                                            {{ bookings?.total_booked || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <div class="bg-purple-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-purple-500': activeFilter === 'voided' }"
                                    @click="filterBookings('voided')">
                                    <CircleX class="h-8 w-8 text-purple-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-purple-600">Voided</p>
                                        <p class="text-2xl font-bold text-purple-800">
                                            {{ bookings?.total_voided || 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- <pre>{{ user_id }}</pre>
            <pre>{{ agentData }}</pre> -->
            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table v-if="bookings?.bookings?.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Booking Date
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Booking ID
                                </th>
                                <th scope="col" class="px-4 py-3">PNR</th>
                                <th scope="col" class="px-4 py-3">Supplier</th>
                                <th scope="col" class="px-4 py-3">Source</th>
                                <th scope="col" class="px-4 py-3">Customer Email</th>
                                <th scope="col" class="px-4 py-3">Route</th>
                                <th scope="col" class="px-4 py-3">
                                    Passenger Name
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Travel Date
                                </th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Expiry on</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookings?.bookings" :key="booking.id"
                                class="border-b dark:border-gray-700">

                                <td class="px-4 py-3">
                                    {{
                                        moment(booking.created_at).format(
                                            "MM-DD-YYYY hh:mm A",
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">{{ booking.id }}</td>
                                <td class="px-4 py-3">
                                    {{ booking.itinerary_ref ? booking?.itinerary_ref : booking.pnr }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ parseFlightData(booking?.flight_data)?.provider?.name.toUpperCase() || 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ parseFlightData(booking?.flight_data)?.provider?.contentSource?.toUpperCase() || 'N/A' }}
                                </td>
                                <td class="px-4 py-3">{{ booking.agency_email }}</td>
                                <td px-4 py-3>
                                    <div v-if="booking?.booking_source == 1">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                            :key="flightIndex" class="flex gap-2 justify-start items-center">
                                            <div class="flex gap-2">
                                                <p>
                                                    {{ flight?.from?.iata }}
                                                </p>
                                                <p>To</p>
                                                <p>

                                                    {{ flight?.to?.iata }}
                                                </p>
                                            </div>

                                        </div>
                                    </div>
                                    <div v-else>
                                        <div v-if="booking.flight_data" class="">
                                            <div v-for="(leg, legIndex
                                            ) in parseFlightData(booking.flight_data,).legs" :key="legIndex" class="">
                                                <div v-for="(stop, stopIndex
                                                ) in leg.stops" :key="stopIndex" class="">
                                                    <div class="flex gap-2 justify-start items-center">
                                                        <!-- Departure -->
                                                        <div class=" ">
                                                            <div class="">
                                                                {{
                                                                    stop.departure
                                                                        .airport
                                                                        .city_name
                                                                }}

                                                                ({{
                                                                    stop.departure
                                                                        .airport
                                                                        .iata_code
                                                                }})
                                                            </div>
                                                        </div>
                                                        To
                                                        <!--     Arrival -->
                                                        <div class="">
                                                            <div class="">
                                                                {{
                                                                    stop.arrival
                                                                        .airport
                                                                        .city_name
                                                                }}
                                                                ({{
                                                                    stop.arrival
                                                                        .airport
                                                                        .iata_code
                                                                }})
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- <div
                                                    v-if="
                                                        stopIndex <
                                                        leg.stops.length - 1
                                                    "
                                                    class=" text-center text-gray-600"
                                                >
                                                    ↓ Connection ↓
                                                </div> -->
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <div v-for="passenger in booking.pessangers" :key="passenger.id" class="mb-2">
                                        <div class="flex">
                                            <div>
                                                <p>
                                                    {{ passenger.title }}
                                                    {{ passenger.first_name }}
                                                    {{ passenger.last_name }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td px-4 py-3>
                                    <div v-if="booking?.booking_source == 1">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights" :key="flightIndex">
                                            <p class="font-semibold">{{ formatDate(flight?.departure_at) }}</p>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p class="font-semibold" v-for="(date, dateIndex
                                        ) in parseFlightData(booking.flight_data,).dates" :key="dateIndex">
                                        {{ formatDate(date.departureDate) }}
                                        <!-- {{ date.departureLocation }} to
                                    {{ date.arrivalLocation }} -->
                                    </p>
                                    </div>
                                </td>
                                <td class="py-2 px-4">
                                    {{ booking.status?.toUpperCase() }}{{ booking.is_manually_issued ? ' (manually issued)' : '' }}
                                </td>
                                <td class="py-2 px-4 whitespace-nowrap">
                                    <span v-if="booking?.status?.toLowerCase() === 'booked'"
                                        class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-300">
                                        {{ getRemainingTime(booking.expiry_time) }}
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 border border-gray-200">
                                        N/A
                                    </span>
                                </td>
                                <td class="px-1 py-4">
                                    <div class="flex space-x-2">
                                        <div class="flex space-x-2">
                                            <button
                                                @click="openStatusDialog(booking)"
                                                class="text-xs font-medium px-2 py-1 rounded border border-primary text-primary hover:bg-primary hover:text-white transition">
                                                Update Status
                                            </button>
                                            <button @click="
                                                $router.push({
                                                    name: 'AdminCustomerBookingsLayout',
                                                    query: {
                                                        booking_id:
                                                            booking.id,
                                                        pnr: booking?.itinerary_ref ?
                                                            booking?.itinerary_ref
                                                            : booking.pnr,
                                                        agent_id: booking.agent_id,
                                                        flight_mode: 'B2C',
                                                        booking_source:
                                                            booking?.booking_source,
                                                        flight_provider:
                                                            booking?.flight_provider,
                                                    },
                                                })
                                                " class="text-primary hover:text-purple-800">
                                                <CircleChevronRight class="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="flex flex-col items-center justify-center py-12 px-4 w-full ">
                        <img class="h-64 md:h-80 max-w-md w-full object-contain mx-auto"
                            src="/public/assets/No data.png" alt="No data available">
                        <p
                            class="text-gray-600 text-lg mt-6 text-center font-medium bg-gray-50 px-6 py-3 rounded-full shadow-sm border border-gray-100 max-w-md mx-auto">
                            <span class="inline-block mr-2">🔍</span>
                            No bookings found for the selected filter
                            <span class="block text-sm text-gray-400 mt-1 font-normal">Try selecting a different
                                category</span>

                        </p>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:open="isStatusDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Update Booking Status</DialogTitle>
                    <DialogDescription>
                        Select a new status and save changes for this booking.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-2">
                    <div class="w-64">
  <label class="block text-sm font-medium text-gray-700 mb-2">
    Status
  </label>

  <div class="relative">
    <select
      v-model="statusForm.status"
      class="w-full appearance-none rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-800 shadow-sm
             focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition"
    >
      <option disabled value="">Select status</option>
      <option
        v-for="status in statusOptions"
        :key="status"
        :value="status"
      >
        {{ status }}
      </option>
    </select>

    <!-- Custom arrow -->
    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400 text-xs">
      ▼
    </div>
  </div>
</div>
                </div>
                <DialogFooter>
                    <button @click="isStatusDialogOpen = false"
                        class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button @click="updateBookingStatus"
                        class="px-4 py-2 bg-primary text-white rounded-md text-sm hover:bg-primary/90">
                        Save
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
