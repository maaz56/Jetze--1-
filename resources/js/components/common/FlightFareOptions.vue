<script setup>
import { ChevronDown, Luggage, ArrowRight, PlaneTakeoff } from "lucide-vue-next";

defineProps({
    groups: { type: Array, required: true },
    references: { type: Array, required: true },
    formatPrice: { type: Function, required: true },
    baggageSummaries: { type: Function, required: true },
    fareLegs: { type: Function, required: true },
    bookingDisabled: { type: Boolean, default: false },
});
defineEmits(["select", "view-baggage", "details", "book"]);

function selected(fare, index, references) {
    return fare.fare_references?.length
        ? fare.fare_references.every((reference, i) => references[i] === reference)
        : fare.ref_id === references[index];
}
</script>

<template>
    <details v-if="groups.length" name="flight-fare-options" class="group w-full border-t border-slate-200 bg-white">
        <summary class="flex cursor-pointer list-none items-center justify-center gap-2 px-6 py-3.5 text-sm font-semibold text-primary transition hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
            <span>More fare options</span>
            <ChevronDown class="h-4 w-4 text-primary transition-transform group-open:rotate-180" />
        </summary>
        <div class="space-y-4 px-4 pb-4 sm:px-6">
            <div v-for="group in groups" :key="group.index" class="space-y-2.5">
                <p v-if="groups.length > 1" class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</p>
                <article
                    v-for="(fare, index) in group.fares"
                    :key="fare.ref_id || fare.fare_references?.join(':') || index"
                    class="cursor-pointer overflow-hidden rounded border-l-4 transition hover:border-slate-300"
                    :class="selected(fare, group.index, references) ? 'border-blue-200 border-l-primary bg-blue-50/50' : 'border-slate-200 border-l-slate-300 bg-slate-50/50 hover:bg-white'"
                    @click="$emit('select', group.index, fare)"
                >
                    <div class="fare-option-layout flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5 sm:px-5">
                        <div class="fare-option-content w-full min-w-0 space-y-2">
                        <div v-for="leg in fareLegs(fare, group.index)" :key="leg.index" class="grid grid-cols-[2rem_minmax(0,7rem)_minmax(0,1fr)] items-center gap-x-3 gap-y-1.5 text-sm text-slate-700">
                            <span v-if="fare.fare_references?.length" class="justify-self-start rounded bg-slate-100 px-1.5 py-0.5 text-xs font-semibold text-slate-600">{{ leg.index === 0 ? 'OW' : 'RT' }}</span>
                            <span v-else aria-hidden="true"></span>
                            <button type="button" :aria-pressed="selected(fare, group.index, references)" class="min-w-0 break-words text-left font-semibold text-slate-900 hover:text-primary" @click.stop="$emit('select', group.index, fare)">{{ leg.name || 'Standard fare' }}</button>
                            <template v-if="!baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab)">
                                <span v-for="summary in baggageSummaries(leg.baggagePolicies).filter((item) => item.label === 'Checked')" :key="summary.label" class="inline-flex min-w-0 flex-wrap items-center gap-1.5">
                                    <Luggage class="h-4 w-4 shrink-0 text-primary" />
                                    <span class="font-semibold">{{ summary.label }}:</span>
                                    <span>{{ summary.description }}</span>
                                </span>
                            </template>
                            <button v-else-if="!fare.fare_references?.length" type="button" class="inline-flex items-center gap-1.5 py-1 font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('view-baggage', group.index, fare)">
                                View baggage <ArrowRight class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="inline-flex shrink-0 items-center justify-center gap-1.5 py-2 text-sm font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('details', group.index, fare)">
                                <PlaneTakeoff class="h-4 w-4" /> Flight details
                            </button>
                            <button v-if="fare.fare_references?.length && fareLegs(fare, group.index).some((leg) => baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab))" type="button" class="inline-flex shrink-0 items-center justify-center gap-1.5 py-2 text-sm font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('view-baggage', group.index, fare)">
                                <Luggage class="h-4 w-4" /> View baggage
                            </button>
                        </div>
                        <span class="ml-auto shrink-0 text-right text-lg font-extrabold tabular-nums text-primary">{{ formatPrice(fare, group.index) }}</span>
                        <button type="button" :disabled="bookingDisabled" class="inline-flex shrink-0 items-center justify-center rounded bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50" @click.stop="$emit('book', group.index, fare)">
                            Book now
                        </button>
                    </div>
                    <p v-if="fare.fare_policies?.length" class="border-t border-slate-100 px-3 py-2 text-xs text-slate-500 sm:px-4">
                        {{ fare.fare_policies.map((policy) => policy?.description || policy?.title).filter(Boolean).join(' • ') }}
                    </p>
                </article>
            </div>
        </div>
    </details>
</template>

<style scoped>
@container (min-width: 780px) {
    .fare-option-layout {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 15rem 8rem auto;
    }
}
</style>
