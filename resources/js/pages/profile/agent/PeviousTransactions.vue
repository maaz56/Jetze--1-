<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
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
import { useStore } from "vuex";
import { computed, onMounted, ref } from "vue";
import { FETCH_TRANSACTIONS } from "@/services/store/actions.type";
import moment from "moment";

const store = useStore();

const transactions = computed(() => store.getters["transaction/transactions"]);
const user = computed(() => store.getters["auth/user"]);

function fetchTransactions() {
    store.dispatch("transaction/" + FETCH_TRANSACTIONS, {
        user_id: user.id,
    });
}

onMounted(() => {
    fetchTransactions();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-3xl font-medium">My Transactions</span>
            </div>
            <div
                class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                    >
                        <thead
                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3">
                                    Receipt no
                                </th>
                                <th scope="col" class="px-4 py-3">Bank</th>
                                <th scope="col" class="px-4 py-3">Amount</th>
                                <th scope="col" class="px-4 py-3">
                                    Payment Type
                                </th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="transaction in transactions"
                                :key="transaction.id"
                                class="border-b dark:border-gray-700"
                            >
                                <td class="px-4 py-3">
                                    {{ transaction.receipt_no }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ transaction.bank }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ transaction.amount }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ transaction.payment_type }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="transaction.is_approved"
                                        class="bg-gray-200 px-4 py-1 rounded-lg text-gray-700"
                                    >
                                        Approved
                                    </span>
                                    <span
                                        v-else
                                        class="bg-red-200 px-4 py-1 rounded-lg text-red-700"
                                    >
                                        Pending
                                    </span>
                                </td>
                                <td>
                                    {{
                                        moment(transaction.created_at).format(
                                            "MM-DD-YYYY"
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>
