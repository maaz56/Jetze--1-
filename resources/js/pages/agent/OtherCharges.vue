<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import { EyeIcon, Trash2 } from "lucide-vue-next";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import FileUploader from "@/components/common/FileUploader.vue";
import { useStore } from "vuex";
import {
    DELETE_VISA,
    DELETE_VISA_HEADER_IMAGES,
    FETCH_VISAS,
    FETCH_VISA_HEADER_IMAGES,
    SAVE_VISA_HEADER_IMAGES,
    FETCH_AGENTS_CHARGES,
} from "@/services/store/actions.type";
import { computed, onMounted, ref } from "vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { useAuthStore } from "@/services/stores/auth";

const store = useStore();
const authStore = useAuthStore();

const user = computed(() => authStore.user);
const user_id = user.value.id;
const user_role = user.value.role;
const selectedCharge = ref(null);
const isDialogOpen = ref(false);

const visas = computed(() => store.getters["visa/visas"]);
const initialHeaderImages = computed(() => store.getters["visa/headerImages"]);
const allCharges = computed(() => store.getters["user/agentCharges"]);

const headerImages = ref([]);
const removeFiles = ref(false);

function fetchAllCharges() {
    store.dispatch("user/" + FETCH_AGENTS_CHARGES, {
        userId: user_id,
        userRole: user_role,
    });
}

function deleteVisa(id) {
    store.dispatch("visa/" + DELETE_VISA, {
        id: id,
    });
}
function openDialog(charge) {
    selectedCharge.value = { ...charge };
    isDialogOpen.value = true;
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

function closeDialog() {
    selectedCharge.value = null;
    isDialogOpen.value = false;
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
                <!-- 
                <div class="flex gap-2">
                    <Button @click="$router.push({ name: 'NewCharges' })"
                        >Add Charges</Button
                    >
                </div> -->
            </div>

            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border">
                <div
                    class="flex flex-col md:flex-row items-center justify-between space-y-3 md:space-y-0 md:space-x-4 p-4">
                    <div class="w-full md:w-1/2">
                        <form class="flex items-center">
                            <label for="simple-search" class="sr-only">Search</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400"
                                        fill="currentColor" viewbox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd"
                                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input type="text" id="simple-search"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="Search" required="" />
                            </div>
                        </form>
                    </div>
                    <div
                        class="w-full md:w-auto flex flex-col md:flex-row space-y-2 md:space-y-0 items-stretch md:items-center justify-end md:space-x-3 flex-shrink-0">
                        <div class="flex items-center space-x-3 w-full md:w-auto"></div>
                    </div>
                </div>
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
                                <th scope="col" class="px-4 py-3">
                                    Descriptions
                                </th>

                                <th scope="col" class="px-4 py-3">Type</th>

                                <th scope="col" class="px-4 py-3">Agent</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in allCharges" :key="item.id" class="border-b dark:border-gray-700">
                                <td class="px-4 py-3">{{ item.agent.agent_data.agent_uid }}_{{ item.id + 1000 }}</td>
                                <td class="px-4 py-3">{{ item.date }}</td>
                                <td class="px-4 py-3">
                                    {{ item.additional_details }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ item.payment_type }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ item.agent.email }}
                                </td>
                                <td class="px-4 py-3">{{ item.amount }}</td>
                                <td class="px-4 py-3"> <Button size="sm" variant="outline" @click="openDialog(item)"
                                        class="flex gap-2 text-primary hover:text-primary">
                                        <EyeIcon class="w-5 h-5" />
                                    </Button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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


                        </div>
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
