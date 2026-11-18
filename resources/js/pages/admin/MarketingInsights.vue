<script setup>
import { computed, onMounted, ref } from "vue";
import moment from "moment";
import { toast } from "vue3-toastify";
import apiService from "@/services/store/apiService";
import { formatAmount, getSelectedCurrencyCode } from "@/lib/utils";
import DonutChart from "@/components/common/DonutChart.vue";

// Icons
import {
    Award,
    Plane,
    Search,
    TrendingUp,
    Calendar,
    RefreshCw,
    ArrowRight,
    Users,
    CircleDollarSign
} from "lucide-vue-next";

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
    agentChannel.value ? data.value.top_agents_by_channel?.[agentChannel.value] || [] : overallAgents.value
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
    <section class="space-y-6">
        <!-- Top Page Header -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-primary">
                        <TrendingUp class="h-5 w-5" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            Insights & Analytics
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Customer booking velocity, airline distribution, and travel search patterns
                        </p>
                    </div>
                </div>

                <!-- Filters Toolbar -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Presets Group -->
                    <div class="flex items-center gap-1 rounded border border-slate-200 bg-slate-100 p-1" role="group" aria-label="Date range">
                        <button
                            v-for="preset in presets"
                            :key="preset.key"
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="activePreset === preset.key ? 'bg-white text-primary shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            :aria-pressed="activePreset === preset.key"
                            @click="applyPreset(preset)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>

                    <!-- Custom Range -->
                    <div class="flex items-center gap-1.5 text-xs text-slate-600">
                        <label class="sr-only" for="insights-from">From date</label>
                        <input
                            id="insights-from"
                            v-model="from"
                            type="date"
                            :max="to || today"
                            class="rounded border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            @change="applyCustomRange"
                        />
                        <span class="text-slate-400">to</span>
                        <label class="sr-only" for="insights-to">To date</label>
                        <input
                            id="insights-to"
                            v-model="to"
                            type="date"
                            :min="from"
                            :max="today"
                            class="rounded border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            @change="applyCustomRange"
                        />
                    </div>

                    <button
                        @click="fetchInsights"
                        :disabled="isLoading"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                        title="Refresh Insights"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isLoading }" />
                        <span class="hidden sm:inline">Sync</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Headline Figures -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Best Customer -->
            <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Best Customer</span>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900 truncate" :title="bestAgent ? bestAgent.agent : '—'">
                    {{ bestAgent ? bestAgent.agent : "—" }}
                </p>
                <span class="mt-1 block text-[11px] font-medium text-slate-500 truncate">
                    {{ bestAgent ? `${formatAmount(bestAgent.sales)} from ${bestAgent.ticketed} ticketed` : "No bookings in this period" }}
                </span>
            </div>

            <!-- Total Sales -->
            <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ticketed Sales (Top Customers)</span>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    {{ formatAmount(totalSales) }}
                </p>
                <span class="mt-1 block text-[11px] font-medium text-slate-500">
                    {{ topAgents.length }} customer{{ topAgents.length === 1 ? "" : "s" }} with bookings
                </span>
            </div>

            <!-- Most Booked Airline -->
            <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Most Booked Airline</span>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900 truncate" :title="topAirlines[0]?.airline || '—'">
                    {{ topAirlines[0]?.airline || "—" }}
                </p>
                <span class="mt-1 block text-[11px] font-medium text-slate-500">
                    {{ topAirlines[0] ? `${topAirlines[0].bookings} booking${topAirlines[0].bookings === 1 ? "" : "s"}` : "No bookings in this period" }}
                </span>
            </div>

            <!-- Flight Searches -->
            <div class="rounded border border-slate-200 bg-white p-4 shadow-xs">
                <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Flight Searches</span>
                <p class="mt-2 text-xl font-bold tracking-tight text-slate-900">
                    {{ totalSearches }}
                </p>
                <span class="mt-1 block text-[11px] font-medium text-slate-500 truncate">
                    {{ topRoutes[0] ? `Top route ${topRoutes[0].origin} → ${topRoutes[0].destination}` : "No searches in this period" }}
                </span>
            </div>
        </div>

        <!-- Donut Charts Row -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3" :class="{ 'opacity-60': isLoading }">
            <DonutChart
                title="Bookings by Channel"
                unit="bookings"
                :items="channels.map((row) => ({ label: row.channel, value: row.bookings }))"
                empty-text="No channel bookings"
            />
            <DonutChart
                title="Sales by Customer"
                unit="ticketed"
                :format="compactAmount"
                :items="overallAgents.map((row) => ({ label: row.agent, value: row.sales }))"
                empty-text="No ticketed sales"
            />
            <DonutChart
                title="Bookings by Airline"
                unit="bookings"
                :items="topAirlines.map((row) => ({ label: row.airline, value: row.bookings }))"
                empty-text="No airline bookings"
            />
        </div>

        <!-- Detail Panels Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2" :class="{ 'opacity-60': isLoading }">
            <!-- Top Customers Table -->
            <article class="lg:col-span-2 rounded border border-slate-200 bg-white p-5 shadow-xs">
                <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 mb-4">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-primary/10 text-primary">
                            <Award class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Top Customers</h2>
                            <p class="text-[11px] text-slate-500">Ranked by ticketed sales and total bookings</p>
                        </div>
                    </div>

                    <!-- Channel Filter Pills -->
                    <div v-if="channels.length" class="flex flex-wrap items-center gap-1 rounded border border-slate-200 bg-slate-100 p-0.5" role="group" aria-label="Sales channel">
                        <button
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="agentChannel === '' ? 'bg-white text-primary shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            :aria-pressed="agentChannel === ''"
                            @click="agentChannel = ''"
                        >
                            Overall
                        </button>
                        <button
                            v-for="row in channels"
                            :key="row.channel"
                            type="button"
                            class="rounded px-2.5 py-1 text-xs font-semibold transition cursor-pointer"
                            :class="agentChannel === row.channel ? 'bg-white text-primary shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                            :aria-pressed="agentChannel === row.channel"
                            @click="agentChannel = row.channel"
                        >
                            {{ row.channel }}
                        </button>
                    </div>
                </header>

                <div v-if="topAgents.length" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                            <tr>
                                <th scope="col" class="w-12 px-4 py-3">#</th>
                                <th scope="col" class="px-4 py-3">Customer</th>
                                <th scope="col" class="px-4 py-3 text-right">Bookings</th>
                                <th scope="col" class="px-4 py-3 text-right">Ticketed</th>
                                <th scope="col" class="px-4 py-3 text-right">Total Sales</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(row, index) in topAgents" :key="row.agent_id" class="transition-colors hover:bg-slate-50/80">
                                <!-- Rank -->
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded text-xs font-bold"
                                        :class="index < 3 ? 'bg-primary text-white shadow-2xs' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                                    >
                                        {{ index + 1 }}
                                    </span>
                                </td>

                                <!-- Customer & Progress Bar -->
                                <td class="px-4 py-3 min-w-[200px]">
                                    <span class="block font-bold text-slate-900 truncate" :title="row.agent">{{ row.agent }}</span>
                                    <div class="mt-1.5 h-1.5 w-full rounded bg-slate-100 overflow-hidden">
                                        <div
                                            class="h-full rounded bg-primary transition-all duration-300"
                                            :style="{ width: share(row.sales, topAgents, 'sales') + '%' }"
                                        />
                                    </div>
                                </td>

                                <!-- Bookings -->
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-700 tabular-nums">
                                    {{ row.bookings }}
                                </td>

                                <!-- Ticketed -->
                                <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-emerald-700 tabular-nums">
                                    {{ row.ticketed }}
                                </td>

                                <!-- Sales -->
                                <td class="whitespace-nowrap px-4 py-3 text-right font-bold text-slate-900 tabular-nums">
                                    {{ formatAmount(row.sales) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="py-12 text-center text-xs text-slate-500">
                    No customer bookings in this period.
                </div>
            </article>

            <!-- Most Booked Airlines -->
            <article class="rounded border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <header class="flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-primary/10 text-primary">
                            <Plane class="h-4 w-4 -rotate-45" />
                        </span>
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Most Booked Airlines</h2>
                            <p class="text-[11px] text-slate-500">Active confirmed and ticketed bookings</p>
                        </div>
                    </header>

                    <ul v-if="topAirlines.length" class="space-y-4">
                        <li v-for="row in topAirlines" :key="row.airline" class="text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-slate-900 truncate" :title="row.airline">{{ row.airline }}</span>
                                <span class="font-bold text-slate-900 tabular-nums">{{ row.bookings }} bookings</span>
                            </div>

                            <div class="mt-1.5 h-1.5 w-full rounded bg-slate-100 overflow-hidden">
                                <div
                                    class="h-full rounded bg-primary transition-all duration-300"
                                    :style="{ width: share(row.bookings, topAirlines, 'bookings') + '%' }"
                                />
                            </div>

                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500">
                                <span
                                    v-for="item in row.channels"
                                    :key="item.channel"
                                    class="rounded border border-primary/20 bg-primary/10 px-1.5 py-0.2 font-semibold text-primary uppercase text-[10px]"
                                >
                                    {{ item.channel }} {{ item.bookings }}
                                </span>
                                <span>{{ row.ticketed }} ticketed · {{ formatAmount(row.sales) }}</span>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="py-12 text-center text-xs text-slate-500">
                        No airline bookings in this period.
                    </div>
                </div>
            </article>

            <!-- Most Searched Routes -->
            <article class="rounded border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div>
                    <header class="flex items-center gap-2 border-b border-slate-100 pb-3 mb-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded bg-primary/10 text-primary">
                            <Search class="h-4 w-4" />
                        </span>
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Most Searched Routes</h2>
                            <p class="text-[11px] text-slate-500">High-intent customer flight search origins & destinations</p>
                        </div>
                    </header>

                    <ul v-if="topRoutes.length" class="space-y-4">
                        <li v-for="row in topRoutes" :key="row.origin + row.destination" class="text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                        {{ row.origin }}
                                    </span>
                                    <ArrowRight class="h-3 w-3 text-slate-400" />
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                        {{ row.destination }}
                                    </span>
                                </div>
                                <span class="font-bold text-slate-900 tabular-nums">{{ row.searches }} searches</span>
                            </div>

                            <div class="mt-1.5 h-1.5 w-full rounded bg-slate-100 overflow-hidden">
                                <div
                                    class="h-full rounded bg-primary transition-all duration-300"
                                    :style="{ width: share(row.searches, topRoutes, 'searches') + '%' }"
                                />
                            </div>

                            <p class="mt-1 text-[11px] text-slate-500">
                                Searched by {{ row.agents }} user{{ row.agents === 1 ? '' : 's' }}
                            </p>
                        </li>
                    </ul>

                    <div v-else class="py-12 text-center text-xs text-slate-500">
                        No flight searches in this period.
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
