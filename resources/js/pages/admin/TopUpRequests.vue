<script setup>
import Button from "@/components/ui/button/Button.vue";
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from "radix-vue";
import { cn, formatAmount } from "@/lib/utils";
import { CircleChevronRight } from "lucide-vue-next";
import { EyeIcon, TrashIcon, LoaderIcon, InboxIcon } from "lucide-vue-next";

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { useStore } from "vuex";
import { computed, onMounted, ref, watch } from "vue";
import { Input } from "@/components/ui/input";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import Calender from "@/components/common/Calender.vue";
import { Plus, SaveIcon } from "lucide-vue-next";
import { useUserStore } from "@/services/stores/user";
import {
    FETCH_TRANSACTIONS,
    UPDATE_TRANSACTION,
    FETCH_DEPOSIT_DATA,
    FETCH_DEPOSITS_DATA_AGENTS,
    FETCH_BANKS,
    FETCH_CURRENCIES,
    SAVE_DEPOSIT_DATA,
} from "@/services/store/actions.type";
import moment from "moment";

const store = useStore();
const agentsDepositData = computed(
    () => store.getters["deposit/depositDataWithAgents"],
);

const transactions = computed(() => store.getters["transaction/transactions"]);
const error = ref(null);
const isLoading = ref(false);
const loading = ref(true);

// ---------------------------------------------------------------------------
// Admin: add a deposit to any customer / agent account
// ---------------------------------------------------------------------------
const userStore = useUserStore();
const CUSTOMER_ROLES = ["agent", "user", "customer"];
const customers = computed(() =>
    (userStore.users?.data || []).filter((u) => CUSTOMER_ROLES.includes(u.role)),
);
const banks = computed(() => store.getters["bank/banks"] || []);
const currencies = computed(() => store.getters["currency/currencies"] || []);

const depositDate = ref(new Date().toISOString().slice(0, 10));
const depositAmount = ref("");
const depositCurrency = ref("AED");
const receiptImage = ref(null);
const receiptInput = ref(null);
const paymentType = ref("");
const selectedBankId = ref(null);
const selectedCustomerId = ref("");
const customerSearchQuery = ref("");
const isCustomerOpen = ref(false);
const additionalDetails = ref("");
const formErrors = ref({});
const formError = ref(null);
const isSubmitting = ref(false);
const isAddOpen = ref(false);

const selectedBank = computed(() => banks.value.find((b) => b.id === selectedBankId.value) || null);
const selectedCustomer = computed(() =>
    customers.value.find((c) => String(c.id) === selectedCustomerId.value) || null,
);
const filteredCustomers = computed(() => {
    const q = customerSearchQuery.value.toLowerCase();
    return q ? customers.value.filter((c) => customerSearchText(c).toLowerCase().includes(q)) : customers.value;
});

function customerDisplayName(user) {
    const name = user?.agent_data?.company_name || user?.name || user?.email;
    const code = user?.agent_data?.agent_uid || user?.customer?.customer_uid;
    return [name, code].filter(Boolean).join(" - ") || "Unnamed account";
}
function customerSearchText(user) {
    return [customerDisplayName(user), user?.name, user?.email].filter(Boolean).join(" ");
}
function selectCustomer(user) {
    selectedCustomerId.value = String(user.id);
    customerSearchQuery.value = "";
    isCustomerOpen.value = false;
}

const bankValue = (bank) => `bank:${bank.id}`;
function selectBank(bank) {
    selectedBankId.value = bank.id;
    paymentType.value = bankValue(bank);
    depositCurrency.value = bank.currency;
}
function handlePaymentTypeChange(value) {
    const bank = banks.value.find((b) => bankValue(b) === value) || null;
    selectedBankId.value = bank?.id ?? null;
    if (bank) depositCurrency.value = bank.currency;
}
function handleReceiptImage(event) {
    receiptImage.value = event.target.files?.[0] || null;
}

