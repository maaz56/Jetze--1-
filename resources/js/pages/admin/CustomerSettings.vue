<template>
    <div class="p-6 space-y-6 bg-white">
        <h1 class="text-2xl font-bold text-gray-800">Customer Settings</h1>
        
        <!-- Card & Booking Allowance Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
        </div>

        <!-- Financial Values Tiles Section -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Financial Values</h2>
                <Dialog v-model:open="isFinancialDialogOpen">
                    <DialogTrigger as-child>
                        <button class="bg-primary text-white px-4 py-2 rounded-md shadow hover:bg-primary/90 transition text-sm">
                            Edit Financial Values
                        </button>
                    </DialogTrigger>
                    
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Update Financial Values</DialogTitle>
                            <DialogDescription>
                                Enter new values for discount, margin amount, and other charges.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div class="space-y-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Discount (%)</label>
                                <input v-model.number="financialForm.discount" type="number"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Margin Amount (%)</label>
                                <input v-model.number="financialForm.amount" type="number"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Other Charges</label>
                                <input v-model.number="financialForm.otherCharges" type="number"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                            </div>
                        </div>
                        
                        <DialogFooter class="mt-4">
                            <button @click="updateFinancialValues"
                                class="bg-primary text-white px-4 py-2 rounded hover:bg-primary/90 transition">
                                Save
                            </button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <PercentIcon class="h-8 w-8 text-blue-500 mr-4" />
                    <div>
                        <p class="text-sm font-medium text-blue-500">Discount (%)</p>
                        <p class="text-2xl font-bold text-blue-500">
                            {{ CustomerMargin?.discount }}%
                        </p>
                    </div>
                </div>
                
                <div class="bg-yellow-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <CalculatorIcon class="h-8 w-8 text-yellow-500 mr-4" />
                    <div>
                        <p class="text-sm font-medium text-yellow-600">Margin Amount (%)</p>
                        <p class="text-2xl font-bold text-yellow-800">
                            {{ CustomerMargin?.margin_amount }}%
                        </p>
                    </div>
                </div>
                
                <div class="bg-primary/10 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <DollarSignIcon class="h-8 w-8 text-primary mr-4" />
                    <div>
                        <p class="text-sm font-medium text-primary">Other Charges</p>
                        <p class="text-2xl font-bold text-primary">
                            {{ CustomerMargin?.other_charges }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- One Bill Charges Section -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Abhi Pay Charges</h2>
                <Dialog v-model:open="isOneBillDialogOpen">
                    <DialogTrigger as-child>
                        <button class="bg-emerald-600 text-white px-4 py-2 rounded-md shadow hover:bg-emerald-700 transition text-sm">
                            Edit One Bill Charges
                        </button>
                    </DialogTrigger>
                    
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Update One Bill Charges</DialogTitle>
                            <DialogDescription>
                                Enter new values for one bill charges.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div class="space-y-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">One Bill Charges (Fixed Amount)</label>
                                <input v-model.number="oneBillForm.fixedCharge" type="number"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                                <p class="text-xs text-gray-500 mt-1">Fixed amount charged per one bill transaction</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Card Charges (%)</label>
                                <input v-model.number="oneBillForm.percentageCharge" type="number" step="0.01"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                                <p class="text-xs text-gray-500 mt-1">Percentage charged on one bill transaction amount</p>
                            </div>
                        </div>
                        
                        <DialogFooter class="mt-4">
                            <button @click="updateOneBillCharges"
                                class="bg-emerald-600 text-white px-4 py-2 rounded hover:bg-emerald-700 transition">
                                Save
                            </button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-emerald-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <DollarSignIcon class="h-8 w-8 text-emerald-600 mr-4" />
                    <div>
                        <p class="text-sm font-medium text-emerald-700">One Bill Charge</p>
                        <p class="text-2xl font-bold text-emerald-700">
                            {{ customerSettings?.one_bill_fixed_charge ?? customerSettings?.one_bill_charges ?? 0 }}
                        </p>
                    </div>
                </div>
                
                <div class="bg-teal-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <PercentIcon class="h-8 w-8 text-teal-600 mr-4" />
                    <div>
                        <p class="text-sm font-medium text-teal-700">Card Charges</p>
                        <p class="text-2xl font-bold text-teal-700">
                            {{ Number(customerSettings?.one_bill_percentage_charge ?? 0).toFixed(2) }}%
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Void Charges Section -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-800">Void Charges</h2>
                <Dialog v-model:open="isVoidChargesDialogOpen">
                    <DialogTrigger as-child>
                        <button class="bg-rose-600 text-white px-4 py-2 rounded-md shadow hover:bg-rose-700 transition text-sm">
                            Edit Void Charges
                        </button>
                    </DialogTrigger>

                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Update Void Charges</DialogTitle>
                            <DialogDescription>
                                Enter new value for void charges.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Void Charges</label>
                                <input v-model.number="voidChargesForm.voidCharges" type="number"
                                    class="mt-1 w-full border rounded px-3 py-2" />
                            </div>
                        </div>

                        <DialogFooter class="mt-4">
                            <button @click="updateVoidCharges"
                                class="bg-rose-600 text-white px-4 py-2 rounded hover:bg-rose-700 transition">
                                Save
                            </button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-1 gap-4">
                <div class="bg-rose-50 rounded-lg p-4 flex items-center cursor-pointer transition-all hover:shadow-md">
                    <DollarSignIcon class="h-8 w-8 text-rose-600 mr-4" />
                    <div>
                        <p class="text-sm font-medium text-rose-700">Void Charges</p>
                        <p class="text-2xl font-bold text-rose-700">
                            {{ Number(customerSettings?.void_charges ?? 0).toFixed(2) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
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
import {
    FETCH_CUSTOMER_MARGIN,
    FETCH_CUSTOMER_SETTINGS,
    SAVE_CUSTOMER_MARGIN,
    UPDATE_CUSTOMER_SETTINGS,
} from "@/services/store/actions.type";

const store = useStore();

const isFinancialDialogOpen = ref(false);
const isOneBillDialogOpen = ref(false);
const isVoidChargesDialogOpen = ref(false);

const customerSettings = computed(() => store.getters['customer/customerSettings']);
const CustomerMargin = computed(() => store.getters['customerMargin/customerMargin']);

const financialForm = ref({
    discount: 0,
    otherCharges: 0,
    amount: 0,
});

const oneBillForm = ref({
    fixedCharge: 0,
    percentageCharge: 0,
});

const voidChargesForm = ref({
    voidCharges: 0,
});

function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS);
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

function updateFinancialValues() {
    const marginPayload = {
        id: 1,
        discount: financialForm.value.discount ?? 0,
        otherCharges: financialForm.value.otherCharges ?? 0,
        amount: financialForm.value.amount ?? 0,
    };

    store.dispatch("customerMargin/" + SAVE_CUSTOMER_MARGIN, marginPayload).then(() => {
        fetchCustomerMarginValues();
        isFinancialDialogOpen.value = false;
    });
}

function updateOneBillCharges() {
    const oneBillPayload = {
        one_bill_fixed_charge: oneBillForm.value.fixedCharge ?? 0,
        one_bill_percentage_charge: oneBillForm.value.percentageCharge ?? 0,
    };

    store.dispatch("customer/" + UPDATE_CUSTOMER_SETTINGS, oneBillPayload).then(() => {
        fetchCustomerSettings();
        isOneBillDialogOpen.value = false;
    });
}

function updateVoidCharges() {
    const voidPayload = {
        void_charges: voidChargesForm.value.voidCharges ?? 0,
    };

    store.dispatch("customer/" + UPDATE_CUSTOMER_SETTINGS, voidPayload).then(() => {
        fetchCustomerSettings();
        isVoidChargesDialogOpen.value = false;
    });
}

watch(CustomerMargin, (margin) => {
    if (!margin) return;
    financialForm.value.discount = Number(margin.discount ?? 0);
    financialForm.value.otherCharges = Number(margin.other_charges ?? margin.otherCharges ?? 0);
    financialForm.value.amount = Number(margin.margin_amount ?? margin.amount ?? 0);
}, { immediate: true });

watch(customerSettings, (settings) => {
    if (!settings) return;
    oneBillForm.value.fixedCharge = Number(settings.one_bill_fixed_charge ?? settings.one_bill_charges ?? 0);
    oneBillForm.value.percentageCharge = Number(settings.one_bill_percentage_charge ?? 0);
    voidChargesForm.value.voidCharges = Number(settings.void_charges ?? 0);
}, { immediate: true });

onMounted(() => {
    fetchCustomerMarginValues();
    fetchCustomerSettings();
});
</script>

<style scoped>
/* Optional: Scoped styles here if needed */
</style>
