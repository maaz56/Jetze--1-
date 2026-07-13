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
    Trash2,
    Mail,
    MailPlus,
    Users,
    UserPlus,
    CheckCircle,
    XCircle,
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { Badge } from "@/components/ui/badge";
import { 
    FETCH_SUBSCRIBERS, 
    SAVE_SUBSCRIBER, 
    UPDATE_SUBSCRIBER, 
    DELETE_SUBSCRIBER 
} from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

// Vuex getters/state
const subscribersData = computed(() => store.getters['newsletter/subscribers']);
const subscribersList = computed(() => {
    if (Array.isArray(subscribersData.value)) {
        return subscribersData.value;
    }

    return subscribersData.value?.data || [];
});
const isLoading = computed(() => store.state.newsletter?.isLoading || false);

// Dialog states
const showDeleteDialog = ref(false);
const subscriberToDelete = ref(null);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);

// Form data
const formData = ref({
    id: null,
    email: "",
    name: "",
    is_active: true
});

// Filters
const searchQuery = ref(route.query.search || "");
// const statusFilter = ref(route.query.status || ""); // Commented out - not using status filter

const fetchSubscribersList = debounce(() => {
    store.dispatch('newsletter/' + FETCH_SUBSCRIBERS, {
        search: searchQuery.value || undefined,
        // status: statusFilter.value || undefined, // Commented out
        page: route.query.page || 1,
    });
}, 350);

// Watch for route query changes
watch(
    () => route.query,
    () => {
        fetchSubscribersList();
    }
);

// Commented out - not using status filter
// function changeStatusFilter(status) {
//     statusFilter.value = status;
//     router.push({
//         query: {
//             ...route.query,
//             status: status || undefined,
//             page: undefined
//         }
//     });
//     fetchSubscribersList();
// }

function handleSearch() {
    router.push({
        query: {
            ...route.query,
            search: searchQuery.value || undefined,
            page: undefined
        }
    });
    fetchSubscribersList();
}

function openCreateDialog() {
    formData.value = {
        id: null,
        email: "",
        name: "",
        is_active: true
    };
    showCreateDialog.value = true;
}

function openEditDialog(subscriber) {
    formData.value = {
        id: subscriber.id,
        email: subscriber.email,
        name: subscriber.name || "",
        is_active: subscriber.is_active
    };
    showEditDialog.value = true;
}

function saveSubscriber() {
    if (!formData.value.email) {
        toast("Email is required", { type: "error" });
        return;
    }

    store.dispatch('newsletter/' + SAVE_SUBSCRIBER, {
        email: formData.value.email,
        name: formData.value.name,
        is_active: formData.value.is_active
    })
    .then(() => {
        showCreateDialog.value = false;
        fetchSubscribersList();
        formData.value = { id: null, email: "", name: "", is_active: true };
    })
    .catch((err) => {
        console.error("Save error", err);
    });
}

function updateSubscriber() {
    if (!formData.value.email) {
        toast("Email is required", { type: "error" });
        return;
    }

    store.dispatch('newsletter/' + UPDATE_SUBSCRIBER, {
        id: formData.value.id,
        email: formData.value.email,
        name: formData.value.name,
        is_active: formData.value.is_active
    })
    .then(() => {
        showEditDialog.value = false;
        fetchSubscribersList();
    })
    .catch((err) => {
        console.error("Update error", err);
    });
}

function toggleStatus(subscriber) {
    store.dispatch('newsletter/' + UPDATE_SUBSCRIBER, {
        id: subscriber.id,
        email: subscriber.email,
        name: subscriber.name,
        is_active: !subscriber.is_active
    })
    .then(() => {
        fetchSubscribersList();
        // toast(`Subscriber ${!subscriber.is_active ? 'activated' : 'deactivated'} successfully`, {
        //     type: "success"
        // });
    })
    .catch((err) => {
        console.error("Status update error", err);
    });
}

function confirmDelete(subscriber) {
    subscriberToDelete.value = subscriber;
    showDeleteDialog.value = true;
}

function deleteSubscriber() {
    if (!subscriberToDelete.value?.id) return;

    store.dispatch('newsletter/' + DELETE_SUBSCRIBER, subscriberToDelete.value.id)
        .then(() => {
            fetchSubscribersList();
            toast("Subscriber deleted successfully", { type: "success" });
        })
        .finally(() => {
            showDeleteDialog.value = false;
            subscriberToDelete.value = null;
        });
}