function validateDepositForm() {
    const errors = {};
    if (!selectedCustomerId.value) errors.agent_id = "Please select a customer.";
    if (!depositDate.value) errors.date = "Date is required.";
    if (!depositAmount.value || Number(depositAmount.value) <= 0) errors.amount = "Enter an amount greater than zero.";
    if (!paymentType.value) errors.payment_type = "Payment type is required.";
    if (!depositCurrency.value) errors.currency = "Currency is required.";
    formErrors.value = errors;
    return Object.keys(errors).length === 0;
}

function resetDepositForm() {
    depositDate.value = new Date().toISOString().slice(0, 10);
    depositAmount.value = "";
    depositCurrency.value = "AED";
    receiptImage.value = null;
    paymentType.value = "";
    selectedBankId.value = null;
    selectedCustomerId.value = "";
    customerSearchQuery.value = "";
    additionalDetails.value = "";
    formErrors.value = {};
    if (receiptInput.value?.$el) receiptInput.value.$el.value = "";
}

async function handleDepositData() {
    if (!validateDepositForm()) return;

    isSubmitting.value = true;
    formError.value = null;
    try {
        const data = new FormData();
        data.append("date", depositDate.value);
        data.append("amount", depositAmount.value);
        if (receiptImage.value) data.append("receipt_image", receiptImage.value);
        if (selectedBank.value) {
            // Server resolves bank name + currency from this ID.
            data.append("bank_id", String(selectedBank.value.id));
            data.append("payment_type", selectedBank.value.bank_name);
        } else {
            data.append("payment_type", paymentType.value);
        }
        data.append("additional_details", additionalDetails.value);
        data.append("agent_id", selectedCustomerId.value);
        data.append("currency", depositCurrency.value);
        data.append("payment_method", "bank");

        await store.dispatch(`deposit/${SAVE_DEPOSIT_DATA}`, data);
        isAddOpen.value = false;
        fetchAgentsDeposits();
    } catch (err) {
        const validationErrors = err?.response?.data?.errors || {};
        formErrors.value = Object.fromEntries(
            Object.entries(validationErrors).map(([f, m]) => [f, Array.isArray(m) ? m[0] : m]),
        );
        formError.value = err?.response?.data?.message || "Failed to save the deposit. Please try again.";
    } finally {
        isSubmitting.value = false;
    }
}

// Opening starts a clean form; closing (Save, Cancel, X, outside click) discards it.
watch(isAddOpen, (open) => {
    formError.value = null;
    if (!open) resetDepositForm();
});

function fetchTransactions() {
    store.dispatch("transaction/" + FETCH_TRANSACTIONS);
}

function approveTopup(id) {
    //console.log("Approving topup request");
    store.dispatch("transaction/" + UPDATE_TRANSACTION, {
        transaction_id: id,
    });
}
function fetchAgentsDeposits() {
    try {
        store.dispatch("deposit/" + FETCH_DEPOSITS_DATA_AGENTS);
        loading.value = false;
    } catch (err) {
        error.value = "Failed to load user deposit data. Please try again.";
        loading.value = false;
    }
}

function formatMoney(money, fallbackAmount = null, fallbackCurrency = "AED") {
    return formatAmount(money?.amount ?? fallbackAmount, money?.currency ?? fallbackCurrency);
}

