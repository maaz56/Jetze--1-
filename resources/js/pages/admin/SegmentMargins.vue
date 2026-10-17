<script setup>
import { onMounted, ref, computed } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import Button from "@/components/ui/button/Button.vue";
import { Plus, Pencil, Trash2, Landmark, Loader2 } from "lucide-vue-next";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";

const router = useRouter();
const store = useStore();

const margins = computed(() => store.getters["segmentMargin/segmentMargins"] || []);
const airlines = computed(() => store.getters["airline/airlines"] || []);
const isLoading = computed(() => store.getters["segmentMargin/isLoading"] || store.getters["airline/isLoading"]);

const isDeleteDialogOpen = ref(false);
const marginToDelete = ref(null);

function getAirlineTag(airlineId, relation = null) {
    const airlineById = new Map(airlines.value.map((airline) => [Number(airline.id), airline]));
    const matched = relation && Number(relation.id) === Number(airlineId)
        ? relation
        : airlineById.get(Number(airlineId));

    return {
        id: airlineId,
        code: matched?.iata_code || `ID:${airlineId}`,
        name: matched?.name || "",
        logo: matched?.logo_url || null,
    };
}

function getExcludedAirlineTags(margin) {
    return (Array.isArray(margin?.excluded_airline_ids) ? margin.excluded_airline_ids : [])
        .map((id) => getAirlineTag(id, margin?.airline));
}

async function fetchMargins() {
    try {
        await Promise.all([
            store.dispatch("segmentMargin/fetchSegmentMargins"),
            store.dispatch("airline/fetchAirlines", { limit: 1000 }),
        ]);
    } catch (error) {
        console.error("Error fetching margins:", error);
    }
}

function openDeleteDialog(margin) {
    marginToDelete.value = margin;
    isDeleteDialogOpen.value = true;
}

async function confirmDelete() {
    if (!marginToDelete.value) return;
    try {
        await store.dispatch("segmentMargin/deleteSegmentMargin", marginToDelete.value.id);
        isDeleteDialogOpen.value = false;
        marginToDelete.value = null;
    } catch (error) {
        console.error("Error deleting margin:", error);
    }
}

onMounted(() => {
    fetchMargins();
});
</script>

<template>
    <section class="h-screen flex flex-col p-6">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-3">
                <div class="p-3 bg-primary/10 rounded-xl">
                    <Landmark class="w-6 h-6 text-primary" />
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Segment Margins</h1>
                    <p class="text-gray-500 dark:text-gray-400">Manage flight markups and discount rules</p>
                </div>
            </div>
            <Button @click="router.push({ name: 'NewSegmentMargin' })" class="gap-2">
                <Plus class="w-4 h-4" />
                Add Margin
            </Button>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border shadow-sm overflow-hidden flex-grow flex flex-col">
            <div class="overflow-x-auto flex-grow">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-300 uppercase text-xs font-semibold">
                        <tr>
                            <th class="px-6 py-4">Title</th>
                            <th class="px-6 py-4">Airline Scope</th>
                            <th class="px-6 py-4">Margin Rule</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="isLoading" class="hover:bg-transparent">
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex items-center justify-center gap-2 text-gray-500">
                                    <Loader2 class="w-5 h-5 animate-spin" />
                                    Loading segment margins...
                                </div>
                            </td>
                        </tr>
                        <tr v-else v-for="margin in margins" :key="margin.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <div>{{ margin.title }}</div>
                                <div class="text-xs font-normal" :class="margin.is_active ? 'text-emerald-600' : 'text-gray-400'">
                                    {{ margin.is_active ? 'Active' : 'Inactive' }} · {{ margin.airline_id ? 'Airline override' : 'All-airlines default' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <span class="text-xs text-blue-600 font-bold uppercase">{{ margin.sale_channel }}</span>
                                    <div v-if="margin.airline_id" class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[10px] font-medium">
                                        <img v-if="getAirlineTag(margin.airline_id, margin.airline).logo" :src="getAirlineTag(margin.airline_id, margin.airline).logo" class="w-3 h-3 object-contain" />
                                        {{ getAirlineTag(margin.airline_id, margin.airline).code }}
                                    </div>
                                    <span v-else class="text-[10px] text-gray-400 italic">ALL AIRLINES</span>
                                    <div v-if="getExcludedAirlineTags(margin).length" class="flex flex-wrap gap-1 mt-1">
                                        <span
                                            v-for="airlineTag in getExcludedAirlineTags(margin)"
                                            :key="`margin-${margin.id}-excluded-airline-${airlineTag.id}`"
                                            class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-full text-[10px] font-medium"
                                        >
                                            <img v-if="airlineTag.logo" :src="airlineTag.logo" class="w-3 h-3 object-contain" />
                                            Excludes {{ airlineTag.code }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-900 dark:text-white capitalize">
                                        {{ margin.margin_type }}
                                    </span>
                                    <span class="text-xs text-gray-500 font-semibold">
                                        {{ margin.margin_value }} AED × segments × passengers
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="ghost" size="icon" @click="router.push({ name: 'UpdateSegmentMargin', params: { id: margin.id } })" class="text-gray-500 hover:text-primary">
                                        <Pencil class="w-4 h-4" />
                                    </Button>
                                    <Button variant="ghost" size="icon" @click="openDeleteDialog(margin)" class="text-gray-500 hover:text-red-500">
                                        <Trash2 class="w-4 h-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="margins.length === 0 && !isLoading">
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                No segment margins found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete Margin</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this margin rule?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">Cancel</Button>
                    <Button variant="destructive" @click="confirmDelete">Delete</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
