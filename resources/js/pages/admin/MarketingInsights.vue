<script setup>
import { formatAmount, getSelectedCurrencyCode } from "@/lib/utils";
import DonutChart from "@/components/common/DonutChart.vue";
import apiService from "@/services/store/apiService";
import { Award, Plane, Search, TrendingUp } from "lucide-vue-next";
import moment from "moment";
import { computed, onMounted, ref } from "vue";
import { toast } from "vue3-toastify";

const presets = [
    { key: "7", label: "Last 7 days", days: 7 },
    { key: "30", label: "Last 30 days", days: 30 },
    { key: "90", label: "Last 90 days", days: 90 },
    { key: "365", label: "Last 12 months", days: 365 },
];

const activePreset = ref("30");
const from = ref(moment().subtract(29, "days").format("YYYY-MM-DD"));
const to = ref(moment().format("YYYY-MM-DD"));
const today = moment().format("YYYY-MM-DD");

const isLoading = ref(false);
const data = ref({
    top_agents: [],
    top_agents_by_channel: {},
    top_airlines: [],
    channels: [],
    top_searches: { total: 0, routes: [] },
});

// Agents can be ranked overall or within one sales channel (GDS, NDC, LCC…).
const agentChannel = ref("");
const channels = computed(() => data.value.channels || []);
const overallAgents = computed(() => data.value.top_agents || []);
const topAgents = computed(() =>
    agentChannel.value ? data.value.top_agents_by_channel?.[agentChannel.value] || [] : overallAgents.value,
);

// Compact money for the middle of a ring, e.g. AED 1.2M.
const compactAmount = (value) =>
    `${getSelectedCurrencyCode()} ${new Intl.NumberFormat("en", { notation: "compact", maximumFractionDigits: 1 }).format(value)}`;
const topAirlines = computed(() => data.value.top_airlines || []);
const topRoutes = computed(() => data.value.top_searches?.routes || []);
const totalSearches = computed(() => data.value.top_searches?.total || 0);

const bestAgent = computed(() => overallAgents.value[0] || null);
const totalSales = computed(() => overallAgents.value.reduce((sum, row) => sum + Number(row.sales || 0), 0));

// Bar lengths are relative to the leader of each list.
const share = (value, list, key) => {
    const max = Math.max(...list.map((row) => Number(row[key]) || 0), 0);
    return max > 0 ? Math.max(4, Math.round(((Number(value) || 0) / max) * 100)) : 0;
};

async function fetchInsights() {
    isLoading.value = true;
    try {
        const response = await apiService.getAdminInsights({ from: from.value, to: to.value });
        data.value = response.data || data.value;

        // A channel with no bookings in the new period cannot stay selected.
        if (agentChannel.value && !data.value.top_agents_by_channel?.[agentChannel.value]) {
            agentChannel.value = "";
        }
    } catch (error) {
        toast(error.response?.data?.message || "Could not load insights.", { type: "error" });
    } finally {
        isLoading.value = false;
    }
}

function applyPreset(preset) {
    activePreset.value = preset.key;
    to.value = today;
    from.value = moment().subtract(preset.days - 1, "days").format("YYYY-MM-DD");
    fetchInsights();
}

function applyCustomRange() {
    if (!from.value || !to.value) return;
    activePreset.value = "";
    fetchInsights();
}

onMounted(fetchInsights);
</script>

