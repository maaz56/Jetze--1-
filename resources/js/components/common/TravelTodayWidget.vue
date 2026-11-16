<script setup>
import apiService from "@/services/store/apiService";
import { ArrowRight, CalendarDays } from "lucide-vue-next";
import moment from "moment";
import { computed, onMounted, ref } from "vue";

const result = ref({ bookings: [], total_travellers: 0 });
const loaded = ref(false);
const names = computed(() => (result.value.bookings || [])
    .flatMap((booking) => (booking.passengers || []).map((passenger) => `${passenger.name} (${booking.sector || "—"})`))
    .slice(0, 3));
const extra = computed(() => Math.max(0, Number(result.value.total_travellers || 0) - names.value.length));

onMounted(async () => {
    try {
        const { data } = await apiService.getTravellersByDate({ date: moment().format("YYYY-MM-DD") });
        result.value = data || result.value;
    } finally {
        loaded.value = true;
    }
});
</script>

<template>
    <router-link :to="{ name: 'TravelCalendar' }" class="mb-6 flex items-center gap-3 rounded-xl border bg-white p-4 shadow-sm transition hover:border-primary/40 hover:shadow-md">
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary"><CalendarDays class="h-5 w-5" /></span>
        <strong class="text-2xl tabular-nums text-slate-900">{{ loaded ? result.total_travellers : "…" }}</strong>
        <span class="min-w-0 flex-1"><span class="block font-semibold text-slate-900">Travelling today</span><span class="block truncate text-sm text-slate-500"><template v-if="names.length">{{ names.join(" · ") }}<template v-if="extra"> · +{{ extra }} more</template></template><template v-else-if="loaded">No one is travelling today</template></span></span>
        <span class="flex items-center gap-1 text-sm font-semibold text-primary">Calendar <ArrowRight class="h-4 w-4" /></span>
    </router-link>
</template>
