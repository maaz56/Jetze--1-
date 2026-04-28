<script setup>
import { computed, ref } from "vue";

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
import { FETCH_CURRENCIES, SAVE_UMRAH_PACKAGE } from "@/services/store/actions.type";
import Button from "@/components/ui/button/Button.vue";
import { Check, ChevronsUpDown } from "lucide-vue-next";
import { cn } from "@/lib/utils";
import router from "@/config/router";
import FileUploader from "@/components/common/FileUploader.vue";

const store = useStore();
const currencies = computed(() => store.getters["currency/currencies"] || []);

const headerImage = ref();
const title = ref();
const startingPrice = ref();
const selectedCurrency = ref();

const description = ref();

const handleFile = (event) => {
    headerImage.value = event.target.files[0];
};
function fetchCurrencies(event) {
    store.dispatch("currency/" + FETCH_CURRENCIES, {
        searchQuery: event.target.value,
    });
}
function saveUmrahPackage() {
    store
        .dispatch("umrahPackage/" + SAVE_UMRAH_PACKAGE, {
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
            <span class="text-3xl font-medium">New Umrah package</span>
        </div>
        <form
            @submit.prevent="saveUmrahPackage"
            class="bg-white rounded-lg p-4"
        >
            <div class="mb-3">
                <Label for="header-image">Header Image</Label>
                <FileUploader
                    max-files="1"
                    @file-uploaded="
                        (file) => {
                            headerImage = file;
                        }
                    "
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
                                      )?.code || "Select a currency..."
                                    : "Select a currency ..."
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
            <Button type="submit">Save</Button>
        </form>
    </div>
</template>
