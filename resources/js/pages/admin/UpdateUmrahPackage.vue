<script setup>
import { computed, onMounted, ref, watch } from "vue";

import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import Label from "@/components/ui/label/Label.vue";
import Input from "@/components/ui/input/Input.vue";
import { useStore } from "vuex";
import {
    DELETE_UMRAH_HEADER_IMAGES,
    FETCH_COUNTRIES,
    FETCH_CURRENCIES,
    FETCH_UMRAH_PACKAGES,
    UPDATE_UMRAH_PACKAGE,
} from "@/services/store/actions.type";
import Button from "@/components/ui/button/Button.vue";
import { Check, ChevronsUpDown, Trash2 } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import { useRoute, useRouter } from "vue-router";
import FileUploader from "@/components/common/FileUploader.vue";

const route = useRoute();
const router = useRouter();
const store = useStore();

const umrahPackage = computed(() =>
    store.getters["umrahPackage/umrahPackage"](route.query.umrah_package_id)
);
const currencies = computed(() => store.getters["currency/currencies"] || []);


const headerImage = ref();
const title = ref();
const startingPrice = ref();
const description = ref();
const selectedCurrency = ref();
const handleFile = (event) => {
    headerImage.value = event.target.files[0];
};

function fetchUmrahPackages() {
    store.dispatch("umrahPackage/" + FETCH_UMRAH_PACKAGES);
}

function deleteUmrahPackageHeaderImage(name) {
    store.dispatch("umrahPackage/" + DELETE_UMRAH_HEADER_IMAGES, {
        file_name: name,
    });
}
function fetchCurrencies(event) {
    store.dispatch("currency/" + FETCH_CURRENCIES, {
        searchQuery: event.target.value,
    });
}
function UpdateUmrahPackage() {
    store
        .dispatch("umrahPackage/" + UPDATE_UMRAH_PACKAGE, {
            umrah_package_id: route.query.umrah_package_id,
            header_image: headerImage.value,
            title: title.value,
            starting_price: startingPrice.value,
            description: description.value,
            currency: selectedCurrency.value,
        })
        .then(() => {
            router.push({ name: "UmrahPackages" });
        });
}

watch(
    umrahPackage,
    (newVal) => {
        if (newVal) {
            title.value = umrahPackage.value?.title;
            startingPrice.value = umrahPackage.value?.starting_price;
            description.value = umrahPackage.value?.description;
            selectedCurrency.value = umrahPackage.value?.currency;
        }
    },
    { immediate: true }
);

onMounted(() => {
    fetchUmrahPackages();
});
</script>

<template>
    <div>
        <div class="my-8">
            <div>
                <Button
                    @click="$router.back()"
                    variant="ghost"
                    class="p-0 text-muted-foreground"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="mr-2 lucide lucide-arrow-left"
                    >
                        <path d="m12 19-7-7 7-7" />
                        <path d="M19 12H5" />
                    </svg>
                    Back
                </Button>
            </div>
            <span class="text-3xl font-medium">Update Umrah package</span>
        </div>
        <form
            @submit.prevent="UpdateUmrahPackage"
            class="bg-white rounded-lg p-4"
        >
            <div class="mb-3">
                <Label for="header-image">Header Image</Label>
                <div
                    v-if="umrahPackage?.header_image"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Card class="relative w-24 h-24 overflow-hidden group">
                        <img
                            class="w-full h-full object-cover"
                            :src="umrahPackage?.header_image"
                            alt=""
                        />
                        <div
                            class="absolute top-0 left-0 bottom-0 right-0 bg-black bg-opacity-50 hidden group-hover:block cursor-pointer"
                        >
                            <div
                                class="flex items-center justify-center w-full h-full"
                            >
                                <button
                                    type="button"
                                    @click="
                                        deleteUmrahPackageHeaderImage(file.name)
                                    "
                                    class="text-white"
                                >
                                    <Trash2 class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </Card>
                </div>

                <FileUploader
                    max-files="1"
                    @file-uploaded="
                        (file) => {
                            headerImage = file;
                        }
                    "
                    :removeFiles="removeFiles"
                />
            </div>
            <div class="mb-3">
                <Label for="logo">Title</Label>
                <Input
                    type="text"
                    v-model="title"
                    placeholder="Enter your website"
                    id="logo"
                />
            </div>
            <div class="flex gap-4">
            <div class="mb-3">
                <Label for="starting-price">Starting price</Label>
                <Input
                    type="text"
                    v-model="startingPrice"
                    placeholder="Enter starting price"
                    id="starting-price"
                />
            </div>
            <div class="mb-3">
                <Label>Currency</Label>
                <Popover v-model:open="isOpenCurrencyDropdown">
                    <PopoverTrigger as-child>
                        <Button
                            variant="outline"
                            role="combobox"
                            class="w-full justify-between py-5"
                        >
                             {{
                                selectedCurrency !== ""
                                    ? currencies.find(
                                          (currency) =>
                                              currency.code === selectedCurrency
                                      )?.code || selectedCurrency
                                    : "Select a currency..."
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
                                @input="fetchCurrencies"
                                placeholder="Search currency..."
                            />
                            <CommandEmpty>No results found.</CommandEmpty>
                            <CommandList>
                                <CommandGroup>
                                    <CommandItem
                                        v-for="currency in currencies"
                                        :key="currency.code"
                                        :value="currency.code"
                                        @select="
                                            (ev) => {
                                                if (
                                                    typeof ev.detail.value ===
                                                    'string'
                                                ) {
                                                    selectedCurrency =
                                                        ev.detail.value;
                                                }
                                                open = false;
                                            }
                                        "
                                    >
                                        {{ currency.code }}
                                        <Check
                                            :class="
                                                cn(
                                                    'ml-auto h-4 w-4',
                                                    selectedCurrency ===
                                                        currency.code
                                                        ? 'opacity-100'
                                                        : 'opacity-0'
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
            <div class="mb-3">
                <Label>Description</Label>
                <QuillEditor v-model:content="description" contentType="html" />
            </div>
            <Button type="submit">Save changes</Button>
        </form>
    </div>
</template>
