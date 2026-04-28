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

import { FETCH_AIRLINES, UPDATE_AIRLINE, FETCH_TRAVELLERS } from "@/services/store/actions.type";
import { useStore } from "vuex";
import { computed, onMounted, ref } from "vue";
const store = useStore();
const airLines = computed(() => store.getters["airline/airlines"]);
const airLinesData = computed(() => store.getters["airline/airlinesData"]);
const agentTravellers = computed(() => store.getters["traveller/travellers"]);


const selectedItems = ref([]);
const searchQuery = ref();
const currentPage = ref(1);
const itemsPerPage = 10; // Adjust this value as needed
const isMarginDialogOpen = ref(false);
const selectedAirline = ref(null);
const type = ref("markup");
const amount = ref(null);
const amount_type = ref("amount");

const isLoading = computed(() => store.getters["airline/isLoading"]);

function fetchAgentTraverllers() {
    store.dispatch("traveller/" + FETCH_TRAVELLERS, {
       
        search: searchQuery.value,
    });
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

onMounted(() => {

    fetchAgentTraverllers();
});
</script>

<template>
    <section class="h-screen flex flex-col">
        <div class="flex-grow overflow-hidden flex flex-col">
            <div class="flex items-center justify-between mb-8">
                <span class="text-3xl font-medium">Travelers</span>
               
                <Button @click="$router.push({ name: 'NewTraveller' })"
                    >Add Traveller</Button
                >
            </div>
            <div
                class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border flex-grow flex flex-col"
            >
                <div
                    class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4"
                >
                    <div class="w-full md:w-1/2">
                        <form class="flex items-center">
                            <label for="simple-search" class="sr-only"
                                >Search</label
                            >
                            <div class="relative w-full">
                                <div
                                    class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none"
                                >
                                    <svg
                                        aria-hidden="true"
                                        class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                        fill="currentColor"
                                        viewbox="0 0 20 20"
                                        xmlns="http://www.w3.org/2000/svg"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    @input="fetchAirLines()"
                                    id="search"
                                    type="text"
                                    placeholder="Search..."
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                />
                            </div>
                        </form>
                    </div>
                </div>
                <div class="overflow-x-auto flex-grow">
                    <div class="h-full overflow-y-auto">
                        <table class=" w-full table-auto text-sm text-gray-500 dark:text-gray-400 border border-gray-300">
    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-300">
        <tr>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Type</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">M/F</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Title</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">First Name</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Last Name</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">DOB</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Nationality</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Doc Type</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Doc No</th>
            <th scope="col" class="p-4 w-1/12 text-center border-r">Doc Expiry</th>
            <th scope="col" class="p-4 w-1/12 text-center">Issue Country</th>
        </tr>
    </thead>

    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
        <tr v-for = "traveller in agentTravellers" :key=traveller.id class="hover:bg-gray-50 dark:hover:bg-gray-700">
            <td class="p-4 text-center border-r">{{traveller.type}}</td>
            <td class="p-4 text-center border-r">{{traveller.gender}}</td>
            <td class="p-4 text-center border-r">{{traveller.title}}</td>
            <td class="p-4 text-center border-r">{{traveller.first_name}}</td>
            <td class="p-4 text-center border-r">{{traveller.last_name}}</td>
            <td class="p-4 text-center border-r">{{traveller.date_of_birth}}</td>
            <td class="p-4 text-center border-r">{{traveller.nationality}}</td>
            <td class="p-4 text-center border-r">{{traveller.doc_type}}</td>
            <td class="p-4 text-center border-r">{{traveller.document_no}}</td>
            <td class="p-4 text-center border-r">{{traveller.expiry_date}}</td>
            <td class="p-4 text-center border-r">{{traveller.issue_country}}</td>
        </tr>
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
