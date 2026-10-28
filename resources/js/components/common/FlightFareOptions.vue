<script setup>
import { ArrowRight, BadgeCheck, BadgeX, Luggage, Tag } from "lucide-vue-next";

defineProps({
    groups: { type: Array, required: true },
    references: { type: Array, required: true },
    formatPrice: { type: Function, required: true },
    baggageSummaries: { type: Function, required: true },
    fareLegs: { type: Function, required: true },
});
defineEmits(["select", "view-baggage", "details"]);

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
    <div v-if="groups.length" class="w-full">
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-6 sm:py-4">
            <div class="min-w-0">
                <h3 class="text-base font-bold text-slate-950">Choose your fare</h3>
                <p class="mt-0.5 text-xs text-slate-500 sm:text-sm">Compare baggage, flexibility and price.</p>
            </div>
            <span class="shrink-0 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary">
                {{ fareCount(groups) }} option{{ fareCount(groups) === 1 ? "" : "s" }}
            </span>
        </header>
        <div class="space-y-4 p-3 sm:p-5">
            <section v-for="group in groups" :key="group.index" class="space-y-2.5">
                <p v-if="groups.length > 1" class="px-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ group.label }}</p>
                <article
                    v-for="(fare, index) in group.fares"
                    :key="fare.ref_id || fare.fare_references?.join(':') || index"
                    role="radio"
                    :aria-checked="selected(fare, group.index, references)"
                    tabindex="0"
                    class="relative cursor-pointer rounded-xl border bg-white p-3.5 transition duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:p-4"
                    :class="selected(fare, group.index, references) ? 'border-primary bg-primary/[0.03] shadow-sm ring-1 ring-primary' : 'border-slate-200 hover:border-primary/40'"
                    @click="$emit('select', group.index, fare)"
                    @keydown.enter.prevent="$emit('select', group.index, fare)"
                    @keydown.space.prevent="$emit('select', group.index, fare)"
                >
                    <!-- Fare name + price -->
                    <div class="flex items-start gap-3">
                        <span
                            class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full border-2 transition"
                            :class="selected(fare, group.index, references) ? 'border-primary' : 'border-slate-300'"
                            aria-hidden="true"
                        >
                            <span v-if="selected(fare, group.index, references)" class="h-2 w-2 rounded-full bg-primary"></span>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-extrabold uppercase tracking-wide text-slate-900">
                                {{ fareLegs(fare, group.index).map((leg) => leg.name || "Standard fare").filter((name, i, names) => names.indexOf(name) === i).join(" / ") }}
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-semibold">
                                <span v-if="fare.brand_tier" class="text-primary">{{ fare.brand_tier }}</span>
                                <span v-if="fare.brand_tier" class="text-slate-300">•</span>
                                <span class="inline-flex items-center gap-0.5" :class="fare.is_refundable ? 'text-emerald-600' : 'text-rose-500'">
                                    <BadgeCheck v-if="fare.is_refundable" class="h-3.5 w-3.5" />
                                    <BadgeX v-else class="h-3.5 w-3.5" />
                                    {{ fare.is_refundable ? "Refundable" : "Non-refundable" }}
                                </span>
                                <template v-if="fare.hold === true">
                                    <span class="text-slate-300">•</span>
                                    <span class="text-amber-600">Hold</span>
                                </template>
                            </div>
                        </div>
                        <p
                            class="shrink-0 text-right text-base font-extrabold tabular-nums sm:text-lg"
                            :class="selected(fare, group.index, references) ? 'text-primary' : 'text-slate-950'"
                        >
                            {{ formatPrice(fare, group.index) }}
                        </p>
                    </div>

                    <!-- Legs: route, booking class and baggage -->
                    <div class="mt-3 space-y-2.5 border-t border-dashed border-slate-200 pt-3">
                        <div v-for="leg in fareLegs(fare, group.index)" :key="leg.index" class="space-y-2">
                            <div class="flex min-w-0 items-center gap-2 text-xs text-slate-500">
                                <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ fare.fare_references?.length ? (leg.index === 0 ? "OW" : "RT") : "OW" }}</span>
                                <span v-if="leg.route" class="truncate font-semibold text-slate-700">{{ leg.route }}</span>
                                <span v-if="fare.fare_references?.length && leg.name" class="truncate">· {{ leg.name }}</span>
                                <span class="ml-auto flex shrink-0 items-center gap-1">
                                    <span v-for="code in fareBookingCodes(fare)" :key="code" class="rounded border border-slate-200 px-1 font-mono text-[10px] font-bold text-slate-500" :title="`Booking class ${code}`">{{ code }}</span>
                                </span>
                            </div>
                            <div v-if="!baggageSummaries(leg.baggagePolicies).some((summary) => summary.actionTab)" class="grid grid-cols-2 gap-2">
                                <span v-for="summary in baggageSummaries(leg.baggagePolicies)" :key="summary.label" class="flex min-w-0 items-center gap-2 rounded-lg bg-slate-50 px-2.5 py-2">
                                    <Luggage class="h-4 w-4 shrink-0 text-primary" />
                                    <span class="min-w-0 leading-tight">
                                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ summary.label }}</span>
                                        <span class="block truncate text-xs font-semibold text-slate-700">{{ summary.description }}</span>
                                    </span>
                                </span>
                            </div>
                            <button v-else type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline" @click.stop="$emit('view-baggage', group.index, fare)">
                                View baggage <ArrowRight class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <p v-if="fareBasisCode(fare) || fare.fare_policies?.length" class="mt-2.5 flex items-center gap-1 text-[10px] text-slate-400">
                        <Tag v-if="fareBasisCode(fare)" class="h-3 w-3 shrink-0" />
                        <span class="truncate">
                            {{ [fareBasisCode(fare), ...(fare.fare_policies ?? []).map((policy) => policy?.description || policy?.title)].filter(Boolean).join(" • ") }}
                        </span>
                    </p>
                </article>
            </section>
        </div>
    </div>
</template>
