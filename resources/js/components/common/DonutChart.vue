<script setup>
import { computed } from "vue";

// A small ring chart with its own legend. The legend carries every label and
// number as text, so the chart is readable without relying on colour alone.
const props = defineProps({
    title: { type: String, required: true },
    // [{ label, value }]
    items: { type: Array, default: () => [] },
    // Text under the total in the middle of the ring, e.g. "bookings".
    unit: { type: String, default: "" },
    format: { type: Function, default: (value) => String(value) },
    emptyText: { type: String, default: "No data" },
    // Slices beyond this many are merged into "Others".
    maxSlices: { type: Number, default: 5 },
});

const COLORS = ["#075985", "#0d9488", "#d97706", "#be123c", "#6d28d9", "#64748b"];
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
    <article class="donut-card">
        <h2 class="donut-title">{{ title }}</h2>

        <div v-if="slices.length" class="donut-body">
            <div class="donut-ring">
                <svg viewBox="0 0 100 100" role="img" :aria-label="`${title}: ${slices.map((s) => `${s.label} ${s.percent}%`).join(', ')}`">
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
                <div class="donut-center">
                    <span class="donut-total">{{ format(total) }}</span>
                    <span v-if="unit" class="donut-unit">{{ unit }}</span>
                </div>
            </div>

            <ul class="donut-legend">
                <li v-for="slice in slices" :key="slice.label">
                    <span class="legend-dot" :style="{ backgroundColor: slice.color }"></span>
                    <span class="legend-label">{{ slice.label }}</span>
                    <span class="legend-value">{{ slice.percent }}%</span>
                </li>
            </ul>
        </div>

        <p v-else class="donut-empty">{{ emptyText }}</p>
    </article>
</template>

<style scoped>
.donut-card {
    border: 1px solid rgb(226 232 240);
    border-radius: 1rem;
    background-color: #fff;
    padding: 1.125rem;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.donut-title {
    margin-bottom: 0.875rem;
    font-size: 1rem;
    font-weight: 600;
    color: rgb(15 23 42);
}

.donut-body {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 1rem 1.25rem;
}

.donut-ring {
    position: relative;
    height: 8.5rem;
    width: 8.5rem;
    flex-shrink: 0;
}

.donut-ring svg {
    height: 100%;
    width: 100%;
}

.donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 0 1.5rem;
    text-align: center;
}

.donut-total {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
    color: rgb(15 23 42);
}

.donut-unit {
    font-size: 0.6875rem;
    color: rgb(100 116 139);
}

.donut-legend {
    display: flex;
    min-width: 9rem;
    flex: 1;
    flex-direction: column;
    gap: 0.5rem;
}

.donut-legend li {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
    color: rgb(51 65 85);
}

.legend-dot {
    height: 0.625rem;
    width: 0.625rem;
    flex-shrink: 0;
    border-radius: 9999px;
}

.legend-label {
    min-width: 0;
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.legend-value {
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: rgb(15 23 42);
}

.donut-empty {
    padding: 2.5rem 0;
    text-align: center;
    font-size: 0.875rem;
    color: rgb(100 116 139);
}
</style>
