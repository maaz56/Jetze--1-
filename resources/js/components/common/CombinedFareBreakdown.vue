<script setup>
import { computed } from "vue";
import { aggregateFareBreakdown } from "./aggregateFareBreakdown";

const props = defineProps({
    fares: { type: Array, required: true },
    resolveMoney: { type: Function, required: true },
    formatMoney: { type: Function, required: true },
    total: { type: String, required: true },
    taxUnavailable: { type: Boolean, default: false },
});
const rows = computed(() => aggregateFareBreakdown(props.fares, props.resolveMoney));
const labels = computed(() => [...new Set(props.fares.map((fare) => fare?.name_class || fare?.class).filter(Boolean))]);
const columns = [
    ["base_price", "Base Price"], ["taxes", "Taxes"], ["fees", "Fees"],
    ["service_charges", "Service Charges"], ["surchage", "Surcharge"],
    ["discount", "Discount"], ["total_price", "Total"],
];
</script>

<template>
    <div class="overflow-hidden rounded border border-gray-200 bg-white shadow-md shadow-slate-200/80">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-primary/20 bg-primary/5 px-4 py-3">
            <div>
                <h4 class="font-bold text-gray-800">Fare Breakdown</h4>
                <p v-if="labels.length" class="mt-1 text-sm text-gray-600">Selected fare: <span class="font-semibold text-primary">{{ labels.join(' / ') }}</span></p>
            </div>
            <span class="text-lg font-bold text-primary">{{ total }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] border-collapse text-left text-xs sm:text-sm">
                <thead class="bg-gray-50 text-gray-900">
                    <tr>
                        <th class="border-b border-gray-200 px-3 py-3 whitespace-nowrap">Passenger Type</th>
                        <th v-for="column in columns" :key="column[0]" class="border-b border-gray-200 px-3 py-3 whitespace-nowrap">{{ column[1] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.traveler_type" class="text-gray-700">
                        <td class="border-b border-gray-100 px-3 py-3 font-medium whitespace-nowrap">{{ row.traveler_type }}<template v-if="row.total_passenger != null"> X {{ row.total_passenger }}</template></td>
                        <td v-for="column in columns" :key="column[0]" class="border-b border-gray-100 px-3 py-3 whitespace-nowrap" :class="column[0] === 'total_price' ? 'font-bold text-primary' : ''">
                            {{ row.values[column[0]]?.available ? (column[0] === 'discount' && row.values[column[0]].amount > 0 ? '-' : '') + formatMoney(row.values[column[0]]) : 'Unavailable' }}
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="8" class="px-3 py-4 text-center text-gray-500">No passenger fare data available for this fare.</td></tr>
                </tbody>
            </table>
        </div>
        <p v-if="taxUnavailable" class="px-4 py-3 text-xs text-amber-700">Tax breakdown is unavailable because the provider's tax details do not reconcile. The fare total remains unchanged.</p>
    </div>
</template>
