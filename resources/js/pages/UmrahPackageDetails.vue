<script setup>
import Nav from "@/components/shared/Nav.vue";
import {
    FETCH_UMRAH_PACKAGES,
    FETCH_VISAS,
} from "@/services/store/actions.type";
import { computed, onMounted } from "vue";
import { useStore } from "vuex";
import { useRoute } from "vue-router";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import Button from "@/components/ui/button/Button.vue";

const store = useStore();
const route = useRoute();

const umrahPackage = computed(() =>
    store.getters["umrahPackage/umrahPackage"](route.query.umrah_package_id)
);

function fetchUmrahPackages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_PACKAGES);
}

onMounted(() => {
    fetchUmrahPackages();
});
</script>

<template>
    
    <div class="h-[200px]">
        <img
            :src="umrahPackage?.header_image"
            alt=""
            class="w-full h-full object-cover"
        />
    </div>
    <section class="md:container mx-auto py-12 flex gap-4">
        <div class="w-full">
            <span class="text-3xl font-medium mr-2">{{
                umrahPackage?.country_flag
            }}</span>
            <span class="text-3xl font-medium">{{ umrahPackage?.title }}</span>

            <div v-html="umrahPackage?.description" class="mt-4"></div>
        </div>
        <div class="w-[500px] p-4 border">
            <span class="text-xl font-medium">Contact us</span>

            <div class="mt-3">
                <div class="mb-3">
                    <Label>Name</Label>
                    <Input placeholder="Enter your name" />
                </div>
                <div class="mb-3">
                    <Label>Email</Label>
                    <Input placeholder="Enter your email" />
                </div>
                <Button>Contact me</Button>
            </div>
        </div>
    </section>
</template>
