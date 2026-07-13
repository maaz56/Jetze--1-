<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import {
    CalendarIcon,
    CheckCircleIcon,
    Receipt,
    MoveRight,
    CircleChevronRight,
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
    FETCH_BOOKING_DATA,
    FETCH_REQUESTS
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { useAuthStore } from "@/services/stores/auth";
import flight from "@/services/store/flight";

const store = useStore();
const authStore = useAuthStore();

const requests = computed(() => store.getters["modifyRequest/requests"]);

const transactions = computed(() => store.getters["transaction/transactions"]);

const user = computed(() => authStore.user);
const emit = defineEmits(['filter-change'])
const activeFilter = ref('all');

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
function fetchRequests() {
    store.dispatch("modifyRequest/" + FETCH_REQUESTS,);
}

onMounted(() => {
    fetchRequests();
});
</script>

<template>
    <section>
        <div>
            <!-- <div class="flex items-center justify-between mb-4">
                <span class="text-3xl font-medium">Bookings</span>
                <span class="text-3xl font-medium">Total Approved Amount: {{ offlineBookings?.approved_amount}}</span>
                <span class="text-3xl font-medium">Total Approved: {{ offlineBookings?.approved_count }}</span>
            </div> -->

            <!-- <div class="bg-gradient-to-br from-gray-100 to-gray-200 mb-4 ">
                <div class="max-w-full mx-auto">
                    <div class="bg-white rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                                Bookings Overview
                            </h1>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                            </div>
                        </div>
                    </div>
                </div>
            </div> -->
            <!-- <pre>{{ user_id }}</pre>
            <pre>{{ agentData }}</pre> -->
            <div class="text-lg font-semibold px-4 py-2 rounded shadow-sm w-fit">
                Modify Requests
            </div>
            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden">
                <div class="custom-scrollbar overflow-x-auto">
                    <table v-if="requests?.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Request Date</th>
                                <th scope="col" class="px-4 py-3">Booking ID / PNR</th>
                                <th scope="col" class="px-4 py-3">Supplier</th>
                                <th scope="col" class="px-4 py-3">Agent Email</th>
                                <th scope="col" class="px-4 py-3">Route</th>
                                <th scope="col" class="px-4 py-3">Travel Date</th>
                                <th scope="col" class="px-4 py-3">Request Status</th>
                                <th scope="col" class="px-4 py-3">Reason</th>
                                <th scope="col" class="px-4 py-3">Message</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="request in requests" :key="request.id"
                                class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                <!-- Request Date -->
                                <td class="px-4 py-3">
                                    {{ moment(request.created_at).format("MM-DD-YYYY hh:mm A") }}
                                </td>

                                <!-- Request ID -->

                                <!-- Booking ID / PNR -->
                                <td class="px-4 py-3">
                                    <div>
                                        <p class="text-xs text-gray-500">
                                            {{ request.booking.itinerary_ref || request.booking.pnr || 'N/A' }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Supplier -->
                                <td class="px-4 py-3">
                                    <div v-if="parseFlightData(request.booking?.flight_data)?.provider">
                                        {{ parseFlightData(request.booking.flight_data)?.provider?.name }}
                                        <span class="text-xs text-gray-500 block">
                                            {{ parseFlightData(request.booking.flight_data)?.provider?.identifier }}
                                        </span>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs">Unknown</span>
                                </td>

                                <!-- Agent Email -->
                                <td class="px-4 py-3 text-xs">{{ request.booking.agency_email || '—' }}</td>

                                <!-- Route -->
                                <td class="px-4 py-3">
                                    <div class="text-xs space-y-1">
                                        <template v-if="request.booking.booking_source == 1">
                                            <div v-for="(flight, i) in parseFlightData(request.booking?.flight_data)?.leg?.flights"
                                                :key="i" class="flex items-center gap-2">
                                                <span class="font-medium">{{ flight.from.iata }}</span>
                                                <span class="text-gray-400">→</span>
                                                <span class="font-medium">{{ flight.to.iata }}</span>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div v-for="(leg, i) in parseFlightData(request.booking.flight_data)?.legs"
                                                :key="i">
                                                <div v-for="(stop, j) in leg.stops" :key="j"
                                                    class="flex items-center gap-2">
                                                    <span>{{ stop.departure.airport.iata_code }}</span>
                                                    <span class="text-gray-400">→</span>
                                                    <span>{{ stop.arrival.airport.iata_code }}</span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>

                                <!-- Passengers -->

                                <!-- Travel Date -->
                                <td class="px-4 py-3 text-xs font-medium">
                                    <template v-if="request.booking.booking_source == 1">
                                        <div v-for="(flight, i) in parseFlightData(request.booking?.flight_data)?.leg?.flights"
                                            :key="i">
                                            {{ formatDate(flight.departure_at) }}
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div v-for="date in parseFlightData(request.booking.flight_data)?.dates"
                                            :key="date.departureDate">
                                            {{ formatDate(date.departureDate) }}
                                        </div>
                                    </template>
                                </td>

                                <!-- Modification Request Status -->
                                <td class="px-4 py-3">
                                    <span :class="{
                                        'px-3 py-1 text-xs font-medium rounded-full': true,
                                        'bg-yellow-100 text-yellow-800': request.status === 'pending',
                                        'bg-green-100 text-green-800': request.status === 'approved',
                                        'bg-red-100 text-red-800': request.status === 'rejected',
                                        'bg-gray-100 text-gray-800': !['pending', 'approved', 'rejected'].includes(request.status)
                                    }">
                                        {{ request.status ? request.status.charAt(0).toUpperCase() +
                                        request.status.slice(1) : '—' }}
                                    </span>
                                </td>

                                <!-- Reason -->
                                <td class="px-4 py-3">
                                    <span class="text-xs font-medium">
                                        {{ request.reason  }}
                                    </span>
                                </td>

                                <!-- Message -->
                                <td class="px-4 py-3 text-xs text-gray-600 max-w-xs">
                                    <p class="truncate">{{ request.message || 'No message provided' }}</p>
                                    <p v-if="request.message && request.message.length > 50"
                                        class="text-primary cursor-pointer hover:underline"
                                        @click="alert(request.message)">
                                        Show more
                                    </p>
                                </td>

                                <!-- Actions -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <!-- View Booking Details -->
                                        <button @click="$router.push({
                                            name: 'AdminCustomerBookingsLayout',
                                            query: {
                                                booking_id: request.booking.id,
                                                modify_request_id: request.id,
                                                pnr: request.booking.itinerary_ref || request.booking.pnr,
                                                agent_id: request.booking.agent_id,
                                                booking_source: request.booking.booking_source,
                                                flight_provider : request.booking.flight_provider,
                                                flight_mode : request.booking.booking_mode,
                                            }
                                        })" class="text-primary hover:text-purple-700 transition" title="View Booking">
                                            <CircleChevronRight class="w-5 h-5" />
                                        </button>


                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- No Data State -->
                    <div v-else class="flex flex-col items-center justify-center py-16 px-4">
                        <img class="h-64 md:h-80 max-w-md w-full object-contain mx-auto"
                            src="/public/assets/No data.png" alt="No data available">
                        <p
                            class="text-gray-600 text-lg mt-6 text-center font-medium bg-gray-50 px-6 py-3 rounded-full shadow-sm border max-w-md mx-auto">
                            No modification requests found
                            <span class="block text-sm text-gray-400 mt-1 font-normal">Try adjusting your filters</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Include the Modify Dialog (from previous answer) here or as a component -->
        </div>
    </section>
</template>

<style scoped>
/* custom scrollbar for mobile */
.custom-scrollbar::-webkit-scrollbar {
    height: 2px;
    width: 2px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #e5e7eb;
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #9ca3af;
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #6b7280;
}


.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #9ca3af #e5e7eb;
}

/* For Firefox to make it even thinner, use this hack */
@-moz-document url-prefix() {
    .custom-scrollbar {
        scrollbar-width: auto;
        scrollbar-color: #9ca3af #e5e7eb;
    }
}
</style>