function formatDate(dateStr) {
    if (!dateStr) return "N/A";
    const date = new Date(dateStr);
    return date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

onMounted(() => {
    fetchSubscribersList();
});
</script>

<template>
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-4">
            <Button
                @click="router.back()"
                variant="outline"
                size="sm"
            >
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <h1 class="text-3xl font-medium tracking-tight flex items-center gap-2">
                <Users class="w-8 h-8 text-primary" />
                Newsletter Subscribers
            </h1>
        </div>
        <Button @click="openCreateDialog" size="sm">
            <UserPlus class="w-4 h-4 mr-1" />
            Add Subscriber
        </Button>
    </div>

    <!-- Filter Pills / Tabs - Commented out -->
    <!-- <div class="flex gap-2 mb-4">
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === '' ? 'bg-primary text-white hover:bg-primary/95' : ''"
            @click="changeStatusFilter('')"
        >
            All Subscribers
        </Button>
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === 'active' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : ''"
            @click="changeStatusFilter('active')"
        >
            Active
        </Button>
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === 'inactive' ? 'bg-gray-600 text-white hover:bg-gray-700' : ''"
            @click="changeStatusFilter('inactive')"
        >
            Inactive
        </Button>
    </div> -->

    <div class="bg-white p-6 md:p-8 rounded-lg border shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div class="relative w-full max-w-md">
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    placeholder="Search by email or name..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="subscribersList.length === 0">
            <NothingFound message="No subscribers found matching your filters." />
        </section>

        <section v-else>
            <div class="overflow-x-auto rounded-md border border-gray-100">
                <Table>
                    <TableHeader class="bg-slate-50">
                        <TableRow>
                            <TableHead class="font-semibold text-slate-800">Subscribed On</TableHead>
                            <TableHead class="font-semibold text-slate-800">Email</TableHead>
                            <TableHead class="font-semibold text-slate-800">Name</TableHead>
                            <TableHead class="font-semibold text-slate-800 w-32">Status</TableHead>
                            <TableHead class="font-semibold text-slate-800 text-right w-44">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="subscriber in subscribersList" :key="subscriber.id" class="hover:bg-slate-50/55 transition-colors duration-150">
                            <TableCell class="text-sm text-slate-600 font-medium whitespace-nowrap">
                                {{ formatDate(subscriber.subscribed_at || subscriber.created_at) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-2">
                                    <Mail class="w-4 h-4 text-slate-400" />
                                    <span class="font-medium text-slate-800">{{ subscriber.email }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <span class="text-slate-700">{{ subscriber.name || '—' }}</span>
                            </TableCell>
                            <TableCell>
                                <Badge 
                                    :class="subscriber.is_active 
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-50' 
                                        : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-50'"
                                    variant="outline"
                                >
                                    {{ subscriber.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right whitespace-nowrap space-x-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :class="subscriber.is_active 
                                        ? 'border-amber-300 text-amber-700 hover:bg-amber-50/70' 
                                        : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50/70'"
                                    @click="toggleStatus(subscriber)"
                                >
                                    <component :is="subscriber.is_active ? XCircle : CheckCircle" class="w-4 h-4 mr-1" />
                                    {{ subscriber.is_active ? 'Deactivate' : 'Activate' }}
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="border-blue-200 text-blue-600 hover:bg-blue-50"
                                    @click="openEditDialog(subscriber)"
                                >
                                    <MailPlus class="w-4 h-4 mr-1" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="border-red-200 text-red-600 hover:bg-red-50"
                                    @click="confirmDelete(subscriber)"
                                >
                                    <Trash2 class="w-4 h-4 mr-1" />
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Pagination -->
            <div v-if="subscribersData.meta" class="flex justify-end pt-6">
                <Pagination :meta="subscribersData.meta" @change="fetchSubscribersList" />
            </div>
        </section>
    </div>

    <!-- Create Subscriber Dialog -->
    <AlertDialog :open="showCreateDialog" @update:open="showCreateDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Add New Subscriber</AlertDialogTitle>
                <AlertDialogDescription>
                    Enter the details to add a new newsletter subscriber.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-4 py-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Email *</label>
                    <Input v-model="formData.email" type="email" placeholder="subscriber@example.com" />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Name (Optional)</label>
                    <Input v-model="formData.name" placeholder="John Doe" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="formData.is_active" class="w-4 h-4" />
                    <label class="text-sm font-medium">Active</label>
                </div>
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="saveSubscriber">
                    Save Subscriber
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Edit Subscriber Dialog -->
    <AlertDialog :open="showEditDialog" @update:open="showEditDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Edit Subscriber</AlertDialogTitle>
                <AlertDialogDescription>
                    Update subscriber information.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <div class="space-y-4 py-4">
                <div>
                    <label class="text-sm font-medium mb-1 block">Email *</label>
                    <Input v-model="formData.email" type="email" placeholder="subscriber@example.com" />
                </div>
                <div>
                    <label class="text-sm font-medium mb-1 block">Name (Optional)</label>
                    <Input v-model="formData.name" placeholder="John Doe" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="formData.is_active" class="w-4 h-4" />
                    <label class="text-sm font-medium">Active</label>
                </div>
            </div>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="updateSubscriber">
                    Update Subscriber
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Subscriber?</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete <strong>{{ subscriberToDelete?.email }}</strong>? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteSubscriber"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
