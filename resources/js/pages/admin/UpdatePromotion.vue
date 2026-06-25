<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useStore } from "vuex";
import { Check, ChevronsUpDown, ArrowLeft, Loader2 } from "lucide-vue-next";
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
const route = useRoute();
const store = useStore();

const isSaving = ref(false);

const form = ref({
    title: "",
    sale_channel: "",
    airline_id: null,
    reservation_type: "",
    price_option: "markup",
    commission_type: "amount",
    commission_value: 0,
    travel_start_date: "",
    travel_end_date: "",
    ticketing_start_date: "",
    ticketing_end_date: "",
});

const airlines = computed(() => store.getters["airline/airlines"] || []);
const channels = computed(() => store.getters["promotion/promotionProviders"] || []);
const isLoading = computed(() => store.getters["promotion/isLoading"]);

const reservationTypes = [
    "ALL-SECTORS",
    "INTERLINE",
    "EX-PAKISTAN",
    "SOTO",
    "DOMESTIC",
    "CODE-SHARE"
];

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

const selectedAirlineName = computed(() => {
    if (!form.value.airline_id) {
        return "All Airlines";
    }
    const airline = airlineCache.value.find(a => Number(a.id) === Number(form.value.airline_id));
    return airline ? `${airline.name} (${airline.iata_code})` : "All Airlines";
});

