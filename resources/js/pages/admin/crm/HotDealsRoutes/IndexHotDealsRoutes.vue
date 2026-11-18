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
    Flame,
    Tag,
    CheckCircle2,
    XCircle,
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
import { FETCH_HOT_DEALS, DELETE_HOT_DEAL, SEND_HOT_DEALS_MAIL } from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const authUser = computed(() => authStore.user);

const hotDealsData = computed(() => store.getters['hotDeals/hotDeals']);
const hotDeals = computed(() => hotDealsData.value.data || []);
const isLoading = computed(() => store.state.hotDeals?.isLoading || false);

const showDeleteDialog = ref(false);
const dealToDelete = ref(null);
const searchQuery = ref(route.query.search_query || "");
const selectedDealIds = ref([]);
const mailAudience = ref("customers");
const isSendingDealMail = ref(false);

const selectedDealCount = computed(() => selectedDealIds.value.length);
const isAllVisibleSelected = computed(() => {
    return hotDeals.value.length > 0 && hotDeals.value.every((deal) => selectedDealIds.value.includes(deal.id));
});

// Quick Metrics
const totalDealsCount = computed(() => hotDealsData.value?.meta?.total ?? hotDeals.value.length);
const activeDealsCount = computed(() => hotDeals.value.filter((d) => d.is_active).length);
const inactiveDealsCount = computed(() => hotDeals.value.filter((d) => !d.is_active).length);

const fetchHotDeals = debounce(() => {
    store.dispatch('hotDeals/' + FETCH_HOT_DEALS, {
        search_query: route.query.search_query || undefined,
        page: route.query.page || 1,
    });
}, 350);

function setDealSelected(dealId, checked) {
    if (checked) {
        selectedDealIds.value = Array.from(new Set([...selectedDealIds.value, dealId]));
        return;
    }

    selectedDealIds.value = selectedDealIds.value.filter((id) => id !== dealId);
}

function toggleVisibleSelection(checked) {
    const visibleIds = hotDeals.value.map((deal) => deal.id);

    if (checked) {
        selectedDealIds.value = Array.from(new Set([...selectedDealIds.value, ...visibleIds]));
        return;
    }

    selectedDealIds.value = selectedDealIds.value.filter((id) => !visibleIds.includes(id));
}

async function sendSelectedHotDealsMail() {
    if (!selectedDealIds.value.length) {
        toast.error("Please select at least one hot deal.");
        return;
    }

    isSendingDealMail.value = true;
    try {
        await store.dispatch("hotDeals/" + SEND_HOT_DEALS_MAIL, {
            deal_ids: selectedDealIds.value,
            audience: mailAudience.value,
        });
        selectedDealIds.value = [];
    } catch (error) {
        // Handled by store
    } finally {
        isSendingDealMail.value = false;
    }
}

function confirmDelete(deal) {
    dealToDelete.value = deal;
    showDeleteDialog.value = true;
}

function deleteDeal() {
    if (!dealToDelete.value?.id) return;

    store.dispatch('hotDeals/' + DELETE_HOT_DEAL, dealToDelete.value.id)
        .then(() => {
            toast.success("Hot deal deleted successfully");
            fetchHotDeals();
        })
        .catch(() => {
            toast.error("Failed to delete hot deal");
        })
        .finally(() => {
            showDeleteDialog.value = false;
            dealToDelete.value = null;
        });
}

