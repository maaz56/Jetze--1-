<script setup>
import Button from "@/components/ui/button/Button.vue";
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from "radix-vue";
import { cn } from "@/lib/utils";
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
import { computed, onMounted, ref } from "vue";
import {
    FETCH_TRANSACTIONS,
    UPDATE_TRANSACTION,
    FETCH_DEPOSIT_DATA,
    FETCH_DEPOSITS_DATA_AGENTS,
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

onMounted(() => {
    fetchAgentsDeposits();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between my-8">
                <span class="text-3xl font-medium">All Top Up Requests</span>
            </div>
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
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="deposit in agentsDepositData.deposits" :key="deposit.id"
                                class="bg-white border-b hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <span class="text-white bg-accent rounded-full px-3 py-1">
                                        {{ deposit.receipt_reference || "_" }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ deposit.date || "_" }}</td>
                                <td class="px-4 py-3">{{ deposit.payment_type || "_" }}</td>
                                <td class="px-4 py-3">{{ deposit.additional_details || "_" }}</td>
                                <td class="px-4 py-3">
                                    {{ deposit?.agent?.agent_data?.company_name || deposit?.agent?.email }}
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
                                <td class="px-4 py-3 text-right">
                                    {{ deposit.amount || "_" }}
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
