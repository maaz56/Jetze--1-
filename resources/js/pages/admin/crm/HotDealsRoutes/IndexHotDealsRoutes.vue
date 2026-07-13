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
        // Store action already shows the API error toast.
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
            <h1 class="text-3xl font-medium tracking-tight">Hot Deals</h1>
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
                :disabled="selectedDealCount === 0 || isSendingDealMail"
                @click="sendSelectedHotDealsMail"
            >
                <Send class="h-4 w-4" />
                {{ isSendingDealMail ? 'Queuing...' : `Send Mail (${selectedDealCount})` }}
            </Button>

            <Button
                @click="router.push({ name: 'HotDealCreate' })"
                class="flex items-center gap-2"
            >
                <PlusCircleIcon class="w-5 h-5" />
                Add Hot Deal
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
                        fetchHotDeals();
                    "
                    placeholder="Search by title, from airport, to airport..."
                    class="pl-10"
                />
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
            </div>
        </div>

        <section v-if="isLoading" class="py-24 flex justify-center">
            <Spinner />
        </section>

        <section v-else-if="hotDeals.length === 0">
            <NothingFound message="No hot deals found" />
        </section>

        <section v-else>
            <div class="overflow-x-auto">
                <Table>
                    <TableHeader class="bg-primary">
                        <TableRow>
                            <TableHead v-if="authUser?.role === 'admin'" class="w-12">
                                <Checkbox
                                    :checked="isAllVisibleSelected"
                                    aria-label="Select visible hot deals"
                                    @update:checked="(checked) => toggleVisibleSelection(checked === true)"
                                />
                            </TableHead>
                            <TableHead>Title</TableHead>
                            <TableHead>Route</TableHead>
                            <TableHead>Tag</TableHead>
                            <TableHead>Original Price</TableHead>
                            <TableHead>Discounted Price</TableHead>
                            <TableHead>Discount</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Order</TableHead>
                            <TableHead>Image</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="deal in hotDeals" :key="deal.id" class="hover:bg-gray-50">
                            <TableCell v-if="authUser?.role === 'admin'" class="w-12">
                                <Checkbox
                                    :checked="selectedDealIds.includes(deal.id)"
                                    :aria-label="`Select ${deal.title || 'hot deal'}`"
                                    @update:checked="(checked) => setDealSelected(deal.id, checked === true)"
                                />
                            </TableCell>
                            <TableCell class="font-medium max-w-xs">
                                <div class="truncate">{{ deal.title }}</div>
                            </TableCell>
                            <TableCell>
                                <Badge variant="outline">
                                    {{ deal.from_airport }} → {{ deal.to_airport }}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <span v-if="deal.tag" class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-semibold">
                                    {{ deal.tag }}
                                </span>
                                <span v-else class="text-muted-foreground text-xs">—</span>
                            </TableCell>
                            <TableCell class="line-through text-gray-400">
                                PKR {{ deal.original_price?.toLocaleString() }}
                            </TableCell>
                            <TableCell class="text-green-600 font-semibold">
                                PKR {{ deal.discounted_price?.toLocaleString() }}
                            </TableCell>
                            <TableCell>
                                <Badge variant="destructive" class="bg-red-100 text-red-700">
                                    {{ deal.discount_percentage }}% OFF
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <Badge :variant="deal.is_active ? 'default' : 'secondary'">
                                    {{ deal.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-sm">
                                {{ deal.display_order }}
                            </TableCell>
                            <TableCell>
                                <div v-if="deal.image_url" class="w-12 h-10 bg-gray-100 rounded overflow-hidden">
                                    <img
                                        :src="deal.image_url"
                                        :alt="deal.title"
                                        class="w-full h-full object-cover"
                                    />
                                </div>
                                <span v-else class="text-muted-foreground text-xs">No image</span>
                            </TableCell>
                            <TableCell class="whitespace-nowrap space-x-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'HotDealView', params: { id: deal.id } })"
                                >
                                    <Eye class="w-4 h-4 mr-1" />
                                    View
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    @click="router.push({ name: 'HotDealEdit', params: { id: deal.id } })"
                                >
                                    <Pencil class="w-4 h-4 mr-1" />
                                    Edit
                                </Button>

                                <Button
                                    v-if="authUser?.role === 'admin'"
                                    variant="outline"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="confirmDelete(deal)"
                                >
                                    <Trash2 class="w-4 h-4 mr-1" />
                                    Delete
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="hotDealsData.meta" class="flex justify-end pt-6">
                <Pagination :meta="hotDealsData.meta" @change="fetchHotDeals" />
            </div>
        </section>
    </div>

    <!-- Delete Confirmation -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Delete Hot Deal?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will permanently delete the hot deal
                    <strong>{{ dealToDelete?.title }}</strong>
                    ({{ dealToDelete?.from_airport }} → {{ dealToDelete?.to_airport }}).
                    This action cannot be undone.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    @click="deleteDeal"
                >
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
