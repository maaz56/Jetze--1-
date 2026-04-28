<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import {
    FETCH_COUNTRIES,
    FETCH_FLIGHT,
    SAVE_BOOKING,
    SAVE_BOOKING_DATA,
    FETCH_AGENT_DATA,
} from "@/services/store/actions.type";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import moment from "moment";
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Badge from "@/components/ui/badge/Badge.vue";
import { cn, formatAmount } from "@/lib/utils";
import { Check, ChevronsUpDown } from "lucide-vue-next";
import { calculateLayover, formatDuration } from "@/lib/utils";

const route = useRoute();
const store = useStore();
const authStore = useAuthStore();

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);

const flight = computed(() => store.getters["flight/flight"]);
const apiErrors = computed(() => store.getters["flight/apiErrors"]);
const countries = computed(() => {
    const countries = store.getters["country/countries"] || [];
    return countries.map((country) => ({
        value: country.name,
        label: country.name,
    }));
});
const isLoading = computed(() => store.getters["flight/isLoading"]);
const loading = ref(true);
const error = ref(null);

const isOpenCountryDropdown = ref(false);
const mainContact = ref({
    email: "",
    phone: "",
    country: "",
});


const agencyContact = computed(() => ({
    email: agentData.value.email,
    phone: agentData?.value?.agent_data?.mobile,
}));

const travellers = ref([]);

const initializeTravellers = () => {
    travellers.value = flight.value.passengerInfo.reduce((acc, passenger) => {
        // Create array of length passengerNumber for each type
        const passengerArray = Array(passenger.passengerNumber)
            .fill()
            .map(() => ({
                type: passenger.passengerType,
                // Add any additional fields needed for each passenger
                title: "",
                firstName: "",
                lastName: "",
                nationality: "",
                documentType: "",
                documentNo: "",
                expiryDate: "",
                issueCountry: "",
            }));

        return [...acc, ...passengerArray];
    }, []);
};
function fetchAgent() {
    if (user_id.value) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: user_id.value,
            }).then(()=>{
                //console.log("Agent data fetched successfully", agentData.value);
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}

function fetchCountries(event) {
    //console.log(event.target.value);

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: event.target.value,
    });
}

function saveBooking() {
    store.dispatch("flight/" + SAVE_BOOKING, {
        main_contact: mainContact.value,
        travellers: travellers.value,
        price_margin: route.query.price_margin,
        flight_id: route.query.flight_id,
        supplier: route.query.supplier,
    });
}

function saveBookingData() {
    store.dispatch("flight/" + SAVE_BOOKING_DATA, {
        main_contact: mainContact.value,
        flight_data: flight.value,
        travellers: travellers.value,
        agency_contact: agencyContact.value,
        flight_id: route.query.flight_id,
    });
}

function fetchFlight() {
    store.dispatch("flight/" + FETCH_FLIGHT, {
        flight_id: route.query.flight_id,
        price_margin: route.query.price_margin,
        supplier: route.query.supplier,
    });
}
watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
    }
});

onMounted(() => {
    if (user.value?.id) {
        //console.log("User ID found:", user.value.id);
        fetchAgent();
    }
    fetchFlight();
});

watch(flight, initializeTravellers);
</script>

