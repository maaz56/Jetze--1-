<script setup>
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import Button from "@/components/ui/button/Button.vue";
import Checkbox from "@/components/ui/checkbox/Checkbox.vue";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { computed, onMounted, ref } from "vue";
import { useStore } from "vuex";
import { FETCH_COUNTRIES } from "@/services/store/actions.type";
import { FETCH_CITIES } from "@/services/store/actions.type";

const store = useStore();

const role = ref();
const country = ref();
const city = ref();
const name = ref();
const email = ref();
const phone = ref();
const password = ref();

const username = ref();
const company_name = ref();
const company_reg_no = ref();
const business_nature = ref();

const countries = computed(() => store.getters["country/countries"]);
const cities = computed(() => store.getters["city/cities"]);

function fetchCountries() {
    store.dispatch("country/" + FETCH_COUNTRIES);
}

function fetchCities() {
    //console.log('Fetch cities called');
    store.dispatch("city/" + FETCH_CITIES, {
        id: country.value,
    });
}

onMounted(() => {
    fetchCountries();
});
</script>

<template>
    <div>
        <div class="my-8">
            <div>
                <Button @click="$router.back()" variant="ghost" class="p-0 text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="mr-2 lucide lucide-arrow-left">
                        <path d="m12 19-7-7 7-7" />
                        <path d="M19 12H5" />
                    </svg>
                    Back
                </Button>
            </div>
            <span class="text-3xl font-medium">New Airport</span>
        </div>
        <div class="bg-white rounded-lg p-4">
            <div class="">
                <form class="max-w-xl mx-auto p-4">
                    <div class="grid grid-cols-2 gap-8">
                        <div>
                            <div class="mb-3">
                                <Label for="name">IATA City Code:</Label>
                                <Input type="text" placeholder="Enter IATA City Code:" id="iATACityCode:" />
                            </div>

                            <div class="mb-3">
                                <Label for="name">IATA Country Code</Label>
                                <Input type="text" placeholder="Enter IATA Country Code" id="iATACountryCode" />
                            </div>

                            <div class="mb-3">
                                <Label for="name">IATA Code:</Label>
                                <Input type="text" placeholder="Enter IATA Code:" id="IATA Code:" />
                            </div>

                            <div class="mb-3">
                                <Label for="name">City Name</Label>
                                <Input type="text" placeholder="Enter City Name" id="cityName" />
                            </div>
                        </div>

                        <div>
                            <div class="mb-3">
                                <Label for="name">Latitude</Label>
                                <Input type="text" placeholder="Enter Latitude" id="latitude" />
                            </div>
                            <div class="mb-3">
                                <Label for="name">Longitude</Label>
                                <Input type="text" placeholder="Enter Longitude" id="longitude" />
                            </div>
                            <div class="mb-3">
                                <Label for="name">Timezone</Label>
                                <Input type="text" placeholder="Enter Timezone" v-model="timezone" id="timezone" />
                            </div>
                            <div class="mb-3">
                                <Label for="name">Name</Label>
                                <Input type="text" placeholder="Enter Name" id="name" />
                            </div>
                        </div>
                         
                    </div>

                    <Button type="submit">Save</Button>

                </form>
            </div>
        </div>
    </div>
</template>
