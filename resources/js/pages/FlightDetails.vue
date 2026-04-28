<script setup>
import { useRoute } from "vue-router";
import Nav from "../components/shared/Nav.vue";
import { computed, onMounted } from "vue";
import { useStore } from "vuex";
import { FETCH_FLIGHT } from "../services/store/actions.type";
import Button from "../components/common/Button.vue";

const store = useStore();
const route = useRoute();

const flight = computed(() => store.getters["flight/flight"](route.query.flight_id));

function fetchFlight() {
    store.dispatch("flight/" + FETCH_FLIGHT, {
        id: route.query.flight_id,
    });
}

onMounted(() => {
    // fetchFlight();
});
</script>

<template>
    <Nav />
    {{ flight }}
    <div class="container-default pt-6">
        <div class="my-4">
            <h1 class="text-3xl">From "ISB" to "LHD"</h1>
        </div>
        <div class="flex gap-4">
            <div class="w-full">
                <div class="card border rounded-2xl w-full p-4">
                    <h1 class="text-2xl">Islamabad to London</h1>
                    <p class="text-sm text-gray-500">
                        2:25pm - 7:15am (20h 50m, 2 stops)
                    </p>
                    <p class="text-sm text-gray-500">
                        Multiple airlines • Tue, May 28
                    </p>
                    <div class="bg-gray-100 rounded-2xl p-4 mt-4">
                        <p class="text-sm font-medium">2:25pm - Islamabad</p>
                        <p class="text-sm text-gray-500">
                            Islamabad Intl. (ISB)
                        </p>
                        <p class="text-sm mt-2 text-gray-500">3h 35m flight</p>
                        <p class="text-sm text-gray-500">
                            Pakistan International Airlines 211
                        </p>
                        <p class="text-sm text-gray-500">Airbus A320</p>
                        <p class="text-sm text-gray-500">Economy/Coach (O)</p>
                        <p class="text-sm font-medium mt-2">7:15am - London</p>
                        <p class="text-sm text-gray-500">London City (LCY)</p>
                    </div>
                </div>

                <div class="card border rounded-2xl w-full p-4 mt-4">
                    <h1 class="text-2xl">London to Islamabad</h1>
                    <p class="text-sm text-gray-500">
                        2:25pm - 7:15am (20h 50m, 2 stops)
                    </p>
                    <p class="text-sm text-gray-500">
                        Multiple airlines • Tue, May 28
                    </p>
                    <div class="bg-gray-100 rounded-2xl p-4 mt-4">
                        <p class="text-sm font-medium">2:25pm - Islamabad</p>
                        <p class="text-sm text-gray-500">
                            Islamabad Intl. (ISB)
                        </p>
                        <p class="text-sm mt-2 text-gray-500">3h 35m flight</p>
                        <p class="text-sm text-gray-500">
                            Pakistan International Airlines 211
                        </p>
                        <p class="text-sm text-gray-500">Airbus A320</p>
                        <p class="text-sm text-gray-500">Economy/Coach (O)</p>
                        <p class="text-sm font-medium mt-2">7:15am - London</p>
                        <p class="text-sm text-gray-500">London City (LCY)</p>
                    </div>
                </div>
            </div>
            <div class="w-[500px]">
                <div class="card border rounded-2xl p-4 h-fit">
                    <h1 class="text-2xl">Summary</h1>
                    <div class="flex items-center justify-between">
                        <span>Traveller 1: Adult </span>
                        <span class="font-medium">$1,675.90 </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Flight </span>
                        <span class="font-medium">$834.00 </span>
                    </div>
                    <hr class="my-3" />
                    <div class="flex items-center justify-between">
                        <h1 class="text-2xl">Total</h1>
                        <h1 class="text-2xl font-medium">$2,509.90</h1>
                    </div>
                    <Button
                        title="Checkout"
                        class="gradient-default text-white w-full border-none my-3"
                    />
                </div>

                <div class="mt-4 card border p-4 rounded-2xl">
                    <p class="font-medium text-sm">Payment rules</p>
                    <span class="text-xs">There's no fee to cancel within 24 hours of booking.</span>
                </div>
            </div>
        </div>
    </div>
</template>
