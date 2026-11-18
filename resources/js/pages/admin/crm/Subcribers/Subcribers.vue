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
    Plus,
    Users,
    CheckCircle,
    XCircle,
    CheckCircle2,
    Pencil,
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

// Quick Metrics
const totalSubscribersCount = computed(() => subscribersData.value?.meta?.total ?? subscribersList.value.length);
const activeCount = computed(() => subscribersList.value.filter((s) => s.is_active).length);
const inactiveCount = computed(() => subscribersList.value.filter((s) => !s.is_active).length);

const fetchSubscribersList = debounce(() => {
    store.dispatch('newsletter/' + FETCH_SUBSCRIBERS, {
        search: searchQuery.value || undefined,
        page: route.query.page || 1,
    });
}, 350);

watch(
    () => route.query,
    () => {
        fetchSubscribersList();
    }
);

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

    store.dispatch('newsletter/' + SAVE_SUBSCRIBER, formData.value)
        .then(() => {
            showCreateDialog.value = false;
            fetchSubscribersList();
            toast("Subscriber added successfully", { type: "success" });
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

    store.dispatch('newsletter/' + UPDATE_SUBSCRIBER, formData.value)
        .then(() => {
            showEditDialog.value = false;
            fetchSubscribersList();
            toast("Subscriber updated successfully", { type: "success" });
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
            toast.success("Subscriber deleted successfully");
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
    <div class="space-y-5">
        <!-- Top Bar Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 px-2.5 text-xs rounded border-slate-200 text-slate-600 hover:bg-slate-100 max-sm:hidden"
                    @click="router.back()"
                >
                    <ArrowLeft class="w-3.5 h-3.5 mr-1" />
                    Back
                </Button>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Newsletter Subscribers
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Manage marketing subscribers, email list subscriptions, and communication status
                    </p>
                </div>
            </div>

            <Button
                v-if="authUser?.role === 'admin'"
                class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5 self-start sm:self-auto"
                @click="openCreateDialog"
            >
                <Plus class="w-4 h-4" />
                <span>Add Subscriber</span>
            </Button>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Subscribers</span>
                    <Users class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalSubscribersCount }}
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
                    <span class="text-[11px] font-bold uppercase tracking-wider">Inactive</span>
                    <XCircle class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-600">
                    {{ inactiveCount }}
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
                        placeholder="Search by email or subscriber name..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
                        @input="handleSearch"
                    />
                </div>
            </div>

            <!-- Loading Spinner -->
            <section v-if="isLoading" class="py-20 flex justify-center">
                <Spinner />
            </section>

            <!-- Empty State -->
            <section v-else-if="subscribersList.length === 0" class="py-12">
                <NothingFound message="No subscribers found matching your search." />
            </section>

            <!-- Table View -->
            <section v-else>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50 border-b border-slate-200">
                            <TableRow>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 pl-4 w-32">Subscribed On</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Email Address</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Name</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 w-28">Status</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4 w-48">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="subscriber in subscribersList"
                                :key="subscriber.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell class="pl-4 text-xs font-mono text-slate-600 whitespace-nowrap">
                                    {{ formatDate(subscriber.subscribed_at || subscriber.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Mail class="w-3.5 h-3.5 text-slate-400 flex-shrink-0" />
                                        <span class="text-xs font-semibold text-slate-900">{{ subscriber.email }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="text-xs text-slate-700">{{ subscriber.name || '—' }}</span>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="subscriber.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'"
                                        class="rounded text-[11px] font-semibold px-2 py-0.5"
                                    >
                                        {{ subscriber.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right whitespace-nowrap pr-4">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded transition-colors"
                                            :class="subscriber.is_active
                                                ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                                            @click="toggleStatus(subscriber)"
                                        >
                                            <component :is="subscriber.is_active ? XCircle : CheckCircle" class="w-3.5 h-3.5 mr-1" />
                                            {{ subscriber.is_active ? 'Deactivate' : 'Activate' }}
                                        </Button>

                                        <Button
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="openEditDialog(subscriber)"
                                        >
                                            <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            Edit
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(subscriber)"
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

                <div v-if="subscribersData.meta" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="subscribersData.meta" @change="fetchSubscribersList" />
                </div>
            </section>
        </div>

        <!-- Create Subscriber Dialog -->
        <AlertDialog :open="showCreateDialog" @update:open="showCreateDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Add New Subscriber</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        Add an email address to the newsletter distribution list.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="space-y-3.5 py-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Email Address *</label>
                        <Input v-model="formData.email" type="email" placeholder="subscriber@example.com" class="h-9 text-xs rounded border-slate-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Subscriber Name (Optional)</label>
                        <Input v-model="formData.name" placeholder="John Doe" class="h-9 text-xs rounded border-slate-200" />
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input id="create_active" type="checkbox" v-model="formData.is_active" class="h-4 w-4 accent-primary rounded" />
                        <label for="create_active" class="text-xs font-medium text-slate-700 cursor-pointer">Active Subscription</label>
                    </div>
                </div>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction class="rounded bg-primary text-white hover:bg-primary/90 text-xs font-semibold" @click="saveSubscriber">
                        Save Subscriber
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Edit Subscriber Dialog -->
        <AlertDialog :open="showEditDialog" @update:open="showEditDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Edit Subscriber</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        Update subscriber details and delivery preferences.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div class="space-y-3.5 py-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Email Address *</label>
                        <Input v-model="formData.email" type="email" placeholder="subscriber@example.com" class="h-9 text-xs rounded border-slate-200" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-700 mb-1 block">Subscriber Name (Optional)</label>
                        <Input v-model="formData.name" placeholder="John Doe" class="h-9 text-xs rounded border-slate-200" />
                    </div>
                    <div class="flex items-center gap-2 pt-1">
                        <input id="edit_active" type="checkbox" v-model="formData.is_active" class="h-4 w-4 accent-primary rounded" />
                        <label for="edit_active" class="text-xs font-medium text-slate-700 cursor-pointer">Active Subscription</label>
                    </div>
                </div>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction class="rounded bg-primary text-white hover:bg-primary/90 text-xs font-semibold" @click="updateSubscriber">
                        Update Subscriber
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Subscriber?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        Are you sure you want to delete <strong class="text-slate-900">{{ subscriberToDelete?.email }}</strong>? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteSubscriber"
                    >
                        Delete Subscriber
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