async function fetchData() {
    try {
        await Promise.all([
            store.dispatch("airline/fetchAirlines", { limit: 100 }),
            store.dispatch("promotion/fetchPromotionProviders"),
            store.dispatch("promotion/fetchPromotion", route.params.id)
        ]);
        syncAirlineCache(airlines.value);
        
        const promo = store.getters["promotion/promotion"];
        if (promo) {
            if (promo.airline) {
                syncAirlineCache([promo.airline]);
            }
            form.value = {
                title: promo.title,
                sale_channel: promo.sale_channel,
                airline_id: promo.airline_id,
                reservation_type: promo.reservation_type,
                price_option: promo.price_option || "markup",
                commission_type: promo.commission_type,
                commission_value: promo.commission_value,
                travel_start_date: promo.travel_start_date,
                travel_end_date: promo.travel_end_date,
                ticketing_start_date: promo.ticketing_start_date,
                ticketing_end_date: promo.ticketing_end_date,
            };
        }
    } catch (error) {
        console.error("Error fetching form data:", error);
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

async function updatePromotion() {
    isSaving.value = true;
    try {
        await store.dispatch("promotion/updatePromotion", {
            id: route.params.id,
            data: { ...form.value },
        });
        router.push({ name: "Promotions" });
    } catch (error) {
        console.error("Error updating promotion:", error);
    } finally {
        isSaving.value = false;
    }
}

onMounted(() => {
    fetchData();
});
</script>


<template>
    <section class="min-h-screen p-6 bg-gray-50/50 dark:bg-gray-900/50">
        <div class="max-w-5xl mx-auto" v-if="!isLoading">
            <div class="flex items-center gap-4 mb-8">
                <Button variant="outline" size="icon" @click="router.back()">
                    <ArrowLeft class="w-4 h-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Update Promotion</h1>
                    <p class="text-gray-500">Edit promotion rule #{{ route.params.id }}</p>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-2xl border shadow-sm p-8">
                <form @submit.prevent="updatePromotion" class="space-y-8">
                    <!-- Title Section -->
                    <div class="space-y-4">
                        <Label for="title" class="text-base font-semibold">Promotion Title</Label>
                        <Input id="title" v-model="form.title" placeholder="e.g. Summer Special 2024" required class="h-12 text-lg" />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Sale Channel -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Sale Channel</Label>
                            <Select v-model="form.sale_channel" required>
                                <SelectTrigger class="h-12">
                                    <SelectValue placeholder="Select Sale Channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="channel in channels" :key="channel.identifier" :value="channel.identifier">
                                        {{ channel.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Airline Search & Select -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Airline</Label>
                            <Popover v-model:open="airlineSearchOpen">
                                <PopoverTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        role="combobox"
                                        :aria-expanded="airlineSearchOpen"
                                        class="w-full h-12 justify-between"
                                    >
                                        {{ selectedAirlineName }}
                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-[400px] p-0">
                                    <Command>
                                        <CommandInput
                                            placeholder="Search airline..."
                                            :value="airlineSearch"
                                            @input="handleAirlineSearchInput"
                                        />
                                            <CommandEmpty>No airline found.</CommandEmpty>
                                        <CommandList>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all-airlines"
                                                    @select="() => {
                                                        form.airline_id = null;
                                                        airlineSearchOpen = false;
                                                    }"
                                                >
                                                    <Check
                                                        :class="cn(
                                                            'mr-2 h-4 w-4',
                                                            !form.airline_id ? 'opacity-100' : 'opacity-0'
                                                        )"
                                                    />
                                                    All Airlines
                                                </CommandItem>
                                                <CommandItem
                                                    v-for="airline in airlines"
                                                    :key="airline.id"
                                                    :value="airline.name + ' ' + airline.iata_code"
                                                    @select="() => {
                                                        form.airline_id = airline.id;
                                                        syncAirlineCache([airline]);
                                                        airlineSearchOpen = false;
                                                    }"
                                                >
                                                    <Check
                                                        :class="cn(
                                                            'mr-2 h-4 w-4',
                                                            form.airline_id === airline.id ? 'opacity-100' : 'opacity-0'
                                                        )"
                                                    />
                                                    <div class="flex items-center gap-2">
                                                        <img v-if="airline.logo_url" :src="airline.logo_url" class="w-5 h-5 object-contain" />
                                                        {{ airline.name }} ({{ airline.iata_code }})
                                                    </div>
                                                </CommandItem>
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <!-- Reservation Type -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Reservation Type</Label>
                            <Select v-model="form.reservation_type" required>
                                <SelectTrigger class="h-12">
                                    <SelectValue placeholder="Select Reservation Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in reservationTypes" :key="type" :value="type">
                                        {{ type }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <!-- Commission -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Promotion Value</Label>
                            <div class="flex gap-2">
                                <Input type="number" v-model="form.commission_value" class="h-12" required min="0" step="0.01" />
                                <Select v-model="form.price_option" required>
                                    <SelectTrigger class="h-12 w-[140px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="markup">Markup</SelectItem>
                                        <SelectItem value="discount">Discount</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="form.commission_type" required>
                                    <SelectTrigger class="h-12 w-[150px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="amount">Amount (PKR)</SelectItem>
                                        <SelectItem value="percentage">Percentage (%)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4 border-t">
                        <!-- Traveling Dates -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Traveling Dates</Label>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label class="text-xs text-gray-500">Start Date</Label>
                                    <Input type="date" v-model="form.travel_start_date" class="h-12" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-xs text-gray-500">End Date</Label>
                                    <Input type="date" v-model="form.travel_end_date" class="h-12" />
                                </div>
                            </div>
                        </div>

                        <!-- Ticketing Dates -->
                        <div class="space-y-4">
                            <Label class="text-base font-semibold">Ticketing Dates</Label>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label class="text-xs text-gray-500">Start Date</Label>
                                    <Input type="date" v-model="form.ticketing_start_date" class="h-12" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-xs text-gray-500">End Date</Label>
                                    <Input type="date" v-model="form.ticketing_end_date" class="h-12" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-8 border-t">
                        <Button type="button" variant="outline" @click="router.back()" class="h-12 px-8">Cancel</Button>
                        <Button type="submit" :disabled="isSaving" class="h-12 px-8 min-w-[150px]">
                            <Loader2 v-if="isSaving" class="w-4 h-4 mr-2 animate-spin" />
                            Update Promotion
                        </Button>
                    </div>
                </form>
            </div>
        </div>
        <div v-else class="flex items-center justify-center h-full">
            <Loader2 class="w-8 h-8 animate-spin text-primary" />
        </div>
    </section>
</template>
