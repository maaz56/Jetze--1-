<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import apiService from "@/services/store/apiService";
import moment from "moment";
import { toast } from "vue3-toastify";

// Icons
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Search,
    Users,
    ArrowRight,
    Copy,
    Check,
    RefreshCw,
    Clock,
    Plane
} from "lucide-vue-next";

const router = useRouter();
const today = moment().format("YYYY-MM-DD");
const selectedDate = ref(today);
const month = ref(moment().format("YYYY-MM"));
const search = ref("");
const loading = ref(false);
const isRefreshing = ref(false);
const copiedPnr = ref("");
const calendar = ref({ days: {} });
const result = ref({ bookings: [], total_bookings: 0, total_travellers: 0 });
const weekdays = ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"];

const cells = computed(() => {
    const first = moment(`${month.value}-01`);
    const days = Array(first.isoWeekday() - 1).fill(null);
    for (let day = 1; day <= first.daysInMonth(); day += 1) {
        const key = first.clone().date(day).format("YYYY-MM-DD");
        days.push({ key, day, count: calendar.value.days?.[key]?.travellers || 0 });
    }
    return days;
});

const monthTotal = computed(() =>
    Object.values(calendar.value.days || {}).reduce((total, day) => total + Number(day.travellers || 0), 0)
);

const heading = computed(() =>
    selectedDate.value === today ? "Travelling Today" : moment(selectedDate.value).format("dddd, D MMMM YYYY")
);

const travellers = computed(() =>
    (result.value.bookings || []).flatMap((booking) =>
        (booking.passengers?.length ? booking.passengers : [{ name: "Passenger details missing" }]).map(
            (passenger, index) => ({ ...passenger, booking, key: `${booking.booking_id}-${index}` })
        )
    )
);

const filteredTravellers = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return travellers.value;
    return travellers.value.filter(({ booking, ...passenger }) =>
        [passenger.name, passenger.ticket_no, booking.pnr, booking.airline_pnr, booking.sector, booking.agent]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term))
    );
});

async function loadCalendar() {
    try {
        const { data } = await apiService.getTravellerCalendar({ month: month.value });
        calendar.value = { days: data?.days || {} };
    } catch (error) {
        toast(error.response?.data?.message || "Could not load travel calendar.", { type: "error" });
    }
}

async function loadTravellers() {
    loading.value = true;
    try {
        const { data } = await apiService.getTravellersByDate({ date: selectedDate.value });
        result.value = data || result.value;
    } catch (error) {
        toast(error.response?.data?.message || "Could not load travellers.", { type: "error" });
    } finally {
        loading.value = false;
    }
}

function selectDate(date) {
    selectedDate.value = date;
    loadTravellers();
}

function shiftMonth(step) {
    month.value = moment(`${month.value}-01`).add(step, "months").format("YYYY-MM");
    loadCalendar();
}

function goToday() {
    selectedDate.value = today;
    month.value = moment().format("YYYY-MM");
    loadCalendar();
    loadTravellers();
}

async function refreshAll() {
    isRefreshing.value = true;
    try {
        await Promise.allSettled([loadCalendar(), loadTravellers()]);
    } finally {
        setTimeout(() => {
            isRefreshing.value = false;
        }, 400);
    }
}

function copyPnrToClipboard(pnr) {
    if (!pnr) return;
    if (navigator?.clipboard?.writeText) {
        navigator.clipboard.writeText(pnr);
        copiedPnr.value = pnr;
        setTimeout(() => {
            if (copiedPnr.value === pnr) copiedPnr.value = "";
        }, 2000);
    }
}

function openBooking(booking) {
    router.push({
        name: "AdminCustomerBookingsLayout",
        query: {
            booking_id: booking.booking_id,
            pnr: booking.pnr,
            agent_id: booking.agent_id,
            booking_source: booking.booking_source,
            flight_provider: booking.flight_provider,
            flight_mode: "B2C",
        },
    });
}

