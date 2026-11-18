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
    Plus,
    Building2,
    MapPin,
    Globe,
    Clock,
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
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

// Quick Metrics
const totalAirportsCount = computed(() => airportsData.value?.meta?.total ?? airports.value.length);
const distinctCitiesCount = computed(() => new Set(airports.value.map((a) => a.city_name).filter(Boolean)).size);
const distinctCountriesCount = computed(() => new Set(airports.value.map((a) => a.iata_country_code).filter(Boolean)).size);

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
            toast.success("Airport deleted successfully");
        })
        .catch(() => {
            toast.error("Failed to delete airport");
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
    <div class="space-y-5">
        <!-- Top Bar Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 px-2.5 text-xs rounded border-slate-200 text-slate-600 hover:bg-slate-100 max-sm:hidden"
                    @click="router.push({ name: 'Dashboard' })"
                >
                    <ArrowLeft class="w-3.5 h-3.5 mr-1" />
                    Back
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Airports Directory
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Manage global airport database, IATA city mappings, timezones, and lookup codes
                    </p>
                </div>
            </div>

            <Button
                v-if="authUser?.role === 'admin'"
                class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5 self-start sm:self-auto"
                @click="router.push({ name: 'NewAirport' })"
            >
                <Plus class="w-4 h-4" />
                <span>Add Airport</span>
            </Button>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Airports</span>
                    <Building2 class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalAirportsCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Cities Covered</span>
                    <MapPin class="h-4 w-4 text-sky-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-sky-700">
                    {{ distinctCitiesCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Countries</span>
                    <Globe class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-emerald-700">
                    {{ distinctCountriesCount }}
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="rounded border border-slate-200 bg-white shadow-xs">
            <!-- Search & Filters Toolbar -->
            <div class="p-3.5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
                <div class="relative w-full max-w-sm">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by name, city, or IATA code..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
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
                    />
                </div>
            </div>

            <!-- Loading Spinner -->
            <section v-if="isLoading" class="py-20 flex justify-center">
                <Spinner />
            </section>

            <!-- Empty State -->
            <section v-else-if="airports.length === 0" class="py-12">
                <NothingFound message="No airports found" />
            </section>

            <!-- Table View -->
            <section v-else>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50 border-b border-slate-200">
                            <TableRow>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 pl-4 w-28">IATA Code</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Airport Name</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">City / City Code</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Country</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Timezone</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="airport in airports"
                                :key="airport.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell class="pl-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-primary/10 text-primary font-mono font-bold text-xs">
                                        {{ airport.iata_code }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-xs font-bold text-slate-900">
                                        {{ airport.name }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5 text-xs text-slate-700">
                                        <span>{{ airport.city_name }}</span>
                                        <span v-if="airport.iata_city_code" class="text-slate-400 font-mono text-[11px]">
                                            ({{ airport.iata_city_code }})
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-slate-700">
                                        <Globe class="w-3.5 h-3.5 text-slate-400" />
                                        {{ airport.iata_country_code || '—' }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1 text-xs text-slate-500 font-mono">
                                        <Clock class="w-3.5 h-3.5 text-slate-400" />
                                        <span>{{ airport.time_zone || 'UTC' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell class="text-right whitespace-nowrap pr-4">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="router.push({ name: 'EditAirport', params: { id: airport.id } })"
                                        >
                                            <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            Edit
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(airport)"
                                        >
                                            <Trash2 class="w-3.5 h-3.5 mr-1" />
                                            Delete
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="airportsData.meta" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="airportsData.meta" @change="fetchAirports" />
                </div>
            </section>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Airport?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        This will permanently delete <strong class="text-slate-900">{{ airportToDelete?.name }} ({{ airportToDelete?.iata_code }})</strong>.
                        This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteAirport"
                    >
                        Delete Airport
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
