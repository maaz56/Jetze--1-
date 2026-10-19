<template>
    <div class="p-6 space-y-6 bg-white">
        <h1 class="text-2xl font-bold text-gray-800">Customer Settings</h1>
        
        <!-- AbhiPay / One Bill settings are temporarily disabled. Keep the section for an easy re-enable. -->
        <div v-if="false">
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

        <div class="rounded-lg border border-primary/20 bg-primary/5 p-4">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Nomod Charges</h2>
                    <p class="text-sm text-gray-500">Applied only to new Nomod checkout attempts.</p>
                </div>
                <button v-on:click="updateNomodCharges" class="bg-primary text-white px-4 py-2 rounded hover:bg-primary/90 transition text-sm">Save</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Percentage charge (%)</label>
                    <input v-model.number="nomodForm.percentageCharge" type="number" min="0" max="100" step="0.01" class="mt-1 w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fixed charge (booking currency)</label>
                    <input v-model.number="nomodForm.fixedCharge" type="number" min="0" step="0.01" class="mt-1 w-full border rounded px-3 py-2" />
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500">Current setting: {{ Number(customerSettings?.nomod_percentage_charge ?? 2.5).toFixed(2) }}% + {{ Number(customerSettings?.nomod_fixed_charge ?? 0).toFixed(2) }} fixed.</p>
        </div>

    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { PercentIcon, DollarSignIcon } from "lucide-vue-next";
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
    FETCH_CUSTOMER_SETTINGS,
    UPDATE_CUSTOMER_SETTINGS,
} from "@/services/store/actions.type";

const store = useStore();

const isOneBillDialogOpen = ref(false);

const customerSettings = computed(() => store.getters['customer/customerSettings']);

const oneBillForm = ref({
    fixedCharge: 0,
    percentageCharge: 0,
});

const nomodForm = ref({
    percentageCharge: 2.5,
    fixedCharge: 0,
});

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS);
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

function updateNomodCharges() {
    store.dispatch("customer/" + UPDATE_CUSTOMER_SETTINGS, {
        nomod_percentage_charge: nomodForm.value.percentageCharge ?? 0,
        nomod_fixed_charge: nomodForm.value.fixedCharge ?? 0,
    }).then(() => fetchCustomerSettings());
}

watch(customerSettings, (settings) => {
    if (!settings) return;
    oneBillForm.value.fixedCharge = Number(settings.one_bill_fixed_charge ?? settings.one_bill_charges ?? 0);
    oneBillForm.value.percentageCharge = Number(settings.one_bill_percentage_charge ?? 0);
    nomodForm.value.percentageCharge = Number(settings.nomod_percentage_charge ?? 2.5);
    nomodForm.value.fixedCharge = Number(settings.nomod_fixed_charge ?? 0);
}, { immediate: true });

onMounted(() => {
    fetchCustomerSettings();
});
</script>

<style scoped>
/* Optional: Scoped styles here if needed */
</style>
