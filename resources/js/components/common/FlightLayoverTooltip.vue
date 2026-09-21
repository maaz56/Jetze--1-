<script setup>
import moment from "moment";
import { CircleAlert, Clock3, PlaneTakeoff } from "lucide-vue-next";
import { computed } from "vue";

const props = defineProps({
    flight: {
        type: Object,
        required: true,
    },
});

const segments = computed(() =>
    Array.isArray(props.flight?.segments) ? props.flight.segments : [],
);

const airportName = (airport) =>
    airport?.name ||
    airport?.airport?.name ||
    airport?.city?.name ||
    airport?.iata ||
    airport?.city?.code ||
    "Airport details unavailable";

const airportCode = (airport) =>
    airport?.iata || airport?.city?.code || airport?.code || "";

const terminal = (value) => {
    if (!value) return "";
    if (typeof value === "string" || typeof value === "number") {
        return String(value).trim();
    }

    return String(
        value?.Gate || value?.gate || value?.terminal || value?.name || value?.code || "",
    ).trim();
};

const formatTime = (value) => {
    if (!value) return "—";
    const parsed = moment.parseZone(value);
    return parsed.isValid() ? parsed.format("HH:mm") : "—";
};

const formatFlightNumber = (number) => {
    if (Array.isArray(number)) return number.filter(Boolean).join(" / ");
    return number ? String(number) : "";
};

const formatDuration = (value) => {
    if (value === null || value === undefined || value === "") return "";
    if (typeof value === "string" && /[a-zA-Z]/.test(value)) return value;

    const minutes = Number(value);
    if (!Number.isFinite(minutes)) return String(value);

    const hours = Math.floor(Math.max(0, minutes) / 60);
    const mins = Math.max(0, minutes) % 60;
    return `${hours}h${mins ? ` ${mins}m` : ""}`;
};

const segmentDuration = (segment) =>
    formatDuration(
        segment?.flight_time ?? segment?.flightTime ?? segment?.travel_time,
    );

const layoverMinutes = (segment, nextSegment) => {
    const explicit = Number(segment?.layover_time);
    if (Number.isFinite(explicit) && explicit > 0) return explicit;

    if (!segment?.arrival_at || !nextSegment?.departure_at) return 0;
    const arrival = moment.parseZone(segment.arrival_at);
    const nextDeparture = moment.parseZone(nextSegment.departure_at);
    const difference = nextDeparture.diff(arrival, "minutes");
    return difference > 0 ? difference : 0;
};

const formatLayover = (minutes) => {
    const total = Math.max(0, Number(minutes) || 0);
    const hours = Math.floor(total / 60);
    const mins = total % 60;
    return `${hours}h ${String(mins).padStart(2, "0")}m`;
};

const hasTerminalChange = (segment, nextSegment) => {
    const arrivalTerminal = terminal(segment?.to_terminal);
    const departureTerminal = terminal(nextSegment?.from_terminal);

    return (
        arrivalTerminal &&
        departureTerminal &&
        arrivalTerminal.toLowerCase() !== departureTerminal.toLowerCase()
    );
};

const carrierName = (segment) =>
    segment?.operating_carrier?.name ||
    segment?.marketing_carrier?.name ||
    props.flight?.operating_carrier?.name ||
    props.flight?.marketing_carrier?.name ||
    "Airline";

const carrierFlightNumber = (segment) => {
    const carrierCode =
        segment?.operating_carrier?.iata ||
        segment?.marketing_carrier?.iata ||
        props.flight?.operating_carrier?.iata ||
        props.flight?.marketing_carrier?.iata ||
        "";
    const number = formatFlightNumber(segment?.flight_number);
    return [carrierCode, number].filter(Boolean).join(" ");
};
</script>

<template>
    <div class="w-[340px] max-w-[calc(100vw-2rem)] bg-white text-slate-700">
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-bold text-slate-900">Flight itinerary</p>
            <p class="mt-0.5 text-[11px] font-medium text-slate-500">
                {{ segments.length }} segment{{ segments.length === 1 ? "" : "s" }}
            </p>
        </div>

        <div v-if="segments.length" class="max-h-[360px] overflow-y-auto px-4 py-3">
            <div class="relative">
                <span class="absolute bottom-5 left-[53px] top-5 w-px bg-slate-200"></span>

                <template v-for="(segment, index) in segments" :key="segment?.ref_id || index">
                    <div class="relative grid grid-cols-[40px_18px_minmax(0,1fr)] gap-x-2 pb-3">
                        <time class="pt-0.5 text-xs font-bold tabular-nums text-slate-900">
                            {{ formatTime(segment?.departure_at) }}
                        </time>
                        <span class="relative z-10 mt-1.5 h-2.5 w-2.5 rounded-full border-2 border-primary bg-white"></span>
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-bold leading-tight text-slate-900">
                                {{ airportCode(segment?.from) }}
                                {{ airportName(segment?.from) }}
                                <span v-if="terminal(segment?.from_terminal)" class="text-primary">
                                    {{ terminal(segment?.from_terminal) }}
                                </span>
                            </p>
                            <p class="mt-1 text-xs font-medium text-slate-600">
                                {{ carrierName(segment) }}
                                <span v-if="carrierFlightNumber(segment)">
                                    {{ carrierFlightNumber(segment) }}
                                </span>
                            </p>
                            <p v-if="segmentDuration(segment)" class="mt-1 flex items-center gap-1 text-[11px] font-medium text-slate-500">
                                <PlaneTakeoff class="h-3 w-3 text-primary" />
                                Flight time: {{ segmentDuration(segment) }}
                            </p>
                        </div>
                    </div>

                    <div class="relative grid grid-cols-[40px_18px_minmax(0,1fr)] gap-x-2" :class="index < segments.length - 1 ? 'pb-3' : ''">
                        <time class="pt-0.5 text-xs font-bold tabular-nums text-slate-900">
                            {{ formatTime(segment?.arrival_at) }}
                        </time>
                        <span class="relative z-10 mt-1.5 flex h-2.5 w-2.5 items-center justify-center rounded-full bg-primary ring-2 ring-white"></span>
                        <div class="min-w-0">
                            <p class="truncate text-[14px] font-bold leading-tight text-slate-900">
                                {{ airportCode(segment?.to) }}
                                {{ airportName(segment?.to) }}
                                <span v-if="terminal(segment?.to_terminal)" class="text-primary">
                                    {{ terminal(segment?.to_terminal) }}
                                </span>
                            </p>

                            <div
                                v-if="segments[index + 1] && layoverMinutes(segment, segments[index + 1])"
                                class="mt-2 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5"
                            >
                                <p class="flex items-center gap-1 text-[11px] font-semibold text-slate-600">
                                    <Clock3 class="h-3 w-3 text-primary" />
                                    Layover: {{ formatLayover(layoverMinutes(segment, segments[index + 1])) }}
                                </p>
                                <p
                                    v-if="hasTerminalChange(segment, segments[index + 1])"
                                    class="mt-1 flex items-center gap-1 text-[11px] font-semibold text-amber-700"
                                >
                                    <CircleAlert class="h-3 w-3" />
                                    Different terminal: {{ terminal(segment?.to_terminal) }} → {{ terminal(segments[index + 1]?.from_terminal) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div v-else class="px-4 py-3 text-xs font-medium text-slate-500">
            Detailed segment information is not available for this flight.
        </div>
    </div>
</template>
