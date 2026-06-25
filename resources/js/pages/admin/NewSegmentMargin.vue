<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useStore } from "vuex";
import { Check, ChevronsUpDown, ArrowLeft, Loader2, X } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import Button from "@/components/ui/button/Button.vue";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

const router = useRouter();
const store = useStore();

const isSaving = ref(false);

const form = ref({
    title: "",
    sale_channel: "",
    airline_id: null,
    airline_ids: [],
    reservation_type: "ALL-SECTORS",
    margin_type: "markup",
    margin_value: 0,
});

const airlines = computed(() => store.getters["airline/airlines"] || []);
const channels = computed(() => store.getters["segmentMargin/providers"] || []);
const isLoading = computed(() => store.getters["segmentMargin/isLoading"] || store.getters["airline/isLoading"]);

const airlineSearchOpen = ref(false);
const airlineSearch = ref("");
let airlineSearchDebounce = null;
const airlineCache = ref([]);

function syncAirlineCache(list = []) {
    const merged = new Map(airlineCache.value.map((airline) => [Number(airline.id), airline]));
    list.forEach((airline) => {
        if (airline?.id == null) return;
        merged.set(Number(airline.id), airline);
    });
    airlineCache.value = Array.from(merged.values());
}

const selectedAirlines = computed(() => {
    if (!Array.isArray(form.value.airline_ids)) return [];
    const airlineById = new Map(airlineCache.value.map((airline) => [Number(airline.id), airline]));
    return form.value.airline_ids.map((airlineId) => {
        const airline = airlineById.get(Number(airlineId));
        return airline || { id: airlineId, iata_code: `ID:${airlineId}`, name: "" };
    });
});

const airlineButtonText = computed(() => {
    if (!form.value.airline_ids?.length) return "No Airlines";
    if (form.value.airline_ids.length === 1) {
        const airline = selectedAirlines.value[0];
        return airline ? `${airline.name} (${airline.iata_code})` : "1 airline selected";
    }
    return `${form.value.airline_ids.length} airlines selected`;
});

function toggleAirline(airlineId) {
    const ids = form.value.airline_ids || [];
    if (ids.includes(airlineId)) {
        form.value.airline_ids = ids.filter((id) => id !== airlineId);
    } else {
        form.value.airline_ids = [...ids, airlineId];
    }
    form.value.airline_id = form.value.airline_ids[0] ?? null;
}

function clearAirlines() {
    form.value.airline_ids = [];
    form.value.airline_id = null;
}

function removeAirline(airlineId) {
    form.value.airline_ids = (form.value.airline_ids || []).filter((id) => id !== airlineId);
    form.value.airline_id = form.value.airline_ids[0] ?? null;
}

async function fetchData() {
    try {
        await Promise.all([
            store.dispatch("airline/fetchAirlines", { limit: 100 }),
            store.dispatch("segmentMargin/fetchSegmentMarginProviders"),
        ]);
        syncAirlineCache(airlines.value);
    } catch (error) {
        console.error("Error fetching data:", error);
    }
}

async function fetchAirlinesBySearch(query = "") {
    try {
        const trimmed = (query || "").trim();
        if (!trimmed) {
            await store.dispatch("airline/fetchAirlines", { limit: 100 });
            syncAirlineCache(airlines.value);
            return;
        }
        await store.dispatch("airline/fetchAirlines", { search: trimmed });
        syncAirlineCache(airlines.value);
    } catch (error) {
        console.error("Error searching airlines:", error);
    }
}

function handleAirlineSearchInput(event) {
    const value = event?.target?.value ?? "";
    airlineSearch.value = value;

    if (airlineSearchDebounce) clearTimeout(airlineSearchDebounce);
    airlineSearchDebounce = setTimeout(() => {
        fetchAirlinesBySearch(airlineSearch.value);
    }, 300);
}

watch(airlineSearchOpen, (isOpen) => {
    if (!isOpen) return;
    fetchAirlinesBySearch(airlineSearch.value);
});

async function saveMargin() {
    isSaving.value = true;
    try {
        form.value.airline_id = form.value.airline_ids?.[0] ?? null;
        await store.dispatch("segmentMargin/saveSegmentMargin", form.value);
        router.push({ name: "SegmentMargins" });
    } catch (error) {
        console.error("Error saving margin:", error);
    } finally {
        isSaving.value = false;
    }
}

