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
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";
import Input from "@/components/ui/input/Input.vue";
import Pagination from "@/components/Pagination.vue";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    ArrowLeft,
    Search,
    Pencil,
    Trash2,
    Plus,
    Eye,
    Send,
    Plane,
    Globe2,
    Compass,
    Sparkles,
    Check,
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
import { DELETE_POPULAR_ROUTE, FETCH_POPULAR_ROUTES, SEND_POPULAR_ROUTES_MAIL } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

// The API wraps Laravel's paginator in a response data object.
const routesData = computed(() => store.getters['cms/popularRoutes']?.data || {});
const popularRoutes = computed(() => {
    const routes = routesData.value?.data;
    return Array.isArray(routes) ? routes.filter((routeItem) => routeItem != null) : [];
});
const isLoading = computed(() => store.state.cms?.isLoading || false);

const showDeleteDialog = ref(false);
const routeToDelete = ref(null);
const searchQuery = ref(route.query.search_query || "");
const selectedRouteIds = ref([]);
const mailAudience = ref("customers");
const isSendingRouteMail = ref(false);

const selectedRouteCount = computed(() => selectedRouteIds.value.length);
const isAllVisibleSelected = computed(() => {
    return popularRoutes.value.length > 0 && popularRoutes.value.every((routeItem) => selectedRouteIds.value.includes(routeItem.id));
});

// Quick Metrics
const totalRoutesCount = computed(() => routesData.value?.total ?? popularRoutes.value.length);
const domesticCount = computed(() => popularRoutes.value.filter((r) => r.type === 'domestic').length);
const internationalCount = computed(() => popularRoutes.value.filter((r) => r.type === 'international').length);

function routeTypeBadgeClass(type) {
    return type === 'domestic'
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : 'bg-sky-50 text-sky-700 border-sky-200';
}

function journeyTypeBadgeClass(type) {
    return type === 'round'
        ? 'bg-indigo-50 text-indigo-700 border-indigo-200'
        : 'bg-amber-50 text-amber-700 border-amber-200';
}

function priceTypeBadgeClass(type) {
    return type === 'dynamic'
        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
        : 'bg-slate-100 text-slate-700 border-slate-200';
}

function formatSearchDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function getDepartureDate(routeItem) {
    if (routeItem?.departure_date) {
        return routeItem.departure_date;
    }

    const daysToAdd = routeItem?.departure_plus_days === null || routeItem?.departure_plus_days === undefined || routeItem?.departure_plus_days === ''
        ? 1
        : Number(routeItem.departure_plus_days);
    const date = new Date();
    date.setDate(date.getDate() + (Number.isFinite(daysToAdd) ? daysToAdd : 1));
    return formatSearchDate(date);
}

function getReturnDate(routeItem) {
    if (routeItem?.journey_type !== 'round') {
        return null;
    }

    if (routeItem?.return_date) {
        return routeItem.return_date;
    }

    const stayDays = Number(routeItem?.stay_duration_days);
    if (!Number.isFinite(stayDays)) {
        return null;
    }

    const [year, month, day] = getDepartureDate(routeItem).split('-').map(Number);
    const date = new Date(year, month - 1, day);
    date.setDate(date.getDate() + stayDays);
    return formatSearchDate(date);
}

