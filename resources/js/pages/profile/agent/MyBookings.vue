<script setup>
import Button from "@/components/ui/button/Button.vue";
import { Switch } from "@/components/ui/switch";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { calculateLayover,formatDuration  } from "@/lib/utils";
import { useStore } from "vuex";
import { computed, onMounted, ref } from "vue";
import {
    FETCH_BOOKINGS,
    FETCH_TRANSACTIONS,
} from "@/services/store/actions.type";
import moment from "moment";
import Badge from "@/components/ui/badge/Badge.vue";

const store = useStore();

const bookings = computed(() => store.getters["flight/bookings"]);
const transactions = computed(() => store.getters["transaction/transactions"]);
const user = computed(() => store.getters["auth/user"]);

function fetchTransactions() {
    store.dispatch("transaction/" + FETCH_TRANSACTIONS, {
        user_id: user.id,
    });
}

function fetchBookings() {
    store.dispatch("flight/" + FETCH_BOOKINGS);
}

onMounted(() => {
    fetchTransactions();
    fetchBookings();
});
</script>

<template>
    <section>
        <div>
            <div class="flex items-center justify-between mb-4">
                <span class="text-3xl font-medium">My Bookings</span>
            </div>
            <div
                class="bg-white dark:bg-gray-800 relative sm:rounded-lg border overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                    >
                        <thead
                            class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-3">Email</th>
                                <th scope="col" class="px-4 py-3">Phone</th>
                                <th scope="col" class="px-4 py-3">Country</th>
                                <th scope="col" class="px-4 py-3">
                                    Price Margin
                                </th>
                                <th scope="col" class="px-4 py-3">
                                    Total Price
                                </th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3">Date</th>
                                <th scope="col" class="px-4 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="booking in bookings"
                                :key="booking.id"
                                class="border-b dark:border-gray-700"
                            >
                                <td class="px-4 py-3">
                                    {{ booking.email }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.phone }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.country }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ booking.flight.price_margin }}
                                </td>
                                <td class="px-4 py-3">
                                    {{
                                        parseFloat(
                                            booking.flight.price.total +
                                                booking.flight.price_margin
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <!-- <span
                                        class="bg-gray-200 px-4 py-1 rounded-lg text-gray-700"
                                    >
                                        Approved
                                    </span> -->
                                    <span
                                        class="bg-red-200 px-4 py-1 rounded-lg text-red-700"
                                    >
                                        Pending
                                    </span>
                                </td>
                                <td>
                                    {{
                                        moment(booking.created_at).format(
                                            "MM-DD-YYYY"
                                        )
                                    }}
                                </td>
                                <td>
                                    <Dialog>
                                        <DialogTrigger class="w-full">
                                            <Button variant="ghost"
                                                >View</Button
                                            >
                                        </DialogTrigger>
                                        <DialogContent class="max-w-screen-xl">
                                            <DialogHeader>
                                                <DialogTitle
                                                    >Booking
                                                    details</DialogTitle
                                                >

                                                <div class="mb-4 mt-4">
                                                    <div
                                                        v-for="(
                                                            stop, index
                                                        ) in booking.flight
                                                            .slices[0].segments"
                                                        :key="stop.id"
                                                        class="relative bg-gradient-to-r from-rose-100/50 to-teal-100/50"
                                                    >
                                                        <div
                                                            class="p-6 border-b-2 border-dashed"
                                                        >
                                                            <div
                                                                class="grid grid-cols-3 gap-x-3"
                                                            >
                                                                <div
                                                                    class="text-start"
                                                                >
                                                                    <div
                                                                        class="flex items-center gap-x-3"
                                                                    >
                                                                        <img
                                                                            class="w-8 h-8 rounded-full"
                                                                            :src="
                                                                                stop
                                                                                    .airline
                                                                                    .logo_url
                                                                            "
                                                                            alt=""
                                                                        />
                                                                        <span
                                                                            class="text-lg font-semibold"
                                                                            >{{
                                                                                stop
                                                                                    .airline
                                                                                    .name
                                                                            }}</span
                                                                        >
                                                                    </div>
                                                                    <div>
                                                                        <span
                                                                            class="text-lg font-semibold"
                                                                        >
                                                                            {{
                                                                                stop
                                                                                    .origin_airport
                                                                                    
                                                                                    .city_name
                                                                            }}
                                                                            <span
                                                                                class="font-medium text-muted-foreground"
                                                                                >({{
                                                                                    stop
                                                                                        .origin_airport
                                                                                        
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
                                                                                $t(
                                                                                    "aircraft"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    stop
                                                                                        .aircraft
                                                                                        ?.name
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                                    >
                                                                        <span>
                                                                            {{
                                                                                $t(
                                                                                    "terminal"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    stop.origin_terminal ??
                                                                                    "N / A"
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                                    >
                                                                        <span>
                                                                            {{
                                                                                $t(
                                                                                    "departure_at"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    moment(
                                                                                        stop.departure_at
                                                                                    ).format(
                                                                                        "ddd HH:mm"
                                                                                    )
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="grid grid-cols-3 items-center"
                                                                >
                                                                    <div
                                                                        class="w-[300px]"
                                                                    >
                                                                        <div
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
                                                                                <span
                                                                                    >{{
                                                                                        formatDuration(
                                                                                            stop.duration
                                                                                        )
                                                                                    }}</span
                                                                                >
                                                                            </span>
                                                                        </div>
                                                                        <div
                                                                            class="flex items-center gap-3"
                                                                        >
                                                                            <span
                                                                                class="text-sm whitespace-nowrap text-muted-foreground font-medium"
                                                                            >
                                                                                {{
                                                                                    moment(
                                                                                        stop.departure_at
                                                                                    ).format(
                                                                                        "H:mm"
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
                                                                                        stop.arriving_at
                                                                                    ).format(
                                                                                        "H:mm"
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
                                                                                        .origin_airport
                                                                                        
                                                                                        .iata_code
                                                                                }}
                                                                            </div>
                                                                            <div
                                                                                class="flex gap-2 text-sm text-muted-foreground font-medium"
                                                                            >
                                                                                {{
                                                                                    stop
                                                                                        .destination_airport
                                                                                        
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
                                                                                stop
                                                                                    .destination_airport
                                                                                    .city_name
                                                                            }}
                                                                            <span
                                                                                class="font-medium text-muted-foreground"
                                                                                >({{
                                                                                    stop
                                                                                        .destination_airport
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
                                                                                $t(
                                                                                    "aircraft"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    stop
                                                                                        .aircraft
                                                                                        ?.name
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                                    >
                                                                        <span>
                                                                            {{
                                                                                $t(
                                                                                    "terminal"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    stop.destination_terminal ??
                                                                                    "N / A"
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                    <div
                                                                        class="text-sm font-medium text-muted-foreground mb-2"
                                                                    >
                                                                        <span>
                                                                            {{
                                                                                $t(
                                                                                    "arriving_at"
                                                                                )
                                                                            }}:
                                                                            <span
                                                                                >{{
                                                                                    moment(
                                                                                        stop.arriving_at
                                                                                    ).format(
                                                                                        "ddd HH:mm"
                                                                                    )
                                                                                }}</span
                                                                            >
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="p-4 absolute right-1/2 botom-1/2 z-20 transform translate-x-1/2 -translate-y-1/2"
                                                        >
                                                            <Badge
                                                                class="text-sm py-2"
                                                                v-if="
                                                                    booking
                                                                        .flight
                                                                        .slices[0]
                                                                        .segments
                                                                        .length -
                                                                        1 >
                                                                    index
                                                                "
                                                            >
                                                                <span
                                                                    class="font-medium mr-1"
                                                                    >{{
                                                                        $t(
                                                                            "layover"
                                                                        )
                                                                    }}:</span
                                                                >
                                                                <span
                                                                    class="font-normal"
                                                                >
                                                                    {{
                                                                        calculateLayover(
                                                                            stop.arriving_at,
                                                                            booking
                                                                                .flight
                                                                                .slices[0]
                                                                                .segments[
                                                                                index +
                                                                                    1
                                                                            ]
                                                                                .departure_at
                                                                        )
                                                                    }}
                                                                </span>
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                            </DialogHeader>
                                        </DialogContent>
                                    </Dialog>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</template>