onMounted(() => {
    fetchHotDeals();
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
                        Hot Deals
                    </h1>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Manage time-limited promotions, discounted flight offers, and email campaigns
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
                        :disabled="selectedDealCount === 0 || isSendingDealMail"
                        @click="sendSelectedHotDealsMail"
                    >
                        <Send class="h-3.5 w-3.5 text-primary" />
                        <span>{{ isSendingDealMail ? 'Sending...' : `Broadcast (${selectedDealCount})` }}</span>
                    </Button>
                </div>

                <Button
                    class="h-9 px-3.5 text-xs font-semibold rounded bg-primary text-white hover:bg-primary/90 shadow-xs flex items-center gap-1.5"
                    @click="router.push({ name: 'HotDealCreate' })"
                >
                    <Plus class="w-4 h-4" />
                    <span>Add Hot Deal</span>
                </Button>
            </div>
        </div>

        <!-- KPI Metrics Ribbon -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Total Deals</span>
                    <Flame class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-900">
                    {{ totalDealsCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Active Deals</span>
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                </div>
                <div class="mt-1 text-xl font-bold text-emerald-700">
                    {{ activeDealsCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Inactive</span>
                    <XCircle class="h-4 w-4 text-slate-400" />
                </div>
                <div class="mt-1 text-xl font-bold text-slate-600">
                    {{ inactiveDealsCount }}
                </div>
            </div>

            <div class="rounded border border-slate-200 bg-white p-3.5 shadow-xs">
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px] font-bold uppercase tracking-wider">Selected</span>
                    <Check class="h-4 w-4 text-primary" />
                </div>
                <div class="mt-1 text-xl font-bold text-primary">
                    {{ selectedDealCount }}
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
                        placeholder="Search by title, origin or destination..."
                        class="pl-9 h-9 text-xs rounded border-slate-200 bg-slate-50 focus:bg-white"
                        @input="
                            router.push({
                                query: {
                                    ...route.query,
                                    search_query: searchQuery || undefined,
                                    page: undefined
                                }
                            });
                            fetchHotDeals();
                        "
                    />
                </div>

                <div v-if="selectedDealCount > 0" class="flex items-center gap-2 text-xs font-medium text-slate-600">
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-primary/10 text-primary font-bold text-[11px]">
                        {{ selectedDealCount }} selected
                    </span>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-slate-600 underline text-xs"
                        @click="selectedDealIds = []"
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
            <section v-else-if="hotDeals.length === 0" class="py-12">
                <NothingFound message="No hot deals found" />
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
                                        aria-label="Select visible hot deals"
                                        @update:checked="(checked) => toggleVisibleSelection(checked === true)"
                                    />
                                </TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Title</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Route</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Tag</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Original Price</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Deal Price</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Discount</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Status</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Order</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600">Preview</TableHead>
                                <TableHead class="text-xs font-bold uppercase tracking-wider text-slate-600 text-right pr-4">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="deal in hotDeals"
                                :key="deal.id"
                                class="border-b border-slate-100 hover:bg-slate-50/70 transition-colors"
                            >
                                <TableCell v-if="authUser?.role === 'admin'" class="w-10 pl-4">
                                    <Checkbox
                                        :checked="selectedDealIds.includes(deal.id)"
                                        :aria-label="`Select ${deal.title || 'hot deal'}`"
                                        @update:checked="(checked) => setDealSelected(deal.id, checked === true)"
                                    />
                                </TableCell>
                                <TableCell class="max-w-[200px]">
                                    <span class="font-semibold text-slate-900 text-xs block truncate" :title="deal.title">
                                        {{ deal.title }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <div class="flex items-center gap-1 font-bold text-slate-800 text-xs">
                                        <span>{{ deal.from_airport }}</span>
                                        <span class="text-slate-400">→</span>
                                        <span>{{ deal.to_airport }}</span>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="deal.tag"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-semibold"
                                    >
                                        <Tag class="w-3 h-3 text-amber-500" />
                                        {{ deal.tag }}
                                    </span>
                                    <span v-else class="text-slate-400 text-xs">—</span>
                                </TableCell>
                                <TableCell class="text-xs text-slate-400 line-through">
                                    PKR {{ Number(deal.original_price || 0).toLocaleString() }}
                                </TableCell>
                                <TableCell class="text-xs font-bold text-emerald-700">
                                    PKR {{ Number(deal.discounted_price || 0).toLocaleString() }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        class="rounded bg-rose-50 text-rose-700 border-rose-200 text-[11px] font-bold px-2 py-0.5"
                                    >
                                        {{ deal.discount_percentage }}% OFF
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        :class="deal.is_active
                                            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                            : 'bg-slate-100 text-slate-600 border-slate-200'"
                                        class="rounded text-[11px] font-semibold px-2 py-0.5"
                                    >
                                        {{ deal.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-xs font-mono text-slate-600">
                                    #{{ deal.display_order }}
                                </TableCell>
                                <TableCell>
                                    <div v-if="deal.image_url" class="w-12 h-10 rounded border border-slate-200 bg-slate-50 overflow-hidden flex-shrink-0">
                                        <img
                                            :src="deal.image_url"
                                            :alt="deal.title"
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
                                            @click="router.push({ name: 'HotDealView', params: { id: deal.id } })"
                                        >
                                            <Eye class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            View
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-slate-200 text-slate-700 hover:bg-slate-50"
                                            @click="router.push({ name: 'HotDealEdit', params: { id: deal.id } })"
                                        >
                                            <Pencil class="w-3.5 h-3.5 mr-1 text-slate-500" />
                                            Edit
                                        </Button>

                                        <Button
                                            v-if="authUser?.role === 'admin'"
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs rounded border-rose-200 text-rose-600 hover:bg-rose-50"
                                            @click="confirmDelete(deal)"
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

                <div v-if="hotDealsData.meta" class="p-3.5 border-t border-slate-200 flex justify-end">
                    <Pagination :meta="hotDealsData.meta" @change="fetchHotDeals" />
                </div>
            </section>
        </div>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <AlertDialogContent class="rounded border border-slate-200 bg-white">
                <AlertDialogHeader>
                    <AlertDialogTitle class="text-base font-bold text-slate-900">Delete Hot Deal?</AlertDialogTitle>
                    <AlertDialogDescription class="text-xs text-slate-600">
                        This will permanently delete the hot deal
                        <strong class="text-slate-900">{{ dealToDelete?.title }}</strong>
                        ({{ dealToDelete?.from_airport }} → {{ dealToDelete?.to_airport }}). This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter class="gap-2">
                    <AlertDialogCancel class="rounded border-slate-200 text-xs">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="rounded bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold"
                        @click="deleteDeal"
                    >
                        Delete Deal
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
