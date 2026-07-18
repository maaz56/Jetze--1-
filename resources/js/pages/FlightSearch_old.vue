<script setup>
import Header from "../components/shared/Header.vue/index.js";
import Nav from "../components/shared/Nav.vue";
import { initFlowbite } from "flowbite";
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import Skeleton from "primevue/skeleton";
import {
    FETCH_FLIGHT,
    FETCH_FLIGHTS,
    FILTER_FLIGHTS,
    SEND_QUOTATION,
} from "../services/store/actions.type";
import Accordion from "../components/common/Collapse.vue";
import { Slider } from "@/components/ui/slider";
import { Badge } from "@/components/ui/badge";
import {
    ChevronDown,
    ChevronsUpDown,
    LoaderCircle,
    Percent,
    Share,
    Zap,
} from "lucide-vue-next";
import { PlaneTakeoff } from "lucide-vue-next";
import { PlaneLanding } from "lucide-vue-next";
import { Button } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import Spinner from "@/components/common/Spinner.vue";
import { useRouter } from "vue-router";
import Input from "@/components/ui/input/Input.vue";
import moment from "moment";
import {
    calculateLayover,
    cn,
    formatAmount,
    formatDuration,
} from "@/lib/utils";

const store = useStore();
const route = useRoute();
const router = useRouter();

const selectedStops = ref();
const selectedAirline = ref([]);
const selectedTimes = ref([]);
const maxPrice = ref();
const priceMargin = ref("");
const quotationTo = ref("");
const isShownMarginInput = ref(false);

const flights = computed(() => store.getters["flight/flights"]);
const user = computed(() => store.getters["auth/user"]);
const isLoading = computed(() => store.getters["flight/isLoading"]);
const availableAirlines = computed(
    () => store.getters["flight/availableAirlines"]
);

function fetchFlights() {
    store.dispatch("flight/" + FETCH_FLIGHTS, {
        origin: route.query.origin,
        destination: route.query.destination,
        departure_date: route.query.departure_date,
        return_date: route.query.return_date ?? null,
        cabin_class: route.query.cabin_class,
        adults: route.query.adults,
        children: route.query.children,
        infants: route.query.infants,

        airline: selectedAirline.value,
        stops: selectedStops.value,
        // times: selectedTimes.value,
        // maxPrice: maxPrice.value,
    });
}

function sendQuotation(flight_id) {
    store.dispatch("flight/" + SEND_QUOTATION, {
        flight_id: flight_id,
        quotation_to: quotationTo.value,
        price_margin: priceMargin.value,
    });
}

watch(
    () => route.query,
    (newQuery) => {
        fetchFlights(newQuery);
    },
    { immediate: true }
);

onMounted(() => {
    initFlowbite();
    // fetchFlights();
});
</script>

