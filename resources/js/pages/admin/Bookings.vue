<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import Spinner from "@/components/common/Spinner.vue";

import {
    CalendarIcon,
    CheckCircleIcon,
    Receipt,
    MoveRight,
    CircleChevronRight,
    ChevronRight
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
import { computed, onMounted, ref } from "vue";
import {
    FETCH_BOOKINGS,
    FETCH_TRANSACTIONS,
    FETCH_BOOKING_DATA
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { useAuthStore } from "@/services/stores/auth";
import DateRangePicker from "@/components/common/DateRangePicker.vue";
import { date } from "zod";
import flight from "@/services/store/flight";

const store = useStore();
const authStore = useAuthStore();
const searchQuery = ref();
const dateRange = ref({
    start: null,
    end: null,
});
const bookings = computed(() => store.getters["flight/bookingData"]);
const isLoading = computed(() => store.getters["flight/isLoading"]);
const transactions = computed(() => store.getters["transaction/transactions"]);
const user = computed(() => authStore.user);
const emit = defineEmits(['filter-change']);
const activeFilter = ref('all');

function filterBookings(type) {
    activeFilter.value = type;
    //console.log("Filter changed to:", type);
    fetchBookings();
}

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


function fetchBookings() {
    console.log("Fetching bookings with filter:");
    store.dispatch("flight/" + FETCH_BOOKING_DATA, {
        user_role: user.value.role,
        bookingFilter: activeFilter.value,
        searchQuery: searchQuery.value,
        dateRange: dateRange.value
    });
}

onMounted(() => {
    fetchBookings();
});
</script>

<template>
    <section>
        <div>
            <div class="bg-gradient-to-br from-gray-100 to-gray-200 mb-4">
                <div class="max-w-full mx-auto">
                    <div class="bg-white rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                                Bookings Overview
                            </h1>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <!-- Total Bookings -->
                                <div class="bg-blue-100 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-blue-500': activeFilter === 'all' }"
                                    @click="filterBookings('all')">
                                    <CalendarIcon class="h-8 w-8 text-blue-600 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-blue-700">
                                            All Bookings
                                        </p>
                                        <p class="text-2xl font-bold text-blue-900">
                                            {{ bookings?.total_count || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Ticketed -->
                                <div class="bg-green-100 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-green-500': activeFilter === 'ticketed' }"
                                    @click="filterBookings('ticketed')">
                                    <CheckCircleIcon class="h-8 w-8 text-green-600 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-green-700">
                                            Ticketed
                                        </p>
                                        <p class="text-2xl font-bold text-green-900">
                                            {{ bookings?.total_ticketed || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Total Canceled -->
                                <div class="bg-red-100 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-red-500': activeFilter === 'canceled' }"
                                    @click="filterBookings('canceled')">
                                    <Ban class="h-8 w-8 text-red-600 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-red-700">
                                            Total Canceled
                                        </p>
                                        <p class="text-2xl font-bold text-red-900">
                                            {{ bookings?.total_canceled || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- On Hold -->
                                <div class="bg-yellow-100 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-yellow-500': activeFilter === 'booked' }"
                                    @click="filterBookings('booked')">
                                    <CirclePause class="h-8 w-8 text-yellow-600 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-yellow-700">
                                            On Hold
                                        </p>
                                        <p class="text-2xl font-bold text-yellow-900">
                                            {{ bookings?.total_booked || 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-1/2 mb-2 ">
                <div class="flex items-center gap-2">
                    <label for="simple-search" class="sr-only">Search</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor"
                                viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd"
                                    d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                    clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input @change="fetchBookings()" v-model="searchQuery" type="text" id="simple-search" class="border bg-white h-10 border-gray-300 text-gray-900 text-sm rounded-lg 
         focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 
         dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 
         dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500" placeholder="Search" />

                    </div>
                    <DateRangePicker @change="fetchBookings()" heading="Select Date Range" v-model="dateRange" />

                    <Button class="h-10 text-white" @click="fetchBookings">Search</Button>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <div v-if="isLoading" class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                        <Spinner color="#805300" />
                    </div>
                    <table v-if="bookings?.bookings?.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Booking Date</th>
                                <th scope="col" class="px-4 py-3">Booking ID</th>
                                <th scope="col" class="px-4 py-3">PNR</th>
                                <th scope="col" class="px-4 py-3">Agent Email</th>
                                <th scope="col" class="px-4 py-3">Supplier</th>
                                <th scope="col" class="px-4 py-3">Source</th>
                                <th scope="col" class="px-4 py-3">Route</th>
                                <th scope="col" class="px-4 py-3">Passenger Name</th>
                                <th scope="col" class="px-4 py-3">Travel Date</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookings?.bookings" :key="booking.id"
                                class="border-b dark:border-gray-700">
                                <td class="px-4 py-3">
                                    {{ moment(booking.created_at).format("DD-MMM-YYYY HH:mm") }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking?.user?.agent_data?.agent_uid }}_{{ booking.id + 1000 }}</td>
                                <td class="px-4 py-3">
                                    {{ booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr }}
                                </td>
                                <td class="px-4 py-3">{{ booking.agency_email }}</td>
                                <td class="px-4 py-3">
                                    <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                        :key="flightIndex" class="flex gap-2 justify-start items-center">

                                        <span class="relative group">
                                            <img :src="flight?.marketing_carrier?.logo" alt="Carrier Logo"
                                                class="h-6 w-6" />
                                            <span v-if="flight?.marketing_carrier?.name"
                                                class="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 px-2 py-1 rounded bg-primary text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-10">
                                                {{ flight?.marketing_carrier?.name }}
                                            </span>
                                        </span>
                                    </div>

                                </td>
                                <td class="px-4 py-3">
                                    {{ parseFlightData(booking?.flight_data)?.provider?.source?.toUpperCase() || 'N/A' }}

                                </td>

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
                                                    {{ passenger.title }} {{ passenger.first_name }}
                                                    {{ passenger.last_name }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td px-4 py-3>
                                    <div v-if="booking?.booking_source == 1">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                            :key="flightIndex">
                                            <p class="font-semibold">{{ formatDate(flight?.departure_at) }}</p>
                                        </div>
                                    </div>
                                    <div v-else>
                                        <p class="font-semibold"
                                            v-for="(date, dateIndex) in parseFlightData(booking.flight_data,).dates"
                                            :key="dateIndex">
                                            {{ formatDate(date.departureDate) }}
                                            <!-- {{ date.departureLocation }} to
                                    {{ date.arrivalLocation }} -->
                                        </p>
                                    </div>

                                </td>
                                <td class="py-2 px-4">
                                    {{ booking.status?.toUpperCase() }}{{ booking.is_manually_issued ? ' (manually issued)' : '' }}
                                </td>
                                <td class="px-1 py-4">
                                    <div class="flex space-x-2">
                                        <Button size="sm" variant="outline" @click="$router.push({
                                            name: 'AdminBookingsDetails',
                                            query: {
                                                booking_id: booking.id,
                                                pnr: booking?.itinerary_reference ??
                                                    booking?.itinerary_ref ??
                                                    booking.pnr,
                                                agent_id: booking.agent_id,
                                                booking_source: booking.booking_source,
                                                flight_provider: parseFlightData(booking?.flight_data)?.provider
                                                    ?.name || 'N/A',
                                                flight_mode:  'B2B',

                                            },
                                        })" class="flex gap-2 text-primary hover:text-primary">
                                            Details
                                            <ChevronRight class="w-5 h-5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else-if="!isLoading" class="flex flex-col items-center justify-center py-12 px-4 w-full">
                        <img class="h-64 md:h-80 max-w-md w-full object-contain mx-auto"
                            src="/public/assets/No data.png" alt="No data available">
                        <p
                            class="text-gray-600 text-lg mt-6 text-center font-medium bg-gray-50 px-6 py-3 rounded-full shadow-sm border border-gray-100 max-w-md mx-auto">
                            <span class="inline-block mr-2">🔍</span>
                            No bookings found for the selected filter
                            <span class="block text-sm text-gray-400 mt-1 font-normal">
                                Try selecting a different category
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
