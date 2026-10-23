<script setup>
import { ArrowRight, BadgeCheck, BadgeX, Luggage, Tag } from "lucide-vue-next";

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

function fareBookingCodes(fare) {
    return [fare?.rbd_code, ...(fare?.booking_codes ?? []).map((code) => code?.booking_code)]
        .filter((code, index, codes) => code && codes.indexOf(code) === index);
}

function fareBasisCode(fare) {
    return fare?.fare_basis_code || fare?.booking_codes?.[0]?.fare_basis_code;
}

function fareCount(groups) {
    return groups.reduce((total, group) => total + (group.fares?.length ?? 0), 0);
}

</script>

<template>
    <div v-if="groups.length" class="w-full bg-slate-50/60">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4 sm:px-6">
            <div>
                <h3 class="text-base font-bold text-slate-950">Fare options</h3>
                <p class="mt-0.5 text-sm text-slate-500">Compare baggage, flexibility, and total price before booking.</p>
            </div>
            <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">
                {{ fareCount(groups) }} option{{ fareCount(groups) === 1 ? "" : "s" }}
            </span>
        </header>
        <div class="space-y-3">
            <div v-for="group in groups" :key="group.index" class="space-y-3">
                <p v-if="groups.length > 1" class="border-y border-slate-200 bg-white px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 sm:px-5">{{ group.label }}</p>
                <article
                    v-for="(fare, index) in group.fares"
                    :key="fare.ref_id || fare.fare_references?.join(':') || index"
                    class="relative cursor-pointer overflow-hidden border-y bg-white transition sm:rounded-md sm:border"
                    :class="selected(fare, group.index, references) ? 'border-primary bg-primary/[0.025] ring-1 ring-primary/25' : 'border-slate-200 hover:border-primary/50 hover:shadow-md'"
                    @click="$emit('select', group.index, fare)"
                >
                    <div v-if="selected(fare, group.index, references)" class="absolute inset-y-0 left-0 w-1 bg-amber-400"></div>
                    <div class="flex flex-col gap-4 bg-slate-100/80 px-5 py-4 sm:grid sm:grid-cols-[auto_minmax(0,1fr)_auto_auto] sm:items-center sm:gap-5 sm:px-6">
                        <span
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border-2 transition"
                            :class="selected(fare, group.index, references) ? 'border-primary' : 'border-slate-400'"
                            aria-hidden="true"
                        >
                            <span v-if="selected(fare, group.index, references)" class="h-2 w-2 rounded-full bg-primary"></span>
                        </span>
                        <div class="min-w-0 flex-1 space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span v-if="fare.brand_tier" class="rounded bg-primary px-2.5 py-1 text-xs font-bold text-white">{{ fare.brand_tier }}</span>
                                <span class="inline-flex items-center gap-1 text-sm font-medium" :class="fare.is_refundable ? 'text-emerald-700' : 'text-rose-600'">
                                    <BadgeCheck v-if="fare.is_refundable" class="h-4 w-4" />
                                    <BadgeX v-else class="h-4 w-4" />
                                    {{ fare.is_refundable ? "Refundable" : "Non-refundable" }}
                                </span>
                                <span v-if="fare.hold === true" class="rounded bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Hold</span>
                                <span v-if="fareBasisCode(fare)" class="inline-flex items-center gap-1 text-xs text-slate-500"><Tag class="h-4 w-4" /> {{ fareBasisCode(fare) }}</span>
                            </div>
                            <div v-for="leg in fareLegs(fare, group.index)" :key="leg.index" class="grid min-w-0 grid-cols-[auto_minmax(7rem,0.7fr)_minmax(0,1fr)] items-center gap-x-3 gap-y-1 text-sm text-slate-700">
                                <span class="rounded bg-slate-500 px-2 py-0.5 text-xs font-bold text-white">{{ fare.fare_references?.length ? (leg.index === 0 ? "OW" : "RT") : "OW" }}</span>
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-2 overflow-hidden whitespace-nowrap">
                                        <span class="truncate font-bold text-slate-900">{{ leg.name || "Standard fare" }}</span>
                                        <span v-for="code in fareBookingCodes(fare)" :key="code" class="shrink-0 rounded border border-slate-300 bg-white px-1.5 py-0.5 font-mono text-xs font-bold text-slate-600">{{ code }}</span>
                                    </div>
                                    <span v-if="leg.route" class="mt-0.5 block truncate text-xs font-semibold text-slate-500">{{ leg.route }}</span>
                                </div>
                                <div v-if="!baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab)" class="flex min-w-0 flex-wrap items-center justify-center gap-x-4 gap-y-1 text-slate-600">
                                    <span v-for="summary in baggageSummaries(leg.baggagePolicies)" :key="summary.label" class="inline-flex items-center gap-1 whitespace-nowrap">
                                        <Luggage class="h-4 w-4 shrink-0 text-primary" /> {{ summary.label }}: {{ summary.description }}
                                    </span>
                                </div>
                                <button v-else type="button" class="inline-flex items-center justify-center gap-1 font-semibold text-primary hover:underline" @click.stop="$emit('view-baggage', group.index, fare)">
                                    View baggage <ArrowRight class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3 self-end sm:self-auto">
                            <p class="text-xl font-extrabold tabular-nums text-slate-950">{{ formatPrice(fare, group.index) }}</p>
                            <button type="button" :disabled="bookingDisabled" class="inline-flex items-center justify-center rounded-md bg-primary px-5 py-2.5 text-base font-bold text-primary-foreground transition hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50" @click.stop="$emit('book', group.index, fare)">
                                Book now
                            </button>
                        </div>
                    </div>
                    <p v-if="fare.fare_policies?.length" class="border-t border-slate-100 bg-slate-50/70 px-4 py-2 text-xs text-slate-500 sm:px-5">
                        {{ fare.fare_policies.map((policy) => policy?.description || policy?.title).filter(Boolean).join(' • ') }}
                    </p>
                </article>
            </div>
        </div>
    </div>
</template>
