<script setup>
import { computed, onMounted, ref } from "vue";
import { useStore } from "vuex";
import Button from "@/components/ui/button/Button.vue";
import { Plus, Pencil, Trash2, Tag } from "lucide-vue-next";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";
import { useRouter } from "vue-router";

const router = useRouter();
const store = useStore();

const promotions = computed(() => store.getters["promotion/promotions"]);
const isLoading = computed(() => store.getters["promotion/isLoading"]);

const isDeleteDialogOpen = ref(false);
const promotionToDelete = ref(null);

function fetchPromotions() {
    store.dispatch("promotion/fetchPromotions");
}

function openDeleteDialog(promotion) {
    promotionToDelete.value = promotion;
    isDeleteDialogOpen.value = true;
}

async function confirmDeletePromotion() {
    if (!promotionToDelete.value) return;
    try {
        await store.dispatch("promotion/deletePromotion", promotionToDelete.value.id);
        isDeleteDialogOpen.value = false;
        promotionToDelete.value = null;
    } catch (error) {
        console.error("Error deleting promotion:", error);
    }
}

onMounted(() => {
    fetchPromotions();
});
</script>


<template>
    <section class="h-screen flex flex-col p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-primary/10 rounded-xl">
                    <Tag class="w-6 h-6 text-primary" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Promotions</h1>
                    <p class="text-gray-500 dark:text-gray-400">Manage flight promotions and commissions</p>
                </div>
            </div>
            <Button @click="router.push({ name: 'NewPromotion' })" class="gap-2">
                <Plus class="w-4 h-4" />
                Add Promotion
            </Button>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border shadow-sm overflow-hidden flex-grow flex flex-col">
            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-300 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Title</th>
                            <th class="px-6 py-4">Channel</th>
                            <th class="px-6 py-4">Airline</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Commission</th>
                            <th class="px-6 py-4">Dates</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="promo in promotions" :key="promo.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ promo.title }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 rounded-full text-xs font-medium">
                                    {{ promo.sale_channel }}
                                </span>
                            </td>

                            <td class="px-6 py-4">
                                <div v-if="promo.airline?.name" class="flex items-center gap-2">
                                    <img v-if="promo.airline?.logo_url" :src="promo.airline.logo_url" class="w-6 h-6 object-contain" />
                                    <span>{{ promo.airline?.name }} ({{ promo.airline?.iata_code }})</span>
                                </div>
                                <div v-else class="text-gray-500 italic">All Airlines</div>
                            </td>
                            <td class="px-6 py-4">
                                {{ promo.reservation_type }}
                            </td>
                            <td class="px-6 py-4 font-semibold">
                                {{ promo.commission_value }} {{ promo.commission_type === 'percentage' ? '%' : 'PKR' }}
                            </td>
                            <td class="px-6 py-4 text-xs text-gray-500">
                                <div>Travel: {{ promo.travel_start_date || 'N/A' }} - {{ promo.travel_end_date || 'N/A' }}</div>
                                <div>Ticket: {{ promo.ticketing_start_date || 'N/A' }} - {{ promo.ticketing_end_date || 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="ghost" size="icon" @click="router.push({ name: 'UpdatePromotion', params: { id: promo.id } })" class="text-gray-500 hover:text-primary">
                                        <Pencil class="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="openDeleteDialog(promo)" class="text-gray-500 hover:text-red-500">
                                        <Trash2 class="w-4 h-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="promotions?.length === 0 && !isLoading">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                No promotions found. Click "Add Promotion" to create one.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Promotion</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this promotion? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="confirmDeletePromotion">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
