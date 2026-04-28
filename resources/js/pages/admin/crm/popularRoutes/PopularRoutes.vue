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
    Eye,
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
import { DELETE_POPULAR_ROUTE, FETCH_POPULAR_ROUTES } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

// Assuming you created a Vuex module: popularRoutes
// Example state: routes = { data: [], meta: {...} }
const routesData = computed(() => store.getters['cms/popularRoutes']);
const popularRoutes = computed(() => routesData.value.data || []);
const isLoading = computed(() => store.state.popularRoutes?.isLoading || false);

const showDeleteDialog = ref(false);
const routeToDelete = ref(null);

const fetchPopularRoutes = debounce(() => {
    store.dispatch('cms/' + FETCH_POPULAR_ROUTES, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

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
            <Button
                @click="router.push({ name: 'Dashboard' })"
                variant="outline"
                size="sm"
            >
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <h1 class="text-3xl font-medium tracking-tight">Popular Routes</h1>
        </div>

        <Button
            v-if="authUser?.role === 'admin'"
            @click="router.push({ name: 'NewPopularRoutes' })"
            class="flex items-center gap-2"
        >
            <PlusCircleIcon class="w-5 h-5" />
            Add Popular Route
        </Button>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg border">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div class="relative w-full max-w-md">
                <Input
                    v-model="route.query.search_query"
                    @input="
                        router.push({
                            query: {
                                ...route.query,
                                search_query: $event.target.value || undefined,
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
                    <TableHeader class="bg-muted">
                        <TableRow>
                            <TableHead>From → To</TableHead>
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
                            <TableCell class="font-medium">
                                {{ route.from_airport }} → {{ route.to_airport }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">
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
                                <Badge :variant="route.price_type === 'dynamic' ? 'default' : 'secondary'">
                                    {{ route.price_type }}
                                </Badge>
                                <div v-if="route.price_type === 'static'" class="text-xs text-muted-foreground mt-1">
                                    {{ route.static_price ? `$${route.static_price}` : '—' }}
                                </div>
                            </TableCell>
                            <TableCell class="text-sm">
                                +{{ route.departure_plus_days }} days
                                <span v-if="route.stay_duration_days" class="text-muted-foreground">
                                    / stay {{ route.stay_duration_days }}d
                                </span>
                            </TableCell>
                            <TableCell>
                                <div v-if="route.image" class="w-16 h-12 bg-gray-100 rounded overflow-hidden">
                                    <img
                                        :src="route.image"
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
                                    @click="router.push({ name: 'PopularRouteView', query: { id: route.id } })"
                                >
                                    <Eye class="w-4 h-4 mr-1" />
                                    View
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'PopularRouteEdit', query: { id: route.id } })"
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