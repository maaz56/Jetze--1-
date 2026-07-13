<script setup>
import { Button } from "@/components/ui/button";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import Input from "@/components/ui/input/Input.vue";
import Pagination from "@/components/Pagination.vue";
import {
    ArrowLeft,
    Search,
    Pencil,
    Trash2,
    PlusCircleIcon,
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { Badge } from "@/components/ui/badge";
import { DELETE_AIRPORT, FETCH_AIRPORTS } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

const airportsData = computed(() => store.getters['airport/airports']);
const airports = computed(() => {
    if (Array.isArray(airportsData.value)) return airportsData.value;
    return airportsData.value?.data || [];
});
const isLoading = computed(() => store.state.airport?.isLoading || false);

const showDeleteDialog = ref(false);
const airportToDelete = ref(null);
const searchQuery = ref(route.query.search_query || "");

const fetchAirports = debounce(() => {
    store.dispatch('airport/' + FETCH_AIRPORTS, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
        with_pagination: true,
    });
}, 350);

function confirmDelete(airport) {
    airportToDelete.value = airport;
    showDeleteDialog.value = true;
}

function deleteAirport() {
    if (!airportToDelete.value?.id) return;

    store.dispatch('airport/' + DELETE_AIRPORT, airportToDelete.value.id)
        .then(() => {
            fetchAirports();
        })
        .finally(() => {
            showDeleteDialog.value = false;
            airportToDelete.value = null;
        });
}

onMounted(() => {
    fetchAirports();
});
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <Button
                @click="router.push({ name: 'Dashboard' })"
                variant="outline"
                size="sm"
            >
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <h1 class="text-3xl font-medium tracking-tight">Airports</h1>
        </div>

        <Button
            v-if="authUser?.role === 'admin'"
            @click="router.push({ name: 'NewAirport' })"
            class="flex items-center gap-2"
        >
            <PlusCircleIcon class="w-5 h-5" />
            Add Airport
        </Button>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg border">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div class="relative w-full max-w-md">
                <Input
                    v-model="searchQuery"
                    @input="
                        router.push({
                            query: {
                                ...route.query,
                                search_query: searchQuery || undefined,
                                page: undefined
                            }
                        });
                        fetchAirports();
                    "
                    placeholder="Search by name, city or code..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="airports.length === 0">
            <NothingFound message="No airports found" />
        </section>

        <section v-else>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader class="bg-primary">
                        <TableRow>
                            <TableHead>IATA Code</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>City</TableHead>
                            <TableHead>Country</TableHead>
                            <TableHead>Timezone</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="airport in airports" :key="airport.id" class="hover:bg-gray-50">
                            <TableCell class="font-bold">
                                {{ airport.iata_code }}
                            </TableCell>
                            <TableCell>
                                {{ airport.name }}
                            </TableCell>
                            <TableCell>
                                {{ airport.city_name }} ({{ airport.iata_city_code }})
                            </TableCell>
                            <TableCell>
                                {{ airport.iata_country_code }}
                            </TableCell>
                            <TableCell>
                                {{ airport.time_zone }}
                            </TableCell>
                            <TableCell class="whitespace-nowrap space-x-2">
                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'EditAirport', params: { id: airport.id } })"
                                >
                                    <Pencil class="w-4 h-4 mr-1" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmDelete(airport)"
                                >
                                    <Trash2 class="w-4 h-4 mr-1" />
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="airportsData.meta" class="flex justify-end pt-6">
                <Pagination :meta="airportsData.meta" @change="fetchAirports" />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Airport?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will permanently delete <strong>{{ airportToDelete?.name }} ({{ airportToDelete?.iata_code }})</strong>.
                    This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteAirport"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