function departureTime(value) {
    const parsed = moment(value);
    return value && parsed.isValid() && String(value).length > 10 ? parsed.format("h:mm A") : "—";
}

onMounted(() => {
    loadCalendar();
    loadTravellers();
});
</script>

<template>
    <main class="space-y-6">
        <!-- Top Page Header -->
        <div class="rounded border border-slate-200 bg-white p-5 shadow-xs">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded bg-primary/10 text-primary">
                        <CalendarDays class="h-5 w-5" />
                    </span>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            Travel Calendar
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Passenger departure schedules, live manifests, and flight distribution
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        @click="refreshAll"
                        :disabled="isRefreshing"
                        class="inline-flex items-center gap-1.5 rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 cursor-pointer disabled:opacity-50"
                        title="Refresh Calendar"
                    >
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isRefreshing }" />
                        <span class="hidden sm:inline">Sync</span>
                    </button>

                    <button
                        v-if="selectedDate !== today"
                        type="button"
                        class="rounded border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-primary hover:bg-slate-50 transition cursor-pointer shadow-2xs"
                        @click="goToday"
                    >
                        Today
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Layout: Calendar Widget & Passenger Manifest -->
        <div class="grid gap-6 xl:grid-cols-[21rem_minmax(0,1fr)]">
            <!-- Calendar Card -->
            <section class="h-fit rounded border border-slate-200 bg-white p-5 shadow-xs">
                <!-- Month Navigator -->
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                        aria-label="Previous month"
                        @click="shiftMonth(-1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>

                    <div class="text-center">
                        <p class="text-sm font-bold text-slate-900">
                            {{ moment(`${month}-01`).format("MMMM YYYY") }}
                        </p>
                        <p class="text-[11px] font-medium text-slate-500">
                            {{ monthTotal }} total travellers
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-8 w-8 items-center justify-center rounded text-slate-600 hover:bg-slate-100 transition cursor-pointer"
                        aria-label="Next month"
                        @click="shiftMonth(1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- Weekday Headers -->
                <div class="grid grid-cols-7 gap-1 mb-2 text-center">
                    <span v-for="day in weekdays" :key="day" class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                        {{ day }}
                    </span>
                </div>

                <!-- Days Grid -->
                <div class="grid grid-cols-7 gap-1">
                    <template v-for="(cell, index) in cells" :key="cell?.key || `blank-${index}`">
                        <span v-if="!cell" class="aspect-square" />
                        <button
                            v-else
                            type="button"
                            class="relative aspect-square flex flex-col items-center justify-center rounded border text-xs transition cursor-pointer"
                            :class="{
                                'bg-primary text-white border-primary shadow-2xs font-bold': cell.key === selectedDate,
                                'border-primary/50 text-slate-900 font-bold': cell.key === today && cell.key !== selectedDate,
                                'bg-primary/5 text-primary font-semibold border-transparent hover:bg-slate-100': cell.count && cell.key !== selectedDate,
                                'border-transparent text-slate-700 hover:bg-slate-100': !cell.count && cell.key !== selectedDate && cell.key !== today,
                            }"
                            @click="selectDate(cell.key)"
                        >
                            <span>{{ cell.day }}</span>
                            <span
                                v-if="cell.count"
                                class="mt-0.5 rounded-sm px-1 text-[9px] font-bold tabular-nums"
                                :class="cell.key === selectedDate ? 'bg-white text-primary' : 'bg-primary text-white'"
                            >
                                {{ cell.count }}
                            </span>
                        </button>
                    </template>
                </div>
            </section>

            <!-- Manifest Section -->
            <section class="space-y-4">
                <!-- Toolbar / Subheader -->
                <div class="flex flex-col gap-3 rounded border border-slate-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 sm:text-base">
                            {{ heading }}
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ result.total_travellers }} traveller{{ result.total_travellers === 1 ? '' : 's' }} across {{ result.total_bookings }} booking{{ result.total_bookings === 1 ? '' : 's' }}
                        </p>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search passenger, PNR, sector…"
                            class="w-full rounded border border-slate-200 bg-white py-1.5 pl-8 pr-3 text-xs text-slate-900 placeholder:text-slate-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                        />
                    </div>
                </div>

                <!-- Table Container -->
                <div class="rounded border border-slate-200 bg-white shadow-xs overflow-hidden" :class="{ 'opacity-60': loading }">
                    <div v-if="filteredTravellers.length" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wider text-slate-600">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Passenger</th>
                                    <th scope="col" class="px-4 py-3">Route / Sector</th>
                                    <th scope="col" class="px-4 py-3">Departure</th>
                                    <th scope="col" class="px-4 py-3">PNR</th>
                                    <th scope="col" class="px-4 py-3">Ticket No</th>
                                    <th scope="col" class="px-4 py-3">Agent</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                <tr v-for="row in filteredTravellers" :key="row.key" class="transition-colors hover:bg-slate-50/80">
                                    <!-- Passenger -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <p class="font-bold text-slate-900">{{ row.name || "—" }}</p>
                                        <p class="text-[11px] text-slate-500 uppercase">{{ row.type || "Adult" }}</p>
                                    </td>

                                    <!-- Route / Sector -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-800 border border-slate-200">
                                            {{ row.booking?.sector || "—" }}
                                        </span>
                                    </td>

                                    <!-- Departure -->
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                        {{ departureTime(row.booking?.departure_at) }}
                                    </td>

                                    <!-- PNR with Copy Action -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="rounded border border-primary/30 bg-primary/5 px-2 py-0.5 font-mono text-xs font-bold text-primary">
                                                {{ row.booking?.pnr || "—" }}
                                            </span>
                                            <button
                                                v-if="row.booking?.pnr"
                                                @click="copyPnrToClipboard(row.booking.pnr)"
                                                class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 cursor-pointer"
                                                title="Copy PNR"
                                            >
                                                <Check v-if="copiedPnr === row.booking.pnr" class="h-3 w-3 text-emerald-600" />
                                                <Copy v-else class="h-3 w-3" />
                                            </button>
                                        </div>
                                    </td>

                                    <!-- Ticket No -->
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600 font-mono text-[11px]">
                                        {{ row.ticket_no || "—" }}
                                    </td>

                                    <!-- Agent -->
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700">
                                        {{ row.booking?.agent || "—" }}
                                    </td>

                                    <!-- Status -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold uppercase tracking-wider"
                                            :class="{
                                                'bg-emerald-50 text-emerald-700 border border-emerald-200': row.booking?.status === 'ticketed',
                                                'bg-amber-50 text-amber-700 border border-amber-200': row.booking?.status === 'booked',
                                                'bg-rose-50 text-rose-700 border border-rose-200': row.booking?.status === 'canceled',
                                                'bg-slate-100 text-slate-700 border border-slate-200': !['ticketed', 'booked', 'canceled'].includes(row.booking?.status),
                                            }"
                                        >
                                            {{ row.booking?.status || "—" }}
                                        </span>
                                    </td>

                                    <!-- Action -->
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="rounded border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:border-primary hover:text-primary transition cursor-pointer shadow-2xs"
                                            @click="openBooking(row.booking)"
                                        >
                                            View Booking
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="p-16 text-center text-xs text-slate-500">
                        <Users class="mx-auto h-8 w-8 text-slate-300 mb-2" />
                        <p class="text-sm font-semibold text-slate-800">
                            {{ loading ? "Loading passengers manifest…" : search ? "No matching travellers found" : "No passengers scheduled for travel" }}
                        </p>
                        <p class="text-slate-400 mt-1">
                            {{ search ? "Try adjusting your search criteria." : "Select another date from the calendar to inspect passengers." }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
