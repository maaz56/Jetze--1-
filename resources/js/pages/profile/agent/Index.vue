<script setup>
import Button from "@/components/ui/button/Button.vue";
import { SwitchRoot, SwitchThumb, useForwardPropsEmits } from "radix-vue";
import { cn, formatAmount } from "@/lib/utils";
import { useStore } from "vuex";
import { computed, onMounted } from "vue";
import {
    FETCH_BOOKINGS,
    FETCH_TRANSACTIONS,
} from "@/services/store/actions.type";
const store = useStore();

const bookings = computed(() => store.getters["flight/bookings"]);
const transactions = computed(() => store.getters["transaction/transactions"]);
const user = computed(() => store.getters["auth/user"]);

function fetchBookings() {
    store.dispatch("flight/" + FETCH_BOOKINGS);
}

function fetchTransactions() {
    store.dispatch("transaction/" + FETCH_TRANSACTIONS, {
        user_id: user.id,
    });
}

onMounted(() => {
    fetchBookings();
    fetchTransactions();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-3xl font-medium"
                    >Welcome back {{ user?.details?.first_name }}</span
                >
            </div>
            <div class="grid grid-cols-4 gap-4">
                <div
                    class="max-w-sm w-full bg-white rounded-2xl border dark:bg-gray-800 p-4 md:p-6"
                >
                    <span class="font-medium">Total bookings</span>
                    <span class="text-2xl font-semibold block">
                        {{
                            formatAmount(
                                bookings
                                    .reduce((prevValue, curValue) => {
                                        let price =
                                            typeof curValue.flight.price ===
                                            "string"
                                                ? JSON.parse(
                                                      curValue.flight.price
                                                  )
                                                : curValue.flight.price;
                                        return (
                                            prevValue + parseFloat(price.total)
                                        );
                                    }, 0)
                                    .toFixed(2)
                            )
                        }}
                    </span>
                </div>
                <div
                    class="max-w-sm w-full bg-white rounded-2xl border dark:bg-gray-800 p-4 md:p-6"
                >
                    <span class="font-medium">Total transactions</span>
                    <span class="text-2xl font-semibold block">
                        {{
                            formatAmount(
                                transactions
                                    .reduce((prevValue, curValue) => {
                                        let amount =
                                            typeof curValue === "string"
                                                ? JSON.parse(curValue)
                                                : curValue.amount;
                                        return prevValue + parseFloat(amount);
                                    }, 0)
                                    .toFixed(2)
                            )
                        }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
