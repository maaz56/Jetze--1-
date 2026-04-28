<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Plane } from "lucide-vue-next";

import { Switch } from "@/components/ui/switch";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from "@/components/ui/dialog";

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

import { FETCH_AIRLINES, UPDATE_AIRLINE, UPDATE_BOOKING_STATUS, FETCH_BOOKING_STATS_SETINGS, DELETE_AIRLINE } from "@/services/store/actions.type";
import { useStore } from "vuex";
import { computed, onMounted, ref, watch } from "vue";
import DialogFooter from "@/components/ui/dialog/DialogFooter.vue";
const store = useStore();
const airLines = computed(() => store.getters["airline/airlines"]);
const airLinesData = computed(() => store.getters["airline/airlinesData"]);

const selectedItems = ref([]);
const searchQuery = ref();
const currentPage = ref(1);
const itemsPerPage = 10; // Adjust this value as needed
const isMarginDialogOpen = ref(false);
const selectedAirline = ref(null);
const type = ref("markup");
const amount = ref(null);
const amount_type = ref("amount");
const bookingStatus = ref(false)
const isDeleteDialogOpen = ref(false);
const airlineToDelete = ref(null)


const isLoading = computed(() => store.getters["airline/isLoading"]);
const booking_status = computed(() => store.getters["settings/bookingStatusSetting"]);

function fetchAirLines() {
    store.dispatch("airline/" + FETCH_AIRLINES, {
        page: currentPage.value,
        search: searchQuery.value,
    });
}

function openDeleteDialog(airline) {
    airlineToDelete.value = airline;
    isDeleteDialogOpen.value = true;
}

function closeDeleteDialog() {
    isDeleteDialogOpen.value = false;
    airlineToDelete.value = null;
}

function confirmDeleteAirline() {
    if (airlineToDelete.value) {
        store.dispatch("airline/" + DELETE_AIRLINE, {
            id: airlineToDelete.value.id
        })
            .then(() => {
                fetchAirLines();
                closeDeleteDialog();
            });
    }
}

function openMarginDialog(airline) {
    selectedAirline.value = airline;
    isMarginDialogOpen.value = true;
}

function closeMarginDialog() {
    isMarginDialogOpen.value = false;
}

function handleMarginSubmit() {
    //console.log({ type: type.value, amount: amount.value });
    store.dispatch("airline/" + UPDATE_AIRLINE, {
        id: selectedAirline.value.id,
        type: type.value,
        amount: amount.value,
        amount_type: amount_type.value,
    });
    isMarginDialogOpen.value = false;
    fetchAirLines();
}
// const handleMarginSubmit = (data) => {
//     // Handle the margin/discount submission
//     //console.log("Margin/Discount submitted:", data);
//     // Update the airlines data with the new margin/discount
//     // You might want to call an API here to update the backend

// };

const handlePagination = (page) => {
    currentPage.value = page;
    fetchAirLines();
};

function fetchBookingStatus() {
    store.dispatch("settings/" + FETCH_BOOKING_STATS_SETINGS);
}
function updateBookingStatus() {
    console.log("Booking status updated:", bookingStatus.value);
    try {
        store.dispatch("settings/" + UPDATE_BOOKING_STATUS, {
            booking_status: bookingStatus.value ? 1 : 0,
        });
        //console.log("Booking status updated successfully: " + bookingStatus.value)
    } catch (err) {
        console.error("Failed to update user status:", err)
        // Optionally, show an error message to the user
    }
}

watch(
    booking_status,
    (newVal) => {
        if (newVal) {
            bookingStatus.value = newVal.bookingStatus === 1;
        }
    },
    { immediate: true }
);

onMounted(() => {
    fetchAirLines();
    fetchBookingStatus();
});
</script>