onMounted(() => {
    fetchAgentsDeposits();
    userStore.fetchUsers();
    store.dispatch(`bank/${FETCH_BANKS}`);
    store.dispatch(`currency/${FETCH_CURRENCIES}`);
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between my-8">
                <span class="text-3xl font-medium">All Top Up Requests</span>
                <Button class="text-white" @click="isAddOpen = true">
                    <Plus class="w-4 h-4 mr-2" />
                    Add Top Up Request
                </Button>
            </div>
            <!-- Add top up request -->
            <Dialog v-model:open="isAddOpen">
                <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="text-2xl">Add Top Up Request</DialogTitle>
                        <DialogDescription>
                            Add a deposit to a customer's account. It is saved as pending until you approve it.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="formError" class="rounded-md bg-red-50 p-3 text-sm text-red-700">{{ formError }}</div>

                    <form id="topup-form" class="space-y-6" @submit.prevent="handleDepositData">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Customer <span class="text-red-500">*</span></label>
                            <Popover v-model:open="isCustomerOpen">
                                <PopoverTrigger
                                    class="flex h-10 w-full items-center rounded-md border border-input bg-background px-3 text-left text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    :class="{ 'border-red-500': formErrors.agent_id }">
                                    <span class="truncate">{{ selectedCustomer ? customerDisplayName(selectedCustomer) : "Select a customer" }}</span>
                                </PopoverTrigger>
                                <PopoverContent class="w-[--radix-popover-trigger-width] p-0">
                                    <Command>
                                        <CommandInput v-model="customerSearchQuery" placeholder="Search by name or email..." class="h-9" />
                                        <CommandList>
                                            <CommandEmpty>No customers found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem v-for="c in filteredCustomers" :key="c.id"
                                                    :value="customerSearchText(c)" class="cursor-pointer"
                                                    @select="selectCustomer(c)">
                                                    {{ customerDisplayName(c) }}
                                                    <span class="ml-2 text-xs text-muted-foreground">{{ c.email }}</span>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                            <p v-if="formErrors.agent_id" class="text-xs text-red-600">{{ formErrors.agent_id }}</p>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Date <span class="text-red-500">*</span></label>
                            <Calender v-model="depositDate" type="date" />
                            <p v-if="formErrors.date" class="text-xs text-red-600">{{ formErrors.date }}</p>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Amount <span class="text-red-500">*</span></label>
                            <Input v-model="depositAmount" type="number" min="0.01" step="0.01" placeholder="Enter amount" />
                            <p v-if="formErrors.amount" class="text-xs text-red-600">{{ formErrors.amount }}</p>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Payment type <span class="text-red-500">*</span></label>
                            <Select v-model="paymentType" @update:model-value="handlePaymentTypeChange">
                                <SelectTrigger :class="{ 'border-red-500': formErrors.payment_type }">
                                    <SelectValue placeholder="Select a payment type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Payment accounts</SelectLabel>
                                        <SelectItem value="cash">Cash</SelectItem>
                                        <SelectItem value="cash-deposit">Cash - Deposit</SelectItem>
                                        <SelectItem v-for="bank in banks" :key="bank.id" :value="bankValue(bank)">
                                            {{ bank.bank_name }} - {{ bank.account_title }} - {{ bank.account_number }} ({{ bank.currency }})
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <p v-if="formErrors.payment_type" class="text-xs text-red-600">{{ formErrors.payment_type }}</p>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Currency <span class="text-red-500">*</span></label>
                            <Select v-model="depositCurrency" :disabled="!!selectedBank">
                                <SelectTrigger :class="{ 'border-red-500': formErrors.currency }">
                                    <SelectValue placeholder="Currency" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-for="currency in currencies" :key="currency.code" :value="currency.code">
                                            {{ currency.code }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <p v-if="formErrors.currency" class="text-xs text-red-600">{{ formErrors.currency }}</p>
                        </div>

                        <div class="grid gap-1">
                            <label class="text-sm font-medium">Receipt image <span class="text-xs text-muted-foreground">(optional)</span></label>
                            <Input ref="receiptInput" type="file" accept="image/jpeg,image/png,image/gif" @change="handleReceiptImage" />
                            <p v-if="formErrors.receipt_image" class="text-xs text-red-600">{{ formErrors.receipt_image }}</p>
                        </div>

                        <div class="grid gap-1 md:col-span-2">
                            <label class="text-sm font-medium">Additional details <span class="text-xs text-muted-foreground">(Document Reference)</span></label>
                            <Input v-model="additionalDetails" placeholder="Details..." />
                        </div>
                    </div>

                    </form>

                    <DialogFooter>
                        <Button type="button" variant="outline" :disabled="isSubmitting" @click="isAddOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" form="topup-form" class="text-white" :disabled="isSubmitting">
                            <LoaderIcon v-if="isSubmitting" class="w-4 h-4 mr-2 animate-spin" />
                            <SaveIcon v-else class="w-4 h-4 mr-2" />
                            {{ isSubmitting ? "Saving..." : "Save Top Up Request" }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <div class="bg-white dark:bg-gray-800 relative sm:rounded-lg overflow-hidden border">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-gray-700 border-collapse">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left">Receipt Reference</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Payment Account</th>
                                <th class="px-4 py-3 text-left">Additional Details</th>
                                <th class="px-4 py-3 text-left">User</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Deposited Amount</th>
                                <th class="px-4 py-3 text-right">AED Amount</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="deposit in agentsDepositData.deposits" :key="deposit.id"
                                class="bg-white border-b hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <span class="inline-flex rounded-full border border-primary/20 bg-primary/10 px-3 py-1 font-semibold text-primary">
                                        {{ deposit.receipt_reference || "_" }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ deposit.date || "_" }}</td>
                                <td class="px-4 py-3">{{ deposit.payment_type || "_" }}</td>
                                <td class="px-4 py-3">{{ deposit.additional_details || "_" }}</td>
                                <td class="px-4 py-3">
                                    {{ deposit?.agent?.agent_data?.company_name || deposit?.agent?.email }}
                                    <span v-if="deposit?.agent?.customer?.customer_uid"
                                        class="block text-xs font-semibold text-primary">
                                        {{ deposit.agent.customer.customer_uid }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    {{ deposit?.agent?.role || "_" }}
                                </td>
                                <td class="px-4 py-3 font-semibold">
                                    <span :class="{
                                        'text-red-500': deposit.deposit_status === 'pending',
                                        'text-green-600': deposit.deposit_status === 'approved',
                                        uppercase: true,
                                    }">
                                        {{ deposit.deposit_status || '_' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{ formatMoney(deposit.source_money, deposit.amount, deposit.currency) }}
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">
                                    {{ deposit.base_money ? formatMoney(deposit.base_money, deposit.aed_amount, 'AED') : "_" }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button @click="
                                        $router.push({
                                            name: 'DepositDetails',
                                            query: { deposit_id: deposit.id },
                                        })
                                        " class="hover:text-purple-700">
                                        <CircleChevronRight class="w-5 h-5 inline" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                </div>
                <nav class="flex flex-col md:flex-row justify-between items-start md:items-center space-y-3 md:space-y-0 p-4"
                    aria-label="Table navigation">
                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                        Showing
                        <span class="font-semibold text-gray-900 dark:text-white">1-10</span>
                        of
                        <span class="font-semibold text-gray-900 dark:text-white">1000</span>
                    </span>
                    <ul class="inline-flex items-stretch -space-x-px">
                        <li>
                            <a href="#"
                                class="flex items-center justify-center h-full py-1.5 px-3 ml-0 text-gray-500 bg-white rounded-l-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                <span class="sr-only">Previous</span>
                                <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewbox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </a>
                        </li>
                        <li>
                            <a href="#"
                                class="flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">1</a>
                        </li>
                        <li>
                            <a href="#"
                                class="flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">2</a>
                        </li>
                        <li>
                            <a href="#" aria-current="page"
                                class="flex items-center justify-center text-sm z-10 py-2 px-3 leading-tight text-primary-600 bg-primary-50 border border-primary-300 hover:bg-primary-100 hover:text-primary-700 dark:border-gray-700 dark:bg-gray-700 dark:text-white">3</a>
                        </li>
                        <li>
                            <a href="#"
                                class="flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">...</a>
                        </li>
                        <li>
                            <a href="#"
                                class="flex items-center justify-center text-sm py-2 px-3 leading-tight text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">100</a>
                        </li>
                        <li>
                            <a href="#"
                                class="flex items-center justify-center h-full py-1.5 px-3 leading-tight text-gray-500 bg-white rounded-r-lg border border-gray-300 hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                                <span class="sr-only">Next</span>
                                <svg class="w-5 h-5" aria-hidden="true" fill="currentColor" viewbox="0 0 20 20"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </section>
</template>
