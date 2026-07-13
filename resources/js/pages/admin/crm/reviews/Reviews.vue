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
import { FETCH_REVIEWS, APPROVE_REVIEW, DELETE_REVIEW } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

// Vuex getters/state
const reviewsData = computed(() => store.getters['review/reviews']);
const reviewsList = computed(() => reviewsData.value.data || []);
const isLoading = computed(() => store.state.review?.isLoading || false);

const showDeleteDialog = ref(false);
const reviewToDelete = ref(null);

const searchQuery = ref(route.query.search || "");
const statusFilter = ref(route.query.status || ""); // "approved", "pending" or ""

const fetchReviewsList = debounce(() => {
    store.dispatch('review/' + FETCH_REVIEWS, {
        search: searchQuery.value || undefined,
        status: statusFilter.value || undefined,
        page: route.query.page || 1,
    });
}, 350);

// Watch for route query changes to fetch data correctly
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
            page: undefined // Reset page on filter change
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
            <h1 class="text-3xl font-medium tracking-tight flex items-center gap-2">
                <MessageSquare class="w-8 h-8 text-primary" />
                Reviews Management
            </h1>
        </div>
    </div>

    <!-- Filter Pills / Tabs -->
    <div class="flex gap-2 mb-4">
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === '' ? 'bg-primary text-white hover:bg-primary/95' : ''"
            @click="changeStatusFilter('')"
        >
            All Reviews
        </Button>
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === 'pending' ? 'bg-amber-600 text-white hover:bg-amber-700' : ''"
            @click="changeStatusFilter('pending')"
        >
            Pending Approval
        </Button>
        <Button 
            variant="outline" 
            size="sm" 
            :class="statusFilter === 'approved' ? 'bg-emerald-600 text-white hover:bg-emerald-700' : ''"
            @click="changeStatusFilter('approved')"
        >
            Approved
        </Button>
    </div>

    <div class="bg-white p-6 md:p-8 rounded-lg border shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div class="relative w-full max-w-md">
                <Input
                    v-model="searchQuery"
                    @input="handleSearch"
                    placeholder="Search by name, email or message content..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="reviewsList.length === 0">
            <NothingFound message="No reviews found matching your filters." />
        </section>

        <section v-else>
            <div class="overflow-x-auto rounded-md border border-gray-100">
                <Table>
                    <TableHeader class="bg-slate-50">
                        <TableRow>
                            <TableHead class="font-semibold text-slate-800">Date</TableHead>
                            <TableHead class="font-semibold text-slate-800">Reviewer</TableHead>
                            <TableHead class="font-semibold text-slate-800 w-28">Rating</TableHead>
                            <TableHead class="font-semibold text-slate-800 max-w-md">Message</TableHead>
                            <TableHead class="font-semibold text-slate-800 w-32">Status</TableHead>
                            <TableHead class="font-semibold text-slate-800 text-right w-44">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="review in reviewsList" :key="review.id" class="hover:bg-slate-50/55 transition-colors duration-150">
                            <TableCell class="text-sm text-slate-600 font-medium whitespace-nowrap">
                                {{ formatDate(review.created_at) }}
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col">
                                    <span class="font-semibold text-slate-800">{{ review.name }}</span>
                                    <span class="text-slate-500 text-xs">{{ review.email || 'No email provided' }}</span>
                                </div>
                            </TableCell>
                            <TableCell>
                                <div class="flex items-center gap-0.5">
                                    <Star 
                                        v-for="star in 5" 
                                        :key="star"
                                        class="w-4 h-4"
                                        :class="star <= review.rating ? 'fill-amber-400 text-amber-400' : 'text-gray-200'"
                                    />
                                </div>
                            </TableCell>
                            <TableCell class="max-w-md">
                                <p class="text-slate-700 text-sm whitespace-normal leading-relaxed break-words">
                                    {{ review.message }}
                                </p>
                            </TableCell>
                            <TableCell>
                                <Badge 
                                    :class="review.is_approved 
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-50' 
                                        : 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-50'"
                                    variant="outline"
                                >
                                    {{ review.is_approved ? 'Approved' : 'Pending' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right whitespace-nowrap space-x-2">
                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    :class="review.is_approved 
                                        ? 'border-amber-300 text-amber-700 hover:bg-amber-50/70 hover:text-amber-800' 
                                        : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50/70 hover:text-emerald-800'"
                                    @click="toggleApproval(review)"
                                >
                                    <component :is="review.is_approved ? XCircle : CheckCircle" class="w-4 h-4 mr-1" />
                                    {{ review.is_approved ? 'Disapprove' : 'Approve' }}
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                    @click="confirmDelete(review)"
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
            <div v-if="reviewsData.meta" class="flex justify-end pt-6">
                <Pagination :meta="reviewsData.meta" @change="fetchReviewsList" />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Review?</AlertDialogTitle>
                <AlertDialogDescription>
                    Are you sure you want to delete the review by <strong>{{ reviewToDelete?.name }}</strong>? This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteReview"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