<template>
    <section class="h-screen flex flex-col">
        <div class="flex-grow overflow-hidden flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <span class="text-3xl font-medium">Airlines</span>

                <Button class="text-white" @click="$router.push({ name: 'NewAirline' })">New Air Line</Button>
            </div>
            <div
                class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border flex-grow flex flex-col">
                <div
                    class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                    <div class="w-full md:w-1/2">
                        <form class="flex items-center">
                            <label for="simple-search" class="sr-only">Search</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                        fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    @input="fetchAirLines()"
                                    id="search"
                                    type="text"
                                    placeholder="Search airlines..."
                                    class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 bg-white dark:bg-gray-800 dark:border-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition"
                                    autocomplete="off"
                                />
                            </div>
                        </form>
                    </div>
                    <!-- <pre>{{ bookingStatus }}</pre> -->
                    <div
                        class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900 flex flex-col md:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Booking Status:</span>
                            <span class="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold"
                                :class="bookingStatus ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'">
                                <span :class="[
                                    'inline-block w-2 h-2 rounded-full',
                                    bookingStatus ? 'bg-red-500' : 'bg-green-500'
                                ]"></span>
                                {{ bookingStatus ? "Offline" : "Online" }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="bookingStatus = !bookingStatus; updateBookingStatus()"
                                :aria-label="bookingStatus ? 'Set Online' : 'Set Offline'"
                                type="button"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                                :class="bookingStatus ? 'bg-primary' : 'bg-gray-200'"
                            >
                                <span
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                    :class="bookingStatus ? 'translate-x-6' : 'translate-x-1'"
                                ></span>
                            </button>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                Toggle to {{ bookingStatus ? "go online" : "go offline" }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-auto flex-grow">
                    <div class="h-full overflow-y-auto">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        Name
                                    </th>
                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        Logo
                                    </th>
                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        IATA Code
                                    </th>

                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        Margin Type
                                    </th>
                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        Amount Type
                                    </th>
                                    <th scope="col" class="p-4 w-1/5 text-start">
                                        Margin Amount
                                    </th>
                                    <th scope="col" class="p-4 w-1/5 text-end">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="airline in airLines" :key="airline.id"
                                    class="group bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <!-- Logo -->
                                    <td class="p-4 w-1/5">
                                        <div class="flex items-center justify-start">
                                            <div
                                                class="w-14 h-14 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center overflow-hidden shadow-sm border border-gray-200 dark:border-gray-600">
                                                <img v-if="airline.logo_url" :src="airline.logo_url"
                                                    :alt="airline.name + ' logo'" class="w-10 h-10 object-contain" />
                                                <Plane v-else class="w-7 h-7 text-primary dark:text-primary" />
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Name -->
                                    <td class="px-4 py-4 w-1/5">
                                        <div class="flex items-center h-full">
                                            <span
                                                class="font-semibold text-gray-900 dark:text-white text-base group-hover:text-primary transition-colors">
                                                {{ airline.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <!-- IATA Code -->
                                    <td class="px-4 py-4 w-1/5">
                                        <span
                                            class="inline-block bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary px-2 py-1 rounded text-xs font-medium uppercase tracking-wide">
                                            {{ airline.iata_code }}
                                        </span>
                                    </td>
                                    <!-- Margin Type -->
                                    <td class="px-4 py-4 w-1/5">
                                        <span
                                            class="inline-block px-2 py-1 rounded bg-gray-100 dark:bg-gray-900 text-xs font-semibold uppercase text-gray-700 dark:text-gray-300">
                                            {{ airline.margin_type }}
                                        </span>
                                    </td>
                                    <!-- Amount Type -->
                                    <td class="px-4 py-4 w-1/5">
                                        <span
                                            class="inline-block px-2 py-1 rounded bg-gray-100 dark:bg-gray-900 text-xs font-semibold uppercase text-gray-700 dark:text-gray-300">
                                            {{ airline.amount_type === "percent" ? "Percent (%)" : airline.amount_type
                                            }}
                                        </span>
                                    </td>
                                    <!-- Margin Amount -->
                                    <td class="px-4 py-4 w-1/5">
                                        <span class="font-bold text-gray-900 dark:text-white text-base">
                                            {{ airline.margin_amount }}
                                        </span>
                                    </td>
                                    <!-- Actions -->
                                    <td class="px-2 py-4 w-1/5">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button size="small"
                                                class="text-xs font-medium px-3 py-1 rounded bg-primary/90 text-white hover:bg-primary transition"
                                                @click="openMarginDialog(airline)">
                                                Set Margin/Discount
                                            </Button>
                                            <Button size="small" variant="destructive"
                                                class="text-xs font-medium px-3 py-1 rounded bg-red-600 text-white hover:bg-red-700 transition"
                                                @click="openDeleteDialog(airline)">
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <Dialog v-model:open="isMarginDialogOpen">
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Set Margin or Discount for
                                                <span
                                                    class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-base font-medium text-green-700 ring-1 ring-green-600/20 ring-inset">{{
                                                        selectedAirline?.name
                                                    }}</span>
                                            </DialogTitle>
                                        </DialogHeader>

                                        <form @submit.prevent="handleSubmit" class="space-y-4">
                                            <div class="flex items-center space-x-4">
                                                <label class="flex items-center space-x-2">
                                                    <input type="radio" value="markup" v-model="type"
                                                        class="form-radio accent-primary" />
                                                    <span>Markup</span>
                                                </label>
                                                <label class="flex items-center space-x-2">
                                                    <input type="radio" value="discount" v-model="type"
                                                        class="form-radio accent-primary" />
                                                    <span>Discount</span>
                                                </label>
                                            </div>
                                            <div class="items-center space-x-4">
                                                <label class="block text-sm font-medium">Chose margin type</label>
                                                <div class="flex items-center space-x-4">
                                                    <label class="flex items-center space-x-2">
                                                        <input type="radio" value="amount" v-model="amount_type
                                                            " class="form-radio accent-primary" />
                                                        <span>Amount</span>
                                                    </label>
                                                    <label class="flex items-center space-x-2">
                                                        <input type="radio" value="percent" v-model="amount_type
                                                            " class="form-radio accent-primary" />
                                                        <span>Percent(%)</span>
                                                    </label>
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-sm font-medium">Amount</label>
                                                <input type="number" v-model="amount"
                                                    class="w-full p-2 border rounded-md focus:ring focus:ring-green-300"
                                                    placeholder="Enter amount" required />
                                            </div>

                                            <div class="flex justify-end space-x-2">
                                                <button type="button"
                                                    class="px-4 py-2 bg-gray-300 rounded-md hover:bg-gray-400" @click="
                                                        isMarginDialogOpen = false
                                                        ">
                                                    Cancel
                                                </button>
                                                <button @click="handleMarginSubmit" type="submit"
                                                    class="px-4 py-2 bg-primary  text-white rounded-md hover:bg-green-700">
                                                    Save
                                                </button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                                <Dialog v-model:open="isDeleteDialogOpen">
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Confirm Deletion</DialogTitle>
                                        </DialogHeader>
                                        <DialogDescription>
                                            Are you sure you want to delete the airline
                                            <span class="font-medium">{{ airlineToDelete?.name }}</span>? This action
                                            cannot be undone.
                                        </DialogDescription>
                                        <DialogFooter>
                                            <Button class="mr-2" variant="outline"
                                                @click="closeDeleteDialog">Cancel</Button>
                                            <Button variant="destructive" @click="confirmDeleteAirline">
                                                Delete
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- <nav
                    class="flex flex-col items-start justify-between pt-4 space-y-3 md:flex-row md:items-center md:space-y-0"
                >
                    <pagination
                        :paginationData="paginationData"
                        @pageChanged="handlePagination"
                    />
                </nav> -->
            </div>
        </div>
    </section>
</template>