const fetchPopularRoutes = debounce(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

function setRouteSelected(routeId, checked) {
    if (checked) {
        selectedRouteIds.value = Array.from(new Set([...selectedRouteIds.value, routeId]));
        return;
    }

    selectedRouteIds.value = selectedRouteIds.value.filter((id) => id !== routeId);
}

function toggleVisibleSelection(checked) {
    const visibleIds = popularRoutes.value.map((routeItem) => routeItem.id);

    if (checked) {
        selectedRouteIds.value = Array.from(new Set([...selectedRouteIds.value, ...visibleIds]));
        return;
    }

    selectedRouteIds.value = selectedRouteIds.value.filter((id) => !visibleIds.includes(id));
}

async function sendSelectedPopularRoutesMail() {
    if (!selectedRouteIds.value.length) {
        toast.error("Please select at least one route.");
        return;
    }

    isSendingRouteMail.value = true;
    try {
        await store.dispatch("cms/" + SEND_POPULAR_ROUTES_MAIL, {
            route_ids: selectedRouteIds.value,
            audience: mailAudience.value,
        });
        selectedRouteIds.value = [];
    } catch (error) {
        // Handled by store
    } finally {
        isSendingRouteMail.value = false;
    }
}

function confirmDelete(routeItem) {
    routeToDelete.value = routeItem;
    showDeleteDialog.value = true;
}

function deleteRoute() {
    if (!routeToDelete.value?.id) return;

    store.dispatch('cms/' + DELETE_POPULAR_ROUTE, {
        id: routeToDelete.value.id
    })
        .then(() => {
            toast.success("Popular route deleted successfully");
            fetchPopularRoutes();
        })
        .catch(() => {
            toast.error("Failed to delete route");
        })
        .finally(() => {
            showDeleteDialog.value = false;
            routeToDelete.value = null;
        });
}

onMounted(() => {
    fetchPopularRoutes();
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
                        Popular Routes
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Manage featured travel routes, promotional pricing, and marketing broadcasts
                    </p>
                </div>
            </div>

            <div v-if="authUser?.role === 'admin'" class="flex flex-wrap items-center gap-2.5">
                <div class="flex items-center gap-1.5">
                    <Select v-model="mailAudience">
                        <SelectTrigger class="h-9 w-36 rounded border-slate-200 bg-white text-xs">
                            <SelectValue placeholder="Audience" />
                        </SelectTrigger>
                        <SelectContent class="rounded border-slate-200 bg-white">
                            <SelectItem value="customers" class="text-xs">Customers</SelectItem>
                            <SelectItem value="subscribers" class="text-xs">Subscribers</SelectItem>
                            <SelectItem value="both" class="text-xs">Both Groups</SelectItem>
                        </SelectContent>
                    </Select>

                    <Button
                        variant="outline"
                        class="h-9 px-3 text-xs font-semibold rounded border-slate-200 text-slate-700 hover:bg-slate-50 flex items-center gap-1.5"
                        :disabled="selectedRouteCount === 0 || isSendingRouteMail"
                        @click="sendSelectedPopularRoutesMail"
                    >
                        <Send class="h-3.5 w-3.5 text-primary" />
                        <span>{{ isSendingRouteMail ? 'Sending...' : `Broadcast (${selectedRouteCount})` }}</span>
                    </Button>
                </div>

                <Button
                    class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
                    @click="router.push({ name: 'NewPopularRoutes' })"
                >
                    <Plus class="w-4 h-4" />
                    <span>Add Popular Route</span>
                </Button>
            </div>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Routes</span>
                    <Compass class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalRoutesCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">International</span>
                    <Globe2 class="h-4 w-4 text-sky-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-sky-700">
                    {{ internationalCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Domestic</span>
                    <Plane class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-emerald-700">
                    {{ domesticCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Selected</span>
                    <Check class="h-4 w-4 text-primary" />
                </div>
                <div class="mt-1 text-xl font-bold text-primary">
                    {{ selectedRouteCount }}
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
                        placeholder="Search destination, airport, or airline..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
                        @input="
                            router.push({
                                query: {
                                    ...route.query,
                                    search_query: searchQuery || undefined,
                                    page: undefined
                                }
                            });
                            fetchPopularRoutes();
                        "
                    />
                </div>

                <div v-if="selectedRouteCount > 0" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-primary/10 text-primary font-bold text-[11px]">
                        {{ selectedRouteCount }} selected
                    </span>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-slate-600 underline text-xs"
                        @click="selectedRouteIds = []"
                    >
                        Clear selection
                    </button>
                </div>
            </div>

            <!-- Loading Spinner -->
            <section v-if="isLoading" class="py-20 flex justify-center">
                <Spinner />
            </section>

            <!-- Empty State -->
            <section v-else-if="popularRoutes.length === 0" class="py-12">
                <NothingFound message="No popular routes found" />
            </section>

            <!-- Table View -->
            <section v-else>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50 border-b border-slate-200">
                            <TableRow>
                                <TableHead v-if="authUser?.role === 'admin'" class="w-10 pl-4">
                                    <Checkbox
                                        :checked="isAllVisibleSelected"
                                        aria-label="Select visible popular routes"
                                        @update:checked="(checked) => toggleVisibleSelection(checked === true)"
                                    />
                                </TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Route</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Type</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Journey & Class</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Airline</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Destination</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Pricing</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Dates / Stay</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Preview</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="routeItem in popularRoutes"
                                :key="routeItem.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell v-if="authUser?.role === 'admin'" class="w-10 pl-4">
                                    <Checkbox
                                        :checked="selectedRouteIds.includes(routeItem.id)"
                                        :aria-label="`Select ${routeItem.destination_name_en || 'popular route'}`"
                                        @update:checked="(checked) => setRouteSelected(routeItem.id, checked === true)"
                                    />
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1.5 font-bold text-slate-900 text-xs">
                                        <span>{{ routeItem.from_airport }}</span>
                                        <span class="text-slate-400">→</span>
                                        <span>{{ routeItem.to_airport }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" :class="[routeTypeBadgeClass(routeItem.type), 'rounded font-semibold text-[11px] px-2 py-0.5']">
                                        {{ routeItem.type === 'international' ? 'International' : 'Domestic' }}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col gap-1 items-start">
                                        <Badge variant="outline" :class="[journeyTypeBadgeClass(routeItem.journey_type), 'rounded font-semibold text-[11px] px-2 py-0.5']">
                                            {{ routeItem.journey_type === 'round' ? 'Round-trip' : 'One-way' }}
                                        </Badge>
                                        <span class="text-[11px] font-medium text-slate-500 uppercase tracking-tight">
                                            {{ routeItem.travel_class || 'Economy' }}
                                        </span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-xs font-medium text-slate-700">
                                        {{ routeItem.airline?.name || (routeItem.airline_id ? `Airline #${routeItem.airline_id}` : '—') }}
                                    </span>
                                </TableCell>
                                <TableCell class="max-w-[180px]">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold text-slate-800 truncate">{{ routeItem.destination_name_en }}</span>
                                        <span class="text-[11px] text-slate-400 truncate">{{ routeItem.destination_name_ar }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge variant="outline" :class="[priceTypeBadgeClass(routeItem.price_type), 'rounded font-semibold text-[11px] px-2 py-0.5']">
                                        {{ routeItem.price_type }}
                                    </Badge>
                                    <div v-if="routeItem.price_type === 'static'" class="text-xs font-bold text-slate-800 mt-1">
                                        {{ routeItem.static_price ? `PKR ${Number(routeItem.static_price).toLocaleString()}` : '—' }}
                                    </div>
                                </TableCell>
                                <TableCell class="text-xs">
                                    <div class="font-semibold text-slate-800">{{ getDepartureDate(routeItem) }}</div>
                                    <div v-if="getReturnDate(routeItem)" class="text-[11px] text-slate-500">
                                        Ret: {{ getReturnDate(routeItem) }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        +{{ routeItem.departure_plus_days ?? 1 }}d
                                        <span v-if="routeItem.stay_duration_days">/ {{ routeItem.stay_duration_days }}d stay</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div v-if="routeItem.image_url" class="w-14 h-10 rounded border border-slate-200 bg-slate-50 overflow-hidden flex-shrink-0">
                                        <img
                                            :src="routeItem.image_url"
                                            alt="Route preview"
                                            class="w-full h-full object-cover"
                                        />
                                    </div>
                                    <span v-else class="text-[11px] text-slate-400 italic">No image</span>
                                </TableCell>
                                <TableCell class="text-right whitespace-nowrap pr-4">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="router.push({ name: 'PopularRouteView', params: { id: routeItem.id } })"
                                        >
                                            <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            View
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="router.push({ name: 'PopularRouteEdit', params: { id: routeItem.id } })"
                                        >
                                            <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            Edit
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(routeItem)"
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

                <div v-if="routesData.total != null && routesData.per_page" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="routesData" @change="fetchPopularRoutes" />
                </div>
            </section>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Popular Route?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        This will permanently delete the route
                        <strong class="text-slate-900">{{ routeToDelete?.from_airport }} → {{ routeToDelete?.to_airport }}</strong>
                        ({{ routeToDelete?.destination_name_en }}). This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteRoute"
                    >
                        Delete Route
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