<template>
    <section class="insights-page">
        <header class="page-head">
            <div class="flex items-center gap-3">
                <span class="page-icon"><TrendingUp class="h-6 w-6" /></span>
                <div>
                    <h1 class="page-title">Insights</h1>
                    <p class="page-note">Who books the most, which airlines get booked, and what customers search for.</p>
                </div>
            </div>

            <div class="filters">
                <div class="preset-group" role="group" aria-label="Date range">
                    <button
                        v-for="preset in presets"
                        :key="preset.key"
                        type="button"
                        class="preset"
                        :class="{ 'is-active': activePreset === preset.key }"
                        :aria-pressed="activePreset === preset.key"
                        @click="applyPreset(preset)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
                <div class="range">
                    <label class="sr-only" for="insights-from">From date</label>
                    <input id="insights-from" v-model="from" type="date" :max="to || today" class="date-input" @change="applyCustomRange" />
                    <span class="text-slate-400">to</span>
                    <label class="sr-only" for="insights-to">To date</label>
                    <input id="insights-to" v-model="to" type="date" :min="from" :max="today" class="date-input" @change="applyCustomRange" />
                </div>
            </div>
        </header>

        <!-- Headline figures -->
        <div class="summary">
            <div class="summary-card">
                <span class="summary-label">Best customer</span>
                <span class="summary-value">{{ bestAgent ? bestAgent.agent : "—" }}</span>
                <span class="summary-note">
                    {{ bestAgent ? `${formatAmount(bestAgent.sales)} from ${bestAgent.ticketed} ticketed` : "No bookings in this period" }}
                </span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Ticketed sales (top customers)</span>
                <span class="summary-value">{{ formatAmount(totalSales) }}</span>
                <span class="summary-note">{{ topAgents.length }} customer{{ topAgents.length === 1 ? "" : "s" }} with bookings</span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Most booked airline</span>
                <span class="summary-value">{{ topAirlines[0]?.airline || "—" }}</span>
                <span class="summary-note">
                    {{ topAirlines[0] ? `${topAirlines[0].bookings} booking${topAirlines[0].bookings === 1 ? "" : "s"}` : "No bookings in this period" }}
                </span>
            </div>
            <div class="summary-card">
                <span class="summary-label">Flight searches</span>
                <span class="summary-value">{{ totalSearches }}</span>
                <span class="summary-note">
                    {{ topRoutes[0] ? `Top route ${topRoutes[0].origin} → ${topRoutes[0].destination}` : "No searches in this period" }}
                </span>
            </div>
        </div>

        <!-- The same numbers at a glance -->
        <div class="rings" :class="{ 'is-loading': isLoading }">
            <DonutChart
                title="Bookings by channel"
                unit="bookings"
                :items="channels.map((row) => ({ label: row.channel, value: row.bookings }))"
                empty-text="No bookings"
            />
            <DonutChart
                title="Sales by customer"
                unit="ticketed"
                :format="compactAmount"
                :items="overallAgents.map((row) => ({ label: row.agent, value: row.sales }))"
                empty-text="No ticketed sales"
            />
            <DonutChart
                title="Bookings by airline"
                unit="bookings"
                :items="topAirlines.map((row) => ({ label: row.airline, value: row.bookings }))"
                empty-text="No bookings"
            />
        </div>

        <div class="panels" :class="{ 'is-loading': isLoading }" :aria-busy="isLoading">
            <!-- Top agents -->
            <article class="panel panel-wide">
                <header class="panel-head">
                    <span class="panel-icon"><Award class="h-4 w-4" /></span>
                    <h2 class="panel-title flex-1">Top customers</h2>

                    <div v-if="channels.length" class="preset-group" role="group" aria-label="Sales channel">
                        <button type="button" class="preset" :class="{ 'is-active': agentChannel === '' }" :aria-pressed="agentChannel === ''" @click="agentChannel = ''">
                            Overall
                        </button>
                        <button
                            v-for="row in channels"
                            :key="row.channel"
                            type="button"
                            class="preset"
                            :class="{ 'is-active': agentChannel === row.channel }"
                            :aria-pressed="agentChannel === row.channel"
                            @click="agentChannel = row.channel"
                        >
                            {{ row.channel }}
                        </button>
                    </div>
                </header>

                <div v-if="topAgents.length" class="overflow-x-auto">
                    <table class="rank-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Customer</th>
                                <th class="text-right">Bookings</th>
                                <th class="text-right">Ticketed</th>
                                <th class="text-right">Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in topAgents" :key="row.agent_id">
                                <td><span class="rank" :class="{ 'is-top': index < 3 }">{{ index + 1 }}</span></td>
                                <td>
                                    <span class="row-name">{{ row.agent }}</span>
                                    <span class="bar"><span class="bar-fill" :style="{ width: share(row.sales, topAgents, 'sales') + '%' }"></span></span>
                                </td>
                                <td class="num">{{ row.bookings }}</td>
                                <td class="num">{{ row.ticketed }}</td>
                                <td class="num is-strong">{{ formatAmount(row.sales) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="panel-empty">No customer bookings in this period.</p>
            </article>

            <!-- Airlines by bookings -->
            <article class="panel">
                <header class="panel-head">
                    <span class="panel-icon"><Plane class="h-4 w-4" /></span>
                    <div>
                        <h2 class="panel-title">Most booked airlines</h2>
                        <p class="panel-note">Bookings that weren't cancelled.</p>
                    </div>
                </header>

                <ul v-if="topAirlines.length" class="bar-list">
                    <li v-for="row in topAirlines" :key="row.airline">
                        <div class="bar-row">
                            <span class="row-name">{{ row.airline }}</span>
                            <span class="num is-strong">{{ row.bookings }}</span>
                        </div>
                        <span class="bar"><span class="bar-fill" :style="{ width: share(row.bookings, topAirlines, 'bookings') + '%' }"></span></span>
                        <span class="bar-note">
                            <span v-for="item in row.channels" :key="item.channel" class="channel-tag">{{ item.channel }} {{ item.bookings }}</span>
                            {{ row.ticketed }} ticketed · {{ formatAmount(row.sales) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="panel-empty">No airline bookings in this period.</p>
            </article>

            <!-- Most searched routes -->
            <article class="panel">
                <header class="panel-head">
                    <span class="panel-icon"><Search class="h-4 w-4" /></span>
                    <div>
                        <h2 class="panel-title">Most searched routes</h2>
                        <p class="panel-note">A search records its route, not an airline, so this is ranked by route.</p>
                    </div>
                </header>

                <ul v-if="topRoutes.length" class="bar-list">
                    <li v-for="row in topRoutes" :key="row.origin + row.destination">
                        <div class="bar-row">
                            <span class="row-name">{{ row.origin }} → {{ row.destination }}</span>
                            <span class="num is-strong">{{ row.searches }}</span>
                        </div>
                        <span class="bar"><span class="bar-fill" :style="{ width: share(row.searches, topRoutes, 'searches') + '%' }"></span></span>
                        <span class="bar-note">Searched by {{ row.agents }} user{{ row.agents === 1 ? "" : "s" }}</span>
                    </li>
                </ul>
                <p v-else class="panel-empty">No flight searches in this period.</p>
            </article>
        </div>
    </section>
</template>

<style scoped>
.insights-page {
    min-height: 100vh;
    padding: 1.5rem;
}

.page-head {
    margin-bottom: 1.25rem;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.page-icon,
.panel-icon {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    background-color: hsl(var(--primary) / 0.1);
    color: hsl(var(--primary));
}

.page-icon {
    height: 3rem;
    width: 3rem;
    border-radius: 0.75rem;
}

.panel-icon {
    height: 2rem;
    width: 2rem;
    border-radius: 0.5rem;
}

.page-title {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    color: rgb(15 23 42);
}

.page-note,
.panel-note,
.summary-note,
.bar-note {
    font-size: 0.8125rem;
    color: rgb(100 116 139);
}

.filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
}

.preset-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    border-radius: 0.75rem;
    background-color: rgb(241 245 249);
    padding: 0.25rem;
}

.preset {
    height: 2rem;
    border-radius: 0.5rem;
    padding: 0 0.75rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgb(71 85 105);
    transition: background-color 150ms ease, color 150ms ease;
}

.preset:hover {
    color: hsl(var(--primary));
}

.preset.is-active {
    background-color: #fff;
    font-weight: 600;
    color: hsl(var(--primary));
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.12);
}

.preset:focus-visible,
.date-input:focus-visible {
    outline: 2px solid hsl(var(--primary) / 0.5);
    outline-offset: 2px;
}

.range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8125rem;
}

.date-input {
    height: 2.5rem;
    border: 1px solid rgb(203 213 225);
    border-radius: 0.625rem;
    background-color: #fff;
    padding: 0 0.625rem;
    font-size: 0.8125rem;
    color: rgb(15 23 42);
}

.summary {
    margin-bottom: 1.25rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    gap: 1rem;
}

.summary-card,
.panel {
    border: 1px solid rgb(226 232 240);
    border-radius: 1rem;
    background-color: #fff;
    box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}

.summary-card {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.125rem;
}

.summary-label {
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.summary-value {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 1.375rem;
    font-weight: 700;
    line-height: 1.3;
    font-variant-numeric: tabular-nums;
    color: rgb(15 23 42);
}

.rings {
    margin-bottom: 1.25rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));
    gap: 1rem;
    transition: opacity 150ms ease;
}

