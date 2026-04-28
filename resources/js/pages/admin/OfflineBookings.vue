```vue
<template>
    <section>
        <div>
            <div class="">
                <div class="max-w-full mx-auto">
                    <div class="rounded-lg overflow-hidden">
                        <div class="p-6">
                            <h1 class="text-3xl font-bold text-gray-800">
                                Bookings Overview
                            </h1>
                        </div>
                    </div>
                </div>
            </div>
            <div class="w-full mb-2 flex items justify-between">
                <div class="flex items-center gap-2">
                    <label for="simple-search" class="sr-only">Search</label>
                    <div class="relative w-full">
                        <Input @change="filterBookings(activeFilter)" v-model="searchQuery" type="text"
                            id="simple-search" class="border bg-white h-10 border-gray-300 text-gray-900 text-sm rounded
                            focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 
                            dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 
                            dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                            placeholder="Search" />
                    </div>
                    <Select v-model="activeFilter" @update:modelValue="filterBookings">
                        <SelectTrigger
                            class="w-full md:w-60 h-10 border border-gray-300 bg-white rounded focus:ring-primary-500 focus:border-primary-500">
                            <SelectValue placeholder="Select booking type" />
                        </SelectTrigger>
                        <SelectContent class="bg-white">
                            <SelectGroup>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="one-way">One Way</SelectItem>
                                <SelectItem value="return">Round Trip</SelectItem>
                                <SelectItem value="multi-city">Multi City</SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <Button class="h-10 text-white" @click="filterBookings(activeFilter)">Search</Button>
                </div>
                <Button @click="router.push({ name: 'NewOfflineBookings' })" variant="outline"
                    class="flex h-10 gap-2 text-primary hover:text-primary">+ Add Booking</Button>
            </div>

            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <div v-if="isLoading" class="flex items-center gap-2 justify-center bg-white p-24 rounded-lg mt-8">
                        <Spinner />
                    </div>
                    <table v-if="filteredBookings.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Booking Date</th>
                                <th scope="col" class="px-4 py-3">Booking ID</th>
                                <th scope="col" class="px-4 py-3">PNR</th>
                                <th scope="col" class="px-4 py-3">Agent Email</th>
                                <th scope="col" class="px-4 py-3">Supplier</th>
                                <th scope="col" class="px-4 py-3">Route</th>
                                <th scope="col" class="px-4 py-3">Passenger Name</th>
                                <th scope="col" class="px-4 py-3">Travel Date</th>
                                <!-- <th scope="col" class="px-4 py-3">Status</th> -->
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in filteredBookings" :key="booking.id"
                                class="border-b dark:border-gray-700">
                                <td class="px-4 py-3">
                                    {{ moment(booking.created_at).format("DD-MMM-YYYY") }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.id + 1000 }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ booking.user?.email || booking.agent_id || 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.booking_pnr || 'N/A' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.user.agent_data.company_name }}
                                </td>
                                <td class="px-4 py-3">
                                    <template v-if="Array.isArray(booking.route)">
                                        <div v-for="(r, i) in booking.route" :key="i">
                                            {{ r.origin }} → {{ r.destination }} ({{ r.date }})
                                        </div>
                                    </template>
                                    <template v-else-if="booking.route">
                                        {{ booking.route.origin }} → {{ booking.route.destination }}
                                        <span v-if="booking.route.dateRange?.start">
                                            ({{ booking.route.dateRange.start }})
                                        </span>
                                        <span v-if="booking.route.dateRange?.end">
                                            - ({{ booking.route.dateRange.end }})
                                        </span>
                                    </template>
                                    <template v-else>
                                        N/A
                                    </template>
                                </td>

                                <td class="px-4 py-3">
  <template v-if="booking.travellers && booking.travellers.length">
    <div v-for="(traveller, index) in booking.travellers" :key="index">
      {{ traveller.first_name }} {{ traveller.last_name }}
    </div>
  </template>
  <span v-else>N/A</span>
</td>

                                <td class="px-4 py-3">
                                    <template v-if="Array.isArray(booking.route)">
                                        <div v-for="(r, i) in booking.route" :key="i">
                                            {{ formatDate(r.date) }}
                                        </div>
                                    </template>
                                    <template v-else-if="booking.route?.dateRange?.start">
                                        {{ formatDate(booking.route.dateRange.start) }}
                                        <span v-if="booking.route.dateRange?.end">
                                            - {{ formatDate(booking.route.dateRange.end) }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        N/A
                                    </template>
                                </td>

                                <!-- <td class="py-2 px-4 uppercase">
                                    {{ booking.status }}
                                </td> -->
                                <td class="px-1 py-4">
                                    <div class="flex space-x-2">
                                        <button @click="deleteBooking(booking.id)"
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-destructive h-8 px-3 text-destructive hover:bg-destructive/10">
                                            <Trash2 class="w-4 h-4 me-2" />
                                            Delete
                                        </button>
                                        <button  @click="
                                            $router.push({
                                                name: 'EditOfflineBookings',
                                                query: {
                                                    booking_id: booking.id,
                                                },
                                            })
                                            "
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-primary h-8 px-3 text-primary hover:bg-primary/10">
                                            <Pencil class="w-4 h-4 me-2" />
                                            Edit
                                        </button>
                                        
                                        <Button size="sm" variant="outline"
                                            class="flex gap-2 text-primary hover:text-primary"
                                            @click="router.push({ name: 'OfflineBookingDetails', query: { id: booking.id } })">
                                            Details
                                            <ChevronRight class="w-5 h-5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div v-else class="flex flex-col items-center justify-center py-12 px-4 w-full">
                        <p class="text-gray-600 text-lg mt-6 text-center font-medium">
                            No bookings found for the selected filter
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import Button from "@/components/ui/button/Button.vue";
import Spinner from "@/components/common/Spinner.vue";
import {
    ChevronRight,
    Pencil,
    Trash,
    Trash2
} from "lucide-vue-next";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { computed, onMounted, ref } from "vue";
import moment from "moment";
import Input from "@/components/ui/input/Input.vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { DELETE_OFFLINE_BOOKING, FETCH_OFFLINE_BOOKINGS } from "@/services/store/actions.type";

const searchQuery = ref('');
const store = useStore();
const router = useRouter();
const isLoading = computed(() => store.getters['offlineBooking/isLoading']);
const offlineBookings = computed(() => {
    // Parse the route field for each booking
    return store.getters['offlineBooking/offlineBookings'].map(booking => {
        try {
            if (typeof booking.route === 'string') {
                booking.route = JSON.parse(booking.route);
            }
            return booking;
        } catch (error) {
            console.error(`Error parsing route for booking ${booking.id}:`, error);
            return { ...booking, route: {} }; // Fallback to empty route object
        }
    });
});
const dateRange = ref({
    start: null,
    end: null,
});
const activeFilter = ref('all');

const filteredBookings = computed(() => {
    let bookings = offlineBookings.value || [];

    if (activeFilter.value !== 'all') {
        bookings = bookings.filter(booking => booking.flight_type === activeFilter.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        bookings = bookings.filter(booking => {
            const passengerName = booking.travellers[0]
                ? `${booking.travellers[0].first_name} ${booking.travellers[0].last_name}`.toLowerCase()
                : '';
            return (
                passengerName.includes(query) ||
                booking.route?.origin?.toLowerCase().includes(query) ||
                booking.route?.destination?.toLowerCase().includes(query) ||
                booking.user?.email?.toLowerCase().includes(query) ||
                booking.agent_id?.toString().includes(query)
            );
        });
    }

    return bookings;
});

function filterBookings(type) {
    activeFilter.value = type;
    // Trigger re-computation of filteredBookings
}

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
};

function fetchBookings() {
    store.dispatch('offlineBooking/' + FETCH_OFFLINE_BOOKINGS).then(() => {
        // No need to set isLoading manually; rely on store getter
    }).catch(error => {
        console.error("Error fetching bookings:", error);
    });
}

function deleteBooking(id) {
    store.dispatch("offlineBooking/" + DELETE_OFFLINE_BOOKING, {
        id: id,
    }).then(() => {
        fetchBookings(); // Refresh the bookings list after deletion
    }).catch(error => {
        console.error("Error deleting booking:", error);
    });
}

onMounted(() => {
    fetchBookings();
});
</script>
```