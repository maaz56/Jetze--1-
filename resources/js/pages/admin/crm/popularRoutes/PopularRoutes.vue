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
    PlusCircleIcon,
    Eye,
    Send,
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

// Assuming you created a Vuex module: popularRoutes
// Example state: routes = { data: [], meta: {...} }
const routesData = computed(() => store.getters['cms/popularRoutes']);
const popularRoutes = computed(() => routesData.value.data || []);
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

function routeTypeBadgeClass(type) {
    return type === 'domestic'
        ? 'bg-green-100 text-green-800 border-green-200 hover:bg-green-100'
        : 'bg-blue-100 text-blue-800 border-blue-200 hover:bg-blue-100';
}

function journeyTypeBadgeClass(type) {
    return type === 'round'
        ? 'bg-purple-100 text-purple-800 border-purple-200 hover:bg-purple-100'
        : 'bg-amber-100 text-amber-800 border-amber-200 hover:bg-amber-100';
}

function priceTypeBadgeClass(type) {
    return type === 'dynamic'
        ? 'bg-emerald-100 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
        : 'bg-slate-100 text-slate-800 border-slate-200 hover:bg-slate-100';
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
        toast.error("Please select at least one popular route.");
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
        // Store action already shows the API error toast.
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
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <Button max-sm:hidden
                @click="router.push({ name: 'Dashboard' })"
                variant="outline"
                size="sm"
            >
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <h1 class="text-3xl font-medium tracking-tight">Popular Routes</h1>
        </div>

        <div v-if="authUser?.role === 'admin'" class="flex flex-wrap items-center gap-3">
            <Select v-model="mailAudience">
                <SelectTrigger class="h-9 w-[170px] bg-white">
                    <SelectValue placeholder="Send to" />
                </SelectTrigger>
                <SelectContent class="bg-white">
                    <SelectItem value="customers">Customers</SelectItem>
                    <SelectItem value="subscribers">Subscribers</SelectItem>
                    <SelectItem value="both">Both</SelectItem>
                </SelectContent>
            </Select>

            <Button
                variant="outline"
                class="h-9 flex items-center gap-2"
                :disabled="selectedRouteCount === 0 || isSendingRouteMail"
                @click="sendSelectedPopularRoutesMail"
            >
                <Send class="h-4 w-4" />
                {{ isSendingRouteMail ? 'Queuing...' : `Send Mail (${selectedRouteCount})` }}
            </Button>

            <Button
                @click="router.push({ name: 'NewPopularRoutes' })"
                class="flex items-center gap-2"
            >
                <PlusCircleIcon class="w-5 h-5" />
                Add Popular Route
            </Button>
        </div>
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
                        fetchPopularRoutes();
                    "
                    placeholder="Search by destination, airport or airline..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>

            <div class="flex items-center gap-3">
                <!-- You can add more filters here later (journey_type, travel_class, price_type, etc.) -->
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="popularRoutes.length === 0">
            <NothingFound message="No popular routes found" />
        </section>

        <section v-else>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader class="bg-primary">
                        <TableRow>
                            <TableHead v-if="authUser?.role === 'admin'" class="w-12">
                                <Checkbox
                                    :checked="isAllVisibleSelected"
                                    aria-label="Select visible popular routes"
                                    @update:checked="(checked) => toggleVisibleSelection(checked === true)"
                                />
                            </TableHead>
                            <TableHead>From → To</TableHead>
                            <TableHead>Airline Type</TableHead>
                            <TableHead>Journey & Class</TableHead>
                            <TableHead>Airline</TableHead>
                            <TableHead>Destination (EN/AR)</TableHead>
                            <TableHead>Price Type</TableHead>
                            <TableHead>Days / Stay</TableHead>
                            <TableHead>Image</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="route in popularRoutes" :key="route.id" class="hover:bg-gray-50">
                            <TableCell v-if="authUser?.role === 'admin'" class="w-12">
                                <Checkbox
                                    :checked="selectedRouteIds.includes(route.id)"
                                    :aria-label="`Select ${route.destination_name_en || 'popular route'}`"
                                    @update:checked="(checked) => setRouteSelected(route.id, checked === true)"
                                />
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ route.from_airport }} → {{ route.to_airport }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="routeTypeBadgeClass(route.type)">
                                    {{ route.type === 'international' ? 'International' : 'Domestic' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="journeyTypeBadgeClass(route.journey_type)">
                                    {{ route.journey_type === 'round' ? 'Round-trip' : 'One-way' }}
                                </Badge>
                                <span class="ml-2 text-xs text-muted-foreground">
                                    {{ route.travel_class }}
                                </span>
                            </TableCell>
                            <TableCell>
                                {{ route.airline?.name || route.airline_id ? `ID: ${route.airline_id}` : '—' }}
                            </TableCell>
                            <TableCell class="max-w-xs">
                                <div class="flex flex-col text-sm">
                                    <span>{{ route.destination_name_en }}</span>
                                    <span class="text-muted-foreground text-xs">{{ route.destination_name_ar }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline" :class="priceTypeBadgeClass(route.price_type)">
                                    {{ route.price_type }}
                                </Badge>
                                <div v-if="route.price_type === 'static'" class="text-xs text-muted-foreground mt-1">
                                    {{ route.static_price ? `PKR ${route.static_price}` : '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="text-sm">
                                <div class="font-medium text-gray-900">{{ getDepartureDate(route) }}</div>
                                <div v-if="getReturnDate(route)" class="text-xs text-gray-700">
                                    Return {{ getReturnDate(route) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    +{{ route.departure_plus_days ?? 1 }} days
                                    <span v-if="route.stay_duration_days">
                                        / stay {{ route.stay_duration_days }}d
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div v-if="route.image_url" class="w-16 h-12 bg-gray-100 rounded overflow-hidden">
                                    <img
                                        :src="route.image_url"
                                        alt="Route preview"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <span v-else class="text-muted-foreground text-xs">No image</span>
                            </TableCell>
                            <TableCell class="whitespace-nowrap space-x-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'PopularRouteView', params: { id: route.id } })"
                                >
                                    <Eye class="w-4 h-4 mr-1" />
                                    View
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'PopularRouteEdit', params: { id: route.id } })"
                                >
                                    <Pencil class="w-4 h-4 mr-1" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmDelete(route)"
                                >
                                    <Trash2 class="w-4 h-4 mr-1" />
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="routesData.meta" class="flex justify-end pt-6">
                <Pagination :meta="routesData.meta" @change="fetchPopularRoutes" />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Popular Route?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will permanently delete the route
                    <strong>{{ routeToDelete?.from_airport }} → {{ routeToDelete?.to_airport }}</strong>
                    ({{ routeToDelete?.destination_name_en }}).
                    This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteRoute"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