onMounted(() => {
    fetchData();
});
</script>

<template>
    <section class="min-h-screen p-6 bg-gray-50/50 dark:bg-gray-900/50 pb-20">
        <div class="max-w-[1400px] mx-auto">
            <div class="flex items-center gap-4 mb-8">
                <Button variant="outline" size="icon" @click="router.back()">
                    <ArrowLeft class="w-4 h-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">New Segment Margin</h1>
                    <p class="text-gray-500">Configure global segment margin rules</p>
                </div>
            </div>

            <form @submit.prevent="saveMargin" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl border p-6 shadow-sm space-y-6">
                    <div class="space-y-2">
                        <Label class="text-xs uppercase font-bold text-gray-500">Margin Title</Label>
                        <Input v-model="form.title" placeholder="e.g. Summer Special B2B Markup" required class="h-10" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="space-y-2">
                            <Label class="text-xs uppercase font-bold text-gray-500">Sale Channel</Label>
                            <Select v-model="form.sale_channel" required>
                                <SelectTrigger class="h-10"><SelectValue placeholder="Select Channel" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="channel in channels" :key="channel.identifier" :value="channel.identifier">
                                        {{ channel.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2 lg:col-span-1">
                            <Label class="text-xs uppercase font-bold text-gray-500">Select Airlines</Label>
                            <Popover v-model:open="airlineSearchOpen">
                                <PopoverTrigger as-child>
                                    <Button variant="outline" class="w-full h-10 justify-between text-left font-normal">
                                        <span class="truncate">{{ airlineButtonText }}</span>
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[300px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Search airline..."
                                            :value="airlineSearch"
                                            @input="handleAirlineSearchInput"
                                        />
                                        <CommandList>
                                            <CommandEmpty>No airline found.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem value="all-airlines" @select="() => { clearAirlines(); }">
                                                    <Check :class="cn('mr-2 h-4 w-4', !form.airline_ids?.length ? 'opacity-100' : 'opacity-0')" />
                                                    No Airlines
                                                </CommandItem>
                                                <CommandItem v-for="airline in airlines" :key="airline.id" :value="airline.name + ' ' + airline.iata_code" @select="() => { toggleAirline(airline.id); }">
                                                    <Check :class="cn('mr-2 h-4 w-4', form.airline_ids?.includes(airline.id) ? 'opacity-100' : 'opacity-0')" />
                                                    {{ airline.name }} ({{ airline.iata_code }})
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                            <div v-if="selectedAirlines.length" class="flex flex-wrap gap-1 mt-2">
                                <div
                                    v-for="airline in selectedAirlines"
                                    :key="`selected-airline-${airline.id}`"
                                    class="flex items-center gap-1 bg-emerald-50 text-emerald-700 text-[10px] px-2 py-1 rounded-full border border-emerald-200"
                                >
                                    {{ airline.iata_code }} - {{ airline.name }}
                                    <X class="w-3 h-3 cursor-pointer hover:text-red-500" @click="removeAirline(airline.id)" />
                                </div>
                            </div>
                            <p class="text-xs text-amber-600 mt-1 flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                                Note: Margin/discount will be blocked on selected airlines
                            </p>
                        </div>

                        <div class="space-y-2 lg:col-span-2">
                            <Label class="text-xs uppercase font-bold text-gray-500">Margin Amount & Type</Label>
                            <div class="flex gap-2">
                                <div class="relative flex-grow">
                                    <Input type="number" v-model="form.margin_value" class="h-10 pr-12" required step="0.01" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">PKR</span>
                                </div>
                                <Select v-model="form.margin_type" required>
                                    <SelectTrigger class="h-10 w-[160px]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="markup">Markup</SelectItem>
                                        <SelectItem value="discount">Discount</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center gap-4 mt-12">
                    <Button type="button" variant="outline" @click="router.back()" class="w-32 h-10 border-gray-300">Cancel</Button>
                    <Button type="submit" class="w-32 h-10 bg-slate-900" :disabled="isSaving || isLoading">
                        <Loader2 v-if="isSaving" class="w-4 h-4 mr-2 animate-spin" />
                        Save Margin
                    </Button>
                </div>
            </form>
        </div>
    </section>
</template>
