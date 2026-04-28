<script setup>
import { ref } from "vue";
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
import { computed, onMounted } from "vue";
import { useStore } from "vuex";
import { SAVE_AIRLINE } from "@/services/store/actions.type";

const store = useStore();

// Form fields
const iataCode = ref("");
const icaoCode = ref("");
const name = ref("");
const logoUrl = ref("");
const carrierConditionUrl = ref("");

function saveAirline(){
    const formData = {
        iata_code: iataCode.value,
        icaoCode: icaoCode.value,
        name: name.value,
        logoUrl: logoUrl.value,
        carrierConditionUrl: carrierConditionUrl.value,
    };

    store.dispatch("airline/" + SAVE_AIRLINE, formData)
    .then(() => {
        // Handle success, e.g., show a success message or redirect
        // Optionally, reset the form fields
        iataCode.value = "";
        icaoCode.value = "";
        name.value = "";
        logoUrl.value = "";
        carrierConditionUrl.value = "";
    });
}

// Submit handler

</script>

<template>
    <div>
        <div class="my-8">
            <div>
                <Button @click="$router.back()" variant="ghost" class="px-4 text-muted-foreground">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="mr-2 lucide lucide-arrow-left">
                        <path d="m12 19-7-7 7-7" />
                        <path d="M19 12H5" />
                    </svg>
                    Back
                </Button>
            </div>
            <span class="text-3xl font-medium">New Airline</span>
        </div>
        <div class="bg-white rounded-lg p-4">
            <div class="">
                <form class="max-w-xs mx-auto p-4" @submit.prevent="saveAirline">
                    <div class="mb-3">
                        <Label for="iataCode">IATA Code:</Label>
                        <Input type="text" placeholder="Enter IATA Code:" v-model="iataCode" id="iataCode" />
                    </div>
                    <div class="mb-3">
                        <Label for="icaoCode">ICAO Code:</Label>
                        <Input type="text" placeholder="Enter ICAO Code:" v-model="icaoCode" id="icaoCode" />
                    </div>
                    <div class="mb-3">
                        <Label for="name">Name</Label>
                        <Input type="text" placeholder="Enter name" v-model="name" id="name" />
                    </div>
                    <div class="mb-3">
                        <Label for="logoUrl">Logo URL:</Label>
                        <Input type="text" placeholder="Enter Logo URL:" v-model="logoUrl" id="logoUrl" />
                    </div>
                    <div class="mb-3">
                        <Label for="carrierConditionUrl">Carrier Condition URL:</Label>
                        <Input type="text" placeholder="Enter Carrier Condition URL:" v-model="carrierConditionUrl" id="carrierConditionUrl" />
                    </div>
                    <div>
                        <Button type="submit">Save</Button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
