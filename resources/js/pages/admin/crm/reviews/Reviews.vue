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
    CheckCircle,
    XCircle,
    Star,
    MessageSquare,
    CheckCircle2,
    Clock,
} from "lucide-vue-next";

import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { debounce } from "lodash";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { Badge } from "@/components/ui/badge";
import { FETCH_REVIEWS, APPROVE_REVIEW, DELETE_REVIEW } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

// Vuex getters/state
const reviewsData = computed(() => store.getters['review/reviews']);
const reviewsList = computed(() => reviewsData.value?.data || []);
const isLoading = computed(() => store.state.review?.isLoading || false);

const showDeleteDialog = ref(false);
const reviewToDelete = ref(null);

const searchQuery = ref(route.query.search || "");
const statusFilter = ref(route.query.status || ""); // "approved", "pending" or ""

// Quick Metrics
const totalReviewsCount = computed(() => reviewsData.value?.meta?.total ?? reviewsList.value.length);
const approvedCount = computed(() => reviewsList.value.filter((r) => r.is_approved).length);
const pendingCount = computed(() => reviewsList.value.filter((r) => !r.is_approved).length);
const avgRating = computed(() => {
    if (!reviewsList.value.length) return "5.0";
    const sum = reviewsList.value.reduce((acc, r) => acc + Number(r.rating || 0), 0);
    return (sum / reviewsList.value.length).toFixed(1);
});

const fetchReviewsList = debounce(() => {
    store.dispatch('review/' + FETCH_REVIEWS, {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
        page: route.query.page || 1,
    });
}, 350);

watch(
    () => route.query,
    () => {
        fetchReviewsList();
    }
);

function changeStatusFilter(status) {
    statusFilter.value = status;
    router.push({
        query: {
            ...route.query,
            status: status || undefined,
            page: undefined
        }
    });
    fetchReviewsList();
}

function handleSearch() {
    router.push({
        query: {
            ...route.query,
            search: searchQuery.value || undefined,
            page: undefined
        }
    });
    fetchReviewsList();
}

function toggleApproval(reviewItem) {
    const nextStatus = !reviewItem.is_approved;
    store.dispatch('review/' + APPROVE_REVIEW, {
        id: reviewItem.id,
        is_approved: nextStatus
    })
    .then(() => {
        fetchReviewsList();
    })
    .catch((err) => {
        console.error("Status update error", err);
    });
}

function confirmDelete(reviewItem) {
    reviewToDelete.value = reviewItem;
    showDeleteDialog.value = true;
}

function deleteReview() {
    if (!reviewToDelete.value?.id) return;

    store.dispatch('review/' + DELETE_REVIEW, reviewToDelete.value.id)
        .then(() => {
            fetchReviewsList();
        })
        .finally(() => {
            showDeleteDialog.value = false;
            reviewToDelete.value = null;
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
    fetchReviewsList();
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
                        Customer Reviews
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Moderate customer feedback, ratings, and testimonials published on public booking pages
                    </p>
                </div>
            </div>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Reviews</span>
                    <MessageSquare class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalReviewsCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Pending</span>
                    <Clock class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-amber-700">
                    {{ pendingCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Approved</span>
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-emerald-700">
                    {{ approvedCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Avg Rating</span>
                    <Star class="h-4 w-4 fill-amber-400 text-amber-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ avgRating }} <span class="text-xs font-normal text-slate-400">/ 5.0</span>
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
                        placeholder="Search by reviewer name, email, or message..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
                        @input="handleSearch"
                    />
                </div>

                <!-- Status Tabs Filter -->
                <div class="inline-flex rounded border border-slate-200 bg-slate-50 p-0.5 text-xs font-semibold self-start sm:self-auto">
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded transition-colors"
                        :class="statusFilter === '' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatusFilter('')"
                    >
                        All Reviews
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded transition-colors"
                        :class="statusFilter === 'pending' ? 'bg-white text-amber-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatusFilter('pending')"
                    >
                        Pending ({{ pendingCount }})
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1.5 rounded transition-colors"
                        :class="statusFilter === 'approved' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                        @click="changeStatusFilter('approved')"
                    >
                        Approved
                    </button>
                </div>
            </div>

            <!-- Loading Spinner -->
            <section v-if="isLoading" class="py-20 flex justify-center">
                <Spinner />
            </section>

            <!-- Empty State -->
            <section v-else-if="reviewsList.length === 0" class="py-12">
                <NothingFound message="No reviews found matching your filters." />
            </section>

            <!-- Table View -->
            <section v-else>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader class="bg-slate-50 border-b border-slate-200">
                            <TableRow>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 pl-4 w-28">Date</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Reviewer</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 w-28">Rating</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 max-w-md">Feedback Message</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 w-28">Status</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4 w-44">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="review in reviewsList"
                                :key="review.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell class="pl-4 text-xs font-mono text-slate-600 whitespace-nowrap">
                                    {{ formatDate(review.created_at) }}
                                </TableCell>
                                <TableCell>
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-slate-900">{{ review.name }}</span>
                                        <span class="text-[11px] text-slate-400">{{ review.email || 'No email provided' }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-0.5">
                                        <Star
                                            v-for="star in 5"
                                            :key="star"
                                            class="w-3.5 h-3.5"
                                            :class="star <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-slate-200'"
                                        />
                                    </div>
                                </TableCell>
                                <TableCell class="max-w-md">
                                    <p class="text-xs text-slate-700 leading-relaxed whitespace-normal break-words">
                                        {{ review.message }}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="review.is_approved
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-amber-50 text-amber-700 border-amber-200'"
                                        class="rounded text-[11px] font-semibold px-2 py-0.5"
                                    >
                                        {{ review.is_approved ? 'Approved' : 'Pending' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right whitespace-nowrap pr-4">
                                    <div class="inline-flex items-center gap-1">
                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2.5 text-xs rounded transition-colors"
                                            :class="review.is_approved
                                                ? 'border-amber-200 text-amber-700 hover:bg-amber-50'
                                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                                            @click="toggleApproval(review)"
                                        >
                                            <component :is="review.is_approved ? XCircle : CheckCircle" class="w-3.5 h-3.5 mr-1" />
                                            {{ review.is_approved ? 'Revoke' : 'Approve' }}
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(review)"
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

                <div v-if="reviewsData.meta" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="reviewsData.meta" @change="fetchReviewsList" />
                </div>
            </section>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Review?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        Are you sure you want to permanently delete the review by <strong class="text-slate-900">{{ reviewToDelete?.name }}</strong>?
                        This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteReview"
                    >
                        Delete Review
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
