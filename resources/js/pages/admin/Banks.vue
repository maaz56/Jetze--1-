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
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";

import {
    Calendar as CalendarIcon,
    ArrowLeft,
    Search,
    RefreshCcw,
    Ellipsis,
    Pencil,
    Trash2,
    Plus,
    UserPlus,
    Filter,
    Landmark,
    X
} from "lucide-vue-next";

import { toast } from "vue3-toastify";
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { debounce } from "lodash";
import { useUserStore } from "@/services/stores/user";
import Nav from "@/components/admin/Nav.vue";
import Spinner from "@/components/common/Spinner.vue";
import NothingFound from "@/components/common/NothingFound.vue";
import { formatDate } from "@/lib/utils";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Eye } from "lucide-vue-next";
import {
  DELETE_BANK,
    FETCH_BANKS,
    UPDATE_BANK,
} from "@/services/store/actions.type";
import { useStore } from "vuex";

const userStore = useUserStore();
const store = useStore();
const route = useRoute();


const banks = computed(() => store.getters["bank/banks"]);

const isLoading = computed(() => userStore.isLoading);
const showDeleteDialog = ref(false);
const bankToDelete = ref(null);
const togglingBankIds = ref([]);


function fetchBanks(){
    store.dispatch("bank/" + FETCH_BANKS);
}
function confirmDelete(bank) {
    bankToDelete.value = bank;
    showDeleteDialog.value = true;
}

function deleteBank(id) {
    store.dispatch("bank/" + DELETE_BANK, {
        id: id,
    });
}

async function updateBankStatus(bank, isActive) {
    if (!bank?.id) return;

    togglingBankIds.value.push(bank.id);
    try {
        await store.dispatch("bank/" + UPDATE_BANK, {
            id: bank.id,
            is_active: isActive ? 1 : 0,
        });
    } finally {
        togglingBankIds.value = togglingBankIds.value.filter((id) => id !== bank.id);
    }
}

onMounted(() => {
    fetchBanks();
});
</script>

<template>
    <div class="flex items-center justify-between mb-3">

        <div class="flex items-center gap-4 mb-3">

            <Button @click="$router.push({ name: 'Dashboard' })" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4 mr-1" />
                Back
            </Button>
            <span class="block text-3xl font-medium leading-none tracking-tight text-gray-900">
                Banks
            </span>
        </div>
    </div>
    <div>
        <div class="bg-white p-8 rounded-lg border">
            <div
                class="flex flex-col py-4 space-y-3 lg:flex-row lg:items-center lg:justify-between lg:space-y-0 lg:space-x-4">
                
                <div
                    class="flex flex-col flex-shrink-0 space-y-3 md:flex-row md:items-center lg:justify-end md:space-y-0 md:space-x-3">
                    <Button variant="outline" @click="
                        () => {
                            $router.push({ name: 'NewBank' });
                        }
                    " class="flex items-center">
                        <UserPlus class="w-4 h-4 mr-2" /> New Bank
                    </Button>
                </div>
            </div>
            <section v-if="users?.data.length === 0">
                <NothingFound />
            </section>
            <section v-if="isLoading" class="p-24 flex items-center justify-center">
                <Spinner />
            </section>
            <section>
                <div class="relative overflow-hidden">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader class="bg-primary">
                                <TableRow>
                                    <TableHead scope="col" class="px-4 py-3">Logo</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Name</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Title</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Number</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">IBAN Number</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Currency</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Status</TableHead>
                                    <TableHead scope="col" class="px-4 py-3">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="bank in banks" :key="bank.id" class="border-b hover:bg-gray-100">
                                    <TableCell class="px-4 py-2">
                                       
                                        <div
                                            class="flex items-center justify-start"
                                        >
                                            <div
                                                class="w-16 h-16 rounded-full bg-white-200 dark:bg-gray-700 flex items-center justify-center overflow-hidden"
                                            >
                                                <img
                                                    v-if="bank.logo_path"
                                                    :src="bank.logo_path"
                                                    :alt="
                                                        bank.name + ' logo'
                                                    "
                                                    class="w-12 h-12 object-contain"
                                                />
                                                <Landmark
                                                    v-else
                                                    class="w-8 h-8 text-primary dark:text-primary"
                                                />
                                            </div>
                                        </div>
                                   
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5">
                                            {{ bank?.bank_name || "_" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5">
                                            {{ bank?.account_title || "_" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5">
                                            {{ bank?.account_number || "_" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <span class="text-xs font-medium px-2 py-0.5">
                                            {{ bank?.iban || "_" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <span class="uppercase bg-primary/20 rounded-full  px-4 py-2  hover:bg-primary/20 text-gray-800 border-primary 50 cursor-default">
                                            {{ bank?.currency|| "_" }}
                                        </span>
                                    </TableCell>
                                    <TableCell class="px-4 py-2">
                                        <div class="flex items-center gap-2">
                                            <Switch
                                                :checked="Boolean(bank?.is_active)"
                                                :disabled="togglingBankIds.includes(bank.id)"
                                                @update:checked="(value) => updateBankStatus(bank, value)"
                                            />
                                            <Badge
                                                variant="outline"
                                                :class="bank?.is_active
                                                    ? 'bg-green-100 text-green-800 border-green-200 hover:bg-green-100'
                                                    : 'bg-red-100 text-red-800 border-red-200 hover:bg-red-100'"
                                            >
                                                {{ bank?.is_active ? "Active" : "Inactive" }}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell class="px-4 py-2 space-x-2 whitespace-nowrap">
                                        <button @click="$router.push({ name: 'UpdateBank', params: { id: bank.id } })"
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:text-primary h-8 px-3 text-black hover:bg-primary/10">
                                            <Pencil class="w-4 h-4 me-2" />
                                            Edit
                                        </button>

                                        <button  @click="confirmDelete(bank)"
                                            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-8 px-3 text-destructive hover:bg-destructive/10">
                                            <Trash2 class="w-4 h-4 me-2" />
                                            Delete
                                        </button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                   
                </div>
            </section>
        </div>
    </div>
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Are you sure you want to delete this user?</AlertDialogTitle>
                <AlertDialogDescription>
                    This action cannot be undone. This will permanently delete the user
                    {{ bankToDelete?.name }} and remove their data from our servers.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="showDeleteDialog = false">Cancel</AlertDialogCancel>
                <AlertDialogAction @click="deleteBank(bankToDelete?.id)"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                    Delete
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
