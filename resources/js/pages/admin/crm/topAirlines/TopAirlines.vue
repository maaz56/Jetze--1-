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
import { DELETE_TOP_AIRLINE, FETCH_TOP_AIRLINES } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

const airlinesData = computed(() => store.getters['cms/topAirlines']);
const topAirlines = computed(() => airlinesData.value.data || []);
const isLoading = computed(() => store.state.cms?.isLoading || false);

const showDeleteDialog = ref(false);
const airlineToDelete = ref(null);
const searchQuery = ref(route.query.search_query || "");

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
            <h1 class="text-3xl font-medium tracking-tight">Top Airlines</h1>
        </div>

        <Button
            v-if="authUser?.role === 'admin'"
            @click="router.push({ name: 'NewTopAirline' })"
            class="flex items-center gap-2"
        >
            <PlusCircleIcon class="w-5 h-5" />
            Add Top Airline
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
                        fetchTopAirlines();
                    "
                    placeholder="Search by airline name..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="topAirlines.length === 0">
            <NothingFound message="No top airlines found" />
        </section>

        <section v-else>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader class="bg-primary">
                        <TableRow>
                            <TableHead>Logo</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Airline Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="airline in topAirlines" :key="airline.id" class="hover:bg-gray-50">
                            <TableCell>
                                <div v-if="airline.image_url" class="w-12 h-12 bg-gray-100 rounded overflow-hidden">
                                    <img
                                        :src="airline.image_url"
                                        alt="Airline logo"
                                        class="w-full h-full object-contain"
                                    />
                                </div>
                                <span v-else class="text-muted-foreground text-xs">No image</span>
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ airline.name }}
                            </TableCell>
                            <TableCell>
                                <Badge :variant="airline.type === 'domestic' ? 'default' : 'secondary'">
                                    Airline Type: {{ airline.type === 'international' ? 'International' : 'Domestic' }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="airline.is_active ? 'success' : 'destructive'">
                                    {{ airline.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="whitespace-nowrap space-x-2">
                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'EditTopAirline', params: { id: airline.id } })"
                                >
                                    <Pencil class="w-4 h-4 mr-1" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmDelete(airline)"
                                >
                                    <Trash2 class="w-4 h-4 mr-1" />
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="airlinesData.meta" class="flex justify-end pt-6">
                <Pagination :meta="airlinesData.meta" @change="fetchTopAirlines" />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Top Airline?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will permanently delete <strong>{{ airlineToDelete?.name }}</strong>.
                    This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteAirline"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