<template>
    <div
        v-if="isLoading"
        class="flex items-center justify-center md:container h-[50vh] bg-white rounded-lg"
    >
        <Spinner />
    </div>
    <div
        v-if="flight == null && !isLoading"
        class="flex flex-col gap-6 items-center justify-center md:container h-[50vh] bg-white rounded-lg"
    >
        Nothing found.
        <Button @click="$router.back()">Back</Button>
    </div>
    <div v-if="!isLoading && flight">
        <div class="flex gap-x-3">
            <div class="w-full">
                <div class="bg-white rounded-lg">
                    <div class="border-b p-4">
                        <span class="text-2xl font-bold">Flight Details</span>
                    </div>
                    <div class="mt-3 p-4">
                        <div>
                            <div class="text-2xl">
                                <span class="font-bold">
                                    {{
                                        flight.legs[0]?.stops[0].departure
                                            .airport.city_name
                                    }}
                                    to
                                    {{
                                        flight.legs[0]?.stops[
                                            flight.legs[0]?.stops.length - 1
                                        ].arrival.airport.city_name
                                    }}
                                </span>
                            </div>
                            <div>
                                <!-- <span class="text-base"
                                        >Travel time:{{
                                            formatDuration(
                                                flight.legs[0].duration
                                            )
                                        }}{{
                                            flight.legs[0]?.stops.length - 1
                                        }}
                                        stop</span
                                    > -->
                            </div>
                        </div>
                        <div>
                            <div>
                                <!-- <div class="mt-4">
                                    <span class="text-2xl block"
                                        >{{
                                            moment(
                                                flight.legs[0]?.stops[0]
                                                    .departure_at,
                                            ).format("dddd HH:mm")
                                        }}-
                                        {{
                                            moment(
                                                flight.legs[0]?.stops[0]
                                                    .arriving_at,
                                            ).format("dddd HH:mm")
                                        }}</span
                                    >
                                    <span
                                        >{{
                                            flight.legs[0]?.stops[0].departure
                                                .airport.city_name
                                        }}({{
                                            flight.legs[0]?.stops[0].departure
                                                .airport.iata_code
                                        }})
                                    </span>
                                    <span>-</span>
                                    <span class="text-base"
                                        >{{
                                            flight.legs[0]?.stops[
                                                flight.legs[0]?.stops.length - 1
                                            ].arrival.airport.city_name
                                        }}
                                        ({{
                                            flight.legs[0]?.stops[
                                                flight.legs[0]?.stops.length - 1
                                            ].arrival.airport.iata_code
                                        }})</span
                                    >
                                </div> -->

                                <div class="mb-4 mt-4">
                                    <div
                                        v-for="(stop, index) in flight.legs[0]
                                            ?.stops"
                                        :key="stop.id"
                                        class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50"
                                    >
                                        <div
                                            class="p-6 border-b-2 border-dashed"
                                        >
                                            <div
                                                class="grid grid-cols-3 gap-x-3"
                                            >
                                                <div class="text-start">
                                                    <div
                                                        class="flex items-center gap-x-3"
                                                    >
                                                        <img
                                                            class="w-8 h-8 rounded-full"
                                                            :src="
                                                                stop.airline
                                                                    .logo_url
                                                            "
                                                            alt=""
                                                        />
                                                        <span
                                                            class="text-lg font-semibold"
                                                            >{{
                                                                stop.airline
                                                                    .name
                                                            }}</span
                                                        >
                                                    </div>
                                                    <div>
                                                        <span
                                                            class="text-lg font-semibold"
                                                        >
                                                            {{
                                                                stop.departure
                                                                    .airport
                                                                    .city_name
                                                            }}
                                                            <span
                                                                class="font-medium text-muted-foreground"
                                                                >({{
                                                                    stop
                                                                        .departure
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
                                                            {{
                                                                $t("aircraft")
                                                            }}:
                                                            <span>{{
                                                                stop.aircraft
                                                                    ?.name
                                                            }}</span>
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                    >
                                                        <span>
                                                            {{
                                                                $t("terminal")
                                                            }}:
                                                            <span>{{
                                                                stop.departure
                                                                    .terminal ??
                                                                "N / A"
                                                            }}</span>
                                                        </span>
                                                    </div>
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
                                                                    {{
                                                                        $t(
                                                                            "duration"
                                                                        )
                                                                    }}:
                                                                    <span>{{
                                                                        stop.duration
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
                                                                        stop
                                                                            .departure
                                                                            .time,
                                                                        "HH:mm:ssZ",
                                                                    ).format(
                                                                        "hh:mm A",
                                                                    )
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
                                                                        stop
                                                                            .arrival
                                                                            .time,
                                                                        "HH:mm:ssZ",
                                                                    ).format(
                                                                        "hh:mm A",
                                                                    )
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
                                                                    stop
                                                                        .departure
                                                                        .airport
                                                                        .iata_code
                                                                }}
                                                            </div>
                                                            <div
                                                                class="flex gap-2 text-sm text-muted-foreground font-medium"
                                                            >
                                                                {{
                                                                    stop.arrival
                                                                        .airport
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
                                                                stop.arrival
                                                                    .airport
                                                                    .city_name
                                                            }}
                                                            <span
                                                                class="font-medium text-muted-foreground"
                                                                >({{
                                                                    stop.arrival
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
                                                            {{
                                                                $t("aircraft")
                                                            }}:
                                                            <span>{{
                                                                stop.aircraft
                                                                    ?.name
                                                            }}</span>
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                    >
                                                        <span>
                                                            {{
                                                                $t("terminal")
                                                            }}:
                                                            <span>{{
                                                                stop.arrival
                                                                    .terminal ??
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
                                                    v-if="
                                                        flight.legs[0]?.stops
                                                            .length -
                                                            1 >
                                                        index
                                                    "
                                                >
                                                    <span
                                                        class="font-medium mr-1"
                                                        >{{
                                                            $t("layover")
                                                        }}:</span
                                                    >
                                                    <span class="font-normal">
                                                        {{
                                                            calculateLayover(
                                                                stop.arriving_at,
                                                                flight.legs[0]
                                                                    .stops[
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
                                    v-for="(stop, index) in flight.legs[1]
                                        ?.stops"
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
                                                        :src="
                                                            stop.airline
                                                                .logo_url
                                                        "
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
                                                            stop.departure
                                                                .airport
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
                                                                .terminal ??
                                                            "N / A"
                                                        }}</span>
                                                    </span>
                                                </div>
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
                                                                {{
                                                                    $t(
                                                                        "duration"
                                                                    )
                                                                }}:
                                                                <span>{{
                                                                    stop.duration
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
                                                                    stop
                                                                        .departure
                                                                        .time,
                                                                    "HH:mm:ssZ",
                                                                ).format(
                                                                    "hh:mm A",
                                                                )
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
                                                                    stop.arriving_at,
                                                                ).format("H:mm")
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
                                                                stop.arrival
                                                                    .airport
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
                                                                stop.arrival
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
                                                            stop.arrival
                                                                .terminal ??
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
                                                v-if="
                                                    flight.legs[1].stops
                                                        .length -
                                                        1 >
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
                                                            flight.legs[1]
                                                                .stops[
                                                                index + 1
                                                            ].departure_at
                                                        )
                                                    }}
                                                </span>
                                            </Badge>
                                        </div> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white mt-3 rounded-lg">
                    <div class="border-b p-4">
                        <span class="text-2xl font-bold"
                            >Main Contact Information</span
                        >
                    </div>
                    <div class="p-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="mb-3">
                                <Label for="main-email">Email</Label>
                                <Input
                                    v-model="mainContact.email"
                                    id="main-email"
                                    type="email"
                                    placeholder="Email"
                                />
                            </div>
                            <div class="mb-3">
                                <Label for="main-phone">Phone</Label>
                                <Input
                                    v-model="mainContact.phone"
                                    id="main-phone"
                                    type="tel"
                                    placeholder="Phone"
                                />
                            </div>

                            <div class="mb-3">
                                <Label>Country</Label>
                                <Popover v-model:open="isOpenCountryDropdown">
                                    <PopoverTrigger as-child>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            class="w-full justify-between py-6"
                                        >
                                            {{
                                                mainContact.country !== ""
                                                    ? countries.find(
                                                          (country) =>
                                                              country.value ===
                                                              mainContact.country,
                                                      )?.label ||
                                                      "Select a country..."
                                                    : "Select a country..."
                                            }}
                                            <ChevronsUpDown
                                                class="ml-2 h-4 w-4 shrink-0 opacity-50"
                                            />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-full p-0">
                                        <Command>
                                            <CommandInput
                                                class="h-9"
                                                @input="fetchCountries"
                                                placeholder="Search country..."
                                            />
                                            <CommandEmpty
                                                >No results found.</CommandEmpty
                                            >
                                            <CommandList>
                                                <CommandGroup>
                                                    <CommandItem
                                                        v-for="country in countries"
                                                        :key="country.value"
                                                        :value="country.label"
                                                        @select="
                                                            (ev) => {
                                                                if (
                                                                    typeof ev
                                                                        .detail
                                                                        .value ===
                                                                    'string'
                                                                ) {
                                                                    mainContact.country =
                                                                        ev.detail.value;
                                                                }
                                                                open = false;
                                                            }
                                                        "
                                                    >
                                                        {{ country.label }}
                                                        <Check
                                                            :class="
                                                                cn(
                                                                    'ml-auto h-4 w-4',
                                                                    mainContact.country ===
                                                                        country.value
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0',
                                                                )
                                                            "
                                                        />
                                                    </CommandItem>
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- <div class="bg-white mt-3 rounded-lg">
                    <div class="border-b p-4">
                        <span class="text-2xl font-bold"
                            >Traveller Details</span
                        >
                    </div>
                    <div
                        v-for="(traveller, index) in travellers"
                        :key="`traveller-${index}`"
                        class="bg-gray-50 mb-4"
                    >
                        <div class="p-4">
                            <span class="text-xl"
                                >{{ traveller.type }} Traveller
                                {{ index + 1 }}</span
                            >
                        </div>
                        <hr />
                        <div class="p-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="mb-3">
                                    <Label :for="`first-name-${index}`"
                                        >First name</Label
                                    >
                                    <Input
                                        v-model="traveller.first_name"
                                        :id="`first-name-${index}`"
                                        placeholder="First name"
                                    />
                                </div>
                                <div class="mb-3">
                                    <Label :for="`last-name-${index}`"
                                        >Last name</Label
                                    >
                                    <Input
                                        v-model="traveller.last_name"
                                        :id="`last-name-${index}`"
                                        placeholder="Last name"
                                    />
                                </div>
                                <div class="mb-3">
                                    <Label :for="`gender-${index}`"
                                        >Gender</Label
                                    >
                                    <Select v-model="traveller.gender">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select gender"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="Male">
                                                    Male
                                                </SelectItem>
                                                <SelectItem value="Female">
                                                    Female
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="mb-3">
                                    <Label :for="`date-of-birth-${index}`"
                                        >Date of birth</Label
                                    >
                                    <Input
                                        v-model="traveller.date_of_birth"
                                        type="date"
                                        :id="`date-of-birth-${index}`"
                                        placeholder="Date Of Birth"
                                    />
                                </div>
                                <div class="mb-3">
                                    <Label :for="`passport-number-${index}`"
                                        >Passport number</Label
                                    >
                                    <Input
                                        v-model="traveller.first_name"
                                        :id="`passport-number-${index}`"
                                        placeholder="Passport number"
                                    />
                                </div>
                                <div class="mb-3">
                                    <Label :for="`passport-expiry-${index}`"
                                        >Passport expiry date</Label
                                    >
                                    <Input
                                        v-model="traveller.date_of_birth"
                                        type="date"
                                        :id="`passport-expiry-${index}`"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div> -->

                <div class="w-full mx-auto mt-3 rounded-lg">
                    <div class="p-4">
                        <h2 class="text-lg font-semibold">Traveller Details</h2>
                        <p class="text-sm text-gray-600">
                            Use all given names and surnames exactly as they
                            appear on your passport/ID to avoid complications.
                        </p>
                    </div>

                    <div
                        v-for="(traveller, index) in travellers"
                        :key="`traveller-${index}`"
                        class="bg-white border rounded-lg mb-4"
                    >
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-lg">
                                {{ traveller.type }} Traveller {{ index + 1 }}
                            </h3>
                        </div>

                        <div class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="mb-3">
                                    <Label
                                        :for="`title-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Title</Label
                                    >
                                    <Select
                                        v-model="traveller.title"
                                        :id="`title-${index}`"
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select title"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="Mr">
                                                    Mr
                                                </SelectItem>
                                                <SelectItem value="Mrs">
                                                    Mrs
                                                </SelectItem>
                                                <SelectItem value="Ms">
                                                    Ms
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`first-name-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >First Name</Label
                                    >
                                    <Input
                                        v-model="traveller.firstName"
                                        :id="`first-name-${index}`"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter first name"
                                    />
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`last-name-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Last Name</Label
                                    >
                                    <Input
                                        v-model="traveller.lastName"
                                        :id="`last-name-${index}`"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter last name"
                                    />
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`nationality-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Nationality</Label
                                    >
                                    <Input
                                        v-model="traveller.nationality"
                                        :id="`nationality-${index}`"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter nationality"
                                    />
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`document-type-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Document Type</Label
                                    >
                                    <Select
                                        v-model="traveller.documentType"
                                        :id="`document-type-${index}`"
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select title"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectItem value="passport">
                                                    Passport
                                                </SelectItem>
                                                <SelectItem value="id">
                                                    ID Card
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`document-no-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Document No</Label
                                    >
                                    <Input
                                        v-model="traveller.documentNo"
                                        :id="`document-no-${index}`"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                        placeholder="Enter document number"
                                    />
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`expiry-date-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Expiry Date</Label
                                    >
                                    <Input
                                        v-model="traveller.expiryDate"
                                        :id="`expiry-date-${index}`"
                                        type="date"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                    />
                                </div>

                                <div class="mb-3">
                                    <Label
                                        :for="`issue-country-${index}`"
                                        class="block text-sm font-medium text-gray-700 mb-1"
                                        >Issue Country</Label
                                    >
                                    <Input
                                        v-model="traveller.issueCountry"
                                        :id="`issue-country-${index}`"
                                        type="text"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h3 class="text-lg font-semibold p-4">
                            Agency Details
                        </h3>
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-white rounded-lg"
                        >
                            <div>
                                <Label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Agency Contact</Label
                                >

                                <Input
                                    type="text"
                                    class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                    placeholder="Enter phone number"
                                    v-model="agencyContact.phone"
                                    readonly
                                />
                            </div>

                            <div>
                                <Label
                                    class="block text-sm font-medium text-gray-700 mb-1"
                                    >Email</Label
                                >
                                <Input
                                    type="email"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                    v-model="agencyContact.email"
                                    readonly
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 p-4">
                        <Input type="checkbox" id="terms" class="" />
                        <Label for="terms" class="text-sm text-gray-600">
                            I understand and agree with the Privacy Policy, the
                            User Agreement and Terms of Service of
                            Jetze.pk
                        </Label>
                    </div>

                    <div class="mt-6 text-right">
                        <button
                            class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700"
                        >
                            Book on Timelimit
                        </button>
                    </div>
                </div>
            </div>
            <div class="w-[500px]">
                <div class="bg-white p-4 border rounded-lg">
                    <div>
                        <span class="text-xl font-semibold"
                            >Price Details (PKR)</span
                        >
                    </div>
                    <div>
                        <ul
                            class="text-sm text-muted-foreground font-medium my-2 space-y-2"
                        >
                            <li class="flex items-center justify-between">
                                <span>Base Fare</span>

                                <span>{{
                                    formatAmount(
                                        parseFloat(
                                            flight.passengerInfo[0]
                                                .passengerTotalFare
                                                .equivalentAmount,
                                        ) +
                                            parseFloat(
                                                agentData?.agent_data
                                                    ?.margin_amount,
                                            ),
                                    )
                                }}</span>
                            </li>
                            <li class="flex items-center justify-between">
                                <span>Tax and fees</span>
                                <span>{{
                                    formatAmount(
                                        flight.pricing.totalTaxAmount || 0,
                                    )
                                }}</span>
                            </li>
                            <hr class="my-2 border border-dashed" />
                            <!-- <li class="flex items-center justify-between">
                                    <span>Price Margin (USD)</span>
                                    <span class="font-semibold text-black">{{
                                        formatAmount($route.query.price_margin)
                                    }}</span>
                                </li> -->
                            <li class="flex items-center justify-between">
                                <span>Total Price (PKR)</span>
                                <span class="font-semibold text-black">{{
                                    formatAmount(
                                        parseFloat(flight.pricing.totalPrice) +
                                            parseFloat(
                                                agentData?.agent_data
                                                    ?.margin_amount,
                                            ),
                                    )
                                }}</span>
                            </li>
                        </ul>
                        <!-- @click="saveBooking" -->
                        <Button @click="saveBookingData" class="w-full mt-3"
                            >Book flight</Button
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
