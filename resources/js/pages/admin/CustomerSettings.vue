<template>
    <!-- <pre>{{ CustomerMargin }}</pre> -->
    <div class="p-6 space-y-6 bg-white">
        <!-- Top Right Button -->
        <h1 class="text-2xl font-bold text-gray-800">Customer Settings</h1>
        <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-500 mb-1">Card Payment Allowance</p>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div :class="[
                        'h-3 w-3 rounded-full mr-2',
                        customerSettings?.is_card_allowed
                            ? 'bg-green-500'
                            : 'bg-yellow-500',
                    ]"></div>
                    <span>{{
                        customerSettings?.is_card_allowed
                            ? "Allowed"
                            : "Not Allowed"
                    }}</span>
                </div>
                <div>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="customerSettings?.is_card_allowed" @change="updateCustomerSettings('card')"
                            class="sr-only peer" />
                        <div
                            class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-500 mb-1">Booking Allowance</p>
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div :class="[
                        'h-3 w-3 rounded-full mr-2',
                        customerSettings?.is_booking_allowed
                            ? 'bg-green-500'
                            : 'bg-yellow-500',
                    ]"></div>
                    <span>{{
                        customerSettings?.is_booking_allowed
                            ? "Allowed"
                            : "Not Allowed"
                    }}</span>
                </div>
                <div>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="customerSettings?.is_booking_allowed" @change="updateCustomerSettings('booking')"
                            class="sr-only peer" />
                        <div
                            class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary">
                        </div>
                    </label>
                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <Dialog v-model:open="isDialogOpen">
                <DialogTrigger as-child>
                    <button class="bg-primary text-white px-6 py-2 rounded-md shadow hover:bg-primary/90 transition">
                        Edit Values
                    </button>
                </DialogTrigger>

                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Update Financial Values</DialogTitle>
                        <DialogDescription>
                            Enter new values for discount, other charges, and
                            amount.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 mt-4">

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Discount (%)</label>
                            <input v-model.number="form.discount" type="number"
                                class="mt-1 w-full border rounded px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700"> Margin Amount(%) </label>
                            <input v-model.number="form.amount" type="number"
                                class="mt-1 w-full border rounded px-3 py-2" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Other Charges</label>
                            <input v-model.number="form.otherCharges" type="number"
                                class="mt-1 w-full border rounded px-3 py-2" />
                        </div>


                    </div>

                    <DialogFooter class="mt-4">
                        <button @click="updateTiles"
                            class="bg-primary text-white px-4 py-2 rounded hover:bg-primary/90 transition">
                            Save
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>

        <!-- Tiles -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-blue-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                <PercentIcon class="h-8 w-8 text-blue-500 mr-4" />
                <div>
                    <p class="text-sm font-medium text-blue-500">Discount ( % )</p>
                    <p class="text-2xl font-bold text-blue-500">
                        {{ CustomerMargin?.discount }}%
                    </p>
                </div>
            </div>



            <div class="bg-yellow-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                <CalculatorIcon class="h-8 w-8 text-yellow-500 mr-4" />
                <div>
                    <p class="text-sm font-medium text-yellow-600">Margin Amount ( % )</p>
                    <p class="text-2xl font-bold text-yellow-800">
                        {{ CustomerMargin?.margin_amount }} %
                    </p>
                </div>
            </div>

            <div class="bg-primary/10 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                <DollarSignIcon class="h-8 w-8 text-primary mr-4" />
                <div>
                    <p class="text-sm font-medium text-primary">
                        Other Charges
                    </p>
                    <p class="text-2xl font-bold text-primary">
                        {{ CustomerMargin?.other_charges }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { PercentIcon, DollarSignIcon, CalculatorIcon } from "lucide-vue-next";
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
import { FETCH_CUSTOMER_MARGIN, FETCH_CUSTOMER_SETTINGS, SAVE_CUSTOMER_MARGIN, UPDATE_CUSTOMER_SETTINGS} from "@/services/store/actions.type";



const store = useStore();

const isDialogOpen = ref(false);

const customerSettings = computed(() => store.getters['customer/customerSettings']);

const CustomerMargin = computed(() => store.getters['customerMargin/customerMargin']);
const tiles = ref({
    discount: 0,
    otherCharges: 0,
    amount: 0,
});

const form = ref({
    discount: 0,
    otherCharges: 0,
    amount: 0,
});
function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN)
}

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS)
}
function updateCustomerSettings(type) {
  const payload = {
    is_card_allowed: type === 'card' ? !customerSettings.value.is_card_allowed : customerSettings.value.is_card_allowed,
    is_booking_allowed: type === 'booking' ? !customerSettings.value.is_booking_allowed : customerSettings.value.is_booking_allowed,
  };
  store.dispatch("customer/" + UPDATE_CUSTOMER_SETTINGS, payload).then(() => {
    fetchCustomerSettings();
  });
}
function updateTiles() {
    tiles.value = { id: 1, ...form.value };

    store.dispatch("customerMargin/" + SAVE_CUSTOMER_MARGIN, tiles.value).then(() => {
        fetchCustomerMarginValues();
    });

    isDialogOpen.value = false;
}
onMounted(() => {
    fetchCustomerMarginValues();
    fetchCustomerSettings();
})
</script>

<style scoped>
/* Optional: Scoped styles here if needed */
</style>
