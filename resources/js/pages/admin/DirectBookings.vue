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
    FETCH_ADMIN_BOOKING,
    FETCH_ADMIN_BOOKINGS
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";
import { useUserStore } from "@/services/stores/user";
import { debounce } from "lodash";
import { useAuthStore } from "@/services/stores/auth";

const store = useStore();
const authStore = useAuthStore();

const bookings = computed(() => store.getters["flight/adminBookings"]);

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
function fetchBookings() {
    store.dispatch("flight/" + FETCH_ADMIN_BOOKINGS, {
        user_role: user.value.role,
        bookingFilter: activeFilter.value,
    });
}

onMounted(() => {
    fetchBookings();
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
            <div class="bg-gradient-to-br from-gray-100 to-gray-200 mb-4">
                <div class="max-w-full mx-auto">
                    <div class="bg-white rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h1 class="text-3xl font-bold text-gray-800 mb-6">
                                Direct Bookings Overview
                            </h1>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <!-- Total Bookings -->
                                <div class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-gray-500': activeFilter === 'all' }"
                                    @click="filterBookings('all')">
                                    <CalendarIcon class="h-8 w-8 text-gray-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">
                                            All Bookings
                                        </p>
                                        <p class="text-2xl font-bold text-gray-800">
                                            {{ bookings?.total_count || 0 }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Ticketed -->
                                <div class="bg-gray-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md"
                                    :class="{ 'ring-2 ring-gray-500': activeFilter === 'ticketed' }"
                                    @click="filterBookings('ticketed')">
                                    <CheckCircleIcon class="h-8 w-8 text-gray-500 mr-4" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-600">
                                            Ticketed
                                        </p>
                                        <p class="text-2xl font-bold text-gray-800">
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
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- <pre>{{ user_id }}</pre>
            <pre>{{ agentData }}</pre> -->
            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table v-if="bookings?.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Booking Date
                                </th>
                               
                                <th scope="col" class="px-4 py-3">PNR</th>
                                <th scope="col" class="px-4 py-3">Agent Email</th>
                                <th scope="col" class="px-4 py-3">Margin</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <!-- <pre>{{ bookings }}</pre> -->
                        <tbody>
                            <tr v-for="booking in bookings" :key="booking.id"
                                class="border-b dark:border-gray-700">
                                <td class="px-4 py-3">
                                    {{
                                        moment(booking.created_at).format(
                                            "MM-DD-YYYY hh:mm A",
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                    
                                        booking.pnr
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                    
                                        booking.agent.email
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                    
                                        booking.margin
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                    
                                        formatAmount(booking.total_amount)
                                    }}
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
    </section>
</template>