<template>
    <div
        class="relative bg-[url('https://digital.ihg.com/is/image/ihg/ihgor-member-rate-web-offers-1440x720')] bg-no-repeat bg-cover bg-center grid place-items-center"
    >
        <div class="absolute bg-black w-full h-full bg-opacity-45"></div>
        <div class="w-full z-30">
            <Nav />
        </div>
    </div>
    <div class="md:container flex flex-col md:flex-row gap-x-10 py-12">
        <div
            v-if="isLoading"
            class="bg-white rounded-lg h-[400px] w-full md:w-[450px] p-4 flex items-center justify-center border"
        >
            <div role="status">
                <LoaderCircle class="w-5 h-5 animate-spin text-primary" />
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <div v-else class="relative w-full md:w-[450px]">
            <div
                class="sticky top-10 p-4 bg-white rounded-lg h-[90vh] overflow-y-auto"
            >
                <div class="bg-gray-100 rounded-lg p-4 flex flex-col">
                    <span class="text-sm text-muted-foreground font-medium">{{
                        $t("search_queries")
                    }}</span>
                    <div class="flex-grow">
                        <div>
                            <span class="text-lg font-semibold">{{
                                $route.query.origin
                            }}</span>
                            <span class="mx-2">To</span>
                            <span class="text-lg font-semibold">{{
                                $route.query.destination
                            }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground mr-2"
                                >{{ $t("departure_date") }}:</span
                            >
                            <span>{{ $route.query.departure_date }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-muted-foreground mr-2"
                                >{{ $t("return_date") }}:</span
                            >
                            <span>{{ $route.query.return_date }}</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div v-if="$route.query.adults">
                                <span class="text-sm text-muted-foreground mr-2"
                                    >{{ $t("adults") }}:</span
                                >
                                <span>{{ $route.query.adults }}</span>
                            </div>
                            <div v-if="$route.query.children">
                                <span class="text-sm text-muted-foreground mr-2"
                                    >{{ $t("children") }}:</span
                                >
                                <span>{{ $route.query.children }}</span>
                            </div>
                            <div v-if="$route.query.infants">
                                <span class="text-sm text-muted-foreground mr-2"
                                    >{{ $t("infants") }}:</span
                                >
                                <span>{{ $route.query.infants }}</span>
                            </div>
                        </div>
                    </div>
                    <Dialog>
                        <DialogTrigger as-child>
                            <Button class="w-full mt-3">
                                {{ $t("modify") }}
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="md:max-w-screen-xl md:p-12">
                            <Header />
                        </DialogContent>
                    </Dialog>
                </div>

                <div class="mt-3">
                    <h1 class="text-xl font-medium">{{ $t("filter") }}</h1>

                    <!-- <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("time") }}
                        </p>
                        <ul>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedTimes"
                                        @change="filterFlights"
                                        id="morning"
                                        type="checkbox"
                                        value="morning"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="morning"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                        >{{ $t("morning") }} (5am - 12pm)</label
                                    >
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedTimes"
                                        @change="filterFlights"
                                        id="afternoon"
                                        type="checkbox"
                                        value="afternoon"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="afternoon"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                        >{{ $t("afternoon") }} (12pm -
                                        6pm)</label
                                    >
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedTimes"
                                        @change="filterFlights"
                                        id="evening"
                                        type="checkbox"
                                        value="evening"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="evening"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                        >{{ $t("evening") }} (6pm - 12am)</label
                                    >
                                </div>
                            </li>
                        </ul>
                    </div>
                    -->

                    <!-- <div class="mb-4">
                        <label for="price-range" class="block text-gray-700">{{
                            $t("max_price")
                        }}</label>
                        <input
                            v-model="maxPrice"
                            @input="filterFlights"
                            id="price-range"
                            type="number"
                            min="0"
                            class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        />
                    </div> -->

                    <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("stops") }}
                        </p>
                        <ul>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedStops"
                                        @change="fetchFlights"
                                        id="all"
                                        type="radio"
                                        value="all"
                                        name="flight-stops"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="all"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                    >
                                        {{ $t("all") }}
                                    </label>
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedStops"
                                        @change="fetchFlights"
                                        id="1"
                                        type="radio"
                                        value="1"
                                        name="flight-stops"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="1"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                    >
                                        1 {{ $t("stop") }}
                                    </label>
                                </div>
                            </li>
                            <li class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedStops"
                                        @change="fetchFlights"
                                        id="2"
                                        type="radio"
                                        value="2"
                                        name="flight-stops"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        for="2"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                    >
                                        2 {{ $t("stops") }}
                                    </label>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-3">
                        <p class="my-3 text-gray-500 font-medium text-sm">
                            {{ $t("airline") }}
                        </p>
                        <ul>
                            <li
                                v-for="item in availableAirlines"
                                :key="item.id"
                                class="flex items-center justify-between mb-2"
                            >
                                <div class="flex items-center gap-x-3">
                                    <input
                                        v-model="selectedAirline"
                                        @change="fetchFlights"
                                        :id="item.name"
                                        type="checkbox"
                                        :value="item.id"
                                        class="w-4 h-4 text-gray-600 bg-gray-100 border-gray-300 rounded focus:ring-gray-500 dark:focus:ring-gray-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                    />
                                    <label
                                        :for="item.name"
                                        class="flex items-center gap-2 text-gray-500 cursor-pointer text-base"
                                    >
                                        <div
                                            class="w-6 h-6 rounded-full overflow-hidden"
                                        >
                                            <img
                                                class="w-full h-full object-cover"
                                                :src="item.logo_url"
                                                alt=""
                                            />
                                        </div>
                                        {{ item.name }}</label
                                    >
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-full">
            <div>
                <h1 class="text-4xl font-semibold">
                    {{ $t("searching_flights_for") }}
                </h1>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3 my-3">
                        <Badge
                            v-if="
                                $route.query.origin && $route.query.destination
                            "
                            >{{
                                $route.query.origin +
                                " " +
                                `${$t("to")}` +
                                " " +
                                $route.query.destination
                            }}</Badge
                        >
                        <Badge v-if="$route.query.departure_date">
                            {{
                                `${$t("departure_date")}` +
                                " " +
                                $route.query.departure_date
                            }}
                        </Badge>
                        <Badge v-if="$route.query.class_type > 0">
                            {{ $route.query.class_type + " " + "class" }}
                        </Badge>
                        <Badge v-if="$route.query.adults > 0">
                            {{ $route.query.adults }}
                            {{
                                " " + $route.query.adults > 1
                                    ? `${$t("adults")}`
                                    : `${$t("adult")}`
                            }}
                        </Badge>
                        <Badge v-if="$route.query.children > 0">
                            {{ $route.query.children }}
                            {{
                                " " + $route.query.children > 1
                                    ? `${$t("children")}`
                                    : `${$t("child")}`
                            }}
                        </Badge>
                    </div>

                    <div class="flex items-center gap-2">
                        <Input
                            v-if="isShownMarginInput"
                            v-model="priceMargin"
                            type="number"
                            class="w-[200px]"
                            placeholder="Price Margin"
                        />
                        <Button
                            @click="isShownMarginInput = !isShownMarginInput"
                            ><Zap class="w-5 h-5"
                        /></Button>
                    </div>
                </div>
            </div>
            <div v-if="flights && !isLoading" class="">
                <Collapsible
                    v-model:open="item.isOpen"
                    v-for="(item, index) in flights.itineraries"
                    :key="index"
                    class="bg-white shadow border hover:shadow-lg hover:scale-105 transition-all duration-150 h-fit cursor-pointer"
                >
                    <!-- <div
                        v-if="item.supplier == 'duffel'"
                        class="bg-gray-500 w-3 h-3"
                    ></div>
                    <div v-else class="bg-red-500 w-3 h-3"></div> -->
                    <div class="grid grid-cols-3 gap-x-3 px-6 pt-4">
                        <div class="flex flex-col gap-x-3">
                            <div class="w-6 h-6 overflow-hidden">
                                <img
                                    class="w-full h-full object-contain"
                                    :src="
                                        item.legs[0].stops[0].airline.logo_url
                                    "
                                    alt=""
                                />
                            </div>
                            <div class="text-start">
                                <div class="font-semibold">
                                    {{
                                        item.legs[0].stops[0].airline.name +
                                        " (" +
                                        item.legs[0].stops[0].airline
                                            .iata_code +
                                        ")"
                                    }}
                                </div>
                                <div class="font-medium mb-2">
                                    {{ item.legs[0].stops[0].aircraft?.name }}
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 items-center">
                            <div class="w-[300px]">
                                <div class="flex items-center justify-center">
                                    <span
                                        class="flex gap-2 text-xs mb-2 text-muted-foreground font-medium"
                                    >
                                        {{ $t("duration") }}:

                                        <span>
                                            {{
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "minutes"
                                                    )
                                                    .asHours()
                                                    | Math.floor) +
                                                " hour" +
                                                ((moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "minutes"
                                                    )
                                                    .asHours()
                                                    | Math.floor) !==
                                                1
                                                    ? "s"
                                                    : "") +
                                                " and " +
                                                moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "minutes"
                                                    )
                                                    .minutes() +
                                                " minute" +
                                                (moment
                                                    .duration(
                                                        item.legs[0].duration,
                                                        "minutes"
                                                    )
                                                    .minutes() !== 1
                                                    ? "s"
                                                    : "")
                                            }}
                                        </span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span
                                        class="text-xs whitespace-nowrap text-muted-foreground font-medium"
                                    >
                                        {{
                                            moment(
                                                item.legs[0].stops[0].departure
                                                    .time,
                                                "HH:mm:ssZ"
                                            ).format("hh:mm A")
                                        }}
                                    </span>
                                    <div class="w-full relative">
                                        <div
                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                        ></div>
                                        <hr
                                            class="border-black border-dashed"
                                        />
                                        <div
                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                        ></div>
                                    </div>
                                    <span
                                        class="text-xs whitespace-nowrap text-muted-foreground font-medium"
                                    >
                                        {{
                                            moment(
                                                item.legs[0].stops[
                                                    item.legs[0].stops.length -
                                                        1
                                                ].arrival.time,
                                                "HH:mm:ssZ"
                                            ).format("hh:mm A")
                                        }}
                                    </span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <div
                                        class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium"
                                    >
                                        {{
                                            item.legs[0].stops[0].departure
                                                .iata_code
                                        }}
                                    </div>
                                    <div
                                        v-if="item.legs[0].stops.length > 1"
                                        class="text-xs text-muted-foreground font-medium"
                                    >
                                        <span
                                            v-if="
                                                item.legs[0].stops.length == 1
                                            "
                                        >
                                            {{
                                                item.legs[0].stops.length -
                                                1 +
                                                `${$t("stop")}`
                                            }}
                                        </span>
                                        <span v-else>
                                            {{
                                                item.legs[0].stops.length -
                                                1 +
                                                `${$t("stops")}`
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-else
                                        class="text-xs text-muted-foreground font-medium"
                                    >
                                        {{ $t("non_stop") }}
                                    </div>
                                    <div
                                        class="flex gap-2 text-xs text-muted-foreground font-medium"
                                    >
                                        {{
                                            item.legs[0].stops[0].arrival
                                                .iata_code
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-2 items-center justify-center">
                            <span class="text-base font-semibold">{{
                                formatAmount(item.pricing.totalPrice)
                            }}</span>

                            <div class="flex flex-col items-end justify-end">
                                <div class="flex items-start gap-4">
                                    <Button
                                        @click="
                                            $router.push({
                                                name: 'FlightCheckout',
                                                query: {
                                                    flight_id: item.id,
                                                    price_margin:
                                                        priceMargin || 0,
                                                    supplier:
                                                        item.supplier ==
                                                        'duffel'
                                                            ? 0
                                                            : 1,
                                                },
                                            })
                                        "
                                        class="flex gap-2 px-4 text-white text-xs"
                                    >
                                        <span>{{ $t("book_now") }}</span>
                                    </Button>

                                    <CollapsibleTrigger as-child>
                                        <Button variant="ghost"
                                            ><ChevronDown class="w-5 h-5"
                                        /></Button>
                                    </CollapsibleTrigger>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="border-y p-6 flex items-center gap-x-6">
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ $t("duration") }}:
                            <span>{{
                                formatDuration(item.legs[0].duration)
                            }}</span>
                        </span>
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ $t("stops") }}:
                            <span>{{
                                item.legs[0].stops.length - 1
                            }}</span>
                        </span>
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ $t("carry_on_bags") }}:
                            <span
                                v-if="
                                    item &&
                                    item.slices &&
                                    item.legs[0] &&
                                    item.legs[0].stops &&
                                    item.legs[0].stops[0] &&
                                    item.legs[0].stops[0].baggages &&
                                    item.legs[0].stops[0].baggages
                                        .carry_on &&
                                    item.legs[0].stops[0].baggages.carry_on
                                        .quantity !== null
                                "
                            >
                                {{
                                    item.legs[0].stops[0].baggages.carry_on
                                        .quantity
                                }}
                            </span>
                            <span v-else> N/A </span>
                        </span>
                        <span class="text-sm font-medium text-muted-foreground">
                            {{ $t("checked_in_bags") }}:
                            <template
                                v-if="
                                    item &&
                                    item.slices &&
                                    item.legs[0] &&
                                    item.legs[0].stops &&
                                    item.legs[0].stops[0] &&
                                    item.legs[0].stops[0].baggages &&
                                    item.legs[0].stops[0].baggages
                                        .checked_in
                                "
                            >
                                <span
                                    v-if="
                                        item.legs[0].stops[0].baggages
                                            .checked_in.quantity !== null
                                    "
                                >
                                    {{
                                        item.legs[0].stops[0].baggages
                                            .checked_in.quantity
                                    }}
                                </span>
                                <span
                                    v-if="
                                        item.legs[0].stops[0].baggages
                                            .checked_in.weight !== undefined &&
                                        item.legs[0].stops[0].baggages
                                            .checked_in.weight !== null
                                    "
                                >
                                    ({{
                                        item.legs[0].stops[0].baggages
                                            .checked_in.weight
                                    }}
                                    kg)
                                </span>
                            </template>
                            <span v-else> N/A </span>
                        </span>
                    </div> -->
                    <CollapsibleContent>
                        <div>
                            <div
                                v-for="(stop, index) in item.legs[0].stops"
                                :key="stop.id"
                                class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50"
                            >
                                <div class="p-6 border-b-2 border-dashed">
                                    <div class="grid grid-cols-3 gap-x-3">
                                        <div class="text-start">
                                            <div
                                                class="flex items-center gap-x-3"
                                            >
                                                <img
                                                    class="w-8 h-8 rounded-full"
                                                    :src="stop.airline.logo_url"
                                                    alt=""
                                                />
                                                <span
                                                    class="text-lg font-semibold"
                                                    >{{
                                                        stop.airline.name
                                                    }}</span
                                                >
                                            </div>
                                            <div>
                                                <span
                                                    class="text-lg font-semibold"
                                                >
                                                    {{
                                                        stop.departure.airport
                                                            .city_name
                                                    }}
                                                    <span
                                                        class="font-medium text-muted-foreground"
                                                        >({{
                                                            stop.departure
                                                                .airport
                                                                .iata_code
                                                        }})</span
                                                    >
                                                </span>
                                            </div>
                                            <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("aircraft") }}:
                                                    <span>{{
                                                        stop.aircraft?.name
                                                    }}</span>
                                                </span>
                                            </div>
                                            <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("terminal") }}:
                                                    <span>{{
                                                        stop.departure
                                                            .terminal ?? "N / A"
                                                    }}</span>
                                                </span>
                                            </div>
                                            <!-- <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("departure_at") }}:
                                                    <span>{{
                                                        moment(
                                                            stop.departure.time,
                                                            "HH:mm:ssZ"
                                                        ).format("hh:mm A")
                                                    }}</span>
                                                </span>
                                            </div> -->
                                        </div>

                                        <div
                                            class="grid grid-cols-3 items-center"
                                        >
                                            <div class="w-[300px]">
                                                <!-- <div
                                                    class="flex items-center justify-center"
                                                >
                                                    <span
                                                        class="flex gap-2 text-xs mb-2 text-muted-foreground font-medium"
                                                    >
                                                        {{ $t("duration") }}:
                                                        <span>
                                                            {{
                                                                (moment
                                                                    .duration(
                                                                        stop.duration,
                                                                        "minutes"
                                                                    )
                                                                    .asHours()
                                                                    | Math.floor) +
                                                                " hour" +
                                                                ((moment
                                                                    .duration(
                                                                        stop.duration,
                                                                        "minutes"
                                                                    )
                                                                    .asHours()
                                                                    | Math.floor) !==
                                                                1
                                                                    ? "s"
                                                                    : "") +
                                                                " and " +
                                                                moment
                                                                    .duration(
                                                                        stop.duration,
                                                                        "minutes"
                                                                    )
                                                                    .minutes() +
                                                                " minute" +
                                                                (moment
                                                                    .duration(
                                                                        stop.duration,
                                                                        "minutes"
                                                                    )
                                                                    .minutes() !==
                                                                1
                                                                    ? "s"
                                                                    : "")
                                                            }}
                                                        </span>
                                                    </span>
                                                </div> -->
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <span
                                                        class="text-sm whitespace-nowrap text-muted-foreground font-medium"
                                                    >
                                                        {{
                                                            moment(
                                                                stop.departure
                                                                    .time,
                                                                "HH:mm:ssZ"
                                                            ).format("hh:mm A")
                                                        }}
                                                    </span>
                                                    <div
                                                        class="w-full relative"
                                                    >
                                                        <div
                                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                                        ></div>
                                                        <hr
                                                            class="border-black border-dashed"
                                                        />
                                                        <div
                                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                                        ></div>
                                                    </div>
                                                    <span
                                                        class="text-sm whitespace-nowrap text-muted-foreground font-medium"
                                                    >
                                                        {{
                                                            moment(
                                                                stop.arrival
                                                                    .time,
                                                                "HH:mm:ssZ"
                                                            ).format("hh:mm A")
                                                        }}
                                                    </span>
                                                </div>
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <div
                                                        class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium"
                                                    >
                                                        {{
                                                            stop.departure
                                                                .airport
                                                                .iata_code
                                                        }}
                                                    </div>
                                                    <div
                                                        class="flex gap-2 text-sm text-muted-foreground font-medium"
                                                    >
                                                        {{
                                                            stop.arrival.airport
                                                                .iata_code
                                                        }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="text-end flex flex-col justify-center"
                                        >
                                            <div>
                                                <span
                                                    class="text-lg font-semibold"
                                                >
                                                    {{
                                                        stop.arrival.airport
                                                            .city_name
                                                    }}
                                                    <span
                                                        class="font-medium text-muted-foreground"
                                                        >({{
                                                            stop.arrival.airport
                                                                .iata_code
                                                        }})</span
                                                    >
                                                </span>
                                            </div>
                                            <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("aircraft") }}:
                                                    <span>{{
                                                        stop.aircraft?.name
                                                    }}</span>
                                                </span>
                                            </div>
                                            <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("terminal") }}:
                                                    <span>{{
                                                        stop.arrival.terminal ??
                                                        "N / A"
                                                    }}</span>
                                                </span>
                                            </div>
                                            <!-- <div
                                                class="text-sm font-medium text-muted-foreground mb-2"
                                            >
                                                <span>
                                                    {{ $t("arriving_at") }}:
                                                    <span>{{
                                                        moment(
                                                            stop.arriving_at
                                                        ).format("DD-MM-YYYY")
                                                    }}</span>
                                                </span>
                                            </div> -->
                                        </div>
                                    </div>
                                </div>
                                <!-- <div
                                    class="p-4 absolute right-1/2 botom-1/2 z-20 transform translate-x-1/2 -translate-y-1/2"
                                >
                                    <Badge
                                        class="text-sm py-2"
                                        v-if="
                                            item.legs[0].stops.length - 1 >
                                            index
                                        "
                                    >
                                        <span class="font-medium mr-1"
                                            >{{ $t("layover") }}:</span
                                        >
                                        <span class="font-normal">
                                            {{
                                                calculateLayover(
                                                    stop.arriving_at,
                                                    item.legs[0].stops[
                                                        index + 1
                                                    ].departure_at
                                                )
                                            }}
                                        </span>
                                    </Badge>
                                </div> -->
                            </div>
                        </div>

                        <!-- return flight -->
                        <div
                            v-for="(stop, index) in item.legs[1]?.stops"
                            :key="stop.id"
                            class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50"
                        >
                            <div class="p-6 border-b-2 border-dashed">
                                <div class="grid grid-cols-3 gap-x-3">
                                    <div class="text-start">
                                        <div class="flex items-center gap-x-3">
                                            <img
                                                class="w-8 h-8 rounded-full"
                                                :src="stop.airline.logo_url"
                                                alt=""
                                            />
                                            <span
                                                class="text-lg font-semibold"
                                                >{{ stop.airline.name }}</span
                                            >
                                        </div>
                                        <div>
                                            <span class="text-lg font-semibold">
                                                {{
                                                    stop.departure.airport
                                                        .city_name
                                                }}
                                                <span
                                                    class="font-medium text-muted-foreground"
                                                    >({{
                                                        stop.departure.airport
                                                            .iata_code
                                                    }})</span
                                                >
                                            </span>
                                        </div>
                                        <div
                                            class="text-sm font-medium text-muted-foreground mb-2"
                                        >
                                            <span>
                                                {{ $t("aircraft") }}:
                                                <span>{{
                                                    stop.aircraft?.name
                                                }}</span>
                                            </span>
                                        </div>
                                        <div
                                            class="text-sm font-medium text-muted-foreground mb-2"
                                        >
                                            <span>
                                                {{ $t("terminal") }}:
                                                <span>{{
                                                    stop.departure.terminal ??
                                                    "N / A"
                                                }}</span>
                                            </span>
                                        </div>
                                        <!-- <div
                                            class="text-sm font-medium text-muted-foreground mb-2"
                                        >
                                            <span>
                                                {{ $t("departure_at") }}:
                                                <span>{{
                                                    moment(
                                                        stop.departure_at
                                                    ).format("DD-MM-YYYY HH:MM")
                                                }}</span>
                                            </span>
                                        </div> -->
                                    </div>

                                    <div class="grid grid-cols-3 items-center">
                                        <div class="w-[300px]">
                                            <!-- <div
                                                class="flex items-center justify-center"
                                            >
                                                <span
                                                    class="flex gap-2 text-xs mb-2 text-muted-foreground font-medium"
                                                >
                                                    {{ $t("duration") }}:
                                                    <span>{{
                                                        formatDuration(
                                                            stop.departure.time,
                                                            stop.arrival.time
                                                        )
                                                    }}</span>
                                                </span>
                                            </div> -->
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <span
                                                    class="text-sm whitespace-nowrap text-muted-foreground font-medium"
                                                >
                                                    {{
                                                        moment(
                                                            stop.departure.time,
                                                            "HH:mm:ssZ"
                                                        ).format("hh:mm A")
                                                    }}
                                                </span>
                                                <div class="w-full relative">
                                                    <div
                                                        class="absolute left-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                                    ></div>
                                                    <hr
                                                        class="border-black border-dashed"
                                                    />
                                                    <div
                                                        class="absolute right-0 top-1/2 transform -translate-y-1/2 rounded-full w-2 h-2 border-2 border-black"
                                                    ></div>
                                                </div>
                                                <span
                                                    class="text-sm whitespace-nowrap text-muted-foreground font-medium"
                                                >
                                                    {{
                                                        moment(
                                                            stop.arrival.time,
                                                            "HH:mm:ssZ"
                                                        ).format("hh:mm A")
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <div
                                                    class="flex gap-2 text-sm mb-2 text-muted-foreground font-medium"
                                                >
                                                    {{
                                                        stop.departure.airport
                                                            .iata_code
                                                    }}
                                                </div>
                                                <div
                                                    class="flex gap-2 text-sm text-muted-foreground font-medium"
                                                >
                                                    {{
                                                        stop.arrival.airport
                                                            .iata_code
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="text-end flex flex-col justify-center"
                                    >
                                        <div>
                                            <span class="text-lg font-semibold">
                                                {{
                                                    stop.arrival.airport
                                                        .city_name
                                                }}
                                                <span
                                                    class="font-medium text-muted-foreground"
                                                    >({{
                                                        stop.arrival.airport
                                                            .iata_code
                                                    }})</span
                                                >
                                            </span>
                                        </div>
                                        <div
                                            class="text-sm font-medium text-muted-foreground mb-2"
                                        >
                                            <span>
                                                {{ $t("aircraft") }}:
                                                <span>{{
                                                    stop.aircraft?.name
                                                }}</span>
                                            </span>
                                        </div>
                                        <div
                                            class="text-sm font-medium text-muted-foreground mb-2"
                                        >
                                            <span>
                                                {{ $t("terminal") }}:
                                                <span>{{
                                                    stop.arrival.terminal ??
                                                    "N / A"
                                                }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- <div
                                class="p-4 absolute right-1/2 botom-1/2 z-20 transform translate-x-1/2 -translate-y-1/2"
                            >
                                <Badge
                                    class="text-sm py-2"
                                    v-if="item.legs[1].stops.length - 1 > index"
                                >
                                    <span class="font-medium mr-1"
                                        >{{ $t("layover") }}:</span
                                    >
                                    <span class="font-normal">
                                        {{
                                            calculateLayover(
                                                stop.arriving_at,
                                                item.legs[1].stops[index + 1]
                                                    .departure_at
                                            )
                                        }}
                                    </span>
                                </Badge>
                            </div> -->
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>

            <div
                v-if="!isLoading && flights.itineraries.length == 0"
                class="flex items-center justify-center p-12 bg-gray-100 rounded-2xl"
            >
                <span>{{ $t("nothing_found") }}.</span>
            </div>
            <div v-if="isLoading" class="space-y-4">
                <div
                    v-for="i in 5"
                    :key="i.id"
                    class="bg-white border w-full rounded-lg h-[200px] p-4 flex items-start justify-between"
                >
                    <div>
                        <div class="flex items-center gap-x-3">
                            <Skeleton
                                width="150px"
                                height="30px"
                                class="rounded-none bg-gray-300 mb-4"
                            />
                        </div>
                        <Skeleton
                            width="60px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="90px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="200px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="150px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                    </div>

                    <div class="flex flex-col items-end">
                        <Skeleton
                            width="150px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="60px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="90px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="200px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                        <Skeleton
                            width="150px"
                            height="15px"
                            class="rounded-none bg-gray-300 mb-4"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
