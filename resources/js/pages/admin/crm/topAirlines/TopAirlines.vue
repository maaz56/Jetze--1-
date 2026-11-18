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
    Plane,
    Globe2,
    CheckCircle2,
    XCircle,
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
import { DELETE_TOP_AIRLINE, FETCH_TOP_AIRLINES } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

const airlinesData = computed(() => store.getters['cms/topAirlines']?.data || {});
const topAirlines = computed(() => {
    const airlines = airlinesData.value.data;
    return Array.isArray(airlines) ? airlines.filter((airline) => airline != null) : [];
});
const isLoading = computed(() => store.state.cms?.isLoading || false);

const showDeleteDialog = ref(false);
const airlineToDelete = ref(null);
const searchQuery = ref(route.query.search_query || "");

// Quick Metrics
const totalAirlinesCount = computed(() => airlinesData.value?.total ?? topAirlines.value.length);
const activeCount = computed(() => topAirlines.value.filter((a) => a.is_active).length);
const domesticCount = computed(() => topAirlines.value.filter((a) => a.type === 'domestic').length);
const internationalCount = computed(() => topAirlines.value.filter((a) => a.type === 'international').length);

const fetchTopAirlines = debounce(() => {
    store.dispatch('cms/' + FETCH_TOP_AIRLINES, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

function confirmDelete(airline) {
    airlineToDelete.value = airline;
    showDeleteDialog.value = true;
}

function deleteAirline() {
    if (!airlineToDelete.value?.id) return;

    store.dispatch('cms/' + DELETE_TOP_AIRLINE, {
        id: airlineToDelete.value.id
    })
        .then(() => {
            toast.success("Top airline deleted successfully");
            fetchTopAirlines();
        })
        .catch(() => {
            toast.error("Failed to delete airline");
        })
        .finally(() => {
            showDeleteDialog.value = false;
            airlineToDelete.value = null;
        });
}

onMounted(() => {
    fetchTopAirlines();
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
                        Top Airlines
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Featured airline partners displayed across landing pages and booking search filters
                    </p>
                </div>
            </div>

            <Button
                v-if="authUser?.role === 'admin'"
                class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5 self-start sm:self-auto"
                @click="router.push({ name: 'NewTopAirline' })"
            >
                <Plus class="w-4 h-4" />
                <span>Add Top Airline</span>
            </Button>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Airlines</span>
                    <Plane class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalAirlinesCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Active</span>
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-emerald-700">
                    {{ activeCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Domestic</span>
                    <Plane class="h-4 w-4 text-sky-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-sky-700">
                    {{ domesticCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">International</span>
                    <Globe2 class="h-4 w-4 text-indigo-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-indigo-700">
                    {{ internationalCount }}
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
                        placeholder="Search by airline name..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
                        @input="
                            router.push({
                                query: {
                                    ...route.query,
                                    search_query: searchQuery || undefined,
                                    page: undefined
                                }
                            });
                            fetchTopAirlines();
                        "
                    />
                </div>
            </div>

            <!-- Loading Spinner -->
            <section v-if="isLoading" class="py-20 flex justify-center">
                <Spinner />
            </section>

            <!-- Empty State -->
            <section v-else-if="topAirlines.length === 0" class="py-12">
                <NothingFound message="No top airlines found" />
            </section>

            <!-- Table View -->
            <section v-else>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50 border-b border-slate-200">
                            <TableRow>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 pl-4 w-20">Logo</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Airline Name</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Coverage Type</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Status</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="airline in topAirlines"
                                :key="airline.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell class="pl-4">
                                    <div v-if="airline.image_url" class="w-12 h-10 rounded border border-slate-200 bg-white p-1 flex items-center justify-center overflow-hidden">
                                        <img
                                            :src="airline.image_url"
                                            :alt="airline.name"
                                            class="w-full h-full object-contain"
                                        />
                                    </div>
                                    <div v-else class="w-12 h-10 rounded border border-slate-200 bg-slate-50 flex items-center justify-center text-[10px] text-slate-400">
                                        No logo
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-xs font-bold text-slate-900">
                                        {{ airline.name }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="airline.type === 'domestic'
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-sky-50 text-sky-700 border-sky-200'"
                                        class="rounded text-[11px] font-semibold px-2 py-0.5"
                                    >
                                        {{ airline.type === 'international' ? 'International' : 'Domestic' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="airline.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'"
                                        class="rounded text-[11px] font-semibold px-2 py-0.5"
                                    >
                                        {{ airline.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right whitespace-nowrap pr-4">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="router.push({ name: 'EditTopAirline', params: { id: airline.id } })"
                                        >
                                            <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            Edit
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(airline)"
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

                <div v-if="airlinesData.total != null && airlinesData.per_page" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="airlinesData" @change="fetchTopAirlines" />
                </div>
            </section>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Top Airline?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        This will permanently delete <strong class="text-slate-900">{{ airlineToDelete?.name }}</strong>.
                        This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteAirline"
                    >
                        Delete Airline
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
