<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { EyeIcon, Trash2 } from "lucide-vue-next";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import NothingFound from "@/components/common/NothingFound.vue";
import { useStore } from "vuex";
import {
    FETCH_AGENTS_CHARGES,
    DELETE_VISA,
    UPDATE_AGENT_CHARGE_STATUS,
} from "@/services/store/actions.type";
import { computed, onMounted, ref } from "vue";
import { useAuthStore } from "@/services/stores/auth";
import Spinner from "@/components/common/Spinner.vue";

const store = useStore();
const authStore = useAuthStore();

const allCharges = computed(() => store.getters["user/agentCharges"]);
const user = computed(() => authStore.user);
const isSaving = computed(() => store.getters["user/isSaving"]);

const selectedCharge = ref(null);
const isDialogOpen = ref(false);

function fetchAllCharges() {
    store.dispatch("user/" + FETCH_AGENTS_CHARGES, {
        userRole: user.value.role,
    });
}

function deleteCharges(id) {
    store.dispatch("visa/" + DELETE_VISA, { id }).then(() =>
        fetchAllCharges()
    );
}

function openDialog(charge) {
    selectedCharge.value = { ...charge };
    isDialogOpen.value = true;
}

function closeDialog() {
    selectedCharge.value = null;
    isDialogOpen.value = false;
}

function updateChargeStatus(e) {
    const newStatus = e.target.checked ? 1 : 0;
    store
        .dispatch("user/" + UPDATE_AGENT_CHARGE_STATUS, {
            id: selectedCharge.value.id,
            is_approved: newStatus,
        })
        .then(() => {
            selectedCharge.value.is_approved = newStatus;
            fetchAllCharges();
        });
}



const downloadReceipt = (file) => {
    window.open(`/${file}`, "_blank");

    //   const link = document.createElement("a")
    //   link.href = `/admin/receipts/${file}`
    //   link.setAttribute("download", file) // forces download instead of opening
    //   document.body.appendChild(link)
    //   link.click()
    //   document.body.removeChild(link)
}

onMounted(() => {
    fetchAllCharges();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between my-8">
                <span class="text-3xl font-medium">Other Charges</span>
            </div>
            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border">
                <div class="overflow-x-auto">
                    <div v-if="allCharges?.length == 0">
                        <NothingFound />
                    </div>
                    <table v-if="allCharges?.length > 0"
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Id</th>
                                <th scope="col" class="px-4 py-3">Date</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">Type</th>
                                <th scope="col" class="px-4 py-3">Charged By</th>
                                <th scope="col" class="px-4 py-3">
                                    Descriptions
                                </th>
                                <th scope="col" class="px-4 py-3">Agent</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in allCharges" :key="item.id" class="border-b dark:border-gray-700">
                                <td class="px-4 py-3">{{ item.agent.agent_data.agent_uid }}_{{ item.id + 1000 }}</td>
                                <td class="px-4 py-3">{{ item.date }}</td>
                                <td class="px-4 py-3">{{ item.amount }}</td>
                                <td class="px-4 py-3">
                                    {{ item.payment_type }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item?.charged_by?.email || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.additional_details }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item?.agent?.email || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="item?.is_approved == 1
                                        ? 'bg-green-100 text-green-800 px-2 py-1 rounded-full text-sm font-medium'
                                        : 'bg-yellow-100 text-yellow-800 px-2 py-1 rounded-full text-sm font-medium'">
                                        {{ item?.is_approved == 1 ? 'Approved' : 'Pending' }}
                                    </span>
                                </td>


                                <td class="px-4 py-3">
                                    <span class="flex gap-4">
                                        <div class="flex space-x-2">
                                            <Button size="sm" variant="outline" @click="openDialog(item)"
                                                class="flex gap-2  hover:text-primary">
                                                <EyeIcon class="w-5 h-5" />
                                            </Button>
                                        </div>
                                        <Button size="sm" variant="outline" class="flex gap-2  hover:text-primary"
                                            @click="deleteCharges(item.id)">
                                            <Trash2 class="w-5 h-5 text-red-500" />
                                        </Button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Charge Details Dialog -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="max-w-xl bg-white rounded-2xl shadow-lg p-6">
                <DialogHeader>
                    <DialogTitle class="text-xl font-semibold text-gray-800">
                        Charge #{{ selectedCharge?.id }}
                    </DialogTitle>
                    <DialogDescription class="text-sm text-gray-500">
                        Full details of this charge
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-4 space-y-6">
                    <!-- Charge Info -->
                    <div class="bg-gray-50 rounded-lg p-4 border space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            <p class="text-sm font-medium text-gray-600">Date</p>
                            <p class="text-sm text-gray-800">{{ selectedCharge?.date }}</p>

                            <p class="text-sm font-medium text-gray-600">Amount</p>
                            <p class="text-sm text-gray-800">{{ selectedCharge?.amount }}</p>

                            <p class="text-sm font-medium text-gray-600">Type</p>
                            <p class="text-sm text-gray-800">{{ selectedCharge?.payment_type }}</p>

                            <p class="text-sm font-medium text-gray-600">Description</p>
                            <p class="text-sm text-gray-800">
                                {{ selectedCharge?.additional_details || "—" }}
                            </p>

                            <p class="text-sm font-medium text-gray-600">Agent</p>
                            <p class="text-sm text-gray-800">
                                {{ selectedCharge?.agent?.email }}
                            </p>
                        </div>
                    </div>

                    <!-- Approval Toggle -->
                    <div class="flex items-center justify-between bg-gray-50 rounded-lg p-4 border">
                        <span class="text-sm font-medium text-gray-700">Approved</span>
                        <label  class="inline-flex items-center cursor-pointer">
                            <input :disabled="isSaving" type="checkbox" :checked="selectedCharge?.is_approved" @change="updateChargeStatus"
                                class="sr-only peer" />
                            <div
                                class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                            </div>
                        </label>
                    </div>

                    <!-- Download Receipt -->
                    <div v-if="selectedCharge?.receipt" class="text-center">
                        <Button @click="downloadReceipt(selectedCharge.receipt)"
                            class="w-full bg-primary text-white hover:bg-primary/90">
                            Download Receipt
                        </Button>
                    </div>
                </div>

                <DialogFooter class="">
                    <Button variant="secondary" class="w-full" @click="closeDialog">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </section>
</template>
