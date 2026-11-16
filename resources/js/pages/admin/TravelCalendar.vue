<script setup>
import apiService from "@/services/store/apiService";
import { CalendarDays, ChevronLeft, ChevronRight, Search, Users } from "lucide-vue-next";
import moment from "moment";
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";

const router = useRouter();
const today = moment().format("YYYY-MM-DD");
const selectedDate = ref(today);
const month = ref(moment().format("YYYY-MM"));
const search = ref("");
const loading = ref(false);
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

const monthTotal = computed(() => Object.values(calendar.value.days || {})
    .reduce((total, day) => total + Number(day.travellers || 0), 0));
const heading = computed(() => selectedDate.value === today ? "Travelling today" : moment(selectedDate.value).format("dddd, D MMMM YYYY"));
const travellers = computed(() => (result.value.bookings || []).flatMap((booking) =>
    (booking.passengers?.length ? booking.passengers : [{ name: "Passenger details missing" }])
        .map((passenger, index) => ({ ...passenger, booking, key: `${booking.booking_id}-${index}` }))));
const filteredTravellers = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return travellers.value;
    return travellers.value.filter(({ booking, ...passenger }) =>
        [passenger.name, passenger.ticket_no, booking.pnr, booking.airline_pnr, booking.sector, booking.agent]
            .filter(Boolean).some((value) => String(value).toLowerCase().includes(term)));
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

function selectDate(date) { selectedDate.value = date; loadTravellers(); }
function shiftMonth(step) { month.value = moment(`${month.value}-01`).add(step, "months").format("YYYY-MM"); loadCalendar(); }
function goToday() { selectedDate.value = today; month.value = moment().format("YYYY-MM"); loadCalendar(); loadTravellers(); }
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
function departureTime(value) { const parsed = moment(value); return value && parsed.isValid() && String(value).length > 10 ? parsed.format("h:mm A") : "—"; }

onMounted(() => { loadCalendar(); loadTravellers(); });
</script>

<template>
    <main class="min-h-screen bg-slate-50 p-4 sm:p-6">
        <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10 text-primary"><CalendarDays class="h-6 w-6" /></span>
                <div><h1 class="text-xl font-bold text-slate-900">Travel Calendar</h1><p class="text-sm text-slate-500">Passenger travel manifest by departure date</p></div>
            </div>
            <button v-if="selectedDate !== today" type="button" class="rounded-lg border bg-white px-3 py-2 text-sm font-semibold text-primary" @click="goToday">Today</button>
        </header>

        <div class="grid gap-5 xl:grid-cols-[21rem_minmax(0,1fr)]">
            <section class="h-fit rounded-2xl border bg-white p-4 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <button type="button" class="calendar-button" aria-label="Previous month" @click="shiftMonth(-1)"><ChevronLeft class="h-5 w-5" /></button>
                    <div class="text-center"><p class="font-semibold text-slate-900">{{ moment(`${month}-01`).format("MMMM YYYY") }}</p><p class="text-xs text-slate-500">{{ monthTotal }} travellers this month</p></div>
                    <button type="button" class="calendar-button" aria-label="Next month" @click="shiftMonth(1)"><ChevronRight class="h-5 w-5" /></button>
                </div>
                <div class="calendar-grid mb-1"><span v-for="day in weekdays" :key="day" class="text-center text-xs font-semibold uppercase text-slate-400">{{ day }}</span></div>
                <div class="calendar-grid">
                    <template v-for="(cell, index) in cells" :key="cell?.key || `blank-${index}`">
                        <span v-if="!cell" />
                        <button v-else type="button" class="calendar-day" :class="{ selected: cell.key === selectedDate, today: cell.key === today, active: cell.count }" @click="selectDate(cell.key)">
                            <span>{{ cell.day }}</span><span v-if="cell.count" class="count">{{ cell.count }}</span>
                        </button>
                    </template>
                </div>
            </section>

            <section>
                <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div><h2 class="text-lg font-bold text-slate-900">{{ heading }}</h2><p class="text-sm text-slate-500">{{ result.total_travellers }} travellers · {{ result.total_bookings }} bookings</p></div>
                    <label class="flex items-center gap-2 rounded-lg border bg-white px-3 py-2 text-sm"><Search class="h-4 w-4 text-slate-400" /><input v-model="search" class="w-52 outline-none" type="search" placeholder="Search passenger, PNR…" /></label>
                </div>
                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm" :class="{ 'opacity-60': loading }">
                    <div v-if="filteredTravellers.length" class="overflow-x-auto"><table class="w-full min-w-[48rem] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th>Passenger</th><th>Route</th><th>Departure</th><th>PNR</th><th>Ticket</th><th>Agent</th><th>Status</th><th></th></tr></thead><tbody><tr v-for="row in filteredTravellers" :key="row.key" class="border-t"><td><p class="font-semibold text-slate-900">{{ row.name || "—" }}</p><p class="text-xs text-slate-500">{{ row.type }}</p></td><td>{{ row.booking.sector || "—" }}</td><td>{{ departureTime(row.booking.departure_at) }}</td><td>{{ row.booking.pnr || "—" }}</td><td>{{ row.ticket_no || "—" }}</td><td>{{ row.booking.agent || "—" }}</td><td class="capitalize">{{ row.booking.status || "—" }}</td><td><button type="button" class="font-semibold text-primary" @click="openBooking(row.booking)">Open</button></td></tr></tbody></table></div>
                    <div v-else class="p-12 text-center text-slate-500"><Users class="mx-auto mb-3 h-8 w-8" /><p class="font-semibold">{{ loading ? "Loading…" : search ? "No matching travellers" : "No one travelling" }}</p></div>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
.calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .25rem; }
.calendar-button { display: grid; height: 2.25rem; width: 2.25rem; place-items: center; border-radius: .6rem; color: hsl(var(--primary)); } .calendar-button:hover { background: rgb(241 245 249); }
.calendar-day { display: flex; aspect-ratio: 1; flex-direction: column; align-items: center; justify-content: center; gap: .1rem; border: 1px solid transparent; border-radius: .6rem; color: rgb(71 85 105); } .calendar-day:hover { background: rgb(241 245 249); } .calendar-day.active { background: hsl(var(--primary) / .08); font-weight: 600; } .calendar-day.today { border-color: hsl(var(--primary) / .5); } .calendar-day.selected { border-color: hsl(var(--primary)); background: hsl(var(--primary)); color: white; } .count { min-width: 1.1rem; border-radius: 999px; background: hsl(var(--primary)); padding: 0 .25rem; font-size: .65rem; font-weight: 700; color: white; } .selected .count { background: white; color: hsl(var(--primary)); }
th, td { padding: .8rem 1rem; }
</style>