.channel-tag {
    margin-right: 0.375rem;
    display: inline-block;
    border-radius: 9999px;
    background-color: hsl(var(--primary) / 0.1);
    padding: 0.0625rem 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    color: hsl(var(--primary));
}

.panels {
    display: grid;
    gap: 1.25rem;
    transition: opacity 150ms ease;
}

.panels.is-loading,
.rings.is-loading {
    opacity: 0.55;
}

@media (min-width: 1024px) {
    .panels {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .panel-wide {
        grid-column: span 2;
    }
}

.panel {
    padding: 1.125rem;
}

.panel-head {
    margin-bottom: 1rem;
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
}

.panel-title {
    font-size: 1rem;
    font-weight: 600;
    color: rgb(15 23 42);
}

.panel-empty {
    padding: 2rem 0;
    text-align: center;
    font-size: 0.875rem;
    color: rgb(100 116 139);
}

.rank-table {
    width: 100%;
    min-width: 34rem;
    font-size: 0.875rem;
    color: rgb(51 65 85);
}

.rank-table th {
    border-bottom: 1px solid rgb(226 232 240);
    padding: 0.5rem 0.75rem;
    text-align: left;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}

.rank-table th.text-right {
    text-align: right;
}

.rank-table td {
    border-bottom: 1px solid rgb(241 245 249);
    padding: 0.75rem;
    vertical-align: middle;
}

.rank-table tbody tr:last-child td {
    border-bottom: 0;
}

.rank {
    display: flex;
    height: 1.625rem;
    width: 1.625rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    background-color: rgb(241 245 249);
    font-size: 0.75rem;
    font-weight: 700;
    color: rgb(71 85 105);
}

.rank.is-top {
    background-color: hsl(var(--primary));
    color: #fff;
}

.row-name {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-weight: 600;
    color: rgb(15 23 42);
}

.num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.num.is-strong {
    font-weight: 700;
    color: rgb(15 23 42);
}

.bar {
    margin-top: 0.375rem;
    display: block;
    height: 0.375rem;
    overflow: hidden;
    border-radius: 9999px;
    background-color: rgb(241 245 249);
}

.bar-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background-color: hsl(var(--primary));
    transition: width 300ms ease;
}

.bar-list {
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.bar-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.75rem;
    font-size: 0.875rem;
}

.bar-note {
    margin-top: 0.25rem;
    display: block;
}

@media (prefers-reduced-motion: reduce) {
    .bar-fill,
    .panels {
        transition: none;
    }
}
</style>
