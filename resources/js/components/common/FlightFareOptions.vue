<script setup>
import { ChevronDown, Luggage, ArrowRight, PlaneTakeoff, Ticket } from "lucide-vue-next";

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

function fareCount(groups) {
    return groups.reduce((total, group) => total + (group.fares?.length ?? 0), 0);
}
</script>

<template>
    <details v-if="groups.length" name="flight-fare-options" class="group w-full border-t border-slate-200 bg-white">
        <summary class="flex cursor-pointer list-none items-center justify-center gap-2 px-5 py-3 text-sm transition hover:bg-slate-50 sm:px-6 [&::-webkit-details-marker]:hidden">
            <span class="inline-flex items-center gap-2 font-semibold text-slate-700">
                <Ticket class="h-4 w-4 text-primary" />
                Fare options
                <span class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ fareCount(groups) }}</span>
            </span>
            <ChevronDown class="h-4 w-4 text-primary transition-transform group-open:rotate-180" />
        </summary>
        <div class="space-y-4 border-t border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-6">
            <div v-for="group in groups" :key="group.index" class="space-y-2">
                <p v-if="groups.length > 1" class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                <article
                    v-for="(fare, index) in group.fares"
                    :key="fare.ref_id || fare.fare_references?.join(':') || index"
                    class="cursor-pointer overflow-hidden rounded border bg-white transition"
                    :class="selected(fare, group.index, references) ? 'border-primary ring-1 ring-primary/30' : 'border-slate-200 hover:border-slate-300 hover:shadow-sm'"
                    @click="$emit('select', group.index, fare)"
                >
                    <div class="fare-option-layout flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3 sm:px-5">
                        <div class="fare-option-content flex w-full min-w-0 items-start gap-3">
                            <span
                                class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition"
                                :class="selected(fare, group.index, references) ? 'border-primary' : 'border-slate-300'"
                                aria-hidden="true"
                            >
                                <span v-if="selected(fare, group.index, references)" class="h-2 w-2 rounded-full bg-primary"></span>
                            </span>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div v-for="leg in fareLegs(fare, group.index)" :key="leg.index" class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-700">
                                    <span v-if="fare.fare_references?.length" class="rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold text-slate-600">{{ leg.index === 0 ? 'OW' : 'RT' }}</span>
                                    <button type="button" :aria-pressed="selected(fare, group.index, references)" class="min-w-0 break-words text-left font-semibold text-slate-900 hover:text-primary" @click.stop="$emit('select', group.index, fare)">{{ leg.name || 'Standard fare' }}</button>
                                    <template v-if="!baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab)">
                                        <span v-for="summary in baggageSummaries(leg.baggagePolicies).filter((item) => item.label === 'Checked')" :key="summary.label" class="inline-flex min-w-0 flex-wrap items-center gap-1 text-xs text-slate-600">
                                            <Luggage class="h-3.5 w-3.5 shrink-0 text-primary" />
                                            <span class="font-semibold">{{ summary.label }}:</span>
                                            <span>{{ summary.description }}</span>
                                        </span>
                                    </template>
                                    <button v-else-if="!fare.fare_references?.length" type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('view-baggage', group.index, fare)">
                                        View baggage <ArrowRight class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pl-7">
                            <button v-if="fare.fare_references?.length && fareLegs(fare, group.index).some((leg) => baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab))" type="button" class="inline-flex shrink-0 items-center gap-1.5 py-1 text-xs font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('view-baggage', group.index, fare)">
                                <Luggage class="h-3.5 w-3.5" /> View baggage
                            </button>
                            <button type="button" class="inline-flex shrink-0 items-center gap-1.5 py-1 text-xs font-semibold text-primary underline-offset-4 transition hover:text-primary/80 hover:underline focus-visible:rounded focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary" @click.stop="$emit('details', group.index, fare)">
                                <PlaneTakeoff class="h-3.5 w-3.5" /> Flight details
                            </button>
                        </div>
                        <span class="ml-auto shrink-0 text-right text-lg font-extrabold tabular-nums text-primary">{{ formatPrice(fare, group.index) }}</span>
                        <button
                            type="button"
                            :disabled="bookingDisabled"
                            class="inline-flex shrink-0 items-center justify-center rounded px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="selected(fare, group.index, references) ? 'bg-primary text-white hover:bg-primary/90' : 'border border-primary text-primary hover:bg-primary hover:text-white'"
                            @click.stop="$emit('book', group.index, fare)"
                        >
                            Book now
                        </button>
                    </div>
                    <p v-if="fare.fare_policies?.length" class="border-t border-slate-100 bg-slate-50/70 px-4 py-2 text-xs text-slate-500 sm:px-5">
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
        grid-template-columns: minmax(0, 1fr) auto 8rem auto;
    }

    .fare-option-layout > div:nth-child(2) {
        padding-left: 0;
    }
}
</style>
