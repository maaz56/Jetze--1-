<script setup>
import { computed } from "vue";

// A small ring chart with its own legend. The legend carries every label and
// number as text, so the chart is readable without relying on colour alone.
const props = defineProps({
    title: { type: String, required: true },
    items: { type: Array, default: () => [] },
    unit: { type: String, default: "" },
    format: { type: Function, default: (value) => String(value) },
    emptyText: { type: String, default: "No data" },
    maxSlices: { type: Number, default: 5 },
});

const COLORS = ["#0284c7", "#0d9488", "#d97706", "#e11d48", "#7c3aed", "#64748b"];
const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const slices = computed(() => {
    const rows = props.items
        .map((item) => ({ label: item.label, value: Number(item.value) || 0 }))
        .filter((item) => item.value > 0)
        .sort((a, b) => b.value - a.value);

    const head = rows.slice(0, props.maxSlices);
    const rest = rows.slice(props.maxSlices);
    if (rest.length) {
        head.push({ label: "Others", value: rest.reduce((sum, item) => sum + item.value, 0) });
    }

    const total = head.reduce((sum, item) => sum + item.value, 0);
    let offset = 0;

    return head.map((item, index) => {
        const share = total ? item.value / total : 0;
        const slice = {
            ...item,
            color: COLORS[index % COLORS.length],
            percent: Math.round(share * 100),
            dash: `${share * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
            offset: -offset * CIRCUMFERENCE,
        };
        offset += share;
        return slice;
    });
});

const total = computed(() => slices.value.reduce((sum, item) => sum + item.value, 0));
</script>

<template>
    <article class="rounded border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ title }}</h2>
            <span v-if="unit" class="text-[11px] font-medium text-slate-500 uppercase">{{ unit }}</span>
        </div>

        <div v-if="slices.length" class="flex flex-wrap items-center gap-4 sm:gap-6">
            <!-- Ring -->
            <div class="relative h-32 w-32 shrink-0">
                <svg viewBox="0 0 100 100" class="h-full w-full" role="img" :aria-label="`${title}: ${slices.map((s) => `${s.label} ${s.percent}%`).join(', ')}`">
                    <circle cx="50" cy="50" :r="RADIUS" fill="none" stroke="#f1f5f9" stroke-width="12" />
                    <circle
                        v-for="slice in slices"
                        :key="slice.label"
                        cx="50"
                        cy="50"
                        :r="RADIUS"
                        fill="none"
                        :stroke="slice.color"
                        stroke-width="12"
                        :stroke-dasharray="slice.dash"
                        :stroke-dashoffset="slice.offset"
                        transform="rotate(-90 50 50)"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center p-2 text-center">
                    <span class="max-w-full truncate text-sm font-bold text-slate-900 tabular-nums">{{ format(total) }}</span>
                    <span v-if="unit" class="text-[10px] text-slate-500 uppercase tracking-tight">{{ unit }}</span>
                </div>
            </div>

            <!-- Legend -->
            <ul class="flex min-w-[9rem] flex-1 flex-col gap-2 text-xs">
                <li v-for="slice in slices" :key="slice.label" class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: slice.color }"></span>
                        <span class="truncate text-slate-700 font-medium" :title="slice.label">{{ slice.label }}</span>
                    </div>
                    <span class="font-bold text-slate-900 tabular-nums shrink-0">{{ slice.percent }}%</span>
                </li>
            </ul>
        </div>

        <div v-else class="py-8 text-center text-xs text-slate-500">
            {{ emptyText }}
        </div>
    </article>
</template>
